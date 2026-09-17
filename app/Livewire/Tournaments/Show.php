<?php

namespace App\Livewire\Tournaments;

use App\Enums\Division;
use App\Models\Tournament;
use App\Services\TournamentRecorder;
use App\Support\TournamentBoard;
use App\Support\TournamentStanding;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The tournament page. Public — no sign-in to watch — and the match-day
 * console for admins on the same screen: scores, finals, captains, attendance.
 *
 * Every admin action re-checks the gate itself, because a guest can reach this
 * component's endpoint as easily as an admin can.
 */
#[Layout('components.layouts.app')]
class Show extends Component
{
    public const TABS = [
        'programme' => 'Programme',
        'roster' => 'Roster',
        'matches' => 'Matches',
        'standings' => 'Standings',
    ];

    public const TABLES = ['men', 'women', 'combined'];

    public Tournament $tournament;

    #[Url(except: 'programme')]
    public string $tab = 'programme';

    #[Url(except: 'men')]
    public string $division = 'men';

    #[Url(except: 'men')]
    public string $table = 'men';

    /** fixture id => ['home' => '', 'away' => ''], for the division on screen. */
    public array $entry = [];

    /** @var array{home: string, away: string, home_penalties: string, away_penalties: string} */
    public array $finalEntry = [];

    public ?string $flash = null;

    public function mount(): void
    {
        // The query string is typed by whoever shared the link.
        $this->tab = array_key_exists($this->tab, self::TABS) ? $this->tab : 'programme';
        $this->division = $this->currentDivision()->value;
        $this->table = in_array($this->table, self::TABLES, true) ? $this->table : 'men';

        $this->loadEntries();
    }

    // ------------------------------------------------------------ navigation

    public function showTab(string $tab): void
    {
        if (array_key_exists($tab, self::TABS)) {
            $this->tab = $tab;
            $this->flash = null;
        }
    }

    public function showDivision(string $division): void
    {
        $this->division = (Division::tryFrom($division) ?? Division::Men)->value;
        $this->flash = null;
        $this->resetValidation();
        $this->loadEntries();
    }

    public function showTable(string $table): void
    {
        if (in_array($table, self::TABLES, true)) {
            $this->table = $table;
        }
    }

    // ---------------------------------------------------------------- scores

    public function saveScore(int $fixtureId, TournamentRecorder $recorder): void
    {
        $this->assertAdmin();

        $fixture = $this->tournament->fixtures()->findOrFail($fixtureId);
        $rule = 'required|integer|min:0|max:'.TournamentRecorder::MAX_SCORE;

        $this->validate(
            ["entry.{$fixtureId}.home" => $rule, "entry.{$fixtureId}.away" => $rule],
            attributes: ["entry.{$fixtureId}.home" => 'home score', "entry.{$fixtureId}.away" => 'away score'],
        );

        $recorder->recordScore(
            $fixture,
            $this->currentDivision(),
            (int) $this->entry[$fixtureId]['home'],
            (int) $this->entry[$fixtureId]['away'],
            auth()->user(),
        );

        $this->flash = "Match {$fixture->number} ({$this->currentDivision()->label()}) saved.";
    }

    public function clearScore(int $fixtureId, TournamentRecorder $recorder): void
    {
        $this->assertAdmin();

        $fixture = $this->tournament->fixtures()->findOrFail($fixtureId);
        $recorder->clearScore($fixture, $this->currentDivision());

        $this->entry[$fixtureId] = ['home' => '', 'away' => ''];
        $this->resetValidation();
        $this->flash = "Match {$fixture->number} ({$this->currentDivision()->label()}) cleared.";
    }

    public function saveFinal(TournamentRecorder $recorder): void
    {
        $this->assertAdmin();

        $score = 'required|integer|min:0|max:'.TournamentRecorder::MAX_SCORE;
        $penalties = 'nullable|integer|min:0|max:'.TournamentRecorder::MAX_SCORE;

        $this->validate(
            [
                'finalEntry.home' => $score,
                'finalEntry.away' => $score,
                'finalEntry.home_penalties' => $penalties,
                'finalEntry.away_penalties' => $penalties,
            ],
            attributes: [
                'finalEntry.home' => 'score',
                'finalEntry.away' => 'score',
                'finalEntry.home_penalties' => 'penalty score',
                'finalEntry.away_penalties' => 'penalty score',
            ],
        );

        $blankToNull = fn ($value) => $value === '' || $value === null ? null : (int) $value;

        $this->resetErrorBag('finalEntry');

        try {
            $recorder->recordFinal(
                $this->tournament,
                $this->currentDivision(),
                (int) $this->finalEntry['home'],
                (int) $this->finalEntry['away'],
                $blankToNull($this->finalEntry['home_penalties'] ?? null),
                $blankToNull($this->finalEntry['away_penalties'] ?? null),
                auth()->user(),
            );
        } catch (InvalidArgumentException $e) {
            $this->addError('finalEntry', $e->getMessage());

            return;
        }

        $this->loadEntries();
        $this->flash = "{$this->currentDivision()->label()}'s final saved.";
    }

    public function clearFinal(TournamentRecorder $recorder): void
    {
        $this->assertAdmin();

        $recorder->clearFinal($this->tournament, $this->currentDivision());

        $this->loadEntries();
        $this->resetValidation();
        $this->flash = "{$this->currentDivision()->label()}'s final cleared. Its finalists are re-seeded from the table.";
    }

    // ---------------------------------------------------------------- squads

