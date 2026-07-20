@extends('layouts.app')

@section('title', 'Kelola Personal — ' . config('app.name'))

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Kelola Personal</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Akun personal yang datanya dipantau — terpisah dari akun media sosial gereja.</p>
        </div>

        @can('create', App\Models\Person::class)
            <a
                href="{{ route('people.create') }}"
                class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700"
            >
                + Tambah Personal
            </a>
        @endcan
    </div>

    <div class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <p class="mb-1 font-bold text-slate-900 dark:text-white">Daftar Personal</p>
        <p class="mb-4 border-b border-black/5 pb-4 text-sm text-slate-500 dark:border-white/5 dark:text-slate-400">Orang yang akun media sosialnya dipantau, terlepas dari punya login atau tidak.</p>

        <form method="GET" class="mb-4">
            <label class="relative block w-full max-w-sm">
                <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                    type="search"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Cari nama atau kota…"
                    class="w-full rounded-full border border-black/10 bg-slate-50 py-2.5 pr-4 pl-9 text-sm font-medium text-slate-700 shadow-sm transition placeholder:font-normal placeholder:text-slate-400 hover:bg-slate-100 focus:bg-white focus:outline-none dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:placeholder:text-slate-500 dark:hover:bg-slate-700 dark:focus:bg-slate-800"
                >
            </label>
        </form>

        @if ($people->isEmpty())
            <p class="py-6 text-center text-sm text-slate-400 dark:text-slate-500">{{ $search ? 'Tidak ada Personal yang cocok.' : 'Belum ada Personal.' }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-slate-500 dark:text-slate-400">
                            <th class="py-2 pr-2 font-medium">#</th>
                            <th class="py-2 pr-2 font-medium">Nama</th>
                            <th class="py-2 pr-2 font-medium">Kota</th>
                            <th class="py-2 pr-2 font-medium">Cakupan</th>
                            <th class="py-2 pr-2 text-right font-medium">Akun Sosial</th>
                            <th class="py-2 pr-2 font-medium">Status</th>
                            <th class="py-2 text-right font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($people as $person)
                            <tr>
                                <td class="py-2 pr-2 text-slate-400 dark:text-slate-500">{{ $people->firstItem() + $loop->index }}</td>
                                <td class="py-2 pr-2 font-medium">
                                    <a href="{{ route('people.show', $person) }}" class="hover:text-blue-600 dark:hover:text-blue-400">{{ $person->name }}</a>
                                </td>
                                <td class="py-2 pr-2 text-slate-500 dark:text-slate-400">{{ $person->city ?? '—' }}</td>
                                <td class="py-2 pr-2 text-slate-500 dark:text-slate-400">
                                    @if ($person->conference)
                                        {{ $person->conference->name }} <span class="text-slate-400">({{ $person->conference->union->name }})</span>
                                    @elseif ($person->union)
                                        {{ $person->union->name }}
                                    @else
                                        <span class="text-slate-400">Independen</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-2 text-right tabular-nums">{{ $person->socials_count }}</td>
                                <td class="py-2 pr-2">
                                    @if ($person->is_active)
                                        <span class="text-emerald-600 dark:text-emerald-400">Aktif</span>
                                    @else
                                        <span class="text-slate-400">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="py-2">
                                    @include('admin.hierarchy.partials.row-actions', [
                                        'item' => $person,
                                        'editRoute' => 'people.edit',
                                        'toggleRoute' => 'people.toggle-active',
                                        'deleteRoute' => 'people.destroy',
                                        'name' => $person->name,
                                        'canDelete' => true,
                                        'blockedReason' => '',
                                        'deleteWarning' => 'Seluruh akun media sosial dan riwayat datanya juga akan terhapus.',
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
@endsection
