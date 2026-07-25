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
        <title>@yield('title', 'Hopenalytics')</title>

        <link rel="icon" type="image/svg+xml" href="{{ asset('images/hopenalytics-mark.svg') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @include('partials.searchable-select')

        <style>
            :root { --sparkline-ring: #ffffff; }
            :root.dark { --sparkline-ring: #0f172a; }

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
    <body class="flex min-h-screen flex-col bg-[#f8f4ec] font-sans text-slate-900 antialiased dark:bg-[#16130f] dark:text-slate-100">
        <header class="sticky top-0 z-10 border-b border-black/5 bg-[#f8f4ec]/80 backdrop-blur-md dark:border-white/5 dark:bg-[#16130f]/80">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <div class="flex items-center justify-between py-2">
                    <a href="{{ route('churches.index') }}" class="flex items-center gap-2.5">
                        <x-logo-mark class="h-16 w-16 shrink-0 text-blue-600 dark:text-[#f3ead9]" />
                        <span class="hidden text-xl font-semibold tracking-tight sm:inline">Hopenalytics</span>
                    </a>

                    <div class="flex items-center gap-2 sm:gap-4">
                        <div class="hidden items-center gap-4 lg:flex">
                            @can('view-directory')
                                <a href="{{ route('churches.directory') }}" class="text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
                                    {{ __('nav.directory') }}
                                </a>
                            @endcan
                            @can('view-analytics')
                                <a href="{{ route('churches.analytics') }}" class="text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
                                    {{ __('nav.analytics') }}
                                </a>
                            @endcan
                            <a href="{{ route('churches.presentation') }}" target="_blank" class="text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
                                {{ __('nav.presentation') }}
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

                        <div class="hidden lg:flex lg:items-center">
                            @auth
                                <div class="relative" data-account-menu>
                                    <button
                                        type="button"
                                        data-account-menu-toggle
                                        aria-expanded="false"
                                        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition-colors duration-150 hover:bg-black/5 dark:text-slate-300 dark:hover:bg-white/10"
                                    >
                                        {{ auth()->user()->name }}
                                        <x-icon name="chevron-down" class="h-4 w-4" />
                                    </button>
                                    <div
                                        data-account-menu-panel
                                        class="absolute right-0 top-full z-20 mt-2 hidden w-64 rounded-xl border border-black/5 bg-white p-1.5 shadow-lg dark:border-white/5 dark:bg-slate-900"
                                    >
                                        <div class="mb-1 border-b border-black/5 px-3 py-3 dark:border-white/5">
                                            <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ auth()->user()->name }}</p>
                                            <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">{{ auth()->user()->email }}</p>
                                        </div>
                                        <a href="{{ route('profile.edit') }}" class="flex items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                            <span class="flex items-center gap-2">
                                                <x-icon name="user" class="h-4 w-4 shrink-0 text-slate-400" />
                                                Profil Saya
                                            </span>
                                            <x-role-badge :role="auth()->user()->role" />
                                        </a>
                                        @if (auth()->user()->church_id)
                                            {{-- admin_gereja's only guaranteed path to their own church — Kelola
                                                 Akun has no organization tab for gereja-level, and the map/growth
                                                 widgets on the dashboard only surface it if it happens to have
                                                 coordinates or growth data yet. --}}
                                            <a href="{{ route('churches.show', auth()->user()->church) }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                                <x-icon name="building-office" class="h-4 w-4 shrink-0 text-slate-400" />
                                                Gereja Saya
                                            </a>
                                        @endif
                                        @if (auth()->user()->union && auth()->user()->can('update', auth()->user()->union))
                                            {{-- Same gap as Gereja Saya, for admin_uni: Kelola Akun hides
                                                 the Uni tab from admin_uni themselves (each level skips editing
                                                 its own entity's identity there), so this is their only path to
                                                 their own Union's Kelola Akun. can:update (not just union_id
                                                 being set) keeps this from dead-ending for pimpinan_uni. --}}
                                            <a href="{{ route('admin.unions.socials.index', auth()->user()->union) }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                                <x-icon name="building-office" class="h-4 w-4 shrink-0 text-slate-400" />
                                                Uni Saya
                                            </a>
                                        @endif
                                        @if (auth()->user()->conference && auth()->user()->can('update', auth()->user()->conference))
                                            <a href="{{ route('admin.conferences.socials.index', auth()->user()->conference) }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                                <x-icon name="building-office" class="h-4 w-4 shrink-0 text-slate-400" />
                                                Daerah Saya
                                            </a>
                                        @endif
                                        @if (auth()->user()->institution)
                                            {{-- Same as Gereja Saya: goes to the read-only institutions.show page (not
                                                 straight to Kelola Akun), presence-only gated since pimpinan_institusi
                                                 should see it too — just read-only, same as churches.show. --}}
                                            <a href="{{ route('institutions.show', auth()->user()->institution) }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                                <x-icon name="building-office" class="h-4 w-4 shrink-0 text-slate-400" />
                                                Institusi Saya
                                            </a>
                                        @endif

                                        @canany(['manage-queue', 'manage-settings', 'manage-goals', 'delegate-users', 'browse-directory-analytics', 'manage-people', 'view-audit-log'])
                                            <div class="my-1 border-t border-black/5 dark:border-white/5"></div>
                                            @can('manage-queue')
                                                <a href="{{ route('queue.index') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                                    <x-icon name="queue-list" class="h-4 w-4 shrink-0 text-slate-400" />
                                                    {{ __('nav.queue') }}
                                                </a>
                                            @endcan
                                            @can('manage-goals')
                                                <a href="{{ route('goals.edit') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                                    <x-icon name="flag" class="h-4 w-4 shrink-0 text-slate-400" />
                                                    {{ __('nav.manage_goals') }}
                                                </a>
                                            @endcan
                                            @can('delegate-users')
                                                <a href="{{ route('admin.users.index') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                                    <x-icon name="users" class="h-4 w-4 shrink-0 text-slate-400" />
                                                    {{ __('nav.manage_users') }}
                                                </a>
                                            @endcan
                                            {{-- manage-people is the gate for the merged Kelola Akun page (Uni/Daerah/
                                                 Gereja/Institusi/Personal tabs) — it's the broader of the two gates that
                                                 used to guard this as two separate links (manage-hierarchy excludes
                                                 gereja-level; manage-people doesn't), so gating the single merged link
                                                 on it alone covers everyone who could reach either half before. --}}
                                            @can('manage-people')
                                                <a href="{{ route('admin.accounts.index') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                                    <x-icon name="building-office" class="h-4 w-4 shrink-0 text-slate-400" />
                                                    {{ __('nav.manage_accounts') }}
                                                </a>
                                            @endcan
                                            @can('view-audit-log')
                                                <a href="{{ route('admin.audit-log.index') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                                    <x-icon name="clock" class="h-4 w-4 shrink-0 text-slate-400" />
                                                    {{ __('audit.title') }}
                                                </a>
                                            @endcan
                                            @can('manage-settings')
                                                <a href="{{ route('settings.edit') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                                    <x-icon name="cog-6-tooth" class="h-4 w-4 shrink-0 text-slate-400" />
                                                    {{ __('nav.settings') }}
                                                </a>
                                            @endcan
                                        @endcanany

                                        <div class="my-1 border-t border-black/5 dark:border-white/5"></div>
                                        <a href="{{ route('about') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                            <x-icon name="information-circle" class="h-4 w-4 shrink-0 text-slate-400" />
                                            {{ __('nav.about') }}
                                        </a>
                                        <form method="POST" action="{{ route('logout') }}">
                                            @csrf
                                            <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                                <x-icon name="arrow-right-on-rectangle" class="h-4 w-4 shrink-0 text-slate-400" />
                                                {{ __('nav.logout') }}
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @else
                                <div class="flex items-center gap-4">
                                    <a href="{{ route('login') }}" class="text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
                                        {{ __('nav.login') }}
                                    </a>
                                    <a href="{{ route('register') }}" class="text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
                                        {{ __('nav.register') }}
                                    </a>
                                </div>
                            @endauth
                        </div>

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
                        @can('view-directory')
                            <a href="{{ route('churches.directory') }}" class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                {{ __('nav.directory') }}
                            </a>
                        @endcan
                        @can('view-analytics')
                            <a href="{{ route('churches.analytics') }}" class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                {{ __('nav.analytics') }}
                            </a>
                        @endcan
                        <a href="{{ route('churches.presentation') }}" target="_blank" class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                            {{ __('nav.presentation') }}
                        </a>
                        @auth
                            <div class="mt-2 border-t border-black/5 pt-3 dark:border-white/5">
                                <div class="px-3 pb-2">
                                    <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ auth()->user()->name }}</p>
                                    <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">{{ auth()->user()->email }}</p>
                                </div>
                                <a href="{{ route('profile.edit') }}" class="flex items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                    <span class="flex items-center gap-2">
                                        <x-icon name="user" class="h-4 w-4 shrink-0 text-slate-400" />
                                        Profil Saya
                                    </span>
                                    <x-role-badge :role="auth()->user()->role" />
                                </a>
                                @if (auth()->user()->church_id)
                                    <a href="{{ route('churches.show', auth()->user()->church) }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                        <x-icon name="building-office" class="h-4 w-4 shrink-0 text-slate-400" />
                                        Gereja Saya
                                    </a>
                                @endif
                                @if (auth()->user()->union && auth()->user()->can('update', auth()->user()->union))
                                    <a href="{{ route('admin.unions.socials.index', auth()->user()->union) }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                        <x-icon name="building-office" class="h-4 w-4 shrink-0 text-slate-400" />
                                        Uni Saya
                                    </a>
                                @endif
                                @if (auth()->user()->conference && auth()->user()->can('update', auth()->user()->conference))
                                    <a href="{{ route('admin.conferences.socials.index', auth()->user()->conference) }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                        <x-icon name="building-office" class="h-4 w-4 shrink-0 text-slate-400" />
                                        Daerah Saya
                                    </a>
                                @endif
                                @if (auth()->user()->institution)
                                    <a href="{{ route('institutions.show', auth()->user()->institution) }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                        <x-icon name="building-office" class="h-4 w-4 shrink-0 text-slate-400" />
                                        Institusi Saya
                                    </a>
                                @endif

                                @canany(['manage-queue', 'manage-settings', 'manage-goals', 'delegate-users', 'browse-directory-analytics', 'manage-people', 'view-audit-log'])
                                    <div class="my-1 border-t border-black/5 dark:border-white/5"></div>
                                    @can('manage-queue')
                                        <a href="{{ route('queue.index') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                            <x-icon name="queue-list" class="h-4 w-4 shrink-0 text-slate-400" />
                                            {{ __('nav.queue') }}
                                        </a>
                                    @endcan
                                    @can('manage-goals')
                                        <a href="{{ route('goals.edit') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                            <x-icon name="flag" class="h-4 w-4 shrink-0 text-slate-400" />
                                            {{ __('nav.manage_goals') }}
                                        </a>
                                    @endcan
                                    @can('delegate-users')
                                        <a href="{{ route('admin.users.index') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                            <x-icon name="users" class="h-4 w-4 shrink-0 text-slate-400" />
                                            {{ __('nav.manage_users') }}
                                        </a>
                                    @endcan
                                    @can('manage-people')
                                        <a href="{{ route('admin.accounts.index') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                            <x-icon name="building-office" class="h-4 w-4 shrink-0 text-slate-400" />
                                            {{ __('nav.manage_accounts') }}
                                        </a>
                                    @endcan
                                    @can('view-audit-log')
                                        <a href="{{ route('admin.audit-log.index') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                            <x-icon name="clock" class="h-4 w-4 shrink-0 text-slate-400" />
                                            {{ __('audit.title') }}
                                        </a>
                                    @endcan
                                    @can('manage-settings')
                                        <a href="{{ route('settings.edit') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                            <x-icon name="cog-6-tooth" class="h-4 w-4 shrink-0 text-slate-400" />
                                            {{ __('nav.settings') }}
                                        </a>
                                    @endcan
                                @endcanany

                                <div class="my-1 border-t border-black/5 dark:border-white/5"></div>
                                <a href="{{ route('about') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                    <x-icon name="information-circle" class="h-4 w-4 shrink-0 text-slate-400" />
                                    {{ __('nav.about') }}
                                </a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                        <x-icon name="arrow-right-on-rectangle" class="h-4 w-4 shrink-0 text-slate-400" />
                                        {{ __('nav.logout') }}
                                    </button>
                                </form>
                            </div>
                        @else
                            <a href="{{ route('login') }}" class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                {{ __('nav.login') }}
                            </a>
                            <a href="{{ route('register') }}" class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                {{ __('nav.register') }}
                            </a>
                        @endauth
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

        @include('partials.footer')

        @include('partials.confirm-dialog')

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
                var wrapper = document.querySelector('[data-account-menu]');
                if (! wrapper) return;

                var toggle = wrapper.querySelector('[data-account-menu-toggle]');
                var panel = wrapper.querySelector('[data-account-menu-panel]');

                toggle.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var isOpen = panel.classList.toggle('hidden') === false;
                    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                });

                document.addEventListener('click', function (e) {
                    if (! panel.classList.contains('hidden') && ! wrapper.contains(e.target)) {
                        panel.classList.add('hidden');
                        toggle.setAttribute('aria-expanded', 'false');
                    }
                });

                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape' && ! panel.classList.contains('hidden')) {
                        panel.classList.add('hidden');
                        toggle.setAttribute('aria-expanded', 'false');
                    }
                });
            })();

            (function () {
                var STORAGE_KEY = 'hopenalytics.refreshBatch';
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

        @include('partials.disable-on-submit')
        @include('partials.password-toggle')
    </body>
</html>
