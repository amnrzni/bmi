<!DOCTYPE html>
<html lang="en" class="bg-ink">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-ink text-bone">

{{--
    One indicator for every Livewire request on every screen. `.delay` holds it
    back ~200ms so quick actions don't flash a bar at you, while slow ones stop
    feeling like a dead click.
--}}
<div wire:loading.delay class="fixed inset-x-0 top-0 z-50 h-0.5 animate-pulse bg-gold" role="status" aria-label="Working"></div>

<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50 focus:bg-gold focus:px-4 focus:py-2 focus:font-cond focus:text-sm focus:font-semibold focus:tracking-wide focus:text-ink focus:uppercase">
    Skip to content
</a>

@auth
    <nav class="relative z-20 flex flex-wrap items-center justify-between gap-3 border-b border-ink-3 px-5 py-4">
        <a href="{{ route('home') }}" class="font-display text-[15px] font-bold tracking-label uppercase">
            BMI <b class="text-gold">Challenge</b>
            <span class="text-bone-dim">· Kapla Home Council</span>
        </a>

        <div class="flex flex-wrap items-center gap-1.5">
            @php
                $tabs = [
                    ['route' => 'home', 'label' => 'Home', 'show' => true],
                    ['route' => 'me', 'label' => 'My Progress', 'show' => auth()->user()->is_participant],
                    ['route' => 'events', 'label' => 'Events', 'show' => true],
                    ['route' => 'tournaments.index', 'label' => 'Tournaments', 'show' => true, 'active' => 'tournaments.*'],
                    ['route' => 'weigh-in', 'label' => 'Weigh-In', 'show' => auth()->user()->isAdmin()],
                    ['route' => 'roster', 'label' => 'Roster', 'show' => auth()->user()->isAdmin()],
                    ['route' => 'analytics', 'label' => 'Analytics', 'show' => auth()->user()->isAdmin()],
                ];
            @endphp

            @foreach (collect($tabs)->where('show') as $tab)
                @php $active = request()->routeIs($tab['active'] ?? $tab['route']); @endphp
                <a href="{{ route($tab['route']) }}"
                   @class([
                       'rounded-sm border px-4 py-2 font-cond text-[13px] font-semibold tracking-wide-cond uppercase transition',
                       'border-gold bg-gold text-ink' => $active,
                       'border-transparent text-bone-dim hover:text-bone' => ! $active,
                   ])>{{ $tab['label'] }}</a>
            @endforeach

            <form method="POST" action="{{ route('logout') }}" class="ml-2">
                @csrf
                <button type="submit"
                        class="cursor-pointer font-cond text-[13px] tracking-wide-cond text-bone-dim uppercase transition hover:text-blood-bright">
                    Sign out
                </button>
            </form>
        </div>
    </nav>
@endauth

<main id="main" class="relative z-10">
    {{ $slot }}
</main>

</body>
</html>
