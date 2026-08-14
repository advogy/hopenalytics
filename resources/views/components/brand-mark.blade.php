{{-- Whichever of the two the current domain resolves to (see ResolveBrand middleware): a
     mirror domain's own uploaded logo image, or — for the primary domain, and any mirror that
     hasn't uploaded one yet — the built-in Hopenalytics <x-logo-mark/>. $class sizes either one
     the same way ("h-16 w-16" etc.), so callers don't need to know which case they got. --}}
@props(['class' => 'h-8 w-8'])

@if ($currentBrand->logoUrl())
    <img
        src="{{ $currentBrand->logoUrl() }}"
        alt="{{ $currentBrand->name }}"
        {{ $attributes->merge(['class' => $class.' shrink-0 object-contain']) }}
    >
@else
    <x-logo-mark :label="$currentBrand->name" :class="$class" {{ $attributes }} />
@endif
