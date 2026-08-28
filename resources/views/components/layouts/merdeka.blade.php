{{--
    The Merdeka contest runs on its own palette and its own chrome — no
    challenge nav, no gold. Its screens are reached by direct link, not from the
    app's tab bar, so this layout is the only thing that pulls them together.
--}}
<!DOCTYPE html>
<html lang="ms" class="bg-merdeka-ink">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Sudut Kemerdekaan' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-merdeka-ink text-merdeka-cream">

<div wire:loading.delay class="fixed inset-x-0 top-0 z-50 h-0.5 animate-pulse bg-merdeka-red" role="status" aria-label="Sedang memproses"></div>

<div class="pointer-events-none fixed inset-0 z-0"
     style="background:
        radial-gradient(1200px 600px at 10% -10%, rgb(214 39 63 / 0.14), transparent 60%),
        radial-gradient(900px 500px at 100% 0%, rgb(0 0 0 / 0.55), transparent 55%);"></div>

<div class="relative z-10 mx-auto max-w-3xl px-4 pb-16 sm:px-5">

    <header class="flex items-center gap-3 py-5">
        {{-- Flag stripe: blood / cream / deep maroon, as the prototype's brand bar. --}}
        <div class="h-10 w-1.5 shrink-0 rounded-sm"
             style="background: linear-gradient(180deg, var(--color-merdeka-blood) 0 33%, var(--color-merdeka-cream) 33% 66%, var(--color-merdeka-ink-4) 66% 100%);"></div>
        <div class="min-w-0">
            <h1 class="font-display text-[15px] font-bold tracking-label uppercase">
                {{ $heading ?? 'Panel Hakim' }} <span class="text-merdeka-muted">· Sudut Kemerdekaan</span>
            </h1>
            <p class="font-cond text-[13px] tracking-wide text-merdeka-muted">
                Pertandingan Dekorasi Sudut Kemerdekaan 2026
            </p>
        </div>
    </header>

    <main id="main">
        {{ $slot }}
    </main>

    <footer class="mt-10 flex justify-end">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit"
                    class="cursor-pointer font-cond text-[13px] tracking-wide text-merdeka-muted uppercase transition hover:text-merdeka-red-soft">
                Log Keluar
            </button>
        </form>
    </footer>

</div>

</body>
</html>
