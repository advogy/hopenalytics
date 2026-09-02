{{-- Generic searchable-combobox engine — filters a list of {id, label} options as you type,
     instead of a plain <select>, so pages with hundreds of options (unions, conferences,
     churches, institutions) stay browsable. A page wires up its own [data-searchable-select]
     markup (hidden value input + text search input + result <ul>) and calls
     window.initSearchableSelect(wrapper, opts) to activate it; the returned controller's
     setOptions() lets the page swap in a new list later (e.g. when a parent select changes),
     and opts.onChange lets cascading selects (e.g. Uni → Daerah → Gereja) react to a pick. --}}
<script>
    var searchableSelectI18n = {
        noResults: @json(__('common.no_results_found')),
        // Templates, not pre-substituted — Laravel's __() leaves an untouched placeholder alone
        // when no replacement is given for it, since the count/query here is only known client-
        // side once the user's actually typed something.
        moreResults: @json(__('common.more_results_refine_search')),
        addAsNew: @json(__('common.add_as_new')),
    };

    window.initSearchableSelect = function (wrapper, opts) {
        opts = opts || {};
        var MAX_RESULTS = opts.maxResults || 50;
        var allowCreate = !! opts.allowCreate;
        var onChange = opts.onChange || function () {};

        var searchInput = wrapper.querySelector('[data-searchable-select-search]');
        var valueInput = wrapper.querySelector('[data-searchable-select-value]');
        var list = wrapper.querySelector('[data-searchable-select-list]');
        var currentOptions = [];

        function closeList() {
            list.classList.add('hidden');
            list.innerHTML = '';
            window.removeEventListener('scroll', closeListOnScroll, true);
        }

        // Flips the list above the input instead of below when there isn't enough room
        // underneath (e.g. a row near the bottom of a long table, like Kelola Pengguna's
        // "Belum Ditugaskan" list) — otherwise it renders off-screen and forces a scroll to
        // even see the results. 208px mirrors the list's own max-h-52 Tailwind class; the list
        // is still display:none at this point (class="hidden"), so its real scrollHeight can't
        // be measured yet — this is a fixed upper-bound estimate, not a live measurement.
        //
        // Positioned as position:fixed with coordinates computed here (rather than the plain
        // CSS absolute/top-full it used to rely on) because every table on this app's admin
        // pages wraps in an overflow-x-auto div (see x-admin-list-card) — that alone forces the
        // *vertical* overflow-y to 'auto' too per the CSS spec, which clips an absolutely-
        // positioned dropdown the moment it extends past that div's own box, confirmed
        // happening. position:fixed escapes any ancestor's overflow clipping since it's placed
        // relative to the viewport, not the nearest scroll container.
        function positionList() {
            var estimatedListHeight = 208;
            var inputRect = searchInput.getBoundingClientRect();
            var spaceBelow = window.innerHeight - inputRect.bottom;
            var spaceAbove = inputRect.top;
            var openUpward = spaceBelow < estimatedListHeight && spaceAbove > spaceBelow;

            list.style.position = 'fixed';
            list.style.left = inputRect.left + 'px';
            // The list's own `w-full` Tailwind class resolves against the viewport once
            // position:fixed takes it out of its relative-positioned wrapper's flow (that's
            // what w-full's 100% used to be relative to) — width has to be set here explicitly,
            // to match the search input, or the list balloons to (almost) the full page width.
            list.style.width = inputRect.width + 'px';
            list.style.top = openUpward ? 'auto' : (inputRect.bottom + 4) + 'px';
            list.style.bottom = openUpward ? (window.innerHeight - inputRect.top + 4) + 'px' : 'auto';
        }

        // A fixed-position dropdown doesn't move with the page the way an absolutely-positioned
        // one naturally would — closing on any scroll (the table's own horizontal one included,
        // since it bubbles) avoids it drifting away from the input it belongs to.
        function closeListOnScroll() {
            closeList();
        }

        function setValue(value) {
            valueInput.value = value;
            onChange(value);
        }

        function selectOption(opt) {
            setValue(opt.id);
            searchInput.value = opt.label;
            closeList();
        }

        function filtered() {
            var query = searchInput.value.trim().toLowerCase();
            return query
                ? currentOptions.filter(function (opt) { return opt.label.toLowerCase().indexOf(query) !== -1; })
                : currentOptions;
        }

        function renderList() {
            var query = searchInput.value.trim();
            var matches = filtered();

            positionList();
            // capture:true so this fires for a scroll on ANY ancestor (the table's own
            // overflow-x-auto div included), not just window-level scrolling. Re-registering
            // the same function reference on every render is a harmless no-op if it's already
            // attached — the browser dedupes identical listeners.
            window.addEventListener('scroll', closeListOnScroll, true);
            list.innerHTML = '';

            if (matches.length === 0 && ! allowCreate) {
                var empty = document.createElement('li');
                empty.className = 'px-2 py-1.5 text-slate-400';
                empty.textContent = searchableSelectI18n.noResults;
                list.appendChild(empty);
                list.classList.remove('hidden');
                return;
            }

            matches.slice(0, MAX_RESULTS).forEach(function (opt) {
                var item = document.createElement('li');
                item.textContent = opt.label;
                item.tabIndex = 0;
                item.className = 'cursor-pointer rounded-md px-2 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-700';
                item.addEventListener('mousedown', function (e) {
                    e.preventDefault(); // keep focus so the click isn't lost to the input's blur
                    selectOption(opt);
                });
                list.appendChild(item);
            });

            if (matches.length > MAX_RESULTS) {
                var hint = document.createElement('li');
                hint.className = 'px-2 py-1.5 text-slate-400';
                hint.textContent = searchableSelectI18n.moreResults.replace(':count', String(matches.length - MAX_RESULTS));
                list.appendChild(hint);
            }

            if (allowCreate && query && ! matches.some(function (opt) { return opt.label.toLowerCase() === query.toLowerCase(); })) {
                var create = document.createElement('li');
                create.textContent = searchableSelectI18n.addAsNew.replace(':query', query);
                create.tabIndex = 0;
                create.className = 'cursor-pointer rounded-md px-2 py-1.5 font-medium text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-950';
                create.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    setValue(query);
                    closeList();
                });
                list.appendChild(create);
            }

            list.classList.remove('hidden');
        }

        searchInput.addEventListener('input', function () {
            setValue(allowCreate ? searchInput.value.trim() : '');
            renderList();
        });
        searchInput.addEventListener('focus', renderList);
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeList();
            if (e.key === 'Enter') {
                e.preventDefault();
                var match = filtered()[0];
                if (match) {
                    selectOption(match);
                } else if (allowCreate && searchInput.value.trim()) {
                    setValue(searchInput.value.trim());
                    closeList();
                }
            }
        });
        document.addEventListener('click', function (e) {
            if (! wrapper.contains(e.target)) closeList();
        });

        var controller = {
            setOptions: function (options, placeholder) {
                currentOptions = options || [];
                valueInput.value = '';
                searchInput.value = '';
                if (placeholder !== undefined) searchInput.placeholder = placeholder;
                closeList();
            },
            getValue: function () { return valueInput.value; },
            preset: function (value, label) {
                valueInput.value = value;
                searchInput.value = label;
            },
        };

        wrapper._searchableSelect = controller;
        return controller;
    };

    // Wires up a Uni → Daerah cascading pair of the searchable-selects above (or just a flat
    // Daerah picker when opts.unionSelector is null / matches nothing — e.g. admin_uni, already
    // scoped to one Uni, has no Uni step to render at all). Shared by churches/form and
    // admin/institutions/form, which used to each carry their own near-identical copy of this.
    // Both wrappers' current value (if any) is read directly off their own
    // [data-searchable-select-value] hidden input, so callers must pre-fill that attribute
    // server-side for edit-mode preselection to work — churches/form derives its otherwise-
    // unsubmitted union value from the church's current conference for exactly this reason,
    // since a church only ever really submits conference_id.
    window.initUnionConferenceCascade = function (opts) {
        // Optional: fires on top of the built-in narrowing logic below, whenever EITHER combobox
        // changes — e.g. the analytics filters use this to reload the page with the new
        // selection, instead of just silently updating a hidden form field to submit later.
        var externalOnChange = opts.onChange || function () {};

        var conferenceWrapper = document.querySelector(opts.conferenceSelector);
        var conferenceCtl = window.initSearchableSelect(conferenceWrapper, {
            onChange: function (conferenceId) { externalOnChange(conferenceId); },
        });
        var currentConferenceId = conferenceWrapper.querySelector('[data-searchable-select-value]').getAttribute('value');

        var unionWrapper = opts.unionSelector ? document.querySelector(opts.unionSelector) : null;

        if (! unionWrapper) {
            conferenceCtl.setOptions(opts.conferences, opts.conferencePlaceholder);
            if (currentConferenceId) {
                var match = opts.conferences.find(function (c) { return String(c.id) === currentConferenceId; });
                if (match) conferenceCtl.preset(match.id, match.label);
            }
            return;
        }

        // Must be read BEFORE setOptions() below — setOptions() blanks the input's live .value,
        // and (at least for a form-associated control) that also clears what getAttribute('value')
        // reports back, not just the live property. Reading this first is what makes edit-mode
        // preselection actually stick instead of silently reverting to blank on load.
        var currentUnionId = unionWrapper.querySelector('[data-searchable-select-value]').getAttribute('value');

        var unionCtl = window.initSearchableSelect(unionWrapper, {
            // Picking a Daerah BEFORE a Uni, then picking the Uni afterward, used to silently
            // wipe that already-chosen Daerah back to blank — setOptions() below always resets
            // the conference field's own value, regardless of whether the Daerah picked earlier
            // is still perfectly valid under the newly-picked Uni. Carrying it forward when it
            // is (re-presetting it right after) makes filling the two fields in either order
            // behave the same, instead of punishing whoever fills Daerah first.
            onChange: function (unionId) {
                var previousConferenceId = conferenceCtl.getValue();
                var filtered = unionId ? opts.conferences.filter(function (c) { return String(c.union_id) === String(unionId); }) : [];
                conferenceCtl.setOptions(filtered, unionId ? opts.conferencePlaceholder : opts.conferenceWaitingPlaceholder);
                if (previousConferenceId) {
                    var stillValid = filtered.find(function (c) { return String(c.id) === String(previousConferenceId); });
                    if (stillValid) conferenceCtl.preset(stillValid.id, stillValid.label);
                }
                externalOnChange(unionId);
            },
        });
        unionCtl.setOptions(opts.unions, opts.unionPlaceholder);

        if (currentUnionId) {
            var currentUnion = opts.unions.find(function (u) { return String(u.id) === currentUnionId; });
            if (currentUnion) unionCtl.preset(currentUnion.id, currentUnion.label);
            conferenceCtl.setOptions(opts.conferences.filter(function (c) { return String(c.union_id) === currentUnionId; }), opts.conferencePlaceholder);
        }
        if (currentConferenceId) {
            var currentConference = opts.conferences.find(function (c) { return String(c.id) === currentConferenceId; });
            if (currentConference) conferenceCtl.preset(currentConference.id, currentConference.label);
        }
    };
</script>
