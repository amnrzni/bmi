<x-layouts.app title="Before you join">
    <div class="mx-auto max-w-2xl px-5 py-14">
        <x-ui.eyebrow>Opt in</x-ui.eyebrow>
        <h1 class="mt-3 mb-8 font-display text-4xl leading-[0.95] font-bold uppercase sm:text-5xl">
            Before you <span class="text-blood">join</span>
        </h1>

        <x-ui.panel>
            <div class="space-y-5 font-sans text-[15px] leading-relaxed text-bone">
                <p>
                    Taking part is entirely voluntary, and it's fine to say no. Before you decide,
                    here's exactly what happens to your data.
                </p>

                <ul class="space-y-3 border-l-2 border-gold pl-5">
                    <li>
                        <b class="text-gold-bright">Your weight is recorded for you.</b>
                        An admin enters it each week after the weigh-in. You don't log it yourself,
                        and every entry records who took it and when.
                    </li>
                    <li>
                        <b class="text-gold-bright">Admins can see your exact weight and BMI, by name.</b>
                        The organising group is deliberately small, but be clear that they see the
                        real numbers — not just your team's total.
                    </li>
                    <li>
                        <b class="text-gold-bright">Other staff cannot.</b>
                        Colleagues only ever see their own figures and the two team averages.
                    </li>
                    <li>
                        <b class="text-gold-bright">You can withdraw at any time.</b>
                        Your name is removed and your figures stay only as part of the anonymous
                        team average.
                    </li>
                </ul>

                <p class="text-bone-dim">
                    Your height is stored once so BMI can be worked out. We don't collect anything else.
                </p>
            </div>

            <form method="POST" action="{{ route('consent.store') }}" class="mt-8">
                @csrf

                <label class="flex cursor-pointer items-start gap-3 font-sans text-[15px] text-bone">
                    <input type="checkbox" name="accept" value="1" class="mt-1 accent-gold">
                    <span>I've read the above and I want to take part.</span>
                </label>

                @error('accept')
                    <p class="mt-2 font-cond text-sm tracking-wide text-blood-bright">{{ $message }}</p>
                @enderror

                <div class="mt-7 flex flex-wrap gap-3">
                    <x-ui.btn type="submit" variant="gold">Count me in</x-ui.btn>
                    <x-ui.btn type="submit" form="decline" variant="ghost">No thanks</x-ui.btn>
                </div>
            </form>

            <form method="POST" action="{{ route('logout') }}" id="decline">@csrf</form>
        </x-ui.panel>
    </div>
</x-layouts.app>
