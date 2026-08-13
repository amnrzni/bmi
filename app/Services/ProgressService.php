<?php

namespace App\Services;

use App\Models\Team;
use App\Models\User;
use App\Models\WeighIn;
use App\Support\ChallengeWeek;
use App\Support\ComplianceReport;
use App\Support\ProgressSummary;
use App\Support\TeamStanding;
use Illuminate\Support\Collection;

/**
 * Every derived number in the app. The math IS the product, so this class is
 * the one thing that is tested exhaustively.
 *
 * Definitions (HANDOFF.md §3 — locked, don't reinterpret):
 *
 *   Baseline        each person's own FIRST recorded weigh-in. Not a fixed
 *                   challenge week 1. Someone who first weighs in at week 3 has
 *                   week 3 as their zero, which is what makes latecomers work
 *                   with no null-handling.
 *   Δ from baseline current vs their first record. The progress number.
 *   Δ week-over-week a week vs their PREVIOUS ACTUAL record — gaps are skipped,
 *                   so it's never compared against a calendar week they missed.
 *
 * BMI is always recomputed from the user's current height rather than read from
 * the stored column, so a height correction is reflected consistently across
 * history (DECISIONS.md §3).
 *
 * Methods take already-loaded models. Callers should eager-load `weighIns` —
 * see `summariesFor()` — so the 43-row grid doesn't N+1.
 */
class ProgressService
{
    /** @return Collection<int,WeighIn> oldest first */
    public function series(User $user): Collection
    {
        return $user->weighIns instanceof Collection
            ? $user->weighIns->sortBy(fn (WeighIn $w) => $w->week_start_date->timestamp)->values()
            : collect();
    }

    public function baseline(User $user): ?WeighIn
    {
        return $this->series($user)->first();
    }

    public function latest(User $user): ?WeighIn
    {
        return $this->series($user)->last();
    }

    public function recordCount(User $user): int
    {
        return $this->series($user)->count();
    }

    /** Enough records to be ranked on the individual leaderboard. */
    public function isRanked(User $user): bool
    {
        return $this->recordCount($user) >= (int) config('challenge.min_records_rank');
    }

    /** Enough records to count toward the team average. */
    public function countsTowardTeam(User $user): bool
    {
        return $this->recordCount($user) >= (int) config('challenge.min_records_team');
    }

    /**
     * The ranking metric. Negative = weight lost.
     *
     * Percent rather than raw kg because raw kg isn't comparable across body
     * sizes or across different numbers of weeks (HANDOFF.md §7).
     */
    public function percentChange(User $user): ?float
    {
        $baseline = $this->baseline($user);
        $latest = $this->latest($user);

        if (! $baseline || ! $latest) {
            return null;
        }

        $from = (float) $baseline->weight_kg;

        if ($from <= 0) {
            return null;
        }

        return round(((float) $latest->weight_kg - $from) / $from * 100, 2);
    }

    /**
     * Week-over-week deltas keyed by week_start_date.
     *
     * The first record maps to null — there's no prior record to compare, which
     * the grid renders as "·" rather than a misleading zero.
     *
     * @return Collection<string,?float>
     */
    public function weekOverWeek(User $user): Collection
    {
        $deltas = collect();
        $previous = null;

        foreach ($this->series($user) as $record) {
            $weight = (float) $record->weight_kg;

            $deltas->put(
                $record->week_start_date->toDateString(),
                $previous === null ? null : round($weight - $previous, 2),
            );

            $previous = $weight;
        }

        return $deltas;
    }

    public function summary(User $user): ProgressSummary
    {
        $baseline = $this->baseline($user);
        $latest = $this->latest($user);

        $baselineWeight = $baseline ? (float) $baseline->weight_kg : null;
        $currentWeight = $latest ? (float) $latest->weight_kg : null;

        // Computed from current height, not the stored bmi column.
        $baselineBmi = $user->bmiFor($baselineWeight);
        $currentBmi = $user->bmiFor($currentWeight);

        return new ProgressSummary(
            user: $user,
            weeksRecorded: $this->recordCount($user),
            baselineWeight: $baselineWeight,
            currentWeight: $currentWeight,
            deltaWeight: $baselineWeight !== null && $currentWeight !== null
                ? round($currentWeight - $baselineWeight, 2)
                : null,
            percentChange: $this->percentChange($user),
            baselineBmi: $baselineBmi,
            currentBmi: $currentBmi,
            deltaBmi: $baselineBmi !== null && $currentBmi !== null
                ? round($currentBmi - $baselineBmi, 2)
                : null,
            isRanked: $this->isRanked($user),
        );
    }

    /**
     * @param  Collection<int,User>  $users  with `weighIns` eager-loaded
     * @return Collection<int,ProgressSummary>
     */
    public function summariesFor(Collection $users): Collection
    {
        return $users->map(fn (User $user) => $this->summary($user));
    }

    /**
     * Team standing: the mean of member percent-changes.
     *
     * Members below `min_records_team` are excluded — with a single record a
     * person's change is 0% by definition, and including them would drag the
     * team average toward zero for reasons that have nothing to do with effort.
     */
    public function teamStanding(Team $team, ?Collection $members = null): TeamStanding
    {
        $members ??= User::query()
            ->participants()
            ->where('team_id', $team->id)
            ->with('weighIns')
            ->get();

        $changes = $members
            ->filter(fn (User $user) => $this->countsTowardTeam($user))
            ->map(fn (User $user) => $this->percentChange($user))
            ->filter(fn (?float $change) => $change !== null);

        return new TeamStanding(
            team: $team,
            memberCount: $members->count(),
            countedMembers: $changes->count(),
            averagePercentChange: $changes->isEmpty() ? null : round($changes->avg(), 2),
        );
    }

    /**
     * Who logged and who's missing for a week.
     *
     * The roster is the denominator, so someone who left keeps their historical
     * rows but stops showing up as missing.
     */
    public function compliance(ChallengeWeek $week): ComplianceReport
    {
        $roster = User::query()
            ->onRosterFor($week)
            ->with('department')
            ->orderBy('name')
            ->get();

        $recordedIds = WeighIn::query()
            ->forWeek($week)
            ->whereIn('user_id', $roster->pluck('id'))
            ->pluck('user_id')
            ->all();

        return new ComplianceReport(
            week: $week,
            recorded: $roster->whereIn('id', $recordedIds)->values(),
            missing: $roster->whereNotIn('id', $recordedIds)->values(),
        );
    }
}
