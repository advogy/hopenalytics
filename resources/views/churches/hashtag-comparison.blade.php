@extends('layouts.app')

@section('title', __('hashtag.comparison_title') . $scope->titleSuffix() . ' — ' . config('app.name'))

@section('content')
    <x-back-link :href="$scope->analyticsUrl()">{{ __('comparison.leaderboard_back_analytics') }}</x-back-link>

    <div class="mb-6">
        <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('hashtag.comparison_title') }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('hashtag.comparison_subtitle') }}</p>
    </div>

    @include('partials.hashtag-comparison-content', [
        'hashtags' => $hashtags,
        'platforms' => $platforms,
        'lastUpdatedAt' => $lastUpdatedAt,
        'rows' => $rows,
        'grandTotalByPlatform' => $grandTotalByPlatform,
        'grandTotal' => $grandTotal,
        'posts' => $posts,
        'selectedHashtagId' => $selectedHashtagId,
        'selectedPlatform' => $selectedPlatform,
        'formAction' => $scope->hashtagComparisonUrl(),
        'clearUrl' => $scope->hashtagComparisonUrl(),
    ])
@endsection
