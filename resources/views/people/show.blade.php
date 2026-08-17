@extends('layouts.app')

@section('title', $person->name . ' — ' . config('app.name'))

@section('content')
    {{-- $isOwnPerson (self-viewing your own linked Person, see PersonController::show()) gates
         every dashboard-only widget below: the account-menu dropdown in layouts.app always links
         to Profil Saya regardless of role, so a self-viewer is never stranded without the
         Kelola Akun shortcut hidden further down — Media Sosial management already lives there
         (see profile/edit.blade.php's "sosial" tab). --}}
    {{-- Only makes sense when you actually drilled in from Analitik & Grafik Personal (e.g. an
         admin clicking a name in the leaderboard) — your own /dashboard is a landing page, not
         somewhere you navigated "into", so there's nothing to go "back" from there. --}}
    @can('view-analytics')
        @unless ($isOwnPerson)
            <x-back-link :href="route('churches.analytics', ['tab' => 'personal'])">{{ __('common.back') }}</x-back-link>
        @endunless
    @endcan

    <div class="mb-8 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $person->name }}</h1>
            <p class="text-slate-500 dark:text-slate-400">
                {{ $isOwnPerson ? __('entity.dashboard_subtitle') : __('entity.personal_account') }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            {{-- A plain member's own /dashboard (akun-saya) renders this same view, but Profil
                 Saya's Media Sosial tab is the real management UI now (see
                 profile/edit.blade.php) — showing this shortcut there too is redundant. Every
                 other viewer with can:update (e.g. an admin who arrived via Analitik & Grafik
                 Personal) still gets it, instead of hunting for this row again in Kelola Akun. --}}
            @can('update', $person)
                @unless ($isOwnPerson)
                    <a
                        href="{{ route('people.socials.index', $person) }}"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-black/10 bg-white px-4 py-2 text-sm font-medium shadow-sm transition hover:bg-slate-50 dark:border-white/10 dark:bg-slate-800 dark:hover:bg-slate-700"
                    >
                        {{ __('nav.manage_accounts') }}
                    </a>
                @endunless
            @endcan
            @can('export', $person)
                <x-export-button :url="route('export.person.preview', $person)" />
            @endcan
        </div>
    </div>

    {{-- Onboarding: a full welcome card only for a brand-new member with no region reported yet
         (the most "day one" state); once a region is set, a missing social account gets a much
         lighter single-line nudge instead — nothing shown here at all once both are done. --}}
    @if ($isOwnPerson && ! $hasRegion)
        <div class="mb-8 rounded-2xl border border-blue-100 bg-blue-50 p-6 dark:border-blue-900 dark:bg-blue-950/40">
            <h2 class="mb-1 text-lg font-bold text-slate-900 dark:text-white">
                {{ __('entity.dashboard_onboarding_title', ['name' => $person->name]) }}
            </h2>
            <p class="mb-4 text-sm text-slate-600 dark:text-slate-300">{{ __('entity.dashboard_onboarding_body') }}</p>
            <div class="flex flex-wrap gap-3">
                <a
                    href="{{ route('profile.edit', ['tab' => 'personal']) }}"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700"
                >
                    {{ __('entity.dashboard_onboarding_set_region') }}
                </a>
                @if ($person->socials->isEmpty())
                    <a
                        href="{{ route('profile.edit', ['tab' => 'sosial']) }}"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-black/10 bg-white px-4 py-2 text-sm font-medium shadow-sm transition hover:bg-slate-50 dark:border-white/10 dark:bg-slate-800 dark:hover:bg-slate-700"
                    >
                        {{ __('entity.dashboard_onboarding_add_social') }}
                    </a>
                @endif
            </div>
        </div>
    @elseif ($isOwnPerson && $person->socials->isEmpty())
        <p class="mb-8 text-sm text-slate-500 dark:text-slate-400">{{ __('entity.dashboard_add_social_nudge') }}</p>
    @endif

    {{-- "Tujuan Bersama" — same Goal-progress rows/component the admin "Ringkasan" dashboard
         uses (churches/index.blade.php), scoped by the current viewer's own role (see
         BuildsLeaderboards::goalProgressRows()); empty for institusi-level viewers, same as
         there. --}}
    @if ($isOwnPerson && $goalRows->isNotEmpty())
        <div class="mb-8">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('entity.dashboard_goal_title') }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $goalRows->first()['scopeLabel'] }}</p>
            </div>
            <div class="grid grid-cols-1 gap-4">
                @foreach ($goalRows as $row)
                    <x-goal-card
                        :label="$row['label']"
                        :year="$row['year']"
                        :target="$row['target']"
                        :current="$row['current']"
                        :percent="$row['percent']"
                    />
                @endforeach
            </div>
        </div>
    @endif

    {{-- "Pertumbuhan Wilayah Anda" — a different scope than the goal rows above: this rolls up
         ChurchSocial growth for the Person's own self-reported union/conference/church (see
         PersonController::resolvePersonRegionSocials()), not the viewer's admin role. --}}
    @if ($isOwnPerson && $regionLabel)
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('entity.dashboard_region_growth_title') }}</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $regionLabel }}</p>
        </div>
        <x-growth-score-summary
            :score-history="$regionScoreHistory"
            :score-metrics="$regionScoreMetrics"
            :score-breakdown="$regionScoreBreakdown"
            :score-sample-count="$regionScoreSampleCount"
            :score-sample-sum="$regionScoreSampleSum"
        />
    @endif

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
