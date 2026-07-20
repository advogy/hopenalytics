@extends('layouts.app')

@section('title', 'Kelola Organisasi — ' . config('app.name'))

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Kelola Organisasi</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Uni &rarr; Daerah &rarr; Gereja.</p>
        </div>

        <div class="flex items-center gap-3">
            <div data-tab-panel="uni">
                <a
                    href="{{ route('admin.unions.create') }}"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700"
                >
                    + Tambah Uni
                </a>
            </div>
            <div data-tab-panel="daerah">
                <a
                    href="{{ route('admin.conferences.create') }}"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700"
                >
                    + Tambah Daerah
                </a>
            </div>
            <div data-tab-panel="gereja">
                @can('create', App\Models\Church::class)
                    <a
                        href="{{ route('churches.create') }}"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700"
                    >
                        + Tambah Gereja
                    </a>
                @endcan
            </div>
            <div data-tab-panel="institusi">
                <a
                    href="{{ route('admin.institutions.create') }}"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700"
                >
                    + Tambah Institusi
                </a>
            </div>
        </div>
    </div>

    <div class="mb-6 flex gap-2 border-b border-black/5 dark:border-white/5">
        <button type="button" data-tab-button="uni" class="border-b-2 px-4 py-2.5 text-sm font-medium transition">
            Uni
        </button>
        <button type="button" data-tab-button="daerah" class="border-b-2 px-4 py-2.5 text-sm font-medium transition">
            Daerah
        </button>
        <button type="button" data-tab-button="gereja" class="border-b-2 px-4 py-2.5 text-sm font-medium transition">
            Gereja
        </button>
        <button type="button" data-tab-button="institusi" class="border-b-2 px-4 py-2.5 text-sm font-medium transition">
            Institusi
        </button>
    </div>

    <div data-tab-panel="uni" class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <p class="mb-1 font-bold text-slate-900 dark:text-white">Daftar Uni</p>
        <p class="mb-4 border-b border-black/5 pb-4 text-sm text-slate-500 dark:border-white/5 dark:text-slate-400">Wilayah kerja tingkat nasional, tingkat tertinggi dalam struktur organisasi.</p>

        <form method="GET" class="mb-4">
            <input type="hidden" name="tab" data-tab-hidden-field value="{{ $activeTab }}">
            <label class="relative block w-full max-w-sm">
                <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                    type="search"
                    name="search_uni"
                    value="{{ $searchUni }}"
                    placeholder="Cari nama Uni…"
                    class="w-full rounded-full border border-black/10 bg-slate-50 py-2.5 pr-4 pl-9 text-sm font-medium text-slate-700 shadow-sm transition placeholder:font-normal placeholder:text-slate-400 hover:bg-slate-100 focus:bg-white focus:outline-none dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:placeholder:text-slate-500 dark:hover:bg-slate-700 dark:focus:bg-slate-800"
                >
            </label>
        </form>

        @if ($unions->isEmpty())
            <p class="py-6 text-center text-sm text-slate-400 dark:text-slate-500">{{ $searchUni ? 'Tidak ada Uni yang cocok.' : 'Belum ada Uni.' }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-slate-500 dark:text-slate-400">
                            <th class="py-2 pr-2 font-medium">#</th>
                            <th class="py-2 pr-2 font-medium">Nama</th>
                            <th class="py-2 pr-2 text-right font-medium">Jumlah Daerah</th>
                            <th class="py-2 pr-2 text-right font-medium">Jumlah Person</th>
                            <th class="py-2 pr-2 font-medium">Status</th>
                            <th class="py-2 text-right font-medium">Aksi</th>
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
                                        <span class="text-emerald-600 dark:text-emerald-400">Aktif</span>
                                    @else
                                        <span class="text-slate-400">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="py-2">
                                    @include('admin.hierarchy.partials.row-actions', [
                                        'item' => $union,
                                        'editRoute' => 'admin.unions.edit',
                                        'toggleRoute' => 'admin.unions.toggle-active',
                                        'deleteRoute' => 'admin.unions.destroy',
                                        'name' => $union->name,
                                        'canDelete' => $union->conferences_count === 0 && $union->users_count === 0,
                                        'blockedReason' => 'Masih memiliki Daerah dan/atau pengguna yang ditugaskan.',
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

    <div data-tab-panel="daerah" class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <p class="mb-1 font-bold text-slate-900 dark:text-white">Daftar Daerah</p>
        <p class="mb-4 border-b border-black/5 pb-4 text-sm text-slate-500 dark:border-white/5 dark:text-slate-400">Wilayah kerja di bawah Uni, menaungi gereja-gereja di dalamnya.</p>

        <form method="GET" class="mb-4">
            <input type="hidden" name="tab" data-tab-hidden-field value="{{ $activeTab }}">
            <label class="relative block w-full max-w-sm">
                <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                    type="search"
                    name="search_daerah"
                    value="{{ $searchDaerah }}"
                    placeholder="Cari nama Daerah…"
                    class="w-full rounded-full border border-black/10 bg-slate-50 py-2.5 pr-4 pl-9 text-sm font-medium text-slate-700 shadow-sm transition placeholder:font-normal placeholder:text-slate-400 hover:bg-slate-100 focus:bg-white focus:outline-none dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:placeholder:text-slate-500 dark:hover:bg-slate-700 dark:focus:bg-slate-800"
                >
            </label>
        </form>

        @if ($conferences->isEmpty())
            <p class="py-6 text-center text-sm text-slate-400 dark:text-slate-500">{{ $searchDaerah ? 'Tidak ada Daerah yang cocok.' : 'Belum ada Daerah.' }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-slate-500 dark:text-slate-400">
                            <th class="py-2 pr-2 font-medium">#</th>
                            <th class="py-2 pr-2 font-medium">Nama</th>
                            <th class="py-2 pr-2 font-medium">Uni</th>
                            <th class="py-2 pr-2 text-right font-medium">Jumlah Gereja</th>
                            <th class="py-2 pr-2 font-medium">Status</th>
                            <th class="py-2 text-right font-medium">Aksi</th>
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
                                        <span class="text-emerald-600 dark:text-emerald-400">Aktif</span>
                                    @else
                                        <span class="text-slate-400">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="py-2">
                                    @include('admin.hierarchy.partials.row-actions', [
                                        'item' => $conference,
                                        'editRoute' => 'admin.conferences.edit',
                                        'toggleRoute' => 'admin.conferences.toggle-active',
                                        'deleteRoute' => 'admin.conferences.destroy',
                                        'name' => $conference->name,
                                        'canDelete' => $conference->churches_count === 0 && $conference->users_count === 0,
                                        'blockedReason' => 'Masih memiliki Gereja dan/atau pengguna yang ditugaskan.',
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

    <div data-tab-panel="gereja" class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <p class="mb-1 font-bold text-slate-900 dark:text-white">Daftar Gereja</p>
        <p class="mb-4 border-b border-black/5 pb-4 text-sm text-slate-500 dark:border-white/5 dark:text-slate-400">Gereja-gereja yang tergabung dalam sebuah Daerah.</p>

        <form method="GET" class="mb-4">
            <input type="hidden" name="tab" data-tab-hidden-field value="{{ $activeTab }}">
            <label class="relative block w-full max-w-sm">
                <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                    type="search"
                    name="search_gereja"
                    value="{{ $searchGereja }}"
                    placeholder="Cari nama Gereja…"
                    class="w-full rounded-full border border-black/10 bg-slate-50 py-2.5 pr-4 pl-9 text-sm font-medium text-slate-700 shadow-sm transition placeholder:font-normal placeholder:text-slate-400 hover:bg-slate-100 focus:bg-white focus:outline-none dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:placeholder:text-slate-500 dark:hover:bg-slate-700 dark:focus:bg-slate-800"
                >
            </label>
        </form>

        @if ($churches->isEmpty())
            <p class="py-6 text-center text-sm text-slate-400 dark:text-slate-500">{{ $searchGereja ? 'Tidak ada Gereja yang cocok.' : 'Belum ada Gereja.' }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-slate-500 dark:text-slate-400">
                            <th class="py-2 pr-2 font-medium">#</th>
                            <th class="py-2 pr-2 font-medium">Gereja</th>
                            <th class="py-2 pr-2 font-medium">Kota</th>
                            <th class="py-2 pr-2 font-medium">Daerah</th>
                            <th class="py-2 pr-2 font-medium">Status</th>
                            <th class="py-2 text-right font-medium">Aksi</th>
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
                                        <span class="text-amber-600 dark:text-amber-400">Belum ditugaskan</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-2">
                                    @if ($church->is_active)
                                        <span class="text-emerald-600 dark:text-emerald-400">Aktif</span>
                                    @else
                                        <span class="text-slate-400">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="py-2">
                                    @include('admin.hierarchy.partials.row-actions', [
                                        'item' => $church,
                                        'editRoute' => 'churches.edit',
                                        'toggleRoute' => 'churches.toggle-active',
                                        'deleteRoute' => 'churches.destroy',
                                        'name' => $church->name,
                                        'canDelete' => $church->users_count === 0,
                                        'blockedReason' => 'Masih ada pengguna yang ditugaskan ke gereja ini.',
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

    <div data-tab-panel="institusi" class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <p class="mb-1 font-bold text-slate-900 dark:text-white">Daftar Institusi</p>
        <p class="mb-4 border-b border-black/5 pb-4 text-sm text-slate-500 dark:border-white/5 dark:text-slate-400">Institusi berdiri sendiri, terpisah dari rantai Uni &rarr; Daerah &rarr; Gereja.</p>

        <form method="GET" class="mb-4">
            <input type="hidden" name="tab" data-tab-hidden-field value="{{ $activeTab }}">
            <label class="relative block w-full max-w-sm">
                <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                    type="search"
                    name="search_institusi"
                    value="{{ $searchInstitusi }}"
                    placeholder="Cari nama Institusi…"
                    class="w-full rounded-full border border-black/10 bg-slate-50 py-2.5 pr-4 pl-9 text-sm font-medium text-slate-700 shadow-sm transition placeholder:font-normal placeholder:text-slate-400 hover:bg-slate-100 focus:bg-white focus:outline-none dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:placeholder:text-slate-500 dark:hover:bg-slate-700 dark:focus:bg-slate-800"
                >
            </label>
        </form>

        @if ($institutions->isEmpty())
            <p class="py-6 text-center text-sm text-slate-400 dark:text-slate-500">{{ $searchInstitusi ? 'Tidak ada Institusi yang cocok.' : 'Belum ada Institusi.' }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-slate-500 dark:text-slate-400">
                            <th class="py-2 pr-2 font-medium">#</th>
                            <th class="py-2 pr-2 font-medium">Nama</th>
                            <th class="py-2 pr-2 text-right font-medium">Jumlah Pengguna</th>
                            <th class="py-2 pr-2 font-medium">Status</th>
                            <th class="py-2 text-right font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($institutions as $institution)
                            <tr>
                                <td class="py-2 pr-2 text-slate-400 dark:text-slate-500">{{ $institutions->firstItem() + $loop->index }}</td>
                                <td class="py-2 pr-2 font-medium">{{ $institution->name }}</td>
                                <td class="py-2 pr-2 text-right tabular-nums">{{ $institution->users_count }}</td>
                                <td class="py-2 pr-2">
                                    @if ($institution->is_active)
                                        <span class="text-emerald-600 dark:text-emerald-400">Aktif</span>
                                    @else
                                        <span class="text-slate-400">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="py-2">
                                    @include('admin.hierarchy.partials.row-actions', [
                                        'item' => $institution,
                                        'editRoute' => 'admin.institutions.edit',
                                        'toggleRoute' => 'admin.institutions.toggle-active',
                                        'deleteRoute' => 'admin.institutions.destroy',
                                        'name' => $institution->name,
                                        'canDelete' => $institution->users_count === 0,
                                        'blockedReason' => 'Masih ada pengguna yang ditugaskan ke institusi ini.',
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

    @include('partials.tab-script', ['activeTab' => $activeTab])
@endsection
