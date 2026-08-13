<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * "Who didn't submit this week" = people on the master roster with no weigh-in
 * row for that week. This only works because the roster is the source of truth
 * and weekly records are checked against it (HANDOFF.md §2).
 */
final readonly class ComplianceReport
{
    /**
     * @param  Collection<int,User>  $recorded
     * @param  Collection<int,User>  $missing
     */
    public function __construct(
        public ChallengeWeek $week,
        public Collection $recorded,
        public Collection $missing,
    ) {}

    public function expectedCount(): int
    {
        return $this->recorded->count() + $this->missing->count();
    }

    public function recordedCount(): int
    {
        return $this->recorded->count();
    }

    public function isComplete(): bool
    {
        return $this->missing->isEmpty();
    }

    /** Departments with at least one person still outstanding. */
    public function outstandingDepartmentCount(): int
    {
        return $this->missing->pluck('department_id')->filter()->unique()->count();
    }
}
