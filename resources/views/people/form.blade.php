@extends('layouts.app')

@section('title', ($person->exists ? __('entity.title_edit_person') : __('entity.title_add_person')) . ' — ' . config('app.name'))

@php
    // This standalone edit form is only ever reached from Kelola Akun's Personal tab row-actions
    // now (people.show has no Edit link anymore — it's read-only display, reached from Analitik
    // & Grafik Personal instead) — mirrors churches/form.blade.php going back to Kelola Akun. A
    // self-registered member editing their own name/city does so inline on Profil Saya's Info
    // Personal tab, never through this page.
    $backRoute = route('admin.accounts.index', ['tab' => 'personal']);
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
        @if ($person->exists)
            <div class="mt-6 max-w-lg rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
                <h2 class="mb-1 font-bold text-slate-900 dark:text-white">{{ __('entity.login_account') }}</h2>

                @if ($person->user)
                    <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
                        {{ __('entity.login_account_linked', ['name' => $person->user->name, 'email' => $person->user->email]) }}
                    </p>
                    <form method="POST" action="{{ route('people.unlink-user', $person) }}" data-confirm="{{ __('entity.unlink_user_confirm', ['name' => $person->name]) }}">
                        @csrf
                        <button type="submit" class="text-sm text-red-600 hover:underline dark:text-red-400">
                            {{ __('entity.unlink_user') }}
                        </button>
                    </form>
                @else
                    <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">{{ __('entity.login_account_unlinked') }}</p>
                    <form method="POST" action="{{ route('people.link-user', $person) }}" class="flex items-center gap-2">
                        @csrf
                        <div class="relative flex-1" data-searchable-select data-link-user>
                            <input type="hidden" name="user_id" data-searchable-select-value>
                            <input
                                type="text"
                                data-searchable-select-search
                                autocomplete="off"
                                placeholder="{{ __('entity.search_user_placeholder') }}"
                                class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800"
                            >
                            <ul data-searchable-select-list class="absolute left-0 top-full z-20 mt-1 hidden max-h-52 w-full overflow-y-auto rounded-lg border border-black/10 bg-white p-1 text-sm shadow-lg dark:border-white/10 dark:bg-slate-800"></ul>
                        </div>
                        <button type="submit" class="shrink-0 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
                            {{ __('entity.link_user') }}
                        </button>
                    </form>

                    <script>
                        (function () {
                            var users = @json($linkableUsers->map(fn ($u) => ['id' => $u->id, 'label' => "{$u->name} ({$u->email})"]));
                            var wrapper = document.querySelector('[data-link-user]');
                            window.initSearchableSelect(wrapper).setOptions(users, "{{ __('entity.search_user_placeholder') }}");
                        })();
                    </script>
                @endif
            </div>
        @endif

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
