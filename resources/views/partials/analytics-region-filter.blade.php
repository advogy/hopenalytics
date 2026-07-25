{{--
    Uni + Daerah cascading filter fields for one Data Per * tab's GET filter form — reloads the
    page on selection (see the onChange below), which is what makes the whole tab (KPI, growth
    chart, and table) reflect the chosen region: ChurchDashboardController::analytics() applies
    the same union_id/conference_id filter to every entity collection right after querying it.

    Expected: $prefix (unique per tab, e.g. "church"/"person"/"institution" — keeps each tab's
    data-* selectors from colliding since all 3 filter forms live in the DOM at once），
    $formId, $isNasionalView, $isUniView, $unionOptions, $conferenceOptions, $selectedUnionId,
    $selectedConferenceId.

    onChange only fires with a real (non-empty) id — initSearchableSelect's onChange also fires
    on every keystroke with an empty value while typing, so submitting unconditionally would
    reload the page after each character.
--}}
@if ($isNasionalView)
    <div class="relative" data-searchable-select data-{{ $prefix }}-union>
        <x-icon name="globe-alt" class="pointer-events-none absolute top-1/2 left-3.5 z-10 h-4 w-4 -translate-y-1/2 text-slate-400" />
        <input type="hidden" name="union_id" data-searchable-select-value value="{{ $selectedUnionId }}">
        <input
            type="text"
            data-searchable-select-search
            autocomplete="off"
            placeholder="{{ __('entity.search_uni_placeholder') }}"
            class="w-40 rounded-full border border-black/10 bg-slate-50 py-2.5 pr-9 pl-9 text-sm font-medium shadow-sm transition hover:bg-slate-100 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
        >
        <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
        <ul data-searchable-select-list class="absolute left-0 top-full z-20 mt-1 hidden max-h-52 w-56 overflow-y-auto rounded-lg border border-black/10 bg-white p-1 text-sm shadow-lg dark:border-white/10 dark:bg-slate-800"></ul>
    </div>
@endif

@if ($isNasionalView || $isUniView)
    <div class="relative" data-searchable-select data-{{ $prefix }}-conference>
        <x-icon name="globe-alt" class="pointer-events-none absolute top-1/2 left-3.5 z-10 h-4 w-4 -translate-y-1/2 text-slate-400" />
        <input type="hidden" name="conference_id" data-searchable-select-value value="{{ $selectedConferenceId }}">
        <input
            type="text"
            data-searchable-select-search
            autocomplete="off"
            placeholder="{{ __('entity.search_daerah_placeholder') }}"
            class="w-40 rounded-full border border-black/10 bg-slate-50 py-2.5 pr-9 pl-9 text-sm font-medium shadow-sm transition hover:bg-slate-100 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
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
