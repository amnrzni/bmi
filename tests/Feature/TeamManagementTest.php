<?php

use App\Livewire\Roster;
use App\Models\Department;
use App\Models\Team;
use App\Models\User;
use App\Models\WeighIn;
use App\Services\ProgressService;
use App\Support\ChallengeWeek;
use Livewire\Livewire;

beforeEach(function () {
    $this->department = Department::factory()->create(['name' => 'Operations']);
    $this->admin = User::factory()->admin()->create();
});

// -------------------------------------------------------------- code helper

it('derives a short code from a team name', function () {
    expect(Team::codeFor('Harimau'))->toBe('HAR')
        ->and(Team::codeFor('Naga Api'))->toBe('NAG');
});

it('makes the derived code unique', function () {
    Team::create(['name' => 'Harimau', 'code' => Team::codeFor('Harimau'), 'sort_order' => 1]);

    // "Harimau Muda" would also want HAR — it must not collide.
    expect(Team::codeFor('Harimau Muda'))->toBe('HAR2');
});

it('falls back to a placeholder code for a name with no letters', function () {
    expect(Team::codeFor('!!!'))->toBe('TM');
});

// ---------------------------------------------------------------------- CRUD

it('creates a team from a name, deriving its code', function () {
    Livewire::actingAs($this->admin)
        ->test(Roster::class)
        ->call('newTeam')
        ->set('teamName', 'Harimau')
        ->call('saveTeam');

    $team = Team::firstWhere('name', 'Harimau');

    expect($team)->not->toBeNull()
        ->and($team->code)->toBe('HAR');
});

it('renames a team and re-derives its code without colliding with itself', function () {
    $team = Team::create(['name' => 'Harimau', 'code' => 'HAR', 'sort_order' => 1]);

    Livewire::actingAs($this->admin)
        ->test(Roster::class)
        ->call('editTeam', $team->id)
        ->set('teamName', 'Helang')
        ->call('saveTeam');

    expect($team->fresh()->name)->toBe('Helang')
        ->and($team->fresh()->code)->toBe('HEL');
});

it('refuses to delete a team that still has members', function () {
    $team = Team::create(['name' => 'Harimau', 'code' => 'HAR', 'sort_order' => 1]);
    User::factory()->onTeam($team)->create();

    $component = Livewire::actingAs($this->admin)
        ->test(Roster::class)
        ->call('deleteTeam', $team->id);

    // Deleting a team with members would silently unassign them.
    expect(Team::find($team->id))->not->toBeNull()
        ->and($component->get('flash'))->toContain('Move them first');
});

it('deletes an empty team', function () {
    $team = Team::create(['name' => 'Empty', 'code' => 'EMP', 'sort_order' => 1]);

    Livewire::actingAs($this->admin)->test(Roster::class)->call('deleteTeam', $team->id);

    expect(Team::find($team->id))->toBeNull();
});

it('reorders teams', function () {
    $first = Team::create(['name' => 'First', 'code' => 'FIR', 'sort_order' => 0]);
    $second = Team::create(['name' => 'Second', 'code' => 'SEC', 'sort_order' => 1]);

    $order = fn () => Team::orderBy('sort_order')->orderBy('name')->pluck('id')->all();

    Livewire::actingAs($this->admin)->test(Roster::class)->call('moveTeam', $second->id, -1);

    expect($order())->toBe([$second->id, $first->id]);
});

it('keeps staff out of the roster component entirely', function () {
    // The component self-guards at mount, so no team (or staff, or department)
    // method is reachable by a non-admin even via a direct Livewire call.
    Livewire::actingAs(User::factory()->create())
        ->test(Roster::class)
        ->assertForbidden();
});

// -------------------------------------------------------------- assignment

it('assigns a person to a team from the dropdown, including a string id', function () {
    $team = Team::create(['name' => 'Harimau', 'code' => 'HAR', 'sort_order' => 1]);
    $person = User::factory()->create();

    // The <select> sends a string; setTeam must accept it.
    Livewire::actingAs($this->admin)
        ->test(Roster::class)
        ->call('setTeam', $person->id, (string) $team->id);

    expect($person->fresh()->team_id)->toBe($team->id);
});

it('unassigns a person when the dropdown sends an empty value', function () {
    $team = Team::create(['name' => 'Harimau', 'code' => 'HAR', 'sort_order' => 1]);
    $person = User::factory()->onTeam($team)->create();

    Livewire::actingAs($this->admin)
        ->test(Roster::class)
        ->call('setTeam', $person->id, '');

    expect($person->fresh()->team_id)->toBeNull();
});

// -------------------------------------------------- ranked standings (3+ teams)

it('ranks three or more teams by percent change, most loss first', function () {
    $service = app(ProgressService::class);
    $recorder = User::factory()->admin()->nonParticipant()->create();

    // Three teams with clearly different average losses.
    $make = function (string $name, float $from, float $to) use ($recorder) {
        $team = Team::create(['name' => $name, 'code' => Team::codeFor($name), 'sort_order' => 1]);
        $user = User::factory()->onTeam($team)->create();
        foreach ([1 => $from, 2 => $to] as $week => $kg) {
            WeighIn::factory()->create([
                'user_id' => $user->id,
                'week_start_date' => ChallengeWeek::fromNumber($week)->key(),
                'weight_kg' => $kg,
                'recorded_by_user_id' => $recorder->id,
            ]);
        }

        return $team;
    };

    $best = $make('Best', 100, 90);   // -10%
    $mid = $make('Mid', 100, 95);     // -5%
    $worst = $make('Worst', 100, 99); // -1%

    $standings = Team::all()
        ->map(fn (Team $t) => $service->teamStanding($t))
        ->sortBy([
            fn ($s) => $s->hasData() ? 0 : 1,
            fn ($s) => $s->averagePercentChange ?? INF,
        ])
        ->values();

    expect($standings->pluck('team.name')->all())->toBe(['Best', 'Mid', 'Worst']);
});

it('sinks teams with no data to the bottom of the standings', function () {
    $service = app(ProgressService::class);
    $recorder = User::factory()->admin()->nonParticipant()->create();

    $withData = Team::create(['name' => 'Active', 'code' => 'ACT', 'sort_order' => 1]);
    $user = User::factory()->onTeam($withData)->create();
    foreach ([1 => 100.0, 2 => 97.0] as $week => $kg) {
        WeighIn::factory()->create([
            'user_id' => $user->id,
            'week_start_date' => ChallengeWeek::fromNumber($week)->key(),
            'weight_kg' => $kg,
            'recorded_by_user_id' => $recorder->id,
        ]);
    }

    $empty = Team::create(['name' => 'Quiet', 'code' => 'QUI', 'sort_order' => 2]);
    User::factory()->onTeam($empty)->create(); // a member, but no weigh-ins

    $standings = Team::all()
        ->map(fn (Team $t) => $service->teamStanding($t))
        ->sortBy([
            fn ($s) => $s->hasData() ? 0 : 1,
            fn ($s) => $s->averagePercentChange ?? INF,
        ])
        ->values();

    expect($standings->first()->team->name)->toBe('Active')
        ->and($standings->last()->team->name)->toBe('Quiet');
});

it('renders the standings leaderboard on the home page', function () {
    $team = Team::create(['name' => 'Harimau', 'code' => 'HAR', 'sort_order' => 1]);
    $staff = User::factory()->onTeam($team)->create();

    $this->actingAs($staff)->get(route('home'))
        ->assertOk()
        ->assertSee('Team standings')
        ->assertSee('Harimau');
});
