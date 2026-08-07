@php
    $countField = ['youtube' => 'subscribers_count', 'instagram' => 'followers_count', 'tiktok' => 'followers_count', 'facebook' => 'followers_count'];
    $postField = ['youtube' => 'videos_count', 'instagram' => 'posts_count', 'tiktok' => 'posts_count', 'facebook' => 'recent_posts_count'];
    // Instagram/TikTok are recent-sample view counts (last ~10-12 posts/videos), not a lifetime
    // total like YouTube's views_count — Facebook has no view-count field at all, so it falls
    // through to 'views_count' via the ?? below, which is always null on a Facebook row (0).
    $viewsField = ['youtube' => 'views_count', 'instagram' => 'recent_reels_views', 'tiktok' => 'recent_video_plays'];
    $platformLabels = ['youtube' => 'YouTube', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook'];

    $activeTab = in_array(request()->query('tab'), ['personal', 'institusi', 'gereja'], true) ? request()->query('tab') : 'organisasi';

    // Data Per * tables: a nasional-level viewer (or a plain member, whose analytics scope is
    // unscoped — see BuildsLeaderboards::analyticsChurchScope()) gets a 3-tier Uni > Daerah >
    // entity breakdown; a uni-level viewer gets Daerah > entity (the Uni tier would always be
    // their own single Uni, so it's not rendered); everyone else (daerah/gereja/institusi-level)
    // just gets the flat list they already had, since their scope is already a single Daerah.
    $analyticsRole = auth()->user()->role;
    $isNasionalView = $analyticsRole === null || ($analyticsRole->hasNasionalAccess() ?? false) || $analyticsRole->level() === 'nasional';
    $isUniView = ! $isNasionalView && $analyticsRole->level() === 'uni';

    // Defaults so these stay defined even when a tab's entity collection is empty — each tab's
    // own @if ($filteredX->isEmpty()) branch skips straight past the @php block that would
    // otherwise assign these, but the toggle-script include at the bottom of this file checks
    // all three unconditionally regardless of which tabs actually had data.
    $groupedChurchRows = null;
    $groupedPersonRows = null;
    $groupedInstitutionRows = null;
    $groupedOrganizationRows = null;

    // $rows is a Collection of the ['church'|'person'|'institution'|'organization' => $entity,
    // ...] arrays already built per tab below; $entityAccessor plucks the entity out of one row
    // so this one closure can group all four tables' rows identically. The Organisasi tab's own
    // entity is a Union OR a Conference model directly (not something with a further ->union/
    // ->conference relation chain to walk) — a Union groups under itself; a Conference groups
    // under its own ->union and bucket-keys under itself (never falling to 'uni-level', unlike
    // every other entity type, which has no conference_id of its own to be *the* Daerah tier).
    $groupEntityRows = function ($rows, $entityAccessor) {
        $resolveUnion = fn ($entity) => match (true) {
            $entity instanceof \App\Models\Union => $entity,
            $entity instanceof \App\Models\Conference => $entity->union,
            default => $entity->conference?->union ?? $entity->union ?? null,
        };
        $resolveConference = fn ($entity) => match (true) {
            $entity instanceof \App\Models\Conference => $entity,
            $entity instanceof \App\Models\Union => null,
            default => $entity->conference ?? null,
        };

        return $rows
            ->groupBy(function ($row) use ($entityAccessor, $resolveUnion) {
                $union = $resolveUnion($entityAccessor($row));

                return $union?->id ?? 'nasional';
            })
            ->map(function ($unionRows) use ($entityAccessor, $resolveUnion, $resolveConference) {
                $sample = $entityAccessor($unionRows->first());
                $union = $resolveUnion($sample);

                // A row whose entity IS the Union itself sits directly under the Union's own
                // header — Union sits above Daerah, so it can never itself "not have a Daerah
                // yet" the way a church/institution/conference-owned row can.
                $ownRows = $unionRows->filter(fn ($row) => $entityAccessor($row) instanceof \App\Models\Union);
                $nestedRows = $unionRows->reject(fn ($row) => $entityAccessor($row) instanceof \App\Models\Union);

                return [
                    'label' => $union?->name ?? __('analytics.group_national'),
                    'rows' => $ownRows,
                    'conferences' => $nestedRows
                        ->groupBy(fn ($row) => $resolveConference($entityAccessor($row))?->id ?? 'uni-level')
                        ->map(function ($conferenceRows) use ($entityAccessor, $resolveConference) {
                            $sample = $entityAccessor($conferenceRows->first());
                            $conference = $resolveConference($sample);

                            return [
                                'label' => $conference?->name ?? __('analytics.group_union_level'),
                                'rows' => $conferenceRows,
                            ];
                        })
                        ->sortBy('label'),
                ];
            })
            ->sortBy('label');
    };

    $selectedPlatformLabel = $selectedPlatform ? ($platformLabels[$selectedPlatform] ?? null) : null;

    // Gereja tab
    $selectedChurchName = $selectedChurchId ? $churches->firstWhere('id', (int) $selectedChurchId)?->name : null;
    $churchSuffix = $selectedChurchName ? __('analytics.suffix_entity', ['name' => $selectedChurchName]) : __('analytics.suffix_all_churches');

    $reachSubtitle = $selectedPlatform
        ? ($selectedPlatform === 'youtube' ? __('analytics.reach_subtitle_youtube') : __('analytics.reach_subtitle_platform', ['platform' => $selectedPlatformLabel]))
        : __('analytics.reach_subtitle_combined');
    $reachSubtitle .= $churchSuffix;

    $viewsSubtitle = __('analytics.views_subtitle');
    $viewsSubtitle .= ($selectedPlatform && ! in_array($selectedPlatform, ['youtube', 'instagram', 'tiktok'], true)) ? __('analytics.not_available_platform') : '';
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

    // NOT $latestPoint->total_reach — that's the SUM for whichever single recorded_at date
    // happens to be latest among the *filtered* rows, and different accounts get fetched on
    // different days, so a filter (category, platform, church_id...) that leaves only
    // slower-to-update accounts can silently land on an older week's snapshot entirely, showing
    // a stale/mismatched "current" total (caught via the new category filter: Akun Umum's
    // latest recorded_at was a full week behind Akun Gereja's). This instead sums each filtered
    // account's own latestStat directly — a true "right now" snapshot per account, same
    // approach $grandReach below already uses for the per-church table.
    $currentReachChurch = $filteredChurches->flatMap(
        fn ($church) => $church->socials
            ->when($selectedPlatform, fn ($socials) => $socials->filter(fn ($s) => $s->platform->value === $selectedPlatform))
            ->when($selectedCategory, fn ($socials) => $socials->filter(fn ($s) => $s->category->value === $selectedCategory))
    )->sum(fn ($s) => $s->latestStat?->{$countField[$s->platform->value]} ?? 0);

    // Personal tab
    $selectedPersonName = $selectedPersonId ? $people->firstWhere('id', (int) $selectedPersonId)?->name : null;
    $personSuffix = $selectedPersonName ? __('analytics.suffix_entity', ['name' => $selectedPersonName]) : __('analytics.suffix_all_personal');

    $reachSubtitlePersonal = $selectedPlatform
        ? ($selectedPlatform === 'youtube' ? __('analytics.reach_subtitle_youtube') : __('analytics.reach_subtitle_platform', ['platform' => $selectedPlatformLabel]))
        : __('analytics.reach_subtitle_combined');
    $reachSubtitlePersonal .= $personSuffix;

    $viewsSubtitlePersonal = __('analytics.views_subtitle');
    $viewsSubtitlePersonal .= ($selectedPlatform && ! in_array($selectedPlatform, ['youtube', 'instagram', 'tiktok'], true)) ? __('analytics.not_available_platform') : '';
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
    $viewsSubtitleInstitution .= ($selectedPlatform && ! in_array($selectedPlatform, ['youtube', 'instagram', 'tiktok'], true)) ? __('analytics.not_available_platform') : '';
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

    // Organisasi tab
    $selectedOrganizationName = $selectedOrganizationKey ? $organizations->first(function ($org) use ($selectedOrganizationKey) {
        $key = ($org instanceof \App\Models\Union ? 'union-' : 'conference-').$org->id;

        return $key === $selectedOrganizationKey;
    })?->name : null;
    $organizationSuffix = $selectedOrganizationName ? __('analytics.suffix_entity', ['name' => $selectedOrganizationName]) : __('analytics.suffix_all_organizations');

    $reachSubtitleOrganization = $selectedPlatform
        ? ($selectedPlatform === 'youtube' ? __('analytics.reach_subtitle_youtube') : __('analytics.reach_subtitle_platform', ['platform' => $selectedPlatformLabel]))
        : __('analytics.reach_subtitle_combined');
    $reachSubtitleOrganization .= $organizationSuffix;

    $viewsSubtitleOrganization = __('analytics.views_subtitle');
    $viewsSubtitleOrganization .= ($selectedPlatform && ! in_array($selectedPlatform, ['youtube', 'instagram', 'tiktok'], true)) ? __('analytics.not_available_platform') : '';
    $viewsSubtitleOrganization .= $organizationSuffix;

    $likesSubtitleOrganization = __('analytics.likes_subtitle');
    $likesSubtitleOrganization .= ($selectedPlatform && $selectedPlatform !== 'tiktok') ? __('analytics.not_available_platform') : '';
    $likesSubtitleOrganization .= $organizationSuffix;

    $postsSubtitleOrganization = __('analytics.posts_subtitle');
    $postsSubtitleOrganization .= ($selectedPlatform === 'facebook') ? __('analytics.not_available_platform') : '';
    $postsSubtitleOrganization .= $organizationSuffix;

    $growthLabelsOrganization = $growthOverTimeOrganization->pluck('recorded_at')->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->translatedFormat('d M'));

    $latestPointOrganization = $growthOverTimeOrganization->last();
    $previousPointOrganization = $growthOverTimeOrganization->count() >= 2 ? $growthOverTimeOrganization[$growthOverTimeOrganization->count() - 2] : null;
    $reachGrowthPercentOrganization = null;
    if ($latestPointOrganization && $previousPointOrganization && $previousPointOrganization->total_reach > 0) {
        $reachGrowthPercentOrganization = round((($latestPointOrganization->total_reach - $previousPointOrganization->total_reach) / $previousPointOrganization->total_reach) * 100, 2);
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

    <div class="mb-6 flex gap-2 overflow-x-auto border-b border-black/5 dark:border-white/5">
        <button
            type="button"
            data-tab-button="organisasi"
            class="border-b-2 px-4 py-2.5 text-sm font-medium transition"
        >
            {{ __('comparison.organization_label') }}
        </button>
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

    {{-- ===================== TAB: ORGANISASI ===================== --}}
    <div data-tab-panel="organisasi">
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
                <a
                    href="{{ route('organizations.metric-comparison') }}"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-black/10 bg-white px-4 py-2 text-sm font-medium shadow-sm transition hover:bg-slate-50 dark:border-white/10 dark:bg-slate-800 dark:hover:bg-slate-700"
                >
                    <x-icon name="globe-alt" class="h-4 w-4" />
                    {{ __('analytics.metric_comparison_organization') }}
                </a>
                <a
                    href="{{ route('organizations.platform-comparison', $selectedPlatform ?: null) }}"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-black/10 bg-white px-4 py-2 text-sm font-medium shadow-sm transition hover:bg-slate-50 dark:border-white/10 dark:bg-slate-800 dark:hover:bg-slate-700"
                >
                    <x-icon name="arrow-trending-up" class="h-4 w-4" />
                    {{ __('analytics.platform_comparison_organization') }}
                </a>
            </div>
            @can('browse-directory-analytics')
                <x-export-button :url="route('export.organization-analytics.preview', array_filter(['organization_id' => $selectedOrganizationKey, 'platform' => $selectedPlatform]))" />
            @endcan
        </div>

        {{-- Filters --}}
        <x-filter-card :clear-url="($selectedOrganizationKey || $selectedPlatform || $selectedConferenceId || $selectedStartDate || $selectedEndDate || ($isNasionalView && $selectedUnionId)) ? route('churches.analytics', ['tab' => 'organisasi']) : null">
            <form method="GET" id="organisasi-filter-form" class="flex flex-wrap items-center gap-3">
                <input type="hidden" name="tab" value="organisasi">

                @include('partials.analytics-region-filter', [
                    'prefix' => 'organization',
                    'formId' => 'organisasi-filter-form',
                    'isNasionalView' => $isNasionalView,
                    'isUniView' => $isUniView,
                    'unionOptions' => $unionOptions,
                    'conferenceOptions' => $conferenceOptions,
                    'selectedUnionId' => $selectedUnionId,
                    'selectedConferenceId' => $selectedConferenceId,
                ])

                @include('partials.analytics-entity-filter', [
                    'prefix' => 'organization',
                    'formId' => 'organisasi-filter-form',
                    'fieldName' => 'organization_id',
                    'icon' => 'flag',
                    'placeholder' => __('analytics.all_organizations'),
                    'selectedId' => $selectedOrganizationKey,
                    'options' => $organizations->map(fn ($org) => [
                        'id' => ($org instanceof \App\Models\Union ? 'union-' : 'conference-').$org->id,
                        'label' => $org->name,
                    ])->values(),
                ])

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

                @include('partials.analytics-date-range-filter', [
                    'prefix' => 'organization',
                    'selectedStartDate' => $selectedStartDate,
                    'selectedEndDate' => $selectedEndDate,
                ])

            </form>
        </x-filter-card>

        {{-- Hero KPI --}}
        <div class="mb-6 flex flex-wrap items-center gap-6 rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
            <span class="inline-flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 shadow-sm dark:bg-blue-950/50 dark:text-blue-300">
                <x-icon name="arrow-trending-up" class="h-7 w-7" />
            </span>

            <div class="min-w-[180px]">
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('analytics.total_reach_current') }}</p>
                <p class="text-3xl font-bold tabular-nums text-slate-900 dark:text-white">
                    {{ number_format($latestPointOrganization->total_reach ?? 0) }}
                </p>
            </div>

            @if ($reachGrowthPercentOrganization !== null)
                <div class="flex items-center gap-2 rounded-full border px-4 py-2 {{ $reachGrowthPercentOrganization >= 0 ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950' : 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950' }}">
                    <x-icon :name="$reachGrowthPercentOrganization > 0 ? 'arrow-trending-up' : ($reachGrowthPercentOrganization < 0 ? 'arrow-trending-down' : 'minus-small')" class="h-4 w-4 {{ $reachGrowthPercentOrganization >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}" />
                    <span class="text-sm font-semibold tabular-nums {{ $reachGrowthPercentOrganization >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400' }}">
                        {{ $reachGrowthPercentOrganization > 0 ? '+' : '' }}{{ number_format($reachGrowthPercentOrganization, 2) }}%
                    </span>
                    <span class="text-xs text-slate-400 dark:text-slate-500">{{ __('common.this_week') }}</span>
                </div>
            @endif
        </div>

        <div class="mb-8 grid gap-6 sm:grid-cols-2">
            <div class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
                <p class="font-bold text-slate-900 dark:text-white">{{ __('analytics.growth_reach_title') }}</p>
                <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">{{ $reachSubtitleOrganization }}{{ __('analytics.per_week') }}</p>
                <x-growth-chart :values="$growthOverTimeOrganization->pluck('total_reach')" :labels="$growthLabelsOrganization" />
            </div>

            <div class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
                <p class="font-bold text-slate-900 dark:text-white">{{ __('analytics.growth_views_title') }}</p>
                <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">{{ $viewsSubtitleOrganization }}{{ __('analytics.per_week') }}</p>
                <x-growth-chart :values="$growthOverTimeOrganization->pluck('total_views')" :labels="$growthLabelsOrganization" />
            </div>

            <div class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
                <p class="font-bold text-slate-900 dark:text-white">{{ __('analytics.growth_likes_title') }}</p>
                <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">{{ $likesSubtitleOrganization }}{{ __('analytics.per_week') }}</p>
                <x-growth-chart :values="$growthOverTimeOrganization->pluck('total_likes')" :labels="$growthLabelsOrganization" />
            </div>

            <div class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
                <p class="font-bold text-slate-900 dark:text-white">{{ __('analytics.growth_posts_title') }}</p>
                <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">{{ $postsSubtitleOrganization }}{{ __('analytics.per_week') }}</p>
                <x-growth-chart :values="$growthOverTimeOrganization->pluck('total_posts')" :labels="$growthLabelsOrganization" />
            </div>
        </div>

        @if ($filteredOrganizations->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-300 p-12 text-center dark:border-slate-700">
                <p class="text-slate-500 dark:text-slate-400">
                    {{ $organizations->isEmpty() ? __('analytics.no_organization_data') : __('analytics.no_organization_match_filter') }}
                </p>
            </div>
        @else
            @php
                $allDisplaySocialsOrganization = $filteredOrganizations->flatMap(
                    fn ($org) => $selectedPlatform
                        ? $org->socials->filter(fn ($s) => $s->platform->value === $selectedPlatform)
                        : $org->socials
                );
                $grandByPlatformOrganization = $allDisplaySocialsOrganization->groupBy(fn ($s) => $s->platform->value);
                $grandReachOrganization = $allDisplaySocialsOrganization->sum(fn ($s) => $s->latestStat?->{$countField[$s->platform->value]} ?? 0);
                $grandViewsOrganization = $allDisplaySocialsOrganization->sum(fn ($s) => $s->latestStat?->{$viewsField[$s->platform->value] ?? 'views_count'} ?? 0);
                $grandLikesOrganization = $allDisplaySocialsOrganization->sum(fn ($s) => $s->latestStat?->likes_count ?? 0);
                $grandPostsOrganization = $allDisplaySocialsOrganization->sum(
                    fn ($s) => isset($postField[$s->platform->value]) ? ($s->latestStat?->{$postField[$s->platform->value]} ?? 0) : 0
                );

                $organizationRows = $filteredOrganizations->map(function ($org) use ($selectedPlatform, $countField, $postField, $viewsField) {
                    $displaySocials = $selectedPlatform
                        ? $org->socials->filter(fn ($s) => $s->platform->value === $selectedPlatform)
                        : $org->socials;

                    return [
                        'organization' => $org,
                        'socials' => $displaySocials,
                        'reach' => $displaySocials->sum(fn ($s) => $s->latestStat?->{$countField[$s->platform->value]} ?? 0),
                        'views' => $displaySocials->sum(fn ($s) => $s->latestStat?->{$viewsField[$s->platform->value] ?? 'views_count'} ?? 0),
                        'likes' => $displaySocials->sum(fn ($s) => $s->latestStat?->likes_count ?? 0),
                        'posts' => $displaySocials->sum(
                            fn ($s) => isset($postField[$s->platform->value]) ? ($s->latestStat?->{$postField[$s->platform->value]} ?? 0) : 0
                        ),
                    ];
                });

                $maxOrganizationReach = $organizationRows->max('reach') ?: 1;
                $groupedOrganizationRows = ($isNasionalView || $isUniView) ? $groupEntityRows($organizationRows, fn ($row) => $row['organization']) : null;
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
                            <td class="px-4 py-3 font-semibold">{{ __('analytics.organizations_count', ['count' => $filteredOrganizations->count()]) }}</td>
                            <td class="px-4 py-3">
                                @if ($grandByPlatformOrganization->isEmpty())
                                    <span class="text-slate-300 dark:text-slate-600">—</span>
                                @else
                                    <p class="mb-1 text-xs text-slate-400 dark:text-slate-500">{{ __('analytics.account_count') }}</p>
                                    <div class="mb-2 flex flex-wrap gap-1.5">
                                        @foreach ($grandByPlatformOrganization as $platformValue => $group)
                                            <span class="inline-flex items-center gap-1.5 rounded-full border border-black/5 bg-slate-50 py-1 pr-2.5 pl-1 dark:border-white/5 dark:bg-slate-800">
                                                <x-platform-icon :platform="$platformValue" class="h-4.5 w-4.5" />
                                                <span class="font-semibold tabular-nums">{{ $group->count() }}</span>
                                            </span>
                                        @endforeach
                                    </div>

                                    <p class="mb-1 text-xs text-slate-400 dark:text-slate-500">{{ __('analytics.total_followers_subscribers') }}</p>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($grandByPlatformOrganization as $platformValue => $group)
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
                            <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ number_format($grandReachOrganization) }}</td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ $grandViewsOrganization ? number_format($grandViewsOrganization) : '—' }}</td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ $grandLikesOrganization ? number_format($grandLikesOrganization) : '—' }}</td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ $grandPostsOrganization ? number_format($grandPostsOrganization) : '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="rounded-2xl border border-black/5 bg-white shadow-sm dark:border-white/5 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('analytics.data_per_organization') }}</h2>
                    <div class="flex flex-wrap items-center gap-4">
                        @if ($groupedOrganizationRows !== null)
                            <label class="relative w-full sm:w-auto">
                                <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                                <input
                                    type="text"
                                    data-group-search-scope="organization"
                                    placeholder="{{ __('analytics.group_search_placeholder') }}"
                                    class="w-full sm:w-52 rounded-full border border-black/10 bg-slate-50 py-1.5 pr-3 pl-8 text-sm shadow-sm focus:border-blue-500 focus:bg-white focus:outline-none dark:border-white/10 dark:bg-slate-800 dark:focus:bg-slate-900"
                                >
                            </label>
                            <x-group-toggle-all-button scope="organization" />
                        @endif
                        <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                            <input type="checkbox" id="hide-empty-organizations" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800">
                            {{ __('analytics.hide_empty_organizations') }}
                        </label>
                    </div>
                </div>

                <div class="overflow-x-auto border-t border-black/5 dark:border-white/5">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-800/60">
                            <tr>
                                <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('comparison.organization_name_label') }}</th>
                                <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('common.account') }}</th>
                                <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('analytics.total_reach') }}</th>
                                <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">Views</th>
                                <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">Likes</th>
                                <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">Post / Video</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                            @if ($groupedOrganizationRows !== null)
                                @foreach ($groupedOrganizationRows as $unionKey => $unionGroup)
                                    @if ($isNasionalView)
                                        <x-analytics-group-row
                                            :label="$unionGroup['label']"
                                            :count="$unionGroup['conferences']->sum(fn ($c) => $c['rows']->count()) + $unionGroup['rows']->count()"
                                            :colspan="6"
                                            :toggle-id="'organization-union-'.$unionKey"
                                        />
                                    @endif
                                    @foreach ($unionGroup['rows'] as $row)
                                        @include('partials.analytics-organization-row', [
                                            'row' => $row,
                                            'maxReach' => $maxOrganizationReach,
                                            'depth' => 1,
                                            'ancestors' => $isNasionalView ? 'organization-union-'.$unionKey : null,
                                        ])
                                    @endforeach
                                    @foreach ($unionGroup['conferences'] as $conferenceKey => $conferenceGroup)
                                        <x-analytics-group-row
                                            :label="$conferenceGroup['label']"
                                            :count="$conferenceGroup['rows']->count()"
                                            :colspan="6"
                                            :toggle-id="'organization-conf-'.$unionKey.'-'.$conferenceKey"
                                            :ancestors="$isNasionalView ? 'organization-union-'.$unionKey : null"
                                            :depth="1"
                                        />
                                        @foreach ($conferenceGroup['rows'] as $row)
                                            @include('partials.analytics-organization-row', [
                                                'row' => $row,
                                                'maxReach' => $maxOrganizationReach,
                                                'depth' => 2,
                                                'ancestors' => ($isNasionalView ? 'organization-union-'.$unionKey.' ' : '').'organization-conf-'.$unionKey.'-'.$conferenceKey,
                                            ])
                                        @endforeach
                                    @endforeach
                                @endforeach
                            @else
                                @foreach ($organizationRows as $row)
                                    @include('partials.analytics-organization-row', ['row' => $row, 'maxReach' => $maxOrganizationReach])
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            <script>
                (function () {
                    var checkbox = document.getElementById('hide-empty-organizations');
                    if (! checkbox) return;

                    checkbox.addEventListener('change', function () {
                        document.querySelectorAll('[data-organization-row]').forEach(function (row) {
                            var isEmpty = row.hasAttribute('data-empty-row');
                            row.classList.toggle('hidden', checkbox.checked && isEmpty);
                        });
                    });
                })();
            </script>
        @endif
    </div>

    {{-- ===================== TAB: GEREJA ===================== --}}
    <div data-tab-panel="gereja">
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
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
                <x-export-button :url="route('export.analytics.preview', array_filter(['church_id' => $selectedChurchId, 'platform' => $selectedPlatform, 'category' => $selectedCategory]))" />
            @endcan
        </div>

        {{-- Filters --}}
        <x-filter-card :clear-url="($selectedChurchId || $selectedPlatform || $selectedCategory || $selectedConferenceId || $selectedStartDate || $selectedEndDate || ($isNasionalView && $selectedUnionId)) ? route('churches.analytics', ['tab' => 'gereja']) : null">
            <form method="GET" id="gereja-filter-form" class="flex flex-wrap items-center gap-3">
                <input type="hidden" name="tab" value="gereja">

                @include('partials.analytics-region-filter', [
                    'prefix' => 'church',
                    'formId' => 'gereja-filter-form',
                    'isNasionalView' => $isNasionalView,
                    'isUniView' => $isUniView,
                    'unionOptions' => $unionOptions,
                    'conferenceOptions' => $conferenceOptions,
                    'selectedUnionId' => $selectedUnionId,
                    'selectedConferenceId' => $selectedConferenceId,
                ])

                @include('partials.analytics-entity-filter', [
                    'prefix' => 'church',
                    'formId' => 'gereja-filter-form',
                    'fieldName' => 'church_id',
                    'icon' => 'building-office',
                    'placeholder' => __('analytics.all_churches'),
                    'selectedId' => $selectedChurchId,
                    'options' => $churches->map(fn ($c) => ['id' => $c->id, 'label' => $c->name])->values(),
                ])

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

                <label class="relative">
                    <x-icon name="building-office" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <select
                        name="category"
                        onchange="this.form.submit()"
                        class="appearance-none rounded-full border border-black/10 bg-slate-50 py-2.5 pr-10 pl-9 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-100 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                    >
                        <option value="">{{ __('common.all_categories') }}</option>
                        <option value="gereja" @selected($selectedCategory === 'gereja')>{{ __('directory.church_accounts') }}</option>
                        <option value="umum" @selected($selectedCategory === 'umum')>{{ __('directory.general_accounts') }}</option>
                    </select>
                    <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                </label>

                @include('partials.analytics-date-range-filter', [
                    'prefix' => 'church',
                    'selectedStartDate' => $selectedStartDate,
                    'selectedEndDate' => $selectedEndDate,
                ])

            </form>
        </x-filter-card>

        {{-- Hero KPI --}}
        <div class="mb-6 flex flex-wrap items-center gap-6 rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
            <span class="inline-flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 shadow-sm dark:bg-blue-950/50 dark:text-blue-300">
                <x-icon name="arrow-trending-up" class="h-7 w-7" />
            </span>

            <div class="min-w-[180px]">
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('analytics.total_reach_current') }}</p>
                <p class="text-3xl font-bold tabular-nums text-slate-900 dark:text-white">
                    {{ number_format($currentReachChurch) }}
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
                    fn ($church) => $church->socials
                        ->when($selectedPlatform, fn ($socials) => $socials->filter(fn ($s) => $s->platform->value === $selectedPlatform))
                        ->when($selectedCategory, fn ($socials) => $socials->filter(fn ($s) => $s->category->value === $selectedCategory))
                );
                $grandByCategory = collect(['gereja', 'umum'])->mapWithKeys(
                    fn ($category) => [
                        $category => $allDisplaySocials
                            ->filter(fn ($s) => $s->category->value === $category)
                            ->groupBy(fn ($s) => $s->platform->value),
                    ]
                );
                $grandReach = $allDisplaySocials->sum(fn ($s) => $s->latestStat?->{$countField[$s->platform->value]} ?? 0);
                $grandViews = $allDisplaySocials->sum(fn ($s) => $s->latestStat?->{$viewsField[$s->platform->value] ?? 'views_count'} ?? 0);
                $grandLikes = $allDisplaySocials->sum(fn ($s) => $s->latestStat?->likes_count ?? 0);
                $grandPosts = $allDisplaySocials->sum(
                    fn ($s) => isset($postField[$s->platform->value]) ? ($s->latestStat?->{$postField[$s->platform->value]} ?? 0) : 0
                );

                $churchRows = $filteredChurches->map(function ($church) use ($selectedPlatform, $selectedCategory, $countField, $postField, $viewsField) {
                    $displaySocials = $church->socials
                        ->when($selectedPlatform, fn ($socials) => $socials->filter(fn ($s) => $s->platform->value === $selectedPlatform))
                        ->when($selectedCategory, fn ($socials) => $socials->filter(fn ($s) => $s->category->value === $selectedCategory));

                    return [
                        'church' => $church,
                        'socialsByCategory' => $displaySocials->groupBy(fn ($s) => $s->category->value),
                        'reach' => $displaySocials->sum(fn ($s) => $s->latestStat?->{$countField[$s->platform->value]} ?? 0),
                        'views' => $displaySocials->sum(fn ($s) => $s->latestStat?->{$viewsField[$s->platform->value] ?? 'views_count'} ?? 0),
                        'likes' => $displaySocials->sum(fn ($s) => $s->latestStat?->likes_count ?? 0),
                        'posts' => $displaySocials->sum(
                            fn ($s) => isset($postField[$s->platform->value]) ? ($s->latestStat?->{$postField[$s->platform->value]} ?? 0) : 0
                        ),
                    ];
                });

                $maxChurchReach = $churchRows->max('reach') ?: 1;
                $groupedChurchRows = ($isNasionalView || $isUniView) ? $groupEntityRows($churchRows, fn ($row) => $row['church']) : null;
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
                    <div class="flex flex-wrap items-center gap-4">
                        @if ($groupedChurchRows !== null)
                            <label class="relative w-full sm:w-auto">
                                <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                                <input
                                    type="text"
                                    data-group-search-scope="church"
                                    placeholder="{{ __('analytics.group_search_placeholder') }}"
                                    class="w-full sm:w-52 rounded-full border border-black/10 bg-slate-50 py-1.5 pr-3 pl-8 text-sm shadow-sm focus:border-blue-500 focus:bg-white focus:outline-none dark:border-white/10 dark:bg-slate-800 dark:focus:bg-slate-900"
                                >
                            </label>
                            <x-group-toggle-all-button scope="church" />
                        @endif
                        <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                            <input type="checkbox" id="hide-empty-churches" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800">
                            {{ __('analytics.hide_empty_churches') }}
                        </label>
                    </div>
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
                            @if ($groupedChurchRows !== null)
                                @foreach ($groupedChurchRows as $unionKey => $unionGroup)
                                    @if ($isNasionalView)
                                        <x-analytics-group-row
                                            :label="$unionGroup['label']"
                                            :count="$unionGroup['conferences']->sum(fn ($c) => $c['rows']->count())"
                                            :colspan="7"
                                            :toggle-id="'church-union-'.$unionKey"
                                        />
                                    @endif
                                    @foreach ($unionGroup['conferences'] as $conferenceKey => $conferenceGroup)
                                        <x-analytics-group-row
                                            :label="$conferenceGroup['label']"
                                            :count="$conferenceGroup['rows']->count()"
                                            :colspan="7"
                                            :toggle-id="'church-conf-'.$unionKey.'-'.$conferenceKey"
                                            :ancestors="$isNasionalView ? 'church-union-'.$unionKey : null"
                                            :depth="1"
                                        />
                                        @foreach ($conferenceGroup['rows'] as $row)
                                            @include('partials.analytics-church-row', [
                                                'row' => $row,
                                                'maxReach' => $maxChurchReach,
                                                'depth' => 2,
                                                'ancestors' => ($isNasionalView ? 'church-union-'.$unionKey.' ' : '').'church-conf-'.$unionKey.'-'.$conferenceKey,
                                            ])
                                        @endforeach
                                    @endforeach
                                @endforeach
                            @else
                                @foreach ($churchRows as $row)
                                    @include('partials.analytics-church-row', ['row' => $row, 'maxReach' => $maxChurchReach])
                                @endforeach
                            @endif
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

    {{-- ===================== TAB: INSTITUSI ===================== --}}
    <div data-tab-panel="institusi">
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
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
        <x-filter-card :clear-url="($selectedInstitutionId || $selectedPlatform || $selectedConferenceId || $selectedStartDate || $selectedEndDate || ($isNasionalView && $selectedUnionId)) ? route('churches.analytics', ['tab' => 'institusi']) : null">
            <form method="GET" id="institusi-filter-form" class="flex flex-wrap items-center gap-3">
                <input type="hidden" name="tab" value="institusi">

                @include('partials.analytics-region-filter', [
                    'prefix' => 'institution',
                    'formId' => 'institusi-filter-form',
                    'isNasionalView' => $isNasionalView,
                    'isUniView' => $isUniView,
                    'unionOptions' => $unionOptions,
                    'conferenceOptions' => $conferenceOptions,
                    'selectedUnionId' => $selectedUnionId,
                    'selectedConferenceId' => $selectedConferenceId,
                ])

                @include('partials.analytics-entity-filter', [
                    'prefix' => 'institution',
                    'formId' => 'institusi-filter-form',
                    'fieldName' => 'institution_id',
                    'icon' => 'building-office',
                    'placeholder' => __('analytics.all_institutions'),
                    'selectedId' => $selectedInstitutionId,
                    'options' => $institutions->map(fn ($i) => ['id' => $i->id, 'label' => $i->name])->values(),
                ])

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

                @include('partials.analytics-date-range-filter', [
                    'prefix' => 'institution',
                    'selectedStartDate' => $selectedStartDate,
                    'selectedEndDate' => $selectedEndDate,
                ])

            </form>
        </x-filter-card>

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
                $grandViewsInstitution = $allDisplaySocialsInstitution->sum(fn ($s) => $s->latestStat?->{$viewsField[$s->platform->value] ?? 'views_count'} ?? 0);
                $grandLikesInstitution = $allDisplaySocialsInstitution->sum(fn ($s) => $s->latestStat?->likes_count ?? 0);
                $grandPostsInstitution = $allDisplaySocialsInstitution->sum(
                    fn ($s) => isset($postField[$s->platform->value]) ? ($s->latestStat?->{$postField[$s->platform->value]} ?? 0) : 0
                );

                $institutionRows = $filteredInstitutions->map(function ($institution) use ($selectedPlatform, $countField, $postField, $viewsField) {
                    $displaySocials = $selectedPlatform
                        ? $institution->socials->filter(fn ($s) => $s->platform->value === $selectedPlatform)
                        : $institution->socials;

                    return [
                        'institution' => $institution,
                        'socials' => $displaySocials,
                        'reach' => $displaySocials->sum(fn ($s) => $s->latestStat?->{$countField[$s->platform->value]} ?? 0),
                        'views' => $displaySocials->sum(fn ($s) => $s->latestStat?->{$viewsField[$s->platform->value] ?? 'views_count'} ?? 0),
                        'likes' => $displaySocials->sum(fn ($s) => $s->latestStat?->likes_count ?? 0),
                        'posts' => $displaySocials->sum(
                            fn ($s) => isset($postField[$s->platform->value]) ? ($s->latestStat?->{$postField[$s->platform->value]} ?? 0) : 0
                        ),
                    ];
                });

                $maxInstitutionReach = $institutionRows->max('reach') ?: 1;
                $groupedInstitutionRows = ($isNasionalView || $isUniView) ? $groupEntityRows($institutionRows, fn ($row) => $row['institution']) : null;
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
                    <div class="flex flex-wrap items-center gap-4">
                        @if ($groupedInstitutionRows !== null)
                            <label class="relative w-full sm:w-auto">
                                <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                                <input
                                    type="text"
                                    data-group-search-scope="institution"
                                    placeholder="{{ __('analytics.group_search_placeholder') }}"
                                    class="w-full sm:w-52 rounded-full border border-black/10 bg-slate-50 py-1.5 pr-3 pl-8 text-sm shadow-sm focus:border-blue-500 focus:bg-white focus:outline-none dark:border-white/10 dark:bg-slate-800 dark:focus:bg-slate-900"
                                >
                            </label>
                            <x-group-toggle-all-button scope="institution" />
                        @endif
                        <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                            <input type="checkbox" id="hide-empty-institutions" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800">
                            {{ __('analytics.hide_empty_institutions') }}
                        </label>
                    </div>
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
                            @if ($groupedInstitutionRows !== null)
                                @foreach ($groupedInstitutionRows as $unionKey => $unionGroup)
                                    @if ($isNasionalView)
                                        <x-analytics-group-row
                                            :label="$unionGroup['label']"
                                            :count="$unionGroup['conferences']->sum(fn ($c) => $c['rows']->count())"
                                            :colspan="6"
                                            :toggle-id="'institution-union-'.$unionKey"
                                        />
                                    @endif
                                    @foreach ($unionGroup['conferences'] as $conferenceKey => $conferenceGroup)
                                        <x-analytics-group-row
                                            :label="$conferenceGroup['label']"
                                            :count="$conferenceGroup['rows']->count()"
                                            :colspan="6"
                                            :toggle-id="'institution-conf-'.$unionKey.'-'.$conferenceKey"
                                            :ancestors="$isNasionalView ? 'institution-union-'.$unionKey : null"
                                            :depth="1"
                                        />
                                        @foreach ($conferenceGroup['rows'] as $row)
                                            @include('partials.analytics-institution-row', [
                                                'row' => $row,
                                                'maxReach' => $maxInstitutionReach,
                                                'depth' => 2,
                                                'ancestors' => ($isNasionalView ? 'institution-union-'.$unionKey.' ' : '').'institution-conf-'.$unionKey.'-'.$conferenceKey,
                                            ])
                                        @endforeach
                                    @endforeach
                                @endforeach
                            @else
                                @foreach ($institutionRows as $row)
                                    @include('partials.analytics-institution-row', ['row' => $row, 'maxReach' => $maxInstitutionReach])
                                @endforeach
                            @endif
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

    {{-- ===================== TAB: PERSONAL ===================== --}}
    <div data-tab-panel="personal">
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
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
        <x-filter-card :clear-url="($selectedPersonId || $selectedPlatform || $selectedConferenceId || $selectedStartDate || $selectedEndDate || ($isNasionalView && $selectedUnionId)) ? route('churches.analytics', ['tab' => 'personal']) : null">
            <form method="GET" id="personal-filter-form" class="flex flex-wrap items-center gap-3">
                <input type="hidden" name="tab" value="personal">

                @include('partials.analytics-region-filter', [
                    'prefix' => 'person',
                    'formId' => 'personal-filter-form',
                    'isNasionalView' => $isNasionalView,
                    'isUniView' => $isUniView,
                    'unionOptions' => $unionOptions,
                    'conferenceOptions' => $conferenceOptions,
                    'selectedUnionId' => $selectedUnionId,
                    'selectedConferenceId' => $selectedConferenceId,
                ])

                @include('partials.analytics-entity-filter', [
                    'prefix' => 'person',
                    'formId' => 'personal-filter-form',
                    'fieldName' => 'person_id',
                    'icon' => 'user',
                    'placeholder' => __('analytics.all_personal'),
                    'selectedId' => $selectedPersonId,
                    'options' => $people->map(fn ($p) => ['id' => $p->id, 'label' => $p->name])->values(),
                ])

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

                @include('partials.analytics-date-range-filter', [
                    'prefix' => 'person',
                    'selectedStartDate' => $selectedStartDate,
                    'selectedEndDate' => $selectedEndDate,
                ])

            </form>
        </x-filter-card>

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
                $grandViewsPersonal = $allDisplaySocialsPersonal->sum(fn ($s) => $s->latestStat?->{$viewsField[$s->platform->value] ?? 'views_count'} ?? 0);
                $grandLikesPersonal = $allDisplaySocialsPersonal->sum(fn ($s) => $s->latestStat?->likes_count ?? 0);
                $grandPostsPersonal = $allDisplaySocialsPersonal->sum(
                    fn ($s) => isset($postField[$s->platform->value]) ? ($s->latestStat?->{$postField[$s->platform->value]} ?? 0) : 0
                );

                $personRows = $filteredPeople->map(function ($person) use ($selectedPlatform, $countField, $postField, $viewsField) {
                    $displaySocials = $selectedPlatform
                        ? $person->socials->filter(fn ($s) => $s->platform->value === $selectedPlatform)
                        : $person->socials;

                    return [
                        'person' => $person,
                        'socials' => $displaySocials,
                        'reach' => $displaySocials->sum(fn ($s) => $s->latestStat?->{$countField[$s->platform->value]} ?? 0),
                        'views' => $displaySocials->sum(fn ($s) => $s->latestStat?->{$viewsField[$s->platform->value] ?? 'views_count'} ?? 0),
                        'likes' => $displaySocials->sum(fn ($s) => $s->latestStat?->likes_count ?? 0),
                        'posts' => $displaySocials->sum(
                            fn ($s) => isset($postField[$s->platform->value]) ? ($s->latestStat?->{$postField[$s->platform->value]} ?? 0) : 0
                        ),
                    ];
                });

                $maxPersonReach = $personRows->max('reach') ?: 1;
                $groupedPersonRows = ($isNasionalView || $isUniView) ? $groupEntityRows($personRows, fn ($row) => $row['person']) : null;
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
                    <div class="flex flex-wrap items-center gap-4">
                        @if ($groupedPersonRows !== null)
                            <label class="relative w-full sm:w-auto">
                                <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                                <input
                                    type="text"
                                    data-group-search-scope="person"
                                    placeholder="{{ __('analytics.group_search_placeholder') }}"
                                    class="w-full sm:w-52 rounded-full border border-black/10 bg-slate-50 py-1.5 pr-3 pl-8 text-sm shadow-sm focus:border-blue-500 focus:bg-white focus:outline-none dark:border-white/10 dark:bg-slate-800 dark:focus:bg-slate-900"
                                >
                            </label>
                            <x-group-toggle-all-button scope="person" />
                        @endif
                        <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                            <input type="checkbox" id="hide-empty-people" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800">
                            {{ __('analytics.hide_empty_personal') }}
                        </label>
                    </div>
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
                            @if ($groupedPersonRows !== null)
                                @foreach ($groupedPersonRows as $unionKey => $unionGroup)
                                    @if ($isNasionalView)
                                        <x-analytics-group-row
                                            :label="$unionGroup['label']"
                                            :count="$unionGroup['conferences']->sum(fn ($c) => $c['rows']->count())"
                                            :colspan="6"
                                            :toggle-id="'person-union-'.$unionKey"
                                        />
                                    @endif
                                    @foreach ($unionGroup['conferences'] as $conferenceKey => $conferenceGroup)
                                        <x-analytics-group-row
                                            :label="$conferenceGroup['label']"
                                            :count="$conferenceGroup['rows']->count()"
                                            :colspan="6"
                                            :toggle-id="'person-conf-'.$unionKey.'-'.$conferenceKey"
                                            :ancestors="$isNasionalView ? 'person-union-'.$unionKey : null"
                                            :depth="1"
                                        />
                                        @foreach ($conferenceGroup['rows'] as $row)
                                            @include('partials.analytics-person-row', [
                                                'row' => $row,
                                                'maxReach' => $maxPersonReach,
                                                'depth' => 2,
                                                'ancestors' => ($isNasionalView ? 'person-union-'.$unionKey.' ' : '').'person-conf-'.$unionKey.'-'.$conferenceKey,
                                            ])
                                        @endforeach
                                    @endforeach
                                @endforeach
                            @else
                                @foreach ($personRows as $row)
                                    @include('partials.analytics-person-row', ['row' => $row, 'maxReach' => $maxPersonReach])
                                @endforeach
                            @endif
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

    @include('partials.tab-script', ['activeTab' => $activeTab])
    @if ($groupedChurchRows !== null || $groupedPersonRows !== null || $groupedInstitutionRows !== null || $groupedOrganizationRows !== null)
        @include('partials.analytics-group-toggle')
    @endif
@endsection
