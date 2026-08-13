<?php

namespace App\Services;

use App\Models\User;
use App\Models\WeighIn;
use App\Support\ChallengeWeek;
use InvalidArgumentException;

/**
 * The only place weigh-ins are written.
 *
 * BMI is derived here and never accepted from a client. Every write carries a
 * `recorded_by` stamp — cheap insurance so data entry is attributable
 * (HANDOFF.md §8).
 */
class WeighInRecorder
{
    /**
     * Create or correct a single week's record.
     *
     * Keyed on (user, week) so re-saving a batch corrects rather than duplicates,
     * and so no week is ever overwritten by another week's value.
     */
    public function record(User $user, ChallengeWeek $week, float $weightKg, User $recordedBy): WeighIn
    {
        $this->assertPlausible($weightKg);

        // Deliberately NOT updateOrCreate: its implicit `where` uses plain
        // equality, and the date cast writes "2026-08-10 00:00:00" while the
        // lookup value is "2026-08-10". MySQL's DATE column truncates and hides
        // that; SQLite stores the string verbatim, so the match fails and the
        // unique index fires on the second save. whereDate normalises both.
        $weighIn = WeighIn::query()
            ->where('user_id', $user->id)
            ->whereDate('week_start_date', $week->key())
            ->first()
            ?? new WeighIn(['user_id' => $user->id, 'week_start_date' => $week->key()]);

        $weighIn->fill([
            'weight_kg' => round($weightKg, 2),
            'bmi' => $user->bmiFor($weightKg),
            'recorded_by_user_id' => $recordedBy->id,
        ])->save();

        return $weighIn;
    }

    public function remove(User $user, ChallengeWeek $week): void
    {
        WeighIn::where('user_id', $user->id)
            ->whereDate('week_start_date', $week->key())
            ->delete();
    }

    /**
     * Re-derive the stored BMI column after a height correction, so the cached
     * value never drifts from what ProgressService computes live.
     */
    public function recalculateBmisFor(User $user): void
    {
        foreach ($user->weighIns()->get() as $weighIn) {
            $weighIn->update(['bmi' => $user->bmiFor((float) $weighIn->weight_kg)]);
        }
    }

    /** Typo guard. Deliberately wide — it catches slips, not unusual bodies. */
    public function assertPlausible(float $weightKg): void
    {
        $min = (float) config('challenge.weight_min');
        $max = (float) config('challenge.weight_max');

        if ($weightKg < $min || $weightKg > $max) {
            throw new InvalidArgumentException("Weight must be between {$min} and {$max} kg.");
        }
    }

    /**
     * A big jump against their last record is usually a typo, but sometimes real —
     * so this warns and never blocks.
     */
    public function jumpWarning(User $user, ChallengeWeek $week, float $weightKg): ?string
    {
        $previous = $user->weighIns()
            ->whereDate('week_start_date', '<', $week->key())
            ->orderByDesc('week_start_date')
            ->first();

        if (! $previous) {
            return null;
        }

        $difference = $weightKg - (float) $previous->weight_kg;
        $threshold = (float) config('challenge.weight_jump_warn');

        if (abs($difference) < $threshold) {
            return null;
        }

        return sprintf(
            '%s%s kg vs their last record (%s kg). Double-check before saving.',
            $difference > 0 ? '+' : '',
            number_format($difference, 1),
            number_format((float) $previous->weight_kg, 1),
        );
    }
}
