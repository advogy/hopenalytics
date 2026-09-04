@php
    $levelLabels = ['nasional' => __('common.national'), 'divisi' => __('common.division'), 'uni' => __('common.union'), 'daerah' => __('common.conference'), 'gereja' => __('common.church')];
    $scopeLabel = match ($targetLevel) {
        'divisi' => __('common.division'),
        'uni' => __('common.union'),
        'daerah' => __('common.conference'),
        'gereja' => __('common.church'),
        default => null,
    };

    $scopeDisplayFor = function ($user) {
        return match ($user->role?->level()) {
            'nasional' => $user->assignedUnions->isNotEmpty() ? $user->assignedUnions->pluck('name')->implode(', ') : '—',
            'divisi' => $user->division?->name ?? '—',
            'uni' => $user->union ? "{$user->union->name}".($user->union->division ? " ({$user->union->division->name})" : '') : '—',
            'daerah' => $user->conference ? "{$user->conference->name} ({$user->conference->union->name})" : '—',
            'gereja' => $user->church ? "{$user->church->name} ({$user->church->conference?->name})" : '—',
            default => '—',
        };
    };

    // Groups the admin/pemimpin lists into a Divisi > Uni > Daerah tree, same collapsible-
    // header shape as the Data Per * tables in churches/analytics.blade.php (see
    // components/analytics-group-row.blade.php + partials/analytics-group-toggle.blade.php) —
    // only meaningful for a $canBootstrapAnyLevel viewer (SuperAdmin/Admin Global/a scoped Admin
    // Nasional), who's the only one ever looking at a list spanning more than one region; a
    // scoped Admin Uni/Daerah/Gereja's own list is already a single region, so grouping it would
    // add a header for exactly one group.
    $resolveUnionFromUser = fn ($user) => match ($user->role?->level()) {
        'uni' => $user->union,
        'daerah' => $user->conference?->union,
        'gereja' => $user->church?->conference?->union,
        default => null,
    };
    $resolveConferenceFromUser = fn ($user) => match ($user->role?->level()) {
        'daerah' => $user->conference,
        'gereja' => $user->church?->conference,
        default => null,
    };

    $groupUsersByRegion = function ($users) use ($resolveUnionFromUser, $resolveConferenceFromUser) {
        // Global/Nasional (Union-set-scoped, not one Union)/Institusi admins don't map onto a
        // single Divisi/Uni/Daerah node — listed flat, above the grouped tree.
        $flatLevels = ['global', 'nasional', 'institusi', null];
        $ungrouped = $users->filter(fn ($u) => in_array($u->role?->level(), $flatLevels, true))->values();
        $groupable = $users->reject(fn ($u) => in_array($u->role?->level(), $flatLevels, true));

        $divisions = $groupable
            ->groupBy(fn ($u) => ($u->role?->level() === 'divisi' ? $u->division : $resolveUnionFromUser($u)?->division)?->id ?? 'no-division')
            ->map(function ($divisionUsers) use ($resolveUnionFromUser, $resolveConferenceFromUser) {
                $sample = $divisionUsers->first();
                $division = $sample->role?->level() === 'divisi' ? $sample->division : $resolveUnionFromUser($sample)?->division;

                $ownRows = $divisionUsers->filter(fn ($u) => $u->role?->level() === 'divisi')->values();
                $nested = $divisionUsers->reject(fn ($u) => $u->role?->level() === 'divisi');

                return [
                    'label' => $division?->name ?? __('users.group_no_division'),
                    'rows' => $ownRows,
                    'unions' => $nested
                        ->groupBy(fn ($u) => $resolveUnionFromUser($u)?->id ?? 'no-union')
                        ->map(function ($unionUsers) use ($resolveUnionFromUser, $resolveConferenceFromUser) {
                            $union = $resolveUnionFromUser($unionUsers->first());

                            $ownRows = $unionUsers->filter(fn ($u) => $u->role?->level() === 'uni')->values();
                            $nested = $unionUsers->reject(fn ($u) => $u->role?->level() === 'uni');

                            return [
                                'label' => $union?->name ?? __('users.group_no_union'),
                                'rows' => $ownRows,
                                'conferences' => $nested
                                    ->groupBy(fn ($u) => $resolveConferenceFromUser($u)?->id ?? 'uni-level')
                                    ->map(fn ($confUsers) => [
                                        'label' => $resolveConferenceFromUser($confUsers->first())?->name ?? __('analytics.group_union_level'),
                                        'rows' => $confUsers->values(),
                                    ])
                                    ->sortBy('label'),
                            ];
                        })
                        ->sortBy('label'),
                ];
            })
            ->sortBy('label');

        return ['ungrouped' => $ungrouped, 'divisions' => $divisions];
    };

    $groupedAdminUsers = $canBootstrapAnyLevel ? $groupUsersByRegion($adminUsers) : null;
    $groupedPimpinanUsers = $canBootstrapAnyLevel ? $groupUsersByRegion($pimpinanUsers) : null;
