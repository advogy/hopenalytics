@extends('layouts.app')

@section('title', ($conference->exists ? __('accounts.title_edit_daerah') : __('accounts.title_add_daerah')) . ' — ' . config('app.name'))

@section('content')
    <a href="{{ route('admin.accounts.index', ['tab' => 'daerah']) }}" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
        &larr; {{ __('common.back') }}
    </a>

    <h1 class="mb-8 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
        {{ $conference->exists ? __('accounts.title_edit_daerah') : __('accounts.title_add_daerah') }}
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

        <x-form-field name="name" :label="__('accounts.daerah_name')" required :value="$conference->name" />

        <div class="mb-5">
            <label class="mb-1.5 block text-sm font-medium">{{ __('common.union') }}</label>
            @if ($canPickUnion)
                <select
                    id="union_id"
                    name="union_id"
                    required
                    class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800"
                >
                    <option value="">{{ __('accounts.choose_uni_placeholder') }}</option>
                    @foreach ($unions as $union)
                        <option value="{{ $union->id }}" @selected(old('union_id', $conference->union_id) == $union->id)>{{ $union->name }}</option>
                    @endforeach
                </select>
                @error('union_id')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            @else
                {{-- admin_uni: always their own union, submitted server-side regardless of this field. --}}
                <input type="hidden" name="union_id" value="{{ $ownUnion->id ?? '' }}">
                <p class="rounded-lg border border-black/10 bg-slate-50 px-3 py-2 text-sm text-slate-500 dark:border-white/10 dark:bg-slate-800 dark:text-slate-400">
                    {{ $ownUnion->name ?? '—' }}
                </p>
            @endif
        </div>

        <x-coordinate-fields :latitude="$conference->latitude" :longitude="$conference->longitude" />

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
                {{ $conference->exists ? __('common.save_changes') : __('accounts.title_add_daerah') }}
            </button>
            <a href="{{ route('admin.accounts.index', ['tab' => 'daerah']) }}" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                {{ __('common.cancel') }}
            </a>
        </div>
    </form>

    @if ($conference->exists)
        <form
            method="POST"
            action="{{ route('admin.conferences.destroy', $conference) }}"
            class="mt-6 max-w-lg"
            data-confirm="{{ __('accounts.deactivate_daerah_confirm', ['name' => $conference->name]) }}"
        >
            @csrf
            @method('DELETE')
            <button type="submit" class="text-sm text-red-600 hover:underline dark:text-red-400">
                {{ __('accounts.deactivate_daerah') }}
            </button>
        </form>
    @endif
@endsection
