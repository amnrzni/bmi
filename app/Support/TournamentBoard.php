<?php

namespace App\Support;

use App\Enums\Division;
use App\Models\Tournament;
use App\Models\TournamentFinal;
use App\Models\TournamentFixture;
use App\Models\TournamentScore;
use App\Models\TournamentTeam;
use Illuminate\Support\Collection;

/**
 * A tournament's state loaded once, and every rule derived from it: standings,
 * whether the group stage is done, and who reaches the final.
 *
 * The public page and the recorder both read through here, so the finalists a
 * viewer sees are exactly the ones a saved final is locked to.
 */
final class TournamentBoard
{
    /**
     * @param  Collection<int, TournamentTeam>  $teams  in list order — the last tiebreak
     * @param  Collection<int, TournamentFixture>  $fixtures  with scores loaded
     * @param  Collection<int, TournamentFinal>  $finals
     */
    private function __construct(
        public readonly Tournament $tournament,
        public readonly Collection $teams,
        public readonly Collection $fixtures,
        private readonly Collection $finals,
    ) {}

    public static function for(Tournament $tournament, bool $withSquads = false): self
    {
        return new self(
            $tournament,
            $tournament->teams()->with($withSquads ? ['players.user'] : [])->get(),
            $tournament->fixtures()->with('scores')->get(),
            $tournament->finals()->get(),
        );
    }

    public function team(?int $id): ?TournamentTeam
    {
        return $this->teams->firstWhere('id', $id);
    }

    public function score(TournamentFixture $fixture, Division $division): ?TournamentScore
    {
        return $fixture->scores->first(fn (TournamentScore $score) => $score->division === $division);
    }

    public function final(Division $division): ?TournamentFinal
    {
        return $this->finals->first(fn (TournamentFinal $final) => $final->division === $division);
    }

    // ------------------------------------------------------------ standings

    /**
     * Ranked on points, then score difference, then scored, then list order.
     * No head-to-head: with four teams it rarely separates anyone, and a rule
     * people can check on their fingers beats one they have to take on trust.
     *
     * @return Collection<int, TournamentStanding>
     */
    public function standings(Division $division): Collection
    {
        $lines = $this->teams->mapWithKeys(fn (TournamentTeam $team) => [$team->id => new TournamentStanding($team)]);

        foreach ($this->fixtures as $fixture) {
            $score = $this->score($fixture, $division);

            if (! $score || ! $lines->has($fixture->home_team_id) || ! $lines->has($fixture->away_team_id)) {
                continue;
            }

            $lines[$fixture->home_team_id] = $lines[$fixture->home_team_id]->withResult($score->home_score, $score->away_score);
            $lines[$fixture->away_team_id] = $lines[$fixture->away_team_id]->withResult($score->away_score, $score->home_score);
        }

        return $this->rank($lines);
    }

    /**
     * Both divisions' lines added together. Information only — there is no
     * combined champion, and the finals never read this.
     *
     * @return Collection<int, TournamentStanding>
     */
    public function combined(): Collection
    {
        $women = $this->standings(Division::Women)->keyBy(fn (TournamentStanding $line) => $line->team->id);

        return $this->rank(
            $this->standings(Division::Men)->map(fn (TournamentStanding $line) => $line->plus($women[$line->team->id]))
        );
    }

    /**
     * @param  Collection<int, TournamentStanding>  $lines
     * @return Collection<int, TournamentStanding>
     */
    private function rank(Collection $lines): Collection
    {
        $position = $this->teams->pluck('id')->flip();

        // Descending on the first three, ascending on list position.
        return $lines
            ->sort(fn (TournamentStanding $a, TournamentStanding $b) => [$b->points(), $b->difference(), $b->scoredFor, $position[$a->team->id]]
                <=> [$a->points(), $a->difference(), $a->scoredFor, $position[$b->team->id]])
            ->values();
    }

    // ---------------------------------------------------------------- final

    public function groupStageComplete(Division $division): bool
    {
        return $this->fixtures->isNotEmpty()
            && $this->fixtures->every(fn (TournamentFixture $fixture) => $this->score($fixture, $division) !== null);
    }

    /**
     * The top two once every group match in the division has a score.
     *
     * @return array{0: TournamentTeam, 1: TournamentTeam}|null
     */
    public function seededFinalists(Division $division): ?array
    {
        if ($this->teams->count() < 2 || ! $this->groupStageComplete($division)) {
            return null;
        }

        $top = $this->standings($division);

        return [$top[0]->team, $top[1]->team];
    }

    /**
     * A group score was corrected after the final was saved, and the table no
     * longer agrees with who played in it. The final keeps its teams until an
     * admin clears it; this is what tells them to.
     */
    public function finalistsOutOfDate(Division $division): bool
    {
        $final = $this->final($division);

        if (! $final) {
            return false;
        }

        $seeded = $this->seededFinalists($division);

        return $seeded === null
            || [$final->home_team_id, $final->away_team_id] !== [$seeded[0]->id, $seeded[1]->id];
    }
}
