@php
    $panelClass = 'border border-merdeka-red/25 bg-merdeka-ink-2';
    $labelClass = 'mb-2 block font-cond text-[13px] tracking-label text-merdeka-red-soft uppercase';
@endphp

<div>
    @if ($flash)
        <div class="mb-5 border border-merdeka-red bg-merdeka-red/10 px-4 py-3 font-cond text-sm tracking-wide">
            {{ $flash }}
        </div>
    @endif

    {{-- ------------------------------------------------------ corner list --}}
    @if (! $openDepartment)
        <div class="mb-5">
            <div class="mb-3 flex items-baseline justify-between font-cond text-[13px] tracking-wide text-merdeka-muted uppercase">
                <span>{{ $judge?->title ?: 'Panel Hakim' }} · {{ auth()->user()->name }}</span>
                <span>{{ $doneCount }} / {{ $departments->count() }} dinilai</span>
            </div>
            <div class="h-2 border border-merdeka-red/25 bg-merdeka-ink">
                <div class="h-full bg-merdeka-red transition-all duration-300"
                     style="width: {{ $departments->count() ? round($doneCount / $departments->count() * 100) : 0 }}%"></div>
            </div>
        </div>

        @if ($departments->isEmpty())
            <div class="{{ $panelClass }} p-8 text-center">
                <h2 class="font-display text-xl font-bold uppercase">Tiada jabatan disenaraikan</h2>
                <p class="mt-2 font-cond text-sm text-merdeka-muted">
                    Admin perlu menambah jabatan yang menyertai pertandingan sebelum penilaian boleh bermula.
                </p>
            </div>
        @elseif ($doneCount === $departments->count())
            <div class="{{ $panelClass }} p-10 text-center">
                <h2 class="font-display text-xl font-bold uppercase">Semua jabatan telah dinilai</h2>
                <p class="mt-3 font-cond text-sm text-merdeka-muted">
                    Terima kasih, {{ Str::before(auth()->user()->name, ' ') }}. Penilaian anda untuk
                    {{ $departments->count() }} jabatan telah lengkap dan dihantar.
                </p>
            </div>
        @else
            <div class="space-y-2.5">
                @foreach ($departments as $index => $department)
                    @php $done = $submitted->has($department->id); @endphp

                    <div wire:key="dept-{{ $department->id }}"
                         @class([
                             'flex items-center gap-4 border px-4 py-3.5',
                             'border-merdeka-good/35 bg-merdeka-good/10' => $done,
                             'border-merdeka-red/25 bg-merdeka-ink-2' => ! $done,
                         ])>
                        <div @class([
                            'flex size-8 shrink-0 items-center justify-center border font-display text-sm font-bold',
                            'border-merdeka-good bg-merdeka-good text-merdeka-ink' => $done,
                            'border-merdeka-red/25 bg-merdeka-ink text-merdeka-red-soft' => ! $done,
                        ])>{{ $done ? '✓' : $index + 1 }}</div>

                        <div class="min-w-0 flex-1">
                            <div class="truncate font-cond text-[15px] font-semibold">{{ $department->name }}</div>
                            <div @class([
                                'font-cond text-[13px] tracking-wide',
                                'text-merdeka-good' => $done,
                                'text-merdeka-muted' => ! $done,
                            ])>{{ $done ? 'Sudah dinilai — markah dikunci' : 'Belum dinilai' }}</div>
                        </div>

                        @if ($done)
                            <div class="shrink-0 font-display text-lg text-merdeka-red-soft">
                                {{ rtrim(rtrim(number_format((float) $submitted[$department->id], 2), '0'), '.') }}
                            </div>
                        @else
                            <button type="button" wire:click="open({{ $department->id }})"
                                    class="shrink-0 cursor-pointer border border-merdeka-red bg-transparent px-4 py-2 font-cond text-[13px] font-semibold tracking-wide-cond text-merdeka-red-soft uppercase transition hover:bg-merdeka-red hover:text-white">
                                Beri Markah
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

    {{-- ------------------------------------------------------- scoresheet --}}
    @else
        <button type="button" wire:click="back"
                class="mb-4 cursor-pointer font-cond text-sm tracking-wide text-merdeka-red-soft">
            ‹ Kembali ke senarai jabatan
        </button>

        <div class="{{ $panelClass }} p-5 sm:p-7">
            <div class="font-cond text-[13px] tracking-label text-merdeka-red-soft uppercase">Beri Markah</div>
            <h2 class="mt-1 font-display text-2xl font-bold uppercase">{{ $openDepartment->name }}</h2>
            <p class="mt-1 mb-6 font-cond text-sm text-merdeka-muted">
                Pilih satu skala 1–5 bagi setiap kriteria. Markah dikira automatik.
            </p>

            @foreach ($criteria as $id => $criterion)
                @php $chosen = $scales[$id] ?? null; @endphp

                <div wire:key="crit-{{ $id }}" class="mb-3 border border-merdeka-red/25 bg-merdeka-ink p-4">
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="font-cond text-[15px] font-semibold">{{ $criterion['name'] }}</span>
                        <span class="shrink-0 font-cond text-[13px] text-merdeka-red-soft">{{ $criterion['weight'] }}%</span>
                    </div>
                    <p class="mt-1 mb-3 font-cond text-[13px] leading-snug text-merdeka-muted">{{ $criterion['hint'] }}</p>

                    <div class="flex items-stretch gap-1.5">
                        @foreach ([1, 2, 3, 4, 5] as $band)
                            <div class="flex min-w-0 flex-1">
                                <input type="radio" class="peer sr-only"
                                       id="{{ $id }}-{{ $band }}"
                                       value="{{ $band }}"
                                       wire:model.live="scales.{{ $id }}">
                                <label for="{{ $id }}-{{ $band }}"
                                       class="flex w-full cursor-pointer flex-col items-center justify-center gap-1 border border-merdeka-red/25 bg-merdeka-ink-2 px-1 py-2.5 text-center transition peer-checked:border-merdeka-red peer-checked:bg-merdeka-red peer-checked:text-white peer-checked:[&_.band-label]:text-white/85 hover:border-merdeka-red">
                                    <span class="font-display text-base leading-none font-bold">{{ $band }}</span>
                                    <span class="band-label font-cond text-[11px] leading-tight text-merdeka-muted">
                                        {{ $scaleLabels[$band] }}
                                    </span>
                                </label>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-2.5 text-right font-cond text-[13px] text-merdeka-muted">
                        Markah:
                        <b class="font-display text-merdeka-red-soft">
                            {{ $chosen ? \App\Support\MerdekaRubric::earned($id, (int) $chosen) : 0 }}
                        </b>
                        / {{ $criterion['weight'] }}
                    </div>

                    @error("scales.{$id}")
                        <p class="mt-2 font-cond text-sm text-merdeka-red-soft">{{ $message }}</p>
                    @enderror

                    {{--
                        wire:ignore because the panel is static text and its open
                        state is DOM state — without it, picking a band would snap
                        an open rubric shut on every render.
                    --}}
                    <details wire:ignore class="group mt-3">
                        <summary class="cursor-pointer list-none font-cond text-[13px] font-semibold text-merdeka-red-soft">
                            <span class="group-open:hidden">▾ Panduan skala 1–5 untuk kriteria ini</span>
                            <span class="hidden group-open:inline">▴ Sembunyikan panduan</span>
                        </summary>
                        <div class="mt-1">
                            @foreach ($bands[$id] as $band => $description)
                                <div class="border-t border-merdeka-red/25 py-2.5 font-cond text-[13px] leading-relaxed">
                                    <div class="font-semibold text-merdeka-red-soft">{{ $band }} — {{ $scaleLabels[$band] }}</div>
                                    <div class="text-merdeka-cream/90">{{ $description }}</div>
                                </div>
                            @endforeach
                        </div>
                    </details>
                </div>
            @endforeach

            <div class="my-4 flex items-center justify-between border border-merdeka-red bg-merdeka-red/15 px-5 py-4">
                <span class="font-cond text-sm tracking-wide uppercase">Jumlah Keseluruhan</span>
                <span class="font-display text-3xl font-bold text-merdeka-red-soft">
                    {{ rtrim(rtrim(number_format($runningTotal, 2), '0'), '.') }} <span class="text-lg text-merdeka-muted">/ 100</span>
                </span>
            </div>

            <div class="mb-6">
                <label for="ulasan" class="{{ $labelClass }}">Ulasan Panel Hakim (pilihan)</label>
                <textarea id="ulasan" rows="3" wire:model="ulasan"
                          placeholder="Catatan atau ulasan tambahan mengenai sudut ini..."
                          class="w-full border border-merdeka-red/25 bg-merdeka-ink px-3 py-2.5 font-cond text-[15px] text-merdeka-cream outline-none focus:border-merdeka-red"></textarea>
                @error('ulasan') <p class="mt-1 font-cond text-sm text-merdeka-red-soft">{{ $message }}</p> @enderror
            </div>

            {{-- Signature and submit share one Alpine scope: `signed` gates the button. --}}
            <div x-data="merdekaSignaturePad()">
                <span class="{{ $labelClass }}">Tandatangan Panel Hakim</span>

                <div wire:ignore class="relative border-2 border-dashed border-merdeka-red bg-merdeka-cream">
                    <canvas x-ref="pad"
                            class="block h-40 w-full touch-none"
                            @pointerdown.prevent="start($event)"
                            @pointermove.prevent="move($event)"
                            @pointerup="end()"
                            @pointercancel="end()"></canvas>
                    <div x-show="! signed"
                         class="pointer-events-none absolute inset-0 flex items-center justify-center font-cond text-sm text-merdeka-ink-3/60 italic">
                        Lukis tandatangan di sini
                    </div>
                </div>

                <div class="mt-2 flex items-center justify-between">
                    <button type="button" x-on:click="clear()"
                            class="cursor-pointer font-cond text-[13px] text-merdeka-muted underline">
                        Padam &amp; tulis semula
                    </button>
                    <span class="font-cond text-[13px]">
                        <span x-show="! signed" class="text-merdeka-muted">Belum bertandatangan</span>
                        <span x-show="signed" x-cloak class="text-merdeka-good">Tandatangan direkodkan</span>
                    </span>
                </div>

                @error('signature')
                    <p class="mt-2 font-cond text-sm text-merdeka-red-soft">{{ $message }}</p>
                @enderror

                <button type="button" wire:click="submit" wire:loading.attr="disabled"
                        :disabled="! signed || ! {{ $allBandsChosen ? 'true' : 'false' }}"
                        class="mt-5 w-full cursor-pointer border border-transparent bg-merdeka-red px-6 py-3.5 font-display text-[15px] font-semibold tracking-wider text-white uppercase transition hover:bg-merdeka-red-soft disabled:cursor-not-allowed disabled:opacity-40">
                    <span wire:loading.remove wire:target="submit">Hantar Markah Jabatan Ini</span>
                    <span wire:loading wire:target="submit">Menghantar…</span>
                </button>

                <p class="mt-2.5 text-center font-cond text-[13px] text-merdeka-muted">
                    Markah tidak boleh diubah selepas dihantar.
                </p>
            </div>

            <div class="mt-5 border-t border-merdeka-red/25 pt-3 font-cond text-[13px] leading-relaxed text-merdeka-muted">
                <b class="text-merdeka-red-soft">Panduan Skala:</b>
                @foreach ($scaleLabels as $band => $label)
                    {{ $band }} = {{ $label }}@if (! $loop->last) · @endif
                @endforeach
            </div>
        </div>
    @endif
</div>
