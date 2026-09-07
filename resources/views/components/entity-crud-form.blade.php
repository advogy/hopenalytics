{{--
    Shared shell for every "add/edit an org-hierarchy entity" page (Divisi/Uni/Daerah/Institusi/
    Gereja/Personal) — back-link, title, the POST form itself (csrf/method/submit/cancel), and
    an optional footer action. $slot holds the entity-specific fields (name, region pickers,
    coordinates, ...). Two mutually-exclusive footer shapes, since this app has two different
    "remove an entity" patterns:
    - destroy* props: a real DELETE (Divisi/Uni/Daerah/Institusi — blocked server-side while the
      entity still has dependents, see e.g. UnionController::destroy()).
    - toggle* props: a PATCH that flips is_active (Gereja/Personal — never truly deleted, just
      hidden; :toggleConfirm is only meaningful while currently active, since re-activating
      needs no confirmation).
    $footerExtra is an optional named slot rendered between the main form and that footer, for
    per-entity content that doesn't fit the shared shape (e.g. people/form.blade.php's
    login-account linking card).

    $modal: true only when this is being rendered for injection into Kelola Akun's edit modal
    (see admin/accounts/index.blade.php + each *.form.blade.php's own conditional @extends) —
    skips the back-link (a close button in the modal chrome does that job instead) and the <h1>
    (the title goes in the modal's own head bar instead — see components/modal.blade.php's
    [data-app-modal-title] — carried here as a data-modal-title attribute on the form itself for
    admin/accounts/index.blade.php's JS to read and move into place after injecting this markup),
    and stamps a hidden `modal=1` field onto the form so a resubmit is recognized as
    modal-originated too (see
    Concerns/RedirectsToAccountsTab::validateOrRespondModal()/respondModalOrRedirect()).
--}}
@props([
    'entity',
    'action',
    'backUrl',
    'backLabel' => null,
    'title',
    'submitLabel',
    'destroyAction' => null,
    'destroyConfirm' => null,
    'destroyLabel' => null,
    'toggleAction' => null,
    'toggleConfirm' => null,
    'toggleLabel' => null,
    'modal' => false,
])

@unless ($modal)
    <x-back-link :href="$backUrl">{{ $backLabel ?? __('common.back') }}</x-back-link>

    <h1 class="mb-8 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
        {{ $title }}
    </h1>
@endunless

{{-- data-disable-on-submit (see partials/disable-on-submit.blade.php, wired globally in
     layouts/app.blade.php): blocks a second identical Gereja/Institusi/Daerah/Personal/Uni/
     Divisi record from a double-click on Submit while the first request is still in flight —
     the Back-button half of preventing duplicate data is the NoCacheFormPage middleware on
     this page's own create/edit route instead (see that class's own doc comment). --}}
<form
    method="POST"
    action="{{ $action }}"
    data-disable-on-submit
    @if ($modal) data-modal-ajax-form data-modal-title="{{ $title }}" @endif
    class="max-w-lg rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900"
>
    @csrf
    @if ($entity->exists)
        @method('PUT')
    @endif
    @if ($modal)
        <input type="hidden" name="modal" value="1">
    @endif

    {{ $slot }}

    <div class="flex items-center gap-3">
        <button type="submit" class="cursor-pointer rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
            {{ $submitLabel }}
        </button>
        @if ($modal)
            {{-- Closes the modal in place (see components/modal.blade.php's shared
                 [data-app-modal-close] listener) instead of navigating the whole page to
                 $backUrl — there's nothing to "go back" to, the page underneath never left. --}}
            <button type="button" data-app-modal-close class="cursor-pointer text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                {{ __('common.cancel') }}
            </button>
        @else
            <a href="{{ $backUrl }}" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                {{ __('common.cancel') }}
            </a>
        @endif
    </div>
</form>

@isset($footerExtra)
    {{ $footerExtra }}
@endisset

@if ($destroyAction)
    <form
        method="POST"
        action="{{ $destroyAction }}"
        class="mt-6 max-w-lg"
        data-confirm="{{ $destroyConfirm }}"
    >
        @csrf
        @method('DELETE')
        <button type="submit" class="cursor-pointer text-sm text-red-600 hover:underline dark:text-red-400">
            {{ $destroyLabel }}
        </button>
    </form>
@endif

@if ($toggleAction)
    <form
        method="POST"
        action="{{ $toggleAction }}"
        class="mt-6 max-w-lg"
        @if ($toggleConfirm) data-confirm="{{ $toggleConfirm }}" @endif
    >
        @csrf
        @method('PATCH')
        <button type="submit" class="cursor-pointer text-sm text-red-600 hover:underline dark:text-red-400">
            {{ $toggleLabel }}
        </button>
    </form>
@endif
