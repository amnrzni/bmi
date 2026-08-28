<?php

use App\Livewire\Merdeka\Judging;
use App\Livewire\Merdeka\Login;
use App\Livewire\Merdeka\Results;
use App\Models\Department;
use App\Models\MerdekaJudge;
use App\Models\MerdekaScore;
use App\Models\User;
use App\Support\MerdekaRubric;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

/** A 1x1 PNG, which is the shape the signature canvas produces. */
function signature(): string
{
    return 'data:image/png;base64,'
        .'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
}

/** @return array<string, int> */
function bands(int $band = 5): array
{
    return array_fill_keys(MerdekaRubric::ids(), $band);
}

function sheetFor(MerdekaJudge $judge, Department $department, float $total): MerdekaScore
{
    return MerdekaScore::create([
        'merdeka_judge_id' => $judge->id,
        'department_id' => $department->id,
        'scales' => bands(),
        'total' => $total,
        'signature' => signature(),
        'submitted_at' => now(),
    ]);
}

beforeEach(function () {
    RateLimiter::clear('merdeka-login:127.0.0.1');

    $this->rm = Department::factory()->create(['name' => 'Resource Management', 'sort_order' => 1]);
    $this->marketing = Department::factory()->create(['name' => 'Marketing', 'sort_order' => 2]);

    $this->founder = MerdekaJudge::create([
        'name' => 'Founder', 'email' => 'founder@qcxis.com', 'title' => 'Pengasas', 'sort_order' => 1,
    ]);

    $this->admin = User::factory()->admin()->create();
});

/** Puts a judge in the session the way the sign-in screen does. */
function asJudge(MerdekaJudge $judge): void
{
    session([MerdekaJudge::SESSION_KEY => $judge->id]);
}

// -------------------------------------------------------------- signing in

it('signs a judge in on a listed email', function () {
    Livewire::test(Login::class)
        // Case and surrounding space must not matter; the column is lowercased.
        ->set('email', '  Founder@QCXIS.com ')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('merdeka.judging'));

    expect(session(MerdekaJudge::SESSION_KEY))->toBe($this->founder->id);
});

it('refuses an email that is not on the panel', function () {
    Livewire::test(Login::class)
        ->set('email', 'stranger@qcxis.com')
        ->call('submit')
        ->assertHasErrors('email');

    expect(session()->has(MerdekaJudge::SESSION_KEY))->toBeFalse();
});

it('refuses a malformed email without spending an attempt', function () {
    Livewire::test(Login::class)
        ->set('email', 'not-an-email')
        ->call('submit')
        ->assertHasErrors('email');

    expect(RateLimiter::attempts('merdeka-login:127.0.0.1'))->toBe(0);
});

// The email is the entire credential, so an unlimited guess rate would let
// anyone walk a list of addresses until one opened the panel.
it('throttles repeated wrong emails', function () {
    foreach (range(1, 10) as $attempt) {
        Livewire::test(Login::class)->set('email', "guess{$attempt}@qcxis.com")->call('submit');
    }

    Livewire::test(Login::class)
        ->set('email', 'founder@qcxis.com')
        ->call('submit')
        ->assertHasErrors('email');

    expect(session()->has(MerdekaJudge::SESSION_KEY))->toBeFalse();
});

it('sends a signed-out visitor to the sign-in screen', function () {
    $this->get(route('merdeka.judging'))->assertRedirect(route('merdeka.login'));
});

it('turns a removed judge out mid-session', function () {
    asJudge($this->founder);
    $this->founder->delete();

    $this->get(route('merdeka.judging'))->assertRedirect(route('merdeka.login'));
    expect(session()->has(MerdekaJudge::SESSION_KEY))->toBeFalse();
});

it('lets a signed-in judge open the scoring screen', function () {
    asJudge($this->founder);

    $this->get(route('merdeka.judging'))->assertOk();
});

it('signs a judge out', function () {
    asJudge($this->founder);

    Livewire::test(Judging::class)->call('logout')->assertRedirect(route('merdeka.login'));

    expect(session()->has(MerdekaJudge::SESSION_KEY))->toBeFalse();
});

// -------------------------------------------------------------- admin side

