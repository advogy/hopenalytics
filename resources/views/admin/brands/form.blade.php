@extends('layouts.app')

@section('title', ($brand->exists ? __('brands.title_edit') : __('brands.title_add')) . ' — ' . config('app.name'))

@section('content')
    <x-back-link :href="route('admin.brands.index')">{{ __('common.back') }}</x-back-link>

    <h1 class="mb-8 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
        {{ $brand->exists ? __('brands.title_edit') : __('brands.title_add') }}
    </h1>

    <form
        method="POST"
        action="{{ $brand->exists ? route('admin.brands.update', $brand) : route('admin.brands.store') }}"
        enctype="multipart/form-data"
        class="max-w-lg rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900"
    >
        @csrf
        @if ($brand->exists)
            @method('PUT')
        @endif

        <x-form-field name="name" :label="__('brands.name')" required :value="$brand->name" />

        <x-form-field
            name="domain"
            :label="__('brands.domain')"
            :hint="__('brands.domain_hint')"
            required
            :value="$brand->domain"
            placeholder="{{ __('brands.domain_placeholder') }}"
        />

        <div class="mb-5">
            <label for="logo" class="mb-1.5 block text-sm font-medium">{{ __('brands.logo') }}</label>
            <p class="mb-1.5 text-xs text-slate-400">
                {{ $brand->exists ? __('brands.logo_hint_edit') : __('brands.logo_hint_create') }}
            </p>

            @if ($brand->logoUrl())
                <div class="mb-2 flex items-center gap-2">
                    <img src="{{ $brand->logoUrl() }}" alt="{{ $brand->name }}" class="h-12 w-12 rounded border border-black/10 object-contain dark:border-white/10">
                    <span class="text-xs text-slate-400">{{ __('brands.logo_current') }}</span>
                </div>
            @endif

            <input
                type="file"
                id="logo"
                name="logo"
                accept="image/png,image/jpeg,image/svg+xml,image/webp"
                class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800"
            >
            @error('logo')
                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
                {{ $brand->exists ? __('common.save_changes') : __('brands.title_add') }}
            </button>
            <a href="{{ route('admin.brands.index') }}" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                {{ __('common.cancel') }}
            </a>
        </div>
    </form>
@endsection
