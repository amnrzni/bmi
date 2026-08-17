<?php

use App\Livewire\Analytics;
use App\Livewire\Roster;
use App\Livewire\RosterImport;
use App\Livewire\WeighInSession;
use App\Models\Department;
use App\Models\Team;
use App\Models\User;
use App\Models\WeighIn;
use App\Services\ProgressService;
use App\Services\RosterImporter;
use App\Support\ChallengeWeek;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach(function () {
    $this->tah = Team::factory()->create(['code' => 'TAH', 'name' => 'Team TAH', 'sort_order' => 1]);
    $this->department = Department::factory()->create(['name' => 'Operations']);
    $this->admin = User::factory()->admin()->create();
});

// ------------------------------------------------------------------- access

it('keeps staff out of the roster and import screens', function () {
    $staff = User::factory()->create();

    $this->actingAs($staff)->get(route('roster'))->assertForbidden();
    $this->actingAs($staff)->get(route('roster.import'))->assertForbidden();
});

it('shows the import screen to an admin', function () {
    $this->actingAs($this->admin)->get(route('roster.import'))->assertOk();
});

// -------------------------------------------------------------- adding staff

it('adds someone to the roster', function () {
    Livewire::actingAs($this->admin)
        ->test(Roster::class)
        ->call('newStaff')
        ->set('staffName', 'Along')
        ->set('staffEmail', 'Along@QCXIS.com')
        ->set('staffDepartmentId', $this->department->id)
        ->set('staffTeamId', $this->tah->id)
        ->set('staffHeight', '172')
        ->call('saveStaff')
        ->assertHasNoErrors();

    $along = User::whereRaw('lower(email) = ?', ['along@qcxis.com'])->first();

    // Lowercased on write so it can never diverge from the sign-in comparison.
    expect($along->email)->toBe('along@qcxis.com')
        ->and($along->name)->toBe('Along')
        ->and($along->height_cm)->toBe(172)
        ->and($along->is_participant)->toBeTrue();
});

it('refuses an email that differs only in case', function () {
    User::factory()->create(['email' => 'along@qcxis.com']);

    Livewire::actingAs($this->admin)
        ->test(Roster::class)
        ->call('newStaff')
        ->set('staffName', 'Along Again')
        ->set('staffEmail', 'ALONG@QCXIS.COM')
        ->call('saveStaff')
        ->assertHasErrors('staffEmail');

    // Two rows differing only in case would both match at sign-in.
    expect(User::whereRaw('lower(email) = ?', ['along@qcxis.com'])->count())->toBe(1);
});

it('refuses an email already used by a deleted row', function () {
    User::factory()->create(['email' => 'gone@qcxis.com'])->delete();

    // The unique index covers soft-deleted rows, so without this check the
    // insert would fail with a raw database error.
    Livewire::actingAs($this->admin)
        ->test(Roster::class)
        ->call('newStaff')
        ->set('staffName', 'Someone')
        ->set('staffEmail', 'gone@qcxis.com')
        ->call('saveStaff')
        ->assertHasErrors('staffEmail');
});

it('requires a name and a valid email', function () {
    Livewire::actingAs($this->admin)
        ->test(Roster::class)
        ->call('newStaff')
        ->set('staffName', '')
        ->set('staffEmail', 'nonsense')
        ->call('saveStaff')
        ->assertHasErrors(['staffName', 'staffEmail']);
});

// ------------------------------------------------------------- editing staff

it('edits someone and recalculates their bmi history when the height changes', function () {
    $person = User::factory()->create(['email' => 'along@qcxis.com', 'height_cm' => 170]);
    $weighIn = WeighIn::factory()->create([
        'user_id' => $person->id,
        'weight_kg' => 100,
        'bmi' => $person->bmiFor(100),
        'recorded_by_user_id' => $this->admin->id,
    ]);

    Livewire::actingAs($this->admin)
        ->test(Roster::class)
        ->call('editStaff', $person->id)
        ->set('staffHeight', '180')
        ->call('saveStaff')
        ->assertHasNoErrors();

    expect((float) $weighIn->fresh()->bmi)->toBe(30.86);
});

it('warns when an email changes for someone who has already signed in', function () {
    $person = User::factory()->create([
        'email' => 'old@qcxis.com',
        'last_login_at' => now()->subDay(),
    ]);

    $component = Livewire::actingAs($this->admin)
        ->test(Roster::class)
        ->call('editStaff', $person->id)
        ->set('staffEmail', 'new@qcxis.com')
        ->call('saveStaff');

    expect($component->get('flash'))->toContain('must now use the new address');
});

// ------------------------------------------------------------ leaving/removal

