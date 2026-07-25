@props(['label', 'year', 'target', 'current', 'percent'])

<div class="flex flex-wrap items-center gap-4 rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900 sm:flex-nowrap">
    <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 shadow-sm dark:bg-blue-950/50 dark:text-blue-300">
        <x-icon name="flag" class="h-5 w-5" />
    </span>

    <div class="shrink-0">
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('goals.goal_title', ['year' => $year, 'metric' => $label]) }}</p>
        <p class="text-2xl font-bold tabular-nums text-slate-900 dark:text-white">{{ number_format($target) }}</p>
    </div>

    <div class="relative h-8 min-w-[8rem] flex-1">
        <div class="absolute inset-y-0 left-0 right-0 top-1/2 h-3 -translate-y-1/2 rounded-full bg-slate-100 dark:bg-slate-800"></div>
        <div
            class="absolute inset-y-0 left-0 top-1/2 flex h-3 -translate-y-1/2 items-center justify-end rounded-full bg-blue-600 pr-2"
            style="width: max(2.5rem, {{ $percent }}%)"
        >
            <span class="text-xs font-semibold text-white">{{ $percent }}%</span>
        </div>
    </div>

    <p class="shrink-0 text-2xl font-bold tabular-nums text-slate-900 dark:text-white">{{ number_format($current) }}</p>
</div>
