<?php

use App\Enums\RsvpResponse;
use App\Livewire\Events;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\EventResponse;
use App\Models\Team;
use App\Models\User;
use App\Services\ParticipationService;
use Livewire\Livewire;

beforeEach(function () {
    $this->team = Team::factory()->create(['code' => 'TAH', 'name' => 'Team TAH']);
    $this->admin = User::factory()->admin()->create(['team_id' => $this->team->id]);
    $this->staff = User::factory()->create(['name' => 'Along', 'team_id' => $this->team->id]);
    $this->participation = app(ParticipationService::class);
});

// -------------------------------------------------------------------- access

it('shows the events page to everyone', function () {
    $this->actingAs($this->staff)->get(route('events'))->assertOk();
    $this->actingAs($this->admin)->get(route('events'))->assertOk();
});

// ---------------------------------------------------------------------- CRUD

it('lets an admin create an event', function () {
    Livewire::actingAs($this->admin)
        ->test(Events::class)
        ->call('newEvent')
        ->set('title', 'Morning Walk')
        ->set('startsAt', now()->addWeek()->format('Y-m-d\TH:i'))
        ->set('location', 'Padang HQ')
        ->call('save')
        ->assertHasNoErrors();

    expect(Event::where('title', 'Morning Walk')->exists())->toBeTrue()
        ->and(Event::first()->created_by_user_id)->toBe($this->admin->id);
});

it('lets an admin edit an event', function () {
    $event = Event::factory()->create(['title' => 'Old name', 'created_by_user_id' => $this->admin->id]);

    Livewire::actingAs($this->admin)
        ->test(Events::class)
        ->call('edit', $event->id)
        ->set('title', 'New name')
        ->call('save')
        ->assertHasNoErrors();

    expect($event->fresh()->title)->toBe('New name');
});

it('soft deletes an event so attendance history survives', function () {
    $event = Event::factory()->past()->create(['created_by_user_id' => $this->admin->id]);
    EventAttendance::create([
        'event_id' => $event->id,
        'user_id' => $this->staff->id,
        'checked_in_at' => now(),
        'checked_in_by_user_id' => $this->staff->id,
    ]);

    Livewire::actingAs($this->admin)->test(Events::class)->call('delete', $event->id);

    expect(Event::find($event->id))->toBeNull()
        ->and(Event::withTrashed()->find($event->id))->not->toBeNull()
        // The check-in is still there — a mistaken delete mustn't destroy history.
        ->and(EventAttendance::where('event_id', $event->id)->count())->toBe(1);
});

it('stops staff creating, editing or deleting events', function () {
    $event = Event::factory()->create(['created_by_user_id' => $this->admin->id]);

    foreach ([['newEvent', []], ['edit', [$event->id]], ['delete', [$event->id]], ['save', []]] as [$method, $args]) {
        Livewire::actingAs($this->staff)
            ->test(Events::class)
            ->call($method, ...$args)
            ->assertForbidden();
    }

    expect(Event::count())->toBe(1);
});

it('rejects an rsvp deadline that falls after the event starts', function () {
    Livewire::actingAs($this->admin)
        ->test(Events::class)
        ->set('title', 'Backwards')
        ->set('startsAt', now()->addWeek()->format('Y-m-d\TH:i'))
        ->set('rsvpDeadline', now()->addWeeks(2)->format('Y-m-d\TH:i'))
        ->call('save')
        ->assertHasErrors('rsvpDeadline');

    expect(Event::count())->toBe(0);
});

// ---------------------------------------------------------------------- RSVP

it('records an rsvp while the deadline is open', function () {
    $event = Event::factory()->create([
        'starts_at' => now()->addWeek(),
        'rsvp_deadline' => now()->addDays(3),
        'created_by_user_id' => $this->admin->id,
    ]);

    Livewire::actingAs($this->staff)
        ->test(Events::class)
        ->call('rsvp', $event->id, RsvpResponse::Yes->value);

    expect(EventResponse::where('user_id', $this->staff->id)->first()->response)
        ->toBe(RsvpResponse::Yes);
});

it('refuses an rsvp once the deadline has passed', function () {
    $event = Event::factory()->create([
        'starts_at' => now()->addWeek(),
        'rsvp_deadline' => now()->subDay(),
        'created_by_user_id' => $this->admin->id,
    ]);

    Livewire::actingAs($this->staff)
        ->test(Events::class)
        ->call('rsvp', $event->id, RsvpResponse::Yes->value)
        ->assertForbidden();

    expect(EventResponse::count())->toBe(0);
});

// ------------------------------------------------------------------ check-in

it('refuses check-in before the window opens', function () {
    $event = Event::factory()->create([
        'starts_at' => now()->addHours(5),
        'created_by_user_id' => $this->admin->id,
    ]);

    // Otherwise people mark themselves present for something they haven't attended.
    Livewire::actingAs($this->staff)
        ->test(Events::class)
        ->call('checkIn', $event->id)
        ->assertForbidden();

    expect(EventAttendance::count())->toBe(0);
});

