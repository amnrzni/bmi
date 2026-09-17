<?php

namespace App\Console\Commands;

use App\Enums\Division;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Create Kudeta Bola Baling (Sat 19 Sep 2026) from the organisers' prototype,
 * and place as much of its squad sheet onto the roster as can be done safely.
 *
 * The sheet lists nicknames ("SYAZWAN MKT", "Ecah"), not roster names, so a
 * name is only placed when it matches exactly one roster member on whole words
 * and that member matches no other name on the sheet. Everything else is
 * reported for an admin to add on the setup screen — a wrong guess would put a
 * colleague on someone else's team in public.
 *
 * Safe to re-run once the roster is complete: an existing tournament's teams,
 * matches and programme are left alone, and people already placed are skipped.
 */
class KudetaTournament extends Command
{
    protected $signature = 'tournaments:kudeta {--dry-run : Report what would change without writing}';

    protected $description = 'Create the Kudeta Bola Baling tournament and place its squads from the roster';

    private const SLUG = 'kudeta-bola-baling';

    /** From MATCH_BOLA_BALING.xlsx. The first name in each list was the captain. */
    private const SQUADS = [
        'TEAM A&B' => [
            'men' => ['HAIKAL', 'RAZIQ', 'HAMIRUL', 'ZAID', 'AMAR', 'MUQARRABIN', 'SYED', 'ADIB'],
            'women' => ['Baizura', 'Shuhaida', 'Aina', 'Shannis', 'Adawiyah', 'Arfah', 'Dinie Azeem', 'Tihani', 'Aleen', 'Anis Zulaikha', 'Adriana Husna', 'Ecah'],
        ],
        'TEAM C&D' => [
            'men' => ['MIOR', 'RAZI', 'SYAZWAN MKT', 'SYAZWAN', 'AIMAN', 'AMIRUL', 'DAUS', 'ARIF'],
            'women' => ['Hazimah', 'Aisyah', 'Dhia Azeem', 'Syafiqah', 'Fatini', 'Darwisya Azeem', 'Hanisah', 'Syahida', 'Izzati', 'Shahirah'],
        ],
        'TEAM E&F' => [
            'men' => ['SHAHRIL', 'SUFIAN', 'SYAHIR', 'FARHAN', 'IRFAN', 'AKMAL', 'ZAIN'],
            'women' => ['Nisha', 'Dania Azeem', 'Khadijah', 'Mastura', 'Nad', 'Syazwani', 'Faizzatul Hanis', 'Hanim', 'Hafizah', 'Allisyah', 'Ain', 'Amni'],
        ],
        'TEAM G&H' => [
            'men' => ['FAKHRUL', 'DZULHILMY', 'AQIL', 'RITHAUDDIN', 'MUSTAQIM', 'SOLIHIN', 'IFFAT'],
            'women' => ['Dea', 'Syikin', 'Farah', 'Puteri', 'Izzah', 'Ezzani', 'Syuhada', 'Alia', 'Amizatul', 'Eisya', 'Nasuha', 'Syazana'],
        ],
    ];

    /** Highlighted "Not Going" on the sheet. */
    private const NOT_GOING = [
        'TEAM A&B' => ['HAMIRUL', 'ADIB', 'Arfah', 'Aleen'],
        'TEAM C&D' => ['SYAZWAN', 'Fatini'],
        'TEAM E&F' => ['SHAHRIL'],
        'TEAM G&H' => ['Puteri', 'Izzah', 'Alia'],
    ];

    private const FIXTURES = [
        [1, 'TEAM A&B', 'TEAM C&D'],
        [2, 'TEAM E&F', 'TEAM G&H'],
        [3, 'TEAM A&B', 'TEAM E&F'],
        [4, 'TEAM C&D', 'TEAM G&H'],
        [5, 'TEAM G&H', 'TEAM A&B'],
        [6, 'TEAM C&D', 'TEAM E&F'],
    ];

    private const PROGRAMME = [
        ['2:30 – 2:45 pm', 'Team registration', '', 'general'],
        ['2:45 – 3:00 pm', 'Doa, briefing & warm-up', '', 'general'],
        ['3:00 – 3:15 pm', 'Match 1', 'Team A&B vs Team C&D', 'match'],
        ['3:20 – 3:35 pm', 'Match 2', 'Team E&F vs Team G&H', 'match'],
        ['3:40 – 3:55 pm', 'Match 3', 'Team A&B vs Team E&F', 'match'],
        ['4:00 – 4:15 pm', 'Match 4', 'Team C&D vs Team G&H', 'match'],
        ['4:20 – 4:35 pm', 'Match 5', 'Team G&H vs Team A&B', 'match'],
        ['4:40 – 5:10 pm', 'Asar prayer (in turns) & refreshments', 'Sandwiches · surau available @ Surau Petron Gunung Lang', 'break'],
        ['5:15 – 5:30 pm', 'Match 6', 'Team C&D vs Team E&F', 'match'],
        ['5:35 – 5:55 pm', 'Final', 'Champion vs Runner-up', 'final'],
        ['6:00 – 6:15 pm', 'Group photo & closing', '', 'general'],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->components->info($dryRun ? 'Dry run — nothing will be written.' : 'Setting up Kudeta Bola Baling.');

        $tournament = Tournament::firstWhere('slug', self::SLUG);

        if ($tournament) {
            $this->components->twoColumnDetail('Tournament', '<fg=gray>already exists — teams, matches and programme left alone</>');
        } elseif ($dryRun) {
            $this->components->twoColumnDetail('Tournament', '<fg=green>would be created</>');
        } else {
            $tournament = $this->createTournament();
            $this->components->twoColumnDetail('Tournament', '<fg=green>created</>');
        }

        [$placed, $unplaced] = $this->placeSquads($tournament, $dryRun);

        $this->newLine();
        $this->line("  {$placed} ".Str::plural('person', $placed).($dryRun ? ' would be placed' : ' placed').", {$unplaced} left to add by hand.");

        if ($unplaced > 0) {
            $this->line('  Add them on the setup screen: '.($tournament ? route('tournaments.setup', $tournament) : '/tournaments/'.self::SLUG.'/setup'));
            $this->line('  Re-running this after the roster is filled in places any new unambiguous matches.');
        }

        return self::SUCCESS;
    }

