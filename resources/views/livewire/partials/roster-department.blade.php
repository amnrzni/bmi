@php
    $unassignedHere = $staff->whereNull('team_id')->count();
    $neverIn = $staff->filter->hasNeverSignedIn()->count();
@endphp

{{-- Open by default: six departments closed meant six clicks before you could
     see anybody, and finding one person was the most common reason to be here. --}}
<div class="border border-ink-3 bg-ink-2" x-data="{ open: true }" wire:key="dept-{{ $name }}">
    <button type="button" @click="open = !open"
            :aria-expanded="open ? 'true' : 'false'"
            class="flex w-full cursor-pointer items-center justify-between gap-4 px-5 py-4 text-left transition hover:bg-ink-3">
        <span>
            <span class="block font-display text-[17px] font-semibold tracking-wide uppercase">{{ $name }}</span>
            <span class="block font-cond text-xs tracking-wide-cond text-bone-dim uppercase">
                {{ $staff->count() }} staff
                @if ($unassignedHere > 0)
                    · <span class="text-blood-bright">{{ $unassignedHere }} unassigned</span>
                @endif
                @if ($neverIn > 0)
                    · <span class="text-blood-bright">{{ $neverIn }} never signed in</span>
                @endif
            </span>
        </span>
        <span aria-hidden="true" class="font-display text-gold transition-transform duration-200"
              :class="open && 'rotate-90'">›</span>
    </button>

    <div x-show="open" x-collapse class="border-t border-ink-3">
        @foreach ($staff as $person)
            <div class="border-b border-ink-3 px-5 py-3 last:border-b-0 {{ $person->hasLeft() ? 'opacity-55' : '' }}"
                 wire:key="staff-{{ $person->id }}">

                <div class="grid grid-cols-[1fr_auto] items-center gap-3 sm:grid-cols-[1fr_110px_130px]">
                    <div class="min-w-0">
                        <div class="truncate font-cond text-base text-bone">
                            {{ $person->name }}
                            @if ($person->isAdmin())
                                <span class="ml-1 rounded-sm bg-gold/20 px-1.5 py-0.5 align-middle font-cond text-[10px] font-semibold tracking-wide-cond text-gold-bright uppercase">
                                    Admin
                                </span>
                            @endif
                            @if ($person->hasLeft())
                                <span class="ml-1 font-cond text-[11px] tracking-wide-cond text-bone-dim uppercase">
                                    · left {{ $person->left_at->format('j M') }}
                                </span>
                            @endif
                        </div>

                        <div class="truncate font-cond text-[11px] tracking-wide-cond text-bone-dim lowercase">
                            {{ $person->email }}
                        </div>

                        <div class="font-cond text-[11px] tracking-wide-cond uppercase">
                            @unless ($person->height_cm)
                                <span class="text-blood-bright">no height · BMI unavailable</span>
                            @endunless

                            @if ($person->hasNeverSignedIn() && ! $person->hasLeft())
                                @unless ($person->height_cm) · @endunless
                                {{-- The practical diagnosis, not just the symptom. --}}
                                <span class="text-blood-bright" title="Usually a mismatch with their QCXIS account email">
                                    never signed in
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="col-start-2 row-start-1 flex items-baseline border border-ink-3 bg-ink px-2 py-1.5 focus-within:border-gold sm:col-start-auto">
                        <input type="number" inputmode="numeric" min="100" max="250" placeholder="—"
                               wire:model="heights.{{ $person->id }}"
                               wire:change="saveHeight({{ $person->id }})"
                               class="w-full min-w-0 border-none bg-transparent font-display text-[15px] font-semibold text-bone outline-none">
                        <span class="pl-1 font-cond text-[11px] text-bone-dim">CM</span>
                    </div>

                    {{-- Dropdown rather than a button per team: with ~8 teams a
                         toggle row would never fit a phone. --}}
                    <div class="col-span-2 sm:col-span-1">
                        <select wire:change="setTeam({{ $person->id }}, $event.target.value)"
                                aria-label="Team for {{ $person->name }}"
                                class="w-full border border-ink-3 bg-ink px-2 py-2 font-cond text-sm text-bone outline-none focus:border-gold">
                            <option value="" @selected(! $person->team_id)>— team —</option>
                            @foreach ($teams as $team)
                                <option value="{{ $team->id }}" @selected($person->team_id === $team->id)>
                                    {{ $team->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mt-2 flex flex-wrap gap-4 font-cond text-[11px] tracking-wide-cond uppercase">
                    <button type="button" wire:click="editStaff({{ $person->id }})"
                            class="cursor-pointer text-bone-dim hover:text-gold">Edit</button>

                    @php
                        $isSelf = $person->id === auth()->id();
                        // Only relevant when demoting — never blocks a promotion.
                        $lastAdmin = $person->isAdmin() && $adminCount <= 1;
                    @endphp

                    @if ($isSelf && $person->isAdmin())
                        <span class="text-bone-dim/50" title="You can't remove your own admin access">
                            Admin
                        </span>
                    @elseif ($lastAdmin)
                        <span class="text-bone-dim/50" title="At least one admin has to remain">
                            Admin
                        </span>
                    @else
                        <button type="button" wire:click="toggleAdmin({{ $person->id }})"
                                @if ($person->isAdmin())
                                    wire:confirm="Remove {{ $person->name }}'s admin access?"
                                @endif
                                class="cursor-pointer text-bone-dim hover:text-gold">
                            {{ $person->isAdmin() ? 'Remove admin' : 'Make admin' }}
                        </button>
                    @endif

                    @if ($person->hasLeft())
                        <button type="button" wire:click="restoreStaff({{ $person->id }})"
                                class="cursor-pointer text-bone-dim hover:text-gold">Bring back</button>
                    @else
                        <button type="button" wire:click="markAsLeft({{ $person->id }})"
                                wire:confirm="Mark {{ $person->name }} as having left? Their recorded weeks are kept."
                                class="cursor-pointer text-bone-dim hover:text-blood-bright">Mark as left</button>
                    @endif

                    {{-- Only offered while they have no history to destroy. --}}
                    @if ($person->weighIns->isEmpty())
                        <button type="button" wire:click="deleteStaff({{ $person->id }})"
                                wire:confirm="Delete {{ $person->name }} from the roster entirely?"
                                class="cursor-pointer text-bone-dim hover:text-blood-bright">Delete</button>
                    @endif
                </div>
            </div>
        @endforeach

        @if ($staff->isEmpty())
            <p class="px-5 py-4 font-cond text-sm tracking-wide text-bone-dim uppercase">No staff in this department.</p>
        @endif
    </div>
</div>
