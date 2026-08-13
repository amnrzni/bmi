<?php

use App\Livewire\Roster;
use App\Livewire\RosterImport;
use App\Models\Department;
use App\Models\Team;
use App\Models\User;
use App\Models\WeighIn;
use App\Services\ProgressService;
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
