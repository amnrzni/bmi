<?php

namespace App\Livewire;

use App\Models\Team;
use App\Models\User;
use App\Services\ProgressService;
use App\Support\ChallengeWeek;
use App\Support\ProgressSummary;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin analytics: the weekly grid and the deltas summary.
 *
 * A separate change-only table was considered and rejected — the Change mode on
 * the grid covers it (HANDOFF.md §5.4). Compliance is a mode here too, rather
 * than a screen of its own.
 */
#[Layout('components.layouts.app')]
#[Title('Analytics — BMI Challenge')]
class Analytics extends Component
{
    /** weight | bmi | change | compliance */
    #[Url]
    public string $metric = 'weight';

    /** all, or a team code. */
    #[Url]
    public string $team = 'all';

    #[Url]
    public string $sort = 'percent';

    #[Url]
    public string $direction = 'asc';

    public function setMetric(string $metric): void
    {
        $this->metric = $metric;
    }

    public function setTeam(string $team): void
    {
        $this->team = $team;
    }

    public function sortBy(string $column): void
    {
        if ($this->sort === $column) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';

            return;
        }

        $this->sort = $column;
        // Ascending suits every column here: names read A–Z, and deltas read
        // smallest-first so the biggest loss lands on top.
        $this->direction = 'asc';
    }

    /** @return Collection<int,User> */
    private function participants(): Collection
    {
        return User::participants()
            ->with(['weighIns', 'team', 'department'])
            ->orderBy('name')
            ->get()
            ->when($this->team !== 'all', fn (Collection $users) => $users->filter(
                fn (User $user) => $user->team?->code === $this->team
            )->values());
    }

    /**
     * @param  Collection<int,ProgressSummary>  $summaries
     * @return Collection<int,ProgressSummary>
     */
    private function sortSummaries(Collection $summaries): Collection
    {
        $key = fn (ProgressSummary $summary) => match ($this->sort) {
            'name' => $summary->user->name,
            'weeks' => $summary->weeksRecorded,
            'current' => $summary->currentWeight ?? INF,
            'delta' => $summary->deltaWeight ?? INF,
            'bmi' => $summary->currentBmi ?? INF,
            default => $summary->percentChange ?? INF,
        };

        $sorted = $this->direction === 'desc'
            ? $summaries->sortByDesc($key)
            : $summaries->sortBy($key);

        // People without enough data always sink to the bottom rather than
        // topping the leaderboard on a technicality.
        return $sorted->sortBy(fn (ProgressSummary $summary) => $summary->isRanked ? 0 : 1)->values();
    }

    public function export(ProgressService $progress): StreamedResponse
    {
        $summaries = $progress->summariesFor($this->participants());
        $filename = 'bmi-challenge-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($summaries) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Name', 'Department', 'Team', 'Weeks recorded', 'Ranked',
                'Start weight (kg)', 'Current weight (kg)', 'Change (kg)', 'Change (%)',
                'Start BMI', 'Current BMI', 'Change BMI',
            ]);

            foreach ($this->sortSummaries($summaries) as $summary) {
                fputcsv($handle, [
                    $summary->user->displayName(),
                    $summary->user->department?->name,
                    $summary->user->team?->code,
                    $summary->weeksRecorded,
                    $summary->isRanked ? 'yes' : 'no',
                    $summary->baselineWeight,
                    $summary->currentWeight,
                    $summary->deltaWeight,
                    $summary->percentChange,
                    $summary->baselineBmi,
                    $summary->currentBmi,
                    $summary->deltaBmi,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function render(ProgressService $progress)
    {
        $participants = $this->participants();
        $weeks = ChallengeWeek::elapsed();

        // One row per person: their weigh-ins keyed by week, plus week-over-week
        // deltas, so the Blade side does lookups rather than arithmetic.
        $grid = $participants->map(fn (User $user) => [
            'user' => $user,
            'byWeek' => $user->weighIns->keyBy(fn ($weighIn) => $weighIn->week_start_date->toDateString()),
            'changes' => $progress->weekOverWeek($user),
        ]);

        $allMembers = User::participants()->with('weighIns')->get();
        $teams = Team::orderBy('sort_order')->get();

        $standings = $teams->map(fn (Team $team) => $progress->teamStanding(
            $team,
            $allMembers->where('team_id', $team->id)->values(),
        ));

        return view('livewire.analytics', [
            'weeks' => $weeks,
            'grid' => $grid,
            'summaries' => $this->sortSummaries($progress->summariesFor($participants)),
            'standings' => $standings,
            'teams' => $teams,
            'minRank' => (int) config('challenge.min_records_rank'),
        ]);
    }
}
