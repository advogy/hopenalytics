<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="90">
    <title>{{ __('presentation.title_growth') }}{{ $scope->titleSuffix() }} — {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        #rank-list::-webkit-scrollbar { display: none; }
        #rank-list { scrollbar-width: none; }
    </style>
</head>
<body class="h-screen overflow-hidden bg-[#0b1120] font-sans text-white antialiased">
    <div class="mx-auto flex h-screen max-w-7xl flex-col px-6 py-6">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-[#111827] px-6 py-4">
            <div class="flex items-center gap-3">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-violet-600 text-base font-bold">
                    CN
                </span>
                <span class="text-lg font-semibold">Churchnalytics</span>
            </div>

            <div class="text-center">
                <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('presentation.avg_weekly_growth_score') }}</p>
                <p class="text-4xl font-bold tabular-nums {{ $avgScore !== null && $avgScore < 0 ? 'text-red-400' : '' }}">
                    @if ($avgScore !== null)
                        {{ $avgScore > 0 ? '+' : '' }}{{ number_format($avgScore, 1) }}%
                    @else
                        &ndash;
                    @endif
                </p>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ $scope->presentationUrl() }}" title="{{ __('presentation.total_reach_icon_title') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-700 hover:text-white">
                    <x-icon name="globe-alt" class="h-4.5 w-4.5" />
                </a>
                <a href="{{ $scope->other()->presentationGrowthUrl() }}" title="{{ $scope->other()->labelCap() }}" class="inline-flex h-9 w-9 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-700 hover:text-white">
                    <x-icon :name="$scope->other()->icon()" class="h-4.5 w-4.5" />
                </a>
            </div>
        </div>

        <div class="grid min-h-0 flex-1 grid-cols-1 gap-6 lg:grid-cols-[300px_1fr]">
            <div class="space-y-4">
                <div class="rounded-2xl bg-[#111827] p-6">
                    <p class="mb-1 text-5xl font-bold tabular-nums">{{ $totalEntities }}</p>
                    <p class="text-sm text-slate-400">{{ __('presentation.registered', ['label' => $scope->labelCap()]) }}</p>
                </div>
                <div class="rounded-2xl bg-[#111827] p-6">
                    <p class="mb-1 text-5xl font-bold tabular-nums">{{ $totalSocials }}</p>
                    <p class="text-sm text-slate-400">{{ __('presentation.connected_socials') }}</p>
                </div>
                <div class="rounded-2xl bg-[#111827] p-4 text-xs leading-relaxed text-slate-400">
                    {{ __('presentation.score_explanation', ['noun' => $scope->noun()]) }}
                </div>
                <div class="rounded-2xl bg-[#111827] p-4 text-sm text-slate-400">
                    {{ __('presentation.data_as_of', ['date' => now('Asia/Jakarta')->translatedFormat('d M Y H:i')]) }}
                </div>
            </div>

            <div id="rank-list" class="min-h-0 space-y-2 overflow-y-auto">
                <div id="rank-set-1" class="space-y-2">
                    @foreach ($rows as $i => $row)
                        @include('churches._presentation-growth-row', ['i' => $i, 'row' => $row])
                    @endforeach
                </div>
                <div id="rank-set-2" class="mt-2 space-y-2" aria-hidden="true">
                    @foreach ($rows as $i => $row)
                        @include('churches._presentation-growth-row', ['i' => $i, 'row' => $row])
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var list = document.getElementById('rank-list');
            var setOne = document.getElementById('rank-set-1');
            if (! list || ! setOne) return;

            function loopHeight() {
                // height of one full set plus the gap before the duplicate set
                return setOne.offsetHeight + 8;
            }

            function tick() {
                if (setOne.offsetHeight > list.clientHeight) {
                    list.scrollTop += 0.6;

                    var height = loopHeight();
                    if (list.scrollTop >= height) {
                        list.scrollTop -= height;
                    }
                }

                requestAnimationFrame(tick);
            }

            requestAnimationFrame(tick);
        })();
    </script>
</body>
</html>