    private function createTournament(): Tournament
    {
        return DB::transaction(function () {
            $tournament = Tournament::create([
                'name' => 'Kudeta Bola Baling',
                'slug' => self::SLUG,
                'starts_at' => '2026-09-19 14:30:00',
                'location' => 'Ace Space Sports Centre',
                'programme' => collect(self::PROGRAMME)
                    ->map(fn (array $row) => array_combine(['time', 'activity', 'detail', 'kind'], $row))
                    ->all(),
            ]);

            $teams = collect(array_keys(self::SQUADS))->mapWithKeys(fn (string $name, int $i) => [
                $name => $tournament->teams()->create(['name' => $name, 'sort_order' => $i + 1]),
            ]);

            foreach (self::FIXTURES as [$number, $home, $away]) {
                $tournament->fixtures()->create([
                    'number' => $number,
                    'home_team_id' => $teams[$home]->id,
                    'away_team_id' => $teams[$away]->id,
                ]);
            }

            return $tournament;
        });
    }

    /** @return array{0: int, 1: int} placed, left for an admin */
    private function placeSquads(?Tournament $tournament, bool $dryRun): array
    {
        $users = User::query()->whereNull('left_at')->whereNull('anonymised_at')->orderBy('name')->get();
        $alreadyIn = $tournament ? $tournament->players()->pluck('user_id')->all() : [];

        $rows = collect();
        foreach (self::SQUADS as $team => $divisions) {
            foreach ($divisions as $division => $names) {
                foreach ($names as $index => $name) {
                    $rows->push([
                        'team' => $team,
                        'division' => Division::from($division),
                        'name' => $name,
                        'captain' => $index === 0,
                        'candidates' => $this->candidates($name, $users),
                    ]);
                }
            }
        }

        // Resolved across the whole sheet first: someone who is the only match
        // for two different names can't safely be either of them.
        $claims = $rows->filter(fn (array $row) => $row['candidates']->count() === 1)
            ->countBy(fn (array $row) => $row['candidates']->first()->id);

        $placed = 0;
        $unplaced = 0;

        foreach ($rows->groupBy('team') as $teamName => $teamRows) {
            $this->newLine();
            $this->line("  <options=bold>{$teamName}</>");

            $team = $tournament?->teams()->where('name', $teamName)->first();

            foreach ($teamRows as $row) {
                $label = "  {$row['division']->label()} · {$row['name']}";
                $candidates = $row['candidates'];

                $problem = match (true) {
                    $candidates->isEmpty() => 'no roster match',
                    $candidates->count() > 1 => 'matches '.$candidates->pluck('name')->implode(', '),
                    $claims[$candidates->first()->id] > 1 => $candidates->first()->name.' also matches another name',
                    $tournament && ! $team => 'team no longer exists',
                    default => null,
                };

                if ($problem) {
                    $unplaced++;
                    $this->components->twoColumnDetail($label, "<fg=yellow>{$problem}</>");

                    continue;
                }

                $user = $candidates->first();

                if (in_array($user->id, $alreadyIn, true)) {
                    $this->components->twoColumnDetail($label, "<fg=gray>already placed ({$user->name})</>");

                    continue;
                }

                if (! $dryRun) {
                    TournamentPlayer::create([
                        'tournament_id' => $tournament->id,
                        'tournament_team_id' => $team->id,
                        'user_id' => $user->id,
                        'division' => $row['division'],
                        // Never displaces a captain an admin has already set.
                        'is_captain' => $row['captain'] && ! $team->players()
                            ->where('division', $row['division']->value)
                            ->where('is_captain', true)
                            ->exists(),
                        'is_out' => in_array($row['name'], self::NOT_GOING[$teamName], true),
                    ]);
                }

                $alreadyIn[] = $user->id;
                $placed++;
                $this->components->twoColumnDetail($label, '<fg=green>'.($dryRun ? 'would place ' : 'placed ').$user->name.'</>');
            }
        }

        return [$placed, $unplaced];
    }

    /** Roster members whose name contains every word of the sheet's name. */
    private function candidates(string $name, Collection $users): Collection
    {
        $wanted = $this->words($name);

        return $users->filter(fn (User $user) => array_diff($wanted, $this->words($user->name)) === [])->values();
    }

    /** @return list<string> */
    private function words(string $value): array
    {
        return preg_split('/[^A-Z0-9]+/', Str::upper(Str::ascii($value)), -1, PREG_SPLIT_NO_EMPTY);
    }
}
