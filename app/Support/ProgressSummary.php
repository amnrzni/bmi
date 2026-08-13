<?php

namespace App\Support;

use App\Models\User;

/**
 * One person's progress, as shown in the deltas summary table.
 *
 * `weeksRecorded` travels with every delta on purpose: baselines are personal,
 * so people are measured over different numbers of weeks and a raw delta is not
 * comparable between a 10-week person and a 3-week one. Showing the count is
 * what stops the number silently lying (HANDOFF.md §3).
 */
final readonly class ProgressSummary
{
    public function __construct(
        public User $user,
        public int $weeksRecorded,
        public ?float $baselineWeight,
        public ?float $currentWeight,
        public ?float $deltaWeight,
        public ?float $percentChange,
        public ?float $baselineBmi,
        public ?float $currentBmi,
        public ?float $deltaBmi,
        public bool $isRanked,
    ) {}

    public function hasData(): bool
    {
        return $this->weeksRecorded > 0;
    }

    /** True when they've lost weight since their own first weigh-in. */
    public function isLoss(): bool
    {
        return $this->deltaWeight !== null && $this->deltaWeight < -0.05;
    }

    public function isGain(): bool
    {
        return $this->deltaWeight !== null && $this->deltaWeight > 0.05;
    }
}
