<?php

namespace App\Support;

use App\Models\Team;

/**
 * A team's event turnout.
 *
 * Kept separate from TeamStanding on purpose: that DTO is about weight, and
 * blending attendance into the weight percentage would produce a composite
 * number nobody could interpret. The two are shown side by side, never merged
 * (DECISIONS.md §7).
 *
 * `rate` is null when no event has happened yet, so the UI can say "no events
 * yet" instead of a misleading 0%.
 */
final readonly class TeamTurnout
{
    public function __construct(
        public Team $team,
        public int $memberCount,
        public int $pastEventCount,
        public int $attended,
        public int $opportunities,
        public ?float $rate,
    ) {}

    public function hasData(): bool
    {
        return $this->rate !== null;
    }
}
