@php
    $hasDivisiFilters = $searchDivisi !== '' || $sortDivisi !== 'name_asc';
    $hasUniFilters = $searchUni !== '' || $sortUni !== 'name_asc' || $selectedDivisionIdUni;
    $hasDaerahFilters = $searchDaerah !== '' || $sortDaerah !== 'name_asc' || $selectedUnionIdDaerah;
    $hasGerejaFilters = $searchGereja !== '' || $sortGereja !== 'name_asc' || $selectedUnionIdGereja || $selectedConferenceIdGereja;
    $hasInstitusiFilters = $searchInstitusi !== '' || $sortInstitusi !== 'name_asc' || $selectedUnionIdInstitusi || $selectedConferenceIdInstitusi;
    $hasPersonalFilters = $searchPersonal !== '' || $sortPersonal !== 'name_asc' || $selectedUnionIdPersonal || $selectedConferenceIdPersonal;
@endphp
@extends('layouts.app')

@section('title', __('nav.manage_accounts') . ' — ' . config('app.name'))

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('nav.manage_accounts') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('accounts.subtitle') }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            @if ($visibleTabs['divisi'])
                <div data-tab-panel="divisi">
                    @can('create', App\Models\Division::class)
                        <a
                            href="{{ route('admin.divisions.create') }}"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700"
                        >
                            {{ __('accounts.add_divisi') }}
                        </a>
                    @endcan
                </div>
            @endif
            @if ($visibleTabs['uni'])
                <div data-tab-panel="uni">
                    @can('create', App\Models\Union::class)
                        <a
                            href="{{ route('admin.unions.create') }}"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700"
                        >
                            {{ __('accounts.add_uni') }}
                        </a>
                    @endcan
                </div>
            @endif
            @if ($visibleTabs['daerah'])
                <div data-tab-panel="daerah">
                    @can('create', App\Models\Conference::class)
                        <a
                            href="{{ route('admin.conferences.create') }}"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700"
                        >
                            {{ __('accounts.add_daerah') }}
                        </a>
                    @endcan
                </div>
            @endif
            @if ($visibleTabs['gereja'])
                <div data-tab-panel="gereja">
                    @can('create', App\Models\Church::class)
                        <a
                            href="{{ route('churches.create') }}"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700"
                        >
                            {{ __('accounts.add_gereja') }}
                        </a>
                    @endcan
                </div>
            @endif
            @if ($visibleTabs['institusi'])
                <div data-tab-panel="institusi">
                    @can('create', App\Models\Institution::class)
                        <a
                            href="{{ route('admin.institutions.create') }}"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700"
                        >
                            {{ __('accounts.add_institusi') }}
                        </a>
                    @endcan
                </div>
            @endif
            @if ($visibleTabs['personal'])
                <div data-tab-panel="personal">
                    @can('create', App\Models\Person::class)
                        <a
                            href="{{ route('people.create') }}"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700"
                        >
                            {{ __('accounts.add_personal') }}
                        </a>
                    @endcan
                </div>
            @endif
        </div>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-stat-card
            :href="route('churches.needs-attention')"
            icon="x-circle"
            :label="__('dashboard.stat_needs_attention')"
            :value="$accountsNeedingAttention"
        />
        <x-stat-card
            :href="route('admin.accounts.no-socials')"
            icon="information-circle"
            :label="__('accounts.stat_no_socials')"
            :value="$entitiesWithoutSocials"
        />
        <x-stat-card
            :href="route('churches.auto-fetch-accounts')"
            icon="arrow-path"
            :label="__('auto_fetch_accounts.title')"
            :value="$autoFetchAccountsCount"
        />
    </div>

    <x-tab-bar>
        @if ($visibleTabs['divisi'])
            <x-tab-button tab-key="divisi">{{ __('common.division') }}</x-tab-button>
        @endif
        @if ($visibleTabs['uni'])
            <x-tab-button tab-key="uni">{{ __('common.union') }}</x-tab-button>
        @endif
        @if ($visibleTabs['daerah'])
            <x-tab-button tab-key="daerah">{{ __('common.conference') }}</x-tab-button>
        @endif
        @if ($visibleTabs['gereja'])
            <x-tab-button tab-key="gereja">{{ __('common.church') }}</x-tab-button>
        @endif
        @if ($visibleTabs['institusi'])
            <x-tab-button tab-key="institusi">{{ __('common.institution') }}</x-tab-button>
        @endif
        @if ($visibleTabs['personal'])
            <x-tab-button tab-key="personal">{{ __('common.personal') }}</x-tab-button>
        @endif
    </x-tab-bar>

    @if ($visibleTabs['divisi'])
    <div data-tab-panel="divisi">
        <x-admin-tab-filter-form
            tab="divisi"
            :active-tab="$activeTab"
            :has-filters="$hasDivisiFilters"
            search-name="search_divisi"
            :search-value="$searchDivisi"
            :search-placeholder="__('accounts.search_divisi_placeholder')"
            sort-name="sort_divisi"
            :sort-value="$sortDivisi"
            :sort-options="[
                'name_asc' => __('accounts.sort_name_asc'),
                'name_desc' => __('accounts.sort_name_desc'),
                'status_active' => __('accounts.sort_status_active'),
                'status_inactive' => __('accounts.sort_status_inactive'),
            ]"
        />

        <x-admin-list-card :items="$divisions" :title="__('accounts.divisi_list_title')" :subtitle="__('accounts.divisi_list_subtitle')" :search="$searchDivisi" :entity-label="__('common.division')">
            <thead>
                <tr class="text-slate-500 dark:text-slate-400">
                    <th class="py-2 pr-2 font-medium">#</th>
                    <th class="py-2 pr-2 font-medium">{{ __('common.name') }}</th>
                    <th class="py-2 pr-2 text-right font-medium">{{ __('accounts.count_uni') }}</th>
                    <th class="py-2 pr-2 text-right font-medium">{{ __('accounts.count_users') }}</th>
                    <th class="py-2 pr-2 font-medium">{{ __('common.status') }}</th>
                    <th class="py-2 text-right font-medium">{{ __('common.action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($divisions as $division)
                    <tr>
                        <td class="py-2 pr-2 text-slate-400 dark:text-slate-500">{{ $divisions->firstItem() + $loop->index }}</td>
                        <td class="py-2 pr-2 font-medium">{{ $division->name }}</td>
                        <td class="py-2 pr-2 text-right tabular-nums">{{ $division->unions_count }}</td>
                        <td class="py-2 pr-2 text-right tabular-nums">{{ $division->users_count }}</td>
                        <td class="py-2 pr-2">
                            @if ($division->is_active)
                                <span class="text-emerald-600 dark:text-emerald-400">{{ __('common.active') }}</span>
                            @else
                                <span class="text-slate-400">{{ __('common.inactive') }}</span>
                            @endif
                        </td>
                        <td class="py-2">
                            @include('admin.accounts.partials.row-actions', [
                                'item' => $division,
                                'editRoute' => 'admin.divisions.edit',
                                'toggleRoute' => 'admin.divisions.toggle-active',
                                'deleteRoute' => 'admin.divisions.destroy',
                                'viewRoute' => 'admin.divisions.socials.index',
                                'name' => $division->name,
                                'canDelete' => $division->unions_count === 0 && $division->users_count === 0,
                                'blockedReason' => $division->users->isEmpty()
                                    ? __('accounts.blocked_divisi')
                                    : __('accounts.blocked_divisi') . ' (' . $division->users->pluck('name')->implode(', ') . ')',
                                'blockingUsers' => $division->users,
                            ])
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-admin-list-card>
    </div>
    @endif

    @if ($visibleTabs['uni'])
    <div data-tab-panel="uni">
        <x-admin-tab-filter-form
            tab="uni"
            :active-tab="$activeTab"
            :has-filters="$hasUniFilters"
            search-name="search_uni"
            :search-value="$searchUni"
            :search-placeholder="__('accounts.search_uni_placeholder')"
            sort-name="sort_uni"
            :sort-value="$sortUni"
            :sort-options="[
                'name_asc' => __('accounts.sort_name_asc'),
                'name_desc' => __('accounts.sort_name_desc'),
                'division_asc' => __('accounts.sort_division_asc'),
                'division_desc' => __('accounts.sort_division_desc'),
                'status_active' => __('accounts.sort_status_active'),
                'status_inactive' => __('accounts.sort_status_inactive'),
            ]"
            form-id="uni-filter-form"
        >
            @if ($divisionOptionsUni->isNotEmpty())
                @include('partials.analytics-entity-filter', [
                    'prefix' => 'accounts-uni',
                    'formId' => 'uni-filter-form',
                    'fieldName' => 'division_id_uni',
                    'icon' => 'globe-alt',
                    'placeholder' => __('entity.search_divisi_placeholder'),
                    'selectedId' => $selectedDivisionIdUni,
                    'options' => $divisionOptionsUni->map(fn ($d) => ['id' => $d->id, 'label' => $d->name])->values()->all(),
                    'wrapperClass' => 'flex-1 min-w-[180px]',
                    'inputWidthClass' => 'w-full',
                ])
            @endif
        </x-admin-tab-filter-form>

        <x-admin-list-card :items="$unions" :title="__('accounts.uni_list_title')" :subtitle="__('accounts.uni_list_subtitle')" :search="$searchUni" :entity-label="__('common.union')">
            <thead>
                <tr class="text-slate-500 dark:text-slate-400">
                    <th class="py-2 pr-2 font-medium">#</th>
                    <th class="py-2 pr-2 font-medium">{{ __('common.name') }}</th>
                    <th class="py-2 pr-2 font-medium">{{ __('common.division') }}</th>
                    <th class="py-2 pr-2 text-right font-medium">{{ __('accounts.count_daerah') }}</th>
                    <th class="py-2 pr-2 text-right font-medium">{{ __('accounts.count_person') }}</th>
                    <th class="py-2 pr-2 font-medium">{{ __('common.status') }}</th>
                    <th class="py-2 text-right font-medium">{{ __('common.action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($unions as $union)
                    <tr>
                        <td class="py-2 pr-2 text-slate-400 dark:text-slate-500">{{ $unions->firstItem() + $loop->index }}</td>
                        <td class="py-2 pr-2 font-medium">{{ $union->name }}</td>
                        <td class="py-2 pr-2 text-slate-500 dark:text-slate-400">{{ $union->division?->name ?? '—' }}</td>
                        <td class="py-2 pr-2 text-right tabular-nums">{{ $union->conferences_count }}</td>
                        <td class="py-2 pr-2 text-right tabular-nums">{{ $union->people_count }}</td>
                        <td class="py-2 pr-2">
                            @if ($union->is_active)
                                <span class="text-emerald-600 dark:text-emerald-400">{{ __('common.active') }}</span>
                            @else
                                <span class="text-slate-400">{{ __('common.inactive') }}</span>
                            @endif
                        </td>
                        <td class="py-2">
                            @include('admin.accounts.partials.row-actions', [
                                'item' => $union,
                                'viewRoute' => 'admin.unions.socials.index',
                                'editRoute' => 'admin.unions.edit',
                                'toggleRoute' => 'admin.unions.toggle-active',
                                'deleteRoute' => 'admin.unions.destroy',
                                'name' => $union->name,
                                'canDelete' => $union->conferences_count === 0 && $union->users_count === 0,
                                'blockedReason' => $union->users->isEmpty()
                                    ? __('accounts.blocked_uni')
                                    : __('accounts.blocked_uni') . ' (' . $union->users->pluck('name')->implode(', ') . ')',
                                'blockingUsers' => $union->users,
                            ])
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-admin-list-card>
    </div>
    @endif

    @if ($visibleTabs['daerah'])
    <div data-tab-panel="daerah">
        <x-admin-tab-filter-form
            tab="daerah"
            :active-tab="$activeTab"
            :has-filters="$hasDaerahFilters"
            search-name="search_daerah"
            :search-value="$searchDaerah"
            :search-placeholder="__('accounts.search_daerah_placeholder')"
            sort-name="sort_daerah"
            :sort-value="$sortDaerah"
            :sort-options="[
                'name_asc' => __('accounts.sort_name_asc'),
                'name_desc' => __('accounts.sort_name_desc'),
                'union_asc' => __('accounts.sort_union_asc'),
                'union_desc' => __('accounts.sort_union_desc'),
                'status_active' => __('accounts.sort_status_active'),
                'status_inactive' => __('accounts.sort_status_inactive'),
            ]"
            form-id="daerah-filter-form"
        >
            @if ($unionOptionsDaerah->isNotEmpty())
                @include('partials.analytics-entity-filter', [
                    'prefix' => 'accounts-daerah',
                    'formId' => 'daerah-filter-form',
                    'fieldName' => 'union_id_daerah',
                    'icon' => 'globe-alt',
                    'placeholder' => __('entity.search_uni_placeholder'),
                    'selectedId' => $selectedUnionIdDaerah,
                    'options' => $unionOptionsDaerah->map(fn ($u) => ['id' => $u->id, 'label' => $u->name])->values()->all(),
                    'wrapperClass' => 'flex-1 min-w-[180px]',
                    'inputWidthClass' => 'w-full',
                ])
            @endif
        </x-admin-tab-filter-form>

        <x-admin-list-card :items="$conferences" :title="__('accounts.daerah_list_title')" :subtitle="__('accounts.daerah_list_subtitle')" :search="$searchDaerah" :entity-label="__('common.conference')">
            <thead>
                <tr class="text-slate-500 dark:text-slate-400">
                    <th class="py-2 pr-2 font-medium">#</th>
                    <th class="py-2 pr-2 font-medium">{{ __('common.name') }}</th>
                    <th class="py-2 pr-2 font-medium">{{ __('common.union') }}</th>
                    <th class="py-2 pr-2 text-right font-medium">{{ __('accounts.count_gereja') }}</th>
                    <th class="py-2 pr-2 font-medium">{{ __('common.status') }}</th>
                    <th class="py-2 text-right font-medium">{{ __('common.action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($conferences as $conference)
                    <tr>
                        <td class="py-2 pr-2 text-slate-400 dark:text-slate-500">{{ $conferences->firstItem() + $loop->index }}</td>
                        <td class="py-2 pr-2 font-medium">{{ $conference->name }}</td>
                        <td class="py-2 pr-2 text-slate-500 dark:text-slate-400">{{ $conference->union->name }}</td>
                        <td class="py-2 pr-2 text-right tabular-nums">{{ $conference->churches_count }}</td>
                        <td class="py-2 pr-2">
                            @if ($conference->is_active)
                                <span class="text-emerald-600 dark:text-emerald-400">{{ __('common.active') }}</span>
                            @else
                                <span class="text-slate-400">{{ __('common.inactive') }}</span>
                            @endif
                        </td>
                        <td class="py-2">
                            @include('admin.accounts.partials.row-actions', [
                                'item' => $conference,
                                'viewRoute' => 'admin.conferences.socials.index',
                                'editRoute' => 'admin.conferences.edit',
                                'toggleRoute' => 'admin.conferences.toggle-active',
                                'deleteRoute' => 'admin.conferences.destroy',
                                'name' => $conference->name,
                                'canDelete' => $conference->churches_count === 0 && $conference->users_count === 0,
                                'blockedReason' => $conference->users->isEmpty()
                                    ? __('accounts.blocked_daerah')
                                    : __('accounts.blocked_daerah') . ' (' . $conference->users->pluck('name')->implode(', ') . ')',
                                'blockingUsers' => $conference->users,
                            ])
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-admin-list-card>
    </div>
    @endif

    @if ($visibleTabs['gereja'])
    <div data-tab-panel="gereja">
        <x-admin-tab-filter-form
            tab="gereja"
            :active-tab="$activeTab"
            :has-filters="$hasGerejaFilters"
            search-name="search_gereja"
            :search-value="$searchGereja"
            :search-placeholder="__('accounts.search_gereja_placeholder')"
            sort-name="sort_gereja"
            :sort-value="$sortGereja"
            :sort-options="[
                'name_asc' => __('accounts.sort_name_asc'),
                'name_desc' => __('accounts.sort_name_desc'),
                'city_asc' => __('accounts.sort_city_asc'),
                'city_desc' => __('accounts.sort_city_desc'),
                'daerah_asc' => __('accounts.sort_daerah_asc'),
                'daerah_desc' => __('accounts.sort_daerah_desc'),
                'status_active' => __('accounts.sort_status_active'),
                'status_inactive' => __('accounts.sort_status_inactive'),
            ]"
            form-id="gereja-filter-form"
        >
            @include('partials.analytics-region-filter', [
                'prefix' => 'accounts-gereja',
                'formId' => 'gereja-filter-form',
                'isNasionalView' => $isNasionalView,
                'isUniView' => $isUniView,
                'unionOptions' => $unionOptionsGereja,
                'conferenceOptions' => $conferenceOptionsGereja,
                'selectedUnionId' => $selectedUnionIdGereja,
                'selectedConferenceId' => $selectedConferenceIdGereja,
                'unionFieldName' => 'union_id_gereja',
                'conferenceFieldName' => 'conference_id_gereja',
                'wrapperClass' => 'flex-1 min-w-[180px]',
                'inputWidthClass' => 'w-full',
            ])
        </x-admin-tab-filter-form>

        <x-admin-list-card :items="$churches" :title="__('accounts.gereja_list_title')" :subtitle="__('accounts.gereja_list_subtitle')" :search="$searchGereja" :entity-label="__('common.church')">
            <thead>
                <tr class="text-slate-500 dark:text-slate-400">
                    <th class="py-2 pr-2 font-medium">#</th>
                    <th class="py-2 pr-2 font-medium">{{ __('common.church') }}</th>
                    <th class="py-2 pr-2 font-medium">{{ __('entity.city') }}</th>
                    <th class="py-2 pr-2 font-medium">{{ __('common.conference') }}</th>
                    <th class="py-2 pr-2 font-medium">{{ __('common.status') }}</th>
                    <th class="py-2 text-right font-medium">{{ __('common.action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($churches as $church)
                    <tr>
                        <td class="py-2 pr-2 text-slate-400 dark:text-slate-500">{{ $churches->firstItem() + $loop->index }}</td>
                        <td class="py-2 pr-2 font-medium">{{ $church->name }}</td>
                        <td class="py-2 pr-2 text-slate-500 dark:text-slate-400">{{ $church->city ?? '—' }}</td>
                        <td class="py-2 pr-2">
                            @if ($church->conference)
                                {{ $church->conference->name }}
                                <span class="text-slate-400">({{ $church->conference->union->name }})</span>
                            @else
                                <span class="text-amber-600 dark:text-amber-400">{{ __('common.not_assigned') }}</span>
                            @endif
                        </td>
                        <td class="py-2 pr-2">
                            @if ($church->is_active)
                                <span class="text-emerald-600 dark:text-emerald-400">{{ __('common.active') }}</span>
                            @else
                                <span class="text-slate-400">{{ __('common.inactive') }}</span>
                            @endif
                        </td>
                        <td class="py-2">
                            @include('admin.accounts.partials.row-actions', [
                                'item' => $church,
                                'viewRoute' => 'churches.socials.index',
                                'editRoute' => 'churches.edit',
                                'toggleRoute' => 'churches.toggle-active',
                                'deleteRoute' => 'churches.destroy',
                                'name' => $church->name,
                                'canDelete' => $church->users_count === 0,
                                'blockedReason' => $church->users->isEmpty()
                                    ? __('accounts.blocked_gereja')
                                    : __('accounts.blocked_gereja') . ' (' . $church->users->pluck('name')->implode(', ') . ')',
                                'blockingUsers' => $church->users,
                            ])
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-admin-list-card>
    </div>
    @endif

    @if ($visibleTabs['institusi'])
    <div data-tab-panel="institusi">
        <x-admin-tab-filter-form
            tab="institusi"
            :active-tab="$activeTab"
            :has-filters="$hasInstitusiFilters"
            search-name="search_institusi"
            :search-value="$searchInstitusi"
            :search-placeholder="__('accounts.search_institusi_placeholder')"
            sort-name="sort_institusi"
            :sort-value="$sortInstitusi"
            :sort-options="[
                'name_asc' => __('accounts.sort_name_asc'),
                'name_desc' => __('accounts.sort_name_desc'),
                'region_asc' => __('accounts.sort_region_asc'),
                'region_desc' => __('accounts.sort_region_desc'),
                'status_active' => __('accounts.sort_status_active'),
                'status_inactive' => __('accounts.sort_status_inactive'),
            ]"
            form-id="institusi-filter-form"
        >
            @include('partials.analytics-region-filter', [
                'prefix' => 'accounts-institusi',
                'formId' => 'institusi-filter-form',
                'isNasionalView' => $isNasionalView,
                'isUniView' => $isUniView,
                'unionOptions' => $unionOptionsInstitusi,
                'conferenceOptions' => $conferenceOptionsInstitusi,
                'selectedUnionId' => $selectedUnionIdInstitusi,
                'selectedConferenceId' => $selectedConferenceIdInstitusi,
                'unionFieldName' => 'union_id_institusi',
                'conferenceFieldName' => 'conference_id_institusi',
                'wrapperClass' => 'flex-1 min-w-[180px]',
                'inputWidthClass' => 'w-full',
            ])
        </x-admin-tab-filter-form>

        <x-admin-list-card :items="$institutions" :title="__('accounts.institusi_list_title')" :subtitle="__('accounts.institusi_list_subtitle')" :search="$searchInstitusi" :entity-label="__('common.institution')">
            <thead>
                <tr class="text-slate-500 dark:text-slate-400">
                    <th class="py-2 pr-2 font-medium">#</th>
                    <th class="py-2 pr-2 font-medium">{{ __('common.name') }}</th>
                    <th class="py-2 pr-2 font-medium">{{ __('accounts.region') }}</th>
                    <th class="py-2 pr-2 text-right font-medium">{{ __('accounts.count_users') }}</th>
                    <th class="py-2 pr-2 font-medium">{{ __('common.status') }}</th>
                    <th class="py-2 text-right font-medium">{{ __('common.action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($institutions as $institution)
                    <tr>
                        <td class="py-2 pr-2 text-slate-400 dark:text-slate-500">{{ $institutions->firstItem() + $loop->index }}</td>
                        <td class="py-2 pr-2 font-medium">{{ $institution->name }}</td>
                        <td class="py-2 pr-2 text-slate-500 dark:text-slate-400">
                            @if ($institution->conference)
                                {{ $institution->conference->name }}
                                <span class="text-slate-400">({{ $institution->conference->union->name }})</span>
                            @elseif ($institution->union)
                                {{ $institution->union->name }}
                            @else
                                <span class="text-slate-400">{{ __('common.national') }}</span>
                            @endif
                        </td>
                        <td class="py-2 pr-2 text-right tabular-nums">{{ $institution->users_count }}</td>
                        <td class="py-2 pr-2">
                            @if ($institution->is_active)
                                <span class="text-emerald-600 dark:text-emerald-400">{{ __('common.active') }}</span>
                            @else
                                <span class="text-slate-400">{{ __('common.inactive') }}</span>
                            @endif
                        </td>
                        <td class="py-2">
                            @include('admin.accounts.partials.row-actions', [
                                'item' => $institution,
                                'viewRoute' => 'admin.institutions.socials.index',
                                'editRoute' => 'admin.institutions.edit',
                                'toggleRoute' => 'admin.institutions.toggle-active',
                                'deleteRoute' => 'admin.institutions.destroy',
                                'name' => $institution->name,
                                'canDelete' => $institution->users_count === 0,
                                'blockedReason' => $institution->users->isEmpty()
                                    ? __('accounts.blocked_institusi')
                                    : __('accounts.blocked_institusi') . ' (' . $institution->users->pluck('name')->implode(', ') . ')',
                                'blockingUsers' => $institution->users,
                            ])
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-admin-list-card>
    </div>
    @endif

    @if ($visibleTabs['personal'])
    <div data-tab-panel="personal">
        <x-admin-tab-filter-form
            tab="personal"
            :active-tab="$activeTab"
            :has-filters="$hasPersonalFilters"
            search-name="search_personal"
            :search-value="$searchPersonal"
            :search-placeholder="__('accounts.search_personal_placeholder')"
            sort-name="sort_personal"
            :sort-value="$sortPersonal"
            :sort-options="[
                'name_asc' => __('accounts.sort_name_asc'),
                'name_desc' => __('accounts.sort_name_desc'),
                'city_asc' => __('accounts.sort_city_asc'),
                'city_desc' => __('accounts.sort_city_desc'),
                'scope_asc' => __('accounts.sort_scope_asc'),
                'scope_desc' => __('accounts.sort_scope_desc'),
                'status_active' => __('accounts.sort_status_active'),
                'status_inactive' => __('accounts.sort_status_inactive'),
            ]"
            form-id="personal-filter-form"
        >
            @include('partials.analytics-region-filter', [
                'prefix' => 'accounts-personal',
                'formId' => 'personal-filter-form',
                'isNasionalView' => $isNasionalView,
                'isUniView' => $isUniView,
                'unionOptions' => $unionOptionsPersonal,
                'conferenceOptions' => $conferenceOptionsPersonal,
                'selectedUnionId' => $selectedUnionIdPersonal,
                'selectedConferenceId' => $selectedConferenceIdPersonal,
                'unionFieldName' => 'union_id_personal',
                'conferenceFieldName' => 'conference_id_personal',
                'wrapperClass' => 'flex-1 min-w-[180px]',
                'inputWidthClass' => 'w-full',
            ])
        </x-admin-tab-filter-form>

        <x-admin-list-card :items="$people" :title="__('accounts.personal_list_title')" :subtitle="__('accounts.personal_list_subtitle')" :search="$searchPersonal" :entity-label="__('common.personal')">
            <thead>
                <tr class="text-slate-500 dark:text-slate-400">
                    <th class="py-2 pr-2 font-medium">#</th>
                    <th class="py-2 pr-2 font-medium">{{ __('common.name') }}</th>
                    <th class="py-2 pr-2 font-medium">{{ __('entity.city') }}</th>
                    <th class="py-2 pr-2 font-medium">{{ __('accounts.scope') }}</th>
                    <th class="py-2 pr-2 text-right font-medium">{{ __('accounts.social_accounts') }}</th>
                    <th class="py-2 pr-2 font-medium">{{ __('common.status') }}</th>
                    <th class="py-2 text-right font-medium">{{ __('common.action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($people as $person)
                    <tr>
                        <td class="py-2 pr-2 text-slate-400 dark:text-slate-500">{{ $people->firstItem() + $loop->index }}</td>
                        <td class="py-2 pr-2 font-medium">
                            <a href="{{ route('people.show', $person) }}" class="hover:text-blue-600 dark:hover:text-blue-400">{{ $person->name }}</a>
                            <p class="text-xs font-normal">
                                @if ($person->user)
                                    <span class="text-blue-600 dark:text-blue-400">{{ __('accounts.has_login', ['name' => $person->user->name]) }}</span>
                                @else
                                    <span class="text-slate-400">{{ __('accounts.no_login') }}</span>
                                @endif
                            </p>
                        </td>
                        <td class="py-2 pr-2 text-slate-500 dark:text-slate-400">{{ $person->city ?? '—' }}</td>
                        <td class="py-2 pr-2 text-slate-500 dark:text-slate-400">
                            @if ($person->conference)
                                {{ $person->conference->name }} <span class="text-slate-400">({{ $person->conference->union->name }})</span>
                            @elseif ($person->union)
                                {{ $person->union->name }}
                            @else
                                <span class="text-slate-400">{{ __('common.independent') }}</span>
                            @endif
                        </td>
                        <td class="py-2 pr-2 text-right tabular-nums">{{ $person->socials_count }}</td>
                        <td class="py-2 pr-2">
                            @if ($person->is_active)
                                <span class="text-emerald-600 dark:text-emerald-400">{{ __('common.active') }}</span>
                            @else
                                <span class="text-slate-400">{{ __('common.inactive') }}</span>
                            @endif
                        </td>
                        <td class="py-2">
                            @include('admin.accounts.partials.row-actions', [
                                'item' => $person,
                                'viewRoute' => 'people.socials.index',
                                'editRoute' => 'people.edit',
                                'toggleRoute' => 'people.toggle-active',
                                'deleteRoute' => 'people.destroy',
                                'name' => $person->name,
                                'canDelete' => true,
                                'blockedReason' => '',
                                'deleteWarning' => __('accounts.delete_warning_personal'),
                            ])
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-admin-list-card>
    </div>
    @endif

    @include('partials.tab-script', ['activeTab' => $activeTab])
@endsection
