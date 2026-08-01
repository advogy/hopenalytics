@extends('layouts.app')

@section('title', __('nav.manage_accounts') . ' — ' . config('app.name'))

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('nav.manage_accounts') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('accounts.subtitle') }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
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

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
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
    </div>

    <div class="mb-6 flex gap-2 overflow-x-auto border-b border-black/5 dark:border-white/5">
        @if ($visibleTabs['uni'])
            <button type="button" data-tab-button="uni" class="border-b-2 px-4 py-2.5 text-sm font-medium transition">
                {{ __('common.union') }}
            </button>
        @endif
        @if ($visibleTabs['daerah'])
            <button type="button" data-tab-button="daerah" class="border-b-2 px-4 py-2.5 text-sm font-medium transition">
                {{ __('common.conference') }}
            </button>
        @endif
        @if ($visibleTabs['gereja'])
            <button type="button" data-tab-button="gereja" class="border-b-2 px-4 py-2.5 text-sm font-medium transition">
                {{ __('common.church') }}
            </button>
        @endif
        @if ($visibleTabs['institusi'])
            <button type="button" data-tab-button="institusi" class="border-b-2 px-4 py-2.5 text-sm font-medium transition">
                {{ __('common.institution') }}
            </button>
        @endif
        @if ($visibleTabs['personal'])
            <button type="button" data-tab-button="personal" class="border-b-2 px-4 py-2.5 text-sm font-medium transition">
                {{ __('common.personal') }}
            </button>
        @endif
    </div>

    @if ($visibleTabs['uni'])
    <div data-tab-panel="uni" class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <p class="mb-1 font-bold text-slate-900 dark:text-white">{{ __('accounts.uni_list_title') }}</p>
        <p class="mb-4 border-b border-black/5 pb-4 text-sm text-slate-500 dark:border-white/5 dark:text-slate-400">{{ __('accounts.uni_list_subtitle') }}</p>

        <form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
            <input type="hidden" name="tab" data-tab-hidden-field value="{{ $activeTab }}">
            <label class="relative block w-full max-w-sm">
                <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                    type="search"
                    name="search_uni"
                    value="{{ $searchUni }}"
                    placeholder="{{ __('accounts.search_uni_placeholder') }}"
                    class="w-full rounded-full border border-black/10 bg-slate-50 py-2.5 pr-4 pl-9 text-sm font-medium text-slate-700 shadow-sm transition placeholder:font-normal placeholder:text-slate-400 hover:bg-slate-100 focus:bg-white focus:outline-none dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:placeholder:text-slate-500 dark:hover:bg-slate-700 dark:focus:bg-slate-800"
                >
            </label>
            <select
                name="sort_uni"
                onchange="this.form.submit()"
                class="rounded-full border border-black/10 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800"
            >
                <option value="name_asc" @selected($sortUni === 'name_asc')>{{ __('accounts.sort_name_asc') }}</option>
                <option value="name_desc" @selected($sortUni === 'name_desc')>{{ __('accounts.sort_name_desc') }}</option>
                <option value="status_active" @selected($sortUni === 'status_active')>{{ __('accounts.sort_status_active') }}</option>
                <option value="status_inactive" @selected($sortUni === 'status_inactive')>{{ __('accounts.sort_status_inactive') }}</option>
            </select>
        </form>

        @if ($unions->isEmpty())
            <p class="py-6 text-center text-sm text-slate-400 dark:text-slate-500">{{ $searchUni ? __('accounts.no_match', ['entity' => __('common.union')]) : __('accounts.no_yet', ['entity' => __('common.union')]) }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-slate-500 dark:text-slate-400">
                            <th class="py-2 pr-2 font-medium">#</th>
                            <th class="py-2 pr-2 font-medium">{{ __('common.name') }}</th>
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
                                        'blockedReason' => __('accounts.blocked_uni'),
                                    ])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-pagination :paginator="$unions" />
        @endif
    </div>
    @endif

    @if ($visibleTabs['daerah'])
    <div data-tab-panel="daerah" class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <p class="mb-1 font-bold text-slate-900 dark:text-white">{{ __('accounts.daerah_list_title') }}</p>
        <p class="mb-4 border-b border-black/5 pb-4 text-sm text-slate-500 dark:border-white/5 dark:text-slate-400">{{ __('accounts.daerah_list_subtitle') }}</p>

        <form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
            <input type="hidden" name="tab" data-tab-hidden-field value="{{ $activeTab }}">
            <label class="relative block w-full max-w-sm">
                <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                    type="search"
                    name="search_daerah"
                    value="{{ $searchDaerah }}"
                    placeholder="{{ __('accounts.search_daerah_placeholder') }}"
                    class="w-full rounded-full border border-black/10 bg-slate-50 py-2.5 pr-4 pl-9 text-sm font-medium text-slate-700 shadow-sm transition placeholder:font-normal placeholder:text-slate-400 hover:bg-slate-100 focus:bg-white focus:outline-none dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:placeholder:text-slate-500 dark:hover:bg-slate-700 dark:focus:bg-slate-800"
                >
            </label>
            <select
                name="sort_daerah"
                onchange="this.form.submit()"
                class="rounded-full border border-black/10 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800"
            >
                <option value="name_asc" @selected($sortDaerah === 'name_asc')>{{ __('accounts.sort_name_asc') }}</option>
                <option value="name_desc" @selected($sortDaerah === 'name_desc')>{{ __('accounts.sort_name_desc') }}</option>
                <option value="union_asc" @selected($sortDaerah === 'union_asc')>{{ __('accounts.sort_union_asc') }}</option>
                <option value="union_desc" @selected($sortDaerah === 'union_desc')>{{ __('accounts.sort_union_desc') }}</option>
                <option value="status_active" @selected($sortDaerah === 'status_active')>{{ __('accounts.sort_status_active') }}</option>
                <option value="status_inactive" @selected($sortDaerah === 'status_inactive')>{{ __('accounts.sort_status_inactive') }}</option>
            </select>
        </form>

        @if ($conferences->isEmpty())
            <p class="py-6 text-center text-sm text-slate-400 dark:text-slate-500">{{ $searchDaerah ? __('accounts.no_match', ['entity' => __('common.conference')]) : __('accounts.no_yet', ['entity' => __('common.conference')]) }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
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
                                        'blockedReason' => __('accounts.blocked_daerah'),
                                    ])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-pagination :paginator="$conferences" />
        @endif
    </div>
    @endif

    @if ($visibleTabs['gereja'])
    <div data-tab-panel="gereja" class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <p class="mb-1 font-bold text-slate-900 dark:text-white">{{ __('accounts.gereja_list_title') }}</p>
        <p class="mb-4 border-b border-black/5 pb-4 text-sm text-slate-500 dark:border-white/5 dark:text-slate-400">{{ __('accounts.gereja_list_subtitle') }}</p>

        <form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
            <input type="hidden" name="tab" data-tab-hidden-field value="{{ $activeTab }}">
            <label class="relative block w-full max-w-sm">
                <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                    type="search"
                    name="search_gereja"
                    value="{{ $searchGereja }}"
                    placeholder="{{ __('accounts.search_gereja_placeholder') }}"
                    class="w-full rounded-full border border-black/10 bg-slate-50 py-2.5 pr-4 pl-9 text-sm font-medium text-slate-700 shadow-sm transition placeholder:font-normal placeholder:text-slate-400 hover:bg-slate-100 focus:bg-white focus:outline-none dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:placeholder:text-slate-500 dark:hover:bg-slate-700 dark:focus:bg-slate-800"
                >
            </label>
            <select
                name="sort_gereja"
                onchange="this.form.submit()"
                class="rounded-full border border-black/10 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800"
            >
                <option value="name_asc" @selected($sortGereja === 'name_asc')>{{ __('accounts.sort_name_asc') }}</option>
                <option value="name_desc" @selected($sortGereja === 'name_desc')>{{ __('accounts.sort_name_desc') }}</option>
                <option value="city_asc" @selected($sortGereja === 'city_asc')>{{ __('accounts.sort_city_asc') }}</option>
                <option value="city_desc" @selected($sortGereja === 'city_desc')>{{ __('accounts.sort_city_desc') }}</option>
                <option value="daerah_asc" @selected($sortGereja === 'daerah_asc')>{{ __('accounts.sort_daerah_asc') }}</option>
                <option value="daerah_desc" @selected($sortGereja === 'daerah_desc')>{{ __('accounts.sort_daerah_desc') }}</option>
                <option value="status_active" @selected($sortGereja === 'status_active')>{{ __('accounts.sort_status_active') }}</option>
                <option value="status_inactive" @selected($sortGereja === 'status_inactive')>{{ __('accounts.sort_status_inactive') }}</option>
            </select>
        </form>

        @if ($churches->isEmpty())
            <p class="py-6 text-center text-sm text-slate-400 dark:text-slate-500">{{ $searchGereja ? __('accounts.no_match', ['entity' => __('common.church')]) : __('accounts.no_yet', ['entity' => __('common.church')]) }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
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
                                        'blockedReason' => __('accounts.blocked_gereja'),
                                    ])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-pagination :paginator="$churches" />
        @endif
    </div>
    @endif

    @if ($visibleTabs['institusi'])
    <div data-tab-panel="institusi" class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <p class="mb-1 font-bold text-slate-900 dark:text-white">{{ __('accounts.institusi_list_title') }}</p>
        <p class="mb-4 border-b border-black/5 pb-4 text-sm text-slate-500 dark:border-white/5 dark:text-slate-400">{{ __('accounts.institusi_list_subtitle') }}</p>

        <form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
            <input type="hidden" name="tab" data-tab-hidden-field value="{{ $activeTab }}">
            <label class="relative block w-full max-w-sm">
                <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                    type="search"
                    name="search_institusi"
                    value="{{ $searchInstitusi }}"
                    placeholder="{{ __('accounts.search_institusi_placeholder') }}"
                    class="w-full rounded-full border border-black/10 bg-slate-50 py-2.5 pr-4 pl-9 text-sm font-medium text-slate-700 shadow-sm transition placeholder:font-normal placeholder:text-slate-400 hover:bg-slate-100 focus:bg-white focus:outline-none dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:placeholder:text-slate-500 dark:hover:bg-slate-700 dark:focus:bg-slate-800"
                >
            </label>
            <select
                name="sort_institusi"
                onchange="this.form.submit()"
                class="rounded-full border border-black/10 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800"
            >
                <option value="name_asc" @selected($sortInstitusi === 'name_asc')>{{ __('accounts.sort_name_asc') }}</option>
                <option value="name_desc" @selected($sortInstitusi === 'name_desc')>{{ __('accounts.sort_name_desc') }}</option>
                <option value="region_asc" @selected($sortInstitusi === 'region_asc')>{{ __('accounts.sort_region_asc') }}</option>
                <option value="region_desc" @selected($sortInstitusi === 'region_desc')>{{ __('accounts.sort_region_desc') }}</option>
                <option value="status_active" @selected($sortInstitusi === 'status_active')>{{ __('accounts.sort_status_active') }}</option>
                <option value="status_inactive" @selected($sortInstitusi === 'status_inactive')>{{ __('accounts.sort_status_inactive') }}</option>
            </select>
        </form>

        @if ($institutions->isEmpty())
            <p class="py-6 text-center text-sm text-slate-400 dark:text-slate-500">{{ $searchInstitusi ? __('accounts.no_match', ['entity' => __('common.institution')]) : __('accounts.no_yet', ['entity' => __('common.institution')]) }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
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
                                        'blockedReason' => __('accounts.blocked_institusi'),
                                    ])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-pagination :paginator="$institutions" />
        @endif
    </div>
    @endif

    @if ($visibleTabs['personal'])
    <div data-tab-panel="personal" class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <p class="mb-1 font-bold text-slate-900 dark:text-white">{{ __('accounts.personal_list_title') }}</p>
        <p class="mb-4 border-b border-black/5 pb-4 text-sm text-slate-500 dark:border-white/5 dark:text-slate-400">{{ __('accounts.personal_list_subtitle') }}</p>

        <form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
            <input type="hidden" name="tab" data-tab-hidden-field value="{{ $activeTab }}">
            <label class="relative block w-full max-w-sm">
                <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                    type="search"
                    name="search_personal"
                    value="{{ $searchPersonal }}"
                    placeholder="{{ __('accounts.search_personal_placeholder') }}"
                    class="w-full rounded-full border border-black/10 bg-slate-50 py-2.5 pr-4 pl-9 text-sm font-medium text-slate-700 shadow-sm transition placeholder:font-normal placeholder:text-slate-400 hover:bg-slate-100 focus:bg-white focus:outline-none dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:placeholder:text-slate-500 dark:hover:bg-slate-700 dark:focus:bg-slate-800"
                >
            </label>
            <select
                name="sort_personal"
                onchange="this.form.submit()"
                class="rounded-full border border-black/10 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800"
            >
                <option value="name_asc" @selected($sortPersonal === 'name_asc')>{{ __('accounts.sort_name_asc') }}</option>
                <option value="name_desc" @selected($sortPersonal === 'name_desc')>{{ __('accounts.sort_name_desc') }}</option>
                <option value="city_asc" @selected($sortPersonal === 'city_asc')>{{ __('accounts.sort_city_asc') }}</option>
                <option value="city_desc" @selected($sortPersonal === 'city_desc')>{{ __('accounts.sort_city_desc') }}</option>
                <option value="scope_asc" @selected($sortPersonal === 'scope_asc')>{{ __('accounts.sort_scope_asc') }}</option>
                <option value="scope_desc" @selected($sortPersonal === 'scope_desc')>{{ __('accounts.sort_scope_desc') }}</option>
                <option value="status_active" @selected($sortPersonal === 'status_active')>{{ __('accounts.sort_status_active') }}</option>
                <option value="status_inactive" @selected($sortPersonal === 'status_inactive')>{{ __('accounts.sort_status_inactive') }}</option>
            </select>
        </form>

        @if ($people->isEmpty())
            <p class="py-6 text-center text-sm text-slate-400 dark:text-slate-500">{{ $searchPersonal ? __('accounts.no_match', ['entity' => __('common.personal')]) : __('accounts.no_yet', ['entity' => __('common.personal')]) }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
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
                </table>
            </div>

            <x-pagination :paginator="$people" />
        @endif
    </div>
    @endif

    @include('partials.tab-script', ['activeTab' => $activeTab])
@endsection
