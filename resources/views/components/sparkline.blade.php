@props(['values' => []])

@php
    $values = array_values(array_map(fn ($v) => (float) $v, $values));
    $count = count($values);
@endphp

@if ($count >= 2)
    @php
        $width = 88;
        $height = 28;
        $padding = 4;
        $min = min($values);
        $max = max($values);
        $range = $max - $min;

        $points = collect($values)->map(function ($value, $i) use ($count, $width, $height, $padding, $min, $range) {
            $x = $padding + ($i / ($count - 1)) * ($width - $padding * 2);
            $y = $range > 0
                ? $height - $padding - (($value - $min) / $range) * ($height - $padding * 2)
                : $height / 2;

            return [round($x, 2), round($y, 2)];
        });

        $polylinePoints = $points->map(fn ($p) => "{$p[0]},{$p[1]}")->implode(' ');
        [$lastX, $lastY] = $points->last();
        $trendUp = end($values) >= reset($values);
    @endphp

    <svg
        viewBox="0 0 {{ $width }} {{ $height }}"
        {{ $attributes->merge(['class' => 'overflow-visible']) }}
        aria-hidden="true"
    >
        <polyline
            points="{{ $polylinePoints }}"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            class="text-slate-300 dark:text-slate-600"
        />
        <circle
            cx="{{ $lastX }}"
            cy="{{ $lastY }}"
            r="4"
            class="{{ $trendUp ? 'fill-emerald-500' : 'fill-red-500' }}"
            stroke="currentColor"
            stroke-width="2"
            style="stroke: var(--sparkline-ring, white)"
        />
    </svg>
@endif
