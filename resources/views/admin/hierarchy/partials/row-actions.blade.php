{{--
    Shared icon-only Edit/Nonaktifkan/Hapus actions for Kelola Organisasi and Kelola Personal.
    Route names differ per entity (Union/Conference/Institution live under admin.*, Church and
    Person don't), so they're passed in rather than hardcoded.

    Expected props: $item, $editRoute, $toggleRoute, $deleteRoute, $name, $canDelete, $blockedReason
    Optional prop: $deleteWarning — appended to the delete confirm text for entities whose
    delete cascades away more than just the row itself (e.g. Person's social accounts).
--}}
@php $deleteWarning = $deleteWarning ?? ''; @endphp
<div class="flex flex-nowrap items-center justify-end gap-3">
    <a
        href="{{ route($editRoute, $item) }}"
        title="Edit"
        aria-label="Edit"
        class="shrink-0 text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
    >
        <x-icon name="pencil-square" class="h-5 w-5" />
    </a>

    <form
        method="POST"
        action="{{ route($toggleRoute, $item) }}"
        @if ($item->is_active) data-confirm="Nonaktifkan &quot;{{ $name }}&quot;?" @endif
    >
        @csrf
        @method('PATCH')
        <button
            type="submit"
            title="{{ $item->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"
            aria-label="{{ $item->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"
            class="shrink-0 {{ $item->is_active ? 'text-slate-500 hover:text-red-600 dark:text-slate-400 dark:hover:text-red-400' : 'text-slate-500 hover:text-emerald-600 dark:text-slate-400 dark:hover:text-emerald-400' }}"
        >
            <x-icon name="{{ $item->is_active ? 'x-circle' : 'check-circle' }}" class="h-5 w-5" />
        </button>
    </form>

    @if ($canDelete)
        <form
            method="POST"
            action="{{ route($deleteRoute, $item) }}"
            data-confirm="Hapus &quot;{{ $name }}&quot;? Tindakan ini permanen dan tidak bisa dibatalkan.{{ $deleteWarning ? ' ' . $deleteWarning : '' }}"
        >
            @csrf
            @method('DELETE')
            <button
                type="submit"
                title="Hapus"
                aria-label="Hapus"
                class="shrink-0 text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
            >
                <x-icon name="trash" class="h-5 w-5" />
            </button>
        </form>
    @else
        <span title="{{ $blockedReason }}" aria-label="{{ $blockedReason }}" class="shrink-0 cursor-not-allowed text-slate-300 dark:text-slate-700">
            <x-icon name="trash" class="h-5 w-5" />
        </span>
    @endif
</div>
