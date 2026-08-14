{{--
    The "Total Reach Saat Ini" hero card at the top of each churches/analytics.blade.php tab panel
    (Organisasi/Gereja/Institusi/Personal) — identical shape across all four, just fed a
    differently-scoped $value/$growthPercent per tab.
--}}
@props(['value', 'growthPercent'])

<div class="mb-6 flex flex-wrap items-center gap-6 rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
    <span class="inline-flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 shadow-sm dark:bg-blue-950/50 dark:text-blue-300">
        <x-icon name="arrow-trending-up" class="h-7 w-7" />
    </span>

    <div class="min-w-[180px]">
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('analytics.total_reach_current') }}</p>
        <p class="text-3xl font-bold tabular-nums text-slate-900 dark:text-white">
            {{ number_format($value) }}
        </p>
    </div>

    @if ($growthPercent !== null)
        <div class="flex items-center gap-2 rounded-full border px-4 py-2 {{ $growthPercent >= 0 ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950' : 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950' }}">
            <x-icon :name="$growthPercent > 0 ? 'arrow-trending-up' : ($growthPercent < 0 ? 'arrow-trending-down' : 'minus-small')" class="h-4 w-4 {{ $growthPercent >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}" />
            <span class="text-sm font-semibold tabular-nums {{ $growthPercent >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400' }}">
                {{ $growthPercent > 0 ? '+' : '' }}{{ number_format($growthPercent, 2) }}%
            </span>
            <span class="text-xs text-slate-400 dark:text-slate-500">{{ __('common.this_week') }}</span>
        </div>
    @endif
</div>
