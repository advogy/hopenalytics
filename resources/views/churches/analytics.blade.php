@php
    $countField = ['youtube' => 'subscribers_count', 'instagram' => 'followers_count', 'tiktok' => 'followers_count', 'facebook' => 'followers_count'];
    $postField = ['youtube' => 'videos_count', 'instagram' => 'posts_count', 'tiktok' => 'posts_count'];
    $platformLabels = ['youtube' => 'YouTube', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook'];

    $activeTab = in_array(request()->query('tab'), ['personal', 'institusi'], true) ? request()->query('tab') : 'gereja';

    $selectedPlatformLabel = $selectedPlatform ? ($platformLabels[$selectedPlatform] ?? null) : null;

    // Gereja tab
    $selectedChurchName = $selectedChurchId ? $churches->firstWhere('id', (int) $selectedChurchId)?->name : null;
    $churchSuffix = $selectedChurchName ? __('analytics.suffix_entity', ['name' => $selectedChurchName]) : __('analytics.suffix_all_churches');

    $reachSubtitle = $selectedPlatform
        ? ($selectedPlatform === 'youtube' ? __('analytics.reach_subtitle_youtube') : __('analytics.reach_subtitle_platform', ['platform' => $selectedPlatformLabel]))
        : __('analytics.reach_subtitle_combined');
    $reachSubtitle .= $churchSuffix;

    $viewsSubtitle = __('analytics.views_subtitle');
    $viewsSubtitle .= ($selectedPlatform && $selectedPlatform !== 'youtube') ? __('analytics.not_available_platform') : '';
    $viewsSubtitle .= $churchSuffix;

    $likesSubtitle = __('analytics.likes_subtitle');
    $likesSubtitle .= ($selectedPlatform && $selectedPlatform !== 'tiktok') ? __('analytics.not_available_platform') : '';
    $likesSubtitle .= $churchSuffix;

    $postsSubtitle = __('analytics.posts_subtitle');
    $postsSubtitle .= ($selectedPlatform === 'facebook') ? __('analytics.not_available_platform') : '';
    $postsSubtitle .= $churchSuffix;

    $growthLabels = $growthOverTime->pluck('recorded_at')->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->translatedFormat('d M'));

    $latestPoint = $growthOverTime->last();
    $previousPoint = $growthOverTime->count() >= 2 ? $growthOverTime[$growthOverTime->count() - 2] : null;
    $reachGrowthPercent = null;
    if ($latestPoint && $previousPoint && $previousPoint->total_reach > 0) {
        $reachGrowthPercent = round((($latestPoint->total_reach - $previousPoint->total_reach) / $previousPoint->total_reach) * 100, 2);
    }

    // Personal tab
    $selectedPersonName = $selectedPersonId ? $people->firstWhere('id', (int) $selectedPersonId)?->name : null;
    $personSuffix = $selectedPersonName ? __('analytics.suffix_entity', ['name' => $selectedPersonName]) : __('analytics.suffix_all_personal');

    $reachSubtitlePersonal = $selectedPlatform
        ? ($selectedPlatform === 'youtube' ? __('analytics.reach_subtitle_youtube') : __('analytics.reach_subtitle_platform', ['platform' => $selectedPlatformLabel]))
        : __('analytics.reach_subtitle_combined');
    $reachSubtitlePersonal .= $personSuffix;

    $viewsSubtitlePersonal = __('analytics.views_subtitle');
    $viewsSubtitlePersonal .= ($selectedPlatform && $selectedPlatform !== 'youtube') ? __('analytics.not_available_platform') : '';
    $viewsSubtitlePersonal .= $personSuffix;

    $likesSubtitlePersonal = __('analytics.likes_subtitle');
    $likesSubtitlePersonal .= ($selectedPlatform && $selectedPlatform !== 'tiktok') ? __('analytics.not_available_platform') : '';
    $likesSubtitlePersonal .= $personSuffix;

    $postsSubtitlePersonal = __('analytics.posts_subtitle');
    $postsSubtitlePersonal .= ($selectedPlatform === 'facebook') ? __('analytics.not_available_platform') : '';
    $postsSubtitlePersonal .= $personSuffix;

    $growthLabelsPersonal = $growthOverTimePersonal->pluck('recorded_at')->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->translatedFormat('d M'));

    $latestPointPersonal = $growthOverTimePersonal->last();
    $previousPointPersonal = $growthOverTimePersonal->count() >= 2 ? $growthOverTimePersonal[$growthOverTimePersonal->count() - 2] : null;
    $reachGrowthPercentPersonal = null;
    if ($latestPointPersonal && $previousPointPersonal && $previousPointPersonal->total_reach > 0) {
        $reachGrowthPercentPersonal = round((($latestPointPersonal->total_reach - $previousPointPersonal->total_reach) / $previousPointPersonal->total_reach) * 100, 2);
    }

    // Institusi tab
    $selectedInstitutionName = $selectedInstitutionId ? $institutions->firstWhere('id', (int) $selectedInstitutionId)?->name : null;
    $institutionSuffix = $selectedInstitutionName ? __('analytics.suffix_entity', ['name' => $selectedInstitutionName]) : __('analytics.suffix_all_institutions');

    $reachSubtitleInstitution = $selectedPlatform
        ? ($selectedPlatform === 'youtube' ? __('analytics.reach_subtitle_youtube') : __('analytics.reach_subtitle_platform', ['platform' => $selectedPlatformLabel]))
        : __('analytics.reach_subtitle_combined');
    $reachSubtitleInstitution .= $institutionSuffix;

    $viewsSubtitleInstitution = __('analytics.views_subtitle');
    $viewsSubtitleInstitution .= ($selectedPlatform && $selectedPlatform !== 'youtube') ? __('analytics.not_available_platform') : '';
    $viewsSubtitleInstitution .= $institutionSuffix;

    $likesSubtitleInstitution = __('analytics.likes_subtitle');
    $likesSubtitleInstitution .= ($selectedPlatform && $selectedPlatform !== 'tiktok') ? __('analytics.not_available_platform') : '';
    $likesSubtitleInstitution .= $institutionSuffix;

    $postsSubtitleInstitution = __('analytics.posts_subtitle');
    $postsSubtitleInstitution .= ($selectedPlatform === 'facebook') ? __('analytics.not_available_platform') : '';
    $postsSubtitleInstitution .= $institutionSuffix;

    $growthLabelsInstitution = $growthOverTimeInstitution->pluck('recorded_at')->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->translatedFormat('d M'));

    $latestPointInstitution = $growthOverTimeInstitution->last();
    $previousPointInstitution = $growthOverTimeInstitution->count() >= 2 ? $growthOverTimeInstitution[$growthOverTimeInstitution->count() - 2] : null;
    $reachGrowthPercentInstitution = null;
    if ($latestPointInstitution && $previousPointInstitution && $previousPointInstitution->total_reach > 0) {
        $reachGrowthPercentInstitution = round((($latestPointInstitution->total_reach - $previousPointInstitution->total_reach) / $previousPointInstitution->total_reach) * 100, 2);
    }
