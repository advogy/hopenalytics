@extends('layouts.app')

@section('title', $title . $scope->titleSuffix() . ' — ' . config('app.name'))

@section('content')
    <a href="{{ $scope->leaderboardBackUrl() }}" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
        &larr; {{ $scope->leaderboardBackLabel() }}
    </a>

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
            @unless ($scope->isOrganization())
                <x-export-button :url="$scope->exportLeaderboardUrl(array_filter(['metric' => $metric, 'sort' => $sort === 'value' ? 'value' : null]))" />
            @endunless
        @endcan
    </div>

    @php $regionParams = array_filter(['union_id' => $selectedUnionId, 'conference_id' => $selectedConferenceId]); @endphp

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2">
            <a
                href="{{ $scope->metricComparisonUrl(array_merge(array_filter(['sort' => $sort === 'value' ? 'value' : null]), $regionParams)) }}"
                class="rounded-full border px-3 py-1.5 text-sm font-medium transition border-black/10 bg-white text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
            >
                {{ __('comparison.sort_all') }}
            </a>
            @foreach ($metricLabels as $value => $label)
                <a
                    href="{{ $scope->leaderboardUrl(array_merge(array_filter(['metric' => $value, 'sort' => $sort === 'value' ? 'value' : null]), $regionParams)) }}"
                    class="rounded-full border px-3 py-1.5 text-sm font-medium transition {{ $value === $metric ? 'border-blue-600 bg-blue-600 text-white' : 'border-black/10 bg-white text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700' }}"
                >
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <x-sort-toggle
            :sort="$sort"
            :delta-url="$scope->leaderboardUrl(array_merge(['metric' => $metric], $regionParams))"
            :value-url="$scope->leaderboardUrl(array_merge(['metric' => $metric, 'sort' => 'value'], $regionParams))"
        />
    </div>

    @if ($isNasionalView || $isUniView)
        <x-filter-card>
            <form method="GET" action="{{ $scope->leaderboardUrl(['metric' => $metric]) }}" id="church-leaderboard-filter-form" class="flex flex-wrap items-center gap-3">
                <input type="hidden" name="sort" value="{{ $sort }}">

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

                @if ($selectedConferenceId || ($isNasionalView && $selectedUnionId))
                    <a href="{{ $scope->leaderboardUrl(array_filter(['metric' => $metric, 'sort' => $sort === 'value' ? 'value' : null])) }}" class="text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
                        {{ __('common.reset_filter') }}
                    </a>
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
    />

    @if ($groupedRows !== null)
        @include('partials.analytics-group-toggle')
    @endif
@endsection
