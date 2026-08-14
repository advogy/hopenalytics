@props(['class' => 'h-8 w-8', 'label' => null])

@php
    // Unique per render so multiple instances on one page (header + footer, etc.) don't
    // collide on the gradient's id — SVG ids aren't scoped to their own <svg> subtree.
    $uid = 'hn-sun-'.uniqid();
@endphp

<svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="{{ $label ?? config('app.name') }}" {{ $attributes->merge(['class' => $class]) }}>
    <defs>
        <radialGradient id="{{ $uid }}" cx="38%" cy="30%" r="78%">
            <stop offset="0%" stop-color="#f7cd9a" />
            <stop offset="100%" stop-color="#df753a" />
        </radialGradient>
    </defs>
    <line x1="6" y1="47" x2="58" y2="47" stroke="#cdb99a" stroke-width="1.4" stroke-linecap="round" />
    <circle cx="32" cy="31" r="14" fill="url(#{{ $uid }})" />
    <polyline points="13,39 22,30 30,34 39,20 51,11" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" />
    <circle cx="51" cy="11" r="3.4" fill="currentColor" />
</svg>
