{{-- Shared bulk-select checkboxes/select-all/bulk-action-buttons wiring — originally built for
     Monitoring Antrean's Job Gagal/Batch Aktif/Batch Selesai (one shared external <form> + a
     "select all" checkbox + N per-row checkboxes referencing it via form="..." + one or more
     submit buttons overriding where the checked ids go via their own formaction), extracted here
     once Kelola Pengguna's own "Semua User" tab needed the identical pattern a second time.
     Defines window.initBulkSelect(sectionId, formId, checkboxSelector, selectAllSelector,
     buttonSelector) — call it once per section that has this pattern; nothing here fires on its
     own, so `@include` this once per page and call the function per section afterward.

     Attached to each section's own stable wrapper element via delegation rather than to the
     checkboxes/buttons themselves, so a section that gets its rows swapped out from under it
     (e.g. by a poll-and-refresh script elsewhere on the page) doesn't silently lose a listener
     bound directly to an about-to-be-replaced element.

     Every section's bulk buttons share one hidden form and so share one data-confirm attribute
     too — since confirm-dialog.blade.php reads that off the FORM, not the button that was
     clicked, each button's own resolved confirm text (with the current checked-count substituted
     in) is written onto the form at click time, just before the submit event fires and
     confirm-dialog.blade.php reads it. A button with no data-confirm-template attribute (an
     action that doesn't need a confirmation) is left alone.

     Optional 6th argument { persist: true } keeps the selection across pagination (per the
     user's explicit call on Monitoring Antrean: tick 50 on page 1, 50 more on page 2, then act on
     all 100 at once). Pagination is a full page load, so the picked ids live in sessionStorage
     (keyed per path + section) rather than only in the checkboxes; at submit time every picked
     id NOT rendered on the current page is appended to the shared form as a hidden ids[] input,
     and the stored selection is dropped once the form actually submits (i.e. past the confirm
     dialog). Optional [data-bulk-selection-info] / [data-bulk-selection-count] /
     [data-bulk-clear-selection] elements inside the section show "N dipilih" + a reset link. --}}
<script>
    window.initBulkSelect = function (sectionId, formId, checkboxSelector, selectAllSelector, buttonSelector, options) {
        var section = document.getElementById(sectionId);
        if (! section) return;

        var persist = !! (options && options.persist);
        var storageKey = 'bulk-select:' + window.location.pathname + ':' + sectionId;
        var selected = new Set();

        // Storage can be unavailable (private window, blocked site data) — selection then just
        // falls back to the current page only, same as without persist.
        function loadSelection() {
            try {
                var raw = window.sessionStorage.getItem(storageKey);
                if (raw) JSON.parse(raw).forEach(function (id) { selected.add(String(id)); });
            } catch (err) {}
        }

        function saveSelection() {
            try {
                if (selected.size === 0) {
                    window.sessionStorage.removeItem(storageKey);
                } else {
                    window.sessionStorage.setItem(storageKey, JSON.stringify(Array.from(selected)));
                }
            } catch (err) {}
        }

        function pageCheckboxes() {
            return section.querySelectorAll(checkboxSelector);
        }

        function checkedCount() {
            return persist ? selected.size : section.querySelectorAll(checkboxSelector + ':checked').length;
        }

        function syncSelectAll() {
            var selectAll = section.querySelector(selectAllSelector);
            if (! selectAll) return;

            var boxes = pageCheckboxes();
            var checked = section.querySelectorAll(checkboxSelector + ':checked').length;
            selectAll.checked = boxes.length > 0 && checked === boxes.length;
            selectAll.indeterminate = checked > 0 && checked < boxes.length;
        }

        function updateBulkButtons() {
            var count = checkedCount();
            section.querySelectorAll(buttonSelector).forEach(function (button) {
                button.disabled = count === 0;
            });

            var info = section.querySelector('[data-bulk-selection-info]');
            if (info) {
                info.classList.toggle('hidden', count === 0);
                var countEl = info.querySelector('[data-bulk-selection-count]');
                if (countEl) countEl.textContent = (countEl.getAttribute('data-template') || ':count').replace(':count', count);
            }

            syncSelectAll();
        }

        function recordCheckbox(checkbox) {
            if (! persist) return;
            if (checkbox.checked) {
                selected.add(String(checkbox.value));
            } else {
                selected.delete(String(checkbox.value));
            }
        }

        if (persist) {
            loadSelection();
            pageCheckboxes().forEach(function (checkbox) {
                checkbox.checked = selected.has(String(checkbox.value));
            });
        }
        updateBulkButtons();

        section.addEventListener('change', function (e) {
            if (e.target.matches(selectAllSelector)) {
                pageCheckboxes().forEach(function (checkbox) {
                    checkbox.checked = e.target.checked;
                    recordCheckbox(checkbox);
                });
            } else if (e.target.matches(checkboxSelector)) {
                recordCheckbox(e.target);
            } else {
                return;
            }

            if (persist) saveSelection();
            updateBulkButtons();
        });

        section.addEventListener('click', function (e) {
            if (persist && e.target.closest('[data-bulk-clear-selection]')) {
                selected.clear();
                saveSelection();
                pageCheckboxes().forEach(function (checkbox) { checkbox.checked = false; });
                updateBulkButtons();
                return;
            }

            var button = e.target.closest(buttonSelector);
            if (! button) return;

            var form = document.getElementById(formId);

            if (persist) {
                // Rebuilt on every click, so a cancelled confirm followed by a changed selection
                // never submits stale ids.
                form.querySelectorAll('input[data-bulk-offpage-id]').forEach(function (input) { input.remove(); });

                var onPage = new Set();
                pageCheckboxes().forEach(function (checkbox) { onPage.add(String(checkbox.value)); });

                selected.forEach(function (id) {
                    if (onPage.has(id)) return;
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'ids[]';
                    input.value = id;
                    input.setAttribute('data-bulk-offpage-id', '');
                    form.appendChild(input);
                });
            }

            var template = button.getAttribute('data-confirm-template');
            if (! template) return;

            form.setAttribute('data-confirm', template.replace(':count', checkedCount()));
        });

        if (persist) {
            // Only once the submit actually goes through — a submit still waiting on
            // confirm-dialog.blade.php (data-confirm set, not yet data-confirmed) keeps the
            // selection so cancelling the dialog loses nothing.
            document.addEventListener('submit', function (e) {
                var form = e.target;
                if (form.id !== formId) return;
                if (form.hasAttribute('data-confirm') && ! form.dataset.confirmed) return;

                selected.clear();
                saveSelection();
            });
        }
    };
</script>
