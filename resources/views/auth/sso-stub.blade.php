<x-layouts.app title="Development sign-in">
    <div class="mx-auto max-w-lg px-5 py-16">
        <div class="mb-6 border border-blood bg-blood/10 px-4 py-3 font-cond text-sm tracking-wide">
            <b class="text-blood-bright">Development only.</b>
            This stands in for QCXIS SSO until the real driver is wired up. It refuses to run
            outside local and testing environments.
        </div>

        <h1 class="mb-6 font-display text-3xl font-bold uppercase">Sign in as</h1>

        <x-ui.panel :padded="false">
            <div class="divide-y divide-ink-3">
                @foreach ($users as $user)
                    <a href="{{ route('sso.callback', ['email' => $user->email]) }}"
                       class="flex items-center justify-between gap-4 px-5 py-3 transition hover:bg-gold/5">
                        <span>
                            <span class="font-cond text-base text-bone">{{ $user->name }}</span>
                            <span class="block font-cond text-xs tracking-wide-cond text-bone-dim uppercase">
                                {{ $user->email }}
                            </span>
                        </span>
                        <span class="flex items-center gap-2">
                            @if ($user->team)
                                <span class="rounded-sm bg-bone/10 px-2.5 py-0.5 font-cond text-xs font-semibold tracking-wide-cond text-bone">
                                    {{ $user->team->code }}
                                </span>
                            @endif
                            @if ($user->isAdmin())
                                <span class="rounded-sm bg-gold/20 px-2.5 py-0.5 font-cond text-xs font-semibold tracking-wide-cond text-gold-bright">
                                    Admin
                                </span>
                            @endif
                        </span>
                    </a>
                @endforeach
            </div>
        </x-ui.panel>
    </div>
</x-layouts.app>