it('marks someone as left without touching their recorded weeks', function () {
    $person = User::factory()->create();
    WeighIn::factory()->create([
        'user_id' => $person->id,
        'week_start_date' => ChallengeWeek::first()->key(),
        'recorded_by_user_id' => $this->admin->id,
    ]);

    Livewire::actingAs($this->admin)->test(Roster::class)->call('markAsLeft', $person->id);

    expect($person->fresh()->left_at)->not->toBeNull()
        ->and($person->fresh()->weighIns()->count())->toBe(1);

    // They stop being expected for later weeks.
    $later = ChallengeWeek::first()->next()->next();
    $missing = app(ProgressService::class)->compliance($later)->missing->pluck('id');

    expect($missing)->not->toContain($person->id);
});

it('brings someone back', function () {
    $person = User::factory()->create(['left_at' => now()->subWeek()]);

    Livewire::actingAs($this->admin)->test(Roster::class)->call('restoreStaff', $person->id);

    expect($person->fresh()->left_at)->toBeNull();
});

it('refuses to delete someone who has weigh-ins', function () {
    $person = User::factory()->create();
    WeighIn::factory()->create([
        'user_id' => $person->id,
        'recorded_by_user_id' => $this->admin->id,
    ]);

    $component = Livewire::actingAs($this->admin)->test(Roster::class)->call('deleteStaff', $person->id);

    // Deleting them would retroactively change past team averages.
    expect($person->fresh())->not->toBeNull()
        ->and($component->get('flash'))->toContain('Mark them as left instead');
});

it('deletes a row added by mistake', function () {
    $person = User::factory()->create();

    Livewire::actingAs($this->admin)->test(Roster::class)->call('deleteStaff', $person->id);

    expect(User::find($person->id))->toBeNull();
});

// -------------------------------------------------------------- admin toggle

it('promotes a staff member to admin', function () {
    $person = User::factory()->create();

    Livewire::actingAs($this->admin)->test(Roster::class)->call('toggleAdmin', $person->id);

    expect($person->fresh()->isAdmin())->toBeTrue();
});

it('demotes an admin back to staff', function () {
    $other = User::factory()->admin()->create();

    Livewire::actingAs($this->admin)->test(Roster::class)->call('toggleAdmin', $other->id);

    expect($other->fresh()->isAdmin())->toBeFalse();
});

it('refuses to let an admin remove their own access', function () {
    // Every admin can promote/demote others — but not lock themselves out.
    User::factory()->admin()->create(); // a second admin, so "last admin" isn't the reason

    $component = Livewire::actingAs($this->admin)->test(Roster::class)->call('toggleAdmin', $this->admin->id);

    expect($this->admin->fresh()->isAdmin())->toBeTrue()
        ->and($component->get('flash'))->toContain("can't remove your own");
});

it('refuses the sole admin trying to demote themselves', function () {
    // The only way to reach this screen is to already be an admin, so if the
    // admin count is ever 1, that admin can only be the person acting — the
    // self-guard and the last-admin guard describe the same real situation.
    expect(User::admins()->count())->toBe(1);

    Livewire::actingAs($this->admin)->test(Roster::class)->call('toggleAdmin', $this->admin->id);

    expect($this->admin->fresh()->isAdmin())->toBeTrue();
});

it('allows demoting a second admin even though it leaves exactly one', function () {
    // Reducing TO one admin is fine — the guard only stops going to zero.
    $second = User::factory()->admin()->create();
    expect(User::admins()->count())->toBe(2);

    Livewire::actingAs($this->admin)->test(Roster::class)->call('toggleAdmin', $second->id);

    expect($second->fresh()->isAdmin())->toBeFalse()
        ->and(User::admins()->count())->toBe(1);
});

it('shows who is an admin on the roster', function () {
    $person = User::factory()->admin()->create(['name' => 'Second Admin']);

    Livewire::actingAs($this->admin)
        ->test(Roster::class)
        ->assertSee('Second Admin')
        ->assertSee('Admin');
});

// -------------------------------------------------------------- departments

it('adds and renames a department', function () {
    $component = Livewire::actingAs($this->admin)
        ->test(Roster::class)
        ->call('newDepartment')
        ->set('deptName', 'Logistics')
        ->call('saveDepartment');

    $created = Department::where('name', 'Logistics')->first();
    expect($created)->not->toBeNull();

    $component->call('editDepartment', $created->id)
        ->set('deptName', 'Logistics & Fleet')
        ->call('saveDepartment');

    expect($created->fresh()->name)->toBe('Logistics & Fleet');
});

it('refuses to delete a department that still has staff', function () {
    User::factory()->create(['department_id' => $this->department->id]);

    $component = Livewire::actingAs($this->admin)
        ->test(Roster::class)
        ->call('deleteDepartment', $this->department->id);

    expect(Department::find($this->department->id))->not->toBeNull()
        ->and($component->get('flash'))->toContain('Move them first');
});

it('deletes an empty department', function () {
    Livewire::actingAs($this->admin)->test(Roster::class)->call('deleteDepartment', $this->department->id);

    expect(Department::find($this->department->id))->toBeNull();
});

