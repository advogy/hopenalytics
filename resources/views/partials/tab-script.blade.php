{{-- Wires up [data-tab-button]/[data-tab-panel] pairs. Every hidden field carrying
     [data-tab-hidden-field] is kept in sync (a page can have more than one — e.g. a separate
     filter <form> per tab — so a filter-form GET submit from *any* of them preserves the
     active tab instead of only whichever hidden field happened to be first in the DOM).
     [data-tab-placeholder] elements (shared across tabs, e.g. one search box for all 3 tabs)
     get their placeholder swapped from a per-tab JSON map, e.g.
     data-tab-placeholder='{"gereja":"Cari gereja…","personal":"Cari personal…"}'. --}}
<script>
    (function () {
        var buttons = document.querySelectorAll('[data-tab-button]');
        var panels = document.querySelectorAll('[data-tab-panel]');
        var hiddenFields = document.querySelectorAll('[data-tab-hidden-field]');
        var placeholderFields = document.querySelectorAll('[data-tab-placeholder]');

        function activate(tab) {
            buttons.forEach(function (btn) {
                var isActive = btn.dataset.tabButton === tab;
                btn.classList.toggle('border-blue-600', isActive);
                btn.classList.toggle('text-blue-600', isActive);
                btn.classList.toggle('border-transparent', !isActive);
                btn.classList.toggle('text-slate-500', !isActive);
                btn.classList.toggle('dark:text-slate-400', !isActive);
            });
            panels.forEach(function (panel) {
                panel.classList.toggle('hidden', panel.dataset.tabPanel !== tab);
            });
            hiddenFields.forEach(function (field) { field.value = tab; });
            placeholderFields.forEach(function (field) {
                var map = JSON.parse(field.dataset.tabPlaceholder);
                if (map[tab]) field.placeholder = map[tab];
            });
        }

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () { activate(btn.dataset.tabButton); });
        });

        activate(@json($activeTab));
    })();
</script>
