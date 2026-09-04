{{-- Whichever of the two the current domain resolves to (see ResolveBrand middleware): a
     mirror domain's own uploaded logo image, or — for the primary domain, and any mirror that
     hasn't uploaded one yet — the built-in Hopenalytics <x-logo-mark/>. $class sizes either one
     the same way ("h-16 w-16" etc.), so callers don't need to know which case they got. --}}
@props(['class' => 'h-8 w-8'])

{{-- Falls back to the built-in branding if $currentBrand somehow never got shared (see
     ResolveBrand) — confirmed live: a route-model-binding 404 thrown before that middleware runs
     used to crash the 404 page itself trying to render this component undefined. --}}
@php($brand = $currentBrand ?? new \App\Models\Brand(['name' => config('app.name')]))

@if ($brand->logoUrl())
    <img
        src="{{ $brand->logoUrl() }}"
        alt="{{ $brand->name }}"
        {{ $attributes->merge(['class' => $class.' shrink-0 object-contain']) }}
    >
@else
    <x-logo-mark :label="$brand->name" :class="$class" {{ $attributes }} />
@endif
