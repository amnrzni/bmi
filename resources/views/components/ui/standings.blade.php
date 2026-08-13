@props(['standings'])

{{--
    Team A vs B, measured as average % change from baseline — never raw kg.
    Both teams get identical treatment: no gold-vs-plain, no favoured side.

    Kept to the single headline figure on purpose. The member-count and turnout
    detail lines were removed as clutter; the "weeks recorded" column on the
    analytics summary is where the honesty-about-sample-size work is done.
--}}
@use('App\Support\Fmt')

<div class="grid grid-cols-1 items-stretch border border-ink-3 bg-ink-2 sm:grid-cols-[1fr_auto_1fr]">
    @foreach ($standings as $index => $standing)
        @if ($index > 0)
            <div class="flex items-center justify-center border-y border-ink-3 bg-ink px-1 py-2 font-display text-[22px] font-bold text-blood sm:border-x sm:border-y-0 sm:py-0">
                VS
            </div>
        @endif

        <div class="relative px-6 py-7 text-center">
            <div class="font-cond text-[13px] tracking-[0.2em] text-bone-dim uppercase">Team</div>
            <div class="mt-1 mb-3.5 font-display text-[34px] font-bold tracking-wide text-bone uppercase">
                {{ $standing->team->name }}
            </div>

            <div class="font-display text-[44px] leading-none font-semibold {{ Fmt::deltaColor($standing->averagePercentChange) }}">
                {{ Fmt::percent($standing->averagePercentChange) }}
                <small class="font-medium text-base text-bone-dim">avg weight</small>
            </div>

            @unless ($standing->hasData())
                {{-- Kept: without it a blank figure looks broken rather than early. --}}
                <div class="mt-2 font-cond text-xs tracking-label text-bone-dim uppercase">
                    not enough data yet · {{ $standing->memberCount }} members
                </div>
            @endunless
        </div>
    @endforeach
</div>

@if (collect($standings)->every(fn ($s) => ! $s->hasData()))
    <p class="mt-2 text-center font-cond text-xs tracking-wide-cond text-bone-dim uppercase">
        Team averages need {{ config('challenge.min_records_team') }} weigh-ins per person — they appear once week
        {{ config('challenge.min_records_team') }} is recorded.
    </p>
@endif
