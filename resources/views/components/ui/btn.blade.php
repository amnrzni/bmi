@props([
    'variant' => 'blood', // blood | gold | ghost
    'as' => 'button',
])

@php
    $classes = [
        'inline-block cursor-pointer rounded-sm border font-display font-semibold tracking-wider uppercase transition text-center',
        // Callers add wire:loading.attr="disabled"; this is what that looks like.
        'disabled:cursor-wait disabled:opacity-60 disabled:hover:translate-y-0',
        'px-7 py-3.5 text-[15px]',
        'border-transparent bg-blood text-bone hover:bg-blood-bright hover:-translate-y-px' => $variant === 'blood',
        'border-transparent bg-gold text-ink hover:bg-gold-bright hover:-translate-y-px' => $variant === 'gold',
        'border-gold bg-transparent text-gold hover:bg-gold hover:text-ink' => $variant === 'ghost',
    ];
@endphp

@if ($as === 'a')
    <a {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
