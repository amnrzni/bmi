@use('App\Support\Fmt')

@php
    $cutoffs = config('challenge.bmi_cutoffs');

    // Seed the client with what's already recorded, keyed by user id.
    $initial = $roster->mapWithKeys(fn ($person) => [
        (string) $person->id => (string) ($weights[$person->id] ?? ''),
    ]);
    $heights = $roster->mapWithKeys(fn ($person) => [(string) $person->id => $person->height_cm]);
@endphp

<div class="mx-auto max-w-2xl px-5 py-10 pb-24">
    <x-ui.eyebrow class="text-center">Weigh-in session · recorded by admin</x-ui.eyebrow>
    <h1 class="mt-3 text-center font-display text-4xl leading-[0.9] font-bold uppercase sm:text-5xl">
        Record <span class="text-blood">Weight</span>
    </h1>
    <p class="mt-2.5 text-center font-cond text-[13px] tracking-[0.22em] text-bone-dim uppercase">
        {{ $week->label() }}
    </p>

    {{-- Week selector. Backfilling a closed week is admin-only and the reason
         week 1 is recoverable at all. --}}
    <div class="mt-6 flex flex-wrap justify-center gap-1.5" role="group" aria-label="Choose a week">
        @foreach ($weeks as $option)
            <button type="button" wire:click="selectWeek('{{ $option->key() }}')"
                    aria-label="{{ $option->label() }}"
                    aria-current="{{ $option->equals($week) ? 'true' : 'false' }}"
                    @class([
                        'cursor-pointer border px-3.5 py-1.5 font-cond text-xs font-semibold tracking-wide-cond uppercase transition',
                        'border-gold bg-gold text-ink' => $option->equals($week),
                        'border-ink-3 bg-ink-2 text-bone-dim hover:text-bone' => ! $option->equals($week),
                    ])>
                {{ $option->shortLabel() }}
                @unless ($option->isOpen())
                    <span aria-hidden="true" class="opacity-60">·</span>
                @endunless
            </button>
        @endforeach
    </div>

    <p class="mt-2.5 text-center font-cond text-xs tracking-wide text-bone-dim uppercase">
        @if ($week->isOpen())
            Open until {{ $week->closesAt()->format('D j M, g:ia') }}
        @else
            Closed {{ $week->closesAt()->format('j M') }} · reopened for you as admin
        @endif
    </p>

    <div wire:loading.flex wire:target="selectWeek,openDepartment"
         class="mt-4 items-center justify-center gap-2 font-cond text-sm tracking-wide text-gold uppercase">
        <span class="h-3 w-3 animate-spin rounded-full border-2 border-gold border-t-transparent"></span>
        Loading…
    </div>

    @if ($flash)
        <div role="status"
             class="mt-5 border border-gold bg-gold/10 px-4 py-3 font-cond text-sm tracking-wide text-bone">
            {{ $flash }}
        </div>
    @endif

    @error('weights')
        <div role="alert" class="mt-5 border border-blood bg-blood/10 px-4 py-3 font-cond text-sm tracking-wide text-bone">
            {{ $message }}
        </div>
    @enderror

    @if (! $departmentId)
        {{-- STEP 1: department picker --}}
        <h2 class="mt-9 mb-3.5 text-center font-cond text-[13px] tracking-label text-bone-dim uppercase">
            Pick a department to record
        </h2>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            @foreach ($departments as $row)
                @php
                    $done = $row['recorded'] >= $row['expected'];
                    $started = $row['recorded'] > 0;
                @endphp
                <button type="button" wire:click="openDepartment({{ $row['model']->id }})"
                        class="cursor-pointer border border-ink-3 bg-ink-2 px-5 py-5 text-left transition hover:-translate-y-0.5 hover:border-gold">
                    <div class="font-display text-lg font-semibold tracking-wide text-bone uppercase">
                        {{ $row['model']->name }}
                    </div>

                    {{-- Progress bar: status readable at a glance from arm's length. --}}
                    <div class="mt-2.5 h-1 overflow-hidden rounded-full bg-ink-3">
                        <div class="h-1 rounded-full {{ $done ? 'bg-gold' : 'bg-blood' }}"
                             style="width: {{ $row['expected'] > 0 ? round($row['recorded'] / $row['expected'] * 100) : 0 }}%"></div>
                    </div>

                    <div @class([
                            'mt-2 font-cond text-xs tracking-wide-cond uppercase',
                            'text-gold' => $done,
                            'text-blood-bright' => ! $started,
                            'text-bone-dim' => $started && ! $done,
                        ])>
                        @if ($done)
                            {{ $row['recorded'] }} / {{ $row['expected'] }} done ✓
                        @elseif (! $started)
                            0 / {{ $row['expected'] }} · not started
                        @else
                            {{ $row['recorded'] }} / {{ $row['expected'] }} recorded
                        @endif
                    </div>
                </button>
            @endforeach
        </div>

        @if ($departments->isEmpty())
            <div class="mt-8 border border-ink-3 bg-ink-2 px-6 py-10 text-center">
                <p class="font-display text-xl font-semibold uppercase">Nobody on the roster yet</p>
                <p class="mt-2 font-cond text-sm tracking-wide text-bone-dim uppercase">
                    Add staff before you can record a weigh-in.
                </p>
                <x-ui.btn as="a" href="{{ route('roster') }}" variant="gold" class="mt-5 px-5 py-2.5 text-sm">
                    Go to the roster
                </x-ui.btn>
            </div>
        @endif
    @else
        {{--
            STEP 2: the department's roster.

            All entry state lives in Alpine and is submitted in ONE request on
            save. Enter moves down the list, so a whole department is a single
            run from the keyboard without touching the screen between people.
        --}}
        <div x-data="{
                weights: @js($initial),
                heights: @js($heights),
                get done() {
                    return Object.values(this.weights).filter(v => String(v).trim() !== '').length;
                },
                fields() {
                    return [...$el.querySelectorAll('input[data-weight]')];
                },
                next(el) {
                    const all = this.fields();
                    const target = all[all.indexOf(el) + 1];
                    if (target) { target.focus(); target.select(); } else { el.blur(); }
                },
                bmi(id) {
                    const kg = parseFloat(this.weights[id]);
                    const cm = this.heights[id];
                    return (kg > 0 && cm) ? kg / ((cm / 100) ** 2) : null;
                },
                bmiLabel(id) { const b = this.bmi(id); return b ? b.toFixed(1) : '—'; },
                bmiClass(id) {
                    const b = this.bmi(id);
                    if (!b) return 'text-ink-3';
                    if (b < {{ $cutoffs['underweight'] }}) return 'text-gold-bright';
                    if (b < {{ $cutoffs['normal'] }}) return 'text-gold';
                    if (b < {{ $cutoffs['overweight'] }}) return 'text-blood-bright';
                    return 'text-blood';
                },
             }"
             x-init="$nextTick(() => $el.querySelector('input[data-weight]:placeholder-shown')?.focus())">

            <button type="button" wire:click="closeDepartment"
                    class="mt-9 mb-5 cursor-pointer font-cond text-[13px] tracking-wide-cond text-gold uppercase hover:text-gold-bright">
                ← All departments
            </button>

            <div class="mb-1.5 flex items-baseline justify-between border-b border-ink-3 pb-3.5">
                <h2 class="font-display text-2xl font-bold uppercase">
                    {{ $departments->firstWhere('model.id', $departmentId)['model']->name ?? '' }}
                </h2>
                <div class="font-cond text-[13px] tracking-label text-gold uppercase" role="status">
                    <span x-text="done">{{ $recordedNow }}</span> / {{ $roster->count() }} recorded
                </div>
            </div>

            {{-- Live progress, so you can see the run filling up without counting. --}}
            <div class="mb-4 h-1 overflow-hidden rounded-full bg-ink-3">
                <div class="h-1 rounded-full bg-gold transition-all duration-200"
                     :style="`width: ${(done / {{ max($roster->count(), 1) }}) * 100}%`"></div>
            </div>

            <div>
                @foreach ($roster as $person)
                    <div class="grid grid-cols-[1fr_130px_60px] items-center gap-3 border-b border-ink-3 py-3.5"
                         wire:key="row-{{ $person->id }}"
                         :class="String(weights[{{ $person->id }}] ?? '').trim() !== '' && 'bg-gold/5'">

                        <label for="w-{{ $person->id }}" class="min-w-0 cursor-pointer">
                            <span class="block truncate font-cond text-[17px] text-bone">{{ $person->name }}</span>
                            <span class="block font-cond text-[11px] tracking-wide-cond text-bone-dim uppercase">
                                @if ($person->height_cm)
                                    height {{ $person->height_cm }} cm
                                @else
                                    <span class="text-blood-bright">no height on file</span>
                                @endif
                            </span>
                        </label>

                        {{-- Taller than a default input: this is used one-handed
                             standing next to a scale. --}}
                        <div class="flex items-baseline border border-ink-3 bg-ink px-3 py-2.5 focus-within:border-gold">
                            <input id="w-{{ $person->id }}" data-weight type="number" step="0.1"
                                   inputmode="decimal" placeholder="00.0"
                                   enterkeyhint="next"
                                   aria-label="Weight in kilograms for {{ $person->name }}"
                                   @disabled(! $editable)
                                   x-model="weights[{{ $person->id }}]"
                                   @keydown.enter.prevent="next($el)"
                                   class="w-full min-w-0 border-none bg-transparent font-display text-2xl font-semibold text-bone outline-none placeholder:text-ink-3 disabled:opacity-50">
                            <span class="pl-1.5 font-cond text-[11px] text-bone-dim" aria-hidden="true">KG</span>
                        </div>

                        <div class="text-center font-display text-xl font-semibold"
                             :class="bmiClass({{ $person->id }})" x-text="bmiLabel({{ $person->id }})"
                             aria-label="BMI for {{ $person->name }}">—</div>

                        @if (isset($warnings[$person->id]))
                            <p role="alert" class="col-span-3 -mt-1 font-cond text-xs tracking-wide text-blood-bright uppercase">
                                ⚠ {{ $warnings[$person->id] }}
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>

            @if ($editable)
                <button type="button" x-on:click="$wire.save(weights)"
                        wire:loading.attr="disabled" wire:target="save"
                        class="mt-7 w-full cursor-pointer rounded-sm bg-gold px-7 py-4 text-center font-display text-[15px]
                               font-semibold tracking-wider text-ink uppercase transition hover:bg-gold-bright
                               disabled:cursor-wait disabled:opacity-60">
                    <span wire:loading.remove wire:target="save">
                        Save <span x-text="done">{{ $recordedNow }}</span> of {{ $roster->count() }}
                    </span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>

                <p class="mt-4 text-center font-cond text-xs tracking-wide text-bone-dim uppercase">
                    Press enter to jump to the next person. Leaving a field blank removes that week's
                    record. Every entry is stamped with your name.
                </p>
            @else
                <p class="mt-7 text-center font-cond text-sm tracking-wide text-bone-dim uppercase">
                    This week is locked.
                </p>
            @endif
        </div>
    @endif
</div>
