{{-- $width/$height set the SVG viewBox's aspect ratio (not a pixel size — the element itself
     still scales via class="w-full" below) — override for a usage that spans much wider than
     the 640x240 default was drawn for (e.g. a full-page-width chart), where that ratio would
     otherwise render disproportionately tall.

     $labelDensity: 'sparse' (default — first/middle/last only, once there are more than 5
     points; every other consumer of this component keeps this, e.g. the weekly reach/views/
     likes/posts charts, which rarely have more than a handful of points anyway) or 'dense' (as
     many evenly-spaced labels as the chart's own width can fit without the text overlapping,
     always including the last point — for a usage with many more points where "first/middle/
     last" alone reads as too sparse to be useful, e.g. the Hastag tab's per-date/per-hour chart).

     $shortLabels/$dateKeys: for a chart whose full $labels sometimes repeat a shared prefix
     (the Hastag hourly chart's own "30 Agt, 01:00" / "30 Agt, 02:00" / ...), $shortLabels is
     that same point with the shared part dropped (e.g. "02:00") and $dateKeys says which
     "group" (day) each point belongs to — printed on the chart's own x-axis, a shown point gets
     its full $labels text only the first time a group appears among the points actually being
     shown (see $labelIndexes below), $shortLabels every time after that, so a run of same-day
     hours doesn't repeat "30 Agt" in front of every single one — while the hover tooltip always
     uses the full, unambiguous $labels text regardless of what's on the axis right by it. This
     decision happens AFTER $labelIndexes is chosen, not before: deciding it against every raw
     point up front risked the one point that actually starts a new group landing on a point
     $labelIndexes goes on to thin away, silently dropping the group change from the chart
     entirely instead of just deferring it to the next point that does get shown. Both default to
     $labels, which — since every point's own text is then necessarily distinct from its
     neighbors' — means every shown point keeps its full text, same as before this pair existed. --}}
@props(['values' => [], 'labels' => [], 'shortLabels' => null, 'dateKeys' => null, 'width' => 640, 'height' => 240, 'labelDensity' => 'sparse'])

@php
    $values = collect($values)->values()->all();
    $labels = collect($labels)->values()->all();
    $shortLabels = collect($shortLabels ?? $labels)->values()->all();
    $dateKeys = collect($dateKeys ?? $labels)->values()->all();
    $count = count($values);
    $chartId = 'growth-chart-'.\Illuminate\Support\Str::random(8);
@endphp

@if ($count < 2)
    <div class="flex h-56 items-center justify-center rounded-xl border border-dashed border-slate-200 px-6 text-center text-sm text-slate-400 dark:border-slate-700 dark:text-slate-500">
        {{ __('common.growth_chart_no_data') }}
    </div>
@else
    @php
        $padLeft = 52;
        $padRight = 16;
        $padTop = 28;
        // 'dense' mode's axis labels can be two lines tall (see $dayMonth below) — the default
        // 28px left them sitting right at the very bottom edge of the chart, cramped against the
        // card's own border underneath with no breathing room.
        $padBottom = $labelDensity === 'dense' ? 44 : 28;
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

        if ($count <= 5) {
            $labelIndexes = range(0, $count - 1);
        } elseif ($labelDensity === 'dense') {
            // Budgeted off the LONGEST label that could possibly land on a shown point — the
            // full $labels form, since which points end up "full" vs "short" (see $axisTexts
            // below) isn't decided until after this step spacing is chosen. Using the shorter
            // $shortLabels here instead would under-budget: whichever shown point ends up
            // needing its full text would then have no extra room reserved for it and could
            // still run into its neighbor. ~6px/character at this 10px font, plus a small gap.
            $maxLabelChars = max(4, collect($labels)->map(fn ($label) => mb_strlen($label))->max());
            $estimatedLabelWidth = $maxLabelChars * 6 + 10;
            $maxLabels = max(2, (int) floor($plotWidth / $estimatedLabelWidth));
            $labelStep = max(1, (int) ceil(($count - 1) / ($maxLabels - 1)));
            $labelIndexes = range(0, $count - 1, $labelStep);
            if (end($labelIndexes) !== $count - 1) {
                // The evenly-spaced steps above don't necessarily land exactly on the final
                // point, so it gets appended on top — but when that leaves it any closer to
                // whichever step-based point came before it than $labelStep itself (the same
                // spacing every other pair of shown labels already relies on), that last pair's
                // own two labels collide instead of just this one label ending up merely a bit
                // crowded (see the "31 Agt"/"01 Sep" bug report — a *half*-step threshold here
                // still let a same-width neighbor through). Swap the too-close neighbor out for
                // the final point instead of keeping both.
                if (($count - 1 - end($labelIndexes)) < $labelStep) {
                    array_pop($labelIndexes);
                }
                $labelIndexes[] = $count - 1;
            }
        } else {
            $labelIndexes = [0, intdiv($count - 1, 2), $count - 1];
        }

        // The text actually printed for each shown point — full $labels the first time its own
        // $dateKeys group appears among the SHOWN points (not among every raw point — see this
        // component's own doc comment above), $shortLabels every time after that.
        $axisTexts = [];
        $lastShownDateKey = null;
        foreach ($labelIndexes as $i) {
            $axisTexts[$i] = ($lastShownDateKey === null || $dateKeys[$i] !== $lastShownDateKey) ? $labels[$i] : $shortLabels[$i];
            $lastShownDateKey = $dateKeys[$i];
        }
    @endphp

    <div class="relative overflow-x-auto">
        <svg id="{{ $chartId }}" viewBox="0 0 {{ $width }} {{ $height }}" class="w-full" style="min-width: 320px" role="img" aria-label="{{ __('common.growth_chart_aria_label') }}">
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

            {{-- A vertical guide line, shown/moved by the hover script below, between the axis
                 and whichever point is currently hovered — hidden (x off-canvas) until then. --}}
            <line data-chart-crosshair x1="-100" x2="-100" y1="{{ $padTop }}" y2="{{ $baseline }}" stroke-width="1" stroke-dasharray="3 3" class="stroke-slate-300 dark:stroke-slate-600" />

            @foreach ($points as $i => $point)
                <circle
                    cx="{{ $point[0] }}"
                    cy="{{ $point[1] }}"
                    r="{{ $i === $count - 1 ? 4 : 3 }}"
                    class="fill-blue-500 dark:fill-blue-400"
                    stroke="var(--sparkline-ring, white)"
                    stroke-width="2"
                />
                {{-- In dense mode, a value label on every point would overlap just as badly as
                     an x-axis label on every point would — only label the same reduced set of
                     points as $labelIndexes below (still always including the last point). --}}
                @if ($labelDensity !== 'dense' || in_array($i, $labelIndexes, true))
                    <text
                        x="{{ $point[0] }}"
                        y="{{ $point[1] - 10 }}"
                        text-anchor="{{ $i === 0 ? 'start' : ($i === $count - 1 ? 'end' : 'middle') }}"
                        class="{{ $i === $count - 1 ? 'fill-slate-700 text-xs font-semibold dark:fill-slate-200' : 'fill-slate-500 text-[10px] font-medium dark:fill-slate-400' }}"
                    >{{ number_format($values[$i]) }}</text>
                @endif

                {{-- Invisible, oversized hit target — the visible dot above is only 3-4px, far
                     too small to reliably hover on its own — carrying the FULL label (never the
                     shortened axis text) so the tooltip is always unambiguous about exactly
                     which point it's describing. --}}
                <circle
                    cx="{{ $point[0] }}"
                    cy="{{ $point[1] }}"
                    r="12"
                    fill="transparent"
                    class="growth-chart-hit"
                    style="cursor: pointer;"
                    data-label="{{ $labels[$i] }}"
                    data-value="{{ number_format($values[$i]) }}"
                />
            @endforeach

            @foreach ($labelIndexes as $i)
                @php
                    // Two-line axis labels, 'dense' mode only — also buys back some of the
                    // horizontal room dense packing costs, since two stacked lines are roughly
                    // half as wide as the same text on one. Two shapes get split, each per the
                    // user's explicit call: a plain "04 Agt" day+month label (daily chart) as day
                    // on top, month below; a comma-joined "31 Agt, 00:00" day-change label
                    // (hourly chart, shown once whenever the date rolls over) as the TIME on top,
                    // date below instead — reversed order, since the time is what changes point
                    // to point on that chart while the date is the "still on the same day"
                    // context. An hour-only label ("16:00", no comma, no space) stays one line —
                    // nothing left to split it into.
                    $axisText = $axisTexts[$i];
                    $lines = null;
                    if ($labelDensity === 'dense') {
                        if (str_contains($axisText, ',')) {
                            [$datePart, $timePart] = array_map('trim', explode(',', $axisText, 2));
                            $lines = [$timePart, $datePart];
                        } elseif (substr_count($axisText, ' ') === 1) {
                            $lines = explode(' ', $axisText, 2);
                        }
                    }
                @endphp
                {{-- In dense mode, EVERY point's first line sits at the same y — height-20 —
                     whether or not that particular point ends up with a second line below it
                     (e.g. an hour-only "12:00" next to a two-line "31 Agt / 00:00"); otherwise
                     an unsplit label kept sitting at the old, lower height-8 position while a
                     split one's first line moved up to make room for its second, so times ended
                     up on two different rows instead of lining up across the whole axis. --}}
                <text
                    x="{{ $points[$i][0] }}"
                    y="{{ $labelDensity === 'dense' ? $height - 20 : $height - 8 }}"
                    text-anchor="{{ $i === 0 ? 'start' : ($i === $count - 1 ? 'end' : 'middle') }}"
                    class="fill-slate-400 text-[10px] dark:fill-slate-500"
                >
                    @if ($lines)
                        <tspan x="{{ $points[$i][0] }}">{{ $lines[0] }}</tspan>
                        <tspan x="{{ $points[$i][0] }}" dy="12">{{ $lines[1] }}</tspan>
                    @else
                        {{ $axisText }}
                    @endif
                </text>
            @endforeach
        </svg>

        {{-- Positioned in JS (see below) relative to whichever point is hovered, converting the
             SVG's own viewBox coordinates to actual on-screen pixels so this lines up correctly
             regardless of how much class="w-full" has scaled the chart up or down. --}}
        <div data-chart-tooltip class="pointer-events-none absolute z-10 hidden -translate-x-1/2 whitespace-nowrap rounded-lg bg-slate-900 px-2.5 py-1.5 text-xs font-medium text-white shadow-lg dark:bg-slate-700">
            <span data-chart-tooltip-label></span>: <span data-chart-tooltip-value class="font-semibold"></span>
        </div>
    </div>

    <script>
        (function () {
            var svg = document.getElementById(@json($chartId));
            if (! svg) return;

            var container = svg.parentElement;
            var tooltip = container.querySelector('[data-chart-tooltip]');
            var labelEl = tooltip.querySelector('[data-chart-tooltip-label]');
            var valueEl = tooltip.querySelector('[data-chart-tooltip-value]');
            var crosshair = svg.querySelector('[data-chart-crosshair]');
            var viewBoxWidth = {{ $width }};
            var viewBoxHeight = {{ $height }};

            function showFor(hit) {
                var svgRect = svg.getBoundingClientRect();
                var scaleX = svgRect.width / viewBoxWidth;
                var scaleY = svgRect.height / viewBoxHeight;
                var cx = parseFloat(hit.getAttribute('cx'));
                var cy = parseFloat(hit.getAttribute('cy'));
                var pixelX = cx * scaleX;
                var pixelY = cy * scaleY;

                labelEl.textContent = hit.dataset.label;
                valueEl.textContent = hit.dataset.value;

                // Above the point by default, matching where its own always-visible value label
                // already sits — but that's exactly the problem for a point near the TOP of the
                // chart (a high value, or this whole chart's range being narrow so every point
                // clusters up there): the tooltip would land right on top of that label, or a
                // neighboring point's. ~36px is a rough tooltip-height-plus-gap guess, since it's
                // still `hidden` here and hasn't actually been measured yet.
                var flip = pixelY < 36;
                tooltip.classList.toggle('-translate-y-full', ! flip);
                tooltip.classList.toggle('translate-y-2', flip);

                // Must remove `hidden` (display:none) BEFORE reading offsetWidth below, or the
                // tooltip measures as 0 and every point looks like it needs no clamping at all.
                tooltip.classList.remove('hidden');

                // Centered on the point by default (see the -translate-x-1/2 class below) — which
                // is exactly what runs the tooltip past the chart's own left/right edge for the
                // first or last point on a chart that already spans its container's full width.
                // Clamped against the chart's own rendered width, not the point's raw position.
                var halfTooltipWidth = tooltip.offsetWidth / 2;
                var clampedX = Math.min(Math.max(pixelX, halfTooltipWidth), svgRect.width - halfTooltipWidth);

                tooltip.style.left = clampedX + 'px';
                tooltip.style.top = (flip ? pixelY + 8 : pixelY - 8) + 'px';

                crosshair.setAttribute('x1', cx);
                crosshair.setAttribute('x2', cx);
            }

            function hide() {
                tooltip.classList.add('hidden');
                crosshair.setAttribute('x1', -100);
                crosshair.setAttribute('x2', -100);
            }

            svg.querySelectorAll('.growth-chart-hit').forEach(function (hit) {
                hit.addEventListener('mouseenter', function () { showFor(hit); });
                hit.addEventListener('mousemove', function () { showFor(hit); });
                hit.addEventListener('touchstart', function () { showFor(hit); }, { passive: true });
            });
            svg.addEventListener('mouseleave', hide);
        })();
    </script>
@endif
