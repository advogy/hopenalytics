@extends('layouts.app')

@section('title', $person->name . ' — ' . config('app.name'))

@section('content')
    @can('view-analytics')
        <x-back-link :href="route('churches.analytics', ['tab' => 'personal'])">{{ __('common.back') }}</x-back-link>
    @endcan

    <div class="mb-8 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $person->name }}</h1>
            <p class="text-slate-500 dark:text-slate-400">{{ __('entity.personal_account') }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            {{-- A plain member's own /dashboard (akun-saya) renders this same view — this is
                 their only guaranteed path to actually manage their accounts from their
                 landing page, since Profil Saya's Media Sosial tab is the "real" management
                 UI but isn't linked from here otherwise. Everyone else with can:update (e.g.
                 an admin who arrived via Analitik & Grafik Personal) gets a shortcut too,
                 instead of hunting for this row again in Kelola Akun. --}}
            @can('update', $person)
                <a
                    href="{{ route('people.socials.index', $person) }}"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-black/10 bg-white px-4 py-2 text-sm font-medium shadow-sm transition hover:bg-slate-50 dark:border-white/10 dark:bg-slate-800 dark:hover:bg-slate-700"
                >
                    {{ __('nav.manage_accounts') }}
                </a>
            @endcan
            @can('export', $person)
                <x-export-button :url="route('export.person.preview', $person)" />
            @endcan
        </div>
    </div>

    <x-entity-social-summary
        :socials="$person->socials"
        :history="$history"
        :score-history="$scoreHistory"
        :score-metrics="$scoreMetrics"
        :score-breakdown="$scoreBreakdown"
        :score-sample-count="$scoreSampleCount"
        :score-sample-sum="$scoreSampleSum"
        :show-recent-content="false"
    />
@endsection