@endphp

@extends('layouts.app')

@section('title', __('nav.manage_users') . ' — ' . config('app.name'))

@section('content')
    <div class="mb-6">
        <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('nav.manage_users') }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            @if ($canBootstrapAnyLevel)
                {{ __('users.subtitle_any_level') }}
            @else
                {{ __('users.subtitle_own_level', ['level' => $levelLabels[$targetLevel]]) }}
            @endif
        </p>
    </div>

    <x-tab-bar>
        <x-tab-button tab-key="unassigned">{{ __('users.tab_unassigned') }}</x-tab-button>
        @if ($canReviewSuggestions)
            <x-tab-button tab-key="saran">
                {{ __('admin_suggestions.tab_label') }}
                @if ($pendingSuggestions->total() > 0)
                    <span class="ml-1 rounded-full bg-amber-100 px-1.5 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-950 dark:text-amber-400">
                        {{ $pendingSuggestions->total() }}
                    </span>
                @endif
            </x-tab-button>
        @endif
        <x-tab-button tab-key="admin">{{ __('users.tab_admin') }}</x-tab-button>
        <x-tab-button tab-key="pemimpin">{{ __('users.tab_pemimpin') }}</x-tab-button>
        @if ($canManageInstitutions)
            <x-tab-button tab-key="institusi">{{ __('common.institution') }}</x-tab-button>
        @endif
        @if ($isSuperAdmin)
            <x-tab-button tab-key="terhapus">{{ __('users.tab_terhapus') }}</x-tab-button>
        @endif
    </x-tab-bar>

    <div data-tab-panel="unassigned" @class(['hidden' => $activeTab !== 'unassigned'])>
        <x-admin-list-card :items="$unassigned" :title="__('users.unassigned_title')" :subtitle="__('users.unassigned_subtitle')" :empty-message="__('users.no_match')">
            <x-slot:beforeContent>
                <form method="GET" class="mb-4 flex flex-wrap gap-3">
                    <input type="hidden" name="tab" data-tab-hidden-field value="{{ $activeTab }}">
                    <label class="relative block w-full max-w-sm flex-1">
                        <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input
                            type="search"
                            name="search"
                            value="{{ $search }}"
                            placeholder="{{ __('users.search_placeholder') }}"
                            class="w-full rounded-full border border-black/10 bg-slate-50 py-2.5 pr-4 pl-9 text-sm font-medium text-slate-700 shadow-sm transition placeholder:font-normal placeholder:text-slate-400 hover:bg-slate-100 focus:bg-white focus:outline-none dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:placeholder:text-slate-500 dark:hover:bg-slate-700 dark:focus:bg-slate-800"
                        >
                    </label>
                    <label class="relative min-w-[200px]">
                        <select
                            name="sort"
                            onchange="this.form.submit()"
                            class="w-full appearance-none rounded-full border border-black/10 bg-slate-50 py-2.5 pr-10 pl-4 text-sm font-medium text-slate-700 shadow-sm focus:border-blue-500 focus:bg-white focus:outline-none dark:border-white/10 dark:bg-slate-800 dark:text-slate-200"
                        >
                            <option value="name_asc" @selected($sort === 'name_asc')>{{ __('users.sort_name_asc') }}</option>
                            <option value="name_desc" @selected($sort === 'name_desc')>{{ __('users.sort_name_desc') }}</option>
                            <option value="date_desc" @selected($sort === 'date_desc')>{{ __('users.sort_date_desc') }}</option>
                            <option value="date_asc" @selected($sort === 'date_asc')>{{ __('users.sort_date_asc') }}</option>
                        </select>
                        <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                    </label>
                </form>

                <script>
                    var assignScopeData = @json($scopeDataByLevel);
                </script>
            </x-slot:beforeContent>

            <thead>
                <tr class="text-slate-500 dark:text-slate-400">
                    <th class="py-2 pr-2 font-medium">#</th>
                    <th class="py-2 pr-2 font-medium">{{ __('users.col_user') }}</th>
                    <th class="py-2 pr-2 font-medium">{{ __('users.col_registered_at') }}</th>
                    <th class="py-2 pr-2 font-medium">{{ __('common.status') }}</th>
                    <th class="py-2 pr-2 font-medium">{{ __('users.col_assign') }}</th>
                    <th class="py-2 pr-2 font-medium">{{ __('common.action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($unassigned as $user)
                    <tr>
                        <td class="py-2 pr-2 text-slate-400 dark:text-slate-500">{{ $unassigned->firstItem() + $loop->index }}</td>
                        <td class="py-2 pr-2">
                            @include('admin.users.partials.name-email', ['user' => $user])
                        </td>
                        <td class="py-2 pr-2 whitespace-nowrap text-slate-500 dark:text-slate-400">
                            {{ $user->created_at->translatedFormat('d M Y') }}
                        </td>
                        <td class="py-2 pr-2">
                            @include('admin.users.partials.status-badges', ['user' => $user])
                        </td>
                        <td class="py-2 pr-2">
                            <form method="POST" action="{{ route('admin.users.promote', $user) }}" class="flex flex-wrap items-center gap-2">
                                @csrf
                                <select name="role" required data-assign-role class="rounded-lg border border-black/10 bg-white px-2 py-1.5 text-xs shadow-sm dark:border-white/10 dark:bg-slate-800">
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->value }}">{{ $role->label() }}</option>
                                    @endforeach
                                </select>
                                <div class="relative" data-assign-scope-wrapper data-searchable-select hidden>
                                    <input type="hidden" name="scope_id" data-searchable-select-value>
                                    <input
                                        type="text"
                                        data-searchable-select-search
                                        autocomplete="off"
                                        placeholder="{{ __('users.search_scope_placeholder') }}"
                                        class="w-36 rounded-lg border border-black/10 bg-white px-2 py-1.5 text-xs shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800"
                                    >
                                    <ul
                                        data-searchable-select-list
                                        class="absolute left-0 top-full z-20 mt-1 hidden max-h-52 w-56 overflow-y-auto rounded-lg border border-black/10 bg-white p-1 text-xs shadow-lg dark:border-white/10 dark:bg-slate-800"
                                    ></ul>
                                </div>
                                {{-- Admin/Pimpinan Nasional is the one role assignable to a SET of Unions rather
                                     than a single scope — a checkbox popover instead of the searchable combobox
                                     above, submitting scope_ids[] (see UserAssignmentController::promote()). --}}
                                <div class="relative" data-assign-scope-checkboxes hidden>
                                    <button
                                        type="button"
                                        data-scope-checkbox-toggle
                                        class="w-36 rounded-lg border border-black/10 bg-white px-2 py-1.5 text-left text-xs shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800"
                                    >
                                        <span data-scope-checkbox-summary>{{ __('users.select_unions') }}</span>
                                    </button>
                                    <div
                                        data-scope-checkbox-list
                                        class="absolute left-0 top-full z-20 mt-1 hidden max-h-52 w-56 space-y-0.5 overflow-y-auto rounded-lg border border-black/10 bg-white p-2 text-xs shadow-lg dark:border-white/10 dark:bg-slate-800"
                                    ></div>
                                </div>
                                <button type="submit" class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition hover:bg-blue-700">
                                    {{ __('users.assign') }}
                                </button>
                            </form>
                        </td>
                        <td class="py-2 pr-2">
                            @include('admin.users.partials.row-actions', ['user' => $user, 'tab' => 'unassigned', 'search' => $search, 'sort' => $sort])
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-admin-list-card>

        <script>
            (function () {
                var LEVEL_LABELS = {
                    divisi: @json(__('common.division')),
                    uni: @json(__('common.union')),
                    daerah: @json(__('common.conference')),
                    gereja: @json(__('common.church')),
                    institusi: @json(__('common.institution')),
                };
                var SEARCH_SCOPE_TEMPLATE = @json(__('users.search_scope_for', ['level' => ':level']));
                var SELECT_UNIONS_LABEL = @json(__('users.select_unions'));
                var UNIONS_SELECTED_TEMPLATE = @json(__('users.unions_selected_count', ['count' => ':count']));

                // 'global' (unrestricted, e.g. Admin Global) needs no scope at all — same as
                // 'nasional' used to before it became Union-set-scoped. 'nasional' now needs the
                // checkbox popover instead of the single-value combobox every other level uses.
                function levelForRole(role) {
                    if (role.endsWith('_global')) return 'global';
                    if (role.endsWith('_nasional')) return 'nasional';
                    if (role.endsWith('_divisi')) return 'divisi';
                    if (role.endsWith('_uni')) return 'uni';
                    if (role.endsWith('_daerah')) return 'daerah';
                    if (role.endsWith('_gereja')) return 'gereja';
                    if (role.endsWith('_institusi')) return 'institusi';
                    return 'global';
                }

                function renderUnionCheckboxes(checkboxWrapper) {
                    var list = checkboxWrapper.querySelector('[data-scope-checkbox-list]');
                    if (list.dataset.rendered) return;
                    list.dataset.rendered = '1';

                    (assignScopeData.nasional || []).forEach(function (option) {
                        var label = document.createElement('label');
                        label.className = 'flex items-center gap-2 rounded px-1 py-1 hover:bg-slate-50 dark:hover:bg-slate-700';

                        var input = document.createElement('input');
                        input.type = 'checkbox';
                        input.name = 'scope_ids[]';
                        input.value = option.id;
                        input.className = 'shrink-0';
                        input.addEventListener('change', function () { updateScopeCheckboxSummary(checkboxWrapper); });

                        var span = document.createElement('span');
                        span.textContent = option.label;

                        label.appendChild(input);
                        label.appendChild(span);
                        list.appendChild(label);
                    });
                }

                function updateScopeCheckboxSummary(checkboxWrapper) {
                    var checked = checkboxWrapper.querySelectorAll('input[type=checkbox]:checked');
                    checkboxWrapper.querySelector('[data-scope-checkbox-summary]').textContent = checked.length
                        ? UNIONS_SELECTED_TEMPLATE.replace(':count', checked.length)
                        : SELECT_UNIONS_LABEL;
                }

                function refreshScope(roleSelect) {
                    var form = roleSelect.closest('form');
                    var wrapper = form.querySelector('[data-assign-scope-wrapper]');
                    var checkboxWrapper = form.querySelector('[data-assign-scope-checkboxes]');
                    var level = levelForRole(roleSelect.value);

                    if (level === 'global') {
                        wrapper.hidden = true;
                        wrapper._searchableSelect.setOptions([]);
                        checkboxWrapper.hidden = true;
                        return;
                    }

                    if (level === 'nasional') {
                        wrapper.hidden = true;
                        wrapper._searchableSelect.setOptions([]);
                        checkboxWrapper.hidden = false;
                        renderUnionCheckboxes(checkboxWrapper);
                        return;
                    }

                    wrapper.hidden = false;
                    checkboxWrapper.hidden = true;
                    wrapper._searchableSelect.setOptions(assignScopeData[level] || [], SEARCH_SCOPE_TEMPLATE.replace(':level', LEVEL_LABELS[level] || ''));
                }

                document.querySelectorAll('[data-assign-scope-wrapper]').forEach(function (wrapper) {
                    window.initSearchableSelect(wrapper);
                });

                // position:fixed with coordinates computed here (rather than the plain CSS
                // absolute/top-full this used to rely on) — every table on this app's admin
                // pages wraps in an overflow-x-auto div (see x-admin-list-card), which forces
                // overflow-y to 'auto' too per the CSS spec, clipping an absolutely-positioned
                // dropdown the moment it extends past that div's own box (confirmed happening,
                // same fix as partials/searchable-select.blade.php's positionList()).
                function positionCheckboxList(btn, list) {
                    var estimatedListHeight = 208; // mirrors the list's own max-h-52 Tailwind class
                    var btnRect = btn.getBoundingClientRect();
                    var spaceBelow = window.innerHeight - btnRect.bottom;
                    var spaceAbove = btnRect.top;
                    var openUpward = spaceBelow < estimatedListHeight && spaceAbove > spaceBelow;

                    list.style.position = 'fixed';
                    list.style.left = btnRect.left + 'px';
                    list.style.top = openUpward ? 'auto' : (btnRect.bottom + 4) + 'px';
                    list.style.bottom = openUpward ? (window.innerHeight - btnRect.top + 4) + 'px' : 'auto';
                }

                function closeAllCheckboxLists() {
                    document.querySelectorAll('[data-scope-checkbox-list]').forEach(function (list) { list.classList.add('hidden'); });
                    window.removeEventListener('scroll', closeAllCheckboxLists, true);
                }

                document.querySelectorAll('[data-scope-checkbox-toggle]').forEach(function (btn) {
                    btn.addEventListener('click', function (e) {
                        e.stopPropagation();
                        var list = btn.closest('[data-assign-scope-checkboxes]').querySelector('[data-scope-checkbox-list]');
                        var opening = list.classList.contains('hidden');

                        closeAllCheckboxLists();

                        if (opening) {
                            positionCheckboxList(btn, list);
                            list.classList.remove('hidden');
                            // A fixed-position dropdown doesn't move with the page the way an
                            // absolutely-positioned one naturally would — closing on any scroll
                            // (the table's own horizontal one included, since it bubbles) avoids
                            // it drifting away from the button it belongs to.
                            window.addEventListener('scroll', closeAllCheckboxLists, true);
                        }
                    });
                });
                document.addEventListener('click', closeAllCheckboxLists);

                document.querySelectorAll('[data-assign-role]').forEach(function (select) {
                    refreshScope(select);
                    select.addEventListener('change', function () { refreshScope(select); });
                });

                // Belt-and-suspenders: a hidden input's `required` attribute is ignored by
                // browsers, so block submission here if a visible scope combobox/checkbox list
                // has no selection yet (matches the old <select required> behavior it replaced).
                document.querySelectorAll('[data-assign-role]').forEach(function (select) {
                    var form = select.closest('form');
                    form.addEventListener('submit', function (e) {
                        var wrapper = form.querySelector('[data-assign-scope-wrapper]');
                        var checkboxWrapper = form.querySelector('[data-assign-scope-checkboxes]');

                        if (! wrapper.hidden && ! wrapper._searchableSelect.getValue()) {
                            e.preventDefault();
                            wrapper.querySelector('[data-searchable-select-search]').focus();
                            return;
                        }

                        if (! checkboxWrapper.hidden && checkboxWrapper.querySelectorAll('input[type=checkbox]:checked').length === 0) {
                            e.preventDefault();
                            checkboxWrapper.querySelector('[data-scope-checkbox-toggle]').focus();
                        }
                    });
                });
            })();
        </script>
    </div>

    @if ($canReviewSuggestions)
        <div data-tab-panel="saran" @class(['hidden' => $activeTab !== 'saran'])>
            @include('admin.users.partials.suggestions-tab')
        </div>
    @endif

    <div data-tab-panel="admin" @class(['hidden' => $activeTab !== 'admin'])>
        <x-admin-list-card
            :items="$adminUsers"
            :title="$canBootstrapAnyLevel ? __('users.admin_all_title') : __('users.admin_level_title', ['level' => $levelLabels[$targetLevel]])"
            :subtitle="__('users.admin_subtitle')"
            :empty-message="__('users.no_admin_yet')"
            :paginated="false"
        >
            <thead>
                <tr class="text-slate-500 dark:text-slate-400">
                    <th class="py-2 pr-2 font-medium">#</th>
                    <th class="py-2 pr-2 font-medium">{{ __('users.col_user') }}</th>
                    @if ($canBootstrapAnyLevel)
                        <th class="py-2 pr-2 font-medium">{{ __('users.col_role') }}</th>
                        <th class="py-2 pr-2 font-medium">{{ __('users.col_scope') }}</th>
                    @endif
                    <th class="py-2 pr-2 font-medium">{{ __('common.status') }}</th>
                    <th class="py-2 pr-2 font-medium">{{ __('common.action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @if ($groupedAdminUsers !== null)
                    @include('admin.users.partials.grouped-user-rows', [
                        'grouped' => $groupedAdminUsers, 'colspan' => 6, 'prefix' => 'admin-users',
                        'canBootstrapAnyLevel' => $canBootstrapAnyLevel, 'tab' => 'admin',
                    ])
                @else
                    @foreach ($adminUsers as $i => $user)
                        @include('admin.users.partials.user-row', ['user' => $user, 'index' => $i + 1, 'canBootstrapAnyLevel' => $canBootstrapAnyLevel, 'tab' => 'admin'])
                    @endforeach
                @endif
            </tbody>
        </x-admin-list-card>
    </div>

    <div data-tab-panel="pemimpin" @class(['hidden' => $activeTab !== 'pemimpin'])>
        <x-admin-list-card
            :items="$pimpinanUsers"
            :title="$canBootstrapAnyLevel ? __('users.pemimpin_all_title') : __('users.pemimpin_level_title', ['level' => $levelLabels[$targetLevel]])"
            :subtitle="__('users.pemimpin_subtitle')"
            :empty-message="__('users.no_pemimpin_yet')"
            :paginated="false"
        >
            <thead>
                <tr class="text-slate-500 dark:text-slate-400">
                    <th class="py-2 pr-2 font-medium">#</th>
                    <th class="py-2 pr-2 font-medium">{{ __('users.col_user') }}</th>
                    @if ($canBootstrapAnyLevel)
                        <th class="py-2 pr-2 font-medium">{{ __('users.col_role') }}</th>
                        <th class="py-2 pr-2 font-medium">{{ __('users.col_scope') }}</th>
                    @endif
                    <th class="py-2 pr-2 font-medium">{{ __('common.status') }}</th>
                    <th class="py-2 pr-2 font-medium">{{ __('common.action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @if ($groupedPimpinanUsers !== null)
                    @include('admin.users.partials.grouped-user-rows', [
                        'grouped' => $groupedPimpinanUsers, 'colspan' => 6, 'prefix' => 'pemimpin-users',
                        'canBootstrapAnyLevel' => $canBootstrapAnyLevel, 'tab' => 'pemimpin',
                    ])
                @else
                    @foreach ($pimpinanUsers as $i => $user)
                        @include('admin.users.partials.user-row', ['user' => $user, 'index' => $i + 1, 'canBootstrapAnyLevel' => $canBootstrapAnyLevel, 'tab' => 'pemimpin'])
                    @endforeach
                @endif
            </tbody>
        </x-admin-list-card>
    </div>

    @if ($canManageInstitutions)
        <div data-tab-panel="institusi" @class(['hidden' => $activeTab !== 'institusi'])>
            <x-admin-list-card
                :items="$institutionAdmins->concat($institutionPimpinan)"
                :title="__('users.institusi_admin_title')"
                :empty-message="__('users.no_institusi_admin_yet')"
                :paginated="false"
            >
                <x-slot:subtitle>
                    {{ __('users.institusi_admin_subtitle_prefix') }} <a href="{{ route('admin.accounts.index', ['tab' => 'institusi']) }}" class="font-medium text-blue-600 hover:underline dark:text-blue-400">{{ __('nav.manage_accounts') }}</a>.
                </x-slot:subtitle>

                <thead>
                    <tr class="text-slate-500 dark:text-slate-400">
                        <th class="py-2 pr-2 font-medium">#</th>
                        <th class="py-2 pr-2 font-medium">{{ __('users.col_user') }}</th>
                        <th class="py-2 pr-2 font-medium">{{ __('users.col_role') }}</th>
                        <th class="py-2 pr-2 font-medium">{{ __('common.institution') }}</th>
                        <th class="py-2 pr-2 font-medium">{{ __('common.status') }}</th>
                        <th class="py-2 pr-2 font-medium">{{ __('common.action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($institutionAdmins->concat($institutionPimpinan) as $user)
                        <tr>
                            <td class="py-2 pr-2 text-slate-400 dark:text-slate-500">{{ $loop->iteration }}</td>
                            <td class="py-2 pr-2">
                                @include('admin.users.partials.name-email', ['user' => $user])
                            </td>
                            <td class="py-2 pr-2 text-slate-500 dark:text-slate-400">{{ $user->role->label() }}</td>
                            <td class="py-2 pr-2 text-slate-500 dark:text-slate-400">{{ $user->institution?->name ?? '—' }}</td>
                            <td class="py-2 pr-2">
                                @include('admin.users.partials.status-badges', ['user' => $user])
                            </td>
                            <td class="py-2 pr-2">
                                <div class="flex items-center justify-end gap-3">
                                    <form
                                        method="POST"
                                        action="{{ route('admin.users.revoke', $user) }}"
                                        data-confirm="{{ __('users.revoke_confirm', ['name' => $user->name]) }}"
                                    >
                                        @csrf
                                        <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">
                                            {{ __('users.revoke') }}
                                        </button>
                                    </form>
                                    @include('admin.users.partials.row-actions', ['user' => $user, 'tab' => 'institusi'])
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-admin-list-card>
        </div>
    @endif

    @if ($isSuperAdmin)
        <div data-tab-panel="terhapus" @class(['hidden' => $activeTab !== 'terhapus'])>
            <x-admin-list-card :items="$trashedUsers" :title="__('users.trashed_title')" :subtitle="__('users.trashed_subtitle')" :empty-message="__('users.no_trashed')" :paginated="false">
                <thead>
                    <tr class="text-slate-500 dark:text-slate-400">
                        <th class="py-2 pr-2 font-medium">#</th>
                        <th class="py-2 pr-2 font-medium">{{ __('users.col_user') }}</th>
                        <th class="py-2 pr-2 font-medium">{{ __('users.col_role') }}</th>
                        <th class="py-2 pr-2 font-medium">{{ __('users.col_scope') }}</th>
                        <th class="py-2 pr-2 font-medium">{{ __('users.col_deleted_at') }}</th>
                        <th class="py-2 text-right font-medium">{{ __('common.action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($trashedUsers as $user)
                        <tr>
                            <td class="py-2 pr-2 text-slate-400 dark:text-slate-500">{{ $loop->iteration }}</td>
                            <td class="py-2 pr-2">
                                @include('admin.users.partials.name-email', ['user' => $user])
                            </td>
                            <td class="py-2 pr-2 text-slate-500 dark:text-slate-400">{{ $user->role?->label() ?? '—' }}</td>
                            <td class="py-2 pr-2 text-slate-500 dark:text-slate-400">
                                {{ $user->church?->name ?? $user->conference?->name ?? $user->union?->name ?? $user->division?->name ?? $user->institution?->name ?? ($user->assignedUnions->isNotEmpty() ? $user->assignedUnions->pluck('name')->implode(', ') : '—') }}
                            </td>
                            <td class="py-2 pr-2 text-slate-500 dark:text-slate-400">{{ $user->deleted_at->translatedFormat('d M Y H:i') }}</td>
                            <td class="py-2">
                                <div class="flex flex-nowrap items-center justify-end gap-3">
                                    <form method="POST" action="{{ route('admin.users.restore', $user) }}">
                                        @csrf
                                        <button
                                            type="submit"
                                            title="{{ __('users.restore') }}"
                                            aria-label="{{ __('users.restore') }}"
                                            class="shrink-0 text-slate-500 hover:text-emerald-600 dark:text-slate-400 dark:hover:text-emerald-400"
                                        >
                                            <x-icon name="arrow-path" class="h-5 w-5" />
                                        </button>
                                    </form>
                                    <form
                                        method="POST"
                                        action="{{ route('admin.users.force-delete', $user) }}"
                                        data-confirm="{{ __('users.force_delete_confirm', ['name' => $user->name]) }}"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            title="{{ __('users.force_delete') }}"
                                            aria-label="{{ __('users.force_delete') }}"
                                            class="shrink-0 text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                        >
                                            <x-icon name="trash" class="h-5 w-5" />
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-admin-list-card>
        </div>
    @endif

    @if ($groupedAdminUsers !== null || $groupedPimpinanUsers !== null)
        @include('partials.analytics-group-toggle', ['expandGroupsByDefault' => true])
    @endif

    @include('partials.tab-script', ['activeTab' => $activeTab])
@endsection
