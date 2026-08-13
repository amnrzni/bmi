<!DOCTYPE html>
<html lang="en" class="bg-ink">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-ink text-bone">

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
                    ['route' => 'weigh-in', 'label' => 'Weigh-In', 'show' => auth()->user()->isAdmin()],
                    ['route' => 'roster', 'label' => 'Roster', 'show' => auth()->user()->isAdmin()],
                    ['route' => 'analytics', 'label' => 'Analytics', 'show' => auth()->user()->isAdmin()],
                ];
            @endphp

            @foreach (collect($tabs)->where('show') as $tab)
                <a href="{{ route($tab['route']) }}"
                   @class([
                       'rounded-sm border px-4 py-2 font-cond text-[13px] font-semibold tracking-wide-cond uppercase transition',
                       'border-gold bg-gold text-ink' => request()->routeIs($tab['route']),
                       'border-transparent text-bone-dim hover:text-bone' => ! request()->routeIs($tab['route']),
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

<main class="relative z-10">
    {{ $slot }}
</main>

</body>
</html>
