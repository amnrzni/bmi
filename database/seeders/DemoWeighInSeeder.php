<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\WeighInRecorder;
use App\Support\ChallengeWeek;
use Illuminate\Database\Seeder;

/**
 * Demo weigh-in history.
 *
 * Seeds every ELAPSED week, so it adapts to whatever CHALLENGE_START_MONDAY is
 * set to. With the real start date (10 Aug 2026) that's just week 1 — which is
 * honestly what Friday's screens will show.
 *
 * To preview a full analytics grid, point the start date ~10 weeks back and
 * reseed:
 *
 *   CHALLENGE_START_MONDAY=2026-06-08 php artisan migrate:fresh --seed
 *
 * Deterministic (fixed random seed) so reseeding gives the same numbers.
 */
class DemoWeighInSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(42);

        $weeks = ChallengeWeek::elapsed();

        if ($weeks->isEmpty()) {
            $this->command?->warn('Challenge has not started yet — no weigh-ins seeded.');

            return;
        }

        $recorder = User::admins()->first() ?? User::first();
        $staff = User::participants()->whereNotNull('height_cm')->get();

        foreach ($staff as $person) {
            // A plausible starting weight for their height: BMI roughly 20–32.
            $heightM = $person->height_cm / 100;
            $weight = round(mt_rand(2000, 3200) / 100 * ($heightM ** 2), 1);

            // Most start at week 1; a few join a week or two late, which is what
            // makes personal baselines matter. Needs at least two elapsed weeks
            // for a latecomer to be possible at all.
            $latestStart = min(3, $weeks->count());
            $startsAtWeek = ($latestStart > 1 && mt_rand(1, 100) <= 15)
                ? mt_rand(2, $latestStart)
                : 1;

            // A handful never get recorded at all, so compliance has teeth.
            $neverRecords = mt_rand(1, 100) <= 8;

            $hasPriorRecord = false;

            foreach ($weeks as $offset => $week) {
                $weekNumber = $offset + 1;

                if ($neverRecords || $weekNumber < $startsAtWeek) {
                    continue;
                }

                // The current week is still being collected, so it's deliberately
                // patchy — that's what gives the progress callout something to say.
                // Earlier weeks are mostly complete, with the occasional gap.
                $missChance = match (true) {
                    $week->isCurrent() => 35,
                    $hasPriorRecord => 8,
                    default => 0, // never skip someone's very first weigh-in
                };

                if (mt_rand(1, 100) <= $missChance) {
                    continue;
                }

                if ($hasPriorRecord) {
                    // Mostly downward drift, with the occasional bad week.
                    $weight = round($weight + (mt_rand(-90, 40) / 100), 1);
                }

                // Goes through the same write path as the real screens, so a
                // reseed corrects rather than duplicating.
                app(WeighInRecorder::class)->record($person, $week, $weight, $recorder);

                $hasPriorRecord = true;
            }
        }
    }
}
