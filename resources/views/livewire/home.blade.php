@use('App\Enums\RsvpResponse')

<div>
    {{-- Hero. The Malay slang is deliberate — the tone is part of the product. --}}
    <div class="relative border-b border-ink-3 px-5 pt-16 pb-12 text-center">
        <x-ui.eyebrow>Aug — Nov 2026 · Timbang hari Isnin</x-ui.eyebrow>
        <h1 class="mt-4 font-display text-[clamp(52px,11vw,110px)] leading-[0.86] font-bold uppercase">
            <span class="text-bone">New week,</span><br>
            <span class="text-blood [text-shadow:0_0_30px_rgb(177_18_38_/_0.35)]">New weight</span>
        </h1>
        <p class="mt-5 font-cond text-sm tracking-[0.3em] text-bone-dim uppercase">
            Disiplin · Komitmen · <b class="text-gold-bright">Konsisten</b>
        </p>
    </div>

    <div class="mx-auto max-w-4xl px-5">
        <div class="my-10">
            <x-ui.standings :standings="$standings" />
        </div>

        {{-- Collection progress: admins only. Staff have no business seeing who's late. --}}
        @if ($compliance)
            <div class="mb-6 flex flex-wrap items-center justify-between gap-5 border border-gold bg-gradient-to-b from-gold/8 to-transparent px-6 py-5">
                <div>
                    <div class="font-display text-xl font-semibold tracking-wide uppercase">
                        {{ $week->shortLabel() }} · {{ $compliance->recordedCount() }} of
                        {{ $compliance->expectedCount() }} recorded
                    </div>
                    <div class="font-cond text-[15px] tracking-wide text-bone-dim uppercase">
                        @if ($compliance->isComplete())
                            All departments in. Nice.
                        @else
                            {{ $compliance->outstandingDepartmentCount() }}
                            {{ Str::plural('department', $compliance->outstandingDepartmentCount()) }} outstanding ·
                            closes {{ $week->closesAt()->format('D g:ia') }}
                        @endif
                    </div>
                </div>
                <x-ui.btn as="a" href="{{ route('weigh-in') }}">Record weigh-ins →</x-ui.btn>
            </div>
        @else
            <div class="mb-6 border border-ink-3 bg-ink-2 px-6 py-5 text-center">
                <div class="font-display text-xl font-semibold tracking-wide uppercase">
                    {{ $week->label() }}
                </div>
                <div class="mt-1 font-cond text-[15px] tracking-wide text-bone-dim uppercase">
                    Your weight is recorded for you at the Monday weigh-in.
                    <a href="{{ route('me') }}" class="text-gold hover:text-gold-bright">See your progress →</a>
                </div>
            </div>
        @endif

        {{-- Events --}}
        <x-ui.section-head>
            Agenda Majlis
            <a href="{{ route('events') }}"
               class="ml-auto font-cond text-xs font-semibold tracking-wide-cond text-bone-dim normal-case hover:text-gold">
                All events →
            </a>
        </x-ui.section-head>

        <div class="grid gap-3 pb-14">
            @forelse ($events as $event)
                @php $mine = $myResponses[$event->id] ?? null; @endphp
                <div class="grid grid-cols-[56px_1fr] items-center gap-4 border border-ink-3 bg-ink-2 px-5 py-4 sm:grid-cols-[64px_1fr_auto] sm:gap-[18px]"
                     wire:key="event-{{ $event->id }}">
                    <div class="border-r border-ink-3 pr-3 text-center">
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
                            {{ $event->starts_at->format('g:ia') }}
                            @if ($event->location) · {{ $event->location }} @endif
                            · {{ $event->goingCount() }} going
                            @if ($event->checkInIsOpen())
                                · <span class="text-gold">{{ $event->attendedCount() }} here</span>
                            @endif
                        </div>
                    </div>

                    <div class="col-start-2 sm:col-start-auto">
                        @include('livewire.partials.event-actions', [
                            'event' => $event,
                            'myResponse' => $mine,
                            'attended' => isset($myAttendance[$event->id]),
                        ])
                    </div>
                </div>
            @empty
                <p class="border border-ink-3 bg-ink-2 px-6 py-8 text-center font-cond text-sm tracking-wide text-bone-dim uppercase">
                    No events scheduled yet.
                </p>
            @endforelse
        </div>
    </div>
</div>
