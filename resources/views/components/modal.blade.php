{{--
    Generic reusable popup, styled to match the <dialog>-based pattern this app already uses
    (components/growth-score-summary.blade.php's detail dialog, partials/confirm-dialog.blade.php)
    instead of introducing a third, differently-styled one: fixed + centered, 1rem rounded
    corners, blurred backdrop, a header row with a title and a close (x-circle) button, and a
    scrollable body. $slot is the body content — left empty here for a modal whose content is
    filled in by JS after the page loads (see admin/accounts/index.blade.php's edit-modal), or
    given real content directly for a modal whose body is known at render time.

    Expected id prop is unique per instance so multiple <x-modal>s can coexist on one page (only
    one is normally shown() at a time, but each needs its own DOM id for its own trigger/close
    wiring elsewhere). $title is plain text; pass an empty string to omit the header row's text
    (rare — most callers want an actual heading).
--}}
@props(['id', 'title' => '', 'bodyId' => null, 'bodyClass' => 'max-h-[70vh] overflow-y-auto p-6', 'maxWidth' => '32rem'])

<dialog id="{{ $id }}" data-app-modal class="bg-white dark:bg-slate-900" style="--app-modal-max-width: {{ $maxWidth }}">
    <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 dark:border-slate-800">
        <p data-app-modal-title class="text-lg font-bold text-slate-900 dark:text-white">{{ $title }}</p>
        <button
            type="button"
            data-app-modal-close
            aria-label="{{ __('common.close') }}"
            class="inline-flex h-7 w-7 shrink-0 cursor-pointer items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800"
        >
            <x-icon name="x-circle" class="h-5 w-5" />
        </button>
    </div>

    <div @if ($bodyId) id="{{ $bodyId }}" @endif class="{{ $bodyClass }}">
        {{ $slot }}
    </div>
</dialog>

@once
    <style>
        dialog[data-app-modal] {
            /* Centered via inset:0 + margin:auto (the same mechanism a <dialog>'s own default
               UA styles already center it with) rather than the more common
               top/left:50% + transform:translate(-50%,-50%) trick — a transform on this
               element would make IT the containing block for every position:fixed descendant
               inside it (per the CSS spec: a transformed ancestor always becomes the containing
               block for fixed/absolute descendants), which broke
               partials/searchable-select.blade.php's own position:fixed dropdown the moment a
               form with a Uni/Daerah searchable-select (Tambah/Edit Gereja, Institusi, ...)
               was shown inside this modal — its left/top are computed from
               getBoundingClientRect() (viewport-relative) but were being applied against the
               dialog's own box instead, throwing the list off to the side. inset+margin:auto
               centers without ever creating a new containing block, so that widget (and any
               other position:fixed content) keeps resolving against the real viewport. */
            position: fixed;
            inset: 0;
            margin: auto;
            padding: 0;
            border: none;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
            max-width: var(--app-modal-max-width, 32rem);
            width: calc(100% - 2rem);
            max-height: calc(100vh - 4rem);
        }
        dialog[data-app-modal]::backdrop {
            background: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(2px);
        }
    </style>

    <script>
        // Delegated on document, same as growth-score-summary's own detail dialog, so any
        // number of modal instances on a page share this one listener instead of each wiring
        // its own. Only handles the close button + backdrop click; opening is always page-specific
        // (each caller decides what triggers it and what fills the body), so that stays outside
        // this shared script.
        if (! window.__appModalBound) {
            window.__appModalBound = true;

            document.addEventListener('click', function (e) {
                var closeBtn = e.target.closest('[data-app-modal-close]');
                if (closeBtn) {
                    closeBtn.closest('dialog').close();
                    return;
                }

                // A click on the <dialog> element itself (not something inside it) only happens
                // via the ::backdrop area or the thin padding box around the content — showModal()
                // makes the backdrop part of the same element for click-target purposes.
                var dialog = e.target.closest('dialog[data-app-modal]');
                if (dialog && e.target === dialog) {
                    dialog.close();
                }
            });
        }
    </script>
@endonce
