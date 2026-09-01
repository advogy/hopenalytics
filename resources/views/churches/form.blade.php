@extends('layouts.app')

@section('title', ($church->exists ? __('entity.title_edit_church') : __('entity.title_add_church')) . ' — ' . config('app.name'))

@section('content')
    <x-entity-crud-form
        :entity="$church"
        :action="$church->exists ? route('churches.update', $church) : route('churches.store')"
        :back-url="route('admin.accounts.index', ['tab' => 'gereja'])"
        :title="$church->exists ? __('entity.title_edit_church') : __('entity.title_add_church')"
        :submit-label="$church->exists ? __('common.save_changes') : __('entity.title_add_church')"
        :toggle-action="$church->exists ? route('churches.toggle-active', $church) : null"
        :toggle-confirm="$church->is_active ? __('entity.deactivate_church_confirm') : null"
        :toggle-label="$church->is_active ? __('entity.deactivate_church') : __('entity.activate_church')"
    >
        <x-form-field name="name" :label="__('entity.church_name')" required :value="$church->name" :placeholder="__('entity.name_placeholder')" />
        <x-similar-name-check :route="route('churches.similar')" :exclude-id="$church->exists ? $church->id : null" />

        <x-form-field name="city" :label="__('entity.city')" :hint="__('entity.city_optional')" :value="$church->city" :placeholder="__('entity.city_placeholder')" />

        <x-form-field name="country" :label="__('entity.country')" :hint="__('entity.country_optional')" :value="$church->country" :placeholder="__('entity.country_placeholder')" />

        <x-form-field name="logo_url" :label="__('entity.logo_url')" :hint="__('entity.city_optional')" type="url" :value="$church->logo_url" placeholder="https://..." />

        <x-coordinate-fields :latitude="$church->latitude" :longitude="$church->longitude" />

        <div class="mb-5">
            <label class="mb-1.5 block text-sm font-medium">{{ __('entity.conference') }}</label>
            @if ($canPickConference)
                @if ($ownDivision)
                    {{-- admin_divisi: Divisi itself is fixed server-side — only Uni (picked
                         below) and Daerah are real choices. --}}
                    <p class="mb-2 rounded-lg border border-black/10 bg-slate-50 px-3 py-2 text-sm text-slate-500 dark:border-white/10 dark:bg-slate-800 dark:text-slate-400">
                        {{ $ownDivision->name }}
                    </p>
                @endif

                @if ($ownUnion)
                    {{-- admin_uni: pinned to their own Uni server-side (resolveConferenceId()
                         never trusts a submitted conference_id outside it) — only the Daerah
                         beneath it is a real choice, so there's no Uni picker here at all, just
                         this read-only label. --}}
                    <p class="mb-2 rounded-lg border border-black/10 bg-slate-50 px-3 py-2 text-sm text-slate-500 dark:border-white/10 dark:bg-slate-800 dark:text-slate-400">
                        {{ $ownUnion->name }}
                    </p>
                @elseif ($unions->isNotEmpty())
                    {{-- Nasional-level: full Uni → Daerah cascade. Only conference_id is ever
                         actually submitted (name="conference_id" below) — this hidden input's
                         value is pre-filled purely so initUnionConferenceCascade() can preselect
                         the right Uni on page load without a round-trip; it derives that from
                         the church's own conference since a church itself never independently
                         "belongs" to a Uni the way an Institution can. --}}
                    <div class="mb-2 relative" data-searchable-select data-church-union>
                        <input type="hidden" data-searchable-select-value value="{{ $church->conference?->union_id }}">
                        <input
                            type="text"
                            data-searchable-select-search
                            autocomplete="off"
                            placeholder="{{ __('entity.search_uni_placeholder') }}"
                            class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800"
                        >
                        <ul data-searchable-select-list class="absolute left-0 top-full z-20 mt-1 hidden max-h-52 w-full overflow-y-auto rounded-lg border border-black/10 bg-white p-1 text-sm shadow-lg dark:border-white/10 dark:bg-slate-800"></ul>
                    </div>
                @endif
                <div class="relative" data-searchable-select data-church-conference>
                    <input type="hidden" name="conference_id" data-searchable-select-value value="{{ old('conference_id', $church->conference_id) }}">
                    <input
                        type="text"
                        data-searchable-select-search
                        autocomplete="off"
                        placeholder="{{ __('entity.search_daerah_placeholder') }}"
                        class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800"
                    >
                    <ul data-searchable-select-list class="absolute left-0 top-full z-20 mt-1 hidden max-h-52 w-full overflow-y-auto rounded-lg border border-black/10 bg-white p-1 text-sm shadow-lg dark:border-white/10 dark:bg-slate-800"></ul>
                </div>
                @error('conference_id')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror

                <script>
                    window.initUnionConferenceCascade({
                        unionSelector: @json($ownUnion ? null : '[data-church-union]'),
                        conferenceSelector: '[data-church-conference]',
                        unions: @json($unions->map(fn ($u) => ['id' => $u->id, 'label' => $u->name])),
                        conferences: @json($conferences->map(fn ($c) => ['id' => $c->id, 'union_id' => $c->union_id, 'label' => $c->name])),
                        unionPlaceholder: @json(__('entity.search_uni_placeholder')),
                        conferencePlaceholder: @json(__('entity.search_daerah_placeholder')),
                        conferenceWaitingPlaceholder: @json(__('accounts.waiting_for_uni')),
                    });
                </script>
            @else
                @php $displayConference = $ownConference ?? $church->conference @endphp
                <p class="rounded-lg border border-black/10 bg-slate-50 px-3 py-2 text-sm text-slate-500 dark:border-white/10 dark:bg-slate-800 dark:text-slate-400">
                    {{ $displayConference ? "{$displayConference->name} ({$displayConference->union->name})" : __('entity.conference_unassigned') }}
                </p>
            @endif
        </div>
    </x-entity-crud-form>
@endsection
