{{--
    Shared icon-only Edit/Nonaktifkan/Hapus actions for every tab on Kelola Akun (Uni/Daerah/
    Gereja/Institusi/Personal). Route names differ per entity (Union/Conference/Institution live
    under admin.*, Church and Person don't), so they're passed in rather than hardcoded.

    Expected props: $item, $editRoute, $toggleRoute, $deleteRoute, $name, $canDelete, $blockedReason
    Optional prop: $deleteWarning — appended to the delete confirm text for entities whose
    delete cascades away more than just the row itself (e.g. Person's social accounts).
    Optional prop: $viewRoute — an entity's own social-account management list (add/edit); only
    Church/Union/Conference/Institution have one of these today, so it's opt-in.
    Optional prop: $blockingUsers — the actual users blocking deletion (only meaningful when
    !$canDelete because of a user assignment, not a child-entity count like Union's Daerah).
    Whichever of them are role === null (self-reported via Lengkapi Profil, never promoted —
    see CompleteProfileController) get an inline "release" shortcut next to the locked trash
    icon, so clearing them doesn't require a separate trip to Kelola Pengguna's easy-to-miss
    "Belum Ditugaskan" tab. Active Admin/Pimpinan blockers deliberately don't get this shortcut
    — see UserAssignmentController::releaseRegionBulk()'s doc comment for why.

    Edit/Toggle/Kelola-Akun are gated by can:update on $item, and Hapus by can:delete — most
    entity types' list query (visibleTo()) matches their update() policy exactly, so this is
    normally a no-op, but Institution is a real exception: a nasional (unscoped) institution is
    visible to every level for context (same as a Uni is visible to the Daerah beneath it), yet
    only nasional-level can actually edit it — without this @can wrap, admin_uni/admin_daerah
    would see fully-clickable Edit/Kelola Akun/Hapus icons on a row they can't actually act on,
    landing on a 403. A row the viewer can't update renders no actions at all, not disabled ones.
--}}
@php
    $deleteWarning = $deleteWarning ?? '';
    $releasableUsers = isset($blockingUsers) ? $blockingUsers->where('role', null) : collect();
@endphp
<div class="flex flex-nowrap items-center justify-end gap-3">
    @can('update', $item)
        @isset($viewRoute)
            <a
                href="{{ route($viewRoute, $item) }}"
                title="{{ __('nav.manage_accounts') }}"
                aria-label="{{ __('nav.manage_accounts') }}"
                class="shrink-0 text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400"
            >
                <x-icon name="globe-alt" class="h-5 w-5" />
            </a>
        @endisset

        <a
            href="{{ route($editRoute, $item) }}"
            title="{{ __('common.edit') }}"
            aria-label="{{ __('common.edit') }}"
            class="shrink-0 text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
        >
            <x-icon name="pencil-square" class="h-5 w-5" />
        </a>

        <form
            method="POST"
            action="{{ route($toggleRoute, $item) }}"
            @if ($item->is_active) data-confirm="{{ __('accounts.deactivate_confirm', ['name' => $name]) }}" @endif
        >
            @csrf
            @method('PATCH')
            <button
                type="submit"
                title="{{ $item->is_active ? __('accounts.deactivate') : __('accounts.activate') }}"
                aria-label="{{ $item->is_active ? __('accounts.deactivate') : __('accounts.activate') }}"
                class="shrink-0 {{ $item->is_active ? 'text-slate-500 hover:text-red-600 dark:text-slate-400 dark:hover:text-red-400' : 'text-slate-500 hover:text-emerald-600 dark:text-slate-400 dark:hover:text-emerald-400' }}"
            >
                <x-icon name="{{ $item->is_active ? 'x-circle' : 'check-circle' }}" class="h-5 w-5" />
            </button>
        </form>
    @endcan

    @can('delete', $item)
        @if ($canDelete)
            <form
                method="POST"
                action="{{ route($deleteRoute, $item) }}"
                data-confirm="{{ __('accounts.delete_confirm', ['name' => $name]) }}{{ $deleteWarning ? ' ' . $deleteWarning : '' }}"
            >
                @csrf
                @method('DELETE')
                <button
                    type="submit"
                    title="{{ __('common.delete') }}"
                    aria-label="{{ __('common.delete') }}"
                    class="shrink-0 text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                >
                    <x-icon name="trash" class="h-5 w-5" />
                </button>
            </form>
        @else
            @if ($releasableUsers->isNotEmpty())
                <form
                    method="POST"
                    action="{{ route('admin.users.release-region-bulk') }}"
                    data-confirm="{{ __('accounts.release_blocking_users_confirm', ['names' => $releasableUsers->pluck('name')->implode(', ')]) }}"
                >
                    @csrf
                    @foreach ($releasableUsers as $releasableUser)
                        <input type="hidden" name="user_ids[]" value="{{ $releasableUser->id }}">
                    @endforeach
                    <button
                        type="submit"
                        title="{{ __('accounts.release_blocking_users', ['names' => $releasableUsers->pluck('name')->implode(', ')]) }}"
                        aria-label="{{ __('accounts.release_blocking_users', ['names' => $releasableUsers->pluck('name')->implode(', ')]) }}"
                        class="shrink-0 text-amber-500 hover:text-amber-600 dark:text-amber-400 dark:hover:text-amber-300"
                    >
                        <x-icon name="x-mark" class="h-5 w-5" />
                    </button>
                </form>
            @endif
            <span title="{{ $blockedReason }}" aria-label="{{ $blockedReason }}" class="shrink-0 cursor-not-allowed text-slate-300 dark:text-slate-700">
                <x-icon name="trash" class="h-5 w-5" />
            </span>
        @endif
    @endcan
</div>
