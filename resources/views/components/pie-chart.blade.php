{{--
    Small reusable "part-to-whole" pie chart — a CSS conic-gradient circle (same no-JS-charting
    convention as bar-chart.blade.php and distribution-channels-card.blade.php) plus a legend.
    $rows: a collection/array of ['label' => string, 'value' => float (already a 0-100 percent,
    not a raw count), 'color' => hex string, 'detail' => string|null (an optional pre-formatted
    secondary line, e.g. a raw count the percent was computed from — the caller formats it since
    this component has no idea what it means)]. Caller supplies the colors so this stays generic —
    validate any new palette via the dataviz skill's validate_palette.js before using it here.

    Hover interaction: hovering a legend row dims the base pie and shows a single-wedge overlay
    (transparent everywhere except that row's slice) on top, so the hovered slice pops while the
    rest recede — same mechanic as distribution-channels-card's donut, minus the center-hole
    percent reveal (a full pie has no hole to write into), per the user's explicit call. Scoped
    via data-pie-card + document.currentScript, not a Tailwind peer/has selector, since the
    legend <li>s and the pie <div> aren't siblings — they're both descendants of the same flex
    row but nested under a <ul>, which plain CSS sibling combinators can't cross.
--}}
@props(['rows'])

@php
    $uid = 'pie-'.\Illuminate\Support\Str::random(8);
    $rows = collect($rows)->values();
    $total = $rows->sum('value');

    $cumulative = 0;
    $segments = $rows->map(function ($row, $i) use (&$cumulative, $total) {
        if ($row['value'] <= 0) {
            return null;
        }

        $shareDeg = $total > 0 ? round($row['value'] / $total * 360, 2) : 0;
        $start = $cumulative;
        $cumulative += $shareDeg;

        return ['index' => $i, 'color' => $row['color'], 'start' => $start, 'end' => $cumulative];
    })->filter()->values();

    $pieStyle = $segments->isEmpty()
        ? 'background: transparent'
        : 'background: conic-gradient('.$segments->map(fn ($s) => "{$s['color']} {$s['start']}deg {$s['end']}deg")->implode(', ').')';

    $highlightStyle = fn (array $segment) => 'background: conic-gradient(transparent 0deg '.$segment['start'].'deg, '
        .$segment['color'].' '.$segment['start'].'deg '.$segment['end'].'deg, transparent '.$segment['end'].'deg 360deg)';
@endphp

@if ($total <= 0)
    <p class="py-6 text-center text-sm text-slate-400 dark:text-slate-500">{{ __('common.no_data_yet') }}</p>
@else
    <div data-pie-card="{{ $uid }}" class="flex flex-col items-center gap-8 sm:flex-row sm:items-center sm:justify-between">
        <ul class="w-full flex-1 space-y-3">
            @foreach ($rows as $i => $row)
                <li
                    data-pie-target="{{ $i }}"
                    class="-mx-2 flex items-center gap-3 rounded-lg px-2 py-1 text-sm transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60"
                >
                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background: {{ $row['color'] }}"></span>
                    <span class="min-w-0 flex-1 truncate font-medium text-slate-700 dark:text-slate-200">{{ $row['label'] }}</span>
                    @if (($row['detail'] ?? null) !== null)
                        <span class="shrink-0 text-slate-400 dark:text-slate-500">{{ $row['detail'] }}</span>
                    @endif
                    <span class="ml-auto shrink-0 font-semibold tabular-nums text-slate-900 dark:text-white">{{ number_format($row['value'], 1) }}%</span>
                </li>
            @endforeach
        </ul>

        <div class="relative h-40 w-40 shrink-0">
            <div data-pie-base class="absolute inset-0 rounded-full transition-opacity duration-150" style="{{ $pieStyle }}"></div>

            @foreach ($segments as $segment)
                <div
                    data-pie-highlight="{{ $segment['index'] }}"
                    class="absolute inset-0 rounded-full opacity-0 transition-opacity duration-150"
                    style="{{ $highlightStyle($segment) }}"
                ></div>
            @endforeach
        </div>

        <script>
            (function () {
                var card = document.currentScript.closest('[data-pie-card]');
                if (!card) return;

                var baseEl = card.querySelector('[data-pie-base]');
                var highlights = card.querySelectorAll('[data-pie-highlight]');
                var legendRows = card.querySelectorAll('[data-pie-target]');

                function setActive(index) {
                    highlights.forEach(function (el) {
                        el.style.opacity = index !== null && el.dataset.pieHighlight === index ? '1' : '0';
                    });
                    baseEl.style.opacity = index !== null ? '0.25' : '1';
                }

                legendRows.forEach(function (row) {
                    row.addEventListener('mouseenter', function () { setActive(row.dataset.pieTarget); });
                    row.addEventListener('mouseleave', function () { setActive(null); });
                });
            })();
        </script>
    </div>
@endif
