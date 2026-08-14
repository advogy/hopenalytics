@extends('layouts.app')

@section('title', ($institution->exists ? __('accounts.title_edit_institusi') : __('accounts.title_add_institusi')) . ' — ' . config('app.name'))

@section('content')
    <x-entity-crud-form
        :entity="$institution"
        :action="$institution->exists ? route('admin.institutions.update', $institution) : route('admin.institutions.store')"
        :back-url="route('admin.accounts.index', ['tab' => 'institusi'])"
        :title="$institution->exists ? __('accounts.title_edit_institusi') : __('accounts.title_add_institusi')"
        :submit-label="$institution->exists ? __('common.save_changes') : __('accounts.title_add_institusi')"
        :destroy-action="$institution->exists ? route('admin.institutions.destroy', $institution) : null"
        :destroy-confirm="__('accounts.deactivate_institusi_confirm', ['name' => $institution->name])"
        :destroy-label="__('accounts.deactivate_institusi')"
    >
        <x-form-field name="name" :label="__('accounts.institusi_name')" required :value="$institution->name" />
        <x-similar-name-check :route="route('admin.institutions.similar')" :exclude-id="$institution->exists ? $institution->id : null" />

        <x-form-field name="city" :label="__('entity.city')" :hint="__('entity.city_optional_map')" :value="$institution->city" :placeholder="__('entity.city_placeholder')" />

        <x-coordinate-fields :latitude="$institution->latitude" :longitude="$institution->longitude" />

        @php
            // admin_uni's $conferences is already scoped to their own union by
            // regionPickerData(), so the same conference list works whether or not a Uni step
            // renders — only the placeholder wording ("kosongkan untuk seluruh Uni" only makes
            // sense once a Uni is already implied) differs between the two pickable cases.
            $conferencePlaceholder = $canPickUnion
                ? __('accounts.search_daerah_optional')
                : __('accounts.search_daerah_optional_own_union');
        @endphp

        <div class="mb-5">
            <label class="mb-1.5 block text-sm font-medium">{{ __('accounts.institusi_region_label') }}</label>
            <p class="mb-2 text-xs text-slate-400">{{ __('accounts.institusi_region_hint') }}</p>

            @if ($canPickUnion || ($ownUnion ?? null))
                @if ($canPickUnion)
                    {{-- Nasional-level: full Uni → Daerah cascade, both optional. --}}
                    <div class="mb-2 relative" data-searchable-select data-institution-union>
                        <input type="hidden" name="union_id" data-searchable-select-value value="{{ old('union_id', $institution->union_id) }}">
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
                    {{-- admin_uni: pinned to their own Uni server-side (InstitutionController::
                         resolveRegion() trusts $user->union_id, never the request), only the
                         Daerah beneath it is a real choice — so there's no union_id field to
                         submit here at all, just this read-only label. --}}
                    <p class="mb-2 rounded-lg border border-black/10 bg-slate-50 px-3 py-2 text-sm text-slate-500 dark:border-white/10 dark:bg-slate-800 dark:text-slate-400">
                        {{ $ownUnion->name }}
                    </p>
                @endif

                <div class="relative" data-searchable-select data-institution-conference>
                    <input type="hidden" name="conference_id" data-searchable-select-value value="{{ old('conference_id', $institution->conference_id) }}">
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
                        unionSelector: @json($canPickUnion ? '[data-institution-union]' : null),
                        conferenceSelector: '[data-institution-conference]',
                        unions: @json($unions->map(fn ($u) => ['id' => $u->id, 'label' => $u->name])),
                        conferences: @json($conferences->map(fn ($c) => ['id' => $c->id, 'union_id' => $c->union_id, 'label' => $c->name])),
                        unionPlaceholder: @json(__('accounts.search_uni_optional')),
                        conferencePlaceholder: @json($conferencePlaceholder),
                        conferenceWaitingPlaceholder: @json(__('accounts.waiting_for_uni')),
                    });
                </script>
            @else
                {{-- admin_daerah (pinned to both, no picker) or a fresh nasional institution with nothing to show yet. --}}
                <p class="rounded-lg border border-black/10 bg-slate-50 px-3 py-2 text-sm text-slate-500 dark:border-white/10 dark:bg-slate-800 dark:text-slate-400">
                    {{ $institution->conference ? "{$institution->conference->name} ({$institution->conference->union->name})" : ($institution->union?->name ?? __('accounts.institusi_region_nasional')) }}
                </p>
            @endif
        </div>
    </x-entity-crud-form>
@endsection
