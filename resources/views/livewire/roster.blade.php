@use('App\Support\Fmt')

@php
    $counts = $balance->pluck('count');
    $total = max($counts->sum(), 1);
    $difference = $counts->count() === 2 ? abs($counts[0] - $counts[1]) : 0;
    $firstShare = round(($counts->first() ?? 0) / $total * 100);

    $inputClass = 'w-full border border-ink-3 bg-ink px-3 py-2.5 font-cond text-[15px] text-bone outline-none focus:border-gold';
    $labelClass = 'mb-2 block font-cond text-[13px] tracking-label text-bone-dim uppercase';
@endphp

<div class="mx-auto max-w-4xl px-5 py-12 pb-20">
    <x-ui.eyebrow class="text-center">Admin · Master roster</x-ui.eyebrow>
    <h1 class="mt-3 mb-8 text-center font-display text-4xl leading-[0.9] font-bold uppercase sm:text-5xl">
        Staff &amp; <span class="text-blood">Teams</span>
    </h1>

    @if ($flash)
        {{-- Sticky: the roster is a long page and actions happen far from the top. --}}
        <div role="status" wire:key="flash-{{ md5($flash) }}"
             class="sticky top-2 z-30 mb-5 border border-gold bg-ink-2 px-4 py-3 font-cond text-sm tracking-wide text-bone shadow-lg shadow-ink">
            {{ $flash }}
        </div>
    @endif

    {{-- Live team balance --}}
    <div class="grid grid-cols-1 items-center border border-ink-3 bg-ink-2 sm:grid-cols-[1fr_2fr_1fr]">
        @foreach ($balance as $index => $side)
            @if ($index === 1)
                <div class="px-5 pb-4 sm:py-0">
                    <div class="h-2 overflow-hidden rounded-full bg-ink-3">
                        <div class="h-2 rounded-full transition-all duration-300
                                    {{ $difference === 0 ? 'bg-gold' : ($difference <= 2 ? 'bg-gold-bright' : 'bg-blood') }}"
                             style="width: {{ $firstShare }}%"></div>
                    </div>
                    <div class="mt-2 text-center font-cond text-[11px] tracking-label text-bone-dim uppercase">
                        @if ($difference === 0)
                            balanced
                        @elseif ($difference <= 2)
                            close ({{ $difference }} apart)
                        @else
                            uneven ({{ $difference }} apart)
                        @endif
                    </div>
                </div>
            @endif

            <div class="px-4 py-5 text-center">
                <div class="font-display text-lg font-bold tracking-wide text-gold uppercase">
                    {{ $side['team']->name }}
                </div>
                <div class="my-0.5 font-display text-[40px] leading-none font-bold">{{ $side['count'] }}</div>
                <div class="font-cond text-[11px] tracking-wide-cond text-bone-dim uppercase">
                    members · starting BMI <b class="text-bone">{{ Fmt::bmi($side['avgBmi']) }}</b>
                </div>
            </div>
        @endforeach
    </div>

    <p class="my-4 text-center font-cond text-xs tracking-wide text-bone-dim uppercase">
        Balance is scored on headcount only. Weight-loss potential can't be measured up front, so this
        gets you a defensible split — not a scientific one.
    </p>

    {{-- Roster health. "Never signed in" is the only reliable sign of a wrong email. --}}
    <div class="mb-5 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 border border-ink-3 bg-ink-2 px-5 py-3 text-center font-cond text-xs tracking-wide-cond uppercase">
        <span class="text-bone-dim">{{ $headcount }} on the roster</span>

        @if ($unassignedCount > 0)
            <span class="text-blood-bright">{{ $unassignedCount }} without a team</span>
        @endif

        @if ($neverSignedIn > 0)
            <span class="text-blood-bright" title="Usually means their email doesn't match their QCXIS account">
                {{ $neverSignedIn }} never signed in
            </span>
        @else
            <span class="text-gold">everyone has signed in</span>
        @endif

        @if ($leftCount > 0)
            <span class="text-bone-dim">{{ $leftCount }} left the challenge</span>
        @endif
    </div>

    {{-- Actions --}}
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2">
            @unless ($showStaffForm)
                <x-ui.btn wire:click="newStaff" variant="gold" class="px-5 py-2.5 text-sm">+ Add person</x-ui.btn>
            @endunless
            <x-ui.btn as="a" href="{{ route('roster.import') }}" variant="ghost" class="px-5 py-2.5 text-sm">
                Import CSV
            </x-ui.btn>
        </div>

        <button type="button" x-data @click="$refs.depts.scrollIntoView({ behavior: 'smooth' })"
                class="cursor-pointer font-cond text-xs tracking-wide-cond text-bone-dim uppercase hover:text-gold">
            Manage departments ↓
        </button>
    </div>

    {{-- Add / edit a person --}}
    @if ($showStaffForm)
        <x-ui.panel class="mb-6">
            <h2 class="mb-5 font-display text-xl font-bold tracking-wide uppercase">
                {{ $editingUserId ? 'Edit person' : 'Add person' }}
            </h2>

            <div class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="staffName" class="{{ $labelClass }}">Name</label>
                        <input id="staffName" type="text" wire:model="staffName" class="{{ $inputClass }}">
                        @error('staffName') <p class="mt-1 font-cond text-sm text-blood-bright">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="staffEmail" class="{{ $labelClass }}">Email</label>
                        <input id="staffEmail" type="email" wire:model="staffEmail" class="{{ $inputClass }}">
                        @error('staffEmail')
                            <p class="mt-1 font-cond text-sm text-blood-bright">{{ $message }}</p>
                        @else
                            <p class="mt-1 font-cond text-xs tracking-wide text-bone-dim uppercase">
                                Must match their QCXIS account exactly, or they can't sign in
                            </p>
                        @enderror
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="staffDepartmentId" class="{{ $labelClass }}">Department</label>
                        <select id="staffDepartmentId" wire:model="staffDepartmentId" class="{{ $inputClass }}">
                            <option value="">— none —</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}">{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="staffTeamId" class="{{ $labelClass }}">Team</label>
                        <select id="staffTeamId" wire:model="staffTeamId" class="{{ $inputClass }}">
                            <option value="">— unassigned —</option>
                            @foreach ($teams as $team)
                                <option value="{{ $team->id }}">{{ $team->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="staffHeight" class="{{ $labelClass }}">Height (cm)</label>
                        <input id="staffHeight" type="number" wire:model="staffHeight" class="{{ $inputClass }}">
                        @error('staffHeight') <p class="mt-1 font-cond text-sm text-blood-bright">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap gap-3">
                <x-ui.btn wire:click="saveStaff" wire:loading.attr="disabled" wire:target="saveStaff"
                          variant="gold" class="px-6 py-3 text-sm">
                    <span wire:loading.remove wire:target="saveStaff">
                        {{ $editingUserId ? 'Save changes' : 'Add to roster' }}
                    </span>
                    <span wire:loading wire:target="saveStaff">Saving…</span>
                </x-ui.btn>
                <x-ui.btn wire:click="cancelStaff" variant="ghost" class="px-6 py-3 text-sm">Cancel</x-ui.btn>
            </div>
        </x-ui.panel>
    @endif

    {{-- Departments --}}
    @if ($headcount === 0)
        <div class="border border-ink-3 bg-ink-2 px-6 py-12 text-center">
            <p class="font-display text-2xl font-semibold uppercase">Nobody on the roster yet</p>
            <p class="mx-auto mt-3 max-w-md font-cond text-sm tracking-wide text-bone-dim uppercase">
                Import your sheet to load everyone at once, or add people one at a time.
                Each person's email must match their QCXIS account, or they can't sign in.
            </p>
            <div class="mt-6 flex flex-wrap justify-center gap-3">
                <x-ui.btn as="a" href="{{ route('roster.import') }}" variant="gold" class="px-5 py-2.5 text-sm">
                    Import CSV
                </x-ui.btn>
                <x-ui.btn wire:click="newStaff" variant="ghost" class="px-5 py-2.5 text-sm">Add one person</x-ui.btn>
            </div>
        </div>
    @endif

    <div class="space-y-3">
        @foreach ($departments as $department)
            @include('livewire.partials.roster-department', [
                'name' => $department->name,
                'staff' => $department->staff,
            ])
        @endforeach

        @if ($unassigned->isNotEmpty())
            @include('livewire.partials.roster-department', [
                'name' => 'No department',
                'staff' => $unassigned,
            ])
        @endif
    </div>

    {{-- Department management --}}
    <div x-ref="depts" class="mt-10">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="brushstroke flex items-center gap-3 font-display text-xl font-bold tracking-wide uppercase">
                Departments
            </h2>
            @unless ($showDeptForm)
                <x-ui.btn wire:click="newDepartment" variant="ghost" class="px-4 py-2 text-xs">+ Add department</x-ui.btn>
            @endunless
        </div>

        @if ($showDeptForm)
            <x-ui.panel class="mb-3">
                <label for="deptName" class="{{ $labelClass }}">
                    {{ $editingDeptId ? 'Rename department' : 'New department' }}
                </label>
                <input id="deptName" type="text" wire:model="deptName" wire:keydown.enter="saveDepartment"
                       class="{{ $inputClass }}">
                @error('deptName') <p class="mt-1 font-cond text-sm text-blood-bright">{{ $message }}</p> @enderror

                <div class="mt-4 flex gap-3">
                    <x-ui.btn wire:click="saveDepartment" variant="gold" class="px-5 py-2.5 text-sm">Save</x-ui.btn>
                    <x-ui.btn wire:click="$set('showDeptForm', false)" variant="ghost" class="px-5 py-2.5 text-sm">
                        Cancel
                    </x-ui.btn>
                </div>
            </x-ui.panel>
        @endif

        <x-ui.panel :padded="false">
            <div class="divide-y divide-ink-3">
                @foreach ($departments as $department)
                    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3"
                         wire:key="dept-row-{{ $department->id }}">
                        <div>
                            <span class="font-cond text-base text-bone">{{ $department->name }}</span>
                            <span class="ml-2 font-cond text-xs tracking-wide-cond text-bone-dim uppercase">
                                {{ $department->staff->count() }} staff
                            </span>
                        </div>
                        <div class="flex items-center gap-3 font-cond text-xs tracking-wide-cond uppercase">
                            <button type="button" wire:click="moveDepartment({{ $department->id }}, -1)"
                                    aria-label="Move {{ $department->name }} up"
                                    class="cursor-pointer px-1 text-bone-dim hover:text-gold">↑</button>
                            <button type="button" wire:click="moveDepartment({{ $department->id }}, 1)"
                                    aria-label="Move {{ $department->name }} down"
                                    class="cursor-pointer px-1 text-bone-dim hover:text-gold">↓</button>
                            <button type="button" wire:click="editDepartment({{ $department->id }})"
                                    class="cursor-pointer text-bone-dim hover:text-gold">Rename</button>
                            <button type="button" wire:click="deleteDepartment({{ $department->id }})"
                                    class="cursor-pointer text-bone-dim hover:text-blood-bright">Delete</button>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-ui.panel>

        <p class="mt-3 font-cond text-xs tracking-wide text-bone-dim uppercase">
            A department can only be deleted once it's empty.
        </p>
    </div>
</div>
