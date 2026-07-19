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
        <title>@yield('title', 'Churchnalytics')</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            :root { --sparkline-ring: #ffffff; }
            :root.dark { --sparkline-ring: #0f172a; }

            dialog#confirm-dialog {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                margin: 0;
                padding: 0;
                border: none;
                border-radius: 1rem;
                box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
                max-width: 24rem;
                width: calc(100% - 2rem);
            }
            dialog#confirm-dialog::backdrop {
                background: rgba(15, 23, 42, 0.4);
                backdrop-filter: blur(2px);
            }

            dialog#export-dialog {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                margin: 0;
                padding: 0;
                border: none;
                border-radius: 1rem;
                box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
                max-width: 64rem;
                width: calc(100% - 2rem);
                max-height: calc(100vh - 4rem);
            }
            dialog#export-dialog::backdrop {
                background: rgba(15, 23, 42, 0.4);
                backdrop-filter: blur(2px);
            }
        </style>
    </head>
    <body class="flex min-h-screen flex-col bg-[#f9f9f7] font-sans text-slate-900 antialiased dark:bg-[#0d0d0d] dark:text-slate-100">
        <header class="sticky top-0 z-10 border-b border-black/5 bg-[#f9f9f7]/80 backdrop-blur-md dark:border-white/5 dark:bg-[#0d0d0d]/80">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <div class="flex items-center justify-between py-4">
                    <a href="{{ route('churches.index') }}" class="flex items-center gap-2.5">
                        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-violet-600 text-sm font-bold text-white shadow-sm">
                            CN
                        </span>
                        <span class="hidden text-lg font-semibold tracking-tight sm:inline">Churchnalytics</span>
                    </a>

                    <div class="flex items-center gap-2 sm:gap-4">
                        <div class="hidden items-center gap-4 lg:flex">
                            <a href="{{ route('churches.directory') }}" class="text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
                                {{ __('nav.directory') }}
                            </a>
                            <a href="{{ route('churches.analytics') }}" class="text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
                                {{ __('nav.analytics') }}
                            </a>
                            <a href="{{ route('churches.presentation') }}" target="_blank" class="text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
                                {{ __('nav.presentation') }}
                            </a>
                            <a
                                href="{{ route('about') }}"
                                title="{{ __('nav.about') }}"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-black/5 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50 dark:border-white/5 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                            >
                                <x-icon name="information-circle" class="h-5 w-5" />
                            </a>
                            <a
                                href="{{ route('queue.index') }}"
                                title="{{ __('nav.queue') }}"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-black/5 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50 dark:border-white/5 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                            >
                                <x-icon name="queue-list" class="h-5 w-5" />
                            </a>
                            <a
                                href="{{ route('settings.edit') }}"
                                title="{{ __('nav.settings') }}"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-black/5 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50 dark:border-white/5 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                            >
                                <x-icon name="cog-6-tooth" class="h-5 w-5" />
                            </a>
                        </div>

                        @php $otherLocale = app()->getLocale() === 'id' ? 'en' : 'id'; @endphp
                        <a
                            href="{{ route('locale.switch', $otherLocale) }}"
                            title="{{ __('nav.language') }}"
                            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-black/5 bg-white text-xs font-bold text-slate-600 shadow-sm transition hover:bg-slate-50 dark:border-white/5 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                        >
                            {{ strtoupper($otherLocale) }}
                        </a>

                        <button
                            id="theme-toggle"
                            type="button"
                            aria-label="{{ __('nav.toggle_theme') }}"
                            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-black/5 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50 dark:border-white/5 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                        >
                            <x-icon name="sun" class="hidden h-5 w-5 dark:block" />
                            <x-icon name="moon" class="block h-5 w-5 dark:hidden" />
                        </button>

                        <button
                            id="mobile-menu-toggle"
                            type="button"
                            aria-label="{{ __('nav.open_menu') }}"
                            aria-expanded="false"
                            aria-controls="mobile-menu"
                            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-black/5 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50 dark:border-white/5 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 lg:hidden"
                        >
                            <x-icon name="bars-3" class="block h-5 w-5" data-menu-icon-open />
                            <x-icon name="x-mark" class="hidden h-5 w-5" data-menu-icon-close />
                        </button>
                    </div>
                </div>

                <div id="mobile-menu" class="hidden border-t border-black/5 pb-4 lg:hidden dark:border-white/5">
                    <div class="flex flex-col gap-1 pt-3">
                        <a href="{{ route('churches.directory') }}" class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                            {{ __('nav.directory') }}
                        </a>
                        <a href="{{ route('churches.analytics') }}" class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                            {{ __('nav.analytics') }}
                        </a>
                        <a href="{{ route('churches.presentation') }}" target="_blank" class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                            {{ __('nav.presentation') }}
                        </a>
                        <a href="{{ route('about') }}" class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                            {{ __('nav.about') }}
                        </a>
                        <a href="{{ route('queue.index') }}" class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                            {{ __('nav.queue') }}
                        </a>
                        <a href="{{ route('settings.edit') }}" class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                            {{ __('nav.settings') }}
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-6xl flex-1 px-6 py-10">
            @if (session('status'))
                <div class="mb-6 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-400">
                    <x-icon name="check-circle" class="h-4 w-4 shrink-0" />
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-6 flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-400">
                    <x-icon name="x-circle" class="h-4 w-4 shrink-0" />
                    {{ session('error') }}
                </div>
            @endif

            @yield('content')
        </main>

        <footer class="border-t border-black/5 dark:border-white/5">
            <div class="mx-auto flex max-w-6xl flex-col items-center gap-1 px-6 py-6 text-center text-xs text-slate-400 dark:text-slate-500 sm:flex-row sm:justify-between sm:text-left">
                <p>
                    &copy; {{ now()->year }} {{ config('app.name') }}. {{ __('nav.footer_copyright') }}
                    {{ __('nav.footer_developer') }}
                    <a href="{{ config('app.developer_url') }}" target="_blank" rel="noopener noreferrer" class="underline hover:text-blue-600 dark:hover:text-blue-400">{{ config('app.developer') }}</a>
                </p>
                <p>{{ __('nav.footer_version', ['version' => config('app.version')]) }}</p>
            </div>
        </footer>

        <dialog id="confirm-dialog" class="bg-white dark:bg-slate-900">
            <div class="p-6">
                <p data-confirm-message class="text-sm text-slate-700 dark:text-slate-200"></p>
                <div class="mt-5 flex justify-end gap-3">
                    <button
                        type="button"
                        data-confirm-cancel
                        class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                    >
                        {{ __('nav.confirm_cancel') }}
                    </button>
                    <button
                        type="button"
                        data-confirm-accept
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700"
                    >
                        {{ __('nav.confirm_accept') }}
                    </button>
                </div>
            </div>
        </dialog>

        <dialog id="export-dialog" class="bg-white dark:bg-slate-900">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('nav.export_preview_title') }}</p>
                <button
                    type="button"
                    data-export-close
                    aria-label="{{ __('nav.export_preview_close') }}"
                    class="inline-flex h-7 w-7 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800"
                >
                    <x-icon name="x-circle" class="h-5 w-5" />
                </button>
            </div>
            <div id="export-dialog-content" class="max-h-[70vh] overflow-y-auto p-6"></div>
        </dialog>

        <div
            id="refresh-progress-widget"
            class="fixed bottom-4 right-4 z-50 hidden w-72 rounded-xl border border-slate-200 bg-white p-4 shadow-lg dark:border-slate-700 dark:bg-slate-900"
        >
            <div class="mb-2 flex items-center justify-between gap-3">
                <p data-progress-title class="text-sm font-medium text-slate-700 dark:text-slate-200"></p>
                <span data-progress-text class="shrink-0 text-xs tabular-nums text-slate-500 dark:text-slate-400">0%</span>
            </div>
            <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                <div data-progress-fill class="h-full w-0 rounded-full bg-blue-600 transition-all duration-300"></div>
            </div>
        </div>

        @php
            $refreshDoneAllSuccessTemplate = __('dashboard.refresh_done_all_success', ['success' => ':success', 'total' => ':total']);
            $refreshDoneWithFailuresTemplate = __('dashboard.refresh_done_with_failures', ['success' => ':success', 'failed' => ':failed', 'total' => ':total']);
        @endphp
        <script>
            var i18n = {
                exportPreviewLoading: @json(__('nav.export_preview_loading')),
                exportPreviewFailed: @json(__('nav.export_preview_failed')),
                refreshInProgress: @json(__('dashboard.refresh_in_progress')),
                refreshDone: @json(__('dashboard.refresh_done')),
                refreshDoneAllSuccess: @json($refreshDoneAllSuccessTemplate),
                refreshDoneWithFailures: @json($refreshDoneWithFailuresTemplate),
                refreshFailed: @json(__('dashboard.refresh_failed')),
            };

            var refreshStatusUrlTemplate = @json(route('socials.refresh-status', ['batch' => '__BATCH__']));
            var refreshActiveUrl = @json(route('socials.refresh-active'));

            document.getElementById('theme-toggle').addEventListener('click', function () {
                var isDark = document.documentElement.classList.toggle('dark');
                localStorage.setItem('theme', isDark ? 'dark' : 'light');
            });

            (function () {
                var toggle = document.getElementById('mobile-menu-toggle');
                var menu = document.getElementById('mobile-menu');
                if (! toggle || ! menu) return;

                var iconOpen = toggle.querySelector('[data-menu-icon-open]');
                var iconClose = toggle.querySelector('[data-menu-icon-close]');

                toggle.addEventListener('click', function () {
                    var isOpen = menu.classList.toggle('hidden') === false;
                    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                    iconOpen.classList.toggle('hidden', isOpen);
                    iconOpen.classList.toggle('block', !isOpen);
                    iconClose.classList.toggle('hidden', !isOpen);
                    iconClose.classList.toggle('block', isOpen);
                });

                // Collapse the menu automatically if the viewport grows past the lg breakpoint.
                window.matchMedia('(min-width: 1024px)').addEventListener('change', function (e) {
                    if (e.matches && ! menu.classList.contains('hidden')) {
                        toggle.click();
                    }
                });
            })();

            (function () {
                var dialog = document.getElementById('confirm-dialog');
                var messageEl = dialog.querySelector('[data-confirm-message]');
                var acceptBtn = dialog.querySelector('[data-confirm-accept]');
                var cancelBtn = dialog.querySelector('[data-confirm-cancel]');
                var pendingForm = null;

                document.addEventListener('submit', function (e) {
                    var form = e.target;
                    if (form.hasAttribute && form.hasAttribute('data-confirm') && !form.dataset.confirmed) {
                        e.preventDefault();
                        pendingForm = form;
                        messageEl.textContent = form.getAttribute('data-confirm');
                        dialog.showModal();
                    }
                });

                acceptBtn.addEventListener('click', function () {
                    if (pendingForm) {
                        pendingForm.dataset.confirmed = 'true';
                        dialog.close();
                        pendingForm.requestSubmit();
                        pendingForm = null;
                    }
                });

                cancelBtn.addEventListener('click', function () {
                    pendingForm = null;
                    dialog.close();
                });
            })();

            (function () {
                var STORAGE_KEY = 'churchnalytics.refreshBatch';
                var widget = document.getElementById('refresh-progress-widget');
                var titleEl = widget.querySelector('[data-progress-title]');
                var textEl = widget.querySelector('[data-progress-text]');
                var fillEl = widget.querySelector('[data-progress-fill]');
                var pollTimer = null;

                var setPercent = function (percent) {
                    fillEl.style.width = percent + '%';
                    textEl.textContent = percent + '%';
                };

                var setButtonLocked = function (locked) {
                    var button = document.querySelector('[data-progress-button]');
                    if (button) button.disabled = locked;
                };

                var stopPolling = function () {
                    if (pollTimer) clearTimeout(pollTimer);
                    pollTimer = null;
                };

                var startPolling = function (batchId) {
                    var url = refreshStatusUrlTemplate.replace('__BATCH__', batchId);

                    var poll = function () {
                        fetch(url, { headers: { 'Accept': 'application/json' } })
                            .then(function (res) { return res.json(); })
                            .then(function (status) {
                                setPercent(status.percent);

                                if (status.finished) {
                                    var successCount = status.processed - status.failed;

                                    if (status.failed > 0) {
                                        titleEl.textContent = i18n.refreshDoneWithFailures
                                            .replace(':success', successCount)
                                            .replace(':failed', status.failed)
                                            .replace(':total', status.total);
                                    } else {
                                        titleEl.textContent = i18n.refreshDoneAllSuccess
                                            .replace(':success', successCount)
                                            .replace(':total', status.total);
                                    }

                                    localStorage.removeItem(STORAGE_KEY);
                                    stopPolling();
                                    setButtonLocked(false);
                                    setTimeout(function () { widget.classList.add('hidden'); }, status.failed > 0 ? 6000 : 2000);
                                    return;
                                }

                                pollTimer = setTimeout(poll, 1500);
                            })
                            .catch(function () { pollTimer = setTimeout(poll, 3000); });
                    };

                    poll();
                };

                var beginTracking = function (batchId) {
                    localStorage.setItem(STORAGE_KEY, JSON.stringify({ batchId: batchId }));
                    titleEl.textContent = i18n.refreshInProgress;
                    setPercent(0);
                    widget.classList.remove('hidden');
                    setButtonLocked(true);
                    startPolling(batchId);
                };

                // Server-authoritative check: is a bulk refresh running right now, from *any*
                // tab/browser/session? This is what actually locks the button and shows the
                // widget on page load — not localStorage, which only reflects this one browser.
                fetch(refreshActiveUrl, { headers: { 'Accept': 'application/json' } })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        if (data.batchId) {
                            beginTracking(data.batchId);
                            return;
                        }

                        localStorage.removeItem(STORAGE_KEY);
                    })
                    .catch(function () {
                        // Server check failed (offline?) — fall back to this browser's own memory
                        // of a batch it started, rather than leaving the button silently unlocked.
                        var stored = localStorage.getItem(STORAGE_KEY);
                        if (! stored) return;

                        try {
                            var data = JSON.parse(stored);
                            if (data && data.batchId) beginTracking(data.batchId);
                        } catch (e) {
                            localStorage.removeItem(STORAGE_KEY);
                        }
                    });

                document.addEventListener('submit', function (e) {
                    var form = e.target;
                    if (! (form.hasAttribute && form.hasAttribute('data-progress-form'))) return;
                    if (form.hasAttribute('data-confirm') && ! form.dataset.confirmed) return;

                    e.preventDefault();

                    // Locked here to block double-submits while the dispatch request is in flight;
                    // beginTracking() re-locks it for the duration of the batch, setPercent(finished) unlocks it.
                    setButtonLocked(true);

                    fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                            'Accept': 'application/json',
                        },
                    })
                        .then(function (res) { return res.json(); })
                        .then(function (data) { beginTracking(data.batchId); })
                        .catch(function () {
                            setButtonLocked(false);
                            titleEl.textContent = i18n.refreshFailed;
                            widget.classList.remove('hidden');
                            setTimeout(function () { widget.classList.add('hidden'); }, 3000);
                        });
                });
            })();

            (function () {
                document.addEventListener('submit', function (e) {
                    var form = e.target;
                    if (! (form.hasAttribute && form.hasAttribute('data-inline-refresh-form'))) return;
                    if (form.hasAttribute('data-confirm') && ! form.dataset.confirmed) return;

                    e.preventDefault();

                    var button = form.querySelector('[data-inline-refresh-button]');
                    var bar = form.parentElement.querySelector('[data-inline-refresh-bar]');

                    button.disabled = true;
                    if (bar) bar.classList.remove('hidden');

                    fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                            'Accept': 'application/json',
                        },
                    })
                        .then(function () { window.location.reload(); })
                        .catch(function () {
                            button.disabled = false;
                            if (bar) bar.classList.add('hidden');
                        });
                });
            })();

            (function () {
                var dialog = document.getElementById('export-dialog');
                var content = document.getElementById('export-dialog-content');
                var closeBtn = dialog.querySelector('[data-export-close]');

                document.addEventListener('click', function (e) {
                    var trigger = e.target.closest('[data-export-trigger]');
                    if (! trigger) return;

                    e.preventDefault();
                    var url = trigger.getAttribute('href');
                    url += (url.indexOf('?') === -1 ? '?' : '&') + 'partial=1';

                    content.innerHTML = '<p class="py-12 text-center text-sm text-slate-400">' + i18n.exportPreviewLoading + '</p>';
                    dialog.showModal();

                    fetch(url)
                        .then(function (response) { return response.text(); })
                        .then(function (html) { content.innerHTML = html; })
                        .catch(function () {
                            content.innerHTML = '<p class="py-12 text-center text-sm text-red-500">' + i18n.exportPreviewFailed + '</p>';
                        });
                });

                closeBtn.addEventListener('click', function () {
                    dialog.close();
                });
            })();
        </script>
    </body>
</html>
