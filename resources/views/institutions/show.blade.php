@extends('layouts.app')

@section('title', $institution->name . ' — ' . config('app.name'))

@section('content')
    @can('view-analytics')
        <a href="{{ route('churches.analytics', ['tab' => 'institusi']) }}" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
            &larr; {{ __('nav.back_to_analytics') }}
        </a>
    @endcan

    <div class="mb-8 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $institution->name }}</h1>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            {{-- Same reasoning as churches.show's Kelola Akun shortcut: only the admin_institusi
                 actually bound to THIS institution gets it. admin_uni/daerah/nasional manage
                 exclusively through Kelola Akun, per the user's explicit call — this page
                 stays read-only for them. --}}
            @if (auth()->user()->role?->level() === 'institusi' && auth()->user()->institution_id === $institution->id && auth()->user()->can('update', $institution))
                <a
                    href="{{ route('admin.institutions.socials.index', $institution) }}"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-black/10 bg-white px-4 py-2 text-sm font-medium shadow-sm transition hover:bg-slate-50 dark:border-white/10 dark:bg-slate-800 dark:hover:bg-slate-700"
                >
                    {{ __('nav.manage_accounts') }}
                </a>
            @endif
            @can('export', $institution)
                <x-export-button :url="route('export.institution.preview', $institution)" />
            @endcan
        </div>
    </div>

    @if ($ownStats)
        <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-stat-card
                :href="route('admin.institutions.socials.index', $institution)"
                icon="arrow-trending-up"
                :label="__('entity.own_institution_reach')"
                :value="number_format($ownStats['reach'])"
            />
            <x-stat-card
                :href="route('admin.institutions.socials.index', $institution)"
                icon="globe-alt"
                :label="__('entity.own_institution_socials')"
                :value="$ownStats['totalAccounts']"
            />
            <x-stat-card
                href="#growth-score"
                icon="arrow-trending-up"
                :label="__('entity.own_weekly_growth')"
                :value="($ownStats['weeklyGrowth'] > 0 ? '+' : '') . number_format($ownStats['weeklyGrowth'])"
            />
        </div>
    @endif

    @if ($institution->socials->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 p-12 text-center dark:border-slate-700">
            <p class="text-slate-500 dark:text-slate-400">{{ __('entity.no_socials') }}</p>
        </div>
    @endif

    @if ($institution->socials->isNotEmpty())
        <x-growth-score-summary
            :score-history="$scoreHistory"
            :score-metrics="$scoreMetrics"
            :score-breakdown="$scoreBreakdown"
            :score-sample-count="$scoreSampleCount"
            :score-sample-sum="$scoreSampleSum"
            :anchored="true"
        />

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($institution->socials as $social)
                <x-social-account-card :social="$social" :history-rows="$history[$social->id] ?? collect()" />
            @endforeach
        </div>
    @endif

    @foreach ($institution->socials as $social)
        <x-social-history-table :social="$social" :history-rows="$history[$social->id] ?? collect()" />
    @endforeach
@endsection
