{{--
    Tournament pages are open to anyone with the link, and the app nav is for
    signed-in staff only. Guests get the brand and a way in, nothing else.
--}}
@guest
    <div class="relative z-20 flex flex-wrap items-center justify-between gap-3 border-b border-ink-3 px-5 py-4">
        <a href="{{ route('tournaments.index') }}" class="font-display text-[15px] font-bold tracking-label uppercase">
            BMI <b class="text-gold">Challenge</b>
            <span class="text-bone-dim">· Kapla Home Council</span>
        </a>
        <a href="{{ route('login') }}"
           class="font-cond text-[13px] tracking-wide-cond text-bone-dim uppercase transition hover:text-gold">
            Staff sign in
        </a>
    </div>
@endguest
