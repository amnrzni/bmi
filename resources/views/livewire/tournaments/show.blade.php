@use('App\Enums\Division')
@use('App\Livewire\Tournaments\Show')

@php
    $words = preg_split('/\s+/', trim($tournament->name));
    $lastWord = array_pop($words);

    $pill = 'cursor-pointer rounded-sm border px-4 py-2 font-cond text-[13px] font-semibold tracking-wide-cond uppercase transition';
    $subPill = 'cursor-pointer rounded-sm border px-4 py-1.5 font-cond text-xs font-semibold tracking-wide-cond uppercase transition';
    $smallBtn = 'cursor-pointer rounded-sm border px-3 py-1.5 font-cond text-xs font-semibold tracking-wide-cond uppercase transition disabled:cursor-not-allowed disabled:opacity-40';
    $scoreInput = 'w-12 border border-gold/50 bg-ink px-1 py-1.5 text-center font-display text-lg text-bone outline-none focus:border-gold-bright sm:w-14';
    $badge = 'flex-none rounded-sm border px-2 py-1 font-cond text-[10px] font-semibold tracking-wide-cond uppercase';
    $error = 'w-full font-cond text-sm text-blood-bright';
@endphp

{{-- Viewers poll; admins don't, so a refresh never lands on a half-typed score. --}}
<div @unless ($isAdmin) wire:poll.15s @endunless>
    @include('livewire.tournaments.partials.guest-bar')

    <div class="mx-auto max-w-4xl px-5 py-12 pb-20">
        <x-ui.eyebrow class="text-center">Tournament</x-ui.eyebrow>
        <h1 class="mt-3 text-center font-display text-4xl leading-[0.95] font-bold uppercase sm:text-6xl">
            {{ implode(' ', $words) }} <span class="text-blood">{{ $lastWord }}</span>
        </h1>
        <p class="mt-3 text-center font-cond text-sm tracking-wide text-bone-dim uppercase">
            @if ($tournament->starts_at)
                <b class="font-semibold text-bone">{{ $tournament->starts_at->format('j M') }}</b>
                · {{ $tournament->starts_at->format('D g:ia') }}
            @endif
            @if ($tournament->location) · {{ $tournament->location }} @endif
        </p>

        @if ($isAdmin)
            <div class="mt-5 flex flex-wrap justify-center gap-2">
                <a href="{{ route('tournaments.setup', $tournament) }}"
                   class="{{ $smallBtn }} border-gold text-gold hover:bg-gold hover:text-ink">Set up teams &amp; fixtures</a>
                <button type="button" wire:click="export" class="{{ $smallBtn }} border-ink-3 text-bone-dim hover:text-bone">
                    Export CSV
                </button>
            </div>
        @endif

        <div class="mt-8 flex flex-wrap justify-center gap-1.5" role="tablist">
            @foreach (Show::TABS as $key => $label)
                <button type="button" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                        wire:click="showTab('{{ $key }}')"
                        @class([
                            $pill,
                            'border-gold bg-gold text-ink' => $tab === $key,
                            'border-ink-3 text-bone-dim hover:text-bone' => $tab !== $key,
                        ])>{{ $label }}</button>
            @endforeach
        </div>

        @if ($flash)
            <div class="mt-6 border border-gold bg-gold/10 px-4 py-3 font-cond text-sm tracking-wide text-bone" role="status">
                {{ $flash }}
            </div>
        @endif

        {{-- ============================================================ programme --}}
        @if ($tab === 'programme')
            <x-ui.section-head>Programme</x-ui.section-head>

            @if (empty($programme))
                <p class="border border-ink-3 bg-ink-2 px-6 py-8 text-center font-cond text-sm tracking-wide text-bone-dim uppercase">
                    The programme hasn't been posted yet.
                </p>
            @else
                <div class="overflow-x-auto border border-ink-3 bg-ink-2">
                    <table class="w-full font-cond text-[15px]">
                        <thead>
                            <tr class="border-b border-ink-3 text-left text-[11px] tracking-label text-gold uppercase">
                                <th class="px-4 py-3 font-semibold">Time</th>
                                <th class="px-4 py-3 font-semibold">Activity</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-3">
                            @foreach ($programme as $row)
                                <tr @class(['bg-bone/[0.02]' => $row['kind'] === 'break'])>
                                    <td @class([
                                        'w-px px-4 py-3 align-top font-semibold whitespace-nowrap',
                                        'text-bone' => in_array($row['kind'], ['match', 'final'], true),
                                        'text-gold-bright' => ! in_array($row['kind'], ['match', 'final'], true),
                                    ])>{{ $row['time'] }}</td>
                                    <td class="px-4 py-3">
                                        <span @class([
                                            'font-medium tracking-wide',
                                            'text-bone-dim' => $row['kind'] === 'break',
                                            'text-bone' => $row['kind'] !== 'break',
                                        ])>{{ $row['activity'] }}</span>
                                        @if ($row['kind'] === 'final')
                                            <span class="{{ $badge }} ml-2 border-gold/60 bg-gold/10 text-gold-bright">Final</span>
                                        @endif
                                        @if ($row['detail'] !== '')
                                            <span class="mt-0.5 block text-[13px] text-bone-dim">{{ $row['detail'] }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif

        {{-- =============================================================== roster --}}
        @if ($tab === 'roster')
            <x-ui.section-head>Roster</x-ui.section-head>

            @if ($isAdmin)
                <p class="-mt-1 mb-4 font-cond text-sm tracking-wide text-bone-dim">
                    Tap <b class="text-gold">C</b> to set a captain and <b class="text-blood-bright">Out</b> for anyone not attending.
                </p>
            @endif

            <div class="grid gap-3.5 sm:grid-cols-2">
                @forelse ($board->teams as $team)
                    <div class="border border-ink-3 bg-ink-2" wire:key="team-{{ $team->id }}">
                        <h3 class="bg-gold px-4 py-3 font-display text-base font-bold tracking-wider text-ink uppercase">
                            {{ $team->name }}
                        </h3>

                        @foreach (Division::cases() as $squadDivision)
                            @php $squad = $team->players->filter(fn ($p) => $p->division === $squadDivision); @endphp
                            <div class="px-4 pt-3 pb-1">
                                <div class="mb-1 font-cond text-[11px] font-semibold tracking-label text-gold uppercase">
                                    {{ $squadDivision->label() }} <span class="font-normal text-bone-dim">{{ $squad->count() }}</span>
                                </div>

                                @forelse ($squad as $player)
                                    <div class="flex items-center justify-between gap-2 border-b border-ink-3 py-1.5 last:border-b-0"
                                         wire:key="player-{{ $player->id }}">
                                        <span @class([
                                            'min-w-0 truncate font-cond text-[15px] tracking-wide uppercase',
                                            'text-bone' => ! $player->is_out,
                                            'text-bone-dim line-through' => $player->is_out,
                                        ])>{{ $player->displayName() }}</span>

                                        <span class="flex flex-none items-center gap-1.5">
                                            @if ($isAdmin)
                                                <button type="button" wire:click="toggleCaptain({{ $player->id }})"
                                                        aria-pressed="{{ $player->is_captain ? 'true' : 'false' }}"
                                                        aria-label="Captain: {{ $player->displayName() }}"
                                                        @class([
                                                            $smallBtn, 'px-2.5 py-1',
                                                            'border-gold bg-gold/15 text-gold-bright' => $player->is_captain,
                                                            'border-ink-3 text-bone-dim hover:text-bone' => ! $player->is_captain,
                                                        ])>C</button>
                                                <button type="button" wire:click="toggleOut({{ $player->id }})"
                                                        aria-pressed="{{ $player->is_out ? 'true' : 'false' }}"
                                                        aria-label="Not attending: {{ $player->displayName() }}"
                                                        @class([
                                                            $smallBtn, 'px-2.5 py-1',
                                                            'border-blood bg-blood/15 text-blood-bright' => $player->is_out,
                                                            'border-ink-3 text-bone-dim hover:text-bone' => ! $player->is_out,
                                                        ])>Out</button>
                                            @else
                                                @if ($player->is_captain)
                                                    <span class="{{ $badge }} border-gold/60 bg-gold/10 text-gold-bright" title="Captain">C</span>
                                                @endif
                                                @if ($player->is_out)
                                                    <span class="{{ $badge }} border-blood bg-blood/10 text-blood-bright">Out</span>
                                                @endif
                                            @endif
                                        </span>
                                    </div>
                                @empty
                                    <p class="py-1.5 font-cond text-sm text-bone-dim">No one yet.</p>
                                @endforelse
                            </div>
                        @endforeach

                        <div class="px-4 pt-2 pb-3 font-cond text-xs tracking-wide text-bone-dim">
                            {{ $team->players->count() }} registered · {{ $team->players->where('is_out', true)->count() }} not attending
                        </div>
                    </div>
                @empty
                    <p class="border border-ink-3 bg-ink-2 px-6 py-8 text-center font-cond text-sm tracking-wide text-bone-dim uppercase sm:col-span-2">
                        Teams haven't been drawn yet.
                    </p>
                @endforelse
            </div>
        @endif

        {{-- ============================================================== matches --}}
        @if ($tab === 'matches')
            <x-ui.section-head>Group matches</x-ui.section-head>

            <div class="mb-4 flex gap-2">
                @foreach (Division::cases() as $option)
                    <button type="button" wire:click="showDivision('{{ $option->value }}')"
                            aria-pressed="{{ $currentDivision === $option ? 'true' : 'false' }}"
                            @class([
                                $subPill,
                                'border-blood bg-blood text-bone' => $currentDivision === $option,
                                'border-ink-3 bg-ink-2 text-bone-dim hover:text-bone' => $currentDivision !== $option,
                            ])>{{ $option->label() }}</button>
                @endforeach
            </div>

            @forelse ($board->fixtures as $fixture)
                @php
                    $score = $board->score($fixture, $currentDivision);
                    $home = $board->team($fixture->home_team_id);
                    $away = $board->team($fixture->away_team_id);
                    $homeWon = $score && $score->home_score > $score->away_score;
                    $awayWon = $score && $score->away_score > $score->home_score;
                @endphp

                <div class="mb-2.5 flex flex-wrap items-center gap-3 border border-ink-3 bg-ink-2 px-3 py-3.5 sm:px-4"
                     wire:key="fixture-{{ $fixture->id }}-{{ $currentDivision->value }}">
                    <span class="flex h-8 w-8 flex-none items-center justify-center rounded-full border border-ink-3 font-display text-sm text-bone-dim">
                        {{ $fixture->number }}
                    </span>

                    <div class="grid min-w-0 flex-1 grid-cols-[1fr_auto_1fr] items-center gap-2 sm:gap-3">
                        <span @class(['text-right font-display text-[13px] leading-tight font-semibold tracking-wide uppercase sm:text-[15px]', 'text-gold-bright' => $homeWon])>
                            {{ $home?->name }}
                        </span>

                        @if ($isAdmin)
                            <span class="flex items-center gap-1.5">
                                <input type="number" inputmode="numeric" min="0" placeholder="–"
                                       wire:model="entry.{{ $fixture->id }}.home"
                                       aria-label="{{ $home?->name }} score" class="{{ $scoreInput }}">
                                <span class="text-bone-dim">:</span>
                                <input type="number" inputmode="numeric" min="0" placeholder="–"
                                       wire:model="entry.{{ $fixture->id }}.away"
                                       aria-label="{{ $away?->name }} score" class="{{ $scoreInput }}">
                            </span>
                        @else
                            <span class="flex min-w-16 items-center justify-center gap-2 font-display text-2xl font-bold">
                                <span>{{ $score?->home_score ?? '–' }}</span>
                                <span class="text-base text-bone-dim">:</span>
                                <span>{{ $score?->away_score ?? '–' }}</span>
                            </span>
                        @endif

                        <span @class(['font-display text-[13px] leading-tight font-semibold tracking-wide uppercase sm:text-[15px]', 'text-gold-bright' => $awayWon])>
                            {{ $away?->name }}
                        </span>
                    </div>

                    <span @class([
                        $badge,
                        'border-gold/50 bg-gold/10 text-gold' => $score,
                        'border-ink-3 text-bone-dim' => ! $score,
                    ])>{{ $score ? 'Played' : 'Upcoming' }}</span>

                    @if ($isAdmin)
                        <span class="flex flex-none gap-1.5">
                            <button type="button" wire:click="saveScore({{ $fixture->id }})" wire:loading.attr="disabled"
                                    class="{{ $smallBtn }} border-gold bg-gold text-ink hover:bg-gold-bright">Save</button>
                            <button type="button" wire:click="clearScore({{ $fixture->id }})" @disabled(! $score)
                                    wire:confirm="Clear the {{ $currentDivision->label() }} score for match {{ $fixture->number }}?"
                                    class="{{ $smallBtn }} border-ink-3 text-bone-dim hover:border-blood hover:text-blood-bright">Clear</button>
                        </span>
                        @error("entry.{$fixture->id}.home") <p class="{{ $error }}">{{ $message }}</p> @enderror
                        @error("entry.{$fixture->id}.away") <p class="{{ $error }}">{{ $message }}</p> @enderror
                    @endif
                </div>
            @empty
                <p class="border border-ink-3 bg-ink-2 px-6 py-8 text-center font-cond text-sm tracking-wide text-bone-dim uppercase">
                    No matches have been drawn yet.
                </p>
            @endforelse

            {{-- ---------------------------------------------------------- final --}}
            @php
                $finalHome = $final ? $board->team($final->home_team_id) : ($seeded[0] ?? null);
                $finalAway = $final ? $board->team($final->away_team_id) : ($seeded[1] ?? null);
                $finalOpen = $final || $seeded;
                $finalWinner = $final?->winnerTeamId();
            @endphp

            @if ($board->fixtures->isNotEmpty())
                <div class="mt-7 mb-3 flex flex-wrap items-baseline gap-3 border-t border-ink-3 pt-5">
                    <span class="font-display text-sm font-semibold tracking-label text-gold-bright uppercase">Final</span>
                    @unless ($finalOpen)
                        <em class="font-cond text-sm text-bone-dim">Finalists appear once every group match has a score</em>
                    @endunless
                </div>

                <div @class([
                        'flex flex-wrap items-center gap-3 border bg-ink-2 px-3 py-3.5 sm:px-4',
                        'border-gold/60' => $finalOpen,
                        'border-ink-3' => ! $finalOpen,
                     ])
                     wire:key="final-{{ $currentDivision->value }}">
                    <span class="flex h-8 w-8 flex-none items-center justify-center rounded-full border border-gold/60 font-display text-sm text-gold-bright">F</span>

                    <div class="grid min-w-0 flex-1 grid-cols-[1fr_auto_1fr] items-center gap-2 sm:gap-3">
                        <span @class([
                            'text-right font-display text-[13px] leading-tight font-semibold tracking-wide uppercase sm:text-[15px]',
                            'text-gold-bright' => $finalWinner && $finalWinner === $finalHome?->id,
                            'text-bone-dim' => ! $finalOpen,
                        ])>{{ $finalHome?->name ?? 'Group winner' }}</span>

                        @if ($isAdmin && $finalOpen)
                            <span class="flex items-center gap-1.5">
                                <input type="number" inputmode="numeric" min="0" placeholder="–" wire:model="finalEntry.home"
                                       aria-label="{{ $finalHome?->name }} final score" class="{{ $scoreInput }}">
                                <span class="text-bone-dim">:</span>
                                <input type="number" inputmode="numeric" min="0" placeholder="–" wire:model="finalEntry.away"
                                       aria-label="{{ $finalAway?->name }} final score" class="{{ $scoreInput }}">
                            </span>
                        @else
                            <span class="flex min-w-16 flex-col items-center">
                                <span class="flex items-center gap-2 font-display text-2xl font-bold">
                                    <span>{{ $final?->home_score ?? '–' }}</span>
                                    <span class="text-base text-bone-dim">:</span>
                                    <span>{{ $final?->away_score ?? '–' }}</span>
                                </span>
                                @if ($final?->wentToPenalties())
                                    <span class="font-cond text-xs tracking-wide text-bone-dim uppercase">
                                        Pens {{ $final->home_penalties }}–{{ $final->away_penalties }}
                                    </span>
                                @endif
                            </span>
                        @endif

                        <span @class([
                            'font-display text-[13px] leading-tight font-semibold tracking-wide uppercase sm:text-[15px]',
                            'text-gold-bright' => $finalWinner && $finalWinner === $finalAway?->id,
                            'text-bone-dim' => ! $finalOpen,
                        ])>{{ $finalAway?->name ?? 'Runner-up' }}</span>
                    </div>

                    <span @class([
                        $badge,
                        'border-gold/50 bg-gold/10 text-gold' => $final,
                        'border-ink-3 text-bone-dim' => ! $final,
                    ])>{{ $final ? 'Played' : ($finalOpen ? 'Upcoming' : 'Awaiting group') }}</span>

                    @if ($isAdmin && $finalOpen)
                        <span class="flex flex-none gap-1.5">
                            <button type="button" wire:click="saveFinal" wire:loading.attr="disabled"
                                    class="{{ $smallBtn }} border-gold bg-gold text-ink hover:bg-gold-bright">Save</button>
                            <button type="button" wire:click="clearFinal" @disabled(! $final)
                                    wire:confirm="Clear the {{ $currentDivision->label() }} final? Its finalists will be re-seeded from the table."
                                    class="{{ $smallBtn }} border-ink-3 text-bone-dim hover:border-blood hover:text-blood-bright">Clear</button>
                        </span>

                        <div class="flex w-full flex-wrap items-center justify-center gap-2 border-t border-ink-3 pt-3 font-cond text-xs tracking-wide text-bone-dim uppercase">
                            <span>Penalties, only if level</span>
                            <input type="number" inputmode="numeric" min="0" placeholder="–" wire:model="finalEntry.home_penalties"
                                   aria-label="{{ $finalHome?->name }} penalties" class="{{ $scoreInput }} !w-12 !text-base">
                            <span>:</span>
                            <input type="number" inputmode="numeric" min="0" placeholder="–" wire:model="finalEntry.away_penalties"
                                   aria-label="{{ $finalAway?->name }} penalties" class="{{ $scoreInput }} !w-12 !text-base">
                        </div>

                        @error('finalEntry') <p class="{{ $error }} text-center">{{ $message }}</p> @enderror
                        @foreach (['home', 'away', 'home_penalties', 'away_penalties'] as $field)
                            @error("finalEntry.{$field}") <p class="{{ $error }} text-center">{{ $message }}</p> @enderror
                        @endforeach
                    @endif
                </div>

                @if ($isAdmin && $board->finalistsOutOfDate($currentDivision))
                    <p class="mt-2 border border-blood bg-blood/10 px-4 py-2.5 font-cond text-sm tracking-wide text-bone">
                        The group table has changed since this final was saved, and it no longer agrees with who played in it.
                        Clear the final to re-seed it from the table.
                    </p>
                @endif

                @if ($final)
                    <div class="mt-3 flex flex-wrap items-center justify-center gap-3 border border-gold/60 bg-gold/10 px-4 py-3.5 text-center">
                        <span class="font-cond text-[11px] font-semibold tracking-label text-gold uppercase">
                            {{ $currentDivision->label() }}'s champion
                        </span>
                        <span class="font-display text-xl font-bold tracking-wide text-gold-bright uppercase">
                            {{ $board->team($finalWinner)?->name }}
                        </span>
                        @if ($final->wentToPenalties())
                            <span class="font-cond text-xs tracking-wide text-bone-dim uppercase">on penalties</span>
                        @endif
                    </div>
                @endif
            @endif
        @endif

        {{-- ============================================================ standings --}}
        @if ($tab === 'standings')
            <x-ui.section-head>Group standings</x-ui.section-head>

            <div class="mb-4 flex flex-wrap gap-2">
                @foreach (['men' => 'Men', 'women' => 'Women', 'combined' => 'Combined'] as $key => $label)
                    <button type="button" wire:click="showTable('{{ $key }}')"
                            aria-pressed="{{ $table === $key ? 'true' : 'false' }}"
                            @class([
                                $subPill,
                                'border-blood bg-blood text-bone' => $table === $key,
                                'border-ink-3 bg-ink-2 text-bone-dim hover:text-bone' => $table !== $key,
                            ])>{{ $label }}</button>
                @endforeach
            </div>

            @if ($table === 'combined')
                <p class="-mt-1 mb-4 font-cond text-sm text-bone-dim italic">
                    Men's and women's group results added together, for information. Finals aren't included.
                </p>
            @endif

            @php $anyPlayed = $standings->contains(fn ($line) => $line->played() > 0); @endphp

            <div class="overflow-x-auto border border-ink-3 bg-ink-2">
                <table class="w-full min-w-[460px] font-cond text-[15px]">
                    <thead>
                        <tr class="border-b border-ink-3 text-center text-[11px] tracking-wide-cond text-gold uppercase">
                            <th class="px-2 py-3 font-semibold">#</th>
                            <th class="px-2 py-3 text-left font-semibold">Team</th>
                            @foreach (['P' => 'Played', 'W' => 'Won', 'D' => 'Drawn', 'L' => 'Lost', 'SF' => 'Scored for', 'SA' => 'Scored against', 'SD' => 'Score difference', 'PTS' => 'Points'] as $abbr => $title)
                                <th class="px-2 py-3 font-semibold"><abbr title="{{ $title }}" class="no-underline">{{ $abbr }}</abbr></th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-3 text-center">
                        @forelse ($standings as $index => $line)
                            <tr wire:key="standing-{{ $table }}-{{ $line->team->id }}">
                                <td @class(['px-2 py-3 font-semibold', 'text-gold-bright' => $index === 0 && $anyPlayed, 'text-bone-dim' => ! ($index === 0 && $anyPlayed)])>{{ $index + 1 }}</td>
                                <td class="px-2 py-3 text-left font-semibold tracking-wide uppercase">{{ $line->team->name }}</td>
                                <td class="px-2 py-3">{{ $line->played() }}</td>
                                <td class="px-2 py-3">{{ $line->won }}</td>
                                <td class="px-2 py-3">{{ $line->drawn }}</td>
                                <td class="px-2 py-3">{{ $line->lost }}</td>
                                <td class="px-2 py-3">{{ $line->scoredFor }}</td>
                                <td class="px-2 py-3">{{ $line->scoredAgainst }}</td>
                                <td class="px-2 py-3">{{ $line->difference() > 0 ? '+' : '' }}{{ $line->difference() }}</td>
                                <td class="px-2 py-3 font-display font-bold text-gold-bright">{{ $line->points() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-6 py-8 text-sm tracking-wide text-bone-dim uppercase">No teams yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <p class="mt-3 font-cond text-xs tracking-wide text-bone-dim">
                Win 3 · draw 1 · loss 0. Level on points: score difference, then scored, then list order.
            </p>
        @endif
    </div>
</div>
