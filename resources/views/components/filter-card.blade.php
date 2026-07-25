{{--
    Shared "Filters" card wrapper — one consistent look for every filter form across Analitik &
    Grafik, Perbandingan Metrik, and Perbandingan Platform: a bordered card with a bold heading,
    around whatever filter fields the caller slots in (region/entity/platform/date-range
    partials, each their own rounded-full pill with a leading icon).
--}}
<div class="mb-6 rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
    <h2 class="mb-4 text-lg font-bold text-slate-900 dark:text-white">{{ __('common.filter') }}</h2>
    {{ $slot }}
</div>
