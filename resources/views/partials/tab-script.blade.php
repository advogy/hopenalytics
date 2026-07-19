{{-- Wires up [data-tab-button]/[data-tab-panel] pairs. If a hidden field carries
     [data-tab-hidden-field], its value is kept in sync so a filter-form GET submit
     preserves the active tab. --}}
<script>
    (function () {
        var buttons = document.querySelectorAll('[data-tab-button]');
        var panels = document.querySelectorAll('[data-tab-panel]');
        var hiddenField = document.querySelector('[data-tab-hidden-field]');

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
            if (hiddenField) hiddenField.value = tab;
        }

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () { activate(btn.dataset.tabButton); });
        });

        activate(@json($activeTab));
    })();
</script>
