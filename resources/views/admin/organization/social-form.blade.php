@php
    $indexRouteName = match (true) {
        $owner instanceof \App\Models\Union => 'admin.unions.socials.index',
        $owner instanceof \App\Models\Conference => 'admin.conferences.socials.index',
        $owner instanceof \App\Models\Institution => 'admin.institutions.socials.index',
    };
    $storeRouteName = match (true) {
        $owner instanceof \App\Models\Union => 'admin.unions.socials.store',
        $owner instanceof \App\Models\Conference => 'admin.conferences.socials.store',
        $owner instanceof \App\Models\Institution => 'admin.institutions.socials.store',
    };
@endphp

@extends('layouts.app')

@section('title', ($social->exists ? __('entity.title_edit_social') : __('entity.title_add_social')) . ' — ' . $owner->name)

@section('content')
    <a href="{{ route($indexRouteName, $owner) }}" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
        &larr; {{ __('entity.back_to', ['name' => $owner->name]) }}
    </a>

    <h1 class="mb-8 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
        {{ $social->exists ? __('entity.title_edit_social') : __('entity.title_add_social') }}
    </h1>

    <form
        method="POST"
        action="{{ $social->exists ? route('socials.update', $social) : route($storeRouteName, $owner) }}"
        class="max-w-lg rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900"
    >
        @csrf
        @if ($social->exists)
            @method('PUT')
        @endif

        <div class="mb-5">
            <label for="platform" class="mb-1.5 block text-sm font-medium">{{ __('entity.platform') }}</label>
            <select id="platform" name="platform" required class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800">
                @foreach (\App\Models\AppSetting::current()->enabledPlatformLabels() as $value => $label)
                    <option value="{{ $value }}" @selected(old('platform', $social->platform?->value) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('platform')
                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <x-form-field name="handle" :label="__('entity.handle')" required :value="$social->handle" :placeholder="__('entity.handle_placeholder', ['example' => 'gmahkbekasi'])" />

        <x-form-field
            name="profile_url"
            :label="__('entity.profile_url')"
            :hint="__('entity.profile_url_hint')"
            type="url"
            :value="$social->profile_url"
            placeholder="https://www.facebook.com/..."
        />
        <div id="social-similar-results" class="hidden mb-5 -mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm dark:border-amber-900 dark:bg-amber-950"></div>
        <script>
            window.initSimilarSocialCheck(
                document.getElementById('platform'),
                document.getElementById('handle'),
                document.getElementById('profile_url'),
                document.getElementById('social-similar-results'),
                {
                    url: '{{ route('socials.similar') }}',
                    excludeId: {{ $social->exists ? $social->id : 'null' }},
                }
            );
        </script>

        <div class="mb-6 flex items-center gap-2">
            <input
                type="checkbox" id="is_auto_fetch" name="is_auto_fetch" value="1"
                @checked(old('is_auto_fetch', $social->exists ? $social->is_auto_fetch : true))
                class="h-4 w-4 rounded border-black/20 text-blue-600 focus:ring-blue-500"
            >
            <label for="is_auto_fetch" class="text-sm">
                {{ __('entity.auto_fetch_weekly') }}
                <span class="block text-xs text-slate-400">{{ __('entity.auto_fetch_hint_church') }}</span>
            </label>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
                {{ $social->exists ? __('common.save_changes') : __('entity.title_add_social') }}
            </button>
            <a href="{{ route($indexRouteName, $owner) }}" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                {{ __('common.cancel') }}
            </a>
        </div>
    </form>

    @if ($social->exists)
        <form
            method="POST"
            action="{{ route('socials.destroy', $social) }}"
            class="mt-6 max-w-lg"
            data-confirm="{{ __('entity.delete_account_confirm', ['handle' => $social->display_handle]) }}"
        >
            @csrf
            @method('DELETE')
            <button type="submit" class="text-sm text-red-600 hover:underline dark:text-red-400">
                {{ __('entity.delete_account') }}
            </button>
        </form>
    @endif
@endsection
