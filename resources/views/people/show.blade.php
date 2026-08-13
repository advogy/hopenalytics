@extends('layouts.app')

@section('title', $person->name . ' — ' . config('app.name'))

@section('content')
    @can('view-analytics')
        <a href="{{ route('churches.analytics', ['tab' => 'personal']) }}" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
            &larr; {{ __('common.back') }}
        </a>
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

    @if ($person->socials->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 p-12 text-center dark:border-slate-700">
            <p class="text-slate-500 dark:text-slate-400">{{ __('entity.no_socials') }}</p>
        </div>
    @endif

    @if ($person->socials->isNotEmpty())
        <x-growth-score-summary
            :score-history="$scoreHistory"
            :score-metrics="$scoreMetrics"
            :score-breakdown="$scoreBreakdown"
            :score-sample-count="$scoreSampleCount"
            :score-sample-sum="$scoreSampleSum"
        />
    @endif

    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($person->socials as $social)
            <x-social-account-card :social="$social" :history-rows="$history[$social->id] ?? collect()" :show-recent-content="false" />
        @endforeach
    </div>

    @foreach ($person->socials as $social)
        <x-social-history-table :social="$social" :history-rows="$history[$social->id] ?? collect()" />
    @endforeach
@endsection
