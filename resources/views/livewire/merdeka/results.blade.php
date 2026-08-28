@php
    $panelClass = 'border border-merdeka-red/25 bg-merdeka-ink-2';
    $labelClass = 'mb-2 block font-cond text-[13px] tracking-label text-merdeka-red-soft uppercase';
    $trim = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
@endphp

<div>
    @if ($flash)
        <div class="mb-5 border border-merdeka-red bg-merdeka-red/10 px-4 py-3 font-cond text-sm tracking-wide">
            {{ $flash }}
        </div>
    @endif

    {{-- ---------------------------------------------------- one filed sheet --}}
    @if ($openScore)
        <button type="button" wire:click="closeScore"
                class="mb-4 cursor-pointer font-cond text-sm tracking-wide text-merdeka-red-soft">
            ‹ Kembali ke ringkasan
        </button>

        <div class="{{ $panelClass }} p-5 sm:p-7">
            <div class="font-cond text-[13px] tracking-label text-merdeka-red-soft uppercase">
                {{ $openScore->department?->name }}
            </div>
            <h2 class="mt-1 font-display text-2xl font-bold uppercase">{{ $openScore->user?->name ?? 'Hakim' }}</h2>
            <p class="mt-1 mb-6 font-cond text-sm text-merdeka-muted">
                Dihantar pada {{ $openScore->submitted_at->timezone(config('app.timezone'))->format('j M Y, g:ia') }}
            </p>

            @foreach ($criteria as $id => $criterion)
                <div class="flex items-center justify-between gap-3 border-b border-merdeka-red/25 py-2.5 font-cond text-sm last:border-b-0">
                    <span class="text-merdeka-muted">{{ $criterion['name'] }} ({{ $criterion['weight'] }}%)</span>
                    <span class="shrink-0 text-right font-semibold">
                        @if ($band = $openScore->band($id))
                            Skala {{ $band }} — {{ $trim($openScore->earned($id)) }}
                        @else
                            —
                        @endif
                    </span>
                </div>
            @endforeach

            <div class="my-4 flex items-center justify-between border border-merdeka-red bg-merdeka-red/15 px-5 py-4">
                <span class="font-cond text-sm tracking-wide uppercase">Jumlah Keseluruhan</span>
                <span class="font-display text-3xl font-bold text-merdeka-red-soft">
                    {{ $trim($openScore->total) }} <span class="text-lg text-merdeka-muted">/ 100</span>
                </span>
            </div>

            <span class="{{ $labelClass }}">Ulasan Panel Hakim</span>
            <div class="border border-merdeka-red/25 bg-merdeka-ink px-4 py-3 font-cond text-sm leading-relaxed">
                {{ $openScore->ulasan ?: 'Tiada ulasan diberikan.' }}
            </div>

            <div class="mt-5">
                <span class="{{ $labelClass }}">Tandatangan</span>
                <div class="bg-merdeka-cream p-2">
                    <img src="{{ $openScore->signature }}" alt="Tandatangan {{ $openScore->user?->name }}" class="block w-full">
                </div>
            </div>
        </div>

    {{-- --------------------------------------------------------- summary --}}
    @else
        <div class="mb-5 grid grid-cols-2 gap-2.5">
            <div class="{{ $panelClass }} px-4 py-3.5">
                <div class="font-display text-2xl font-bold text-merdeka-red-soft">{{ $filedCount }} / {{ $expected }}</div>
                <div class="font-cond text-[13px] tracking-label text-merdeka-muted uppercase">Penilaian Dihantar</div>
            </div>
            <div class="{{ $panelClass }} px-4 py-3.5">
                <div class="font-display text-2xl font-bold text-merdeka-red-soft">{{ $departmentsComplete }} / {{ $departmentCount }}</div>
                <div class="font-cond text-[13px] tracking-label text-merdeka-muted uppercase">Jabatan Lengkap</div>
            </div>
        </div>

        @if ($ranked->isNotEmpty())
            <div class="{{ $panelClass }} mb-5 p-5">
                <h2 class="mb-3 font-display text-lg font-bold tracking-wide uppercase">Kedudukan Semasa</h2>
                <p class="mb-4 font-cond text-[13px] text-merdeka-muted">
                    Purata markah panel. Kedudukan berubah selagi ada penilaian yang belum dihantar.
                </p>
                @foreach ($ranked as $index => $row)
                    <div class="flex items-center gap-3 border-b border-merdeka-red/25 py-2.5 last:border-b-0">
                        <div class="flex size-7 shrink-0 items-center justify-center border border-merdeka-red/25 bg-merdeka-ink font-display text-[13px] font-bold text-merdeka-red-soft">
                            {{ $index + 1 }}
                        </div>
                        <div class="min-w-0 flex-1 truncate font-cond text-[15px] font-semibold">{{ $row['department']->name }}</div>
                        @if ($row['missing']->isNotEmpty())
                            <span class="shrink-0 font-cond text-[12px] tracking-wide text-merdeka-muted uppercase">
                                {{ $row['filed']->count() }} drpd {{ $judges->count() }} hakim
                            </span>
                        @endif
                        <div class="shrink-0 font-display text-lg font-bold text-merdeka-red-soft">{{ $trim($row['average']) }}</div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="space-y-3">
            @foreach ($rows as $row)
                <div wire:key="row-{{ $row['department']->id }}" class="{{ $panelClass }} p-4">
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <span class="min-w-0 truncate font-cond text-[15px] font-semibold">{{ $row['department']->name }}</span>
                        @if ($row['average'] !== null)
                            <span class="shrink-0 font-display text-base text-merdeka-red-soft">Purata: {{ $trim($row['average']) }}</span>
                        @else
                            <span class="shrink-0 font-cond text-[13px] text-merdeka-muted">Menunggu penilaian</span>
                        @endif
                    </div>

                    @foreach ($row['filed'] as $score)
                        @php $offPanel = ! $judges->contains('user_id', $score->user_id); @endphp
                        <button type="button" wire:click="openScore({{ $score->id }})"
                                wire:key="score-{{ $score->id }}"
                                class="mt-2 flex w-full cursor-pointer items-center justify-between gap-3 border border-merdeka-red/25 bg-merdeka-ink px-3 py-2.5 text-left transition hover:border-merdeka-red">
                            <span class="min-w-0">
                                <span class="block truncate font-cond text-sm">{{ $score->user?->name ?? 'Hakim' }}</span>
                                <span class="block font-cond text-[13px] text-merdeka-good">
                                    Sudah dinilai
                                    @if ($offPanel)
                                        <span class="text-merdeka-muted">· bukan lagi ahli panel</span>
                                    @endif
                                </span>
                            </span>
                            <span class="shrink-0 font-display text-base text-merdeka-red-soft">{{ $trim($score->total) }}</span>
                        </button>
                    @endforeach

                    @foreach ($row['missing'] as $judge)
                        <div wire:key="missing-{{ $row['department']->id }}-{{ $judge->id }}"
                             class="mt-2 flex items-center justify-between gap-3 border border-merdeka-red/25 bg-merdeka-ink px-3 py-2.5 opacity-60">
                            <span class="min-w-0">
                                <span class="block truncate font-cond text-sm">{{ $judge->user?->name ?? 'Hakim' }}</span>
                                <span class="block font-cond text-[13px] text-merdeka-muted">Belum dinilai</span>
                            </span>
                            <span class="shrink-0 font-display text-base text-merdeka-muted">—</span>
                        </div>
                    @endforeach

                    @if ($judges->isEmpty() && $row['filed']->isEmpty())
                        <p class="mt-2 font-cond text-[13px] text-merdeka-muted">Tiada panel hakim dilantik lagi.</p>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- --------------------------------------------------- panel admin --}}
        <div class="{{ $panelClass }} mt-6 p-5">
            <h2 class="mb-1 font-display text-lg font-bold tracking-wide uppercase">Panel Hakim</h2>
            <p class="mb-4 font-cond text-[13px] leading-relaxed text-merdeka-muted">
                Hanya nama dalam senarai ini boleh membuka borang markah. Mereka mesti sudah berada dalam
                roster dengan e-mel QCXIS yang tepat — padanan log masuk dibuat pada e-mel sahaja.
            </p>

            @forelse ($judges as $judge)
                <div wire:key="judge-{{ $judge->id }}"
                     class="mb-2 flex items-center justify-between gap-3 border border-merdeka-red/25 bg-merdeka-ink px-3 py-2.5">
                    <span class="min-w-0">
                        <span class="block truncate font-cond text-sm font-semibold">{{ $judge->user?->name ?? '—' }}</span>
                        <span class="block truncate font-cond text-[13px] text-merdeka-muted">
                            {{ $judge->title ?: 'Panel Hakim' }} · {{ $judge->user?->email }}
                        </span>
                    </span>
                    <button type="button" wire:click="removeJudge({{ $judge->id }})"
                            wire:confirm="Keluarkan {{ $judge->user?->name }} dari panel? Markah yang telah dihantar akan dikekalkan."
                            class="shrink-0 cursor-pointer border border-merdeka-red/50 px-3 py-1.5 font-cond text-[13px] tracking-wide text-merdeka-red-soft uppercase transition hover:bg-merdeka-red hover:text-white">
                        Keluarkan
                    </button>
                </div>
            @empty
                <p class="mb-4 font-cond text-sm text-merdeka-muted">Belum ada hakim dilantik.</p>
            @endforelse

            <div class="mt-4 grid gap-3 sm:grid-cols-[2fr_1fr_auto] sm:items-end">
                <div>
                    <label for="newJudgeUserId" class="{{ $labelClass }}">Tambah dari roster</label>
                    <select id="newJudgeUserId" wire:model="newJudgeUserId"
                            class="w-full border border-merdeka-red/25 bg-merdeka-ink px-3 py-2.5 font-cond text-[15px] text-merdeka-cream outline-none focus:border-merdeka-red">
                        <option value="">— Pilih nama —</option>
                        @foreach ($candidates as $candidate)
                            <option value="{{ $candidate->id }}">{{ $candidate->name }} ({{ $candidate->email }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="newJudgeTitle" class="{{ $labelClass }}">Gelaran</label>
                    <input id="newJudgeTitle" type="text" wire:model="newJudgeTitle" placeholder="Pengasas"
                           class="w-full border border-merdeka-red/25 bg-merdeka-ink px-3 py-2.5 font-cond text-[15px] text-merdeka-cream outline-none focus:border-merdeka-red">
                </div>
                <button type="button" wire:click="addJudge"
                        class="cursor-pointer border border-transparent bg-merdeka-red px-5 py-2.5 font-display text-sm font-semibold tracking-wider text-white uppercase transition hover:bg-merdeka-red-soft">
                    Tambah
                </button>
            </div>
            @error('newJudgeUserId') <p class="mt-2 font-cond text-sm text-merdeka-red-soft">Sila pilih nama dari roster.</p> @enderror
            @error('newJudgeTitle') <p class="mt-2 font-cond text-sm text-merdeka-red-soft">{{ $message }}</p> @enderror
        </div>
    @endif
</div>
