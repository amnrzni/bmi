@props(['padded' => true])

{{-- The raised dark panel used for every card in the mock. --}}
<div {{ $attributes->class(['border border-ink-3 bg-ink-2', 'p-6 sm:p-8' => $padded]) }}>
    {{ $slot }}
</div>
