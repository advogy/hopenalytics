@extends('layouts.app')

@section('title', __('comparison.platform_comparison_title', ['suffix' => $scope->titleSuffix()]) . ' — ' . $platformLabels[$platform] . ' — ' . config('app.name'))

@php
    // "semua" is the default — route it without the segment so /gereja/platform means "all platforms".
    $platformParam = $platform === 'semua' ? null : $platform;
@endphp

@section('content')
    <a href="{{ $scope->analyticsUrl() }}" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
        &larr; {{ __('comparison.leaderboard_back_analytics') }}
    </a>

    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('comparison.platform_comparison_title', ['suffix' => $scope->titleSuffix()]) }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ __('comparison.platform_overview_subtitle', ['platform' => $platform === 'semua' ? __('comparison.platform_overview_all') : $platformLabels[$platform], 'scope' => $scope->scopeNoun()]) }}
            </p>
        </div>
        @can('browse-directory-analytics')
            <x-export-button :url="$scope->exportPlatformOverviewUrl(['platform' => $platform])" />
        @endcan
    </div>

    @php $regionParams = array_filter(['union_id' => $selectedUnionId, 'conference_id' => $selectedConferenceId]); @endphp

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2">
            @foreach ($platformLabels as $value => $label)
                <a
                    href="{{ $scope->platformComparisonUrl(array_merge(array_filter(['platform' => $value === 'semua' ? null : $value, 'sort' => $sort === 'value' ? 'value' : null]), $regionParams)) }}"
                    class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-medium transition {{ $value === $platform ? 'border-blue-600 bg-blue-600 text-white' : 'border-black/10 bg-white text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700' }}"
                >
                    @if ($value !== 'semua')
                        <x-platform-icon :platform="$value" class="h-4.5 w-4.5" />
                    @endif
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <x-sort-toggle
            :sort="$sort"
            :delta-url="$scope->platformComparisonUrl(array_merge(array_filter(['platform' => $platformParam]), $regionParams))"
            :value-url="$scope->platformComparisonUrl(array_merge(array_filter(['platform' => $platformParam, 'sort' => 'value']), $regionParams))"
        />
    </div>

    @if ($platform !== 'semua' && ($isNasionalView || $isUniView))
        <x-filter-card>
            <form method="GET" action="{{ $scope->platformComparisonUrl(array_filter(['platform' => $platformParam])) }}" id="platform-overview-filter-form" class="flex flex-wrap items-center gap-3">
                <input type="hidden" name="sort" value="{{ $sort }}">

                @include('partials.analytics-region-filter', [
                    'prefix' => 'platform-overview',
                    'formId' => 'platform-overview-filter-form',
                    'isNasionalView' => $isNasionalView,
                    'isUniView' => $isUniView,
                    'unionOptions' => $unionOptions,
                    'conferenceOptions' => $conferenceOptions,
                    'selectedUnionId' => $selectedUnionId,
                    'selectedConferenceId' => $selectedConferenceId,
                ])

                @if ($selectedConferenceId || ($isNasionalView && $selectedUnionId))
                    <a href="{{ $scope->platformComparisonUrl(array_filter(['platform' => $platformParam, 'sort' => $sort === 'value' ? 'value' : null])) }}" class="text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
                        {{ __('common.reset_filter') }}
                    </a>
                @endif
            </form>
        </x-filter-card>
    @endif

    @if ($platform === 'semua')
        <x-platform-score-card
            :title="__('comparison.platform_score_title')"
            :subtitle="__('comparison.section_subtitle', ['count' => $platformScoreRows->count(), 'noun' => __('comparison.noun_platform'), 'basis' => __('comparison.sort_basis_score')])"
            :rows="$platformScoreRows"
            :platform-labels="$platformLabels"
            :scope="$scope"
        />
    @else
    <div id="metric-sections" class="grid gap-6 {{ $sections->count() > 1 ? 'lg:grid-cols-2' : '' }}">
        @foreach ($sections as $metric => $rows)
            @php
                $valueHeader = match (true) {
                    $metric !== 'reach' => $metricLabels[$metric],
                    $platform === 'youtube' => 'Subscribers',
                    $platform === 'semua' => __('comparison.reach_label'),
                    default => 'Followers',
                };
                $top = $rows->take(5);
                $sectionParams = array_filter(['platform' => $platformParam, 'metric' => $metric, 'sort' => $sort === 'value' ? 'value' : null]);
            @endphp
            <div class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
                <div class="mb-1 flex items-start justify-between gap-2">
                    <p class="font-bold text-slate-900 dark:text-white">{{ $valueHeader }} {{ $platformLabels[$platform] }}</p>
                    <div class="flex shrink-0 items-center gap-3 text-sm">
                        <a href="{{ $scope->platformComparisonUrl($sectionParams) }}" class="text-blue-600 hover:underline dark:text-blue-400">
                            {{ __('common.view_all') }}
                        </a>
                    </div>
                </div>
                <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('comparison.section_subtitle', ['count' => $rows->count(), 'noun' => $scope->noun(), 'basis' => $sort === 'delta' ? __('comparison.sort_basis_delta') : __('comparison.sort_basis_value', ['value' => $valueHeader])]) }}
                </p>

                @if ($top->isEmpty())
                    <p class="py-6 text-center text-sm text-slate-400 dark:text-slate-500">{{ __('comparison.no_data') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="text-slate-500 dark:text-slate-400">
                                    <th class="py-2 pr-2 font-medium">#</th>
                                    <th class="py-2 pr-2 font-medium">{{ $scope->nameLabel() }}</th>
                                    <th class="py-2 pr-2 text-right font-medium">{{ $valueHeader }}</th>
                                    <th class="py-2 text-right font-medium">{{ __('comparison.growth') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach ($top as $i => $row)
                                    <tr>
                                        <td class="py-2 pr-2 text-slate-400 dark:text-slate-500">{{ $i + 1 }}</td>
                                        <td class="py-2 pr-2">
                                            <a href="{{ $scope->showUrl($row[$scope->rowKey()]) }}" class="font-medium hover:text-blue-600 dark:hover:text-blue-400">
                                                {{ $row['label'] }}
                                            </a>
                                        </td>
                                        <td class="py-2 pr-2 text-right font-medium tabular-nums">{{ number_format($row['value']) }}</td>
                                        <td class="py-2 text-right tabular-nums">
                                            @if ($row['delta'] === null)
                                                <span class="text-slate-300 dark:text-slate-600">—</span>
                                            @else
                                                <span class="inline-flex items-center gap-1 font-medium {{ $row['delta'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : ($row['delta'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-400 dark:text-slate-500') }}">
                                                    <x-icon :name="$row['delta'] > 0 ? 'arrow-trending-up' : ($row['delta'] < 0 ? 'arrow-trending-down' : 'minus-small')" class="h-3.5 w-3.5" />
                                                    {{ $row['delta'] > 0 ? '+' : '' }}{{ number_format($row['delta']) }}
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
    @endif
@endsection
