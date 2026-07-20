{{-- Generic searchable-combobox engine — filters a list of {id, label} options as you type,
     instead of a plain <select>, so pages with hundreds of options (unions, conferences,
     churches, institutions) stay browsable. A page wires up its own [data-searchable-select]
     markup (hidden value input + text search input + result <ul>) and calls
     window.initSearchableSelect(wrapper, opts) to activate it; the returned controller's
     setOptions() lets the page swap in a new list later (e.g. when a parent select changes),
     and opts.onChange lets cascading selects (e.g. Uni → Daerah → Gereja) react to a pick. --}}
<script>
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

            list.innerHTML = '';

            if (matches.length === 0 && ! allowCreate) {
                var empty = document.createElement('li');
                empty.className = 'px-2 py-1.5 text-slate-400';
                empty.textContent = 'Tidak ditemukan.';
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
                hint.textContent = '+' + (matches.length - MAX_RESULTS) + ' lainnya — perhalus pencarian…';
                list.appendChild(hint);
            }

            if (allowCreate && query && ! matches.some(function (opt) { return opt.label.toLowerCase() === query.toLowerCase(); })) {
                var create = document.createElement('li');
                create.textContent = '+ Tambah "' + query + '" sebagai baru';
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
</script>
