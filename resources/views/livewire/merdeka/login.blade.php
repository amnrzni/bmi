<div class="mx-auto max-w-md pt-[6vh]">
    <div class="border border-merdeka-red/25 bg-merdeka-ink-2 p-6 sm:p-8">
        <h2 class="text-center font-display text-2xl font-bold uppercase">Log Masuk Panel Hakim</h2>
        <p class="mt-2 mb-6 text-center font-cond text-sm text-merdeka-muted">
            Masukkan e-mel anda untuk mula memberi markah.
        </p>

        <form wire:submit="submit">
            <label for="email" class="mb-2 block font-cond text-[13px] tracking-label text-merdeka-red-soft uppercase">
                E-mel
            </label>
            <input id="email" type="email" wire:model="email" placeholder="nama@qcxis.com"
                   autocapitalize="off" autocorrect="off" spellcheck="false" autofocus
                   class="w-full border border-merdeka-red/25 bg-merdeka-ink px-3 py-3 font-cond text-[15px] text-merdeka-cream outline-none focus:border-merdeka-red">

            @error('email')
                <p class="mt-2 font-cond text-sm text-merdeka-red-soft">{{ $message }}</p>
            @enderror

            <button type="submit" wire:loading.attr="disabled"
                    class="mt-5 w-full cursor-pointer border border-transparent bg-merdeka-red px-6 py-3.5 font-display text-[15px] font-semibold tracking-wider text-white uppercase transition hover:bg-merdeka-red-soft disabled:opacity-50">
                <span wire:loading.remove>Log Masuk</span>
                <span wire:loading>Sebentar…</span>
            </button>
        </form>

        <p class="mt-5 text-center font-cond text-[13px] leading-relaxed text-merdeka-muted">
            Hanya e-mel yang telah didaftarkan sebagai panel hakim boleh log masuk.
            Hubungi admin jika e-mel anda tidak diterima.
        </p>
    </div>
</div>
