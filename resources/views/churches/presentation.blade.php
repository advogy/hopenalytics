<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <script>
        (function () {
            var theme = localStorage.getItem('theme');
            var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (theme === 'dark' || (! theme && prefersDark)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="90">
    <title>{{ __('presentation.title') }}{{ $scope->titleSuffix() }} — {{ config('app.name') }}</title>

    <link rel="icon" type="image/svg+xml" href="{{ asset('images/hopenalytics-mark.svg') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        #rank-list::-webkit-scrollbar { display: none; }
        #rank-list { scrollbar-width: none; }
    </style>
</head>
<body class="h-screen overflow-hidden bg-[#f8f4ec] font-sans text-slate-900 antialiased dark:bg-[#0b1728] dark:text-white">
    <div class="mx-auto flex h-screen max-w-7xl flex-col px-6 py-6">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-black/5 bg-white px-6 py-4 dark:border-white/5 dark:bg-[#0f1e33]">
            <div class="flex items-center gap-3">
                <x-logo-mark class="h-10 w-10 shrink-0 text-blue-600 dark:text-[#f3ead9]" />
                <span class="text-lg font-semibold">Hopenalytics</span>
            </div>

            <div class="text-center">
                <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('presentation.total_reach_all', ['label' => $scope->labelCap()]) }}</p>
                <p class="text-4xl font-bold tabular-nums">{{ number_format($totalReach) }}</p>
            </div>

            <div class="flex items-center gap-2">
                <button
                    id="theme-toggle"
                    type="button"
                    aria-label="{{ __('nav.toggle_theme') }}"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-white"
                >
                    <x-icon name="sun" class="hidden h-4.5 w-4.5 dark:block" />
                    <x-icon name="moon" class="block h-4.5 w-4.5 dark:hidden" />
                </button>
                <a href="{{ $scope->presentationGrowthUrl() }}" title="{{ __('presentation.growth_icon_title') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-white">
                    <x-icon name="arrow-trending-up" class="h-4.5 w-4.5" />
                </a>
                <a href="{{ $scope->other()->presentationUrl() }}" title="{{ $scope->other()->labelCap() }}" class="inline-flex h-9 w-9 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-white">
                    <x-icon :name="$scope->other()->icon()" class="h-4.5 w-4.5" />
                </a>
            </div>
        </div>

        <div class="grid min-h-0 flex-1 grid-cols-1 gap-6 lg:grid-cols-[300px_1fr]">
            <div class="space-y-4">
                <div class="rounded-2xl border border-black/5 bg-white p-6 dark:border-white/5 dark:bg-[#0f1e33]">
                    <p class="mb-1 text-5xl font-bold tabular-nums">{{ $totalEntities }}</p>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('presentation.registered', ['label' => $scope->labelCap()]) }}</p>
                </div>
                <div class="rounded-2xl border border-black/5 bg-white p-6 dark:border-white/5 dark:bg-[#0f1e33]">
                    <p class="mb-1 text-5xl font-bold tabular-nums">{{ $totalSocials }}</p>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('presentation.connected_socials') }}</p>
                </div>
                <div class="rounded-2xl border border-black/5 bg-white p-4 text-sm text-slate-500 dark:border-white/5 dark:bg-[#0f1e33] dark:text-slate-400">
                    {{ __('presentation.data_as_of', ['date' => now('Asia/Jakarta')->translatedFormat('d M Y H:i')]) }}
                </div>
            </div>

            <div id="rank-list" class="min-h-0 space-y-2 overflow-y-auto">
                <div id="rank-set-1" class="space-y-2">
                    @foreach ($rows as $i => $row)
                        @include('churches._presentation-row', ['i' => $i, 'row' => $row])
                    @endforeach
                </div>
                <div id="rank-set-2" class="mt-2 space-y-2" aria-hidden="true">
                    @foreach ($rows as $i => $row)
                        @include('churches._presentation-row', ['i' => $i, 'row' => $row])
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('theme-toggle').addEventListener('click', function () {
            var isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
        });
    </script>

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
