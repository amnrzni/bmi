<?php

namespace App\Livewire;

use App\Enums\BmiCategory;
use App\Models\EventResponse;
use App\Models\Team;
use App\Services\ParticipationService;
use App\Services\ProgressService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The staff-facing view, and the screen regular staff actually use.
 *
 * Progress-focused by design: their own line, their own baseline, no rank and
 * no comparison to a named colleague. Telling someone they're worst on their
 * team is demotivating and gets personal in a workplace (HANDOFF.md §7).
 */
#[Layout('components.layouts.app')]
#[Title('My Progress — BMI Challenge')]
class MyProgress extends Component
{
    public function render(ProgressService $progress, ParticipationService $participation)
    {
        $user = auth()->user()->load(['weighIns.recordedBy', 'team']);

        $summary = $progress->summary($user);
        $series = $progress->series($user);
        $changes = $progress->weekOverWeek($user);

        // Team context, kept gentle: their team's overall figure and where the
        // team sits — never the person's own standing within it.
        $teamStanding = null;
        $teamRank = null;
        $teamCount = null;

        if ($user->team) {
            $teams = Team::all();
            $teamCount = $teams->count();

            $standings = $teams
                ->map(fn (Team $team) => $progress->teamStanding($team))
                ->sortBy([
                    fn ($s) => $s->hasData() ? 0 : 1,
                    fn ($s) => $s->averagePercentChange ?? INF,
                ])
                ->values();

            $teamStanding = $standings->firstWhere(fn ($s) => $s->team->id === $user->team_id);

            // Only meaningful once the team actually has a figure to rank on.
            if ($teamStanding?->hasData()) {
                $teamRank = $standings->search(fn ($s) => $s->team->id === $user->team_id) + 1;
            }
        }

        return view('livewire.my-progress', [
            'summary' => $summary,
            'series' => $series,
            'changes' => $changes,
            'category' => BmiCategory::forBmi($summary->currentBmi),
            'teamStanding' => $teamStanding,
            'teamRank' => $teamRank,
            'teamCount' => $teamCount,
            // Their own attendance only — a count, never a rank against anyone.
            'attendance' => $participation->forUser($user),
            'myEvents' => EventResponse::with('event')
                ->where('user_id', $user->id)
                ->whereHas('event', fn ($query) => $query->where('starts_at', '>=', now()))
                ->get(),
            'chartPoints' => $series->map(fn ($record) => [
                'label' => $record->week()->shortLabel(),
                'value' => (float) $record->weight_kg,
            ])->values()->all(),
        ]);
    }
}
