{{-- Shared styled confirm dialog for any form with a data-confirm="..." attribute — replaces
     the browser's native confirm() popup. Used by both layouts.app and layouts.guest. --}}
<style>
    dialog#confirm-dialog {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        margin: 0;
        padding: 0;
        border: none;
        border-radius: 1rem;
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
        max-width: 24rem;
        width: calc(100% - 2rem);
    }
    dialog#confirm-dialog::backdrop {
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(2px);
    }
</style>

<dialog id="confirm-dialog" class="bg-white dark:bg-slate-900">
    <div class="p-6">
        <p data-confirm-message class="text-sm text-slate-700 dark:text-slate-200"></p>
        <div class="mt-5 flex justify-end gap-3">
            <button
                type="button"
                data-confirm-cancel
                class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
            >
                {{ __('nav.confirm_cancel') }}
            </button>
            <button
                type="button"
                data-confirm-accept
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700"
            >
                {{ __('nav.confirm_accept') }}
            </button>
        </div>
    </div>
</dialog>

<script>
    (function () {
        var dialog = document.getElementById('confirm-dialog');
        var messageEl = dialog.querySelector('[data-confirm-message]');
        var acceptBtn = dialog.querySelector('[data-confirm-accept]');
        var cancelBtn = dialog.querySelector('[data-confirm-cancel]');
        var pendingForm = null;

        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (form.hasAttribute && form.hasAttribute('data-confirm') && !form.dataset.confirmed) {
                e.preventDefault();
                pendingForm = form;
                messageEl.textContent = form.getAttribute('data-confirm');
                dialog.showModal();
            }
        });

        acceptBtn.addEventListener('click', function () {
            if (pendingForm) {
                pendingForm.dataset.confirmed = 'true';
                dialog.close();
                pendingForm.requestSubmit();
                pendingForm = null;
            }
        });

        cancelBtn.addEventListener('click', function () {
            pendingForm = null;
            dialog.close();
        });
    })();
</script>
