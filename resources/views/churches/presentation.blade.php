@php $rowView = 'churches.partials.presentation-row'; @endphp

@extends('layouts.presentation')

@section('title', __('presentation.title') . $scope->titleSuffix())

@section('headerStat')
    <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('presentation.total_reach_all', ['label' => $scope->labelCap()]) }}</p>
    <p class="text-4xl font-bold tabular-nums">{{ number_format($totalReach) }}</p>
@endsection

@section('headerLinks')
    <a href="{{ $scope->presentationGrowthUrl() }}" title="{{ __('presentation.growth_icon_title') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-white">
        <x-icon name="arrow-trending-up" class="h-4.5 w-4.5" />
    </a>
    <a href="{{ $scope->other()->presentationUrl() }}" title="{{ $scope->other()->labelCap() }}" class="inline-flex h-9 w-9 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-white">
        <x-icon :name="$scope->other()->icon()" class="h-4.5 w-4.5" />
    </a>
@endsection