it('sends a signed-out visitor from the results screen to the app login', function () {
    $this->get(route('merdeka.results'))->assertRedirect(route('login'));
});

it('keeps non-admin staff off the results screen', function () {
    $this->actingAs(User::factory()->create())->get(route('merdeka.results'))->assertForbidden();
});

// Being a judge is not app auth, and grants nothing on the admin side.
it('does not let a judge session reach the results screen', function () {
    asJudge($this->founder);

    $this->get(route('merdeka.results'))->assertRedirect(route('login'));
});

it('shows an admin the results screen', function () {
    $this->actingAs($this->admin)->get(route('merdeka.results'))->assertOk();
});

// ------------------------------------------------------------------ scoring

it('files a scoresheet and computes the total server-side', function () {
    asJudge($this->founder);

    Livewire::test(Judging::class)
        ->call('open', $this->rm->id)
        ->set('scales', ['tema' => 5, 'kreativiti' => 4, 'persembahan' => 4, 'kos' => 3, 'kekemasan' => 5, 'impak' => 2])
        ->set('ulasan', '  Sudut yang kemas.  ')
        ->set('signature', signature())
        ->call('submit')
        ->assertHasNoErrors();

    $score = MerdekaScore::sole();

    // 20 + 16 + 16 + 12 + 10 + 4
    expect((float) $score->total)->toBe(78.0)
        ->and($score->scales['tema'])->toBe(5)
        ->and($score->ulasan)->toBe('Sudut yang kemas.')
        ->and($score->merdeka_judge_id)->toBe($this->founder->id)
        ->and($score->department_id)->toBe($this->rm->id);
});

it('refuses a sheet with a criterion left blank', function () {
    asJudge($this->founder);

    Livewire::test(Judging::class)
        ->call('open', $this->rm->id)
        ->set('scales', ['tema' => 5, 'kreativiti' => 4, 'persembahan' => 4, 'kos' => 3, 'kekemasan' => 5])
        ->set('signature', signature())
        ->call('submit')
        ->assertHasErrors('scales.impak');

    expect(MerdekaScore::count())->toBe(0);
});

it('refuses a sheet with no signature', function () {
    asJudge($this->founder);

    Livewire::test(Judging::class)
        ->call('open', $this->rm->id)
        ->set('scales', bands())
        ->call('submit')
        ->assertHasErrors('signature');

    expect(MerdekaScore::count())->toBe(0);
});

it('refuses a signature that is well formed but not a png', function () {
    asJudge($this->founder);

    Livewire::test(Judging::class)
        ->call('open', $this->rm->id)
        ->set('scales', bands())
        ->set('signature', 'data:image/png;base64,'.base64_encode('not actually a png'))
        ->call('submit')
        ->assertHasErrors('signature');

    expect(MerdekaScore::count())->toBe(0);
});

// A band outside 1–5 can only come from a hand-rolled request.
it('refuses a band outside the scale', function () {
    asJudge($this->founder);

    Livewire::test(Judging::class)
        ->call('open', $this->rm->id)
        ->set('scales', [...bands(), 'tema' => 9])
        ->set('signature', signature())
        ->call('submit')
        ->assertHasErrors('scales.tema');

    expect(MerdekaScore::count())->toBe(0);
});

// ------------------------------------------------------------------ locking

it('will not reopen a department the judge has already scored', function () {
    asJudge($this->founder);
    sheetFor($this->founder, $this->rm, 100);

    Livewire::test(Judging::class)
        ->call('open', $this->rm->id)
        ->assertSet('openDepartmentId', null)
        ->assertSee('tidak boleh diubah');
});

it('does not write a second sheet for the same department', function () {
    asJudge($this->founder);

    $component = Livewire::test(Judging::class)
        ->call('open', $this->rm->id)
        ->set('scales', bands())
        ->set('signature', signature());

    sheetFor($this->founder, $this->rm, 60);

    $component->call('submit')->assertHasNoErrors();

    expect(MerdekaScore::count())->toBe(1)
        ->and((float) MerdekaScore::sole()->total)->toBe(60.0);
});

// ---------------------------------------------------------------- isolation

