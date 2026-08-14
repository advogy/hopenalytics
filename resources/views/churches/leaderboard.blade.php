@extends('layouts.app')

@section('title', $title . $scope->titleSuffix() . ' — ' . config('app.name'))

@section('content')
    <x-back-link :href="$scope->leaderboardBackUrl()">{{ $scope->leaderboardBackLabel() }}</x-back-link>

    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $title }}{{ $scope->titleSuffix() }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                @php $noun = $scope->isChurch() ? '' : ' ' . $scope->noun(); @endphp
                @if ($sort === 'value')
                    {{ __('comparison.leaderboard_subtitle_value', ['noun' => $noun, 'subtitle' => $subtitle]) }}
                @else
                    {{ __('comparison.leaderboard_subtitle_delta', ['noun' => $noun, 'subtitle' => $subtitle]) }}
                @endif
            </p>
        </div>
        @can('browse-directory-analytics')
            <x-export-button :url="$scope->exportLeaderboardUrl(array_filter(['metric' => $metric, 'sort' => $sort === 'value' ? 'value' : null, 'category' => $selectedCategory ?? null]))" />
        @endcan
    </div>

    @php $regionParams = array_filter(['union_id' => $selectedUnionId, 'conference_id' => $selectedConferenceId]); @endphp

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2">
            <x-pill-link :href="$scope->metricComparisonUrl(array_merge(array_filter(['sort' => $sort === 'value' ? 'value' : null]), $regionParams))">
                {{ __('comparison.sort_all') }}
            </x-pill-link>
            @foreach ($metricLabels as $value => $label)
                <x-pill-link
                    :href="$scope->leaderboardUrl(array_merge(array_filter(['metric' => $value, 'sort' => $sort === 'value' ? 'value' : null]), $regionParams))"
                    :active="$value === $metric"
                >
                    {{ $label }}
                </x-pill-link>
            @endforeach
        </div>

        <x-sort-toggle
            :sort="$sort"
            :delta-url="$scope->leaderboardUrl(array_merge(['metric' => $metric], $regionParams))"
            :value-url="$scope->leaderboardUrl(array_merge(['metric' => $metric, 'sort' => 'value'], $regionParams))"
        />
    </div>

    @if ($isNasionalView || $isUniView || $scope->type === 'gereja')
        <x-filter-card :clear-url="($selectedConferenceId || ($isNasionalView && $selectedUnionId) || ($selectedCategory ?? null)) ? $scope->leaderboardUrl(array_filter(['metric' => $metric, 'sort' => $sort === 'value' ? 'value' : null])) : null">
            <form method="GET" action="{{ $scope->leaderboardUrl(['metric' => $metric]) }}" id="church-leaderboard-filter-form" class="flex flex-wrap items-center gap-3">
                <input type="hidden" name="sort" value="{{ $sort }}">

                @if ($isNasionalView || $isUniView)
                    @include('partials.analytics-region-filter', [
                        'prefix' => 'church-leaderboard',
                        'formId' => 'church-leaderboard-filter-form',
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

    <x-leaderboard
        :title="$title"
        :subtitle="__('comparison.accounts_count', ['count' => $rows->count()])"
        :rows="$rows"
        :value-label="__('common.current')"
        :name-label="$scope->nameLabel()"
        :grouped-rows="$groupedRows"
        group-prefix="church-leaderboard"
        :is-nasional-view="$isNasionalView"
        :show-division-header="$showDivisionHeader"
    />

    @if ($groupedRows !== null)
        @include('partials.analytics-group-toggle')
    @endif
@endsection
