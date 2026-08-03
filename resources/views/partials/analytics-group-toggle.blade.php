{{--
    Shared click-to-expand/collapse behavior for the Data Per * tables' Uni/Daerah groups (see
    components/analytics-group-row.blade.php). A row's visibility depends on ALL of its listed
    ancestor group ids being expanded — not just its immediate parent — since a deeply nested
    entity row and a mid-tier Daerah header are both plain sibling <tr>s in the same <tbody>, with
    no real DOM nesting for hidden-ness to cascade through on its own. Everything starts collapsed
    (the `expanded` map starts empty, so any row with ancestors starts hidden) — UNLESS
    $expandGroupsByDefault (optional, default false) is on, which expands every group right away.
    Pass that whenever the page just reloaded with a server-side filter/sort/search actually
    applied (a plain GET, not the client-side live-filter below) — otherwise the visible effect of
    that filter is invisible behind still-collapsed groups, indistinguishable from "did nothing".

    Each table's search box (data-group-search-scope="church"/"person"/"institution", next to its
    title) filters that table's entity rows by name and auto-expands every ancestor group of a
    match, so a hit inside a currently-collapsed group is actually visible instead of silently
    matching nothing. It only ever touches entity rows (skips group-header rows themselves, which
    also carry data-group-ancestors) via inline style.display — a separate mechanism from the
    `hidden` attribute above or the "hide empty" checkbox's .hidden class, so all three can each
    hide a row independently without one undoing another.

    Each table also gets one data-group-toggle-all="<scope>" button (see components/group-toggle-
    all-button.blade.php) that expands or collapses every group whose toggle id starts with
    "<scope>-" in one click, instead of requiring one click per Uni/Daerah header. Its own label
    flips between "Buka Semua"/"Tutup Semua" to reflect whether that table is currently fully
    expanded — computed fresh on every refresh() rather than tracked as separate state, so it
    can never drift out of sync with the headers it represents.
--}}
@php $expandGroupsByDefault ??= false; @endphp
<script>
    (function () {
        var expanded = {};

        if (@json($expandGroupsByDefault)) {
            document.querySelectorAll('[data-group-toggle]').forEach(function (header) {
                expanded[header.dataset.groupToggle] = true;
            });
        }

        function isVisible(row) {
            var ancestors = (row.dataset.groupAncestors || '').split(' ').filter(Boolean);
            return ancestors.every(function (id) { return expanded[id]; });
        }

        function headersInScope(scope) {
            return Array.prototype.filter.call(document.querySelectorAll('[data-group-toggle]'), function (header) {
                return header.dataset.groupToggle.indexOf(scope + '-') === 0;
            });
        }

        function refresh() {
            document.querySelectorAll('[data-group-ancestors]').forEach(function (row) {
                row.hidden = ! isVisible(row);
            });
            document.querySelectorAll('[data-group-toggle]').forEach(function (header) {
                var chevron = header.querySelector('[data-group-chevron]');
                if (chevron) chevron.classList.toggle('-rotate-90', ! expanded[header.dataset.groupToggle]);
            });
            document.querySelectorAll('[data-group-toggle-all]').forEach(function (btn) {
                var headers = headersInScope(btn.dataset.groupToggleAll);
                var allExpanded = headers.length > 0 && headers.every(function (h) { return expanded[h.dataset.groupToggle]; });
                btn.textContent = allExpanded ? btn.dataset.labelCollapse : btn.dataset.labelExpand;
                btn.disabled = headers.length === 0;
                btn.classList.toggle('opacity-50', headers.length === 0);
            });
        }

        document.addEventListener('click', function (e) {
            var header = e.target.closest('[data-group-toggle]');
            if (header) {
                var id = header.dataset.groupToggle;
                expanded[id] = ! expanded[id];
                refresh();
                return;
            }

            var toggleAllBtn = e.target.closest('[data-group-toggle-all]');
            if (! toggleAllBtn) return;

            var headers = headersInScope(toggleAllBtn.dataset.groupToggleAll);
            var allExpanded = headers.length > 0 && headers.every(function (h) { return expanded[h.dataset.groupToggle]; });
            headers.forEach(function (h) { expanded[h.dataset.groupToggle] = ! allExpanded; });
            refresh();
        });

        document.addEventListener('input', function (e) {
            var input = e.target.closest('[data-group-search-scope]');
            if (! input) return;

            var scope = input.dataset.groupSearchScope;
            var query = input.value.trim().toLowerCase();

            document.querySelectorAll('[data-group-ancestors]').forEach(function (row) {
                if (row.hasAttribute('data-group-toggle')) return; // never hide/filter group headers themselves

                var ancestors = row.dataset.groupAncestors.split(' ').filter(Boolean);
                if (! ancestors.length || ancestors[0].indexOf(scope + '-') !== 0) return; // different table

                if (! query) {
                    row.style.display = '';
                    return;
                }

                var matches = row.textContent.toLowerCase().indexOf(query) !== -1;
                row.style.display = matches ? '' : 'none';

                if (matches) {
                    ancestors.forEach(function (id) { expanded[id] = true; });
                }
            });

            refresh();
        });

        refresh();
    })();
</script>
