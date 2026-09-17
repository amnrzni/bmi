@use('App\Enums\Division')
@use('App\Models\Tournament')

@php
    $inputClass = 'w-full min-w-0 border border-ink-3 bg-ink px-3 py-2 font-cond text-[15px] text-bone outline-none focus:border-gold';
    $labelClass = 'mb-2 block font-cond text-[13px] tracking-label text-bone-dim uppercase';
    $smallBtn = 'cursor-pointer rounded-sm border px-3 py-1.5 font-cond text-xs font-semibold tracking-wide-cond uppercase transition disabled:cursor-not-allowed disabled:opacity-30';
    $iconBtn = 'flex h-8 w-8 flex-none cursor-pointer items-center justify-center rounded-sm border border-ink-3 font-cond text-sm text-bone-dim transition hover:text-bone disabled:cursor-not-allowed disabled:opacity-30';
    $error = 'mt-1 font-cond text-sm text-blood-bright';
@endphp

<div class="mx-auto max-w-4xl px-5 py-12 pb-20">
    <a href="{{ route('tournaments.show', $tournament) }}"
       class="font-cond text-sm tracking-wide text-bone-dim uppercase transition hover:text-gold">‹ Back to the tournament</a>

    <x-ui.eyebrow class="mt-6 text-center">Setup</x-ui.eyebrow>
    <h1 class="mt-3 mb-8 text-center font-display text-4xl leading-[0.95] font-bold uppercase sm:text-5xl">
        {{ $tournament->name }}
    </h1>

    @if ($flash)
        <div class="mb-5 border border-gold bg-gold/10 px-4 py-3 font-cond text-sm tracking-wide text-bone" role="status">
            {{ $flash }}
        </div>
    @endif

    {{-- ============================================================== details --}}
    <x-ui.section-head>Details</x-ui.section-head>
    <x-ui.panel>
        <div class="space-y-4">
            <div>
                <label for="name" class="{{ $labelClass }}">Name</label>
                <input id="name" type="text" wire:model="name" class="{{ $inputClass }}">
                @error('name') <p class="{{ $error }}">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="startsAt" class="{{ $labelClass }}">Starts</label>
                    <input id="startsAt" type="datetime-local" wire:model="startsAt" class="{{ $inputClass }}">
                    @error('startsAt') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="location" class="{{ $labelClass }}">Location</label>
                    <input id="location" type="text" wire:model="location" class="{{ $inputClass }}">
                    @error('location') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>
        <x-ui.btn wire:click="saveDetails" variant="gold" class="mt-5 px-6 py-2.5 text-sm">Save details</x-ui.btn>
    </x-ui.panel>

    {{-- ================================================================ teams --}}
    <x-ui.section-head>Teams &amp; squads</x-ui.section-head>
    <p class="-mt-1 mb-4 font-cond text-sm tracking-wide text-bone-dim">
        Players come from the roster, and each person can be in one squad per tournament. Captains and attendance are
        set on the tournament page's Roster tab.
    </p>

    <div class="grid gap-3.5 sm:grid-cols-2">
        @foreach ($teams as $team)
            <div class="border border-ink-3 bg-ink-2" wire:key="setup-team-{{ $team->id }}">
                <div class="flex items-center gap-2 border-b border-ink-3 px-4 py-3">
                    <label for="team-name-{{ $team->id }}" class="sr-only">Team name</label>
                    <input id="team-name-{{ $team->id }}" type="text" wire:model="teamNames.{{ $team->id }}"
                           wire:blur="renameTeam({{ $team->id }})" wire:keydown.enter="renameTeam({{ $team->id }})"
                           class="{{ $inputClass }} font-display font-semibold tracking-wide text-gold-bright uppercase">
                    <button type="button" wire:click="removeTeam({{ $team->id }})"
                            wire:confirm="Remove {{ $team->name }}? Its squad, its matches and their scores are removed with it."
                            class="{{ $iconBtn }} hover:border-blood hover:text-blood-bright" aria-label="Remove {{ $team->name }}">✕</button>
                </div>
                @error("teamNames.{$team->id}") <p class="{{ $error }} px-4">{{ $message }}</p> @enderror

                @foreach (Division::cases() as $division)
                    @php $squad = $team->players->filter(fn ($p) => $p->division === $division); @endphp
                    <div class="px-4 pt-3">
                        <div class="mb-1 font-cond text-[11px] font-semibold tracking-label text-gold uppercase">
                            {{ $division->label() }} <span class="font-normal text-bone-dim">{{ $squad->count() }}</span>
                        </div>
                        @forelse ($squad as $player)
                            <div class="flex items-center justify-between gap-2 border-b border-ink-3 py-1.5 last:border-b-0"
                                 wire:key="setup-player-{{ $player->id }}">
                                <span class="min-w-0 truncate font-cond text-[15px] tracking-wide uppercase">{{ $player->displayName() }}</span>
                                <button type="button" wire:click="removePlayer({{ $player->id }})"
                                        class="{{ $iconBtn }} h-7 w-7 hover:border-blood hover:text-blood-bright"
                                        aria-label="Remove {{ $player->displayName() }}">✕</button>
                            </div>
                        @empty
                            <p class="py-1 font-cond text-sm text-bone-dim">No one yet.</p>
                        @endforelse
                    </div>
                @endforeach

                <div class="mt-3 border-t border-dashed border-ink-3 px-4 py-3">
                    <div class="flex flex-wrap gap-2">
                        <label for="add-user-{{ $team->id }}" class="sr-only">Roster member</label>
                        <select id="add-user-{{ $team->id }}" wire:model="newPlayers.{{ $team->id }}.user_id"
                                class="{{ $inputClass }} flex-1 basis-40 cursor-pointer">
                            <option value="">Add from roster…</option>
                            @foreach ($pickable as $person)
                                <option value="{{ $person->id }}">{{ $person->name }}</option>
                            @endforeach
                        </select>
                        <label for="add-division-{{ $team->id }}" class="sr-only">Division</label>
                        <select id="add-division-{{ $team->id }}" wire:model="newPlayers.{{ $team->id }}.division"
                                class="{{ $inputClass }} w-auto cursor-pointer">
                            @foreach (Division::cases() as $division)
                                <option value="{{ $division->value }}">{{ $division->label() }}</option>
                            @endforeach
                        </select>
                        <button type="button" wire:click="addPlayer({{ $team->id }})"
                                class="{{ $smallBtn }} border-gold text-gold hover:bg-gold hover:text-ink">Add</button>
                    </div>
                    @error("newPlayers.{$team->id}") <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>
            </div>
        @endforeach

        <div class="flex flex-col justify-center gap-2 border border-dashed border-ink-3 px-4 py-5">
            <label for="newTeamName" class="{{ $labelClass }}">New team</label>
            <div class="flex gap-2">
                <input id="newTeamName" type="text" wire:model="newTeamName" wire:keydown.enter="addTeam"
                       placeholder="e.g. TEAM A&amp;B" class="{{ $inputClass }}">
                <button type="button" wire:click="addTeam"
                        class="{{ $smallBtn }} flex-none border-gold text-gold hover:bg-gold hover:text-ink">Add team</button>
            </div>
            @error('newTeamName') <p class="{{ $error }}">{{ $message }}</p> @enderror
        </div>
    </div>

    {{-- ============================================================= fixtures --}}
    <x-ui.section-head>Group matches</x-ui.section-head>
    <p class="-mt-1 mb-4 font-cond text-sm tracking-wide text-bone-dim">
        Each pairing is played once by the men and once by the women. The final isn't listed — its teams come from the
        table. Changing the teams on a match that has a score keeps the score on that match number.
    </p>

    @forelse ($fixtures as $fixture)
        <div class="mb-2 flex flex-wrap items-center gap-2 border border-ink-3 bg-ink-2 px-3 py-2.5" wire:key="setup-fixture-{{ $fixture->id }}">
            <span class="flex h-8 w-8 flex-none items-center justify-center rounded-full border border-ink-3 font-display text-sm text-bone-dim">
                {{ $fixture->number }}
            </span>
            <div class="grid min-w-0 flex-1 grid-cols-[1fr_auto_1fr] items-center gap-2">
                @foreach (['home', 'away'] as $side)
                    @if ($side === 'away') <span class="font-cond text-xs tracking-wide text-bone-dim uppercase">vs</span> @endif
                    <select wire:change="updateFixture({{ $fixture->id }}, '{{ $side }}', $event.target.value)"
                            aria-label="Match {{ $fixture->number }} {{ $side }} team"
                            class="{{ $inputClass }} cursor-pointer">
                        @foreach ($teams as $team)
                            <option value="{{ $team->id }}" @selected($team->id === $fixture->{$side.'_team_id'})>{{ $team->name }}</option>
                        @endforeach
                    </select>
                @endforeach
            </div>
            @if ($fixture->scores_count > 0)
                <span class="flex-none rounded-sm border border-gold/50 bg-gold/10 px-2 py-1 font-cond text-[10px] font-semibold tracking-wide-cond text-gold uppercase">Has score</span>
            @endif
            <button type="button" wire:click="removeFixture({{ $fixture->id }})"
                    wire:confirm="Remove match {{ $fixture->number }}{{ $fixture->scores_count ? ' and its scores' : '' }}?"
                    class="{{ $iconBtn }} hover:border-blood hover:text-blood-bright" aria-label="Remove match {{ $fixture->number }}">✕</button>
        </div>
    @empty
        <p class="mb-2 border border-ink-3 bg-ink-2 px-6 py-6 text-center font-cond text-sm tracking-wide text-bone-dim uppercase">
            No matches yet.
        </p>
    @endforelse

    <div class="mt-3 text-center">
        <button type="button" wire:click="addFixture" @disabled($teams->count() < 2)
                class="{{ $smallBtn }} border-gold text-gold hover:bg-gold hover:text-ink">Add match</button>
    </div>

    {{-- ============================================================ programme --}}
    <x-ui.section-head>Programme</x-ui.section-head>

    <div class="space-y-2">
        @foreach ($programme as $i => $row)
            <div class="grid gap-2 border border-ink-3 bg-ink-2 p-2.5 sm:grid-cols-[8rem_1fr_1fr_7rem_auto]" wire:key="programme-{{ $i }}">
                <input type="text" wire:model="programme.{{ $i }}.time" placeholder="Time" aria-label="Time" class="{{ $inputClass }}">
                <input type="text" wire:model="programme.{{ $i }}.activity" placeholder="Activity" aria-label="Activity" class="{{ $inputClass }}">
                <input type="text" wire:model="programme.{{ $i }}.detail" placeholder="Detail (optional)" aria-label="Detail" class="{{ $inputClass }}">
                <select wire:model="programme.{{ $i }}.kind" aria-label="Row type" class="{{ $inputClass }} cursor-pointer">
                    @foreach (Tournament::PROGRAMME_KINDS as $kind)
                        <option value="{{ $kind }}">{{ ucfirst($kind) }}</option>
                    @endforeach
                </select>
                <div class="flex justify-end gap-1.5">
                    <button type="button" wire:click="moveProgrammeRow({{ $i }}, -1)" @disabled($i === 0) class="{{ $iconBtn }}" aria-label="Move up">▲</button>
                    <button type="button" wire:click="moveProgrammeRow({{ $i }}, 1)" @disabled($i === count($programme) - 1) class="{{ $iconBtn }}" aria-label="Move down">▼</button>
                    <button type="button" wire:click="removeProgrammeRow({{ $i }})" class="{{ $iconBtn }} hover:border-blood hover:text-blood-bright" aria-label="Delete row">✕</button>
                </div>
                @foreach (['time', 'activity', 'detail', 'kind'] as $field)
                    @error("programme.{$i}.{$field}") <p class="{{ $error }} sm:col-span-5">{{ $message }}</p> @enderror
                @endforeach
            </div>
        @endforeach
    </div>

    <div class="mt-3 flex flex-wrap justify-center gap-2">
        <button type="button" wire:click="addProgrammeRow" class="{{ $smallBtn }} border-ink-3 text-bone-dim hover:text-bone">Add row</button>
        <button type="button" wire:click="saveProgramme" class="{{ $smallBtn }} border-gold bg-gold text-ink hover:bg-gold-bright">Save programme</button>
    </div>
</div>
