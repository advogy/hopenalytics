{{--
    Shared add/edit modal — one instance per page, filled by JS per click on any
    [data-entity-modal-trigger] element (an "Edit" row icon, a "Tambah ..." button, ...) rather
    than one dialog per button/row. Originally built for Kelola Akun (6 entity types) and reused
    as-is by Kelola Pengguna and any other page that wants "edit this in a modal instead of a
    separate page" — nothing here is specific to any one page's entities, routes, or forms; it
    just fetches whatever URL the trigger names as a fragment (?modal=1 appended) and drops it
    into the modal body, then intercepts that fragment's own <form data-modal-ajax-form> submit
    the same way regardless of what it actually updates. See
    Concerns/HandlesModalForms::validateOrRespondModal()/respondModalOrRedirect() for the
    server-side half of this same contract, and components/entity-crud-form.blade.php's own
    :modal prop (or, for a page not using that shared component, e.g. admin/users/edit.blade.php,
    the same data-modal-ajax-form/data-modal-title/hidden-modal-field markup applied by hand) for
    how a form opts into it.

    Starts with no title text — the script below sets/clears it on every open (and every
    re-render) from the fetched form's own data-modal-title attribute, since which entity's form
    is loaded isn't known until the fetch resolves.
--}}
<x-modal id="entity-edit-modal" body-id="entity-edit-modal-body" max-width="36rem" />

<script>
    (function () {
        var dialog = document.getElementById('entity-edit-modal');
        var body = document.getElementById('entity-edit-modal-body');
        var titleEl = dialog.querySelector('[data-app-modal-title]');

        // showModal() alone (native <dialog> behaviour) blocks interacting with the page
        // behind it, but doesn't stop it from scrolling underneath — a scroll/wheel/keyboard
        // scroll while the modal is open still moves the list in the background. Locking body
        // scroll while open, restored on close, is the standard fix.
        function openDialog() {
            if (dialog.open) return;
            document.body.style.overflow = 'hidden';
            dialog.showModal();
        }
        // Bumped on every new open so a slow-to-resolve fetch from a previously-clicked row
        // can't clobber the modal after the visitor has already clicked a different row (or
        // closed it) in the meantime.
        var requestToken = 0;

        // Scripts inserted via innerHTML never execute on their own (a browser security
        // rule, not a bug) — an entity form's own inline script tag (region-cascade wiring,
        // similar-name-check, group-links-fields, ...) calls an already-page-global function
        // (window.initUnionConferenceCascade / window.initSearchableSelect, both defined once in
        // partials/searchable-select.blade.php via layouts.app, already loaded on the host
        // page), so re-creating and re-inserting each script node is enough to make them run —
        // nothing about them needs to change for modal use.
        function runScripts(container) {
            container.querySelectorAll('script').forEach(function (oldScript) {
                var newScript = document.createElement('script');
                Array.from(oldScript.attributes).forEach(function (attr) {
                    newScript.setAttribute(attr.name, attr.value);
                });
                newScript.textContent = oldScript.textContent;
                oldScript.replaceWith(newScript);
            });
        }

        function fillModal(html, token) {
            if (token !== requestToken) return;
            body.innerHTML = html;
            runScripts(body);

            // The title lives in the modal's own head bar, not in the fetched fragment's
            // body — the fragment just carries it as a data-modal-title attribute on its
            // <form> for this to pick up and place. Left blank if that form isn't found (a
            // 422 error state still carries its own form/title same as the initial load, so
            // this only ever misses on something unexpected — better a blank head than a
            // stale one).
            var form = body.querySelector('[data-modal-ajax-form]');
            titleEl.textContent = form ? form.getAttribute('data-modal-title') : '';

            openDialog();
        }

        // Shared by every "Edit"/"Tambah ..." trigger on the host page — all of them just fetch
        // a form URL as a fragment and drop it into the same modal; what a successful submit
        // actually updates, and where it redirects afterward, is entirely the server's business
        // (see HandlesModalForms::respondModalOrRedirect()) — this JS just follows whatever
        // redirect comes back.
        function openEntityModal(url) {
            var token = ++requestToken;
            var separator = url.indexOf('?') === -1 ? '?' : '&';

            titleEl.textContent = '';
            body.innerHTML = '<p class="p-6 text-sm text-slate-400">' + @json(__('common.loading')) + '</p>';
            openDialog();

            fetch(url + separator + 'modal=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (res) { return res.text(); })
                .then(function (html) { fillModal(html, token); });
        }

        document.addEventListener('click', function (e) {
            var trigger = e.target.closest('[data-entity-modal-trigger]');
            if (trigger) openEntityModal(trigger.getAttribute('data-entity-modal-trigger'));
        });

        // Scoped to [data-modal-ajax-form] (only the entity form's own main update form stamps
        // this) so any OTHER form living inside the same fetched fragment (a destroy/toggle
        // form, say) is left alone, submitting as a normal full-page POST exactly as it already
        // does outside a modal.
        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (! (form.matches && form.matches('[data-modal-ajax-form]'))) return;

            e.preventDefault();
            var token = requestToken;

            fetch(form.getAttribute('action'), {
                method: 'POST',
                body: new FormData(form),
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            }).then(function (res) {
                if (res.status === 422) {
                    return res.text().then(function (html) { fillModal(html, token); });
                }

                return res.json().then(function (data) {
                    if (token !== requestToken) return;
                    window.location.href = data.redirect;
                });
            }).catch(function () {
                // Network hiccup or an unexpected non-JSON error response (a 500, say) — no
                // generic in-modal error banner exists to route this into, so at minimum
                // don't leave the form silently stuck with its submit button still disabled
                // (see partials/disable-on-submit.blade.php) with no way to retry.
                form.querySelectorAll('button[type="submit"]').forEach(function (button) {
                    button.disabled = false;
                    var icon = button.querySelector('svg');
                    if (icon) icon.classList.remove('animate-spin');
                });
                window.alert(@json(__('common.unexpected_error')));
            });
        });

        dialog.addEventListener('close', function () {
            requestToken++;
            body.innerHTML = '';
            document.body.style.overflow = '';
        });
    })();
</script>
