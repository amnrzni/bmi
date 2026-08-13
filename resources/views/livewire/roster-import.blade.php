@use('App\Support\ImportRow')

<div class="mx-auto max-w-4xl px-5 py-12 pb-20">
    <x-ui.eyebrow class="text-center">Admin · Master roster</x-ui.eyebrow>
    <h1 class="mt-3 mb-8 text-center font-display text-4xl leading-[0.9] font-bold uppercase sm:text-5xl">
        Import <span class="text-blood">Roster</span>
    </h1>

    @if ($done)
        <div class="mb-6 border border-gold bg-gold/10 px-4 py-3 font-cond text-sm tracking-wide text-bone">
            {{ $done }}
            <a href="{{ route('roster') }}" class="ml-2 text-gold hover:text-gold-bright">Back to the roster →</a>
        </div>
    @endif

    <x-ui.panel class="mb-6">
        <label for="file" class="mb-2 block font-cond text-[13px] tracking-label text-bone-dim uppercase">
            CSV file
        </label>

        <input id="file" type="file" accept=".csv,text/csv" wire:model="file"
               class="w-full cursor-pointer border border-dashed border-ink-3 bg-ink px-4 py-6 font-cond text-sm text-bone-dim
                      file:mr-4 file:cursor-pointer file:border-0 file:bg-gold file:px-4 file:py-2 file:font-display
                      file:text-sm file:font-semibold file:uppercase file:text-ink hover:border-gold">

        <div wire:loading wire:target="file" class="mt-3 font-cond text-sm tracking-wide text-gold uppercase">
            Reading…
        </div>

        @error('file') <p class="mt-2 font-cond text-sm text-blood-bright">{{ $message }}</p> @enderror

        <div class="mt-5 font-cond text-xs tracking-wide text-bone-dim uppercase">
            <p>In Google Sheets: <b class="text-bone">File → Download → Comma-separated values</b></p>
            <p class="mt-1">
                Needs a header row with at least <b class="text-gold">name</b> and
                <b class="text-gold">email</b>. Optional: department, team, height, joined.
                Malay headings work too (nama, emel, jabatan, pasukan, tinggi).
            </p>
        </div>
    </x-ui.panel>

    @if ($error)
        <div class="mb-6 border border-blood bg-blood/10 px-4 py-3 font-cond text-sm tracking-wide text-bone">
            {{ $error }}
        </div>
    @endif

    @if ($rows->isNotEmpty())
        {{-- Preview first. Nothing is written until this is confirmed. --}}
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <h2 class="brushstroke flex items-center gap-3 font-display text-xl font-bold tracking-wide uppercase">
                Preview
            </h2>
            <div class="font-cond text-xs tracking-label uppercase">
                <span class="text-gold">{{ $createCount }} new</span> ·
                <span class="text-bone">{{ $updateCount }} update</span>
                @if ($errorCount > 0)
                    · <span class="text-blood-bright">{{ $errorCount }} error</span>
                @endif
            </div>
        </div>

        <div class="overflow-x-auto border border-ink-3">
            <table class="w-full min-w-[680px] border-collapse">
                <thead>
                    <tr>
                        @foreach (['Line', 'Name', 'Email', 'Department', 'Team', 'Height', 'Action'] as $heading)
                            <th class="bg-ink-2 px-2.5 py-3 text-left font-cond text-xs font-semibold tracking-wide text-bone-dim uppercase">
                                {{ $heading }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-t border-ink-3 {{ $row->isError() ? 'bg-blood/5' : '' }}">
                            <td class="px-2.5 py-2.5 font-display text-sm text-bone-dim">{{ $row->line }}</td>
                            <td class="px-2.5 py-2.5 font-cond text-sm text-bone">{{ $row->value('name') ?? '—' }}</td>
                            <td class="px-2.5 py-2.5 font-cond text-sm lowercase text-bone-dim">{{ $row->value('email') ?? '—' }}</td>
                            <td class="px-2.5 py-2.5 font-cond text-sm text-bone-dim">{{ $row->value('department') ?? '—' }}</td>
                            <td class="px-2.5 py-2.5 font-cond text-sm text-bone-dim">{{ $row->value('team') ?? '—' }}</td>
                            <td class="px-2.5 py-2.5 font-cond text-sm text-bone-dim">{{ $row->value('height_cm') ?? '—' }}</td>
                            <td class="px-2.5 py-2.5 font-cond text-xs tracking-wide-cond uppercase">
                                @if ($row->isError())
                                    <span class="text-blood-bright">{{ $row->errorText() }}</span>
                                @elseif ($row->action === ImportRow::UPDATE)
                                    <span class="text-bone">Update</span>
                                @else
                                    <span class="text-gold">New</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-3">
            @if ($errorCount === 0)
                <x-ui.btn wire:click="commit" wire:loading.attr="disabled" wire:target="commit"
                          variant="gold" class="px-6 py-3 text-sm">
                    <span wire:loading.remove wire:target="commit">
                        Import {{ $createCount + $updateCount }} {{ Str::plural('row', $createCount + $updateCount) }}
                    </span>
                    <span wire:loading wire:target="commit">Importing…</span>
                </x-ui.btn>
            @else
                <p class="font-cond text-sm tracking-wide text-blood-bright uppercase">
                    Fix the {{ $errorCount }} {{ Str::plural('error', $errorCount) }} in the sheet and upload again —
                    nothing is imported while any row fails.
                </p>
            @endif
        </div>

        <p class="mt-4 font-cond text-xs tracking-wide text-bone-dim uppercase">
            Existing people are matched on email and updated. Anyone missing from the file is left
            untouched — removing someone is a separate action on the roster.
        </p>
    @endif
</div>
