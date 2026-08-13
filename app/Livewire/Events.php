<?php

namespace App\Livewire;

use App\Enums\RsvpResponse;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\EventResponse;
use App\Models\User;
use App\Services\ParticipationService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One events page for everyone; only the controls differ by role.
 *
 * Staff get RSVP and their own check-in. Admins additionally get the create/edit
 * form, delete, and the attendee export. A single screen rather than two,
 * because the list itself is the same thing either way.
 */
#[Layout('components.layouts.app')]
#[Title('Events — BMI Challenge')]
class Events extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    #[Validate('required|string|max:255')]
    public string $title = '';

    #[Validate('nullable|string|max:2000')]
    public string $description = '';

    #[Validate('required|date')]
    public string $startsAt = '';

    #[Validate('nullable|string|max:255')]
    public string $location = '';

    // A deadline after the event has started is always a mistake.
    #[Validate('nullable|date|before_or_equal:startsAt')]
    public string $rsvpDeadline = '';

    public ?string $flash = null;

    // ------------------------------------------------------------------ admin

    private function assertAdmin(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
    }

    public function newEvent(): void
    {
        $this->assertAdmin();
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $eventId): void
    {
        $this->assertAdmin();

        $event = Event::findOrFail($eventId);

        $this->editingId = $event->id;
        $this->title = $event->title;
        $this->description = (string) $event->description;
        $this->startsAt = $event->starts_at->format('Y-m-d\TH:i');
        $this->location = (string) $event->location;
        $this->rsvpDeadline = $event->rsvp_deadline?->format('Y-m-d\TH:i') ?? '';
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->assertAdmin();
        $this->validate();

        $attributes = [
            'title' => $this->title,
            'description' => $this->description ?: null,
            'starts_at' => $this->startsAt,
            'location' => $this->location ?: null,
            'rsvp_deadline' => $this->rsvpDeadline ?: null,
        ];

        if ($this->editingId) {
            Event::findOrFail($this->editingId)->update($attributes);
            $this->flash = 'Event updated.';
        } else {
            Event::create([...$attributes, 'created_by_user_id' => auth()->id()]);
            $this->flash = 'Event created.';
        }

        $this->resetForm();
        $this->showForm = false;
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    /**
     * Soft delete: RSVPs and check-ins stay on the row, so a deletion by mistake
     * doesn't destroy attendance history that turnout is calculated from.
     */
    public function delete(int $eventId): void
    {
        $this->assertAdmin();

        $event = Event::findOrFail($eventId);
        $event->delete();

        $this->flash = "\"{$event->title}\" was removed.";
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'title', 'description', 'startsAt', 'location', 'rsvpDeadline']);
        $this->resetValidation();
    }

    // ------------------------------------------------------------ participation

    public function rsvp(int $eventId, string $response, ParticipationService $participation): void
    {
        $participation->rsvp(auth()->user(), Event::findOrFail($eventId), RsvpResponse::from($response));
    }

    /** Always the authenticated user — an id is never accepted from the client. */
    public function checkIn(int $eventId, ParticipationService $participation): void
    {
        $event = Event::findOrFail($eventId);
        $participation->checkIn(auth()->user(), $event);

        $this->flash = "You're checked in for \"{$event->title}\".";
    }

    public function exportAttendees(int $eventId): StreamedResponse
    {
        $this->assertAdmin();

        $event = Event::with(['responses.user.department', 'responses.user.team'])->findOrFail($eventId);
        $attendedIds = $event->attendances()->pluck('user_id')->all();

        $rows = User::participants()
            ->with(['department', 'team'])
            ->orderBy('name')
            ->get();

        $responses = $event->responses->keyBy('user_id');

        return response()->streamDownload(function () use ($rows, $responses, $attendedIds) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Name', 'Department', 'Team', 'RSVP', 'Attended']);

            foreach ($rows as $person) {
                fputcsv($handle, [
                    $person->displayName(),
                    $person->department?->name,
                    $person->team?->code,
                    $responses[$person->id]?->response->value ?? '',
                    in_array($person->id, $attendedIds, true) ? 'yes' : 'no',
                ]);
            }

            fclose($handle);
        }, 'attendees-'.str($event->title)->slug().'.csv', ['Content-Type' => 'text/csv']);
    }

    // ----------------------------------------------------------------- render

    public function render(ParticipationService $participation)
    {
        $with = ['responses', 'attendances'];

        $upcoming = Event::upcoming()->with($with)->get();
        $past = Event::past()->with($with)->get();
        $all = $upcoming->concat($past);

        return view('livewire.events', [
            'upcoming' => $upcoming,
            'past' => $past,
            'myResponses' => $this->myResponses($all),
            'myAttendance' => $this->myAttendance($all),
            'mine' => $participation->forUser(auth()->user()),
            'isAdmin' => auth()->user()->isAdmin(),
        ]);
    }

    /** @param  Collection<int,Event>  $events */
    private function myResponses(Collection $events): Collection
    {
        return EventResponse::where('user_id', auth()->id())
            ->whereIn('event_id', $events->pluck('id'))
            ->pluck('response', 'event_id');
    }

    /** @param  Collection<int,Event>  $events */
    private function myAttendance(Collection $events): Collection
    {
        return EventAttendance::where('user_id', auth()->id())
            ->whereIn('event_id', $events->pluck('id'))
            ->pluck('checked_in_at', 'event_id');
    }
}
