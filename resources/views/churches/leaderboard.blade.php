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
            <x-export-button :url="$scope->exportLeaderboardUrl(array_filter(['metric' => $metric, 'sort' => $sort === 'value' ? 'value' : null]))" />
        @endcan
    </div>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2">
            <a
                href="{{ $scope->metricComparisonUrl(array_filter(['sort' => $sort === 'value' ? 'value' : null])) }}"
                class="rounded-full border px-3 py-1.5 text-sm font-medium transition border-black/10 bg-white text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
            >
                {{ __('comparison.sort_all') }}
            </a>
            @foreach ($metricLabels as $value => $label)
                <a
                    href="{{ $scope->leaderboardUrl(array_filter(['metric' => $value, 'sort' => $sort === 'value' ? 'value' : null])) }}"
                    class="rounded-full border px-3 py-1.5 text-sm font-medium transition {{ $value === $metric ? 'border-blue-600 bg-blue-600 text-white' : 'border-black/10 bg-white text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700' }}"
                >
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <x-sort-toggle
            :sort="$sort"
            :delta-url="$scope->leaderboardUrl(['metric' => $metric])"
            :value-url="$scope->leaderboardUrl(['metric' => $metric, 'sort' => 'value'])"
        />
    </div>

    <x-leaderboard :title="$title" :subtitle="__('comparison.accounts_count', ['count' => $rows->count()])" :rows="$rows" :value-label="__('common.current')" :name-label="$scope->nameLabel()" />
@endsection
