@use('App\Support\Fmt')

<div class="mx-auto max-w-3xl px-5 py-12 pb-20">
    <x-ui.eyebrow class="text-center">Your progress</x-ui.eyebrow>
    <h1 class="mt-3 mb-8 text-center font-display text-4xl leading-[0.9] font-bold uppercase sm:text-5xl">
        {{ auth()->user()->name }}
    </h1>

    @if (! $summary->hasData())
        <x-ui.panel class="text-center">
            <p class="font-display text-2xl font-semibold uppercase">No weigh-ins yet</p>
            <p class="mt-2 font-cond text-[15px] tracking-wide text-bone-dim uppercase">
                Your first weigh-in becomes your baseline — everything is measured from there,
                so joining late costs you nothing.
            </p>
        </x-ui.panel>
    @else
        {{-- Headline: change from their own baseline. --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <x-ui.panel class="text-center">
                <div class="font-cond text-xs tracking-label text-bone-dim uppercase">Since your first weigh-in</div>
                <div class="mt-1.5 font-display text-[44px] leading-none font-bold {{ Fmt::deltaColor($summary->deltaWeight) }}">
                    {{ Fmt::delta($summary->deltaWeight) }}<small class="text-base text-bone-dim">kg</small>
                </div>
                <div class="mt-1.5 font-cond text-xs tracking-wide-cond text-bone-dim uppercase">
                    {{ Fmt::percent($summary->percentChange) }} · over {{ $summary->weeksRecorded }}
                    {{ Str::plural('week', $summary->weeksRecorded) }}
                </div>
            </x-ui.panel>

            <x-ui.panel class="text-center">
                <div class="font-cond text-xs tracking-label text-bone-dim uppercase">Current weight</div>
                <div class="mt-1.5 font-display text-[44px] leading-none font-bold text-bone">
                    {{ Fmt::weight($summary->currentWeight) }}<small class="text-base text-bone-dim">kg</small>
                </div>
                <div class="mt-1.5 font-cond text-xs tracking-wide-cond text-bone-dim uppercase">
                    started at {{ Fmt::weight($summary->baselineWeight) }} kg
                </div>
            </x-ui.panel>

            <x-ui.panel class="text-center">
                <div class="font-cond text-xs tracking-label text-bone-dim uppercase">The verdict</div>
                <div class="mt-1.5 font-display text-[44px] leading-none font-bold {{ $category?->colorClass() ?? 'text-ink-3' }}">
                    {{ Fmt::bmi($summary->currentBmi) }}
                </div>
                <div class="mt-1.5 font-cond text-xs tracking-wide-cond text-bone-dim uppercase">
                    @if ($category)
                        BMI · <b class="text-bone">{{ $category->label() }}</b>
                    @else
                        BMI needs your height on file
                    @endif
                </div>
            </x-ui.panel>
        </div>

        {{-- The line --}}
        <x-ui.section-head>Your line</x-ui.section-head>
        <x-ui.panel class="text-bone">
            <x-ui.line-chart :points="$chartPoints" />
        </x-ui.panel>

        {{-- Weekly records, with provenance: staff don't log their own weight,
             so it must be obvious who did and when. --}}
        <x-ui.section-head>Week by week</x-ui.section-head>
        <x-ui.panel :padded="false">
            <div class="divide-y divide-ink-3">
                @foreach ($series->reverse() as $record)
                    @php $change = $changes[$record->week_start_date->toDateString()] ?? null; @endphp
                    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5">
                        <div>
                            <span class="font-display text-[17px] font-semibold uppercase">
                                {{ $record->week()->shortLabel() }}
                            </span>
                            <span class="ml-2 font-cond text-sm tracking-wide text-bone-dim uppercase">
                                {{ $record->week()->startDate->format('j M') }}
                            </span>
                            <span class="block font-cond text-[11px] tracking-wide-cond text-bone-dim uppercase">
                                recorded by {{ $record->recordedBy?->name ?? 'admin' }}
                                on {{ $record->created_at->format('j M') }}
                            </span>
                        </div>

                        <div class="flex items-baseline gap-4">
                            <span class="font-display text-xl font-semibold text-bone">
                                {{ Fmt::weight((float) $record->weight_kg) }}<small class="text-xs text-bone-dim">kg</small>
                            </span>
                            <span class="w-14 text-right font-display text-[15px] {{ Fmt::deltaColor($change) }}">
                                {{ $change === null ? '·' : Fmt::delta($change) }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-ui.panel>
    @endif

    {{-- Team context, deliberately gentle: the team's number, never your rank in it. --}}
    @if ($teamStanding)
        <x-ui.section-head>Your team</x-ui.section-head>
        <x-ui.panel class="text-center">
            <div class="font-display text-2xl font-bold tracking-wide uppercase">{{ $teamStanding->team->name }}</div>
            <div class="mt-1.5 font-display text-[34px] leading-none font-semibold {{ Fmt::deltaColor($teamStanding->averagePercentChange) }}">
                {{ Fmt::percent($teamStanding->averagePercentChange) }}
            </div>
            <div class="mt-1.5 font-cond text-xs tracking-wide-cond text-bone-dim uppercase">
                average change across {{ $teamStanding->countedMembers }} of {{ $teamStanding->memberCount }} members
            </div>
        </x-ui.panel>
    @endif

    @if ($attendance['opportunities'] > 0)
        <x-ui.section-head>Turning up</x-ui.section-head>
        <x-ui.panel class="text-center">
            <div class="font-display text-[34px] leading-none font-semibold text-gold">
                {{ $attendance['attended'] }}<span class="text-bone-dim">/{{ $attendance['opportunities'] }}</span>
            </div>
            <div class="mt-1.5 font-cond text-xs tracking-wide-cond text-bone-dim uppercase">
                events you've made so far
            </div>
        </x-ui.panel>
    @endif

    @if ($myEvents->isNotEmpty())
        <x-ui.section-head>You're down for</x-ui.section-head>
        <x-ui.panel :padded="false">
            <div class="divide-y divide-ink-3">
                @foreach ($myEvents as $response)
                    <div class="flex items-center justify-between gap-3 px-5 py-3.5">
                        <div>
                            <div class="font-display text-[17px] font-semibold uppercase">
                                {{ $response->event->title }}
                            </div>
                            <div class="font-cond text-sm tracking-wide text-bone-dim uppercase">
                                {{ $response->event->starts_at->format('D j M, g:ia') }}
                            </div>
                        </div>
                        <span @class([
                                'rounded-sm px-3 py-1 font-cond text-xs font-semibold tracking-wide-cond uppercase',
                                'bg-gold/20 text-gold-bright' => $response->response->value === 'yes',
                                'bg-bone/10 text-bone-dim' => $response->response->value !== 'yes',
                            ])>
                            {{ $response->response->label() }}
                        </span>
                    </div>
                @endforeach
            </div>
        </x-ui.panel>
    @endif

    <p class="mt-10 text-center font-cond text-xs tracking-wide text-bone-dim uppercase">
        Only you and the organising admins can see these figures.
    </p>
</div>