@endphp

@extends('layouts.app')

@section('title', __('nav.analytics') . ' — ' . config('app.name'))

@section('content')
    <a href="{{ route('churches.index') }}" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
        &larr; {{ __('common.back_to_dashboard') }}
    </a>

    <div class="mb-6">
        <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('nav.analytics') }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('analytics.subtitle') }}</p>
    </div>

    <div class="mb-6 flex gap-2 border-b border-black/5 dark:border-white/5">
        <button
            type="button"
            data-tab-button="gereja"
            class="border-b-2 px-4 py-2.5 text-sm font-medium transition"
        >
            {{ __('common.church') }}
        </button>
        <button
            type="button"
            data-tab-button="institusi"
            class="border-b-2 px-4 py-2.5 text-sm font-medium transition"
        >
            {{ __('common.institution') }}
        </button>
        <button
            type="button"
            data-tab-button="personal"
            class="border-b-2 px-4 py-2.5 text-sm font-medium transition"
        >
            {{ __('common.personal') }}
        </button>
    </div>

    {{-- ===================== TAB: GEREJA ===================== --}}
    <div data-tab-panel="gereja">
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-center gap-3">
                <a
                    href="{{ route('churches.metric-comparison') }}"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-black/10 bg-white px-4 py-2 text-sm font-medium shadow-sm transition hover:bg-slate-50 dark:border-white/10 dark:bg-slate-800 dark:hover:bg-slate-700"
                >
                    <x-icon name="globe-alt" class="h-4 w-4" />
                    {{ __('analytics.metric_comparison_church') }}
                </a>
                <a
                    href="{{ route('churches.platform-comparison', $selectedPlatform ?: null) }}"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-black/10 bg-white px-4 py-2 text-sm font-medium shadow-sm transition hover:bg-slate-50 dark:border-white/10 dark:bg-slate-800 dark:hover:bg-slate-700"
                >
                    <x-icon name="arrow-trending-up" class="h-4 w-4" />
                    {{ __('analytics.platform_comparison_church') }}
                </a>
            </div>
            @can('browse-directory-analytics')
                <x-export-button :url="route('export.analytics.preview', array_filter(['church_id' => $selectedChurchId, 'platform' => $selectedPlatform]))" />
            @endcan
        </div>

        {{-- Filters --}}
        <div class="mb-6 rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
            <h2 class="mb-4 text-lg font-bold text-slate-900 dark:text-white">{{ __('common.filter') }}</h2>
            <form method="GET" class="flex flex-wrap items-center gap-3">
                <input type="hidden" name="tab" value="gereja">

                <label class="relative">
                    <x-icon name="building-office" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <select
                        name="church_id"
                        onchange="this.form.submit()"
                        class="appearance-none rounded-full border border-black/10 bg-slate-50 py-2.5 pr-10 pl-9 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-100 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                    >
                        <option value="">{{ __('analytics.all_churches') }}</option>
                        @foreach ($churches as $church)
                            <option value="{{ $church->id }}" @selected((int) $selectedChurchId === $church->id)>{{ $church->name }}</option>
                        @endforeach
                    </select>
                    <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                </label>

                <label class="relative">
                    <x-icon name="globe-alt" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <select
                        name="platform"
                        onchange="this.form.submit()"
                        class="appearance-none rounded-full border border-black/10 bg-slate-50 py-2.5 pr-10 pl-9 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-100 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                    >
                        <option value="">{{ __('common.all_social_media') }}</option>
                        @foreach ($platformLabels as $value => $label)
                            <option value="{{ $value }}" @selected($selectedPlatform === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                </label>

                @if ($selectedChurchId || $selectedPlatform)
                    <a href="{{ route('churches.analytics', ['tab' => 'gereja']) }}" class="text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
                        {{ __('common.reset_filter') }}
                    </a>
                @endif
            </form>
        </div>

        {{-- Hero KPI --}}
        <div class="mb-6 flex flex-wrap items-center gap-6 rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
            <span class="inline-flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 shadow-sm dark:bg-blue-950/50 dark:text-blue-300">
                <x-icon name="arrow-trending-up" class="h-7 w-7" />
            </span>

            <div class="min-w-[180px]">
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('analytics.total_reach_current') }}</p>
                <p class="text-3xl font-bold tabular-nums text-slate-900 dark:text-white">
                    {{ number_format($latestPoint->total_reach ?? 0) }}
                </p>
            </div>

            @if ($reachGrowthPercent !== null)
                <div class="flex items-center gap-2 rounded-full border px-4 py-2 {{ $reachGrowthPercent >= 0 ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950' : 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950' }}">
                    <x-icon :name="$reachGrowthPercent > 0 ? 'arrow-trending-up' : ($reachGrowthPercent < 0 ? 'arrow-trending-down' : 'minus-small')" class="h-4 w-4 {{ $reachGrowthPercent >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}" />
                    <span class="text-sm font-semibold tabular-nums {{ $reachGrowthPercent >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400' }}">
                        {{ $reachGrowthPercent > 0 ? '+' : '' }}{{ number_format($reachGrowthPercent, 2) }}%
                    </span>
                    <span class="text-xs text-slate-400 dark:text-slate-500">{{ __('common.this_week') }}</span>
                </div>
            @endif
        </div>

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

        @if ($filteredChurches->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-300 p-12 text-center dark:border-slate-700">
                <p class="text-slate-500 dark:text-slate-400">
                    {{ $churches->isEmpty() ? __('analytics.no_church_data') : __('analytics.no_church_match_filter') }}
                </p>
            </div>
        @else
            @php
                $allDisplaySocials = $filteredChurches->flatMap(
                    fn ($church) => $selectedPlatform
                        ? $church->socials->filter(fn ($s) => $s->platform->value === $selectedPlatform)
                        : $church->socials
                );
                $grandByCategory = collect(['gereja', 'umum'])->mapWithKeys(
                    fn ($category) => [
                        $category => $allDisplaySocials
                            ->filter(fn ($s) => $s->category->value === $category)
                            ->groupBy(fn ($s) => $s->platform->value),
                    ]
                );
                $grandReach = $allDisplaySocials->sum(fn ($s) => $s->latestStat?->{$countField[$s->platform->value]} ?? 0);
                $grandViews = $allDisplaySocials->sum(fn ($s) => $s->latestStat?->views_count ?? 0);
                $grandLikes = $allDisplaySocials->sum(fn ($s) => $s->latestStat?->likes_count ?? 0);
                $grandPosts = $allDisplaySocials->sum(
                    fn ($s) => isset($postField[$s->platform->value]) ? ($s->latestStat?->{$postField[$s->platform->value]} ?? 0) : 0
                );

                $churchRows = $filteredChurches->map(function ($church) use ($selectedPlatform, $countField, $postField) {
                    $displaySocials = $selectedPlatform
                        ? $church->socials->filter(fn ($s) => $s->platform->value === $selectedPlatform)
                        : $church->socials;

                    return [
                        'church' => $church,
                        'socialsByCategory' => $displaySocials->groupBy(fn ($s) => $s->category->value),
                        'reach' => $displaySocials->sum(fn ($s) => $s->latestStat?->{$countField[$s->platform->value]} ?? 0),
                        'views' => $displaySocials->sum(fn ($s) => $s->latestStat?->views_count ?? 0),
                        'likes' => $displaySocials->sum(fn ($s) => $s->latestStat?->likes_count ?? 0),
                        'posts' => $displaySocials->sum(
                            fn ($s) => isset($postField[$s->platform->value]) ? ($s->latestStat?->{$postField[$s->platform->value]} ?? 0) : 0
                        ),
                    ];
                });

                $maxChurchReach = $churchRows->max('reach') ?: 1;
            @endphp

            <div class="mb-6 overflow-x-auto rounded-2xl border border-black/5 dark:border-white/5">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800/60">
                        <tr>
                            <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('analytics.grand_total') }}</th>
                            <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('directory.church_accounts') }}</th>
                            <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('directory.general_accounts') }}</th>
                            <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('analytics.total_reach') }}</th>
                            <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('analytics.total_views') }}</th>
                            <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('analytics.total_likes') }}</th>
                            <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('analytics.total_posts') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-900">
                        <tr>
                            <td class="px-4 py-3 font-semibold">{{ __('analytics.churches_count', ['count' => $filteredChurches->count()]) }}</td>
                            @foreach (['gereja', 'umum'] as $category)
                                <td class="px-4 py-3">
                                    @if ($grandByCategory[$category]->isEmpty())
                                        <span class="text-slate-300 dark:text-slate-600">—</span>
                                    @else
                                        <p class="mb-1 text-xs text-slate-400 dark:text-slate-500">{{ __('analytics.account_count') }}</p>
                                        <div class="mb-2 flex flex-wrap gap-1.5">
                                            @foreach ($grandByCategory[$category] as $platformValue => $group)
                                                <span class="inline-flex items-center gap-1.5 rounded-full border border-black/5 bg-slate-50 py-1 pr-2.5 pl-1 dark:border-white/5 dark:bg-slate-800">
                                                    <x-platform-icon :platform="$platformValue" class="h-4.5 w-4.5" />
                                                    <span class="font-semibold tabular-nums">{{ $group->count() }}</span>
                                                </span>
                                            @endforeach
                                        </div>

                                        <p class="mb-1 text-xs text-slate-400 dark:text-slate-500">{{ __('analytics.total_followers_subscribers') }}</p>
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach ($grandByCategory[$category] as $platformValue => $group)
                                                <span class="inline-flex items-center gap-1.5 rounded-full border border-black/5 bg-slate-50 py-1 pr-2.5 pl-1 dark:border-white/5 dark:bg-slate-800">
                                                    <x-platform-icon :platform="$platformValue" class="h-4.5 w-4.5" />
                                                    <span class="font-semibold tabular-nums">
                                                        {{ number_format($group->sum(fn ($s) => $s->latestStat?->{$countField[$platformValue]} ?? 0)) }}
                                                    </span>
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                            @endforeach
                            <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ number_format($grandReach) }}</td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ $grandViews ? number_format($grandViews) : '—' }}</td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ $grandLikes ? number_format($grandLikes) : '—' }}</td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ $grandPosts ? number_format($grandPosts) : '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="rounded-2xl border border-black/5 bg-white shadow-sm dark:border-white/5 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('analytics.data_per_church') }}</h2>
                    <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input type="checkbox" id="hide-empty-churches" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800">
                        {{ __('analytics.hide_empty_churches') }}
                    </label>
                </div>

                <div class="overflow-x-auto border-t border-black/5 dark:border-white/5">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-800/60">
                            <tr>
                                <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('common.church') }}</th>
                                <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('directory.church_accounts') }}</th>
                                <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('directory.general_accounts') }}</th>
                                <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('analytics.total_reach') }}</th>
                                <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">Views</th>
                                <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">Likes</th>
                                <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">Post / Video</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                            @foreach ($churchRows as $row)
                                @php
                                    $church = $row['church'];
                                    $percent = $maxChurchReach > 0 ? round($row['reach'] / $maxChurchReach * 100, 1) : 0;
                                    $isEmpty = $row['reach'] == 0 && $row['views'] == 0 && $row['likes'] == 0 && $row['posts'] == 0;
                                @endphp
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40" data-church-row @if ($isEmpty) data-empty-row @endif>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#f7cd9a] text-xs font-bold text-blue-600 dark:bg-violet-950/60 dark:text-[#f7cd9a]">
                                                {{ mb_substr($church->name, 0, 1) }}
                                            </span>
                                            <div class="min-w-0">
                                                <a href="{{ route('churches.show', $church) }}" class="font-medium hover:text-blue-600 dark:hover:text-blue-400">
                                                    {{ $church->name }}
                                                </a>
                                                @if ($church->city)
                                                    <p class="text-xs text-slate-400 dark:text-slate-500">{{ $church->city }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    @foreach (['gereja', 'umum'] as $category)
                                        <td class="px-4 py-3">
                                            <div class="flex flex-wrap gap-1.5">
                                                @forelse ($row['socialsByCategory']->get($category, collect()) as $social)
                                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-black/5 bg-slate-50 py-1 pr-2.5 pl-1 dark:border-white/5 dark:bg-slate-800">
                                                        <x-platform-icon :platform="$social->platform" class="h-4.5 w-4.5" />
                                                        <span class="font-medium tabular-nums">
                                                            {{ number_format($social->latestStat?->{$countField[$social->platform->value]} ?? 0) }}
                                                        </span>
                                                    </span>
                                                @empty
                                                    <span class="text-slate-300 dark:text-slate-600">—</span>
                                                @endforelse
                                            </div>
                                        </td>
                                    @endforeach
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <span class="w-16 shrink-0 text-right font-semibold tabular-nums">{{ number_format($row['reach']) }}</span>
                                            <div class="relative h-1.5 w-32 shrink-0 rounded-full bg-slate-100 dark:bg-slate-700">
                                                <div class="h-1.5 rounded-full bg-blue-500 dark:bg-blue-400" style="width: {{ $percent }}%"></div>
                                                <span class="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 h-2.5 w-2.5 rounded-full border-2 border-white bg-blue-600 shadow dark:border-slate-900" style="left: {{ $percent }}%"></span>
                                            </div>
                                            <span class="w-10 shrink-0 text-xs text-slate-400 dark:text-slate-500">{{ $percent }}%</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ $row['views'] ? number_format($row['views']) : '—' }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ $row['likes'] ? number_format($row['likes']) : '—' }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ $row['posts'] ? number_format($row['posts']) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <script>
                (function () {
                    var checkbox = document.getElementById('hide-empty-churches');
                    if (! checkbox) return;

                    checkbox.addEventListener('change', function () {
                        document.querySelectorAll('[data-church-row]').forEach(function (row) {
                            var isEmpty = row.hasAttribute('data-empty-row');
                            row.classList.toggle('hidden', checkbox.checked && isEmpty);
                        });
                    });
                })();
            </script>
        @endif
    </div>

    {{-- ===================== TAB: PERSONAL ===================== --}}
    <div data-tab-panel="personal">
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-center gap-3">
                <a
                    href="{{ route('people.metric-comparison') }}"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-black/10 bg-white px-4 py-2 text-sm font-medium shadow-sm transition hover:bg-slate-50 dark:border-white/10 dark:bg-slate-800 dark:hover:bg-slate-700"
                >
                    <x-icon name="globe-alt" class="h-4 w-4" />
                    {{ __('analytics.metric_comparison_personal') }}
                </a>
                <a
                    href="{{ route('people.platform-comparison', $selectedPlatform ?: null) }}"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-black/10 bg-white px-4 py-2 text-sm font-medium shadow-sm transition hover:bg-slate-50 dark:border-white/10 dark:bg-slate-800 dark:hover:bg-slate-700"
                >
                    <x-icon name="arrow-trending-up" class="h-4 w-4" />
                    {{ __('analytics.platform_comparison_personal') }}
                </a>
            </div>
            @can('browse-directory-analytics')
                <x-export-button :url="route('export.personal-analytics.preview', array_filter(['person_id' => $selectedPersonId, 'platform' => $selectedPlatform]))" />
            @endcan
        </div>

        {{-- Filters --}}
        <div class="mb-6 rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
            <h2 class="mb-4 text-lg font-bold text-slate-900 dark:text-white">{{ __('common.filter') }}</h2>
            <form method="GET" class="flex flex-wrap items-center gap-3">
                <input type="hidden" name="tab" value="personal">

                <label class="relative">
                    <x-icon name="user" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <select
                        name="person_id"
                        onchange="this.form.submit()"
                        class="appearance-none rounded-full border border-black/10 bg-slate-50 py-2.5 pr-10 pl-9 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-100 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                    >
                        <option value="">{{ __('analytics.all_personal') }}</option>
                        @foreach ($people as $person)
                            <option value="{{ $person->id }}" @selected((int) $selectedPersonId === $person->id)>{{ $person->name }}</option>
                        @endforeach
                    </select>
                    <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                </label>

                <label class="relative">
                    <x-icon name="globe-alt" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <select
                        name="platform"
                        onchange="this.form.submit()"
                        class="appearance-none rounded-full border border-black/10 bg-slate-50 py-2.5 pr-10 pl-9 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-100 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                    >
                        <option value="">{{ __('common.all_social_media') }}</option>
                        @foreach ($platformLabels as $value => $label)
                            <option value="{{ $value }}" @selected($selectedPlatform === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                </label>

                @if ($selectedPersonId || $selectedPlatform)
                    <a href="{{ route('churches.analytics', ['tab' => 'personal']) }}" class="text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
                        {{ __('common.reset_filter') }}
                    </a>
                @endif
            </form>
        </div>

        {{-- Hero KPI --}}
        <div class="mb-6 flex flex-wrap items-center gap-6 rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
            <span class="inline-flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 shadow-sm dark:bg-blue-950/50 dark:text-blue-300">
                <x-icon name="arrow-trending-up" class="h-7 w-7" />
            </span>

            <div class="min-w-[180px]">
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('analytics.total_reach_current') }}</p>
                <p class="text-3xl font-bold tabular-nums text-slate-900 dark:text-white">
                    {{ number_format($latestPointPersonal->total_reach ?? 0) }}
                </p>
            </div>

            @if ($reachGrowthPercentPersonal !== null)
                <div class="flex items-center gap-2 rounded-full border px-4 py-2 {{ $reachGrowthPercentPersonal >= 0 ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950' : 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950' }}">
                    <x-icon :name="$reachGrowthPercentPersonal > 0 ? 'arrow-trending-up' : ($reachGrowthPercentPersonal < 0 ? 'arrow-trending-down' : 'minus-small')" class="h-4 w-4 {{ $reachGrowthPercentPersonal >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}" />
                    <span class="text-sm font-semibold tabular-nums {{ $reachGrowthPercentPersonal >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400' }}">
                        {{ $reachGrowthPercentPersonal > 0 ? '+' : '' }}{{ number_format($reachGrowthPercentPersonal, 2) }}%
                    </span>
                    <span class="text-xs text-slate-400 dark:text-slate-500">{{ __('common.this_week') }}</span>
                </div>
            @endif
        </div>

        <div class="mb-8 grid gap-6 sm:grid-cols-2">
            <div class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
                <p class="font-bold text-slate-900 dark:text-white">{{ __('analytics.growth_reach_title') }}</p>
                <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">{{ $reachSubtitlePersonal }}{{ __('analytics.per_week') }}</p>
                <x-growth-chart :values="$growthOverTimePersonal->pluck('total_reach')" :labels="$growthLabelsPersonal" />
            </div>

            <div class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
                <p class="font-bold text-slate-900 dark:text-white">{{ __('analytics.growth_views_title') }}</p>
                <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">{{ $viewsSubtitlePersonal }}{{ __('analytics.per_week') }}</p>
                <x-growth-chart :values="$growthOverTimePersonal->pluck('total_views')" :labels="$growthLabelsPersonal" />
            </div>

            <div class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
                <p class="font-bold text-slate-900 dark:text-white">{{ __('analytics.growth_likes_title') }}</p>
                <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">{{ $likesSubtitlePersonal }}{{ __('analytics.per_week') }}</p>
                <x-growth-chart :values="$growthOverTimePersonal->pluck('total_likes')" :labels="$growthLabelsPersonal" />
            </div>

            <div class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
                <p class="font-bold text-slate-900 dark:text-white">{{ __('analytics.growth_posts_title') }}</p>
                <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">{{ $postsSubtitlePersonal }}{{ __('analytics.per_week') }}</p>
                <x-growth-chart :values="$growthOverTimePersonal->pluck('total_posts')" :labels="$growthLabelsPersonal" />
            </div>
        </div>

        @if ($filteredPeople->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-300 p-12 text-center dark:border-slate-700">
                <p class="text-slate-500 dark:text-slate-400">
                    {{ $people->isEmpty() ? __('analytics.no_personal_data') : __('analytics.no_personal_match_filter') }}
                </p>
            </div>
        @else
            @php
                $allDisplaySocialsPersonal = $filteredPeople->flatMap(
                    fn ($person) => $selectedPlatform
                        ? $person->socials->filter(fn ($s) => $s->platform->value === $selectedPlatform)
                        : $person->socials
                );
                $grandByPlatformPersonal = $allDisplaySocialsPersonal->groupBy(fn ($s) => $s->platform->value);
                $grandReachPersonal = $allDisplaySocialsPersonal->sum(fn ($s) => $s->latestStat?->{$countField[$s->platform->value]} ?? 0);
                $grandViewsPersonal = $allDisplaySocialsPersonal->sum(fn ($s) => $s->latestStat?->views_count ?? 0);
                $grandLikesPersonal = $allDisplaySocialsPersonal->sum(fn ($s) => $s->latestStat?->likes_count ?? 0);
                $grandPostsPersonal = $allDisplaySocialsPersonal->sum(
                    fn ($s) => isset($postField[$s->platform->value]) ? ($s->latestStat?->{$postField[$s->platform->value]} ?? 0) : 0
                );

                $personRows = $filteredPeople->map(function ($person) use ($selectedPlatform, $countField, $postField) {
                    $displaySocials = $selectedPlatform
                        ? $person->socials->filter(fn ($s) => $s->platform->value === $selectedPlatform)
                        : $person->socials;

                    return [
                        'person' => $person,
                        'socials' => $displaySocials,
                        'reach' => $displaySocials->sum(fn ($s) => $s->latestStat?->{$countField[$s->platform->value]} ?? 0),
                        'views' => $displaySocials->sum(fn ($s) => $s->latestStat?->views_count ?? 0),
                        'likes' => $displaySocials->sum(fn ($s) => $s->latestStat?->likes_count ?? 0),
                        'posts' => $displaySocials->sum(
                            fn ($s) => isset($postField[$s->platform->value]) ? ($s->latestStat?->{$postField[$s->platform->value]} ?? 0) : 0
                        ),
                    ];
                });

                $maxPersonReach = $personRows->max('reach') ?: 1;
            @endphp

            <div class="mb-6 overflow-x-auto rounded-2xl border border-black/5 dark:border-white/5">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800/60">
                        <tr>
                            <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('analytics.grand_total') }}</th>
                            <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('common.account') }}</th>
                            <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('analytics.total_reach') }}</th>
                            <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('analytics.total_views') }}</th>
                            <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('analytics.total_likes') }}</th>
                            <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('analytics.total_posts') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-900">
                        <tr>
                            <td class="px-4 py-3 font-semibold">{{ __('analytics.personal_count', ['count' => $filteredPeople->count()]) }}</td>
                            <td class="px-4 py-3">
                                @if ($grandByPlatformPersonal->isEmpty())
                                    <span class="text-slate-300 dark:text-slate-600">—</span>
                                @else
                                    <p class="mb-1 text-xs text-slate-400 dark:text-slate-500">{{ __('analytics.account_count') }}</p>
                                    <div class="mb-2 flex flex-wrap gap-1.5">
                                        @foreach ($grandByPlatformPersonal as $platformValue => $group)
                                            <span class="inline-flex items-center gap-1.5 rounded-full border border-black/5 bg-slate-50 py-1 pr-2.5 pl-1 dark:border-white/5 dark:bg-slate-800">
                                                <x-platform-icon :platform="$platformValue" class="h-4.5 w-4.5" />
                                                <span class="font-semibold tabular-nums">{{ $group->count() }}</span>
                                            </span>
                                        @endforeach
                                    </div>

                                    <p class="mb-1 text-xs text-slate-400 dark:text-slate-500">{{ __('analytics.total_followers_subscribers') }}</p>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($grandByPlatformPersonal as $platformValue => $group)
                                            <span class="inline-flex items-center gap-1.5 rounded-full border border-black/5 bg-slate-50 py-1 pr-2.5 pl-1 dark:border-white/5 dark:bg-slate-800">
                                                <x-platform-icon :platform="$platformValue" class="h-4.5 w-4.5" />
                                                <span class="font-semibold tabular-nums">
                                                    {{ number_format($group->sum(fn ($s) => $s->latestStat?->{$countField[$platformValue]} ?? 0)) }}
                                                </span>
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ number_format($grandReachPersonal) }}</td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ $grandViewsPersonal ? number_format($grandViewsPersonal) : '—' }}</td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ $grandLikesPersonal ? number_format($grandLikesPersonal) : '—' }}</td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ $grandPostsPersonal ? number_format($grandPostsPersonal) : '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="rounded-2xl border border-black/5 bg-white shadow-sm dark:border-white/5 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('analytics.data_per_personal') }}</h2>
                    <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input type="checkbox" id="hide-empty-people" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800">
                        {{ __('analytics.hide_empty_personal') }}
                    </label>
                </div>

                <div class="overflow-x-auto border-t border-black/5 dark:border-white/5">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-800/60">
                            <tr>
                                <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('common.name') }}</th>
                                <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('common.account') }}</th>
                                <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('analytics.total_reach') }}</th>
                                <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">Views</th>
                                <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">Likes</th>
                                <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">Post / Video</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                            @foreach ($personRows as $row)
                                @php
                                    $person = $row['person'];
                                    $percent = $maxPersonReach > 0 ? round($row['reach'] / $maxPersonReach * 100, 1) : 0;
                                    $isEmpty = $row['reach'] == 0 && $row['views'] == 0 && $row['likes'] == 0 && $row['posts'] == 0;
                                @endphp
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40" data-person-row @if ($isEmpty) data-empty-row @endif>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#f7cd9a] text-xs font-bold text-blue-600 dark:bg-violet-950/60 dark:text-[#f7cd9a]">
                                                {{ mb_substr($person->name, 0, 1) }}
                                            </span>
                                            <a href="{{ route('people.show', $person) }}" class="min-w-0 font-medium hover:text-blue-600 dark:hover:text-blue-400">
                                                {{ $person->name }}
                                            </a>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-1.5">
                                            @forelse ($row['socials'] as $social)
                                                <span class="inline-flex items-center gap-1.5 rounded-full border border-black/5 bg-slate-50 py-1 pr-2.5 pl-1 dark:border-white/5 dark:bg-slate-800">
                                                    <x-platform-icon :platform="$social->platform" class="h-4.5 w-4.5" />
                                                    <span class="font-medium tabular-nums">
                                                        {{ number_format($social->latestStat?->{$countField[$social->platform->value]} ?? 0) }}
                                                    </span>
                                                </span>
                                            @empty
                                                <span class="text-slate-300 dark:text-slate-600">—</span>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <span class="w-16 shrink-0 text-right font-semibold tabular-nums">{{ number_format($row['reach']) }}</span>
                                            <div class="relative h-1.5 w-32 shrink-0 rounded-full bg-slate-100 dark:bg-slate-700">
                                                <div class="h-1.5 rounded-full bg-blue-500 dark:bg-blue-400" style="width: {{ $percent }}%"></div>
                                                <span class="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 h-2.5 w-2.5 rounded-full border-2 border-white bg-blue-600 shadow dark:border-slate-900" style="left: {{ $percent }}%"></span>
                                            </div>
                                            <span class="w-10 shrink-0 text-xs text-slate-400 dark:text-slate-500">{{ $percent }}%</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ $row['views'] ? number_format($row['views']) : '—' }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ $row['likes'] ? number_format($row['likes']) : '—' }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ $row['posts'] ? number_format($row['posts']) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <script>
                (function () {
                    var checkbox = document.getElementById('hide-empty-people');
                    if (! checkbox) return;

                    checkbox.addEventListener('change', function () {
                        document.querySelectorAll('[data-person-row]').forEach(function (row) {
                            var isEmpty = row.hasAttribute('data-empty-row');
                            row.classList.toggle('hidden', checkbox.checked && isEmpty);
                        });
                    });
                })();
            </script>
        @endif
    </div>

    {{-- ===================== TAB: INSTITUSI ===================== --}}
    <div data-tab-panel="institusi">
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-center gap-3">
                <a
                    href="{{ route('institutions.metric-comparison') }}"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-black/10 bg-white px-4 py-2 text-sm font-medium shadow-sm transition hover:bg-slate-50 dark:border-white/10 dark:bg-slate-800 dark:hover:bg-slate-700"
                >
                    <x-icon name="globe-alt" class="h-4 w-4" />
                    {{ __('analytics.metric_comparison_institution') }}
                </a>
                <a
                    href="{{ route('institutions.platform-comparison', $selectedPlatform ?: null) }}"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-black/10 bg-white px-4 py-2 text-sm font-medium shadow-sm transition hover:bg-slate-50 dark:border-white/10 dark:bg-slate-800 dark:hover:bg-slate-700"
                >
                    <x-icon name="arrow-trending-up" class="h-4 w-4" />
                    {{ __('analytics.platform_comparison_institution') }}
                </a>
            </div>
            @can('browse-directory-analytics')
                <x-export-button :url="route('export.institution-analytics.preview', array_filter(['institution_id' => $selectedInstitutionId, 'platform' => $selectedPlatform]))" />
            @endcan
        </div>

        {{-- Filters --}}
        <div class="mb-6 rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
            <h2 class="mb-4 text-lg font-bold text-slate-900 dark:text-white">{{ __('common.filter') }}</h2>
            <form method="GET" class="flex flex-wrap items-center gap-3">
                <input type="hidden" name="tab" value="institusi">

                <label class="relative">
                    <x-icon name="building-office" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <select
                        name="institution_id"
                        onchange="this.form.submit()"
                        class="appearance-none rounded-full border border-black/10 bg-slate-50 py-2.5 pr-10 pl-9 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-100 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                    >
                        <option value="">{{ __('analytics.all_institutions') }}</option>
                        @foreach ($institutions as $institution)
                            <option value="{{ $institution->id }}" @selected((int) $selectedInstitutionId === $institution->id)>{{ $institution->name }}</option>
                        @endforeach
                    </select>
                    <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                </label>

                <label class="relative">
                    <x-icon name="globe-alt" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <select
                        name="platform"
                        onchange="this.form.submit()"
                        class="appearance-none rounded-full border border-black/10 bg-slate-50 py-2.5 pr-10 pl-9 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-100 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                    >
                        <option value="">{{ __('common.all_social_media') }}</option>
                        @foreach ($platformLabels as $value => $label)
                            <option value="{{ $value }}" @selected($selectedPlatform === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                </label>

                @if ($selectedInstitutionId || $selectedPlatform)
                    <a href="{{ route('churches.analytics', ['tab' => 'institusi']) }}" class="text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
                        {{ __('common.reset_filter') }}
                    </a>
                @endif
            </form>
        </div>

        {{-- Hero KPI --}}
        <div class="mb-6 flex flex-wrap items-center gap-6 rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
            <span class="inline-flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 shadow-sm dark:bg-blue-950/50 dark:text-blue-300">
                <x-icon name="arrow-trending-up" class="h-7 w-7" />
            </span>

            <div class="min-w-[180px]">
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('analytics.total_reach_current') }}</p>
                <p class="text-3xl font-bold tabular-nums text-slate-900 dark:text-white">
                    {{ number_format($latestPointInstitution->total_reach ?? 0) }}
                </p>
            </div>

            @if ($reachGrowthPercentInstitution !== null)
                <div class="flex items-center gap-2 rounded-full border px-4 py-2 {{ $reachGrowthPercentInstitution >= 0 ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950' : 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950' }}">
                    <x-icon :name="$reachGrowthPercentInstitution > 0 ? 'arrow-trending-up' : ($reachGrowthPercentInstitution < 0 ? 'arrow-trending-down' : 'minus-small')" class="h-4 w-4 {{ $reachGrowthPercentInstitution >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}" />
                    <span class="text-sm font-semibold tabular-nums {{ $reachGrowthPercentInstitution >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400' }}">
                        {{ $reachGrowthPercentInstitution > 0 ? '+' : '' }}{{ number_format($reachGrowthPercentInstitution, 2) }}%
                    </span>
                    <span class="text-xs text-slate-400 dark:text-slate-500">{{ __('common.this_week') }}</span>
                </div>
            @endif
        </div>

        <div class="mb-8 grid gap-6 sm:grid-cols-2">
            <div class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
                <p class="font-bold text-slate-900 dark:text-white">{{ __('analytics.growth_reach_title') }}</p>
                <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">{{ $reachSubtitleInstitution }}{{ __('analytics.per_week') }}</p>
                <x-growth-chart :values="$growthOverTimeInstitution->pluck('total_reach')" :labels="$growthLabelsInstitution" />
            </div>

            <div class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
                <p class="font-bold text-slate-900 dark:text-white">{{ __('analytics.growth_views_title') }}</p>
                <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">{{ $viewsSubtitleInstitution }}{{ __('analytics.per_week') }}</p>
                <x-growth-chart :values="$growthOverTimeInstitution->pluck('total_views')" :labels="$growthLabelsInstitution" />
            </div>

            <div class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
                <p class="font-bold text-slate-900 dark:text-white">{{ __('analytics.growth_likes_title') }}</p>
                <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">{{ $likesSubtitleInstitution }}{{ __('analytics.per_week') }}</p>
                <x-growth-chart :values="$growthOverTimeInstitution->pluck('total_likes')" :labels="$growthLabelsInstitution" />
            </div>

            <div class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
                <p class="font-bold text-slate-900 dark:text-white">{{ __('analytics.growth_posts_title') }}</p>
                <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">{{ $postsSubtitleInstitution }}{{ __('analytics.per_week') }}</p>
                <x-growth-chart :values="$growthOverTimeInstitution->pluck('total_posts')" :labels="$growthLabelsInstitution" />
            </div>
        </div>

        @if ($filteredInstitutions->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-300 p-12 text-center dark:border-slate-700">
                <p class="text-slate-500 dark:text-slate-400">
                    {{ $institutions->isEmpty() ? __('analytics.no_institution_data') : __('analytics.no_institution_match_filter') }}
                </p>
            </div>
        @else
            @php
                $allDisplaySocialsInstitution = $filteredInstitutions->flatMap(
                    fn ($institution) => $selectedPlatform
                        ? $institution->socials->filter(fn ($s) => $s->platform->value === $selectedPlatform)
                        : $institution->socials
                );
                $grandByPlatformInstitution = $allDisplaySocialsInstitution->groupBy(fn ($s) => $s->platform->value);
                $grandReachInstitution = $allDisplaySocialsInstitution->sum(fn ($s) => $s->latestStat?->{$countField[$s->platform->value]} ?? 0);
                $grandViewsInstitution = $allDisplaySocialsInstitution->sum(fn ($s) => $s->latestStat?->views_count ?? 0);
                $grandLikesInstitution = $allDisplaySocialsInstitution->sum(fn ($s) => $s->latestStat?->likes_count ?? 0);
                $grandPostsInstitution = $allDisplaySocialsInstitution->sum(
                    fn ($s) => isset($postField[$s->platform->value]) ? ($s->latestStat?->{$postField[$s->platform->value]} ?? 0) : 0
                );

                $institutionRows = $filteredInstitutions->map(function ($institution) use ($selectedPlatform, $countField, $postField) {
                    $displaySocials = $selectedPlatform
                        ? $institution->socials->filter(fn ($s) => $s->platform->value === $selectedPlatform)
                        : $institution->socials;

                    return [
                        'institution' => $institution,
                        'socials' => $displaySocials,
                        'reach' => $displaySocials->sum(fn ($s) => $s->latestStat?->{$countField[$s->platform->value]} ?? 0),
                        'views' => $displaySocials->sum(fn ($s) => $s->latestStat?->views_count ?? 0),
                        'likes' => $displaySocials->sum(fn ($s) => $s->latestStat?->likes_count ?? 0),
                        'posts' => $displaySocials->sum(
                            fn ($s) => isset($postField[$s->platform->value]) ? ($s->latestStat?->{$postField[$s->platform->value]} ?? 0) : 0
                        ),
                    ];
                });

                $maxInstitutionReach = $institutionRows->max('reach') ?: 1;
            @endphp

            <div class="mb-6 overflow-x-auto rounded-2xl border border-black/5 dark:border-white/5">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800/60">
                        <tr>
                            <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('analytics.grand_total') }}</th>
                            <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('common.account') }}</th>
                            <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('analytics.total_reach') }}</th>
                            <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('analytics.total_views') }}</th>
                            <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('analytics.total_likes') }}</th>
                            <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('analytics.total_posts') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-900">
                        <tr>
                            <td class="px-4 py-3 font-semibold">{{ __('analytics.institutions_count', ['count' => $filteredInstitutions->count()]) }}</td>
                            <td class="px-4 py-3">
                                @if ($grandByPlatformInstitution->isEmpty())
                                    <span class="text-slate-300 dark:text-slate-600">—</span>
                                @else
                                    <p class="mb-1 text-xs text-slate-400 dark:text-slate-500">{{ __('analytics.account_count') }}</p>
                                    <div class="mb-2 flex flex-wrap gap-1.5">
                                        @foreach ($grandByPlatformInstitution as $platformValue => $group)
                                            <span class="inline-flex items-center gap-1.5 rounded-full border border-black/5 bg-slate-50 py-1 pr-2.5 pl-1 dark:border-white/5 dark:bg-slate-800">
                                                <x-platform-icon :platform="$platformValue" class="h-4.5 w-4.5" />
                                                <span class="font-semibold tabular-nums">{{ $group->count() }}</span>
                                            </span>
                                        @endforeach
                                    </div>

                                    <p class="mb-1 text-xs text-slate-400 dark:text-slate-500">{{ __('analytics.total_followers_subscribers') }}</p>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($grandByPlatformInstitution as $platformValue => $group)
                                            <span class="inline-flex items-center gap-1.5 rounded-full border border-black/5 bg-slate-50 py-1 pr-2.5 pl-1 dark:border-white/5 dark:bg-slate-800">
                                                <x-platform-icon :platform="$platformValue" class="h-4.5 w-4.5" />
                                                <span class="font-semibold tabular-nums">
                                                    {{ number_format($group->sum(fn ($s) => $s->latestStat?->{$countField[$platformValue]} ?? 0)) }}
                                                </span>
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ number_format($grandReachInstitution) }}</td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ $grandViewsInstitution ? number_format($grandViewsInstitution) : '—' }}</td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ $grandLikesInstitution ? number_format($grandLikesInstitution) : '—' }}</td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ $grandPostsInstitution ? number_format($grandPostsInstitution) : '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="rounded-2xl border border-black/5 bg-white shadow-sm dark:border-white/5 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('analytics.data_per_institution') }}</h2>
                    <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input type="checkbox" id="hide-empty-institutions" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800">
                        {{ __('analytics.hide_empty_institutions') }}
                    </label>
                </div>

                <div class="overflow-x-auto border-t border-black/5 dark:border-white/5">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-800/60">
                            <tr>
                                <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('common.institution') }}</th>
                                <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('common.account') }}</th>
                                <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('analytics.total_reach') }}</th>
                                <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">Views</th>
                                <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">Likes</th>
                                <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">Post / Video</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                            @foreach ($institutionRows as $row)
                                @php
                                    $institution = $row['institution'];
                                    $percent = $maxInstitutionReach > 0 ? round($row['reach'] / $maxInstitutionReach * 100, 1) : 0;
                                    $isEmpty = $row['reach'] == 0 && $row['views'] == 0 && $row['likes'] == 0 && $row['posts'] == 0;
                                @endphp
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40" data-institution-row @if ($isEmpty) data-empty-row @endif>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#f7cd9a] text-xs font-bold text-blue-600 dark:bg-violet-950/60 dark:text-[#f7cd9a]">
                                                {{ mb_substr($institution->name, 0, 1) }}
                                            </span>
                                            <a href="{{ route('institutions.show', $institution) }}" class="min-w-0 font-medium hover:text-blue-600 dark:hover:text-blue-400">
                                                {{ $institution->name }}
                                            </a>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-1.5">
                                            @forelse ($row['socials'] as $social)
                                                <span class="inline-flex items-center gap-1.5 rounded-full border border-black/5 bg-slate-50 py-1 pr-2.5 pl-1 dark:border-white/5 dark:bg-slate-800">
                                                    <x-platform-icon :platform="$social->platform" class="h-4.5 w-4.5" />
                                                    <span class="font-medium tabular-nums">
                                                        {{ number_format($social->latestStat?->{$countField[$social->platform->value]} ?? 0) }}
                                                    </span>
                                                </span>
                                            @empty
                                                <span class="text-slate-300 dark:text-slate-600">—</span>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <span class="w-16 shrink-0 text-right font-semibold tabular-nums">{{ number_format($row['reach']) }}</span>
                                            <div class="relative h-1.5 w-32 shrink-0 rounded-full bg-slate-100 dark:bg-slate-700">
                                                <div class="h-1.5 rounded-full bg-blue-500 dark:bg-blue-400" style="width: {{ $percent }}%"></div>
                                                <span class="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 h-2.5 w-2.5 rounded-full border-2 border-white bg-blue-600 shadow dark:border-slate-900" style="left: {{ $percent }}%"></span>
                                            </div>
                                            <span class="w-10 shrink-0 text-xs text-slate-400 dark:text-slate-500">{{ $percent }}%</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ $row['views'] ? number_format($row['views']) : '—' }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ $row['likes'] ? number_format($row['likes']) : '—' }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ $row['posts'] ? number_format($row['posts']) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <script>
                (function () {
                    var checkbox = document.getElementById('hide-empty-institutions');
                    if (! checkbox) return;

                    checkbox.addEventListener('change', function () {
                        document.querySelectorAll('[data-institution-row]').forEach(function (row) {
                            var isEmpty = row.hasAttribute('data-empty-row');
                            row.classList.toggle('hidden', checkbox.checked && isEmpty);
                        });
                    });
                })();
            </script>
        @endif
    </div>

    @include('partials.tab-script', ['activeTab' => $activeTab])
@endsection
