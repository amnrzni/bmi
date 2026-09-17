@php
    $inputClass = 'w-full border border-ink-3 bg-ink px-3 py-2.5 font-cond text-[15px] text-bone outline-none focus:border-gold';
    $labelClass = 'mb-2 block font-cond text-[13px] tracking-label text-bone-dim uppercase';
@endphp

<div>
    @include('livewire.tournaments.partials.guest-bar')

    <div class="mx-auto max-w-3xl px-5 py-12 pb-20">
        <x-ui.eyebrow class="text-center">Hari Sukan</x-ui.eyebrow>
        <h1 class="mt-3 mb-8 text-center font-display text-4xl leading-[0.9] font-bold uppercase sm:text-5xl">
            Tour<span class="text-blood">naments</span>
        </h1>

        @if ($isAdmin)
            <div class="mb-5 flex justify-end">
                @unless ($showForm)
                    <x-ui.btn wire:click="newTournament" variant="gold" class="px-5 py-2.5 text-sm">+ New tournament</x-ui.btn>
                @endunless
            </div>

            @if ($showForm)
                <x-ui.panel class="mb-8">
                    <h2 class="mb-5 font-display text-xl font-bold tracking-wide uppercase">New tournament</h2>

                    <div class="space-y-4">
                        <div>
                            <label for="name" class="{{ $labelClass }}">Name</label>
                            <input id="name" type="text" wire:model="name" class="{{ $inputClass }}">
                            @error('name') <p class="mt-1 font-cond text-sm text-blood-bright">{{ $message }}</p> @enderror
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="startsAt" class="{{ $labelClass }}">Starts</label>
                                <input id="startsAt" type="datetime-local" wire:model="startsAt" class="{{ $inputClass }}">
                                @error('startsAt') <p class="mt-1 font-cond text-sm text-blood-bright">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="location" class="{{ $labelClass }}">Location</label>
                                <input id="location" type="text" wire:model="location" class="{{ $inputClass }}">
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <x-ui.btn wire:click="create" wire:loading.attr="disabled" variant="gold" class="px-6 py-3 text-sm">Create &amp; set up</x-ui.btn>
                        <x-ui.btn wire:click="cancel" variant="ghost" class="px-6 py-3 text-sm">Cancel</x-ui.btn>
                    </div>
                </x-ui.panel>
            @endif
        @endif

        <div class="grid gap-3">
            @forelse ($tournaments as $tournament)
                <a href="{{ route('tournaments.show', $tournament) }}" wire:key="tournament-{{ $tournament->id }}"
                   class="flex items-center gap-4 border border-ink-3 bg-ink-2 px-5 py-4 transition hover:border-gold">
                    <div class="border-r border-ink-3 pr-4 text-center">
                        <div class="font-display text-[26px] leading-none font-bold text-gold">
                            {{ $tournament->starts_at?->format('j') ?? '—' }}
                        </div>
                        <div class="font-cond text-[11px] tracking-label text-bone-dim uppercase">
                            {{ $tournament->starts_at?->format('M Y') ?? 'TBC' }}
                        </div>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="font-display text-[17px] font-semibold tracking-wide uppercase">{{ $tournament->name }}</div>
                        <div class="font-cond text-sm tracking-wide text-bone-dim uppercase">
                            @if ($tournament->starts_at) {{ $tournament->starts_at->format('D g:ia') }} @endif
                            @if ($tournament->location) · {{ $tournament->location }} @endif
                        </div>
                        <div class="font-cond text-xs tracking-wide-cond text-bone-dim uppercase">
                            {{ $tournament->teams_count }} {{ Str::plural('team', $tournament->teams_count) }}
                            · {{ $tournament->fixtures_count }} {{ Str::plural('match', $tournament->fixtures_count) }}
                        </div>
                    </div>
                    <span class="flex-none font-display text-xl text-gold" aria-hidden="true">›</span>
                </a>
            @empty
                <p class="border border-ink-3 bg-ink-2 px-6 py-8 text-center font-cond text-sm tracking-wide text-bone-dim uppercase">
                    No tournaments yet.
                </p>
            @endforelse
        </div>
    </div>
</div>
