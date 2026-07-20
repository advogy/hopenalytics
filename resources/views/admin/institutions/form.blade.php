@extends('layouts.app')

@section('title', ($institution->exists ? 'Edit Institusi' : 'Tambah Institusi') . ' — ' . config('app.name'))

@section('content')
    <a href="{{ route('admin.hierarchy.index', ['tab' => 'institusi']) }}" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
        &larr; {{ __('common.back') }}
    </a>

    <h1 class="mb-8 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
        {{ $institution->exists ? 'Edit Institusi' : 'Tambah Institusi' }}
    </h1>

    <form
        method="POST"
        action="{{ $institution->exists ? route('admin.institutions.update', $institution) : route('admin.institutions.store') }}"
        class="max-w-lg rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900"
    >
        @csrf
        @if ($institution->exists)
            @method('PUT')
        @endif

        <x-form-field name="name" label="Nama Institusi" required :value="$institution->name" />

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
                {{ $institution->exists ? __('common.save_changes') : 'Tambah Institusi' }}
            </button>
            <a href="{{ route('admin.hierarchy.index', ['tab' => 'institusi']) }}" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                {{ __('common.cancel') }}
            </a>
        </div>
    </form>

    @if ($institution->exists)
        <form
            method="POST"
            action="{{ route('admin.institutions.destroy', $institution) }}"
            class="mt-6 max-w-lg"
            data-confirm="Nonaktifkan Institusi &quot;{{ $institution->name }}&quot;?"
        >
            @csrf
            @method('DELETE')
            <button type="submit" class="text-sm text-red-600 hover:underline dark:text-red-400">
                Nonaktifkan Institusi
            </button>
        </form>
    @endif
@endsection
