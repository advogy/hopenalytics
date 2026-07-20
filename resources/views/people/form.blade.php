@extends('layouts.app')

@section('title', ($person->exists ? __('entity.title_edit_person') : __('entity.title_add_person')) . ' — ' . config('app.name'))

@php
    // Self-registered members reach this form to edit their own Person (PersonPolicy's
    // self-ownership carve-out) and can't see Kelola Personal — send them back to their own
    // profile instead. Admins (who can see the management page) go back there.
    $backRoute = auth()->user()->can('browse-directory-analytics')
        ? route('admin.people.index')
        : ($person->exists ? route('people.show', $person) : route('churches.directory', ['tab' => 'personal']));
@endphp

@section('content')
    <a
        href="{{ $backRoute }}"
        class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400"
    >
        &larr; {{ __('common.back') }}
    </a>

    <h1 class="mb-8 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
        {{ $person->exists ? __('entity.title_edit_person') : __('entity.title_add_person') }}
    </h1>

    <form
        method="POST"
        action="{{ $person->exists ? route('people.update', $person) : route('people.store') }}"
        class="max-w-lg rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900"
    >
        @csrf
        @if ($person->exists)
            @method('PUT')
        @endif

        <x-form-field name="name" :label="__('entity.name')" required :value="$person->name" :placeholder="__('entity.name_placeholder_person')" />

        <x-form-field name="city" :label="__('entity.city')" :hint="__('entity.city_optional_map')" :value="$person->city" :placeholder="__('entity.city_placeholder')" />

        <x-coordinate-fields :latitude="$person->latitude" :longitude="$person->longitude" />

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
                {{ $person->exists ? __('common.save_changes') : __('entity.title_add_person') }}
            </button>
            <a href="{{ $backRoute }}" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                {{ __('common.cancel') }}
            </a>
        </div>
    </form>

    @can('delete', $person)
        <form
            method="POST"
            action="{{ route('people.toggle-active', $person) }}"
            class="mt-6 max-w-lg"
            @if ($person->is_active) data-confirm="{{ __('entity.deactivate_person_confirm', ['name' => $person->name]) }}" @endif
        >
            @csrf
            @method('PATCH')
            <button type="submit" class="text-sm text-red-600 hover:underline dark:text-red-400">
                {{ $person->is_active ? __('entity.deactivate_person') : __('entity.activate_person') }}
            </button>
        </form>
    @endcan
@endsection
