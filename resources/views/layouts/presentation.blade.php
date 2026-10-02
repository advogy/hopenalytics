{{--
    Shared shell for the two big-screen "Presentasi" pages (Total Reach / Weekly Growth) — a
    standalone HTML document (not layouts.app: no nav/sidebar, full-bleed, auto-refreshing).
    Children set $rowView (the row partial, via @php before @extends) and the title/headerStat/
    headerLinks/sidebarExtra sections; $rows/$totalEntities/$totalSocials/$scope/$filter come from
    the controller.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <script>
        (function () {
            // Light is the default for everyone until they explicitly switch — no falling
            // back to the OS/browser's prefers-color-scheme like before.
            if (localStorage.getItem('theme') === 'dark') {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="90">
    <title>@yield('title') — {{ config('app.name') }}</title>

    <link rel="icon" type="image/svg+xml" href="{{ asset('images/hopenalytics-mark.svg') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @include('partials.searchable-select')

    <style>
        #rank-list::-webkit-scrollbar { display: none; }
        #rank-list { scrollbar-width: none; }
    </style>
</head>
<body class="h-screen overflow-hidden bg-[#f8f4ec] font-sans text-slate-900 antialiased dark:bg-[#0b1728] dark:text-white">
    @include('partials.platform-icon-sprite')
    <div class="mx-auto flex h-screen max-w-7xl flex-col px-6 py-6">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-black/5 bg-white px-6 py-4 dark:border-white/5 dark:bg-[#0f1e33]">
            <div class="flex items-center gap-3">
                <x-brand-mark class="h-10 w-10 text-blue-600 dark:text-[#f3ead9]" />
                <span class="text-lg font-semibold">{{ config('app.name') }}</span>
            </div>

            <div class="text-center">
                @yield('headerStat')
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
                @yield('headerLinks')
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
                {{-- Uni/Daerah filter (see ChurchDashboardController::presentationRegionFilter()) —
                     the same type-to-search Uni → Daerah cascade Analitik & Grafik's own region
                     filter uses (partials/searchable-select), auto-submitted on pick so it works
                     with a single click on a big screen; the 90s meta refresh above re-requests
                     the same URL, so the chosen filter survives every refresh. --}}
                <form method="GET" id="presentation-filter" class="space-y-3 rounded-2xl border border-black/5 bg-white p-4 dark:border-white/5 dark:bg-[#0f1e33]">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold">{{ __('presentation.filter_title') }}</p>
                        @if ($filter['isActive'])
                            <a href="{{ url()->current() }}" class="text-xs font-medium text-blue-600 hover:underline dark:text-blue-400">{{ __('presentation.filter_reset') }}</a>
                        @endif
                    </div>
                    <div class="relative" data-searchable-select data-presentation-union>
                        <x-icon name="globe-alt" class="pointer-events-none absolute top-1/2 left-3 z-10 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input type="hidden" name="union_id" data-searchable-select-value value="{{ $filter['selectedUnionId'] }}">
                        <input
                            type="text"
                            data-searchable-select-search
                            autocomplete="off"
                            placeholder="{{ __('entity.search_uni_placeholder') }}"
                            aria-label="{{ __('entity.search_uni_placeholder') }}"
                            class="w-full rounded-lg border py-2 pr-9 pl-9 text-sm transition {{ $filter['selectedUnionId'] ? 'border-blue-600 bg-blue-50 text-blue-900 dark:border-blue-500 dark:bg-blue-950/40 dark:text-blue-200' : 'border-black/10 bg-white text-slate-700 dark:border-white/10 dark:bg-[#0b1728] dark:text-slate-200' }}"
                        >
                        <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <ul data-searchable-select-list class="absolute left-0 top-full z-20 mt-1 hidden max-h-52 w-full overflow-y-auto rounded-lg border border-black/10 bg-white p-1 text-sm shadow-lg dark:border-white/10 dark:bg-slate-800"></ul>
                    </div>
                    <div class="relative" data-searchable-select data-presentation-conference>
                        <x-icon name="globe-alt" class="pointer-events-none absolute top-1/2 left-3 z-10 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input type="hidden" name="conference_id" data-searchable-select-value value="{{ $filter['selectedConferenceId'] }}">
                        <input
                            type="text"
                            data-searchable-select-search
                            autocomplete="off"
                            placeholder="{{ __('entity.search_daerah_placeholder') }}"
                            aria-label="{{ __('entity.search_daerah_placeholder') }}"
                            class="w-full rounded-lg border py-2 pr-9 pl-9 text-sm transition {{ $filter['selectedConferenceId'] ? 'border-blue-600 bg-blue-50 text-blue-900 dark:border-blue-500 dark:bg-blue-950/40 dark:text-blue-200' : 'border-black/10 bg-white text-slate-700 dark:border-white/10 dark:bg-[#0b1728] dark:text-slate-200' }}"
                        >
                        <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <ul data-searchable-select-list class="absolute left-0 top-full z-20 mt-1 hidden max-h-52 w-full overflow-y-auto rounded-lg border border-black/10 bg-white p-1 text-sm shadow-lg dark:border-white/10 dark:bg-slate-800"></ul>
                    </div>
                </form>
                @yield('sidebarExtra')
                <div class="rounded-2xl border border-black/5 bg-white p-4 text-sm text-slate-500 dark:border-white/5 dark:bg-[#0f1e33] dark:text-slate-400">
                    {{ __('presentation.data_as_of', ['date' => now('Asia/Jakarta')->translatedFormat('d M Y H:i')]) }}
                </div>
            </div>

            <div id="rank-list" class="min-h-0 space-y-2 overflow-y-auto">
                <div id="rank-set-1" class="space-y-2">
                    @foreach ($rows as $i => $row)
                        @include($rowView, ['i' => $i, 'row' => $row])
                    @endforeach
                </div>
                <div id="rank-set-2" class="mt-2 space-y-2" aria-hidden="true">
                    @foreach ($rows as $i => $row)
                        @include($rowView, ['i' => $i, 'row' => $row])
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var form = document.getElementById('presentation-filter');
            if (! form) return;

            window.initUnionConferenceCascade({
                unionSelector: '[data-presentation-union]',
                conferenceSelector: '[data-presentation-conference]',
                unions: @json($filter['unionOptions']->map(fn ($u) => ['id' => $u->id, 'label' => $u->name])->values()),
                conferences: @json($filter['conferenceOptions']->map(fn ($c) => ['id' => $c->id, 'union_id' => $c->union_id, 'label' => $c->name])->values()),
                unionPlaceholder: @json(__('entity.search_uni_placeholder')),
                conferencePlaceholder: @json(__('entity.search_daerah_placeholder')),
                conferenceWaitingPlaceholder: @json(__('accounts.waiting_for_uni')),
                // Only a real pick submits — the engine also fires onChange('') on every
                // keystroke while typing (see partials/analytics-region-filter's own note).
                onChange: function (value) {
                    if (! value) return;
                    // Drop empty params so the URL stays clean (?union_id=3, not ?union_id=3&conference_id=).
                    form.querySelectorAll('[data-searchable-select-value]').forEach(function (field) {
                        field.disabled = field.value === '';
                    });
                    form.submit();
                },
            });
        })();
    </script>

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

            // The duplicate set only exists to make the auto-scroll loop seamless — when the
            // whole list already fits on screen there's no scrolling, so showing it would just
            // list every row twice.
            var setTwo = document.getElementById('rank-set-2');

            function tick() {
                var overflows = setOne.offsetHeight > list.clientHeight;
                if (setTwo) setTwo.classList.toggle('hidden', ! overflows);

                if (overflows) {
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
