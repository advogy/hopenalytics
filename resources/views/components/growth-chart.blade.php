@props(['values' => [], 'labels' => []])

@php
    $values = collect($values)->values()->all();
    $labels = collect($labels)->values()->all();
    $count = count($values);
@endphp

@if ($count < 2)
    <div class="flex h-56 items-center justify-center rounded-xl border border-dashed border-slate-200 px-6 text-center text-sm text-slate-400 dark:border-slate-700 dark:text-slate-500">
        Grafik pertumbuhan akan muncul setelah data tercatat minimal 2 minggu.
    </div>
@else
    @php
        $width = 640;
        $height = 240;
        $padLeft = 52;
        $padRight = 16;
        $padTop = 28;
        $padBottom = 28;
        $plotWidth = $width - $padLeft - $padRight;
        $plotHeight = $height - $padTop - $padBottom;

        $maxVal = max($values);
        $range = max($maxVal, 1);
        $targetTicks = 4;
        $roughStep = $range / $targetTicks;
        $magnitude = 10 ** floor(log10($roughStep));
        $residual = $roughStep / $magnitude;
        $niceResidual = $residual > 5 ? 10 : ($residual > 2 ? 5 : ($residual > 1 ? 2 : 1));
        $step = $niceResidual * $magnitude;
        $niceMax = max(ceil($maxVal / $step) * $step, $step);

        $ticks = [];
        for ($t = 0; $t <= $niceMax; $t += $step) {
            $ticks[] = $t;
        }

        $points = collect($values)->map(function ($value, $i) use ($count, $plotWidth, $plotHeight, $padLeft, $padTop, $niceMax) {
            $x = $padLeft + ($count > 1 ? ($i / ($count - 1)) * $plotWidth : 0);
            $y = $padTop + $plotHeight - ($niceMax > 0 ? ($value / $niceMax) * $plotHeight : 0);

            return [round($x, 2), round($y, 2)];
        });

        $polylinePoints = $points->map(fn ($p) => "{$p[0]},{$p[1]}")->implode(' ');
        $baseline = $padTop + $plotHeight;
        $areaPoints = $polylinePoints." {$points->last()[0]},{$baseline} {$points->first()[0]},{$baseline}";

        $labelIndexes = $count <= 5 ? range(0, $count - 1) : [0, intdiv($count - 1, 2), $count - 1];
    @endphp

    <div class="overflow-x-auto">
        <svg viewBox="0 0 {{ $width }} {{ $height }}" class="w-full" style="min-width: 320px" role="img" aria-label="Grafik pertumbuhan total jangkauan">
            @foreach ($ticks as $tick)
                @php $y = $padTop + $plotHeight - ($niceMax > 0 ? ($tick / $niceMax) * $plotHeight : 0); @endphp
                <line x1="{{ $padLeft }}" y1="{{ $y }}" x2="{{ $width - $padRight }}" y2="{{ $y }}" stroke-width="1" class="stroke-slate-100 dark:stroke-slate-800" />
                <text x="{{ $padLeft - 8 }}" y="{{ $y + 3 }}" text-anchor="end" class="fill-slate-400 text-[10px] dark:fill-slate-500">{{ number_format($tick) }}</text>
            @endforeach

            <polygon points="{{ $areaPoints }}" class="fill-blue-500 dark:fill-blue-400" opacity="0.1" />

            <polyline
                points="{{ $polylinePoints }}"
                fill="none"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                class="stroke-blue-500 dark:stroke-blue-400"
            />

            @foreach ($points as $i => $point)
                <circle
                    cx="{{ $point[0] }}"
                    cy="{{ $point[1] }}"
                    r="{{ $i === $count - 1 ? 4 : 3 }}"
                    class="fill-blue-500 dark:fill-blue-400"
                    stroke="var(--sparkline-ring, white)"
                    stroke-width="2"
                />
                <text
                    x="{{ $point[0] }}"
                    y="{{ $point[1] - 10 }}"
                    text-anchor="{{ $i === 0 ? 'start' : ($i === $count - 1 ? 'end' : 'middle') }}"
                    class="{{ $i === $count - 1 ? 'fill-slate-700 text-xs font-semibold dark:fill-slate-200' : 'fill-slate-500 text-[10px] font-medium dark:fill-slate-400' }}"
                >{{ number_format($values[$i]) }}</text>
            @endforeach

            @foreach ($labelIndexes as $i)
                <text
                    x="{{ $points[$i][0] }}"
                    y="{{ $height - 8 }}"
                    text-anchor="{{ $i === 0 ? 'start' : ($i === $count - 1 ? 'end' : 'middle') }}"
                    class="fill-slate-400 text-[10px] dark:fill-slate-500"
                >{{ $labels[$i] }}</text>
            @endforeach
        </svg>
    </div>
@endif
