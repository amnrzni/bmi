<x-layouts.app title="Sign in — BMI Challenge">
    <div class="mx-auto max-w-md px-5 py-16 sm:py-24">
        <x-ui.eyebrow class="text-center">Kapla Home Council</x-ui.eyebrow>

        <h1 class="mt-4 text-center font-display text-5xl leading-[0.9] font-bold uppercase sm:text-6xl">
            Report <span class="text-blood">In</span>
        </h1>
        <p class="mt-3 mb-9 text-center font-cond text-[13px] tracking-[0.22em] text-bone-dim uppercase">
            Aug — Nov 2026 · Weigh-in every Monday
        </p>

        @if ($errors->any())
            <div class="mb-6 border border-blood bg-blood/10 px-4 py-3 font-cond text-sm tracking-wide text-bone">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <x-ui.panel>
            @if ($ssoAvailable)
                <a href="{{ route('sso.redirect') }}"
                   class="block w-full cursor-pointer rounded-sm bg-gold px-7 py-4 text-center font-display text-[15px] font-semibold tracking-wider text-ink uppercase transition hover:bg-gold-bright">
                    {{ $ssoLabel }}
                </a>
                <p class="mt-3 text-center font-cond text-xs tracking-wide-cond text-bone-dim uppercase">
                    Staff sign in with their QCXIS account
                </p>

                <div class="my-7 flex items-center gap-4">
                    <span class="h-px flex-1 bg-ink-3"></span>
                    <span class="font-cond text-xs tracking-label text-bone-dim uppercase">or admin sign-in</span>
                    <span class="h-px flex-1 bg-ink-3"></span>
                </div>
            @elseif ($ssoSetupHint)
                {{-- Setup-time only (APP_DEBUG), for whoever is wiring SSO up. --}}
                <div class="mb-7 border border-blood bg-blood/10 px-4 py-3 font-cond text-sm tracking-wide text-bone">
                    <b class="text-blood-bright">QCXIS SSO isn't configured.</b>
                    Set <code class="text-gold">QCXIS_CLIENT_ID</code> and
                    <code class="text-gold">QCXIS_REDIRECT_URI</code> in <code class="text-gold">.env</code>
                    (plus <code class="text-gold">QCXIS_CLIENT_SECRET</code> for a Confidential app),
                    then run <code class="text-gold">php artisan config:clear</code>.
                </div>
            @else
                {{-- Say why rather than just hiding the button: staff sent here
                     would otherwise have no idea what went wrong. --}}
                <div class="mb-7 border border-ink-3 bg-ink px-4 py-3 text-center font-cond text-sm tracking-wide text-bone-dim">
                    Staff sign-in isn't available yet. Ask an admin to record your weigh-in in the meantime.
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="mb-2 block font-cond text-[13px] tracking-label text-bone-dim uppercase">
                        Email
                    </label>
                    <input id="email" name="email" type="email" required autofocus
                           value="{{ old('email') }}"
                           class="w-full border border-ink-3 bg-ink px-3 py-2.5 font-display text-lg text-bone outline-none focus:border-gold">
                </div>

                <div>
                    <label for="password" class="mb-2 block font-cond text-[13px] tracking-label text-bone-dim uppercase">
                        Password
                    </label>
                    <input id="password" name="password" type="password" required
                           class="w-full border border-ink-3 bg-ink px-3 py-2.5 font-display text-lg text-bone outline-none focus:border-gold">
                </div>

                <label class="flex cursor-pointer items-center gap-2 font-cond text-[13px] tracking-wide-cond text-bone-dim uppercase">
                    <input type="checkbox" name="remember" value="1" class="accent-gold">
                    Stay signed in
                </label>

                <x-ui.btn type="submit" class="w-full">Sign in</x-ui.btn>
            </form>

            <p class="mt-5 text-center font-cond text-xs tracking-wide-cond text-bone-dim uppercase">
                Admin accounts only. Staff use single sign-on.
            </p>
        </x-ui.panel>
    </div>
</x-layouts.app>
