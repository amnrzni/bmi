<?php

namespace App\Services;

use App\Enums\RsvpResponse;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\EventResponse;
use App\Models\Team;
use App\Models\User;
use App\Support\TeamTurnout;
use Illuminate\Support\Collection;

/**
 * Event turnout, the second half of the scoring model.
 *
 * Denominator is deliberately simple — past events × current team members —
 * rather than working out which events each person could have attended. It is
 * slightly unkind to late joiners, and that is an accepted trade: turnout is a
 * side figure here, not the ranking, and a number people can explain to each
 * other beats a fairer one they can't.
 */
class ParticipationService
{
    /**
     * Record an RSVP. Shared by the landing strip and the events page so the
     * deadline rule can't drift between them.
     */
    public function rsvp(User $user, Event $event, RsvpResponse $response): EventResponse
    {
        abort_unless($event->rsvpIsOpen(), 403, 'RSVP has closed for this event.');

        return EventResponse::updateOrCreate(
            ['event_id' => $event->id, 'user_id' => $user->id],
            ['response' => $response, 'responded_at' => now()],
        );
    }

    /**
     * Check someone in. Callers pass the authenticated user and never an
     * arbitrary id, so nobody can check in on another person's behalf.
     *
     * Idempotent: checking in twice is a no-op, not a duplicate or an error.
     */
    public function checkIn(User $user, Event $event): EventAttendance
    {
        abort_unless($event->checkInIsOpen(), 403, 'Check-in is not open for this event.');

        return EventAttendance::firstOrCreate(
            ['event_id' => $event->id, 'user_id' => $user->id],
            ['checked_in_at' => now(), 'checked_in_by_user_id' => $user->id],
        );
    }

    /** Admin correction for a check-in that shouldn't be there. */
    public function removeCheckIn(Event $event, User $user): void
    {
        EventAttendance::where('event_id', $event->id)->where('user_id', $user->id)->delete();
    }

    /** Events that have already happened; the only ones turnout counts against. */
    public function pastEventIds(): Collection
    {
        return Event::past()->pluck('id');
    }

    public function teamTurnout(Team $team, ?Collection $pastEventIds = null): TeamTurnout
    {
        $pastEventIds ??= $this->pastEventIds();

        $memberIds = User::participants()->where('team_id', $team->id)->pluck('id');
        $opportunities = $pastEventIds->count() * $memberIds->count();

        $attended = $opportunities === 0 ? 0 : EventAttendance::query()
            ->whereIn('event_id', $pastEventIds)
            ->whereIn('user_id', $memberIds)
            ->count();

        return new TeamTurnout(
            team: $team,
            memberCount: $memberIds->count(),
            pastEventCount: $pastEventIds->count(),
            attended: $attended,
            opportunities: $opportunities,
            rate: $opportunities === 0 ? null : round($attended / $opportunities * 100, 1),
        );
    }

    /**
     * @param  Collection<int,Team>  $teams
     * @return Collection<int,TeamTurnout> keyed by team id
     */
    public function turnouts(Collection $teams): Collection
    {
        // Resolve the past-event list once rather than per team.
        $pastEventIds = $this->pastEventIds();

        return $teams->mapWithKeys(fn (Team $team) => [
            $team->id => $this->teamTurnout($team, $pastEventIds),
        ]);
    }

    /**
     * One person's attendance, for their own view only.
     *
     * @return array{attended: int, opportunities: int}
     */
    public function forUser(User $user): array
    {
        $pastEventIds = $this->pastEventIds();

        return [
            'attended' => $pastEventIds->isEmpty() ? 0 : EventAttendance::query()
                ->where('user_id', $user->id)
                ->whereIn('event_id', $pastEventIds)
                ->count(),
            'opportunities' => $pastEventIds->count(),
        ];
    }
}
