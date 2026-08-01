{{--
    Uni + Daerah cascading filter fields for one Data Per * tab's GET filter form — reloads the
    page on selection (see the onChange below), which is what makes the whole tab (KPI, growth
    chart, and table) reflect the chosen region: ChurchDashboardController::analytics() applies
    the same union_id/conference_id filter to every entity collection right after querying it.

    Expected: $prefix (unique per tab, e.g. "church"/"person"/"institution" — keeps each tab's
    data-* selectors from colliding since all 3 filter forms live in the DOM at once），
    $formId, $isNasionalView, $isUniView, $unionOptions, $conferenceOptions, $selectedUnionId,
    $selectedConferenceId. $unionFieldName/$conferenceFieldName (default 'union_id'/'conference_id')
    override the submitted field names — needed wherever more than one of these filters lives on
    the same page with each meant to filter independently (e.g. Kelola Akun's per-tab forms),
    since otherwise every instance would submit under the same two names and collide.
    $wrapperClass (default 'w-full sm:w-auto') / $inputWidthClass (default 'w-full sm:w-40') let a
    caller opt into e.g. an equal-width flex item that actually fills it, instead of the default
    hug-content sizing (see Kelola Akun, which wants every filter pill on a row to stretch and
    share it evenly — both need overriding together there, since the default pairing relies on
    the wrapper hugging a fixed-width input).

    onChange only fires with a real (non-empty) id — initSearchableSelect's onChange also fires
    on every keystroke with an empty value while typing, so submitting unconditionally would
    reload the page after each character.
--}}
@php
    $unionFieldName ??= 'union_id';
    $conferenceFieldName ??= 'conference_id';
    $wrapperClass ??= 'w-full sm:w-auto';
    $inputWidthClass ??= 'w-full sm:w-40';
    $activeFieldClass = 'border-blue-600 bg-blue-50 text-blue-900 dark:border-blue-500 dark:bg-blue-950/40 dark:text-blue-200';
    $inactiveFieldClass = 'border-black/10 bg-slate-50 text-slate-700 hover:bg-slate-100 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700';
@endphp
@if ($isNasionalView)
    <div class="relative {{ $wrapperClass }}" data-searchable-select data-{{ $prefix }}-union>
        <x-icon name="globe-alt" class="pointer-events-none absolute top-1/2 left-3.5 z-10 h-4 w-4 -translate-y-1/2 text-slate-400" />
        <input type="hidden" name="{{ $unionFieldName }}" data-searchable-select-value value="{{ $selectedUnionId }}">
        <input
            type="text"
            data-searchable-select-search
            autocomplete="off"
            placeholder="{{ __('entity.search_uni_placeholder') }}"
            class="{{ $inputWidthClass }} rounded-full border py-2.5 pr-9 pl-9 text-sm font-medium shadow-sm transition {{ $selectedUnionId ? $activeFieldClass : $inactiveFieldClass }}"
        >
        <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
        <ul data-searchable-select-list class="absolute left-0 top-full z-20 mt-1 hidden max-h-52 w-56 overflow-y-auto rounded-lg border border-black/10 bg-white p-1 text-sm shadow-lg dark:border-white/10 dark:bg-slate-800"></ul>
    </div>
@endif

@if ($isNasionalView || $isUniView)
    <div class="relative {{ $wrapperClass }}" data-searchable-select data-{{ $prefix }}-conference>
        <x-icon name="globe-alt" class="pointer-events-none absolute top-1/2 left-3.5 z-10 h-4 w-4 -translate-y-1/2 text-slate-400" />
        <input type="hidden" name="{{ $conferenceFieldName }}" data-searchable-select-value value="{{ $selectedConferenceId }}">
        <input
            type="text"
            data-searchable-select-search
            autocomplete="off"
            placeholder="{{ __('entity.search_daerah_placeholder') }}"
            class="{{ $inputWidthClass }} rounded-full border py-2.5 pr-9 pl-9 text-sm font-medium shadow-sm transition {{ $selectedConferenceId ? $activeFieldClass : $inactiveFieldClass }}"
        >
        <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
        <ul data-searchable-select-list class="absolute left-0 top-full z-20 mt-1 hidden max-h-52 w-56 overflow-y-auto rounded-lg border border-black/10 bg-white p-1 text-sm shadow-lg dark:border-white/10 dark:bg-slate-800"></ul>
    </div>

    <script>
        window.initUnionConferenceCascade({
            unionSelector: @json($isNasionalView ? '[data-'.$prefix.'-union]' : null),
            conferenceSelector: '[data-{{ $prefix }}-conference]',
            unions: @json($unionOptions->map(fn ($u) => ['id' => $u->id, 'label' => $u->name])->values()),
            conferences: @json($conferenceOptions->map(fn ($c) => ['id' => $c->id, 'union_id' => $c->union_id, 'label' => $c->name])->values()),
            unionPlaceholder: @json(__('entity.search_uni_placeholder')),
            conferencePlaceholder: @json(__('entity.search_daerah_placeholder')),
            conferenceWaitingPlaceholder: @json(__('accounts.waiting_for_uni')),
            onChange: function (value) { if (value) document.getElementById(@json($formId)).submit(); },
        });
    </script>
@endif
