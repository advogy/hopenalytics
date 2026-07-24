{{--
    Shared click-to-expand/collapse behavior for the Data Per * tables' Uni/Daerah groups (see
    components/analytics-group-row.blade.php). A row's visibility depends on ALL of its listed
    ancestor group ids being expanded — not just its immediate parent — since a deeply nested
    entity row and a mid-tier Daerah header are both plain sibling <tr>s in the same <tbody>, with
    no real DOM nesting for hidden-ness to cascade through on its own. Everything starts collapsed
    (the `expanded` map starts empty, so any row with ancestors starts hidden).
--}}
<script>
    (function () {
        var expanded = {};

        function isVisible(row) {
            var ancestors = (row.dataset.groupAncestors || '').split(' ').filter(Boolean);
            return ancestors.every(function (id) { return expanded[id]; });
        }

        function refresh() {
            document.querySelectorAll('[data-group-ancestors]').forEach(function (row) {
                row.hidden = ! isVisible(row);
            });
            document.querySelectorAll('[data-group-toggle]').forEach(function (header) {
                var chevron = header.querySelector('[data-group-chevron]');
                if (chevron) chevron.classList.toggle('-rotate-90', ! expanded[header.dataset.groupToggle]);
            });
        }

        document.addEventListener('click', function (e) {
            var header = e.target.closest('[data-group-toggle]');
            if (! header) return;

            var id = header.dataset.groupToggle;
            expanded[id] = ! expanded[id];
            refresh();
        });

        refresh();
    })();
</script>
