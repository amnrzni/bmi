@props(['standings'])

{{--
    Team leaderboard, ranked by average % change from baseline — never raw kg,
    since a heavier team would win by default on kilograms.

    Teams with no data yet sink to the bottom rather than topping the board on a
    technicality, the same rule the individual leaderboard uses. Ranking is
    computed here so both callers (Home, Analytics) stay one-liners.
--}}
@use('App\Support\Fmt')

@php
    $ranked = collect($standings)
        ->sortBy([
            fn ($s) => $s->hasData() ? 0 : 1,                 // data first
            fn ($s) => $s->averagePercentChange ?? INF,       // most loss on top
        ])
        ->values();
@endphp

<div class="border border-ink-3 bg-ink-2">
    <div class="border-b border-ink-3 px-5 py-3 text-center font-cond text-[13px] tracking-[0.2em] text-bone-dim uppercase">
        Team standings
    </div>

    <ol class="divide-y divide-ink-3">
        @foreach ($ranked as $index => $standing)
            @php $rank = $standing->hasData() ? $index + 1 : null; @endphp
            <li class="flex items-center gap-4 px-5 py-3.5">
                {{-- Rank badge; leader gets the gold. --}}
                <span @class([
                        'flex h-8 w-8 flex-none items-center justify-center rounded-sm font-display text-lg font-bold',
                        'bg-gold text-ink' => $rank === 1,
                        'bg-ink-3 text-bone' => $rank && $rank > 1,
                        'bg-ink text-bone-dim' => ! $rank,
                    ])>{{ $rank ?? '—' }}</span>

                <span class="min-w-0 flex-1">
                    <span class="block truncate font-display text-[19px] font-semibold tracking-wide text-bone uppercase">
                        {{ $standing->team->name }}
                    </span>
                    <span class="block font-cond text-[11px] tracking-wide-cond text-bone-dim uppercase">
                        @if ($standing->hasData())
                            {{ $standing->countedMembers }} of {{ $standing->memberCount }} members counted
                        @else
                            not enough data yet · {{ $standing->memberCount }} {{ Str::plural('member', $standing->memberCount) }}
                        @endif
                    </span>
                </span>

                <span class="flex-none text-right font-display text-[26px] leading-none font-semibold {{ Fmt::deltaColor($standing->averagePercentChange) }}">
                    {{ Fmt::percent($standing->averagePercentChange) }}
                    <span class="block font-cond text-[10px] font-medium tracking-wide-cond text-bone-dim">avg weight</span>
                </span>
            </li>
        @endforeach

        @if ($ranked->isEmpty())
            <li class="px-5 py-8 text-center font-cond text-sm tracking-wide text-bone-dim uppercase">
                No teams yet.
            </li>
        @endif
    </ol>
</div>
