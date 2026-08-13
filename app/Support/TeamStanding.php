<?php

namespace App\Support;

use App\Models\Team;

/**
 * A team's position, measured as average % change from baseline.
 *
 * Never raw kg: different people with different starting points means a heavier
 * team would win by default. This is non-negotiable for fairness (HANDOFF.md §5.1).
 *
 * `countedMembers` vs `memberCount` is shown in the UI so nobody reads the
 * average as covering the whole team when it doesn't yet.
 */
final readonly class TeamStanding
{
    public function __construct(
        public Team $team,
        public int $memberCount,
        public int $countedMembers,
        public ?float $averagePercentChange,
    ) {}

    public function hasData(): bool
    {
        return $this->averagePercentChange !== null;
    }

    public function isLoss(): bool
    {
        return $this->averagePercentChange !== null && $this->averagePercentChange < -0.05;
    }
}