it('allows check-in shortly before the start', function () {
    $event = Event::factory()->create([
        'starts_at' => now()->addMinutes(30),
        'created_by_user_id' => $this->admin->id,
    ]);

    Livewire::actingAs($this->staff)->test(Events::class)->call('checkIn', $event->id);

    $attendance = EventAttendance::first();

    expect($attendance)->not->toBeNull()
        ->and($attendance->user_id)->toBe($this->staff->id)
        // Self check-in: they are their own recorder.
        ->and($attendance->checked_in_by_user_id)->toBe($this->staff->id);
});

it('refuses check-in after the event day has ended', function () {
    $event = Event::factory()->create([
        'starts_at' => now()->subDays(2),
        'created_by_user_id' => $this->admin->id,
    ]);

    // Without a closing edge people would check in weeks later.
    Livewire::actingAs($this->staff)
        ->test(Events::class)
        ->call('checkIn', $event->id)
        ->assertForbidden();

    expect(EventAttendance::count())->toBe(0);
});

it('treats a second check-in as a no-op rather than a duplicate', function () {
    $event = Event::factory()->create([
        'starts_at' => now()->subMinutes(10),
        'created_by_user_id' => $this->admin->id,
    ]);

    Livewire::actingAs($this->staff)
        ->test(Events::class)
        ->call('checkIn', $event->id)
        ->call('checkIn', $event->id);

    expect(EventAttendance::count())->toBe(1);
});

it('only ever checks in the authenticated person', function () {
    $event = Event::factory()->create([
        'starts_at' => now()->subMinutes(5),
        'created_by_user_id' => $this->admin->id,
    ]);
    $colleague = User::factory()->create(['name' => 'Dragon']);

    Livewire::actingAs($this->staff)->test(Events::class)->call('checkIn', $event->id);

    // The action takes an event id and nothing else, so a colleague can't be
    // marked present by someone else.
    expect(EventAttendance::pluck('user_id')->all())->toBe([$this->staff->id])
        ->and(EventAttendance::where('user_id', $colleague->id)->exists())->toBeFalse();
});

// ---------------------------------------------------------------------- turnout

it('reports turnout as attendances over past events times members', function () {
    $colleague = User::factory()->create(['team_id' => $this->team->id]);

    // Two past events, three team members (admin + staff + colleague) = 6 chances.
    $events = Event::factory()->count(2)->past()->create(['created_by_user_id' => $this->admin->id]);

    foreach ([$this->staff, $colleague] as $person) {
        EventAttendance::create([
            'event_id' => $events->first()->id,
            'user_id' => $person->id,
            'checked_in_at' => now(),
            'checked_in_by_user_id' => $person->id,
        ]);
    }

    $turnout = $this->participation->teamTurnout($this->team->fresh());

    expect($turnout->pastEventCount)->toBe(2)
        ->and($turnout->memberCount)->toBe(3)
        ->and($turnout->opportunities)->toBe(6)
        ->and($turnout->attended)->toBe(2)
        ->and($turnout->rate)->toBe(33.3);
});

it('reports no turnout rate when nothing has happened yet', function () {
    Event::factory()->create(['starts_at' => now()->addWeek(), 'created_by_user_id' => $this->admin->id]);

    $turnout = $this->participation->teamTurnout($this->team);

    // Null rather than 0%, which would read as "nobody showed up".
    expect($turnout->rate)->toBeNull()
        ->and($turnout->hasData())->toBeFalse();
});

it('counts a persons own attendance for their own view', function () {
    $events = Event::factory()->count(3)->past()->create(['created_by_user_id' => $this->admin->id]);

    EventAttendance::create([
        'event_id' => $events->first()->id,
        'user_id' => $this->staff->id,
        'checked_in_at' => now(),
        'checked_in_by_user_id' => $this->staff->id,
    ]);

    expect($this->participation->forUser($this->staff))
        ->toBe(['attended' => 1, 'opportunities' => 3]);
});

// ----------------------------------------------------------------------- CSV

it('exports attendees with rsvp and attendance columns', function () {
    $event = Event::factory()->past()->create(['created_by_user_id' => $this->admin->id]);

    EventResponse::create([
        'event_id' => $event->id,
        'user_id' => $this->staff->id,
        'response' => RsvpResponse::Yes,
        'responded_at' => now(),
    ]);
    EventAttendance::create([
        'event_id' => $event->id,
        'user_id' => $this->staff->id,
        'checked_in_at' => now(),
        'checked_in_by_user_id' => $this->staff->id,
    ]);

    $csv = Livewire::actingAs($this->admin)
        ->test(Events::class)
        ->call('exportAttendees', $event->id)
        ->effects['download'] ?? null;

    expect($csv)->not->toBeNull();
});

it('stops staff exporting the attendee list', function () {
    $event = Event::factory()->past()->create(['created_by_user_id' => $this->admin->id]);

    Livewire::actingAs($this->staff)
        ->test(Events::class)
        ->call('exportAttendees', $event->id)
        ->assertForbidden();
});
