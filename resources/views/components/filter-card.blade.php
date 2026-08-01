{{--
    Shared "Filters" card wrapper — one consistent look for every filter form across Analitik &
    Grafik, Perbandingan Metrik, Perbandingan Platform, and Kelola Akun: a bordered card with a
    bold heading, around whatever filter fields the caller slots in (region/entity/platform/
    date-range partials, each their own rounded-full pill with a leading icon).

    $clearUrl (optional): shows a "Clear All" link in the header when at least one filter on the
    page is active — the caller decides that (typically `$hasActiveFilters ? route(...) : null`),
    since only it knows which of its own fields count as "active".
--}}
@props(['clearUrl' => null])

<div class="mb-6 rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
    <div class="mb-4 flex items-center justify-between gap-3">
        <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('common.filter') }}</h2>
        @if ($clearUrl)
            <a href="{{ $clearUrl }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 hover:text-blue-700 dark:text-blue-400">
                <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-blue-600 text-white">
                    <x-icon name="x-mark" class="h-2.5 w-2.5" />
                </span>
                {{ __('common.reset_filter') }}
            </a>
        @endif
    </div>
    {{ $slot }}
</div>
