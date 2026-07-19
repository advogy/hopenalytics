@props(['sort', 'deltaUrl', 'valueUrl'])

<div class="flex items-center gap-2 text-sm">
    <span class="text-slate-400 dark:text-slate-500">{{ __('common.sort_by') }}</span>
    <a
        href="{{ $deltaUrl }}"
        class="rounded-full border px-3 py-1.5 font-medium transition {{ $sort === 'delta' ? 'border-blue-600 bg-blue-600 text-white' : 'border-black/10 bg-white text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700' }}"
    >
        {{ __('common.sort_weekly_growth') }}
    </a>
    <a
        href="{{ $valueUrl }}"
        class="rounded-full border px-3 py-1.5 font-medium transition {{ $sort === 'value' ? 'border-blue-600 bg-blue-600 text-white' : 'border-black/10 bg-white text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700' }}"
    >
        {{ __('common.sort_current_value') }}
    </a>
</div>
