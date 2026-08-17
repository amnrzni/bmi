@use('App\Support\Fmt')

@php
    // Flag teams whose headcount is notably off the average, so "is this even?"
    // is still answerable across many teams without a two-team bar.
    $counts = $balance->pluck('count');
    $avgCount = $counts->count() ? $counts->avg() : 0;
    $spread = $counts->count() > 1 ? $counts->max() - $counts->min() : 0;

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

    {{-- Team balance: a row per team, flagging any that are lopsided. --}}
    @if ($balance->isNotEmpty())
        <div class="border border-ink-3 bg-ink-2">
            <div class="flex items-baseline justify-between border-b border-ink-3 px-5 py-3">
                <span class="font-cond text-[13px] tracking-label text-bone-dim uppercase">Team balance</span>
                <span class="font-cond text-[11px] tracking-wide-cond uppercase {{ $spread <= 2 ? 'text-gold' : 'text-blood-bright' }}">
                    {{ $spread <= 2 ? 'evenly split' : $spread.' apart, largest to smallest' }}
                </span>
            </div>

            <div class="divide-y divide-ink-3">
                @foreach ($balance as $side)
                    @php $off = abs($side['count'] - $avgCount) > 2; @endphp
                    <div class="flex items-center justify-between gap-4 px-5 py-2.5">
                        <span class="font-cond text-[15px] text-bone">{{ $side['team']->name }}</span>
                        <span class="flex items-baseline gap-3 font-cond text-[11px] tracking-wide-cond text-bone-dim uppercase">
                            <span>BMI <b class="text-bone">{{ Fmt::bmi($side['avgBmi']) }}</b></span>
                            <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-sm px-1.5 font-display text-base font-bold
                                         {{ $off ? 'bg-blood/20 text-blood-bright' : 'bg-ink-3 text-bone' }}">
                                {{ $side['count'] }}
                            </span>
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        <p class="my-4 text-center font-cond text-xs tracking-wide text-bone-dim uppercase">
            Balance is judged on headcount only. Weight-loss potential can't be measured up front, so
            this gets you a defensible split — not a scientific one.
        </p>
    @endif

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
        {{--
            The form renders here, above the department list — but "Edit" is
            clicked from a person's row, which for someone in "No department"
            can be at the very bottom of a long page. Without this, the form
            appears off-screen and clicking Edit looks like it did nothing.
        --}}
        <x-ui.panel class="mb-6" x-data
                    x-init="$nextTick(() => { $el.scrollIntoView({ behavior: 'smooth', block: 'start' }); $el.querySelector('#staffName')?.focus(); })">
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

    {{-- Team management --}}
    <div x-ref="teams" class="mt-10">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="brushstroke flex items-center gap-3 font-display text-xl font-bold tracking-wide uppercase">
                Teams
            </h2>
            @unless ($showTeamForm)
                <x-ui.btn wire:click="newTeam" variant="ghost" class="px-4 py-2 text-xs">+ Add team</x-ui.btn>
            @endunless
        </div>

        @if ($showTeamForm)
            <x-ui.panel class="mb-3">
                <label for="teamName" class="{{ $labelClass }}">
                    {{ $editingTeamId ? 'Rename team' : 'New team' }}
                </label>
                <input id="teamName" type="text" wire:model="teamName" wire:keydown.enter="saveTeam"
                       placeholder="e.g. Harimau" class="{{ $inputClass }}">
                @error('teamName') <p class="mt-1 font-cond text-sm text-blood-bright">{{ $message }}</p> @enderror

                <div class="mt-4 flex gap-3">
                    <x-ui.btn wire:click="saveTeam" variant="gold" class="px-5 py-2.5 text-sm">Save</x-ui.btn>
                    <x-ui.btn wire:click="$set('showTeamForm', false)" variant="ghost" class="px-5 py-2.5 text-sm">
                        Cancel
                    </x-ui.btn>
                </div>
            </x-ui.panel>
        @endif

        <x-ui.panel :padded="false">
            <div class="divide-y divide-ink-3">
                @foreach ($teams as $team)
                    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3"
                         wire:key="team-row-{{ $team->id }}">
                        <div>
                            <span class="font-cond text-base text-bone">{{ $team->name }}</span>
                            <span class="ml-2 rounded-sm bg-bone/10 px-2 py-0.5 font-cond text-[11px] font-semibold tracking-wide-cond text-bone-dim uppercase">
                                {{ $team->code }}
                            </span>
                            <span class="ml-2 font-cond text-xs tracking-wide-cond text-bone-dim uppercase">
                                {{ $team->members_count }} {{ Str::plural('member', $team->members_count) }}
                            </span>
                        </div>
                        <div class="flex items-center gap-3 font-cond text-xs tracking-wide-cond uppercase">
                            <button type="button" wire:click="moveTeam({{ $team->id }}, -1)"
                                    aria-label="Move {{ $team->name }} up"
                                    class="cursor-pointer px-1 text-bone-dim hover:text-gold">↑</button>
                            <button type="button" wire:click="moveTeam({{ $team->id }}, 1)"
                                    aria-label="Move {{ $team->name }} down"
                                    class="cursor-pointer px-1 text-bone-dim hover:text-gold">↓</button>
                            <button type="button" wire:click="editTeam({{ $team->id }})"
                                    class="cursor-pointer text-bone-dim hover:text-gold">Rename</button>
                            <button type="button" wire:click="deleteTeam({{ $team->id }})"
                                    class="cursor-pointer text-bone-dim hover:text-blood-bright">Delete</button>
                        </div>
                    </div>
                @endforeach

                @if ($teams->isEmpty())
                    <p class="px-5 py-6 text-center font-cond text-sm tracking-wide text-bone-dim uppercase">
                        No teams yet. Add a few, then assign people to them.
                    </p>
                @endif
            </div>
        </x-ui.panel>

        <p class="mt-3 font-cond text-xs tracking-wide text-bone-dim uppercase">
            A team can only be deleted once it's empty. The short tag is set automatically from the name.
        </p>
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
