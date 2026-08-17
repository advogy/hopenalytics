@extends('layouts.app')

@section('title', ($person->exists ? __('entity.title_edit_person') : __('entity.title_add_person')) . ' — ' . config('app.name'))

@php
    // This standalone edit form is only ever reached from Kelola Akun's Personal tab row-actions
    // now (people.show has no Edit link anymore — it's read-only display, reached from Analitik
    // & Grafik Personal instead) — mirrors churches/form.blade.php going back to Kelola Akun. A
    // self-registered member editing their own name/city does so inline on Profil Saya's Info
    // Personal tab, never through this page.
    $backRoute = route('admin.accounts.index', ['tab' => 'personal']);

    // PersonPolicy::delete() checks Person::whereKey($person->id)->visibleTo($user)->exists(),
    // which is false for an unsaved Person (id is null) — so this is already false on the create
    // form, no separate $person->exists check needed alongside it.
    $canManagePerson = auth()->user()->can('delete', $person);
@endphp

@section('content')
    <x-entity-crud-form
        :entity="$person"
        :action="$person->exists ? route('people.update', $person) : route('people.store')"
        :back-url="$backRoute"
        :title="$person->exists ? __('entity.title_edit_person') : __('entity.title_add_person')"
        :submit-label="$person->exists ? __('common.save_changes') : __('entity.title_add_person')"
        :toggle-action="$canManagePerson ? route('people.toggle-active', $person) : null"
        :toggle-confirm="$canManagePerson && $person->is_active ? __('entity.deactivate_person_confirm', ['name' => $person->name]) : null"
        :toggle-label="$person->is_active ? __('entity.deactivate_person') : __('entity.activate_person')"
    >
        <x-form-field name="name" :label="__('entity.name')" required :value="$person->name" :placeholder="__('entity.name_placeholder_person')" />
        <x-similar-name-check :route="route('people.similar')" :exclude-id="$person->exists ? $person->id : null" />

        <x-form-field name="city" :label="__('entity.city')" :hint="__('entity.city_optional_map')" :value="$person->city" :placeholder="__('entity.city_placeholder')" />

        <x-coordinate-fields :latitude="$person->latitude" :longitude="$person->longitude" />

        @php
            // admin_uni's $conferences is already scoped to their own union by
            // personOrgScopeData(), so the same conference list works whether or not a Uni step
            // renders — only the placeholder wording ("kosongkan untuk seluruh Uni" only makes
            // sense once a Uni is already implied) differs between the two pickable cases.
            $conferencePlaceholder = $canPickUnion
                ? __('accounts.search_daerah_optional')
                : __('accounts.search_daerah_optional_own_union');
        @endphp

        <div class="mb-5">
            <label class="mb-1.5 block text-sm font-medium">{{ __('entity.person_org_scope_label') }}</label>
            <p class="mb-2 text-xs text-slate-400">{{ __('entity.person_org_scope_hint') }}</p>

            @if ($canPickUnion || ($ownUnion ?? null))
                @if ($canPickUnion)
                    {{-- Nasional/Divisi/Global-level: full Uni → Daerah cascade, both optional. --}}
                    <div class="mb-2 relative" data-searchable-select data-person-union>
                        <input type="hidden" name="union_id" data-searchable-select-value value="{{ old('union_id', $person->union_id) }}">
                        <input
                            type="text"
                            data-searchable-select-search
                            autocomplete="off"
                            placeholder="{{ __('accounts.search_uni_optional') }}"
                            class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800"
                        >
                        <ul data-searchable-select-list class="absolute left-0 top-full z-20 mt-1 hidden max-h-52 w-full overflow-y-auto rounded-lg border border-black/10 bg-white p-1 text-sm shadow-lg dark:border-white/10 dark:bg-slate-800"></ul>
                    </div>
                @else
                    {{-- admin_uni: pinned to their own Uni server-side (PersonController::
                         resolveOrgScope() trusts $user->union_id, never the request), only the
                         Daerah beneath it is a real choice — so there's no union_id field to
                         submit here at all, just this read-only label. --}}
                    <p class="mb-2 rounded-lg border border-black/10 bg-slate-50 px-3 py-2 text-sm text-slate-500 dark:border-white/10 dark:bg-slate-800 dark:text-slate-400">
                        {{ $ownUnion->name }}
                    </p>
                @endif

                <div class="relative" data-searchable-select data-person-conference>
                    <input type="hidden" name="conference_id" data-searchable-select-value value="{{ old('conference_id', $person->conference_id) }}">
                    <input
                        type="text"
                        data-searchable-select-search
                        autocomplete="off"
                        placeholder="{{ $conferencePlaceholder }}"
                        class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800"
                    >
                    <ul data-searchable-select-list class="absolute left-0 top-full z-20 mt-1 hidden max-h-52 w-full overflow-y-auto rounded-lg border border-black/10 bg-white p-1 text-sm shadow-lg dark:border-white/10 dark:bg-slate-800"></ul>
                </div>
                @error('union_id')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
                @error('conference_id')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror

                <script>
                    window.initUnionConferenceCascade({
                        unionSelector: @json($canPickUnion ? '[data-person-union]' : null),
                        conferenceSelector: '[data-person-conference]',
                        unions: @json($unions->map(fn ($u) => ['id' => $u->id, 'label' => $u->name])),
                        conferences: @json($conferences->map(fn ($c) => ['id' => $c->id, 'union_id' => $c->union_id, 'label' => $c->name])),
                        unionPlaceholder: @json(__('accounts.search_uni_optional')),
                        conferencePlaceholder: @json($conferencePlaceholder),
                        conferenceWaitingPlaceholder: @json(__('accounts.waiting_for_uni')),
                    });
                </script>
            @else
                {{-- admin_daerah (pinned to their own Daerah, no picker), gereja/institusi-level,
                     or a self-editing member (PersonPolicy's self-ownership carve-out) — none of
                     these submit a union_id/conference_id field at all, so resolveOrgScope()
                     simply keeps whatever this Person already had. --}}
                <p class="rounded-lg border border-black/10 bg-slate-50 px-3 py-2 text-sm text-slate-500 dark:border-white/10 dark:bg-slate-800 dark:text-slate-400">
                    {{ $person->conference ? "{$person->conference->name} ({$person->conference->union->name})" : ($person->union?->name ?? __('entity.person_org_scope_independent')) }}
                </p>
            @endif
        </div>

        <x-slot:footerExtra>
            @if ($canManagePerson)
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
        </x-slot:footerExtra>
    </x-entity-crud-form>
@endsection
