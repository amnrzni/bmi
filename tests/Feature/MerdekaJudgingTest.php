<?php

use App\Livewire\Merdeka\Judging;
use App\Livewire\Merdeka\Results;
use App\Models\Department;
use App\Models\MerdekaJudge;
use App\Models\MerdekaScore;
use App\Models\User;
use App\Support\MerdekaRubric;
use Livewire\Livewire;

/** A 1x1 PNG, which is what the canvas produces the shape of. */
function signature(): string
{
    return 'data:image/png;base64,'.base64_encode(base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    ));
}

/** @return array<string, int> */
function bands(int $band = 5): array
{
    return array_fill_keys(MerdekaRubric::ids(), $band);
}

beforeEach(function () {
    $this->rm = Department::factory()->create(['name' => 'Resource Management', 'sort_order' => 1]);
    $this->marketing = Department::factory()->create(['name' => 'Marketing', 'sort_order' => 2]);

    $this->founder = User::factory()->create(['name' => 'Founder', 'is_participant' => false]);
    MerdekaJudge::create(['user_id' => $this->founder->id, 'title' => 'Pengasas', 'sort_order' => 1]);

    $this->admin = User::factory()->admin()->create();
    $this->staff = User::factory()->create();
});

// ------------------------------------------------------------------- access

it('lets a judge open the scoring screen', function () {
    $this->actingAs($this->founder)->get(route('merdeka.judging'))->assertOk();
});

it('keeps ordinary staff out of both merdeka screens', function () {
    $this->actingAs($this->staff)->get(route('merdeka.judging'))->assertForbidden();
    $this->actingAs($this->staff)->get(route('merdeka.results'))->assertForbidden();
});

