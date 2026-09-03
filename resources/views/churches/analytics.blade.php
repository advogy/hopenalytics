@php
    $countField = ['youtube' => 'subscribers_count', 'instagram' => 'followers_count', 'tiktok' => 'followers_count', 'facebook' => 'followers_count', 'x' => 'followers_count', 'threads' => 'followers_count'];
    $postField = ['youtube' => 'videos_count', 'instagram' => 'posts_count', 'tiktok' => 'posts_count', 'facebook' => 'recent_posts_count', 'x' => 'posts_count', 'threads' => 'recent_posts_count'];
    // Instagram/TikTok are recent-sample view counts (last ~10-12 posts/videos), not a lifetime
    // total like YouTube's views_count — Facebook, X, and Threads have no view-count field at
    // all, so they fall through to 'views_count' via the ?? below, which is always null on
    // those rows (0).
    $viewsField = ['youtube' => 'views_count', 'instagram' => 'recent_reels_views', 'tiktok' => 'recent_video_plays'];
    $platformLabels = \App\Models\AppSetting::current()->enabledPlatformLabels();

    $activeTab = in_array(request()->query('tab'), ['personal', 'institusi', 'gereja', 'hastag'], true) ? request()->query('tab') : 'organisasi';

    // Data Per * tables: a nasional-level viewer gets a 3-tier Uni > Daerah > entity breakdown;
    // a uni-level viewer gets Daerah > entity (the Uni tier would always be their own single
    // Uni, so it's not rendered); everyone else (daerah/gereja/institusi-level, and a plain
    // member — whose analytics scope is now their own Daerah/Uni at most, same breadth, see
    // BuildsLeaderboards::analyticsChurchScope()) just gets the flat list they already had,
    // since their scope is already a single Daerah. Mirrors BuildsLeaderboards::
    // isNasionalView()/isUniView() exactly — duplicated here rather than passed from the
    // controller since this file computes it once for four tabs' worth of tables at once.
    $analyticsRole = auth()->user()->role;
    $isNasionalView = $analyticsRole !== null && ($analyticsRole->hasGlobalAccess() || in_array($analyticsRole->level(), ['global', 'nasional', 'divisi'], true));
    $isUniView = $analyticsRole !== null && ! $isNasionalView && $analyticsRole->level() === 'uni';
    // Same reasoning as isUniView() never showing its own Uni tier — a Divisi-level viewer is
    // isNasionalView() too (they can see multiple Unions), but shouldn't see a Divisi tier for
    // their own single Divisi.
    $showDivisionHeader = $isNasionalView && $analyticsRole?->level() !== 'divisi';

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
    // so this one closure can group all four tables' rows identically — same Divisi > Uni >
    // Daerah > entity shape as BuildsLeaderboards::groupByRegion() (this is its Blade-side twin,
    // since this file's own $filteredX collections aren't ChurchSocial rows the trait method can
    // take directly). The Organisasi tab's own entity is a Division, Union, OR Conference model
    // directly (not something with a further ->union/->conference relation chain to walk) — a
    // Division groups under itself; a Union groups under its own ->division and bucket-keys
    // under itself; a Conference groups under its own ->union and bucket-keys under itself
    // (never falling to 'uni-level', unlike every other entity type, which has no conference_id
    // of its own to be *the* Daerah tier).
    $groupEntityRows = function ($rows, $entityAccessor) {
        $resolveUnion = fn ($entity) => match (true) {
            $entity instanceof \App\Models\Union => $entity,
            $entity instanceof \App\Models\Conference => $entity->union,
            $entity instanceof \App\Models\Division => null,
            default => $entity->conference?->union ?? $entity->union ?? null,
        };
        $resolveConference = fn ($entity) => match (true) {
            $entity instanceof \App\Models\Conference => $entity,
            $entity instanceof \App\Models\Union, $entity instanceof \App\Models\Division => null,
            default => $entity->conference ?? null,
        };
        $resolveDivision = fn ($entity) => $entity instanceof \App\Models\Division ? $entity : $resolveUnion($entity)?->division;

        return $rows
            ->groupBy(function ($row) use ($entityAccessor, $resolveDivision) {
                $division = $resolveDivision($entityAccessor($row));

                return $division?->id ?? 'no-division';
            })
            ->map(function ($divisionRows) use ($entityAccessor, $resolveUnion, $resolveConference, $resolveDivision) {
                $sample = $entityAccessor($divisionRows->first());
                $division = $resolveDivision($sample);

                $ownRows = $divisionRows->filter(fn ($row) => $entityAccessor($row) instanceof \App\Models\Division);
                $nestedRows = $divisionRows->reject(fn ($row) => $entityAccessor($row) instanceof \App\Models\Division);

                return [
                    'label' => $division?->name ?? __('analytics.group_no_division'),
                    'rows' => $ownRows,
                    'unions' => $nestedRows
                        ->groupBy(function ($row) use ($entityAccessor, $resolveUnion) {
                            $union = $resolveUnion($entityAccessor($row));

                            return $union?->id ?? 'nasional';
                        })
                        ->map(function ($unionRows) use ($entityAccessor, $resolveUnion, $resolveConference) {
                            $sample = $entityAccessor($unionRows->first());
                            $union = $resolveUnion($sample);

                            // A row whose entity IS the Union itself sits directly under the
                            // Union's own header — Union sits above Daerah, so it can never
                            // itself "not have a Daerah yet" the way a church/institution/
                            // conference-owned row can.
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
        $key = match (true) {
            $org instanceof \App\Models\Division => 'division-',
            $org instanceof \App\Models\Union => 'union-',
            default => 'conference-',
        }.$org->id;

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
    <x-back-link :href="route('churches.index')">
        {{ __('common.back_to_dashboard') }}
    </x-back-link>

    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('nav.analytics') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('analytics.subtitle') }}</p>
        </div>

        @can('trigger-refresh')
            <div class="flex flex-col items-end gap-1.5">
                <form
                    method="POST"
                    action="{{ route('socials.refresh-all') }}"
                    data-confirm="{{ __('dashboard.refresh_confirm', ['count' => $totalRefreshableSocials]) }}"
                    data-progress-form
                >
                    @csrf
                    <button
                        type="submit"
                        data-progress-button
                        class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-70"
                    >
                        <x-icon name="arrow-path" class="h-4 w-4" />
                        {{ __('dashboard.refresh_button') }}
                    </button>
                </form>
                <p class="text-xs text-slate-400 dark:text-slate-500">
                    {{ $lastFetchedAt ? __('dashboard.last_updated_at', ['time' => $lastFetchedAt->translatedFormat('d M Y H:i')]) : __('dashboard.last_updated_never') }}
                </p>
            </div>
        @endcan
    </div>

    @if ($noPersonalRegion)
        <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300">
            <p class="mb-2 font-bold">{{ __('analytics.no_personal_region_title') }}</p>
            <p class="mb-3">{{ __('analytics.no_personal_region_body') }}</p>
            <a href="{{ route('profile.edit', ['tab' => 'personal']) }}" class="font-medium underline">
                {{ __('analytics.no_personal_region_cta') }}
            </a>
        </div>
    @endif

    <x-tab-bar>
        <x-tab-button tab-key="organisasi">{{ __('comparison.organization_label') }}</x-tab-button>
        <x-tab-button tab-key="gereja">{{ __('common.church') }}</x-tab-button>
        <x-tab-button tab-key="institusi">{{ __('common.institution') }}</x-tab-button>
        <x-tab-button tab-key="personal">{{ __('common.personal') }}</x-tab-button>
        <x-tab-button tab-key="hastag">{{ __('hashtag.tab_label') }}</x-tab-button>
    </x-tab-bar>

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
                <x-export-button :url="route('export.organization-analytics.preview', array_filter(['organization_id' => $selectedOrganizationKey, 'platform' => $selectedPlatform, 'union_id' => $selectedUnionId, 'conference_id' => $selectedConferenceId]))" />
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
                        'id' => match (true) {
                            $org instanceof \App\Models\Division => 'division-',
                            $org instanceof \App\Models\Union => 'union-',
                            default => 'conference-',
                        }.$org->id,
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
        <x-analytics-hero-kpi :value="$latestPointOrganization->total_reach ?? 0" :growth-percent="$reachGrowthPercentOrganization" />

        <x-analytics-growth-grid
            :growth-over-time="$growthOverTimeOrganization"
            :growth-labels="$growthLabelsOrganization"
            :reach-subtitle="$reachSubtitleOrganization"
            :views-subtitle="$viewsSubtitleOrganization"
            :likes-subtitle="$likesSubtitleOrganization"
            :posts-subtitle="$postsSubtitleOrganization"
        />

        @if ($filteredOrganizations->isEmpty())
            <x-empty-state>
                {{ $organizations->isEmpty() ? __('analytics.no_organization_data') : __('analytics.no_organization_match_filter') }}
            </x-empty-state>
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
                                <x-platform-count-chips :group="$grandByPlatformOrganization" :count-field="$countField" />
                            </td>
                            <x-analytics-grand-total-cells :reach="$grandReachOrganization" :views="$grandViewsOrganization" :likes="$grandLikesOrganization" :posts="$grandPostsOrganization" />
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
                            <input type="checkbox" data-hide-empty-toggle="[data-organization-row]" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800">
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
                                <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('common.metric_views') }}</th>
                                <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('common.metric_likes') }}</th>
                                <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('common.metric_posts') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                            @if ($groupedOrganizationRows !== null)
                                <x-grouped-rows
                                    :grouped="$groupedOrganizationRows"
                                    prefix="organization"
                                    :colspan="6"
                                    row-view="partials.analytics-organization-row"
                                    row-key="row"
                                    :row-extra="['maxReach' => $maxOrganizationReach, 'countField' => $countField]"
                                    :show-union-header="$isNasionalView"
                                    :show-division-header="$showDivisionHeader"
                                />
                            @else
                                @foreach ($organizationRows as $row)
                                    @include('partials.analytics-organization-row', ['row' => $row, 'maxReach' => $maxOrganizationReach])
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            @include('partials.hide-empty-rows')
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
                <x-export-button :url="route('export.analytics.preview', array_filter(['church_id' => $selectedChurchId, 'platform' => $selectedPlatform, 'category' => $selectedCategory, 'union_id' => $selectedUnionId, 'conference_id' => $selectedConferenceId]))" />
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

                <x-category-filter :selected-category="$selectedCategory" />

                @include('partials.analytics-date-range-filter', [
                    'prefix' => 'church',
                    'selectedStartDate' => $selectedStartDate,
                    'selectedEndDate' => $selectedEndDate,
                ])

            </form>
        </x-filter-card>

        {{-- Hero KPI --}}
        <x-analytics-hero-kpi :value="$currentReachChurch" :growth-percent="$reachGrowthPercent" />

        <x-analytics-growth-grid
            :growth-over-time="$growthOverTime"
            :growth-labels="$growthLabels"
            :reach-subtitle="$reachSubtitle"
            :views-subtitle="$viewsSubtitle"
            :likes-subtitle="$likesSubtitle"
            :posts-subtitle="$postsSubtitle"
        />

        @if ($filteredChurches->isEmpty())
            <x-empty-state>
                {{ $churches->isEmpty() ? __('analytics.no_church_data') : __('analytics.no_church_match_filter') }}
            </x-empty-state>
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
                                    <x-platform-count-chips :group="$grandByCategory[$category]" :count-field="$countField" />
                                </td>
                            @endforeach
                            <x-analytics-grand-total-cells :reach="$grandReach" :views="$grandViews" :likes="$grandLikes" :posts="$grandPosts" />
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
                            <input type="checkbox" data-hide-empty-toggle="[data-church-row]" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800">
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
                                <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('common.metric_views') }}</th>
                                <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('common.metric_likes') }}</th>
                                <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('common.metric_posts') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                            @if ($groupedChurchRows !== null)
                                <x-grouped-rows
                                    :grouped="$groupedChurchRows"
                                    prefix="church"
                                    :colspan="7"
                                    row-view="partials.analytics-church-row"
                                    row-key="row"
                                    :row-extra="['maxReach' => $maxChurchReach, 'countField' => $countField]"
                                    :show-union-header="$isNasionalView"
                                    :show-division-header="$showDivisionHeader"
                                />
                            @else
                                @foreach ($churchRows as $row)
                                    @include('partials.analytics-church-row', ['row' => $row, 'maxReach' => $maxChurchReach])
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            @include('partials.hide-empty-rows')
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
                <x-export-button :url="route('export.institution-analytics.preview', array_filter(['institution_id' => $selectedInstitutionId, 'platform' => $selectedPlatform, 'union_id' => $selectedUnionId, 'conference_id' => $selectedConferenceId]))" />
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
        <x-analytics-hero-kpi :value="$latestPointInstitution->total_reach ?? 0" :growth-percent="$reachGrowthPercentInstitution" />

        <x-analytics-growth-grid
            :growth-over-time="$growthOverTimeInstitution"
            :growth-labels="$growthLabelsInstitution"
            :reach-subtitle="$reachSubtitleInstitution"
            :views-subtitle="$viewsSubtitleInstitution"
            :likes-subtitle="$likesSubtitleInstitution"
            :posts-subtitle="$postsSubtitleInstitution"
        />

        @if ($filteredInstitutions->isEmpty())
            <x-empty-state>
                {{ $institutions->isEmpty() ? __('analytics.no_institution_data') : __('analytics.no_institution_match_filter') }}
            </x-empty-state>
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
                                <x-platform-count-chips :group="$grandByPlatformInstitution" :count-field="$countField" />
                            </td>
                            <x-analytics-grand-total-cells :reach="$grandReachInstitution" :views="$grandViewsInstitution" :likes="$grandLikesInstitution" :posts="$grandPostsInstitution" />
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
                            <input type="checkbox" data-hide-empty-toggle="[data-institution-row]" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800">
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
                                <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('common.metric_views') }}</th>
                                <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('common.metric_likes') }}</th>
                                <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('common.metric_posts') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                            @if ($groupedInstitutionRows !== null)
                                <x-grouped-rows
                                    :grouped="$groupedInstitutionRows"
                                    prefix="institution"
                                    :colspan="6"
                                    row-view="partials.analytics-institution-row"
                                    row-key="row"
                                    :row-extra="['maxReach' => $maxInstitutionReach, 'countField' => $countField]"
                                    :show-union-header="$isNasionalView"
                                    :show-division-header="$showDivisionHeader"
                                />
                            @else
                                @foreach ($institutionRows as $row)
                                    @include('partials.analytics-institution-row', ['row' => $row, 'maxReach' => $maxInstitutionReach])
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            @include('partials.hide-empty-rows')
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
                <x-export-button :url="route('export.personal-analytics.preview', array_filter(['person_id' => $selectedPersonId, 'platform' => $selectedPlatform, 'union_id' => $selectedUnionId, 'conference_id' => $selectedConferenceId]))" />
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
        <x-analytics-hero-kpi :value="$latestPointPersonal->total_reach ?? 0" :growth-percent="$reachGrowthPercentPersonal" />

        <x-analytics-growth-grid
            :growth-over-time="$growthOverTimePersonal"
            :growth-labels="$growthLabelsPersonal"
            :reach-subtitle="$reachSubtitlePersonal"
            :views-subtitle="$viewsSubtitlePersonal"
            :likes-subtitle="$likesSubtitlePersonal"
            :posts-subtitle="$postsSubtitlePersonal"
        />

        @if ($filteredPeople->isEmpty())
            <x-empty-state>
                {{ $people->isEmpty() ? __('analytics.no_personal_data') : __('analytics.no_personal_match_filter') }}
            </x-empty-state>
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
                                <x-platform-count-chips :group="$grandByPlatformPersonal" :count-field="$countField" />
                            </td>
                            <x-analytics-grand-total-cells :reach="$grandReachPersonal" :views="$grandViewsPersonal" :likes="$grandLikesPersonal" :posts="$grandPostsPersonal" />
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
                            <input type="checkbox" data-hide-empty-toggle="[data-person-row]" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800">
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
                                <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('common.metric_views') }}</th>
                                <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('common.metric_likes') }}</th>
                                <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('common.metric_posts') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                            @if ($groupedPersonRows !== null)
                                <x-grouped-rows
                                    :grouped="$groupedPersonRows"
                                    prefix="person"
                                    :colspan="6"
                                    row-view="partials.analytics-person-row"
                                    row-key="row"
                                    :row-extra="['maxReach' => $maxPersonReach, 'countField' => $countField]"
                                    :show-union-header="$isNasionalView"
                                    :show-division-header="$showDivisionHeader"
                                />
                            @else
                                @foreach ($personRows as $row)
                                    @include('partials.analytics-person-row', ['row' => $row, 'maxReach' => $maxPersonReach])
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            @include('partials.hide-empty-rows')
        @endif
    </div>

    {{-- ===================== TAB: HASTAG ===================== --}}
    <div data-tab-panel="hastag">
        {{-- Same title-row-with-export-button shape as every other tab above (Organisasi/Gereja/
             Institusi/Personal) — this used to be its own separate block, stacked above the
             partial's own right-aligned button block, leaving a much taller gap here than any
             other tab has before its Filter card. --}}
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('hashtag.comparison_title') }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('hashtag.comparison_subtitle') }}</p>
            </div>
            @can('browse-directory-analytics')
                <x-export-button :url="route('export.hashtag.preview', array_filter([
                    'hashtag' => $hashtagData['selectedHashtagId'],
                    'platform' => $hashtagData['selectedPlatform'],
                    'union_id' => $hashtagData['selectedUnionId'],
                    'conference_id' => $hashtagData['selectedConferenceId'],
                ]))" />
            @endcan
        </div>

        @include('partials.hashtag-comparison-content', [
            'hideExportButton' => true,
            'hashtags' => $hashtagData['hashtags'],
            'platforms' => $hashtagData['platforms'],
            'lastUpdatedAt' => $hashtagData['lastUpdatedAt'],
            'rows' => $hashtagData['rows'],
            'grandTotalByPlatform' => $hashtagData['grandTotalByPlatform'],
            'grandTotal' => $hashtagData['grandTotal'],
            'growthLabels' => $hashtagData['growthLabels'],
            'growthShortLabels' => $hashtagData['growthShortLabels'],
            'growthDateKeys' => $hashtagData['growthDateKeys'],
            'growthValues' => $hashtagData['growthValues'],
            'isMonitoringWindow' => $hashtagData['isMonitoringWindow'],
            'selectedPostedFrom' => $hashtagData['selectedPostedFrom'],
            'selectedPostedTo' => $hashtagData['selectedPostedTo'],
            'posts' => $hashtagData['posts'],
            'selectedHashtagId' => $hashtagData['selectedHashtagId'],
            'selectedPlatform' => $hashtagData['selectedPlatform'],
            // Explicitly $hashtagData's own copies, not this page's top-level $isNasionalView/
            // $selectedUnionId/etc. (used by the other four tabs) — those happen to carry the
            // same values today since both are derived from the same viewer/request, but relying
            // on that coincidence via Blade's ambient scope-sharing would silently break the
            // moment the two ever computed something different.
            'isNasionalView' => $hashtagData['isNasionalView'],
            'isUniView' => $hashtagData['isUniView'],
            'unionOptions' => $hashtagData['unionOptions'],
            'conferenceOptions' => $hashtagData['conferenceOptions'],
            'selectedUnionId' => $hashtagData['selectedUnionId'],
            'selectedConferenceId' => $hashtagData['selectedConferenceId'],
            'clearUrl' => route('churches.analytics', ['tab' => 'hastag']),
            'platformParam' => 'hashtag_platform',
            'hiddenFields' => ['tab' => 'hastag'],
        ])
    </div>

    @include('partials.tab-script', ['activeTab' => $activeTab])
    @if ($groupedChurchRows !== null || $groupedPersonRows !== null || $groupedInstitutionRows !== null || $groupedOrganizationRows !== null)
        @include('partials.analytics-group-toggle')
    @endif
@endsection
