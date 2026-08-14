@php $rowView = 'churches.partials.presentation-growth-row'; @endphp

@extends('layouts.presentation')

@section('title', __('presentation.title_growth') . $scope->titleSuffix())

@section('headerStat')
    <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('presentation.avg_weekly_growth_score') }}</p>
    <p class="text-4xl font-bold tabular-nums {{ $avgScore !== null && $avgScore < 0 ? 'text-red-600 dark:text-red-400' : '' }}">
        @if ($avgScore !== null)
            {{ $avgScore > 0 ? '+' : '' }}{{ number_format($avgScore, 1) }}%
        @else
            &ndash;
        @endif
    </p>
@endsection

@section('headerLinks')
    <a href="{{ $scope->presentationUrl() }}" title="{{ __('presentation.total_reach_icon_title') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-white">
        <x-icon name="globe-alt" class="h-4.5 w-4.5" />
    </a>
    <a href="{{ $scope->other()->presentationGrowthUrl() }}" title="{{ $scope->other()->labelCap() }}" class="inline-flex h-9 w-9 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-white">
        <x-icon :name="$scope->other()->icon()" class="h-4.5 w-4.5" />
    </a>
@endsection

@section('sidebarExtra')
    <div class="rounded-2xl border border-black/5 bg-white p-4 text-xs leading-relaxed text-slate-500 dark:border-white/5 dark:bg-[#0f1e33] dark:text-slate-400">
        {{ __('presentation.score_explanation', ['noun' => $scope->noun()]) }}
    </div>
@endsection
