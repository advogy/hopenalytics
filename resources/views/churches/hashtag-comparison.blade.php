@extends('layouts.app')

@section('title', __('hashtag.comparison_title') . $scope->titleSuffix() . ' — ' . config('app.name'))

@section('content')
    <x-back-link :href="$scope->analyticsUrl()">{{ __('comparison.leaderboard_back_analytics') }}</x-back-link>

    {{-- Same title-row-with-export-button shape as leaderboard.blade.php/metric-comparison.blade.php/
         etc. — this used to be its own separate block, stacked above the partial's own
         right-aligned button block, leaving a taller gap here than every sibling comparison page. --}}
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('hashtag.comparison_title') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('hashtag.comparison_subtitle') }}</p>
        </div>
        @can('browse-directory-analytics')
            <x-export-button :url="route('export.hashtag.preview', array_filter([
                'hashtag' => $selectedHashtagId,
                'platform' => $selectedPlatform,
                'union_id' => $selectedUnionId,
                'conference_id' => $selectedConferenceId,
            ]))" />
        @endcan
    </div>

    @include('partials.hashtag-comparison-content', [
        'hideExportButton' => true,
        'hashtags' => $hashtags,
        'platforms' => $platforms,
        'lastUpdatedAt' => $lastUpdatedAt,
        'rows' => $rows,
        'grandTotalByPlatform' => $grandTotalByPlatform,
        'grandTotal' => $grandTotal,
        'growthLabels' => $growthLabels,
        'growthShortLabels' => $growthShortLabels,
        'growthDateKeys' => $growthDateKeys,
        'growthValues' => $growthValues,
        'isMonitoringWindow' => $isMonitoringWindow,
        'selectedPostedFrom' => $selectedPostedFrom,
        'selectedPostedTo' => $selectedPostedTo,
        'posts' => $posts,
        'selectedHashtagId' => $selectedHashtagId,
        'selectedPlatform' => $selectedPlatform,
        'isNasionalView' => $isNasionalView,
        'isUniView' => $isUniView,
        'unionOptions' => $unionOptions,
        'conferenceOptions' => $conferenceOptions,
        'selectedUnionId' => $selectedUnionId,
        'selectedConferenceId' => $selectedConferenceId,
        'formAction' => $scope->hashtagComparisonUrl(),
        'clearUrl' => $scope->hashtagComparisonUrl(),
    ])
@endsection
