@extends('layouts.app')

@section('title', __('comparison.metric_comparison_title', ['label' => $scope->labelCap()]) . ' — ' . config('app.name'))

@section('content')
    <a href="{{ $scope->analyticsUrl() }}" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
        &larr; {{ __('comparison.leaderboard_back_analytics') }}
    </a>

    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('comparison.metric_comparison_title', ['label' => $scope->labelCap()]) }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ __('comparison.metric_comparison_subtitle_score', ['scope' => $scope->isChurch() ? __('comparison.for_all_churches') : __('comparison.for_all_personal')]) }}
            </p>
        </div>
        <x-export-button :url="$scope->exportMetricComparisonUrl()" />
    </div>

    <div class="mb-6 flex flex-wrap gap-2">
        <a
            href="{{ $scope->metricComparisonUrl() }}"
            class="rounded-full border px-3 py-1.5 text-sm font-medium transition border-blue-600 bg-blue-600 text-white"
        >
            {{ __('comparison.sort_all') }}
        </a>
        @foreach ($metricLabels as $value => $label)
            <a
                href="{{ $scope->leaderboardUrl(['metric' => $value]) }}"
                class="rounded-full border px-3 py-1.5 text-sm font-medium transition border-black/10 bg-white text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
            >
                {{ $label }}
            </a>
        @endforeach
    </div>

    <x-growth-score-card
        :title="__('comparison.growth_score_title')"
        :subtitle="__('comparison.section_subtitle', ['count' => $scoreRows->count(), 'noun' => $scope->isChurch() ? __('comparison.scope_church') : __('comparison.scope_personal'), 'basis' => __('comparison.sort_basis_score')])"
        :rows="$scoreRows"
    />
@endsection