// An admin runs the contest; they don't get to file a signed sheet as a judge.
it('keeps an admin who is not on the panel off the scoring screen', function () {
    $this->actingAs($this->admin)->get(route('merdeka.judging'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('merdeka.results'))->assertOk();
});

it('keeps a judge out of the results screen', function () {
    $this->actingAs($this->founder)->get(route('merdeka.results'))->assertForbidden();
});

it('does not send an unconsented judge to the consent gate', function () {
    $judge = User::factory()->create(['is_participant' => true, 'consented_at' => null]);
    MerdekaJudge::create(['user_id' => $judge->id]);

    $this->actingAs($judge)->get(route('merdeka.judging'))->assertOk();
});

// ------------------------------------------------------------------ scoring

it('files a scoresheet and computes the total server-side', function () {
    Livewire::actingAs($this->founder)
        ->test(Judging::class)
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
        ->and($score->user_id)->toBe($this->founder->id)
        ->and($score->department_id)->toBe($this->rm->id);
});

it('refuses a sheet with a criterion left blank', function () {
    Livewire::actingAs($this->founder)
        ->test(Judging::class)
        ->call('open', $this->rm->id)
        ->set('scales', ['tema' => 5, 'kreativiti' => 4, 'persembahan' => 4, 'kos' => 3, 'kekemasan' => 5])
        ->set('signature', signature())
        ->call('submit')
        ->assertHasErrors('scales.impak');

    expect(MerdekaScore::count())->toBe(0);
});

it('refuses a sheet with no signature', function () {
    Livewire::actingAs($this->founder)
        ->test(Judging::class)
        ->call('open', $this->rm->id)
        ->set('scales', bands())
        ->call('submit')
        ->assertHasErrors('signature');

    expect(MerdekaScore::count())->toBe(0);
});

it('refuses a signature that is well formed but not a png', function () {
    Livewire::actingAs($this->founder)
        ->test(Judging::class)
        ->call('open', $this->rm->id)
        ->set('scales', bands())
        ->set('signature', 'data:image/png;base64,'.base64_encode('not actually a png'))
        ->call('submit')
        ->assertHasErrors('signature');

    expect(MerdekaScore::count())->toBe(0);
});

// A band outside 1–5 can only come from a hand-rolled request.
it('refuses a band outside the scale', function () {
    Livewire::actingAs($this->founder)
        ->test(Judging::class)
        ->call('open', $this->rm->id)
        ->set('scales', [...bands(), 'tema' => 9])
        ->set('signature', signature())
        ->call('submit')
        ->assertHasErrors('scales.tema');

    expect(MerdekaScore::count())->toBe(0);
});

// ------------------------------------------------------------------ locking

it('will not reopen a department the judge has already scored', function () {
    MerdekaScore::create([
        'user_id' => $this->founder->id,
        'department_id' => $this->rm->id,
        'scales' => bands(),
        'total' => 100,
        'signature' => signature(),
        'submitted_at' => now(),
    ]);

    Livewire::actingAs($this->founder)
        ->test(Judging::class)
        ->call('open', $this->rm->id)
        ->assertSet('openDepartmentId', null)
        ->assertSee('tidak boleh diubah');
});

it('does not write a second sheet for the same department', function () {
    $component = Livewire::actingAs($this->founder)
        ->test(Judging::class)
        ->call('open', $this->rm->id)
        ->set('scales', bands())
        ->set('signature', signature());

    MerdekaScore::create([
        'user_id' => $this->founder->id,
        'department_id' => $this->rm->id,
        'scales' => bands(3),
        'total' => 60,
        'signature' => signature(),
        'submitted_at' => now(),
    ]);

    $component->call('submit')->assertHasNoErrors();

    expect(MerdekaScore::count())->toBe(1)
        ->and((float) MerdekaScore::sole()->total)->toBe(60.0);
});

// ---------------------------------------------------------------- isolation

it('shows a judge their own totals and never another judge\'s', function () {
    $other = User::factory()->create(['name' => 'Co-Founder']);
    MerdekaJudge::create(['user_id' => $other->id, 'title' => 'Pengasas Bersama']);

    MerdekaScore::create([
        'user_id' => $other->id, 'department_id' => $this->rm->id,
        'scales' => bands(4), 'total' => 80, 'signature' => signature(), 'submitted_at' => now(),
    ]);
    MerdekaScore::create([
        'user_id' => $this->founder->id, 'department_id' => $this->marketing->id,
        'scales' => bands(3), 'total' => 60, 'signature' => signature(), 'submitted_at' => now(),
    ]);

    Livewire::actingAs($this->founder)
        ->test(Judging::class)
        // Their own Marketing sheet is done; Resource Management is still open
        // to them even though the other judge has filed one.
        ->assertSee('60')
        ->assertDontSee('80')
        ->assertSee('Beri Markah');
});

// ------------------------------------------------------------------ results

it('averages every filed sheet for a department', function () {
    $other = User::factory()->create(['name' => 'Co-Founder']);
    MerdekaJudge::create(['user_id' => $other->id]);

    foreach ([[$this->founder, 78], [$other, 91]] as [$judge, $total]) {
        MerdekaScore::create([
            'user_id' => $judge->id, 'department_id' => $this->rm->id,
            'scales' => bands(), 'total' => $total, 'signature' => signature(), 'submitted_at' => now(),
        ]);
    }

    Livewire::actingAs($this->admin)
        ->test(Results::class)
        ->assertSee('84.5')
        ->assertSee('2 / 4');
});

it('adds and removes a judge without touching their filed sheets', function () {
    $co = User::factory()->create(['name' => 'Co-Founder']);

    $component = Livewire::actingAs($this->admin)
        ->test(Results::class)
        ->set('newJudgeUserId', $co->id)
        ->set('newJudgeTitle', 'Pengasas Bersama')
        ->call('addJudge')
        ->assertHasNoErrors();

    expect(MerdekaJudge::where('user_id', $co->id)->exists())->toBeTrue();

    MerdekaScore::create([
        'user_id' => $co->id, 'department_id' => $this->rm->id,
        'scales' => bands(), 'total' => 100, 'signature' => signature(), 'submitted_at' => now(),
    ]);

    $component->call('removeJudge', MerdekaJudge::where('user_id', $co->id)->value('id'));

    expect(MerdekaJudge::where('user_id', $co->id)->exists())->toBeFalse()
        ->and(MerdekaScore::count())->toBe(1);
});

it('keeps an off-panel judge\'s sheet in the average', function () {
    $gone = User::factory()->create(['name' => 'Bekas Hakim']);

    MerdekaScore::create([
        'user_id' => $gone->id, 'department_id' => $this->rm->id,
        'scales' => bands(), 'total' => 100, 'signature' => signature(), 'submitted_at' => now(),
    ]);

    Livewire::actingAs($this->admin)
        ->test(Results::class)
        ->assertSee('100')
        ->assertSee('bukan lagi ahli panel');
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
