<x-layouts.app title="Sign in — BMI Challenge">
    <div class="mx-auto max-w-md px-5 py-16 sm:py-24">
        <x-ui.eyebrow class="text-center">Kapla Home Council</x-ui.eyebrow>

        <h1 class="mt-4 text-center font-display text-5xl leading-[0.9] font-bold uppercase sm:text-6xl">
            Report <span class="text-blood">In</span>
        </h1>
        <p class="mt-3 mb-9 text-center font-cond text-[13px] tracking-[0.22em] text-bone-dim uppercase">
            Aug — Nov 2026 · Weigh-in every Monday
        </p>

        {{-- Kept: SSO rejections ("not on the roster", "sign-in cancelled") surface here. --}}
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
            @elseif ($ssoSetupHint)
                {{-- Setup-time only (APP_DEBUG), for whoever is wiring SSO up. --}}
                <div class="border border-blood bg-blood/10 px-4 py-3 font-cond text-sm tracking-wide text-bone">
                    <b class="text-blood-bright">QCXIS SSO isn't configured.</b>
                    Set <code class="text-gold">QCXIS_CLIENT_ID</code> and
                    <code class="text-gold">QCXIS_REDIRECT_URI</code> in <code class="text-gold">.env</code>
                    (plus <code class="text-gold">QCXIS_CLIENT_SECRET</code> for a Confidential app),
                    then run <code class="text-gold">php artisan config:clear</code>.
                </div>
            @else
                <div class="border border-ink-3 bg-ink px-4 py-3 text-center font-cond text-sm tracking-wide text-bone-dim">
                    Sign-in isn't available yet.
                </div>
            @endif
        </x-ui.panel>

        {{--
            The email/password fallback form was removed from the page by request.
            The POST /login route still exists, so an admin locked out by an SSO
            outage can still authenticate against it directly.
        --}}
    </div>
</x-layouts.app>