    /** One captain per team per division; tapping the captain again unsets them. */
    public function toggleCaptain(int $playerId): void
    {
        $this->assertAdmin();

        $player = $this->tournament->players()->findOrFail($playerId);
        $becomesCaptain = ! $player->is_captain;

        DB::transaction(function () use ($player, $becomesCaptain) {
            $this->tournament->players()
                ->where('tournament_team_id', $player->tournament_team_id)
                ->where('division', $player->division->value)
                ->update(['is_captain' => false]);

            $player->update(['is_captain' => $becomesCaptain]);
        });
    }

    public function toggleOut(int $playerId): void
    {
        $this->assertAdmin();

        $player = $this->tournament->players()->findOrFail($playerId);
        $player->update(['is_out' => ! $player->is_out]);
    }

    // ---------------------------------------------------------------- export

    public function export(): StreamedResponse
    {
        $this->assertAdmin();

        $board = TournamentBoard::for($this->tournament, withSquads: true);

        return response()->streamDownload(function () use ($board) {
            $out = fopen('php://output', 'w');
            $section = function (string $title, array $header, iterable $rows) use ($out) {
                fputcsv($out, [$title]);
                fputcsv($out, $header);
                foreach ($rows as $row) {
                    fputcsv($out, $row);
                }
                fputcsv($out, []);
            };

            $section('ROSTER', ['Team', 'Division', 'Name', 'Captain', 'Attendance'], $board->teams->flatMap(
                fn ($team) => $team->players->map(fn ($player) => [
                    $team->name,
                    $player->division->label(),
                    $player->displayName(),
                    $player->is_captain ? 'Captain' : '',
                    $player->is_out ? 'Not attending' : 'Attending',
                ])
            ));

            $section('MATCHES', ['Division', 'Match', 'Home', 'Home score', 'Away score', 'Away', 'Penalties', 'Winner'], $this->matchRows($board));

            $standingHeader = ['#', 'Team', 'P', 'W', 'D', 'L', 'SF', 'SA', 'SD', 'PTS'];
            foreach ([['MEN', $board->standings(Division::Men)], ['WOMEN', $board->standings(Division::Women)], ['COMBINED', $board->combined()]] as [$label, $lines]) {
                $section("STANDINGS - {$label}", $standingHeader, $this->standingRows($lines));
            }

            fclose($out);
        }, $this->tournament->slug.'-'.now()->format('Y-m-d-Hi').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function matchRows(TournamentBoard $board): array
    {
        $rows = [];

        foreach (Division::cases() as $division) {
            foreach ($board->fixtures as $fixture) {
                $score = $board->score($fixture, $division);
                $home = $board->team($fixture->home_team_id)?->name;
                $away = $board->team($fixture->away_team_id)?->name;

                $rows[] = [
                    $division->label(), $fixture->number, $home,
                    $score?->home_score, $score?->away_score, $away, '',
                    match (true) {
                        ! $score => '',
                        $score->home_score > $score->away_score => $home,
                        $score->away_score > $score->home_score => $away,
                        default => 'Draw',
                    },
                ];
            }

            if ($final = $board->final($division)) {
                $rows[] = [
                    $division->label(), 'Final',
                    $board->team($final->home_team_id)?->name, $final->home_score,
                    $final->away_score, $board->team($final->away_team_id)?->name,
                    $final->wentToPenalties() ? "{$final->home_penalties}-{$final->away_penalties}" : '',
                    $board->team($final->winnerTeamId())?->name,
                ];
            }
        }

        return $rows;
    }

    /** @param  Collection<int, TournamentStanding>  $lines */
    private function standingRows(Collection $lines): array
    {
        return $lines->values()->map(fn (TournamentStanding $line, int $i) => [
            $i + 1, $line->team->name, $line->played(), $line->won, $line->drawn, $line->lost,
            $line->scoredFor, $line->scoredAgainst, $line->difference(), $line->points(),
        ])->all();
    }

    // -------------------------------------------------------------- helpers

    private function isAdmin(): bool
    {
        return (bool) auth()->user()?->can('manage-tournaments');
    }

    private function assertAdmin(): void
    {
        abort_unless($this->isAdmin(), 403);
    }

    private function currentDivision(): Division
    {
        return Division::tryFrom($this->division) ?? Division::Men;
    }

    /** Fill the score boxes from what is stored for the division on screen. */
    private function loadEntries(): void
    {
        $board = TournamentBoard::for($this->tournament);
        $division = $this->currentDivision();
        $text = fn (?int $value) => $value === null ? '' : (string) $value;

        $this->entry = $board->fixtures->mapWithKeys(function ($fixture) use ($board, $division, $text) {
            $score = $board->score($fixture, $division);

            return [$fixture->id => ['home' => $text($score?->home_score), 'away' => $text($score?->away_score)]];
        })->all();

        $final = $board->final($division);

        $this->finalEntry = [
            'home' => $text($final?->home_score),
            'away' => $text($final?->away_score),
            'home_penalties' => $text($final?->home_penalties),
            'away_penalties' => $text($final?->away_penalties),
        ];
    }

    public function render()
    {
        $board = TournamentBoard::for($this->tournament, withSquads: true);
        $division = $this->currentDivision();

        return view('livewire.tournaments.show', [
            'board' => $board,
            'isAdmin' => $this->isAdmin(),
            'currentDivision' => $division,
            'standings' => match ($this->table) {
                'combined' => $board->combined(),
                'women' => $board->standings(Division::Women),
                default => $board->standings(Division::Men),
            },
            'final' => $board->final($division),
            'seeded' => $board->seededFinalists($division),
            'programme' => $this->tournament->programmeRows(),
        ])->title($this->tournament->name.' — BMI Challenge');
    }
}
