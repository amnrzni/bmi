<?php

namespace App\Livewire\Tournaments;

use App\Enums\Division;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin setup for one tournament: details, teams and squads, fixtures, and the
 * running order.
 *
 * Its own screen rather than an edit mode on the public page, which polls for
 * viewers — a refresh landing mid-edit would throw away what was being typed.
 * Scores, captains and attendance stay on the public page, where they happen.
 */
#[Layout('components.layouts.app')]
class Setup extends Component
{
    public Tournament $tournament;

    public string $name = '';

    public string $startsAt = '';

    public string $location = '';

    public string $newTeamName = '';

    /** team id => name, bound to the inline rename inputs. */
    public array $teamNames = [];

    /** team id => ['user_id' => '', 'division' => 'men'] */
    public array $newPlayers = [];

    /** @var list<array{time: string, activity: string, detail: string, kind: string}> */
    public array $programme = [];

    public ?string $flash = null;

    public function mount(): void
    {
        // The route is admin-only too; no method here is reachable without this.
        abort_unless(auth()->user()?->can('manage-tournaments'), 403);

        $this->name = $this->tournament->name;
        $this->startsAt = $this->tournament->starts_at?->format('Y-m-d\TH:i') ?? '';
        $this->location = (string) $this->tournament->location;
        $this->programme = $this->tournament->programmeRows();

        $this->syncTeamInputs();
    }

    // --------------------------------------------------------------- details

    public function saveDetails(): void
    {
        $this->name = trim($this->name);

        $this->validate([
            'name' => 'required|string|max:255',
            'startsAt' => 'nullable|date',
            'location' => 'nullable|string|max:255',
        ]);

        $this->tournament->update([
            'name' => $this->name,
            'starts_at' => $this->startsAt ?: null,
            'location' => trim($this->location) ?: null,
        ]);

        $this->flash = 'Details saved. The link stays the same.';
    }

    // ----------------------------------------------------------------- teams

    public function addTeam(): void
    {
        $this->newTeamName = trim($this->newTeamName);
        $this->validate(['newTeamName' => 'required|string|max:255'], attributes: ['newTeamName' => 'team name']);

        if ($this->tournament->teams()->where('name', $this->newTeamName)->exists()) {
            $this->addError('newTeamName', 'There is already a team with that name.');

            return;
        }

        $this->tournament->teams()->create([
            'name' => $this->newTeamName,
            'sort_order' => (int) $this->tournament->teams()->max('sort_order') + 1,
        ]);

        $this->flash = "Added {$this->newTeamName}.";
        $this->reset('newTeamName');
        $this->syncTeamInputs();
    }

    public function renameTeam(int $teamId): void
    {
        $team = $this->tournament->teams()->findOrFail($teamId);
        $name = trim((string) ($this->teamNames[$teamId] ?? ''));

        if ($name === '' || $name === $team->name) {
            $this->teamNames[$teamId] = $team->name;

            return;
        }

        if ($this->tournament->teams()->where('name', $name)->whereKeyNot($teamId)->exists()) {
            $this->addError("teamNames.{$teamId}", 'Another team already has that name.');

            return;
        }

        $this->resetValidation("teamNames.{$teamId}");
        $team->update(['name' => mb_substr($name, 0, 255)]);
        $this->flash = "Renamed to {$team->name}.";
    }

    /** Takes its squad, its fixtures and their scores with it — the confirm says so. */
    public function removeTeam(int $teamId): void
    {
        $team = $this->tournament->teams()->findOrFail($teamId);
        $team->delete();

        $this->flash = "Removed {$team->name}.";
        $this->syncTeamInputs();
    }

    // --------------------------------------------------------------- players

    public function addPlayer(int $teamId): void
    {
        $team = $this->tournament->teams()->findOrFail($teamId);
        $userId = (int) ($this->newPlayers[$teamId]['user_id'] ?? 0);
        $division = Division::tryFrom((string) ($this->newPlayers[$teamId]['division'] ?? ''));

        $user = $this->pickableUsers()->firstWhere('id', $userId);

        if (! $user || ! $division) {
            $this->addError("newPlayers.{$teamId}", 'Pick someone from the roster and a division.');

            return;
        }

        $team->players()->create([
            'tournament_id' => $this->tournament->id,
            'user_id' => $user->id,
            'division' => $division,
        ]);

        $this->newPlayers[$teamId]['user_id'] = '';
        $this->resetValidation("newPlayers.{$teamId}");
        $this->flash = "{$user->name} is in {$team->name} ({$division->label()}).";
    }

