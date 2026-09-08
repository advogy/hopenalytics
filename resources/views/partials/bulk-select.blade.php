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
     action that doesn't need a confirmation) is left alone. --}}
<script>
    window.initBulkSelect = function (sectionId, formId, checkboxSelector, selectAllSelector, buttonSelector) {
        var section = document.getElementById(sectionId);
        if (! section) return;

        function checkedCount() {
            return section.querySelectorAll(checkboxSelector + ':checked').length;
        }

        function updateBulkButtons() {
            var count = checkedCount();
            section.querySelectorAll(buttonSelector).forEach(function (button) {
                button.disabled = count === 0;
            });
        }

        section.addEventListener('change', function (e) {
            if (e.target.matches(selectAllSelector)) {
                section.querySelectorAll(checkboxSelector).forEach(function (checkbox) {
                    checkbox.checked = e.target.checked;
                });
                updateBulkButtons();
            } else if (e.target.matches(checkboxSelector)) {
                updateBulkButtons();
            }
        });

        section.addEventListener('click', function (e) {
            var button = e.target.closest(buttonSelector);
            if (! button) return;

            var template = button.getAttribute('data-confirm-template');
            if (! template) return;

            var form = document.getElementById(formId);
            form.setAttribute('data-confirm', template.replace(':count', checkedCount()));
        });
    };
</script>
