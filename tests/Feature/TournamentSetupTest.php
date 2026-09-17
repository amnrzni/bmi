<?php

use App\Enums\Division;
use App\Livewire\Tournaments\Index;
use App\Livewire\Tournaments\Setup;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use App\Models\TournamentScore;
use App\Models\User;
use App\Services\TournamentRecorder;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->tournament = tournamentWith(['Alpha', 'Bravo']);
    $this->alpha = $this->tournament->teams()->where('name', 'Alpha')->sole();
    $this->bravo = $this->tournament->teams()->where('name', 'Bravo')->sole();

    $this->setup = fn () => Livewire::actingAs($this->admin)->test(Setup::class, ['tournament' => $this->tournament]);
});

// ------------------------------------------------------------- tournaments

it('lets an admin start a tournament and sends them to set it up', function () {
    $page = Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('newTournament')
        ->set('name', '  Kudeta Bola Baling ')
        ->set('startsAt', '2026-09-19T14:30')
        ->call('create');

    $tournament = Tournament::firstWhere('name', 'Kudeta Bola Baling');

    expect($tournament->slug)->toBe('kudeta-bola-baling')
        ->and($tournament->created_by_user_id)->toBe($this->admin->id);

    $page->assertRedirect(route('tournaments.setup', $tournament));
});

it('gives a same-named tournament its own link, and keeps the link through a rename', function () {
    expect(Tournament::slugFor('Test Cup'))->toBe('test-cup-2');

    ($this->setup)()->set('name', 'Renamed Cup')->call('saveDetails')->assertHasNoErrors();

    expect($this->tournament->fresh()->slug)->toBe('test-cup');
});

it('stops staff starting a tournament', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(Index::class)
        ->call('create')
        ->assertForbidden();
});

// ------------------------------------------------------------------- teams

it('adds and renames teams, refusing a duplicate name', function () {
    $page = ($this->setup)()
        ->set('newTeamName', 'Charlie')
        ->call('addTeam')
        ->assertHasNoErrors()
        ->set('newTeamName', 'Alpha')
        ->call('addTeam')
        ->assertHasErrors('newTeamName');

    expect($this->tournament->teams()->pluck('name')->all())->toBe(['Alpha', 'Bravo', 'Charlie']);

    $page->set("teamNames.{$this->alpha->id}", 'Bravo')
        ->call('renameTeam', $this->alpha->id)
        ->assertHasErrors("teamNames.{$this->alpha->id}")
        ->set("teamNames.{$this->alpha->id}", 'Aces')
        ->call('renameTeam', $this->alpha->id);

    expect($this->alpha->fresh()->name)->toBe('Aces');
});

it('takes a removed team’s squad, matches and scores with it', function () {
    $fixture = $this->tournament->fixtures()->sole();
    app(TournamentRecorder::class)->recordScore($fixture, Division::Men, 1, 0, $this->admin);
    $this->alpha->players()->create([
        'tournament_id' => $this->tournament->id,
        'user_id' => User::factory()->create()->id,
        'division' => Division::Men,
    ]);

    ($this->setup)()->call('removeTeam', $this->alpha->id);

    expect($this->tournament->teams()->count())->toBe(1)
        ->and($this->tournament->fixtures()->count())->toBe(0)
        ->and(TournamentScore::count())->toBe(0)
        ->and(TournamentPlayer::count())->toBe(0);
});

// ----------------------------------------------------------------- players

it('places roster members in a squad, once per tournament', function () {
    $person = User::factory()->create();

    ($this->setup)()
        ->set("newPlayers.{$this->alpha->id}.user_id", (string) $person->id)
        ->set("newPlayers.{$this->alpha->id}.division", 'women')
        ->call('addPlayer', $this->alpha->id)
        ->assertHasNoErrors()
        ->set("newPlayers.{$this->bravo->id}.user_id", (string) $person->id)
        ->call('addPlayer', $this->bravo->id)
        ->assertHasErrors("newPlayers.{$this->bravo->id}");

    $player = TournamentPlayer::sole();

    expect($player->tournament_team_id)->toBe($this->alpha->id)
        ->and($player->division)->toBe(Division::Women);
});

it("won't place someone who has left the roster", function () {
    $leaver = User::factory()->leftOn('2026-09-01')->create();

    ($this->setup)()
        ->set("newPlayers.{$this->alpha->id}.user_id", (string) $leaver->id)
        ->call('addPlayer', $this->alpha->id)
        ->assertHasErrors("newPlayers.{$this->alpha->id}");

    expect(TournamentPlayer::count())->toBe(0);
});

// ---------------------------------------------------------------- fixtures

it('adds a match, changes its teams, and refuses a team playing itself', function () {
    $charlie = $this->tournament->teams()->create(['name' => 'Charlie', 'sort_order' => 3]);

    // New matches pair the first two teams: Alpha v Bravo.
    $page = ($this->setup)()->call('addFixture');
    $fixture = $this->tournament->fixtures()->where('number', 2)->sole();

    $page->call('updateFixture', $fixture->id, 'home', (string) $this->bravo->id);
    expect($fixture->fresh()->home_team_id)->toBe($this->alpha->id);

    $page->call('updateFixture', $fixture->id, 'home', (string) $charlie->id)
        ->call('updateFixture', $fixture->id, 'away', (string) $this->alpha->id)
        ->call('updateFixture', $fixture->id, 'home', (string) $this->bravo->id);

    expect($fixture->fresh()->home_team_id)->toBe($this->bravo->id)
        ->and($fixture->fresh()->away_team_id)->toBe($this->alpha->id);
});

// --------------------------------------------------------------- programme

it('saves the running order as arranged', function () {
    ($this->setup)()
        ->call('addProgrammeRow')
        ->call('addProgrammeRow')
        ->set('programme.0.time', '3:00 pm')
        ->set('programme.0.activity', 'Match 1')
        ->set('programme.0.kind', 'match')
        ->set('programme.1.activity', ' Registration ')
        ->call('moveProgrammeRow', 1, -1)
        ->assertHasNoErrors();

    expect(array_column($this->tournament->fresh()->programmeRows(), 'activity'))->toBe(['Registration', 'Match 1'])
        ->and($this->tournament->fresh()->programmeRows()[1]['kind'])->toBe('match');
});

it('rejects an unknown programme row type', function () {
    ($this->setup)()
        ->call('addProgrammeRow')
        ->set('programme.0.kind', 'party')
        ->call('saveProgramme')
        ->assertHasErrors('programme.0.kind');
});
