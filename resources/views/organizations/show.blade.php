{{--
    Shared "show" page for both Union and Conference (see ChurchDashboardController::showUnion()/
    showConference()) — same shape as churches.show/institutions.show, minus their $ownStats
    shortcut cards (no "this is my one entity" viewer concept exists for Union/Conference; an
    admin_uni/admin_daerah already manages their whole region via Kelola Akun instead).
--}}
@php
    $isUnion = $organization instanceof \App\Models\Union;
    $manageRoute = $isUnion ? 'admin.unions.socials.index' : 'admin.conferences.socials.index';
    $levelLabel = $isUnion ? __('analytics.organization_level_union') : __('analytics.organization_level_conference');
@endphp

@extends('layouts.app')

@section('title', $organization->name . ' — ' . config('app.name'))

@section('content')
    @can('view-analytics')
        <x-back-link :href="route('churches.analytics', ['tab' => 'organisasi'])">{{ __('nav.back_to_analytics') }}</x-back-link>
    @endcan

    <div class="mb-8 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $organization->name }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $levelLabel }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            @can('update', $organization)
                <a
                    href="{{ route($manageRoute, $organization) }}"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-black/10 bg-white px-4 py-2 text-sm font-medium shadow-sm transition hover:bg-slate-50 dark:border-white/10 dark:bg-slate-800 dark:hover:bg-slate-700"
                >
                    {{ __('nav.manage_accounts') }}
                </a>
            @endcan
        </div>
    </div>

    <x-entity-social-summary
        :socials="$organization->socials"
        :history="$history"
        :score-history="$scoreHistory"
        :score-metrics="$scoreMetrics"
        :score-breakdown="$scoreBreakdown"
        :score-sample-count="$scoreSampleCount"
        :score-sample-sum="$scoreSampleSum"
        :anchored="true"
    />
@endsection
