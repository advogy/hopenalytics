{{--
    Generic "hide rows with no data" checkbox behavior — one delegated listener instead of a
    copy per scope. Pair any checkbox with data-hide-empty-toggle="<row selector>" (e.g.
    "[data-organization-row]") and it'll toggle .hidden on every matching element that also
    carries data-empty-row, same idea as partials/searchable-select.blade.php's single shared
    script backing many independent widgets.
--}}
@once
    <script>
        document.addEventListener('change', function (e) {
            var checkbox = e.target.closest('[data-hide-empty-toggle]');
            if (! checkbox) return;

            document.querySelectorAll(checkbox.dataset.hideEmptyToggle).forEach(function (row) {
                row.classList.toggle('hidden', checkbox.checked && row.hasAttribute('data-empty-row'));
            });
        });
    </script>
@endonce
