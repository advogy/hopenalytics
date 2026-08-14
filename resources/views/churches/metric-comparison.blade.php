@extends('layouts.app')

@section('title', __('comparison.metric_comparison_title', ['label' => $scope->labelCap()]) . ' — ' . config('app.name'))

@section('content')
    <x-back-link :href="$scope->analyticsUrl()">{{ __('comparison.leaderboard_back_analytics') }}</x-back-link>

    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('comparison.metric_comparison_title', ['label' => $scope->labelCap()]) }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ __('comparison.metric_comparison_subtitle_score', ['scope' => $scope->forAllLabel()]) }}
            </p>
        </div>
        @can('browse-directory-analytics')
            <x-export-button :url="$scope->exportMetricComparisonUrl(array_filter(['category' => $selectedCategory ?? null]))" />
        @endcan
    </div>

    @php $regionParams = array_filter(['union_id' => $selectedUnionId, 'conference_id' => $selectedConferenceId]); @endphp

    <div class="mb-6 flex flex-wrap gap-2">
        <x-pill-link :href="$scope->metricComparisonUrl($regionParams)" active>
            {{ __('comparison.sort_all') }}
        </x-pill-link>
        @foreach ($metricLabels as $value => $label)
            <x-pill-link :href="$scope->leaderboardUrl(array_merge(['metric' => $value], $regionParams))">
                {{ $label }}
            </x-pill-link>
        @endforeach
    </div>

    @if ($isNasionalView || $isUniView || $scope->type === 'gereja')
        <x-filter-card :clear-url="($selectedConferenceId || ($isNasionalView && $selectedUnionId) || ($selectedCategory ?? null)) ? $scope->metricComparisonUrl() : null">
            <form method="GET" action="{{ $scope->metricComparisonUrl() }}" id="church-metric-filter-form" class="flex flex-wrap items-center gap-3">
                @if ($isNasionalView || $isUniView)
                    @include('partials.analytics-region-filter', [
                        'prefix' => 'church-metric',
                        'formId' => 'church-metric-filter-form',
                        'isNasionalView' => $isNasionalView,
                        'isUniView' => $isUniView,
                        'unionOptions' => $unionOptions,
                        'conferenceOptions' => $conferenceOptions,
                        'selectedUnionId' => $selectedUnionId,
                        'selectedConferenceId' => $selectedConferenceId,
                    ])
                @endif

                @if ($scope->type === 'gereja')
                    <x-category-filter :selected-category="$selectedCategory ?? null" />
                @endif
            </form>
        </x-filter-card>
    @endif

    <x-growth-score-card
        :title="__('comparison.growth_score_title')"
        :subtitle="__('comparison.section_subtitle', ['count' => $scoreRows->count(), 'noun' => $scope->scopeNoun(), 'basis' => __('comparison.sort_basis_score')])"
        :rows="$scoreRows"
        :grouped-rows="$groupedScoreRows"
        group-prefix="church-metric"
        :is-nasional-view="$isNasionalView"
        :scope="$scope"
    />

    @if ($groupedScoreRows !== null)
        @include('partials.analytics-group-toggle')
    @endif
@endsection
