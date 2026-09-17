<?php

namespace App\Services;

use App\Enums\Division;
use App\Models\Tournament;
use App\Models\TournamentFinal;
use App\Models\TournamentFixture;
use App\Models\TournamentScore;
use App\Models\User;
use App\Support\TournamentBoard;
use InvalidArgumentException;

/**
 * The only place tournament results are written.
 *
 * Everything is editable — scores get typed wrong at a courtside table — but
 * every write is stamped with who made it and lands in the activity log.
 */
class TournamentRecorder
{
    public const MAX_SCORE = 999;

    public function recordScore(TournamentFixture $fixture, Division $division, int $home, int $away, User $recordedBy): TournamentScore
    {
        $this->assertScore($home);
        $this->assertScore($away);

        $score = TournamentScore::query()
            ->where('tournament_fixture_id', $fixture->id)
            ->where('division', $division->value)
            ->first()
            ?? new TournamentScore(['tournament_fixture_id' => $fixture->id, 'division' => $division]);

        $score->fill([
            'home_score' => $home,
            'away_score' => $away,
            'recorded_by_user_id' => $recordedBy->id,
        ])->save();

        return $score;
    }

    public function clearScore(TournamentFixture $fixture, Division $division): void
    {
        // A model delete rather than a query delete, so the activity log sees it.
        TournamentScore::query()
            ->where('tournament_fixture_id', $fixture->id)
            ->where('division', $division->value)
            ->first()
            ?->delete();
    }

    /**
     * Save a division's final. The first save locks in the current top two;
     * later saves only change the score, until the final is cleared.
     */
    public function recordFinal(
        Tournament $tournament,
        Division $division,
        int $home,
        int $away,
        ?int $homePenalties,
        ?int $awayPenalties,
        User $recordedBy,
    ): TournamentFinal {
        $this->assertScore($home);
        $this->assertScore($away);

        if ($home === $away) {
            if ($homePenalties === null || $awayPenalties === null) {
                throw new InvalidArgumentException('The score is level, so enter the penalty score for both teams.');
            }

            $this->assertScore($homePenalties);
            $this->assertScore($awayPenalties);

            if ($homePenalties === $awayPenalties) {
                throw new InvalidArgumentException("The penalty score can't be level too — one team has to win.");
            }
        } else {
            // A decided final has no shoot-out, whatever was left in the boxes.
            $homePenalties = $awayPenalties = null;
        }

        $board = TournamentBoard::for($tournament);
        $final = $board->final($division);

        if (! $final) {
            $seeded = $board->seededFinalists($division)
                ?? throw new InvalidArgumentException('The final opens once every group match in this division has a score.');

            $final = new TournamentFinal([
                'tournament_id' => $tournament->id,
                'division' => $division,
                'home_team_id' => $seeded[0]->id,
                'away_team_id' => $seeded[1]->id,
            ]);
        }

        $final->fill([
            'home_score' => $home,
            'away_score' => $away,
            'home_penalties' => $homePenalties,
            'away_penalties' => $awayPenalties,
            'recorded_by_user_id' => $recordedBy->id,
        ])->save();

        return $final;
    }

    /** Also the way to re-seed a final after a group score was corrected. */
    public function clearFinal(Tournament $tournament, Division $division): void
    {
        TournamentFinal::query()
            ->where('tournament_id', $tournament->id)
            ->where('division', $division->value)
            ->first()
            ?->delete();
    }

    private function assertScore(int $value): void
    {
        if ($value < 0 || $value > self::MAX_SCORE) {
            throw new InvalidArgumentException('Scores must be between 0 and '.self::MAX_SCORE.'.');
        }
    }
}
