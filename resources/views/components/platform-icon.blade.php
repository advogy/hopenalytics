@props(['platform'])

@php
    $platform = $platform instanceof \App\Enums\SocialPlatform ? $platform->value : $platform;
    $icon = \App\Support\PlatformIcons::ICONS[$platform] ?? null;
@endphp

@if ($icon)
    <span
        {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center justify-center rounded-full text-white shadow-sm']) }}
        style="background: {{ $icon['bg'] }}"
    >
        {{-- Glyph comes from the page's sprite (partials/platform-icon-sprite) — see App\Support\PlatformIcons. --}}
        <svg viewBox="0 0 24 24" fill="currentColor" class="h-[58%] w-[58%]"><use href="#{{ \App\Support\PlatformIcons::symbolId($platform) }}" /></svg>
    </span>
@endif