it('shows a judge their own totals and never another judge\'s', function () {
    $co = MerdekaJudge::create(['name' => 'Co-Founder', 'email' => 'co@qcxis.com']);

    sheetFor($co, $this->rm, 80);
    sheetFor($this->founder, $this->marketing, 60);

    asJudge($this->founder);

    Livewire::test(Judging::class)
        // Asserted on the data rather than the rendered text: Livewire embeds a
        // random checksum in the snapshot, so a bare assertDontSee('80') passes
        // or fails depending on that hash.
        ->assertViewHas('submitted', fn ($submitted) => $submitted->keys()->all() === [$this->marketing->id]
            && (float) $submitted[$this->marketing->id] === 60.0)
        // Resource Management is still open to them even though the other judge
        // has already filed one for it.
        ->assertSee('Beri Markah');
});

// ------------------------------------------------------------------ results

it('averages every filed sheet for a department', function () {
    $co = MerdekaJudge::create(['name' => 'Co-Founder', 'email' => 'co@qcxis.com']);

    sheetFor($this->founder, $this->rm, 78);
    sheetFor($co, $this->rm, 91);

    Livewire::actingAs($this->admin)
        ->test(Results::class)
        ->assertSee('84.5')
        ->assertSee('2 / 4');
});

// ------------------------------------------------------------- the panel

it('seats a judge from a name and an email', function () {
    Livewire::actingAs($this->admin)
        ->test(Results::class)
        ->set('newJudgeName', 'Co-Founder')
        ->set('newJudgeEmail', '  CoFounder@QCXIS.com ')
        ->set('newJudgeTitle', 'Pengasas Bersama')
        ->call('addJudge')
        ->assertHasNoErrors();

    $co = MerdekaJudge::where('email', 'cofounder@qcxis.com')->sole();

    expect($co->name)->toBe('Co-Founder')
        ->and($co->title)->toBe('Pengasas Bersama');

    // And that email is immediately the credential.
    Livewire::test(Login::class)
        ->set('email', 'cofounder@qcxis.com')
        ->call('submit')
        ->assertHasNoErrors();
});

it('refuses an email already seated', function () {
    Livewire::actingAs($this->admin)
        ->test(Results::class)
        ->set('newJudgeName', 'Founder Again')
        ->set('newJudgeEmail', 'FOUNDER@qcxis.com')
        ->call('addJudge')
        ->assertSee('sudah berada dalam panel');

    expect(MerdekaJudge::count())->toBe(1);
});

it('refuses a malformed judge email', function () {
    Livewire::actingAs($this->admin)
        ->test(Results::class)
        ->set('newJudgeName', 'Someone')
        ->set('newJudgeEmail', 'not-an-email')
        ->call('addJudge')
        ->assertHasErrors('newJudgeEmail');

    expect(MerdekaJudge::count())->toBe(1);
});

it('removes a judge who has filed nothing', function () {
    Livewire::actingAs($this->admin)
        ->test(Results::class)
        ->call('removeJudge', $this->founder->id);

    expect(MerdekaJudge::count())->toBe(0);
});

// Removing them would take signed sheets with them and move a corner's average.
it('refuses to remove a judge who has filed a sheet', function () {
    sheetFor($this->founder, $this->rm, 100);

    Livewire::actingAs($this->admin)
        ->test(Results::class)
        ->call('removeJudge', $this->founder->id)
        ->assertSee('tidak boleh dikeluarkan');

    expect(MerdekaJudge::count())->toBe(1)
        ->and(MerdekaScore::count())->toBe(1);
});

// ----------------------------------------------------------------- the rubric

it('weights the criteria to exactly 100', function () {
    expect(array_sum(array_column(MerdekaRubric::CRITERIA, 'weight')))->toBe(100)
        ->and(MerdekaRubric::total(bands()))->toBe(100.0);
});

it('scores nothing for a criterion a tampered payload omits or corrupts', function () {
    expect(MerdekaRubric::total(['tema' => 5]))->toBe(20.0)
        ->and(MerdekaRubric::total([...bands(), 'tema' => 99]))->toBe(80.0)
        ->and(MerdekaRubric::total([...bands(), 'nonsense' => 5]))->toBe(100.0);
});
