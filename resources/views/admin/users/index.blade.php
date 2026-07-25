@php
    $levelLabels = ['nasional' => __('common.national'), 'uni' => __('common.union'), 'daerah' => __('common.conference'), 'gereja' => __('common.church')];
    $scopeLabel = match ($targetLevel) {
        'uni' => __('common.union'),
        'daerah' => __('common.conference'),
        'gereja' => __('common.church'),
        default => null,
    };

    $scopeDisplayFor = function ($user) {
        return match ($user->role?->level()) {
            'uni' => $user->union?->name ?? '—',
            'daerah' => $user->conference ? "{$user->conference->name} ({$user->conference->union->name})" : '—',
            'gereja' => $user->church ? "{$user->church->name} ({$user->church->conference?->name})" : '—',
            default => '—',
        };
    };
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

    <div class="mb-6 flex gap-2 overflow-x-auto border-b border-black/5 dark:border-white/5">
        <button type="button" data-tab-button="unassigned" class="border-b-2 px-4 py-2.5 text-sm font-medium transition">
            {{ __('users.tab_unassigned') }}
        </button>
        <button type="button" data-tab-button="admin" class="border-b-2 px-4 py-2.5 text-sm font-medium transition">
            {{ __('users.tab_admin') }}
        </button>
        <button type="button" data-tab-button="pemimpin" class="border-b-2 px-4 py-2.5 text-sm font-medium transition">
            {{ __('users.tab_pemimpin') }}
        </button>
        @if ($canManageInstitutions)
            <button type="button" data-tab-button="institusi" class="border-b-2 px-4 py-2.5 text-sm font-medium transition">
                {{ __('common.institution') }}
            </button>
        @endif
        @if ($isSuperAdmin)
            <button type="button" data-tab-button="terhapus" class="border-b-2 px-4 py-2.5 text-sm font-medium transition">
                {{ __('users.tab_terhapus') }}
            </button>
        @endif
    </div>

    <div data-tab-panel="unassigned" class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <p class="mb-1 font-bold text-slate-900 dark:text-white">{{ __('users.unassigned_title') }}</p>
        <p class="mb-4 border-b border-black/5 pb-4 text-sm text-slate-500 dark:border-white/5 dark:text-slate-400">{{ __('users.unassigned_subtitle') }}</p>

        <form method="GET" class="mb-4">
            <input type="hidden" name="tab" data-tab-hidden-field value="{{ $activeTab }}">
            <label class="relative block w-full max-w-sm">
                <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                    type="search"
                    name="search"
                    value="{{ $search }}"
                    placeholder="{{ __('users.search_placeholder') }}"
                    class="w-full rounded-full border border-black/10 bg-slate-50 py-2.5 pr-4 pl-9 text-sm font-medium text-slate-700 shadow-sm transition placeholder:font-normal placeholder:text-slate-400 hover:bg-slate-100 focus:bg-white focus:outline-none dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:placeholder:text-slate-500 dark:hover:bg-slate-700 dark:focus:bg-slate-800"
                >
            </label>
        </form>

        <script>
            var assignScopeData = @json($scopeDataByLevel);
        </script>

        @if ($unassigned->isEmpty())
            <p class="py-6 text-center text-sm text-slate-400 dark:text-slate-500">{{ __('users.no_match') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-slate-500 dark:text-slate-400">
                            <th class="py-2 pr-2 font-medium">#</th>
                            <th class="py-2 pr-2 font-medium">{{ __('users.col_user') }}</th>
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
                                        <button type="submit" class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition hover:bg-blue-700">
                                            {{ __('users.assign') }}
                                        </button>
                                    </form>
                                </td>
                                <td class="py-2 pr-2">
                                    @include('admin.users.partials.row-actions', ['user' => $user, 'tab' => 'unassigned'])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-pagination :paginator="$unassigned" />
        @endif

        <script>
            (function () {
                var LEVEL_LABELS = {
                    uni: @json(__('common.union')),
                    daerah: @json(__('common.conference')),
                    gereja: @json(__('common.church')),
                    institusi: @json(__('common.institution')),
                };
                var SEARCH_SCOPE_TEMPLATE = @json(__('users.search_scope_for', ['level' => ':level']));

                function levelForRole(role) {
                    if (role.endsWith('_uni')) return 'uni';
                    if (role.endsWith('_daerah')) return 'daerah';
                    if (role.endsWith('_gereja')) return 'gereja';
                    if (role.endsWith('_institusi')) return 'institusi';
                    return 'nasional';
                }

                function refreshScope(roleSelect) {
                    var form = roleSelect.closest('form');
                    var wrapper = form.querySelector('[data-assign-scope-wrapper]');
                    var level = levelForRole(roleSelect.value);

                    if (level === 'nasional') {
                        wrapper.hidden = true;
                        wrapper._searchableSelect.setOptions([]);
                        return;
                    }

                    wrapper.hidden = false;
                    wrapper._searchableSelect.setOptions(assignScopeData[level] || [], SEARCH_SCOPE_TEMPLATE.replace(':level', LEVEL_LABELS[level] || ''));
                }

                document.querySelectorAll('[data-assign-scope-wrapper]').forEach(function (wrapper) {
                    window.initSearchableSelect(wrapper);
                });

                document.querySelectorAll('[data-assign-role]').forEach(function (select) {
                    refreshScope(select);
                    select.addEventListener('change', function () { refreshScope(select); });
                });

                // Belt-and-suspenders: a hidden input's `required` attribute is ignored by
                // browsers, so block submission here if a visible scope combobox has no
                // selection yet (matches the old <select required> behavior it replaced).
                document.querySelectorAll('[data-assign-role]').forEach(function (select) {
                    var form = select.closest('form');
                    form.addEventListener('submit', function (e) {
                        var wrapper = form.querySelector('[data-assign-scope-wrapper]');
                        if (wrapper.hidden) return;

                        if (! wrapper._searchableSelect.getValue()) {
                            e.preventDefault();
                            wrapper.querySelector('[data-searchable-select-search]').focus();
                        }
                    });
                });
            })();
        </script>
    </div>

    <div data-tab-panel="admin" class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <p class="mb-1 font-bold text-slate-900 dark:text-white">
            {{ $canBootstrapAnyLevel ? __('users.admin_all_title') : __('users.admin_level_title', ['level' => $levelLabels[$targetLevel]]) }}
        </p>
        <p class="mb-4 border-b border-black/5 pb-4 text-sm text-slate-500 dark:border-white/5 dark:text-slate-400">{{ __('users.admin_subtitle') }}</p>

        @if ($adminUsers->isEmpty())
            <p class="py-6 text-center text-sm text-slate-400 dark:text-slate-500">{{ __('users.no_admin_yet') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
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
                        @foreach ($adminUsers as $user)
                            <tr>
                                <td class="py-2 pr-2 text-slate-400 dark:text-slate-500">{{ $loop->iteration }}</td>
                                <td class="py-2 pr-2">
                                    @include('admin.users.partials.name-email', ['user' => $user])
                                </td>
                                @if ($canBootstrapAnyLevel)
                                    <td class="py-2 pr-2 text-slate-500 dark:text-slate-400">{{ $user->role->label() }}</td>
                                    <td class="py-2 pr-2 text-slate-500 dark:text-slate-400">{{ $scopeDisplayFor($user) }}</td>
                                @endif
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
                                        @include('admin.users.partials.row-actions', ['user' => $user, 'tab' => 'admin'])
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div data-tab-panel="pemimpin" class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <p class="mb-1 font-bold text-slate-900 dark:text-white">
            {{ $canBootstrapAnyLevel ? __('users.pemimpin_all_title') : __('users.pemimpin_level_title', ['level' => $levelLabels[$targetLevel]]) }}
        </p>
        <p class="mb-4 border-b border-black/5 pb-4 text-sm text-slate-500 dark:border-white/5 dark:text-slate-400">{{ __('users.pemimpin_subtitle') }}</p>

        @if ($pimpinanUsers->isEmpty())
            <p class="py-6 text-center text-sm text-slate-400 dark:text-slate-500">{{ __('users.no_pemimpin_yet') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
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
                        @foreach ($pimpinanUsers as $user)
                            <tr>
                                <td class="py-2 pr-2 text-slate-400 dark:text-slate-500">{{ $loop->iteration }}</td>
                                <td class="py-2 pr-2">
                                    @include('admin.users.partials.name-email', ['user' => $user])
                                </td>
                                @if ($canBootstrapAnyLevel)
                                    <td class="py-2 pr-2 text-slate-500 dark:text-slate-400">{{ $user->role->label() }}</td>
                                    <td class="py-2 pr-2 text-slate-500 dark:text-slate-400">{{ $scopeDisplayFor($user) }}</td>
                                @endif
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
                                        @include('admin.users.partials.row-actions', ['user' => $user, 'tab' => 'pemimpin'])
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($canManageInstitutions)
        <div data-tab-panel="institusi" class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
            <p class="mb-1 font-bold text-slate-900 dark:text-white">{{ __('users.institusi_admin_title') }}</p>
            <p class="mb-4 border-b border-black/5 pb-4 text-sm text-slate-500 dark:border-white/5 dark:text-slate-400">
                {{ __('users.institusi_admin_subtitle_prefix') }} <a href="{{ route('admin.accounts.index', ['tab' => 'institusi']) }}" class="font-medium text-blue-600 hover:underline dark:text-blue-400">{{ __('nav.manage_accounts') }}</a>.
            </p>

            <div class="overflow-x-auto">
                @if ($institutionAdmins->isEmpty() && $institutionPimpinan->isEmpty())
                    <p class="py-6 text-center text-sm text-slate-400 dark:text-slate-500">{{ __('users.no_institusi_admin_yet') }}</p>
                @else
                    <table class="w-full text-left text-sm">
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
                    </table>
                @endif
            </div>
        </div>
    @endif

    @if ($isSuperAdmin)
        <div data-tab-panel="terhapus" class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
            <p class="mb-1 font-bold text-slate-900 dark:text-white">{{ __('users.trashed_title') }}</p>
            <p class="mb-4 border-b border-black/5 pb-4 text-sm text-slate-500 dark:border-white/5 dark:text-slate-400">
                {{ __('users.trashed_subtitle') }}
            </p>

            @if ($trashedUsers->isEmpty())
                <p class="py-6 text-center text-sm text-slate-400 dark:text-slate-500">{{ __('users.no_trashed') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
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
                                        {{ $user->church?->name ?? $user->conference?->name ?? $user->union?->name ?? $user->institution?->name ?? '—' }}
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
                    </table>
                </div>
            @endif
        </div>
    @endif

    @include('partials.tab-script', ['activeTab' => $activeTab])
@endsection
