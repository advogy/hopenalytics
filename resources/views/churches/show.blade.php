@php
    $categoryLabels = ['gereja' => __('directory.church_accounts'), 'umum' => __('directory.general_accounts')];
    $socialsByCategory = $church->socials->groupBy(fn ($social) => $social->category->value);
@endphp

@extends('layouts.app')

@section('title', $church->name . ' — ' . config('app.name'))

@section('content')
    @can('view-analytics')
        <a href="{{ route('churches.analytics', ['tab' => 'gereja']) }}" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
            &larr; {{ __('nav.back_to_analytics') }}
        </a>
    @endcan

    <div class="mb-8 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $church->name }}</h1>
            @if ($church->city)
                <p class="text-slate-500 dark:text-slate-400">{{ $church->city }}</p>
            @endif
        </div>

        <div class="flex items-center gap-3">
            {{-- Only the admin_gereja actually assigned to THIS specific church gets this
                 shortcut — they have no other path to management (Kelola Akun has no Gereja
                 tab for gereja-level). admin_uni/daerah/nasional can technically can:update
                 many churches, but per the user's explicit call, they manage exclusively
                 through Kelola Akun — this page stays read-only for them, even though
                 the underlying policy would allow it. The extra can:update check (on top of
                 the level/church_id match) is what excludes pimpinan_gereja specifically —
                 same "gereja" level, but read-only, so update() correctly fails for them. --}}
            @if (auth()->user()->role?->level() === 'gereja' && auth()->user()->church_id === $church->id && auth()->user()->can('update', $church))
                <a
                    href="{{ route('churches.socials.index', $church) }}"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-black/10 bg-white px-4 py-2 text-sm font-medium shadow-sm transition hover:bg-slate-50 dark:border-white/10 dark:bg-slate-800 dark:hover:bg-slate-700"
                >
                    {{ __('nav.manage_accounts') }}
                </a>
            @endif
            @can('export', $church)
                <x-export-button :url="route('export.church.preview', $church)" />
            @endcan
        </div>
    </div>

    @if ($ownStats)
        <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-stat-card
                :href="route('churches.socials.index', $church)"
                icon="arrow-trending-up"
                :label="__('entity.own_church_reach')"
                :value="number_format($ownStats['churchReach'])"
            />
            <x-stat-card
                :href="route('churches.socials.index', $church)"
                icon="globe-alt"
                :label="__('entity.own_church_socials')"
                :value="$ownStats['totalAccounts']"
            />
            <x-stat-card
                :href="auth()->user()->person ? route('people.socials.index', auth()->user()->person) : route('profile.edit', ['tab' => 'sosial'])"
                icon="user"
                :label="__('entity.own_personal_socials')"
                :value="$ownStats['personalAccounts']"
            />
            <x-stat-card
                href="#growth-score"
                icon="arrow-trending-up"
                :label="__('entity.own_weekly_growth')"
                :value="($ownStats['weeklyGrowth'] > 0 ? '+' : '') . number_format($ownStats['weeklyGrowth'])"
            />
        </div>
    @endif

    @if ($church->socials->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 p-12 text-center dark:border-slate-700">
            <p class="text-slate-500 dark:text-slate-400">{{ __('entity.no_socials') }}</p>
        </div>
    @endif

    @if ($church->socials->isNotEmpty())
        <x-growth-score-summary :score-history="$scoreHistory" :anchored="true" />
    @endif

    @foreach (['gereja', 'umum'] as $category)
        @continue($socialsByCategory->get($category, collect())->isEmpty())

        <h2 class="mb-3 mt-8 text-lg font-medium first:mt-0">{{ $categoryLabels[$category] }}</h2>

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($socialsByCategory[$category] as $social)
                <x-social-account-card :social="$social" :history-rows="$history[$social->id] ?? collect()" />
            @endforeach
        </div>
    @endforeach

    @foreach ($church->socials as $social)
        <x-social-history-table
            :social="$social"
            :history-rows="$history[$social->id] ?? collect()"
            :category-label="$categoryLabels[$social->category->value]"
        />
    @endforeach
@endsection
