{{--
    "Distribution Channels" — how total reach splits across platforms, for whatever scope the
    caller already computed $rows in (see ChurchDashboardController::index()). $rows: a
    collection of ['platform' => SocialPlatform, 'label' => string, 'count' => int, 'reach' =>
    int], one per platform this app tracks (in SocialPlatform::cases() order — the fixed,
    never-cycled order the donut/legend/icon-row all share).

    Brand colors (not an abstract categorical ramp) so each platform reads as itself, same
    palette as <x-platform-icon>. TikTok's teal needs a darker dark-mode step to clear the
    OKLCH lightness band against a dark surface (validated via dataviz skill's
    validate_palette.js — light passes clean, dark passes with a contrast WARN on Instagram's
    purple, mitigated here by the always-visible direct label next to every dot).

    Hover interaction: hovering a legend row dims the base ring and shows a single-wedge overlay
    (transparent everywhere except that platform's arc) on top, so the hovered slice pops while
    the rest recede — plus writes that platform's percentage into the donut hole. Plain inline JS
    scoped via data-donut-card + document.currentScript (not a Tailwind peer/has selector) since
    the legend <li>s and the ring <div> aren't siblings — they're both descendants of the same
    flex row but nested under a <ul>, which plain CSS sibling combinators can't cross.
--}}
@props(['rows', 'viewAllUrl' => null, 'scope' => null])

@php
    $uid = 'dist-'.\Illuminate\Support\Str::random(8);

    $colors = [
        'youtube' => '#FF0000',
        'instagram' => '#833AB4',
        'tiktok' => '#00B8B0',
        'facebook' => '#1877F2',
    ];
    $colorsDark = $colors;
    $colorsDark['tiktok'] = '#00897B';

    $totalReach = collect($rows)->sum('reach');
    $percents = collect($rows)->mapWithKeys(fn ($row) => [
        $row['platform']->value => $totalReach > 0 ? round($row['reach'] / $totalReach * 100, 1) : 0,
    ]);

    $cumulative = 0;
    $segments = collect($rows)->filter(fn ($row) => $row['reach'] > 0)->map(function ($row) use (&$cumulative, $totalReach) {
        $shareDeg = $totalReach > 0 ? round($row['reach'] / $totalReach * 360, 2) : 0;
        $start = $cumulative;
        $cumulative += $shareDeg;

        return ['platform' => $row['platform']->value, 'start' => $start, 'end' => $cumulative];
    })->values();

    $ringStyle = fn (array $palette) => $segments->isEmpty()
        ? 'background: transparent'
        : 'background: conic-gradient('.$segments->map(fn ($s) => "{$palette[$s['platform']]} {$s['start']}deg {$s['end']}deg")->implode(', ').')';

    $highlightStyle = fn (array $palette, array $segment) => 'background: conic-gradient(transparent 0deg '.$segment['start'].'deg, '
        .$palette[$segment['platform']].' '.$segment['start'].'deg '.$segment['end'].'deg, transparent '.$segment['end'].'deg 360deg)';
@endphp

<div data-donut-card="{{ $uid }}" class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
    <div class="mb-1 flex items-start justify-between gap-2">
        <p class="font-bold text-slate-900 dark:text-white">{{ __('dashboard.distribution_channels') }}</p>
        @if ($viewAllUrl)
            <a href="{{ $viewAllUrl }}" class="shrink-0 text-sm text-blue-600 hover:underline dark:text-blue-400">
                {{ __('common.view_all') }}
            </a>
        @endif
    </div>
    <p class="mb-5 text-sm text-slate-500 dark:text-slate-400">{{ __('dashboard.distribution_channels_subtitle') }}</p>

    @if ($totalReach === 0)
        <p class="py-6 text-center text-sm text-slate-400 dark:text-slate-500">{{ __('common.no_data_yet') }}</p>
    @else
        <div class="flex flex-col items-center gap-8 sm:flex-row sm:items-center sm:justify-between">
            <ul class="w-full flex-1 space-y-3.5">
                @foreach ($rows as $row)
                    <li
                        data-donut-target="{{ $row['platform']->value }}"
                        class="-mx-2 flex items-center gap-3 rounded-lg px-2 py-1 text-sm transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60"
                    >
                        <span
                            class="h-2.5 w-2.5 shrink-0 rounded-full bg-[var(--dot-color-light)] dark:bg-[var(--dot-color-dark)]"
                            style="--dot-color-light: {{ $colors[$row['platform']->value] }}; --dot-color-dark: {{ $colorsDark[$row['platform']->value] }}"
                        ></span>
                        <div class="min-w-0 flex-1">
                            @if ($scope)
                                <a
                                    href="{{ $scope->platformComparisonUrl(['platform' => $row['platform']->value]) }}"
                                    class="block truncate font-medium text-slate-700 hover:text-blue-600 dark:text-slate-200 dark:hover:text-blue-400"
                                >
                                    {{ $row['label'] }}
                                </a>
                            @else
                                <p class="truncate font-medium text-slate-700 dark:text-slate-200">{{ $row['label'] }}</p>
                            @endif
                            <p class="text-xs text-slate-400 dark:text-slate-500">
                                {{ __('dashboard.platform_reach', ['value' => number_format($row['reach']), 'total' => number_format($totalReach)]) }}
                            </p>
                        </div>
                        <span class="ml-auto shrink-0 font-semibold tabular-nums text-slate-900 dark:text-white">{{ $percents[$row['platform']->value] }}%</span>
                    </li>
                @endforeach
            </ul>

            <div class="relative h-40 w-40 shrink-0">
                <div data-donut-base class="absolute inset-0 rounded-full transition-opacity duration-150 dark:hidden" style="{{ $ringStyle($colors) }}"></div>
                <div data-donut-base class="absolute inset-0 hidden rounded-full transition-opacity duration-150 dark:block" style="{{ $ringStyle($colorsDark) }}"></div>

                @foreach ($segments as $segment)
                    <div
                        data-donut-highlight="{{ $segment['platform'] }}"
                        class="absolute inset-0 rounded-full opacity-0 transition-opacity duration-150 dark:hidden"
                        style="{{ $highlightStyle($colors, $segment) }}"
                    ></div>
                    <div
                        data-donut-highlight="{{ $segment['platform'] }}"
                        class="absolute inset-0 hidden rounded-full opacity-0 transition-opacity duration-150 dark:block"
                        style="{{ $highlightStyle($colorsDark, $segment) }}"
                    ></div>
                @endforeach

                <div class="absolute inset-[22%] flex items-center justify-center rounded-full bg-white dark:bg-slate-900">
                    <span data-donut-percent class="text-base font-bold leading-none tabular-nums text-slate-900 dark:text-white"></span>
                </div>
            </div>
        </div>

        <script>
            (function () {
                var card = document.currentScript.closest('[data-donut-card]');
                if (!card) return;

                var percentEl = card.querySelector('[data-donut-percent]');
                var baseRings = card.querySelectorAll('[data-donut-base]');
                var highlights = card.querySelectorAll('[data-donut-highlight]');
                var legendRows = card.querySelectorAll('[data-donut-target]');
                var percents = @json($percents->map(fn ($p) => (string) $p));

                function setActive(platform) {
                    highlights.forEach(function (el) {
                        el.style.opacity = platform && el.dataset.donutHighlight === platform ? '1' : '0';
                    });
                    baseRings.forEach(function (el) {
                        el.style.opacity = platform ? '0.25' : '1';
                    });
                    percentEl.textContent = platform ? percents[platform] + '%' : '';
                }

                legendRows.forEach(function (row) {
                    row.addEventListener('mouseenter', function () { setActive(row.dataset.donutTarget); });
                    row.addEventListener('mouseleave', function () { setActive(null); });
                });
            })();
        </script>
    @endif
</div>
