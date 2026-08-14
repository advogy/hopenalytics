@php
    $valueHeader = match (true) {
        $metric !== 'reach' => $metricLabels[$metric],
        $platform === 'youtube' => 'Subscribers',
        $platform === 'semua' => __('comparison.reach_label'),
        default => 'Followers',
    };
@endphp

@extends('layouts.app')

@section('title', __('comparison.platform_detail_title', ['metric' => $valueHeader, 'platform' => $platformLabels[$platform], 'suffix' => $scope->titleSuffix()]) . ' — ' . config('app.name'))

@php
    $platformParam = $platform === 'semua' ? null : $platform;
@endphp

@section('content')
    <x-back-link :href="$scope->platformComparisonUrl(array_filter(['platform' => $platformParam]))">{{ __('comparison.back_to_overview') }}</x-back-link>

    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('comparison.platform_comparison_title', ['suffix' => $scope->titleSuffix()]) }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                @if ($sort === 'delta')
                    {{ __('comparison.platform_comparison_subtitle_delta', ['count' => $rows->count(), 'noun' => $scope->noun(), 'metric' => $valueHeader, 'platform' => $platformLabels[$platform]]) }}
                @else
                    {{ __('comparison.platform_comparison_subtitle_value', ['count' => $rows->count(), 'noun' => $scope->noun(), 'value' => $valueHeader, 'platform' => $platformLabels[$platform]]) }}
                @endif
            </p>
        </div>
        @can('browse-directory-analytics')
            <x-export-button :url="$scope->exportPlatformComparisonUrl(array_filter(['platform' => $platform, 'metric' => $metric, 'sort' => $sort === 'value' ? 'value' : null, 'category' => $selectedCategory ?? null]))" />
        @endcan
    </div>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
            @if ($platform !== 'semua')
                <x-platform-icon :platform="$platform" class="h-4.5 w-4.5" />
            @endif
            <span class="font-medium text-slate-700 dark:text-slate-200">{{ $platformLabels[$platform] }}</span>
            <span class="text-slate-300 dark:text-slate-600">&middot;</span>
            <span>{{ $valueHeader }}</span>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            @if ($groupedRows !== null)
                <x-group-toggle-all-button scope="platform-detail" />
            @endif
            <x-sort-toggle
                :sort="$sort"
                :delta-url="$scope->platformComparisonUrl(array_filter(['platform' => $platformParam, 'metric' => $metric]))"
                :value-url="$scope->platformComparisonUrl(array_filter(['platform' => $platformParam, 'metric' => $metric, 'sort' => 'value']))"
            />
        </div>
    </div>

    @if ($isNasionalView || $isUniView || $scope->type === 'gereja')
        <x-filter-card :clear-url="($selectedConferenceId || ($isNasionalView && $selectedUnionId) || ($selectedCategory ?? null)) ? $scope->platformComparisonUrl(array_filter(['platform' => $platformParam, 'metric' => $metric, 'sort' => $sort === 'value' ? 'value' : null])) : null">
            <form method="GET" action="{{ $scope->platformComparisonUrl(array_filter(['platform' => $platformParam, 'metric' => $metric])) }}" id="platform-detail-filter-form" class="flex flex-wrap items-center gap-3">
                <input type="hidden" name="sort" value="{{ $sort }}">

                @if ($isNasionalView || $isUniView)
                    @include('partials.analytics-region-filter', [
                        'prefix' => 'platform-detail',
                        'formId' => 'platform-detail-filter-form',
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

    <div class="mb-8 rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <p class="mb-4 font-bold text-slate-900 dark:text-white">
            {{ __('comparison.value_per_entity', ['value' => $valueHeader, 'platform' => $platformLabels[$platform], 'label' => $scope->labelCap()]) }}
        </p>
        <x-bar-chart :rows="$rows" />
    </div>

    @if ($rows->isNotEmpty())
        <div class="overflow-x-auto rounded-2xl border border-black/5 dark:border-white/5">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/60">
                    <tr>
                        <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">#</th>
                        <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ $scope->nameLabel() }}</th>
                        <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">
                            {{ $valueHeader }}
                        </th>
                        <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('comparison.weekly_growth') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                    @if ($groupedRows !== null)
                        <x-grouped-rows
                            :grouped="$groupedRows"
                            prefix="platform-detail"
                            :colspan="4"
                            row-view="partials.platform-comparison-row"
                            row-key="row"
                            :row-extra="['scope' => $scope]"
                            :show-union-header="$isNasionalView"
                            :show-division-header="$showDivisionHeader"
                        />
                    @else
                        @foreach ($rows as $i => $row)
                            @include('partials.platform-comparison-row', ['row' => $row, 'index' => $i, 'scope' => $scope])
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>
    @endif

    @if ($groupedRows !== null)
        @include('partials.analytics-group-toggle')
    @endif
@endsection
