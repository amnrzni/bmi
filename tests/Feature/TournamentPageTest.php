<?php

use App\Enums\Division;
use App\Livewire\Tournaments\Show;
use App\Models\TournamentFinal;
use App\Models\TournamentScore;
use App\Models\User;
use App\Services\TournamentRecorder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->staff = User::factory()->create(['name' => 'Along']);
    $this->tournament = tournamentWith(['Alpha', 'Bravo']);
    $this->alpha = $this->tournament->teams()->where('name', 'Alpha')->sole();

    $this->place = fn (User $user, Division $division = Division::Men) => $this->tournament->players()->create([
        'tournament_team_id' => $this->alpha->id,
        'user_id' => $user->id,
        'division' => $division,
    ]);

    $this->page = fn () => Livewire::actingAs($this->admin)->test(Show::class, ['tournament' => $this->tournament]);
});

// ------------------------------------------------------------------ access

it('lets anyone watch without signing in', function () {
    ($this->place)($this->staff);

    $this->get(route('tournaments.index'))->assertOk()->assertSee('Test Cup');
    $this->get(route('tournaments.show', $this->tournament))->assertOk()->assertSee('Staff sign in');
    $this->get(route('tournaments.show', ['tournament' => $this->tournament, 'tab' => 'roster']))->assertOk()->assertSee('Along');
    $this->get(route('tournaments.show', ['tournament' => $this->tournament, 'tab' => 'standings']))->assertOk()->assertSee('Bravo');
});

it('renders every tab with results in, for viewers and admins', function () {
    $recorder = app(TournamentRecorder::class);
    $recorder->recordScore($this->tournament->fixtures()->sole(), Division::Women, 3, 3, $this->admin);
    $recorder->recordFinal($this->tournament, Division::Women, 2, 2, 4, 5, $this->admin);
    ($this->place)($this->staff, Division::Women)->update(['is_captain' => true, 'is_out' => true]);
    $this->tournament->update(['programme' => [
        ['time' => '3:00 pm', 'activity' => 'Match 1', 'detail' => 'Alpha vs Bravo', 'kind' => 'match'],
        ['time' => '5:35 pm', 'activity' => 'The final', 'detail' => '', 'kind' => 'final'],
    ]]);

    $url = fn (array $query) => route('tournaments.show', ['tournament' => $this->tournament, ...$query]);

    $this->get($url([]))->assertOk()->assertSee('Alpha vs Bravo')->assertSee('The final');
    $this->get($url(['tab' => 'matches', 'division' => 'women']))->assertOk()
        ->assertSee('Pens 4–5')
        ->assertSee("Women's champion", false)
        ->assertSee('on penalties');
    $this->get($url(['tab' => 'standings', 'table' => 'combined']))->assertOk()->assertSee('for information');

    $this->actingAs($this->admin);
    $this->get($url(['tab' => 'matches', 'division' => 'women']))->assertOk()
        ->assertSee('Penalties, only if level')
        ->assertSee('Export CSV');
    $this->get($url(['tab' => 'roster']))->assertOk()->assertSee('aria-pressed="true"', false);
    $this->get(route('tournaments.setup', $this->tournament))->assertOk()
        ->assertSee('Along')
        ->assertSee('Has score')
        ->assertSee('Save programme');
});

it('shrugs off a mangled query string', function () {
    $this->get(route('tournaments.show', ['tournament' => $this->tournament, 'tab' => 'nope', 'division' => 'x', 'table' => 'y']))
        ->assertOk();
});

it('refreshes itself for viewers but not for admins mid-entry', function () {
    $this->get(route('tournaments.show', $this->tournament))->assertSee('wire:poll.15s', false);
    $this->actingAs($this->admin)->get(route('tournaments.show', $this->tournament))->assertDontSee('wire:poll', false);
});

it('puts tournaments in the nav for signed-in staff', function () {
    $this->actingAs($this->staff)->get(route('events'))->assertSee(route('tournaments.index'));
});

it('keeps setup to admins', function () {
    $this->get(route('tournaments.setup', $this->tournament))->assertRedirect(route('login'));
    $this->actingAs($this->staff)->get(route('tournaments.setup', $this->tournament))->assertForbidden();
    $this->actingAs($this->admin)->get(route('tournaments.setup', $this->tournament))->assertOk();
});

