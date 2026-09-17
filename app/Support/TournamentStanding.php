<?php

namespace App\Support;

use App\Models\TournamentTeam;

/** One team's group-stage line: win 3, draw 1, loss 0. */
final readonly class TournamentStanding
{
    public const POINTS_FOR_WIN = 3;

    public const POINTS_FOR_DRAW = 1;

    public function __construct(
        public TournamentTeam $team,
        public int $won = 0,
        public int $drawn = 0,
        public int $lost = 0,
        public int $scoredFor = 0,
        public int $scoredAgainst = 0,
    ) {}

    public function played(): int
    {
        return $this->won + $this->drawn + $this->lost;
    }

    public function difference(): int
    {
        return $this->scoredFor - $this->scoredAgainst;
    }

    public function points(): int
    {
        return $this->won * self::POINTS_FOR_WIN + $this->drawn * self::POINTS_FOR_DRAW;
    }

    /** Record one result from this team's side. */
    public function withResult(int $for, int $against): self
    {
        return new self(
            $this->team,
            $this->won + ($for > $against ? 1 : 0),
            $this->drawn + ($for === $against ? 1 : 0),
            $this->lost + ($for < $against ? 1 : 0),
            $this->scoredFor + $for,
            $this->scoredAgainst + $against,
        );
    }

    /** Sum of two lines for the same team — the combined table. */
    public function plus(self $other): self
    {
        return new self(
            $this->team,
            $this->won + $other->won,
            $this->drawn + $other->drawn,
            $this->lost + $other->lost,
            $this->scoredFor + $other->scoredFor,
            $this->scoredAgainst + $other->scoredAgainst,
        );
    }
}