    public function removePlayer(int $playerId): void
    {
        $player = $this->tournament->players()->with('user')->findOrFail($playerId);
        $player->delete();

        $this->flash = "Removed {$player->displayName()}.";
    }

    // -------------------------------------------------------------- fixtures

    public function addFixture(): void
    {
        $teams = $this->tournament->teams()->take(2)->get();

        if ($teams->count() < 2) {
            $this->flash = 'Add at least two teams before adding matches.';

            return;
        }

        $this->tournament->fixtures()->create([
            'number' => (int) $this->tournament->fixtures()->max('number') + 1,
            'home_team_id' => $teams[0]->id,
            'away_team_id' => $teams[1]->id,
        ]);
    }

    /**
     * Allowed on a scored match — mistakes in the draw get fixed on the day. The
     * score stays with the match number, which the screen spells out.
     */
    public function updateFixture(int $fixtureId, string $side, string $teamId): void
    {
        $fixture = $this->tournament->fixtures()->findOrFail($fixtureId);
        $team = $this->tournament->teams()->findOrFail((int) $teamId);

        $column = match ($side) {
            'home' => 'home_team_id',
            'away' => 'away_team_id',
            default => abort(422),
        };
        $other = $side === 'home' ? $fixture->away_team_id : $fixture->home_team_id;

        if ($team->id === $other) {
            $this->flash = "Match {$fixture->number}: a team can't play itself.";

            return;
        }

        $fixture->update([$column => $team->id]);
    }

    public function removeFixture(int $fixtureId): void
    {
        $fixture = $this->tournament->fixtures()->findOrFail($fixtureId);
        $fixture->delete();

        $this->flash = "Removed match {$fixture->number}.";
    }

    // ------------------------------------------------------------- programme

    public function addProgrammeRow(): void
    {
        $this->programme[] = ['time' => '', 'activity' => '', 'detail' => '', 'kind' => 'general'];
        $this->saveProgramme(quiet: true);
    }

    public function removeProgrammeRow(int $index): void
    {
        unset($this->programme[$index]);
        $this->programme = array_values($this->programme);
        $this->saveProgramme(quiet: true);
    }

    public function moveProgrammeRow(int $index, int $direction): void
    {
        $target = $index + ($direction < 0 ? -1 : 1);

        if (! isset($this->programme[$index], $this->programme[$target])) {
            return;
        }

        [$this->programme[$index], $this->programme[$target]] = [$this->programme[$target], $this->programme[$index]];
        $this->saveProgramme(quiet: true);
    }

    /** Every row button saves too, so typed text is never lost to a reorder. */
    public function saveProgramme(bool $quiet = false): void
    {
        $this->validate([
            'programme' => 'array|max:60',
            'programme.*.time' => 'nullable|string|max:60',
            'programme.*.activity' => 'nullable|string|max:255',
            'programme.*.detail' => 'nullable|string|max:255',
            'programme.*.kind' => 'required|in:'.implode(',', Tournament::PROGRAMME_KINDS),
        ]);

        $this->tournament->update(['programme' => collect($this->programme)->map(fn ($row) => [
            'time' => trim((string) $row['time']),
            'activity' => trim((string) $row['activity']),
            'detail' => trim((string) $row['detail']),
            'kind' => $row['kind'],
        ])->values()->all()]);

        if (! $quiet) {
            $this->flash = 'Programme saved.';
        }
    }

    // ------------------------------------------------------------------ data

    /** Current roster members not already in this tournament. */
    private function pickableUsers(): Collection
    {
        return User::query()
            ->whereNull('left_at')
            ->whereNull('anonymised_at')
            ->whereNotIn('id', $this->tournament->players()->select('user_id'))
            ->orderBy('name')
            ->get();
    }

    private function syncTeamInputs(): void
    {
        $teams = $this->tournament->teams()->get();

        $this->teamNames = $teams->pluck('name', 'id')->all();
        $this->newPlayers = $teams->mapWithKeys(fn ($team) => [
            $team->id => $this->newPlayers[$team->id] ?? ['user_id' => '', 'division' => Division::Men->value],
        ])->all();
    }

    public function render()
    {
        return view('livewire.tournaments.setup', [
            'teams' => $this->tournament->teams()->with('players.user')->get(),
            'fixtures' => $this->tournament->fixtures()->withCount('scores')->get(),
            'pickable' => $this->pickableUsers(),
        ])->title('Set up '.$this->tournament->name.' — BMI Challenge');
    }
}
