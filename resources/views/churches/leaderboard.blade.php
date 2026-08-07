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
            <x-export-button :url="$scope->exportLeaderboardUrl(array_filter(['metric' => $metric, 'sort' => $sort === 'value' ? 'value' : null, 'category' => $selectedCategory ?? null]))" />
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
                    <label class="relative">
                        <x-icon name="building-office" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <select
                            name="category"
                            onchange="this.form.submit()"
                            class="appearance-none rounded-full border border-black/10 bg-slate-50 py-2.5 pr-10 pl-9 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-100 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                        >
                            <option value="">{{ __('common.all_categories') }}</option>
                            <option value="gereja" @selected(($selectedCategory ?? null) === 'gereja')>{{ __('directory.church_accounts') }}</option>
                            <option value="umum" @selected(($selectedCategory ?? null) === 'umum')>{{ __('directory.general_accounts') }}</option>
                        </select>
                        <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                    </label>
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
