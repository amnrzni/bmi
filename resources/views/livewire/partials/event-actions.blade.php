{{--
    RSVP and check-in controls, shared by the landing strip and the events page
    so the two can't drift apart. Expects $event, $myResponse, $attended.

    Check-in takes priority once its window opens: on the day, turning up is the
    only thing that still matters.
--}}
@use('App\Enums\RsvpResponse')

<div class="flex flex-wrap gap-1.5">
    @if ($attended)
        <span class="rounded-sm border border-gold bg-gold/15 px-4 py-2 font-cond text-xs font-semibold tracking-wide-cond text-gold-bright uppercase">
            Checked in ✓
        </span>
    @elseif ($event->checkInIsOpen())
        <button type="button" wire:click="checkIn({{ $event->id }})"
                class="cursor-pointer rounded-sm border border-transparent bg-blood px-4 py-2 font-cond text-xs font-semibold tracking-wide-cond text-bone uppercase transition hover:bg-blood-bright">
            I'm here
        </button>
    @elseif ($event->starts_at->isPast())
        <span class="rounded-sm border border-ink-3 px-4 py-2 font-cond text-xs font-semibold tracking-wide-cond text-bone-dim uppercase">
            {{ $myResponse === RsvpResponse::Yes ? 'Missed it' : 'Done' }}
        </span>
    @elseif (! $event->rsvpIsOpen())
        <span class="rounded-sm border border-ink-3 px-4 py-2 font-cond text-xs font-semibold tracking-wide-cond text-bone-dim uppercase">
            RSVP closed
        </span>
    @else
        @foreach (RsvpResponse::cases() as $option)
            <button type="button" wire:click="rsvp({{ $event->id }}, '{{ $option->value }}')"
                    @class([
                        'cursor-pointer rounded-sm border px-4 py-2 font-cond text-xs font-semibold tracking-wide-cond uppercase transition',
                        'border-gold bg-gold text-ink' => $myResponse === $option,
                        'border-gold bg-transparent text-gold hover:bg-gold hover:text-ink' => $myResponse !== $option && $option === RsvpResponse::Yes,
                        'border-ink-3 bg-transparent text-bone-dim hover:text-bone' => $myResponse !== $option && $option === RsvpResponse::No,
                    ])>
                {{ $option === RsvpResponse::Yes ? 'Going' : 'Cannot' }}
            </button>
        @endforeach
    @endif
</div>
