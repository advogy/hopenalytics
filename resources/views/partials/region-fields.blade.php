{{-- Cascading Uni → Daerah/Konferens → Gereja fields, each a searchable combobox (see
     partials/searchable-select.blade.php) instead of a plain <select> since these lists can
     run into the hundreds. Gereja additionally allows typing a name that doesn't exist yet —
     it's created automatically on submit (see CompleteProfileController::findOrCreateChurch()).
     Expects: $unions, $conferences, $churches (each with id/union_id or conference_id/name),
     and optionally $selectedUnionId, $selectedConferenceId, $selectedChurchName to pre-fill. --}}
@php
    $selectedUnionId ??= null;
    $selectedConferenceId ??= null;
    $selectedChurchName ??= null;
    $selectedUnionName = $selectedUnionId ? $unions->firstWhere('id', $selectedUnionId)?->name : null;
    $selectedConferenceName = $selectedConferenceId ? $conferences->firstWhere('id', $selectedConferenceId)?->name : null;
@endphp

<div class="mb-5">
    <label class="mb-1.5 block text-sm font-medium">{{ __('common.union') }}</label>
    <div class="relative" data-searchable-select data-region-union>
        <input type="hidden" name="union_id" data-searchable-select-value value="{{ old('union_id', $selectedUnionId) }}">
        <input
            type="text"
            data-searchable-select-search
            autocomplete="off"
            placeholder="{{ __('entity.search_uni_placeholder') }}"
            value="{{ old('union_id') ? '' : $selectedUnionName }}"
            class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800"
        >
        <ul data-searchable-select-list class="absolute left-0 top-full z-20 mt-1 hidden max-h-52 w-full overflow-y-auto rounded-lg border border-black/10 bg-white p-1 text-sm shadow-lg dark:border-white/10 dark:bg-slate-800"></ul>
    </div>
    @error('union_id')
        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>

<div class="mb-5">
    <label class="mb-1.5 block text-sm font-medium">{{ __('entity.conference') }}</label>
    <div class="relative" data-searchable-select data-region-conference>
        <input type="hidden" name="conference_id" data-searchable-select-value value="{{ old('conference_id', $selectedConferenceId) }}">
        <input
            type="text"
            data-searchable-select-search
            autocomplete="off"
            placeholder="{{ $selectedUnionId ? __('entity.search_daerah_placeholder') : __('accounts.waiting_for_uni') }}"
            value="{{ old('conference_id') ? '' : $selectedConferenceName }}"
            @unless ($selectedUnionId) disabled @endunless
            class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none disabled:bg-slate-100 disabled:cursor-not-allowed dark:border-white/10 dark:bg-slate-800 dark:disabled:bg-slate-900"
        >
        <ul data-searchable-select-list class="absolute left-0 top-full z-20 mt-1 hidden max-h-52 w-full overflow-y-auto rounded-lg border border-black/10 bg-white p-1 text-sm shadow-lg dark:border-white/10 dark:bg-slate-800"></ul>
    </div>
    @error('conference_id')
        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>

<div class="mb-5">
    <label class="mb-0.5 block text-sm font-medium">{{ __('entity.church_name') }}</label>
    <p class="mb-1.5 text-xs text-slate-400">{{ __('entity.church_name_hint') }}</p>
    <div class="relative" data-searchable-select data-region-church>
        <input type="hidden" name="church_name" data-searchable-select-value value="{{ old('church_name', $selectedChurchName) }}">
        <input
            type="text"
            data-searchable-select-search
            autocomplete="off"
            placeholder="{{ $selectedConferenceId ? __('entity.search_or_create_church_placeholder') : __('entity.waiting_for_daerah') }}"
            value="{{ old('church_name', $selectedChurchName) }}"
            @unless ($selectedConferenceId) disabled @endunless
            class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none disabled:bg-slate-100 disabled:cursor-not-allowed dark:border-white/10 dark:bg-slate-800 dark:disabled:bg-slate-900"
        >
        <ul data-searchable-select-list class="absolute left-0 top-full z-20 mt-1 hidden max-h-52 w-full overflow-y-auto rounded-lg border border-black/10 bg-white p-1 text-sm shadow-lg dark:border-white/10 dark:bg-slate-800"></ul>
    </div>
    @error('church_name')
        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>

<script>
    (function () {
        var conferencesByUnion = @json($conferences->groupBy('union_id')->map(fn ($group) => $group->map(fn ($c) => ['id' => $c->id, 'label' => $c->name])->values()));
        var churchesByConference = @json($churches->groupBy('conference_id')->map(fn ($group) => $group->map(fn ($c) => ['id' => $c->name, 'label' => $c->name])->values()));
        var i18n = @json([
            'searchUni' => __('entity.search_uni_placeholder'),
            'searchDaerah' => __('entity.search_daerah_placeholder'),
            'waitingForUni' => __('accounts.waiting_for_uni'),
            'waitingForDaerah' => __('entity.waiting_for_daerah'),
            'searchOrCreateChurch' => __('entity.search_or_create_church_placeholder'),
        ]);

        var unionWrapper = document.querySelector('[data-region-union]');
        var conferenceWrapper = document.querySelector('[data-region-conference]');
        var churchWrapper = document.querySelector('[data-region-church]');
        var conferenceSearch = conferenceWrapper.querySelector('[data-searchable-select-search]');
        var churchSearch = churchWrapper.querySelector('[data-searchable-select-search]');

        var churchCtl = window.initSearchableSelect(churchWrapper, { allowCreate: true });

        var conferenceCtl = window.initSearchableSelect(conferenceWrapper, {
            onChange: function (conferenceId) {
                if (! conferenceId) {
                    churchSearch.disabled = true;
                    churchCtl.setOptions([], i18n.waitingForDaerah);
                    return;
                }
                churchSearch.disabled = false;
                churchCtl.setOptions(churchesByConference[conferenceId] || [], i18n.searchOrCreateChurch);
            },
        });

        var unionCtl = window.initSearchableSelect(unionWrapper, {
            onChange: function (unionId) {
                if (! unionId) {
                    conferenceSearch.disabled = true;
                    conferenceCtl.setOptions([], i18n.waitingForUni);
                    churchSearch.disabled = true;
                    churchCtl.setOptions([], i18n.waitingForDaerah);
                    return;
                }
                conferenceSearch.disabled = false;
                conferenceCtl.setOptions(conferencesByUnion[unionId] || [], i18n.searchDaerah);
                churchSearch.disabled = true;
                churchCtl.setOptions([], i18n.waitingForDaerah);
            },
        });

        unionCtl.setOptions(@json($unions->map(fn ($u) => ['id' => $u->id, 'label' => $u->name])), i18n.searchUni);

        @if ($selectedUnionId)
            unionCtl.preset({{ $selectedUnionId }}, @json($selectedUnionName));
            conferenceCtl.setOptions(conferencesByUnion[{{ $selectedUnionId }}] || [], i18n.searchDaerah);
            conferenceSearch.disabled = false;
        @endif

        @if ($selectedConferenceId)
            conferenceCtl.preset({{ $selectedConferenceId }}, @json($selectedConferenceName));
            churchCtl.setOptions(churchesByConference[{{ $selectedConferenceId }}] || [], i18n.searchOrCreateChurch);
            churchSearch.disabled = false;
        @endif

        @if ($selectedChurchName)
            churchCtl.preset(@json($selectedChurchName), @json($selectedChurchName));
        @endif
    })();
</script>
