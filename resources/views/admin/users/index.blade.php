@php
    $levelLabels = ['nasional' => 'Nasional', 'uni' => 'Uni', 'daerah' => 'Daerah', 'gereja' => 'Gereja'];
    $scopeLabel = match ($targetLevel) {
        'uni' => 'Uni',
        'daerah' => 'Daerah',
        'gereja' => 'Gereja',
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

@section('title', 'Kelola Pengguna — ' . config('app.name'))

@section('content')
    <div class="mb-6">
        <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Kelola Pengguna</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            @if ($canBootstrapAnyLevel)
                Tugaskan anggota terdaftar sebagai admin/pimpinan di level manapun, atau cabut/ubah penugasan yang sudah ada.
            @else
                Tugaskan anggota terdaftar sebagai admin/pimpinan {{ $levelLabels[$targetLevel] }}, atau cabut/ubah penugasan yang sudah ada.
            @endif
        </p>
    </div>

    <div class="mb-6 flex gap-2 border-b border-black/5 dark:border-white/5">
        <button type="button" data-tab-button="unassigned" class="border-b-2 px-4 py-2.5 text-sm font-medium transition">
            Belum Ditugaskan
        </button>
        <button type="button" data-tab-button="admin" class="border-b-2 px-4 py-2.5 text-sm font-medium transition">
            Admin
        </button>
        <button type="button" data-tab-button="pemimpin" class="border-b-2 px-4 py-2.5 text-sm font-medium transition">
            Pemimpin
        </button>
        @if ($canManageInstitutions)
            <button type="button" data-tab-button="institusi" class="border-b-2 px-4 py-2.5 text-sm font-medium transition">
                Institusi
            </button>
        @endif
    </div>

    <div data-tab-panel="unassigned" class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <p class="mb-1 font-bold text-slate-900 dark:text-white">Anggota Belum Ditugaskan</p>
        <p class="mb-4 border-b border-black/5 pb-4 text-sm text-slate-500 dark:border-white/5 dark:text-slate-400">Anggota terdaftar yang belum memiliki peran apapun.</p>

        <form method="GET" class="mb-4">
            <input type="hidden" name="tab" data-tab-hidden-field value="{{ $activeTab }}">
            <label class="relative block w-full max-w-sm">
                <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                    type="search"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Cari nama atau email…"
                    class="w-full rounded-full border border-black/10 bg-slate-50 py-2.5 pr-4 pl-9 text-sm font-medium text-slate-700 shadow-sm transition placeholder:font-normal placeholder:text-slate-400 hover:bg-slate-100 focus:bg-white focus:outline-none dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:placeholder:text-slate-500 dark:hover:bg-slate-700 dark:focus:bg-slate-800"
                >
            </label>
        </form>

        <script>
            var assignScopeData = @json($scopeDataByLevel);
        </script>

        @if ($unassigned->isEmpty())
            <p class="py-6 text-center text-sm text-slate-400 dark:text-slate-500">Tidak ada anggota yang cocok.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-slate-500 dark:text-slate-400">
                            <th class="py-2 pr-2 font-medium">#</th>
                            <th class="py-2 pr-2 font-medium">Pengguna</th>
                            <th class="py-2 pr-2 font-medium">Status</th>
                            <th class="py-2 pr-2 font-medium">Tugaskan</th>
                            <th class="py-2 pr-2 font-medium">Aksi</th>
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
                                                placeholder="Cari…"
                                                class="w-36 rounded-lg border border-black/10 bg-white px-2 py-1.5 text-xs shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800"
                                            >
                                            <ul
                                                data-searchable-select-list
                                                class="absolute left-0 top-full z-20 mt-1 hidden max-h-52 w-56 overflow-y-auto rounded-lg border border-black/10 bg-white p-1 text-xs shadow-lg dark:border-white/10 dark:bg-slate-800"
                                            ></ul>
                                        </div>
                                        <button type="submit" class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition hover:bg-blue-700">
                                            Tugaskan
                                        </button>
                                    </form>
                                </td>
                                <td class="py-2 pr-2">
                                    @include('admin.users.partials.row-actions', ['user' => $user])
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
                var LEVEL_LABELS = { uni: 'Uni', daerah: 'Daerah', gereja: 'Gereja', institusi: 'Institusi' };

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
                    wrapper._searchableSelect.setOptions(assignScopeData[level] || [], 'Cari ' + (LEVEL_LABELS[level] || '') + '…');
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
            {{ $canBootstrapAnyLevel ? 'Semua Admin' : 'Admin ' . $levelLabels[$targetLevel] }}
        </p>
        <p class="mb-4 border-b border-black/5 pb-4 text-sm text-slate-500 dark:border-white/5 dark:text-slate-400">Admin yang sedang aktif mengelola wilayahnya masing-masing.</p>

        @if ($adminUsers->isEmpty())
            <p class="py-6 text-center text-sm text-slate-400 dark:text-slate-500">Belum ada admin yang ditugaskan.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-slate-500 dark:text-slate-400">
                            <th class="py-2 pr-2 font-medium">#</th>
                            <th class="py-2 pr-2 font-medium">Pengguna</th>
                            @if ($canBootstrapAnyLevel)
                                <th class="py-2 pr-2 font-medium">Peran</th>
                                <th class="py-2 pr-2 font-medium">Cakupan</th>
                            @endif
                            <th class="py-2 pr-2 font-medium">Status</th>
                            <th class="py-2 pr-2 font-medium">Aksi</th>
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
                                            data-confirm="Cabut peran &quot;{{ $user->name }}&quot;? Mereka akan kembali menjadi anggota biasa."
                                        >
                                            @csrf
                                            <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">
                                                Cabut
                                            </button>
                                        </form>
                                        @include('admin.users.partials.row-actions', ['user' => $user])
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
            {{ $canBootstrapAnyLevel ? 'Semua Pemimpin' : 'Pemimpin ' . $levelLabels[$targetLevel] }}
        </p>
        <p class="mb-4 border-b border-black/5 pb-4 text-sm text-slate-500 dark:border-white/5 dark:text-slate-400">Pemimpin dengan akses lihat-saja di wilayahnya masing-masing.</p>

        @if ($pimpinanUsers->isEmpty())
            <p class="py-6 text-center text-sm text-slate-400 dark:text-slate-500">Belum ada pemimpin yang ditugaskan.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-slate-500 dark:text-slate-400">
                            <th class="py-2 pr-2 font-medium">#</th>
                            <th class="py-2 pr-2 font-medium">Pengguna</th>
                            @if ($canBootstrapAnyLevel)
                                <th class="py-2 pr-2 font-medium">Peran</th>
                                <th class="py-2 pr-2 font-medium">Cakupan</th>
                            @endif
                            <th class="py-2 pr-2 font-medium">Status</th>
                            <th class="py-2 pr-2 font-medium">Aksi</th>
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
                                            data-confirm="Cabut peran &quot;{{ $user->name }}&quot;? Mereka akan kembali menjadi anggota biasa."
                                        >
                                            @csrf
                                            <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">
                                                Cabut
                                            </button>
                                        </form>
                                        @include('admin.users.partials.row-actions', ['user' => $user])
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
            <p class="mb-1 font-bold text-slate-900 dark:text-white">Admin &amp; Pimpinan Institusi</p>
            <p class="mb-4 border-b border-black/5 pb-4 text-sm text-slate-500 dark:border-white/5 dark:text-slate-400">
                Institusi berdiri sendiri, tidak di bawah Uni/Daerah — kelola daftarnya di <a href="{{ route('admin.hierarchy.index', ['tab' => 'institusi']) }}" class="font-medium text-blue-600 hover:underline dark:text-blue-400">Kelola Organisasi</a>.
            </p>

            <div class="overflow-x-auto">
                @if ($institutionAdmins->isEmpty() && $institutionPimpinan->isEmpty())
                    <p class="py-6 text-center text-sm text-slate-400 dark:text-slate-500">Belum ada Admin/Pimpinan Institusi.</p>
                @else
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="text-slate-500 dark:text-slate-400">
                                <th class="py-2 pr-2 font-medium">#</th>
                                <th class="py-2 pr-2 font-medium">Pengguna</th>
                                <th class="py-2 pr-2 font-medium">Peran</th>
                                <th class="py-2 pr-2 font-medium">Institusi</th>
                                <th class="py-2 pr-2 font-medium">Status</th>
                                <th class="py-2 pr-2 font-medium">Aksi</th>
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
                                                data-confirm="Cabut peran &quot;{{ $user->name }}&quot;? Mereka akan kembali menjadi anggota biasa."
                                            >
                                                @csrf
                                                <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">
                                                    Cabut
                                                </button>
                                            </form>
                                            @include('admin.users.partials.row-actions', ['user' => $user])
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

    @include('partials.tab-script', ['activeTab' => $activeTab])
@endsection
