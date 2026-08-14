{{--
    Shared per-tab result card (Kelola Akun, Kelola Pengguna, Riwayat Aktivitas): title/subtitle
    header, an empty-state message when the list has nothing, otherwise a table (columns
    entirely up to the caller — $slot holds the <thead>/<tbody>, since every tab's columns
    genuinely differ) plus pagination.

    Empty-state text: pass :empty-message directly for a single fixed message, or leave it out
    and pass :search + :entity-label for the search-conditional "no match" vs "nothing yet" pair
    (Kelola Akun's own convention).

    :paginated (default true): set false when $items is a plain Collection rather than a
    paginator (several Kelola Pengguna tabs list everyone in one go, no pagination).
--}}
@props(['items', 'title', 'subtitle', 'search' => null, 'entityLabel' => null, 'emptyMessage' => null, 'paginated' => true])

<div class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
    <p class="mb-1 font-bold text-slate-900 dark:text-white">{{ $title }}</p>
    <p class="mb-4 border-b border-black/5 pb-4 text-sm text-slate-500 dark:border-white/5 dark:text-slate-400">{{ $subtitle }}</p>

    @isset($beforeContent)
        {{ $beforeContent }}
    @endisset

    @if ($items->isEmpty())
        <x-empty-state variant="inline">{{ $emptyMessage ?? ($search ? __('accounts.no_match', ['entity' => $entityLabel]) : __('accounts.no_yet', ['entity' => $entityLabel])) }}</x-empty-state>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                {{ $slot }}
            </table>
        </div>

        @if ($paginated)
            <x-pagination :paginator="$items" />
        @endif
    @endif
</div>