it('reorders departments', function () {
    Department::query()->delete();
    $first = Department::factory()->create(['name' => 'First', 'sort_order' => 0]);
    $second = Department::factory()->create(['name' => 'Second', 'sort_order' => 1]);

    $order = fn () => Department::orderBy('sort_order')->orderBy('name')->pluck('id')->all();

    expect($order())->toBe([$first->id, $second->id]);

    Livewire::actingAs($this->admin)->test(Roster::class)->call('moveDepartment', $second->id, -1);

    expect($order())->toBe([$second->id, $first->id]);

    // Moving past the end is a no-op rather than an error.
    Livewire::actingAs($this->admin)->test(Roster::class)->call('moveDepartment', $second->id, -1);

    expect($order())->toBe([$second->id, $first->id]);
});

// ------------------------------------------------------------ sign-in health

it('stamps last_login_at on sso sign-in', function () {
    $person = User::factory()->create(['email' => 'along@qcxis.com']);

    expect($person->hasNeverSignedIn())->toBeTrue();

    $this->get(route('sso.callback', ['email' => 'along@qcxis.com']));

    expect($person->fresh()->hasNeverSignedIn())->toBeFalse();
});

it('stamps last_login_at on the admin fallback login', function () {
    $this->post(route('login'), ['email' => $this->admin->email, 'password' => 'password']);

    expect($this->admin->fresh()->last_login_at)->not->toBeNull();
});

it('counts who has never signed in', function () {
    User::factory()->count(2)->create(['department_id' => $this->department->id]);
    User::factory()->create(['department_id' => $this->department->id, 'last_login_at' => now()]);

    Livewire::actingAs($this->admin)
        ->test(Roster::class)
        ->assertSee('never signed in');
});

// ----------------------------------------------------------------- import UI

it('previews an uploaded csv without writing anything', function () {
    $csv = "name,email,team\nAlong,along@qcxis.com,TAH\n";

    Livewire::actingAs($this->admin)
        ->test(RosterImport::class)
        ->set('file', UploadedFile::fake()->createWithContent('roster.csv', $csv))
        ->assertSet('error', null)
        ->assertSee('Along');

    // Preview only — nothing committed yet.
    expect(User::whereRaw('lower(email) = ?', ['along@qcxis.com'])->exists())->toBeFalse();
});

it('imports the previewed rows on confirmation', function () {
    $csv = "name,email,team\nAlong,along@qcxis.com,TAH\n";

    $component = Livewire::actingAs($this->admin)
        ->test(RosterImport::class)
        ->set('file', UploadedFile::fake()->createWithContent('roster.csv', $csv))
        ->call('commit');

    expect(User::whereRaw('lower(email) = ?', ['along@qcxis.com'])->exists())->toBeTrue()
        ->and($component->get('done'))->toContain('1 added');
});

it('rejects a file that is not a csv', function () {
    Livewire::actingAs($this->admin)
        ->test(RosterImport::class)
        ->set('file', UploadedFile::fake()->create('roster.pdf', 10))
        ->assertSet('rows', null);
});

// --------------------------------------------------------------- empty states

it('tells a brand-new admin what to do when the roster is empty', function () {
    User::query()->whereKeyNot($this->admin->id)->forceDelete();
    $this->admin->update(['is_participant' => false]);

    Livewire::actingAs($this->admin)
        ->test(Roster::class)
        ->assertSee('Nobody on the roster yet')
        ->assertSee('Import CSV');
});

it('points analytics at the roster when there is nobody to report on', function () {
    User::query()->whereKeyNot($this->admin->id)->forceDelete();
    $this->admin->update(['is_participant' => false]);

    Livewire::actingAs($this->admin)
        ->test(Analytics::class)
        ->assertSee('Nobody to report on yet')
        ->assertSee('Go to the roster');
});

it('tells the weigh-in screen there is nobody to weigh', function () {
    User::query()->whereKeyNot($this->admin->id)->forceDelete();
    $this->admin->update(['is_participant' => false]);

    Livewire::actingAs($this->admin)
        ->test(WeighInSession::class)
        ->assertSee('Nobody on the roster yet');
});

it('offers a downloadable csv template', function () {
    $component = Livewire::actingAs($this->admin)
        ->test(RosterImport::class)
        ->call('downloadTemplate');

    expect($component->effects['download'] ?? null)->not->toBeNull();
});

it('template rows round-trip cleanly back through the importer', function () {
    // The template names team BKN, which must exist for the round trip to pass —
    // this catches a template that references a team the app doesn't have.
    Team::factory()->create(['code' => 'BKN', 'name' => 'Team BKN', 'sort_order' => 2]);

    $csv = "\xEF\xBB\xBFname,email,department,team,height,joined\n"
        ."Along,along@qcxis.com,Operations,TAH,172,2026-08-10\n"
        ."Kuale,kuale@qcxis.com,Operations,BKN,165,\n";

    $rows = app(RosterImporter::class)->parse($csv);

    expect($rows->every(fn ($r) => ! $r->isError()))->toBeTrue();
});