it('refuses every match-day action to guests and staff', function () {
    $fixture = $this->tournament->fixtures()->sole();
    $player = ($this->place)($this->staff);

    $actions = [
        ['saveScore', [$fixture->id]], ['clearScore', [$fixture->id]],
        ['saveFinal', []], ['clearFinal', []],
        ['toggleCaptain', [$player->id]], ['toggleOut', [$player->id]],
        ['export', []],
    ];

    // Guest first: acting as staff sticks for the rest of the test.
    foreach ([null, $this->staff] as $viewer) {
        foreach ($actions as [$method, $args]) {
            ($viewer
                ? Livewire::actingAs($viewer)->test(Show::class, ['tournament' => $this->tournament])
                : Livewire::test(Show::class, ['tournament' => $this->tournament]))
                ->call($method, ...$args)
                ->assertForbidden();
        }
    }

    expect(TournamentScore::count())->toBe(0)
        ->and($player->fresh()->is_captain)->toBeFalse()
        ->and($player->fresh()->is_out)->toBeFalse();
});

// ------------------------------------------------------------------ scores

it('lets an admin record and clear a score for the division on screen', function () {
    $fixture = $this->tournament->fixtures()->sole();

    $page = ($this->page)()
        ->call('showDivision', 'women')
        ->set("entry.{$fixture->id}.home", '4')
        ->set("entry.{$fixture->id}.away", '2')
        ->call('saveScore', $fixture->id)
        ->assertHasNoErrors();

    $score = TournamentScore::sole();

    expect($score->division)->toBe(Division::Women)
        ->and($score->home_score)->toBe(4)
        ->and($score->away_score)->toBe(2)
        ->and($score->recorded_by_user_id)->toBe($this->admin->id);

    $page->call('clearScore', $fixture->id);

    expect(TournamentScore::count())->toBe(0);
});

it('needs both halves of a score', function () {
    $fixture = $this->tournament->fixtures()->sole();

    ($this->page)()
        ->set("entry.{$fixture->id}.home", '3')
        ->set("entry.{$fixture->id}.away", '')
        ->call('saveScore', $fixture->id)
        ->assertHasErrors(["entry.{$fixture->id}.away"]);

    expect(TournamentScore::count())->toBe(0);
});

it("won't score a match from another tournament", function () {
    $other = tournamentWith(['X', 'Y'])->fixtures()->sole();

    // Rendered as a 404 over HTTP; the Livewire harness hands back the exception.
    expect(fn () => ($this->page)()
        ->set("entry.{$other->id}", ['home' => '1', 'away' => '0'])
        ->call('saveScore', $other->id))
        ->toThrow(ModelNotFoundException::class);

    expect(TournamentScore::count())->toBe(0);
});

it('asks for penalties when an admin saves a level final', function () {
    app(TournamentRecorder::class)->recordScore($this->tournament->fixtures()->sole(), Division::Men, 1, 0, $this->admin);

    ($this->page)()
        ->set('finalEntry.home', '2')
        ->set('finalEntry.away', '2')
        ->call('saveFinal')
        ->assertHasErrors('finalEntry')
        ->set('finalEntry.home_penalties', '5')
        ->set('finalEntry.away_penalties', '4')
        ->call('saveFinal')
        ->assertHasNoErrors();

    expect(TournamentFinal::sole()->home_penalties)->toBe(5);
});

it('explains why the final is not open yet', function () {
    ($this->page)()
        ->set('finalEntry.home', '1')
        ->set('finalEntry.away', '0')
        ->call('saveFinal')
        ->assertHasErrors('finalEntry');

    expect(TournamentFinal::count())->toBe(0);
});

// ------------------------------------------------------------------ squads

it('keeps one captain per team and division', function () {
    $first = ($this->place)(User::factory()->create());
    $second = ($this->place)(User::factory()->create());
    $women = ($this->place)(User::factory()->create(), Division::Women);

    $page = ($this->page)()
        ->call('toggleCaptain', $first->id)
        ->call('toggleCaptain', $women->id)
        ->call('toggleCaptain', $second->id);

    expect($first->fresh()->is_captain)->toBeFalse()
        ->and($second->fresh()->is_captain)->toBeTrue()
        ->and($women->fresh()->is_captain)->toBeTrue();

    $page->call('toggleCaptain', $second->id);

    expect($second->fresh()->is_captain)->toBeFalse();
});

it('marks someone as not attending and back', function () {
    $player = ($this->place)($this->staff);

    $page = ($this->page)()->call('toggleOut', $player->id);
    expect($player->fresh()->is_out)->toBeTrue();

    $page->call('toggleOut', $player->id);
    expect($player->fresh()->is_out)->toBeFalse();
});

it('exports the tournament as CSV for admins', function () {
    ($this->place)($this->staff);

    ($this->page)()->call('export')->assertFileDownloaded();
});
