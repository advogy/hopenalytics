@extends('layouts.app')

@section('title', ($church->exists ? __('entity.title_edit_church') : __('entity.title_add_church')) . ' — ' . config('app.name'))

@section('content')
    <a
        href="{{ $church->exists ? route('churches.show', $church) : route('churches.directory', ['tab' => 'gereja']) }}"
        class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400"
    >
        &larr; {{ __('common.back') }}
    </a>

    <h1 class="mb-8 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
        {{ $church->exists ? __('entity.title_edit_church') : __('entity.title_add_church') }}
    </h1>

    <form
        method="POST"
        action="{{ $church->exists ? route('churches.update', $church) : route('churches.store') }}"
        class="max-w-lg rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900"
    >
        @csrf
        @if ($church->exists)
            @method('PUT')
        @endif

        <x-form-field name="name" :label="__('entity.church_name')" required :value="$church->name" :placeholder="__('entity.name_placeholder')" />

        <x-form-field name="city" :label="__('entity.city')" :hint="__('entity.city_optional')" :value="$church->city" :placeholder="__('entity.city_placeholder')" />

        <x-form-field name="logo_url" :label="__('entity.logo_url')" :hint="__('entity.city_optional')" type="url" :value="$church->logo_url" placeholder="https://..." />

        <x-coordinate-fields :latitude="$church->latitude" :longitude="$church->longitude" />

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
                {{ $church->exists ? __('common.save_changes') : __('entity.title_add_church') }}
            </button>
            <a href="{{ $church->exists ? route('churches.show', $church) : route('churches.directory', ['tab' => 'gereja']) }}" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                {{ __('common.cancel') }}
            </a>
        </div>
    </form>

    @if ($church->exists)
        <form
            method="POST"
            action="{{ route('churches.destroy', $church) }}"
            class="mt-6 max-w-lg"
            data-confirm="{{ __('entity.deactivate_church_confirm') }}"
        >
            @csrf
            @method('DELETE')
            <button type="submit" class="text-sm text-red-600 hover:underline dark:text-red-400">
                {{ __('entity.deactivate_church') }}
            </button>
        </form>
    @endif
@endsection
