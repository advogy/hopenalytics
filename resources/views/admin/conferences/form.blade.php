@extends('layouts.app')

@section('title', ($conference->exists ? 'Edit Daerah' : 'Tambah Daerah') . ' — ' . config('app.name'))

@section('content')
    <a href="{{ route('admin.hierarchy.index', ['tab' => 'daerah']) }}" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
        &larr; {{ __('common.back') }}
    </a>

    <h1 class="mb-8 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
        {{ $conference->exists ? 'Edit Daerah' : 'Tambah Daerah' }}
    </h1>

    <form
        method="POST"
        action="{{ $conference->exists ? route('admin.conferences.update', $conference) : route('admin.conferences.store') }}"
        class="max-w-lg rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900"
    >
        @csrf
        @if ($conference->exists)
            @method('PUT')
        @endif

        <x-form-field name="name" label="Nama Daerah" required :value="$conference->name" />

        <div class="mb-5">
            <label for="union_id" class="mb-1.5 block text-sm font-medium">Uni</label>
            <select
                id="union_id"
                name="union_id"
                required
                class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800"
            >
                <option value="">Pilih Uni…</option>
                @foreach ($unions as $union)
                    <option value="{{ $union->id }}" @selected(old('union_id', $conference->union_id) == $union->id)>{{ $union->name }}</option>
                @endforeach
            </select>
            @error('union_id')
                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
                {{ $conference->exists ? __('common.save_changes') : 'Tambah Daerah' }}
            </button>
            <a href="{{ route('admin.hierarchy.index', ['tab' => 'daerah']) }}" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                {{ __('common.cancel') }}
            </a>
        </div>
    </form>

    @if ($conference->exists)
        <form
            method="POST"
            action="{{ route('admin.conferences.destroy', $conference) }}"
            class="mt-6 max-w-lg"
            data-confirm="Nonaktifkan Daerah &quot;{{ $conference->name }}&quot;?"
        >
            @csrf
            @method('DELETE')
            <button type="submit" class="text-sm text-red-600 hover:underline dark:text-red-400">
                Nonaktifkan Daerah
            </button>
        </form>
    @endif
@endsection
