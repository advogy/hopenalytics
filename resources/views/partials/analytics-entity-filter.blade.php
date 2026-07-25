{{--
    Searchable single-entity picker for a Data Per * tab's filter form (replaces a plain <select>
    that would otherwise list hundreds of churches/personal/institutions unfilterably). Submits
    the form on an actual pick — see analytics-region-filter.blade.php's onChange note for why
    it's gated on a truthy value rather than firing on every keystroke.

    Expected: $prefix, $formId, $fieldName ('church_id'/'person_id'/'institution_id'), $icon,
    $placeholder, $selectedId, $options (a plain array of ['id' => ..., 'label' => ...]).
--}}
<div class="relative" data-searchable-select data-{{ $prefix }}-entity>
    <x-icon name="{{ $icon }}" class="pointer-events-none absolute top-1/2 left-3.5 z-10 h-4 w-4 -translate-y-1/2 text-slate-400" />
    <input type="hidden" name="{{ $fieldName }}" data-searchable-select-value value="{{ $selectedId }}">
    <input
        type="text"
        data-searchable-select-search
        autocomplete="off"
        placeholder="{{ $placeholder }}"
        class="w-56 rounded-full border border-black/10 bg-slate-50 py-2.5 pr-9 pl-9 text-sm font-medium shadow-sm transition hover:bg-slate-100 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
    >
    <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
    <ul data-searchable-select-list class="absolute left-0 top-full z-20 mt-1 hidden max-h-52 w-64 overflow-y-auto rounded-lg border border-black/10 bg-white p-1 text-sm shadow-lg dark:border-white/10 dark:bg-slate-800"></ul>
</div>

<script>
    (function () {
        var ctl = window.initSearchableSelect(document.querySelector('[data-{{ $prefix }}-entity]'), {
            onChange: function (value) { if (value) document.getElementById(@json($formId)).submit(); },
        });
        var options = @json($options);
        ctl.setOptions(options, @json($placeholder));

        var currentId = @json((string) ($selectedId ?? ''));
        if (currentId) {
            var match = options.find(function (o) { return String(o.id) === currentId; });
            if (match) ctl.preset(match.id, match.label);
        }
    })();
</script>
