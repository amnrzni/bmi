@use('App\Support\Fmt')

@php
    $metrics = [
        'weight' => 'Weight (kg)',
        'bmi' => 'BMI',
        'change' => 'Change',
        'compliance' => 'Logged',
    ];

    $columns = [
        'name' => 'Name',
        'weeks' => 'Weeks',
        'current' => 'Current kg',
        'delta' => 'Δ kg',
        'percent' => 'Δ %',
        'bmi' => 'BMI now',
    ];
@endphp

<div class="mx-auto max-w-5xl px-5 py-12 pb-20">
    <x-ui.eyebrow class="text-center">Analytics · admin view</x-ui.eyebrow>
    <h1 class="mt-3 mb-8 text-center font-display text-4xl leading-[0.9] font-bold uppercase sm:text-5xl">
        Full <span class="text-blood">Picture</span>
    </h1>

    @if ($weeks->isEmpty() || $grid->isEmpty())
        <div class="border border-ink-3 bg-ink-2 px-6 py-12 text-center">
            <p class="font-display text-2xl font-semibold uppercase">
                {{ $grid->isEmpty() ? 'Nobody to report on yet' : 'The challenge hasn\'t started' }}
            </p>
            <p class="mx-auto mt-3 max-w-md font-cond text-sm tracking-wide text-bone-dim uppercase">
                {{ $grid->isEmpty()
                    ? 'Add staff to the roster, then record a weigh-in.'
                    : 'Week 1 begins '.\App\Support\ChallengeWeek::first()->startDate->format('j F Y').'.' }}
            </p>
            <x-ui.btn as="a" href="{{ $grid->isEmpty() ? route('roster') : route('weigh-in') }}"
                      variant="gold" class="mt-6 px-5 py-2.5 text-sm">
                {{ $grid->isEmpty() ? 'Go to the roster' : 'Go to weigh-in' }}
            </x-ui.btn>
        </div>
    @else

    <x-ui.standings :standings="$standings" />

    {{-- Controls --}}
    <div class="my-5 flex flex-wrap items-center justify-between gap-3">
        <div class="flex gap-1.5">
            <button type="button" wire:click="setTeam('all')"
                    @class([
                        'cursor-pointer border px-4.5 py-2 font-cond text-xs font-semibold tracking-wide-cond uppercase transition',
                        'border-gold bg-gold text-ink' => $team === 'all',
                        'border-ink-3 bg-ink-2 text-bone-dim hover:text-bone' => $team !== 'all',
                    ])>All</button>

            @foreach ($teams as $option)
                <button type="button" wire:click="setTeam('{{ $option->code }}')"
                        @class([
                            'cursor-pointer border px-4.5 py-2 font-cond text-xs font-semibold tracking-wide-cond uppercase transition',
                            'border-gold bg-gold text-ink' => $team === $option->code,
                            'border-ink-3 bg-ink-2 text-bone-dim hover:text-bone' => $team !== $option->code,
                        ])>{{ $option->name }}</button>
            @endforeach
        </div>

        <div class="font-cond text-xs tracking-label text-bone-dim uppercase">
            Baseline = each person's own first weigh-in
        </div>
    </div>

    {{-- TABLE 1: weekly grid --}}
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2.5">
        <h2 class="brushstroke flex items-center gap-3 font-display text-xl font-bold tracking-wide uppercase">
            Weekly record
        </h2>
        <div class="flex gap-1">
            @foreach ($metrics as $key => $label)
                <button type="button" wire:click="setMetric('{{ $key }}')"
                        @class([
                            'cursor-pointer border px-4 py-1.5 font-cond text-xs font-semibold tracking-wide-cond uppercase transition',
                            'border-gold bg-gold text-ink' => $metric === $key,
                            'border-ink-3 bg-ink-2 text-bone-dim hover:text-bone' => $metric !== $key,
                        ])>{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <div class="overflow-x-auto border border-ink-3">
        <table class="w-full min-w-[680px] border-collapse">
            <thead>
                <tr>
                    <th class="sticky left-0 z-20 bg-ink-2 px-2.5 py-3.5 text-left font-cond text-xs font-semibold tracking-wide text-bone-dim uppercase">
                        Name
                    </th>
                    <th class="bg-ink-2 px-2.5 py-3.5 font-cond text-xs font-semibold tracking-wide text-bone-dim uppercase">
                        Team
                    </th>
                    @foreach ($weeks as $week)
                        <th class="bg-ink-2 px-2.5 py-3.5 font-cond text-[11px] font-semibold tracking-wide text-bone-dim uppercase">
                            {{ $week->shortLabel() }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($grid as $row)
                    <tr class="border-t border-ink-3 hover:bg-gold/5">
                        <td class="sticky left-0 z-10 bg-ink px-2.5 py-3 text-left font-cond text-base text-bone">
                            {{ $row['user']->displayName() }}
                        </td>
                        <td class="px-2.5 py-3 text-center">
                            @if ($row['user']->team)
                                <span class="rounded-sm bg-bone/10 px-2.5 py-0.5 font-cond text-xs font-semibold tracking-wide-cond text-bone">
                                    {{ $row['user']->team->code }}
                                </span>
                            @endif
                        </td>

                        @foreach ($weeks as $week)
                            @php
                                $record = $row['byWeek'][$week->key()] ?? null;
                                $change = $row['changes'][$week->key()] ?? null;
                            @endphp

                            @if ($metric === 'compliance')
                                {{-- Presence/absence, replacing a separate compliance screen. --}}
                                <td class="px-2.5 py-3 text-center font-display text-sm">
                                    @if ($record)
                                        <span class="inline-block h-3 w-3 rounded-full bg-gold" title="Recorded"></span>
                                    @else
                                        <span class="inline-block h-3 w-3 rounded-full bg-blood" title="Missing"></span>
                                    @endif
                                </td>
                            @elseif (! $record)
                                <td class="px-2.5 py-3 text-center font-display text-sm text-ink-3">—</td>
                            @elseif ($metric === 'change')
                                @if ($change === null)
                                    {{-- Their first record: nothing to compare against. --}}
                                    <td class="px-2.5 py-3 text-center font-display text-sm text-bone-dim">·</td>
                                @else
                                    <td class="px-2.5 py-3 text-center font-display text-sm {{ Fmt::deltaColor($change) }}">
                                        {{ Fmt::delta($change) }}
                                    </td>
                                @endif
                            @else
                                <td class="px-2.5 py-3 text-center font-display text-sm text-bone">
                                    {{ $metric === 'weight'
                                        ? Fmt::weight((float) $record->weight_kg)
                                        : Fmt::bmi($row['user']->bmiFor((float) $record->weight_kg)) }}
                                </td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="mt-2.5 font-cond text-xs tracking-wide text-bone-dim uppercase">
        Read across a row for one person's journey ·
        <span class="text-ink-3">—</span> = no weigh-in ·
        in Change mode <span class="text-bone-dim">·</span> = first week, nothing to compare
    </p>

    {{-- TABLE 2: deltas summary --}}
    <div class="mt-10 mb-3 flex flex-wrap items-center justify-between gap-2.5">
        <h2 class="brushstroke flex items-center gap-3 font-display text-xl font-bold tracking-wide uppercase">
            Change summary
        </h2>
        <div class="flex items-center gap-3">
            <span class="font-cond text-xs tracking-label text-bone-dim uppercase">baseline → latest</span>
            <button type="button" wire:click="export"
                    class="cursor-pointer border border-ink-3 bg-ink-2 px-4 py-1.5 font-cond text-xs font-semibold tracking-wide-cond text-bone-dim uppercase transition hover:text-bone">
                Export CSV
            </button>
        </div>
    </div>

    <div class="overflow-x-auto border border-ink-3">
        <table class="w-full min-w-[680px] border-collapse">
            <thead>
                <tr>
                    @foreach ($columns as $key => $label)
                        <th @class([
                                'bg-ink-2 px-2.5 py-3.5 font-cond text-xs font-semibold tracking-wide uppercase',
                                'sticky left-0 z-20 text-left' => $key === 'name',
                                'text-center' => $key !== 'name',
                                'text-gold' => $sort === $key,
                                'text-bone-dim' => $sort !== $key,
                            ])>
                            <button type="button" wire:click="sortBy('{{ $key }}')" class="cursor-pointer uppercase hover:text-bone">
                                {{ $label }}
                                @if ($sort === $key)
                                    {{ $direction === 'asc' ? '↑' : '↓' }}
                                @endif
                            </button>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($summaries as $summary)
                    <tr class="border-t border-ink-3 hover:bg-gold/5 {{ $summary->isRanked ? '' : 'opacity-55' }}">
                        <td class="sticky left-0 z-10 bg-ink px-2.5 py-3 text-left font-cond text-base text-bone">
                            {{ $summary->user->displayName() }}
                            @unless ($summary->isRanked)
                                <span class="block font-cond text-[11px] tracking-wide-cond text-bone-dim uppercase">
                                    not ranked · needs {{ $minRank }} weigh-ins
                                </span>
                            @endunless
                        </td>
                        <td class="px-2.5 py-3 text-center font-display text-[15px]">{{ $summary->weeksRecorded }}</td>
                        <td class="px-2.5 py-3 text-center font-display text-[15px]">
                            {{ Fmt::weight($summary->currentWeight) }}
                        </td>
                        <td class="px-2.5 py-3 text-center font-display text-[15px] {{ Fmt::deltaColor($summary->deltaWeight) }}">
                            {{ Fmt::delta($summary->deltaWeight) }}
                        </td>
                        <td class="px-2.5 py-3 text-center font-display text-[15px] {{ Fmt::deltaColor($summary->percentChange) }}">
                            {{ Fmt::percent($summary->percentChange) }}
                        </td>
                        <td class="px-2.5 py-3 text-center font-display text-[15px]">
                            {{ Fmt::bmi($summary->currentBmi) }}
                        </td>
                    </tr>
                @endforeach

                @if ($summaries->isEmpty())
                    <tr>
                        <td colspan="6" class="px-5 py-8 text-center font-cond text-sm tracking-wide text-bone-dim uppercase">
                            Nobody matches this filter yet.
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    <p class="mt-3 font-cond text-xs tracking-wide text-bone-dim uppercase">
        <span class="text-gold">gold</span> = down ·
        <span class="text-blood-bright">red</span> = up ·
        ranking is on <b class="text-bone">% change</b>, because raw kg isn't comparable across body sizes
        or across different numbers of weeks · the "Weeks" column is there so no number reads as more
        than it is
    </p>
    @endif
</div>
