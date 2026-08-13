@php
    $inputClass = 'w-full border border-ink-3 bg-ink px-3 py-2.5 font-cond text-[15px] text-bone outline-none focus:border-gold';
    $labelClass = 'mb-2 block font-cond text-[13px] tracking-label text-bone-dim uppercase';
@endphp

<div class="mx-auto max-w-3xl px-5 py-12 pb-20">
    <x-ui.eyebrow class="text-center">Agenda Majlis</x-ui.eyebrow>
    <h1 class="mt-3 mb-8 text-center font-display text-4xl leading-[0.9] font-bold uppercase sm:text-5xl">
        Events &amp; <span class="text-blood">Turnout</span>
    </h1>

    @if ($flash)
        <div class="mb-5 border border-gold bg-gold/10 px-4 py-3 font-cond text-sm tracking-wide text-bone">
            {{ $flash }}
        </div>
    @endif

    {{-- Your own attendance, no ranking against anyone else. --}}
    @if ($mine['opportunities'] > 0)
        <p class="mb-6 text-center font-cond text-sm tracking-wide text-bone-dim uppercase">
            You've made <b class="text-gold">{{ $mine['attended'] }}</b> of
            {{ $mine['opportunities'] }} {{ Str::plural('event', $mine['opportunities']) }} so far
        </p>
    @endif

    @if ($isAdmin)
        <div class="mb-5 flex justify-end">
            @unless ($showForm)
                <x-ui.btn wire:click="newEvent" variant="gold" class="px-5 py-2.5 text-sm">+ New event</x-ui.btn>
            @endunless
        </div>

        @if ($showForm)
            <x-ui.panel class="mb-8">
                <h2 class="mb-5 font-display text-xl font-bold tracking-wide uppercase">
                    {{ $editingId ? 'Edit event' : 'New event' }}
                </h2>

                <div class="space-y-4">
                    <div>
                        <label for="title" class="{{ $labelClass }}">Title</label>
                        <input id="title" type="text" wire:model="title" class="{{ $inputClass }}">
                        @error('title') <p class="mt-1 font-cond text-sm text-blood-bright">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="startsAt" class="{{ $labelClass }}">Starts</label>
                            <input id="startsAt" type="datetime-local" wire:model="startsAt" class="{{ $inputClass }}">
                            @error('startsAt') <p class="mt-1 font-cond text-sm text-blood-bright">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="rsvpDeadline" class="{{ $labelClass }}">RSVP closes <span class="normal-case">(optional)</span></label>
                            <input id="rsvpDeadline" type="datetime-local" wire:model="rsvpDeadline" class="{{ $inputClass }}">
                            @error('rsvpDeadline') <p class="mt-1 font-cond text-sm text-blood-bright">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label for="location" class="{{ $labelClass }}">Location</label>
                        <input id="location" type="text" wire:model="location" class="{{ $inputClass }}">
                    </div>

                    <div>
                        <label for="description" class="{{ $labelClass }}">Details</label>
                        <textarea id="description" rows="3" wire:model="description" class="{{ $inputClass }}"></textarea>
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <x-ui.btn wire:click="save" variant="gold" class="px-6 py-3 text-sm">
                        {{ $editingId ? 'Save changes' : 'Create event' }}
                    </x-ui.btn>
                    <x-ui.btn wire:click="cancel" variant="ghost" class="px-6 py-3 text-sm">Cancel</x-ui.btn>
                </div>

                <p class="mt-4 font-cond text-xs tracking-wide text-bone-dim uppercase">
                    Check-in opens {{ config('challenge.checkin_opens_before_minutes') }} minutes before the
                    start and closes at the end of that day.
                </p>
            </x-ui.panel>
        @endif
    @endif

    @foreach ([['Coming up', $upcoming], ['Been and gone', $past]] as [$heading, $events])
        <x-ui.section-head>{{ $heading }}</x-ui.section-head>

        <div class="grid gap-3">
            @forelse ($events as $event)
                <div class="border border-ink-3 bg-ink-2 px-5 py-4" wire:key="event-{{ $event->id }}">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="flex min-w-0 gap-4">
                            <div class="border-r border-ink-3 pr-4 text-center">
                                <div class="font-display text-[26px] leading-none font-bold text-gold">
                                    {{ $event->starts_at->format('j') }}
                                </div>
                                <div class="font-cond text-[11px] tracking-label text-bone-dim uppercase">
                                    {{ $event->starts_at->format('M') }}
                                </div>
                            </div>

                            <div class="min-w-0">
                                <div class="font-display text-[17px] font-semibold tracking-wide uppercase">
                                    {{ $event->title }}
                                </div>
                                <div class="font-cond text-sm tracking-wide text-bone-dim uppercase">
                                    {{ $event->starts_at->format('D g:ia') }}
                                    @if ($event->location) · {{ $event->location }} @endif
                                </div>
                                <div class="font-cond text-xs tracking-wide-cond text-bone-dim uppercase">
                                    {{ $event->goingCount() }} going
                                    @if ($event->starts_at->isPast() || $event->checkInIsOpen())
                                        · <span class="text-gold">{{ $event->attendedCount() }} turned up</span>
                                    @endif
                                    @if ($event->rsvp_deadline && $event->rsvpIsOpen())
                                        · RSVP by {{ $event->rsvp_deadline->format('j M') }}
                                    @endif
                                </div>
                                @if ($event->description)
                                    <p class="mt-1.5 font-sans text-sm text-bone-dim">{{ $event->description }}</p>
                                @endif
                            </div>
                        </div>

                        @include('livewire.partials.event-actions', [
                            'event' => $event,
                            'myResponse' => $myResponses[$event->id] ?? null,
                            'attended' => isset($myAttendance[$event->id]),
                        ])
                    </div>

                    @if ($isAdmin)
                        <div class="mt-3 flex flex-wrap gap-4 border-t border-ink-3 pt-3">
                            @foreach ([['edit('.$event->id.')', 'Edit'], ['exportAttendees('.$event->id.')', 'Export attendees']] as [$action, $label])
                                <button type="button" wire:click="{{ $action }}"
                                        class="cursor-pointer font-cond text-xs tracking-wide-cond text-bone-dim uppercase transition hover:text-gold">
                                    {{ $label }}
                                </button>
                            @endforeach
                            <button type="button" wire:click="delete({{ $event->id }})"
                                    wire:confirm="Remove &quot;{{ $event->title }}&quot;? RSVPs and check-ins are kept."
                                    class="cursor-pointer font-cond text-xs tracking-wide-cond text-bone-dim uppercase transition hover:text-blood-bright">
                                Remove
                            </button>
                        </div>
                    @endif
                </div>
            @empty
                <p class="border border-ink-3 bg-ink-2 px-6 py-8 text-center font-cond text-sm tracking-wide text-bone-dim uppercase">
                    {{ $heading === 'Coming up' ? 'Nothing scheduled yet.' : 'No events have happened yet.' }}
                </p>
            @endforelse
        </div>
    @endforeach
</div>
