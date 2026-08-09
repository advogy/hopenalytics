{{-- Advisory "did you mean an existing entity" / "this account is already tracked" widgets —
     debounced fetch against the *.similar routes (see NameSimilarity + ChurchSocialController::
     similar()), rendering a dismissible-by-editing warning under the field. Never blocks
     submission — purely a heads-up so an admin can catch an accidental duplicate before saving,
     same "warn, don't block" idea as the rest of the app's advisory UI. Mirrors
     partials/searchable-select.blade.php's pattern: reusable window.init*() functions defined
     once here, wired up per-page with a small inline <script> passing that page's own route/ids. --}}
<script>
    (function () {
        function escapeHtml(str) {
            var div = document.createElement('div');
            div.textContent = str == null ? '' : String(str);
            return div.innerHTML;
        }

        function renderList(resultsEl, heading, matches, itemHtml) {
            if (! matches.length) {
                resultsEl.classList.add('hidden');
                resultsEl.innerHTML = '';
                return;
            }

            resultsEl.innerHTML =
                '<p class="mb-1.5 font-medium text-amber-800 dark:text-amber-300">' + heading + '</p>' +
                '<ul class="list-inside list-disc space-y-0.5">' + matches.map(itemHtml).join('') + '</ul>';
            resultsEl.classList.remove('hidden');
        }

        function fetchJson(url, params) {
            return fetch(url + '?' + params.toString(), { headers: { Accept: 'application/json' } })
                .then(function (res) { return res.ok ? res.json() : []; })
                .catch(function () { return []; });
        }

        // inputEl: the name field's own input element. resultsEl: an empty results div placed
        // right after it (hidden by default — see the forms that call this).
        window.initSimilarNameCheck = function (inputEl, resultsEl, opts) {
            opts = opts || {};
            var timer = null;

            function check() {
                clearTimeout(timer);
                var name = inputEl.value.trim();

                if (! name) {
                    renderList(resultsEl, '', [], function () { return ''; });
                    return;
                }

                timer = setTimeout(function () {
                    var params = new URLSearchParams({ name: name });
                    if (opts.excludeId) params.set('exclude_id', opts.excludeId);

                    fetchJson(opts.url, params).then(function (matches) {
                        renderList(resultsEl, opts.heading || 'Mungkin sudah ada yang mirip:', matches, function (m) {
                            var context = m.context ? ' <span class="text-amber-600/70 dark:text-amber-500/70">(' + escapeHtml(m.context) + ')</span>' : '';

                            return '<li><a href="' + escapeHtml(m.url) + '" target="_blank" class="text-amber-700 underline hover:text-amber-900 dark:text-amber-400">' + escapeHtml(m.name) + '</a>' + context + '</li>';
                        });
                    });
                }, 350);
            }

            inputEl.addEventListener('input', check);
        };

        // platformEl: the platform select element. handleEl: the handle field's input element.
        // profileUrlEl: the profile_url field's input element (optional — pass null if the form
        // has no such field). resultsEl: an empty results div placed after the handle field.
        window.initSimilarSocialCheck = function (platformEl, handleEl, profileUrlEl, resultsEl, opts) {
            opts = opts || {};
            var timer = null;

            function check() {
                clearTimeout(timer);
                var handle = handleEl.value.trim();
                var profileUrl = profileUrlEl ? profileUrlEl.value.trim() : '';

                if (! handle && ! profileUrl) {
                    renderList(resultsEl, '', [], function () { return ''; });
                    return;
                }

                timer = setTimeout(function () {
                    var params = new URLSearchParams({ platform: platformEl.value, handle: handle, profile_url: profileUrl });
                    if (opts.excludeId) params.set('exclude_id', opts.excludeId);

                    fetchJson(opts.url, params).then(function (matches) {
                        renderList(resultsEl, opts.heading || 'Akun ini sepertinya sudah terdaftar:', matches, function (m) {
                            var owner = m.owner ? ' — <span class="text-amber-600/70 dark:text-amber-500/70">' + escapeHtml(m.owner) + '</span>' : '';

                            return '<li><a href="' + escapeHtml(m.url) + '" target="_blank" class="text-amber-700 underline hover:text-amber-900 dark:text-amber-400">' + escapeHtml(m.handle) + '</a>' + owner + '</li>';
                        });
                    });
                }, 350);
            }

            handleEl.addEventListener('input', check);
            if (profileUrlEl) profileUrlEl.addEventListener('input', check);
            platformEl.addEventListener('change', check);
        };
    })();
</script>
