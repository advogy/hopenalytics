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
        <a href="{{ route('churches.analytics', ['tab' => 'organisasi']) }}" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
            &larr; {{ __('nav.back_to_analytics') }}
        </a>
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

    @if ($organization->socials->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 p-12 text-center dark:border-slate-700">
            <p class="text-slate-500 dark:text-slate-400">{{ __('entity.no_socials') }}</p>
        </div>
    @endif

    @if ($organization->socials->isNotEmpty())
        <x-growth-score-summary :score-history="$scoreHistory" :anchored="true" />

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($organization->socials as $social)
                <x-social-account-card :social="$social" :history-rows="$history[$social->id] ?? collect()" />
            @endforeach
        </div>
    @endif

    @foreach ($organization->socials as $social)
        <x-social-history-table :social="$social" :history-rows="$history[$social->id] ?? collect()" />
    @endforeach
@endsection
