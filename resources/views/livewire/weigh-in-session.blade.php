@use('App\Support\Fmt')

@php
    $cutoffs = config('challenge.bmi_cutoffs');
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
    <div class="mt-6 flex flex-wrap justify-center gap-1.5">
        @foreach ($weeks as $option)
            <button type="button" wire:click="selectWeek('{{ $option->key() }}')"
                    @class([
                        'cursor-pointer border px-3.5 py-1.5 font-cond text-xs font-semibold tracking-wide-cond uppercase transition',
                        'border-gold bg-gold text-ink' => $option->equals($week),
                        'border-ink-3 bg-ink-2 text-bone-dim hover:text-bone' => ! $option->equals($week),
                    ])>
                {{ $option->shortLabel() }}
                @unless ($option->isOpen())
                    <span class="opacity-60">·</span>
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

    @if ($flash)
        <div class="mt-5 border border-gold bg-gold/10 px-4 py-3 font-cond text-sm tracking-wide text-bone">
            {{ $flash }}
        </div>
    @endif

    @error('weights')
        <div class="mt-5 border border-blood bg-blood/10 px-4 py-3 font-cond text-sm tracking-wide text-bone">
            {{ $message }}
        </div>
    @enderror

    @if (! $departmentId)
        {{-- STEP 1: department picker --}}
        <p class="mt-9 mb-3.5 text-center font-cond text-[13px] tracking-label text-bone-dim uppercase">
            Pick a department to record
        </p>

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
                    <div @class([
                            'mt-1.5 font-cond text-xs tracking-wide-cond uppercase',
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
            <p class="mt-8 text-center font-cond text-sm tracking-wide text-bone-dim uppercase">
                Nobody is on the roster for this week yet.
            </p>
        @endif
    @else
        {{-- STEP 2: the department's roster --}}
        @php
            // Count only this department's roster: $weights holds the whole
            // week, so that switching departments doesn't lose unsaved entries.
            $recordedNow = $roster->filter(
                fn ($person) => trim((string) ($weights[$person->id] ?? '')) !== ''
            )->count();
        @endphp

        <button type="button" wire:click="closeDepartment"
                class="mt-9 mb-5 cursor-pointer font-cond text-[13px] tracking-wide-cond text-gold uppercase hover:text-gold-bright">
            ← All departments
        </button>

        <div class="mb-1.5 flex items-baseline justify-between border-b border-ink-3 pb-3.5">
            <div class="font-display text-2xl font-bold uppercase">
                {{ $departments->firstWhere('model.id', $departmentId)['model']->name ?? '' }}
            </div>
            <div class="font-cond text-[13px] tracking-label text-gold uppercase">
                {{ $recordedNow }} / {{ $roster->count() }} recorded
            </div>
        </div>

        <div>
            @foreach ($roster as $person)
                <div class="grid grid-cols-[1fr_120px_64px] items-center gap-3 border-b border-ink-3 py-3.5"
                     wire:key="row-{{ $person->id }}"
                     x-data="{
                         weight: @js((string) ($weights[$person->id] ?? '')),
                         height: @js($person->height_cm),
                         get bmi() {
                             const kg = parseFloat(this.weight);
                             if (!kg || kg <= 0 || !this.height) return null;
                             return kg / ((this.height / 100) ** 2);
                         },
                         get bmiLabel() { return this.bmi ? this.bmi.toFixed(1) : '—'; },
                         get bmiClass() {
                             if (!this.bmi) return 'text-ink-3';
                             if (this.bmi < {{ $cutoffs['underweight'] }}) return 'text-gold-bright';
                             if (this.bmi < {{ $cutoffs['normal'] }}) return 'text-gold';
                             if (this.bmi < {{ $cutoffs['overweight'] }}) return 'text-blood-bright';
                             return 'text-blood';
                         },
                     }">

                    <div class="min-w-0">
                        <div class="truncate font-cond text-[17px] text-bone">{{ $person->name }}</div>
                        <div class="font-cond text-[11px] tracking-wide-cond text-bone-dim uppercase">
                            @if ($person->height_cm)
                                height {{ $person->height_cm }} cm
                            @else
                                <span class="text-blood-bright">no height on file</span>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-baseline border border-ink-3 bg-ink px-2.5 py-2 focus-within:border-gold"
                         :class="weight && 'bg-gold/5'">
                        <input type="number" step="0.1" inputmode="decimal" placeholder="00.0"
                               @disabled(! $editable)
                               x-on:input="weight = $event.target.value"
                               wire:model.blur="weights.{{ $person->id }}"
                               class="w-full min-w-0 border-none bg-transparent font-display text-[22px] font-semibold text-bone outline-none placeholder:text-ink-3 disabled:opacity-50">
                        <span class="pl-1.5 font-cond text-[11px] text-bone-dim">KG</span>
                    </div>

                    <div class="text-center font-display text-xl font-semibold" :class="bmiClass" x-text="bmiLabel">—</div>

                    @if (isset($warnings[$person->id]))
                        <p class="col-span-3 -mt-1 font-cond text-xs tracking-wide text-blood-bright uppercase">
                            ⚠ {{ $warnings[$person->id] }}
                        </p>
                    @endif
                </div>
            @endforeach
        </div>

        @if ($editable)
            <x-ui.btn wire:click="save" variant="gold" class="mt-7 w-full py-4">
                Save this department
            </x-ui.btn>
            <p class="mt-4 text-center font-cond text-xs tracking-wide text-bone-dim uppercase">
                Leaving a field blank removes that week's record. Every entry is stamped with your name.
            </p>
        @else
            <p class="mt-7 text-center font-cond text-sm tracking-wide text-bone-dim uppercase">
                This week is locked.
            </p>
        @endif
    @endif
</div>
