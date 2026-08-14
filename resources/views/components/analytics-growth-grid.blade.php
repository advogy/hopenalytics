{{--
    The 4-chart Reach/Views/Likes/Posts growth-over-time grid below each churches/analytics.blade.php
    tab panel's hero KPI — identical shape across all four tabs, just fed a differently-scoped
    $growthOverTime/$growthLabels/subtitle set per tab.
--}}
@props(['growthOverTime', 'growthLabels', 'reachSubtitle', 'viewsSubtitle', 'likesSubtitle', 'postsSubtitle'])

<div class="mb-8 grid gap-6 sm:grid-cols-2">
    <div class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <p class="font-bold text-slate-900 dark:text-white">{{ __('analytics.growth_reach_title') }}</p>
        <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">{{ $reachSubtitle }}{{ __('analytics.per_week') }}</p>
        <x-growth-chart :values="$growthOverTime->pluck('total_reach')" :labels="$growthLabels" />
    </div>

    <div class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <p class="font-bold text-slate-900 dark:text-white">{{ __('analytics.growth_views_title') }}</p>
        <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">{{ $viewsSubtitle }}{{ __('analytics.per_week') }}</p>
        <x-growth-chart :values="$growthOverTime->pluck('total_views')" :labels="$growthLabels" />
    </div>

    <div class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <p class="font-bold text-slate-900 dark:text-white">{{ __('analytics.growth_likes_title') }}</p>
        <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">{{ $likesSubtitle }}{{ __('analytics.per_week') }}</p>
        <x-growth-chart :values="$growthOverTime->pluck('total_likes')" :labels="$growthLabels" />
    </div>

    <div class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <p class="font-bold text-slate-900 dark:text-white">{{ __('analytics.growth_posts_title') }}</p>
        <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">{{ $postsSubtitle }}{{ __('analytics.per_week') }}</p>
        <x-growth-chart :values="$growthOverTime->pluck('total_posts')" :labels="$growthLabels" />
    </div>
</div>
