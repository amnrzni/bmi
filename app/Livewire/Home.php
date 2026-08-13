<?php

namespace App\Livewire;

use App\Enums\RsvpResponse;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\EventResponse;
use App\Models\Team;
use App\Models\User;
use App\Services\ParticipationService;
use App\Services\ProgressService;
use App\Support\ChallengeWeek;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Landing page.
 *
 * Team standings are framed neutrally — no us-vs-them, no favoured side — and
 * measured as average % change from baseline (HANDOFF.md §5.1). Turnout sits
 * beside that figure rather than being folded into it.
 */
#[Layout('components.layouts.app')]
#[Title('BMI Challenge')]
class Home extends Component
{
    public function rsvp(int $eventId, string $response, ParticipationService $participation): void
    {
        $participation->rsvp(auth()->user(), Event::findOrFail($eventId), RsvpResponse::from($response));
    }

    /** Always the authenticated user — an id is never accepted from the client. */
    public function checkIn(int $eventId, ParticipationService $participation): void
    {
        $participation->checkIn(auth()->user(), Event::findOrFail($eventId));
    }

    public function render(ProgressService $progress)
    {
        $week = ChallengeWeek::current();

        $members = User::participants()->with('weighIns')->get();
        $teams = Team::orderBy('sort_order')->get();

        $standings = $teams->map(fn (Team $team) => $progress->teamStanding(
            $team,
            $members->where('team_id', $team->id)->values(),
        ));

        // Events happening today come first — that's when check-in matters.
        $events = Event::query()
            ->where('starts_at', '>=', now()->startOfDay())
            ->orderBy('starts_at')
            ->with(['responses', 'attendances'])
            ->take(4)
            ->get();

        return view('livewire.home', [
            'week' => $week,
            'standings' => $standings,
            'compliance' => auth()->user()->isAdmin() ? $progress->compliance($week) : null,
            'events' => $events,
            'myResponses' => EventResponse::where('user_id', auth()->id())
                ->whereIn('event_id', $events->pluck('id'))
                ->pluck('response', 'event_id'),
            'myAttendance' => EventAttendance::where('user_id', auth()->id())
                ->whereIn('event_id', $events->pluck('id'))
                ->pluck('checked_in_at', 'event_id'),
        ]);
    }
}
