@php
    $platformLabels = ['youtube' => 'YouTube', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook'];
@endphp

@extends('layouts.app')

@section('title', __('nav.directory') . ' — ' . config('app.name'))

@section('content')
    <a href="{{ route('churches.index') }}" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
        &larr; {{ __('common.back_to_dashboard') }}
    </a>

    <div class="mb-6">
        <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('nav.directory') }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('directory.subtitle') }}</p>
    </div>

    <div class="mb-6 flex gap-2 overflow-x-auto border-b border-black/5 dark:border-white/5">
        <button type="button" data-tab-button="organisasi" class="border-b-2 px-4 py-2.5 text-sm font-medium transition">
            {{ __('comparison.organization_label') }}
        </button>
        <button type="button" data-tab-button="gereja" class="border-b-2 px-4 py-2.5 text-sm font-medium transition">
            {{ __('common.church') }}
        </button>
        <button type="button" data-tab-button="institusi" class="border-b-2 px-4 py-2.5 text-sm font-medium transition">
            {{ __('common.institution') }}
        </button>
        <button type="button" data-tab-button="personal" class="border-b-2 px-4 py-2.5 text-sm font-medium transition">
            {{ __('common.personal') }}
        </button>
    </div>

    <x-filter-card>
        <form id="directory-filter-form" method="GET" class="flex flex-wrap items-center gap-3">
            <input type="hidden" name="tab" data-tab-hidden-field value="{{ $activeTab }}">

            {{--
                The directory is a public, unscoped listing (see directory()'s comment) — every
                viewer gets the full nasional picker regardless of role, unlike the region filter
                on Analitik & Grafik/Perbandingan Metrik/Perbandingan Platform, which narrows to
                what the viewer's own role can already see.
            --}}
            @include('partials.analytics-region-filter', [
                'prefix' => 'directory',
                'formId' => 'directory-filter-form',
                'isNasionalView' => true,
                'isUniView' => false,
                'unionOptions' => $unionOptions,
                'conferenceOptions' => $conferenceOptions,
                'selectedUnionId' => $selectedUnionId,
                'selectedConferenceId' => $selectedConferenceId,
            ])

            <label class="relative w-full sm:w-auto">
                <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                    type="text"
                    name="search"
                    value="{{ $search }}"
                    placeholder="{{ __('directory.search_placeholder_'.$activeTab) }}"
                    data-tab-placeholder="{{ json_encode(['gereja' => __('directory.search_placeholder_gereja'), 'personal' => __('directory.search_placeholder_personal'), 'institusi' => __('directory.search_placeholder_institusi'), 'organisasi' => __('directory.search_placeholder_organisasi')]) }}"
                    class="w-full sm:w-56 rounded-full border border-black/10 bg-slate-50 py-2.5 pr-4 pl-9 text-sm font-medium text-slate-700 shadow-sm transition placeholder:font-normal placeholder:text-slate-400 hover:bg-slate-100 focus:bg-white focus:outline-none dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:placeholder:text-slate-500 dark:hover:bg-slate-700 dark:focus:bg-slate-800"
                >
            </label>

            <label class="relative">
                <x-icon name="globe-alt" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <select
                    name="platform"
                    onchange="this.form.submit()"
                    class="appearance-none rounded-full border border-black/10 bg-slate-50 py-2.5 pr-10 pl-9 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-100 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                >
                    <option value="">{{ __('common.all_social_media') }}</option>
                    @foreach ($platformLabels as $value => $label)
                        <option value="{{ $value }}" @selected($selectedPlatform === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
            </label>

            <button type="submit" class="rounded-full bg-blue-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
                {{ __('common.search') }}
            </button>

            @if ($selectedPlatform || $search || $autoFetch || $hideEmptyChurches || $hideEmptyPeople || $hideEmptyInstitutions || $hideEmptyOrganizations || $selectedUnionId || $selectedConferenceId)
                <a href="{{ route('churches.directory', ['tab' => $activeTab]) }}" class="text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
                    {{ __('common.reset_filter') }}
                </a>
            @endif
        </form>
    </x-filter-card>

    {{-- ===================== TAB: ORGANISASI ===================== --}}
    <div data-tab-panel="organisasi">
        <div class="rounded-2xl border border-black/5 bg-white shadow-sm dark:border-white/5 dark:bg-slate-900">
            <div class="flex items-center justify-end gap-3 px-4 py-3">
                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input
                        type="checkbox"
                        form="directory-filter-form"
                        name="hide_empty_organizations"
                        value="1"
                        onchange="this.form.submit()"
                        @checked($hideEmptyOrganizations)
                        class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800"
                    >
                    {{ __('directory.hide_empty_organizations') }}
                </label>
            </div>

            @if ($organizations->isEmpty())
                <div class="border-t border-black/5 p-12 text-center dark:border-white/5">
                    <p class="text-slate-500 dark:text-slate-400">{{ __('directory.no_organizations') }}</p>
                </div>
            @else
                <div class="overflow-x-auto border-t border-black/5 dark:border-white/5">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-800/60">
                            <tr>
                                <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('comparison.organization_name_label') }}</th>
                                <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('common.account') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                            @foreach ($groupedOrganizations as $unionKey => $unionGroup)
                                <x-analytics-group-row
                                    :label="$unionGroup['label']"
                                    :count="$unionGroup['conferences']->sum(fn ($c) => $c['rows']->count()) + $unionGroup['rows']->count()"
                                    :colspan="2"
                                    :toggle-id="'directory-organization-union-'.$unionKey"
                                />
                                @foreach ($unionGroup['rows'] as $organization)
                                    @include('partials.directory-organization-row', [
                                        'organization' => $organization,
                                        'depth' => 1,
                                        'ancestors' => 'directory-organization-union-'.$unionKey,
                                    ])
                                @endforeach
                                @foreach ($unionGroup['conferences'] as $conferenceKey => $conferenceGroup)
                                    <x-analytics-group-row
                                        :label="$conferenceGroup['label']"
                                        :count="$conferenceGroup['rows']->count()"
                                        :colspan="2"
                                        :toggle-id="'directory-organization-conf-'.$unionKey.'-'.$conferenceKey"
                                        :ancestors="'directory-organization-union-'.$unionKey"
                                        :depth="1"
                                    />
                                    @foreach ($conferenceGroup['rows'] as $organization)
                                        @include('partials.directory-organization-row', [
                                            'organization' => $organization,
                                            'depth' => 2,
                                            'ancestors' => 'directory-organization-union-'.$unionKey.' directory-organization-conf-'.$unionKey.'-'.$conferenceKey,
                                        ])
                                    @endforeach
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- ===================== TAB: GEREJA ===================== --}}
    <div data-tab-panel="gereja">
        <div class="rounded-2xl border border-black/5 bg-white shadow-sm dark:border-white/5 dark:bg-slate-900">
            <div class="flex items-center justify-end gap-3 px-4 py-3">
                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input
                        type="checkbox"
                        form="directory-filter-form"
                        name="hide_empty_churches"
                        value="1"
                        onchange="this.form.submit()"
                        @checked($hideEmptyChurches)
                        class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800"
                    >
                    {{ __('directory.hide_empty_churches') }}
                </label>
            </div>

            @if ($churches->isEmpty())
                <div class="border-t border-black/5 p-12 text-center dark:border-white/5">
                    <p class="text-slate-500 dark:text-slate-400">{{ __('directory.no_churches') }}</p>
                </div>
            @else
                <div class="overflow-x-auto border-t border-black/5 dark:border-white/5">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-800/60">
                            <tr>
                                <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('common.church') }}</th>
                                <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('directory.church_accounts') }}</th>
                                <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('directory.general_accounts') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                            @foreach ($groupedChurches as $unionKey => $unionGroup)
                                <x-analytics-group-row
                                    :label="$unionGroup['label']"
                                    :count="$unionGroup['conferences']->sum(fn ($c) => $c['rows']->count())"
                                    :colspan="3"
                                    :toggle-id="'directory-church-union-'.$unionKey"
                                />
                                @foreach ($unionGroup['conferences'] as $conferenceKey => $conferenceGroup)
                                    <x-analytics-group-row
                                        :label="$conferenceGroup['label']"
                                        :count="$conferenceGroup['rows']->count()"
                                        :colspan="3"
                                        :toggle-id="'directory-church-conf-'.$unionKey.'-'.$conferenceKey"
                                        :ancestors="'directory-church-union-'.$unionKey"
                                        :depth="1"
                                    />
                                    @foreach ($conferenceGroup['rows'] as $church)
                                        @include('partials.directory-church-row', [
                                            'church' => $church,
                                            'depth' => 2,
                                            'ancestors' => 'directory-church-union-'.$unionKey.' directory-church-conf-'.$unionKey.'-'.$conferenceKey,
                                        ])
                                    @endforeach
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- ===================== TAB: INSTITUSI ===================== --}}
    <div data-tab-panel="institusi">
        <div class="rounded-2xl border border-black/5 bg-white shadow-sm dark:border-white/5 dark:bg-slate-900">
            <div class="flex items-center justify-end gap-3 px-4 py-3">
                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input
                        type="checkbox"
                        form="directory-filter-form"
                        name="hide_empty_institutions"
                        value="1"
                        onchange="this.form.submit()"
                        @checked($hideEmptyInstitutions)
                        class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800"
                    >
                    {{ __('directory.hide_empty_institutions') }}
                </label>
            </div>

            @if ($institutions->isEmpty())
                <div class="border-t border-black/5 p-12 text-center dark:border-white/5">
                    <p class="text-slate-500 dark:text-slate-400">{{ __('directory.no_institutions') }}</p>
                </div>
            @else
                <div class="overflow-x-auto border-t border-black/5 dark:border-white/5">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-800/60">
                            <tr>
                                <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('common.institution') }}</th>
                                <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('common.account') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                            @foreach ($groupedInstitutions as $unionKey => $unionGroup)
                                <x-analytics-group-row
                                    :label="$unionGroup['label']"
                                    :count="$unionGroup['conferences']->sum(fn ($c) => $c['rows']->count())"
                                    :colspan="2"
                                    :toggle-id="'directory-institution-union-'.$unionKey"
                                />
                                @foreach ($unionGroup['conferences'] as $conferenceKey => $conferenceGroup)
                                    <x-analytics-group-row
                                        :label="$conferenceGroup['label']"
                                        :count="$conferenceGroup['rows']->count()"
                                        :colspan="2"
                                        :toggle-id="'directory-institution-conf-'.$unionKey.'-'.$conferenceKey"
                                        :ancestors="'directory-institution-union-'.$unionKey"
                                        :depth="1"
                                    />
                                    @foreach ($conferenceGroup['rows'] as $institution)
                                        @include('partials.directory-institution-row', [
                                            'institution' => $institution,
                                            'depth' => 2,
                                            'ancestors' => 'directory-institution-union-'.$unionKey.' directory-institution-conf-'.$unionKey.'-'.$conferenceKey,
                                        ])
                                    @endforeach
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- ===================== TAB: PERSONAL ===================== --}}
    <div data-tab-panel="personal">
        <div class="rounded-2xl border border-black/5 bg-white shadow-sm dark:border-white/5 dark:bg-slate-900">
            <div class="flex items-center justify-end gap-3 px-4 py-3">
                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input
                        type="checkbox"
                        form="directory-filter-form"
                        name="hide_empty_people"
                        value="1"
                        onchange="this.form.submit()"
                        @checked($hideEmptyPeople)
                        class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800"
                    >
                    {{ __('directory.hide_empty_people') }}
                </label>
            </div>

            @if ($people->isEmpty())
                <div class="border-t border-black/5 p-12 text-center dark:border-white/5">
                    <p class="text-slate-500 dark:text-slate-400">{{ __('directory.no_people') }}</p>
                </div>
            @else
                <div class="overflow-x-auto border-t border-black/5 dark:border-white/5">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-800/60">
                            <tr>
                                <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('common.name') }}</th>
                                <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('common.account') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                            @foreach ($groupedPeople as $unionKey => $unionGroup)
                                <x-analytics-group-row
                                    :label="$unionGroup['label']"
                                    :count="$unionGroup['conferences']->sum(fn ($c) => $c['rows']->count())"
                                    :colspan="2"
                                    :toggle-id="'directory-person-union-'.$unionKey"
                                />
                                @foreach ($unionGroup['conferences'] as $conferenceKey => $conferenceGroup)
                                    <x-analytics-group-row
                                        :label="$conferenceGroup['label']"
                                        :count="$conferenceGroup['rows']->count()"
                                        :colspan="2"
                                        :toggle-id="'directory-person-conf-'.$unionKey.'-'.$conferenceKey"
                                        :ancestors="'directory-person-union-'.$unionKey"
                                        :depth="1"
                                    />
                                    @foreach ($conferenceGroup['rows'] as $person)
                                        @include('partials.directory-person-row', [
                                            'person' => $person,
                                            'depth' => 2,
                                            'ancestors' => 'directory-person-union-'.$unionKey.' directory-person-conf-'.$unionKey.'-'.$conferenceKey,
                                        ])
                                    @endforeach
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @include('partials.analytics-group-toggle')
    @include('partials.tab-script', ['activeTab' => $activeTab])
@endsection
