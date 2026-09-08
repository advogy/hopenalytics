@extends('layouts.app')

@section('title', __('queue.title') . ' — ' . config('app.name'))

@section('content')
    <div class="mb-6">
        <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('queue.title') }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('queue.subtitle') }}</p>
    </div>

    <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card href="{{ route('queue.index', ['tab' => 'tertunda']) }}" icon="clock" :label="__('queue.stat_pending')" :value="$totalPending" />
        <x-stat-card href="{{ route('queue.index', ['tab' => 'aktif']) }}" icon="arrow-path" :label="__('queue.stat_active_batches')" :value="$activeBatches->total()" />
        <x-stat-card href="{{ route('queue.index', ['tab' => 'selesai']) }}" icon="check-circle" :label="__('queue.stat_completed_batches')" :value="$completedBatches->total()" />
        <x-stat-card href="{{ route('queue.index', ['tab' => 'gagal']) }}" icon="x-circle" :label="__('queue.stat_failed')" :value="$totalFailed" />
    </div>

    {{-- Per-Uni "Fetch Now" — a scoped alternative to the global refresh button (which can take a
         long time across every account nationwide), per the user's explicit call. Included in
         the same poll-and-swap cycle below (see $sectionIds) so the account count/last-fetched/
         running state all stay live without a dedicated JS progress widget — reusing
         data-disable-on-submit + the global confirm-dialog is enough, since a page reload every
         3s already picks up the new state once a batch finishes. --}}
    <div id="fetch-per-uni" class="mb-8 scroll-mt-20 rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <p class="mb-1 font-bold text-slate-900 dark:text-white">{{ __('queue.fetch_uni_title') }}</p>
        <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">{{ __('queue.fetch_uni_subtitle') }}</p>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="bg-slate-50 text-slate-600 dark:bg-slate-800/60 dark:text-slate-300">
                        <th class="py-2 pr-2 font-semibold">{{ __('queue.fetch_uni_col_union') }}</th>
                        <th class="py-2 pr-2 text-right font-semibold">{{ __('queue.fetch_uni_col_accounts') }}</th>
                        <th class="py-2 pr-2 font-semibold">{{ __('queue.fetch_uni_col_last_fetched') }}</th>
                        <th class="py-2 text-right font-semibold">{{ __('common.action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @if ($unionFetchRows->isEmpty())
                        <tr>
                            <td colspan="4" class="py-4">
                                <x-empty-state variant="inline">{{ __('queue.fetch_uni_empty') }}</x-empty-state>
                            </td>
                        </tr>
                    @endif
                        @foreach ($unionFetchRows as $row)
                            <tr>
                                <td class="py-2 pr-2 font-medium">{{ $row['union']->name }}</td>
                                <td class="py-2 pr-2 text-right tabular-nums">{{ number_format($row['accountCount']) }}</td>
                                <td class="py-2 pr-2 text-slate-500 dark:text-slate-400">
                                    {{ $row['lastFetchedAt']?->translatedFormat('d M Y, H:i') ?? __('queue.fetch_uni_never') }}
                                </td>
                                <td class="py-2 text-right">
                                    @if ($row['isRunning'])
                                        <span class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap text-xs font-medium text-slate-400 dark:text-slate-500">
                                            <x-icon name="arrow-path" class="h-4 w-4 animate-spin" />
                                            {{ __('queue.fetch_uni_running') }}
                                        </span>
                                    @elseif ($row['accountCount'] > 0)
                                        <form method="POST" action="{{ route('socials.refresh-union', $row['union']) }}" data-confirm="{{ __('queue.fetch_uni_confirm', ['count' => $row['accountCount'], 'union' => $row['union']->name]) }}" data-disable-on-submit>
                                            @csrf
                                            <button
                                                type="submit"
                                                class="inline-flex shrink-0 cursor-pointer items-center gap-1.5 whitespace-nowrap rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-70"
                                            >
                                                <x-icon name="arrow-path" class="h-4 w-4" />
                                                {{ __('queue.fetch_uni_button') }}
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        {{-- Nationwide "fetch everything" row, below every per-Uni one — per the
                             user's explicit call to have both options in one place. Shares the
                             exact 'refresh-socials' batch (see globalFetchRow()) and the same
                             data-progress-form/data-progress-button wiring
                             churches/analytics.blade.php's own global refresh button already
                             uses, so clicking either one feeds the same floating progress widget
                             in layouts/app.blade.php rather than a second, conflicting tracker. --}}
                        <tr class="border-t-2 border-slate-100 dark:border-slate-800">
                            <td class="py-2 pr-2 font-bold text-slate-900 dark:text-white">{{ __('queue.fetch_all_label') }}</td>
                            <td class="py-2 pr-2 text-right tabular-nums">{{ number_format($globalFetchRow['accountCount']) }}</td>
                            <td class="py-2 pr-2 text-slate-500 dark:text-slate-400">
                                {{ $globalFetchRow['lastFetchedAt']?->translatedFormat('d M Y, H:i') ?? __('queue.fetch_uni_never') }}
                            </td>
                            <td class="py-2 text-right">
                                @can('trigger-refresh')
                                    @if ($globalFetchRow['isRunning'])
                                        <span class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap text-xs font-medium text-slate-400 dark:text-slate-500">
                                            <x-icon name="arrow-path" class="h-4 w-4 animate-spin" />
                                            {{ __('queue.fetch_uni_running') }}
                                        </span>
                                    @elseif ($globalFetchRow['accountCount'] > 0)
                                        <form
                                            method="POST"
                                            action="{{ route('socials.refresh-all') }}"
                                            data-confirm="{{ __('dashboard.refresh_confirm', ['count' => $globalFetchRow['accountCount']]) }}"
                                            data-progress-form
                                        >
                                            @csrf
                                            <button
                                                type="submit"
                                                data-progress-button
                                                class="inline-flex shrink-0 cursor-pointer items-center gap-1.5 whitespace-nowrap rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-70"
                                            >
                                                <x-icon name="arrow-path" class="h-4 w-4" />
                                                {{ __('queue.fetch_uni_button') }}
                                            </button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
    </div>

    {{-- Job Tertunda / Batch Aktif / Batch Selesai / Job Gagal as four tabs rather than
         always-visible cards, per the user's explicit call (Tertunda first) — each panel's own
         id (antrean-pending/batch-aktif/batch-selesai/job-gagal) is kept for the stat cards
         above and for @can/@empty markup already keyed off them. Only Tertunda stays part of the
         poll-and-swap auto-refresh below (see that script's own sectionIds) — it has no
         interactive state to lose; Aktif/Selesai/Gagal all carry their own bulk-select
         checkboxes now (a background swap would silently clear an in-progress selection, or
         yank a form out from under an open confirm dialog) and are excluded for that reason
         (see their own comment). --}}
    <x-tab-bar>
        <x-tab-button tab-key="tertunda">{{ __('queue.tab_pending') }}</x-tab-button>
        <x-tab-button tab-key="aktif">{{ __('queue.tab_active') }}</x-tab-button>
        <x-tab-button tab-key="selesai">{{ __('queue.tab_completed') }}</x-tab-button>
        <x-tab-button tab-key="gagal">{{ __('queue.tab_failed') }}</x-tab-button>
    </x-tab-bar>

    <div
        id="antrean-pending"
        data-tab-panel="tertunda"
        @class([
            'mb-8', 'scroll-mt-20', 'rounded-2xl', 'border', 'border-black/5', 'bg-white', 'p-5', 'shadow-sm', 'dark:border-white/5', 'dark:bg-slate-900',
            'hidden' => $activeTab !== 'tertunda',
        ])
    >
        {{-- Title-left / actions-right header, same shape on every one of these four panels
             (see e.g. #job-gagal's own) — just this panel's own single action instead of a
             row of bulk buttons. --}}
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <p class="font-bold text-slate-900 dark:text-white">{{ __('queue.pending_title') }}</p>
            @if ($pendingByQueue->isNotEmpty())
                <div class="flex items-center gap-3">
                    <form method="POST" action="{{ route('queue.clear') }}" data-confirm="{{ __('queue.clear_all_confirm') }}">
                        @csrf
                        <button type="submit" title="{{ __('queue.clear_all') }}" aria-label="{{ __('queue.clear_all') }}" class="inline-flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-lg text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-950/40 dark:hover:text-red-300">
                            <x-icon name="trash" class="h-5 w-5" />
                        </button>
                    </form>
                </div>
            @endif
        </div>

        @if ($pendingByQueue->isEmpty())
            <x-empty-state variant="inline">{{ __('queue.pending_empty') }}</x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-slate-600 dark:bg-slate-800/60 dark:text-slate-300">
                            <th class="py-2 pr-2 font-semibold">{{ __('queue.pending_queue_col') }}</th>
                            <th class="py-2 pr-2 text-right font-semibold">{{ __('queue.pending_count_col') }}</th>
                            <th class="py-2 text-right font-semibold">{{ __('common.action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($pendingByQueue as $row)
                            <tr>
                                <td class="py-2 pr-2 font-medium">{{ $row->queue }}</td>
                                <td class="py-2 pr-2 text-right tabular-nums">{{ number_format($row->total) }}</td>
                                <td class="py-2 text-right">
                                    <form method="POST" action="{{ route('queue.clear') }}" data-confirm="{{ __('queue.clear_queue_confirm', ['queue' => $row->queue]) }}">
                                        @csrf
                                        <input type="hidden" name="queue" value="{{ $row->queue }}">
                                        <button type="submit" title="{{ __('queue.clear_queue') }}" aria-label="{{ __('queue.clear_queue') }}" class="inline-flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-lg text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-950/40 dark:hover:text-red-300">
                                            <x-icon name="trash" class="h-5 w-5" />
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div
        id="batch-aktif"
        data-tab-panel="aktif"
        @class([
            'mb-8', 'scroll-mt-20', 'rounded-2xl', 'border', 'border-black/5', 'bg-white', 'p-5', 'shadow-sm', 'dark:border-white/5', 'dark:bg-slate-900',
            'hidden' => $activeTab !== 'aktif',
        ])
    >
        {{-- Same shared-external-form shape as Job Gagal's own bulk actions (see
             #failed-bulk-form's doc comment) — checkboxes below reference it purely via
             form="active-bulk-form" since they can't nest inside it (each row's own Cancel is
             already its own <form>). --}}
        <form method="POST" id="active-bulk-form" data-disable-on-submit>@csrf</form>

        {{-- Title-left / bulk-actions-right — same shape as every other panel on this page (see
             #job-gagal's own header comment); the select-all checkbox itself lives in the row
             list's own header bar just below instead of floating here, matching Job Gagal's
             select-all living in its <thead> rather than beside the title. --}}
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <p class="font-bold text-slate-900 dark:text-white">{{ __('queue.batches_title') }}</p>
            @if ($activeBatches->isNotEmpty())
                <div class="flex items-center gap-3">
                    <button
                        type="submit"
                        form="active-bulk-form"
                        formaction="{{ route('queue.cancel-batches-batch') }}"
                        data-bulk-cancel-button
                        data-confirm-template="{{ __('queue.batches_cancel_selected_confirm', ['count' => ':count']) }}"
                        disabled
                        title="{{ __('queue.batches_cancel_selected') }}"
                        aria-label="{{ __('queue.batches_cancel_selected') }}"
                        class="inline-flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-lg text-red-600 hover:bg-red-50 hover:text-red-700 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent dark:text-red-400 dark:hover:bg-red-950/40 dark:hover:text-red-300"
                    >
                        <x-icon name="x-circle" class="h-5 w-5" />
                    </button>
                </div>
            @endif
        </div>

        @if ($activeBatches->isEmpty())
            <x-empty-state variant="inline">{{ __('queue.batches_empty') }}</x-empty-state>
        @else
            {{-- Mimics a <table>'s own <thead> row — same bg-slate-50/dark:bg-slate-800/60 tint,
                 text-sm/font-semibold weight, and "no text next to the select-all checkbox
                 itself" convention (position alone reads as "select all") every real <thead> on
                 this page uses (see e.g. #failed-jobs-table's own) — but still needs real column
                 labels the way Job Gagal's does (Antrean/Akun/Waktu/Error) rather than just a
                 lone checkbox with nothing else, or the header row loses its whole point. The
                 two labels here mirror each row's own two pieces of info on its first line (name
                 left, progress right) — the progress bar/started-date lines underneath have no
                 header of their own, same as how a table row can carry more than what its column
                 headers alone describe. --}}
            <div class="mb-1 flex items-center justify-between gap-2 bg-slate-50 py-2 text-sm font-semibold text-slate-600 dark:bg-slate-800/60 dark:text-slate-300">
                <div class="flex items-center gap-3">
                    <input type="checkbox" data-select-all-active aria-label="{{ __('queue.select_all') }}" title="{{ __('queue.select_all') }}" class="h-4 w-4 shrink-0 cursor-pointer rounded border-black/20 text-blue-600 focus:ring-blue-500">
                    <span>{{ __('queue.batches_col_name') }}</span>
                </div>
                {{-- Mirrors the row's own right-side grouping exactly (see the flex
                     shrink-0 ... gap-3 wrapper around progress text + Cancel button below) so
                     "Aksi" lines up with where that icon actually sits, same as every real
                     table's own Aksi column on this page. --}}
                <div class="flex shrink-0 items-center gap-3">
                    <span>{{ __('queue.batches_col_progress') }}</span>
                    <span>{{ __('common.action') }}</span>
                </div>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($activeBatches as $batch)
                    <div class="flex items-start gap-3 py-2 first:pt-0 last:pb-0">
                        <input type="checkbox" name="ids[]" value="{{ $batch['id'] }}" form="active-bulk-form" data-active-batch-checkbox class="mt-1 h-4 w-4 shrink-0 cursor-pointer rounded border-black/20 text-blue-600 focus:ring-blue-500">
                        <div class="min-w-0 flex-1">
                            <div class="mb-1 flex flex-wrap items-center justify-between gap-2 text-sm">
                                <span class="min-w-0 truncate font-medium">{{ $batch['name'] }}</span>
                                <div class="flex shrink-0 flex-wrap items-center gap-3">
                                    <span class="text-slate-500 dark:text-slate-400">
                                        {{ __('queue.batches_progress', ['processed' => $batch['processed'], 'total' => $batch['total'], 'percent' => $batch['percent']]) }}
                                    </span>
                                    <form method="POST" action="{{ route('queue.cancel-batch', $batch['id']) }}" data-confirm="{{ __('queue.batches_cancel_confirm') }}">
                                        @csrf
                                        <button type="submit" title="{{ __('queue.batches_cancel') }}" aria-label="{{ __('queue.batches_cancel') }}" class="inline-flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-lg text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-950/40 dark:hover:text-red-300">
                                            <x-icon name="x-circle" class="h-5 w-5" />
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                <div class="h-full rounded-full bg-blue-600 transition-all" style="width: {{ $batch['percent'] }}%"></div>
                            </div>
                            <div class="mt-1 flex items-center gap-2 text-xs text-slate-400 dark:text-slate-500">
                                <span>{{ __('queue.batches_started', ['date' => $batch['createdAt']->translatedFormat('d M Y, H:i')]) }}</span>
                                @if ($batch['failed'] > 0)
                                    <span class="text-red-500 dark:text-red-400">
                                        &middot; {{ __('queue.batches_failed_note', ['count' => $batch['failed']]) }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <x-pagination :paginator="$activeBatches" />
        @endif
    </div>

    <div
        id="batch-selesai"
        data-tab-panel="selesai"
        @class([
            'mb-8', 'scroll-mt-20', 'rounded-2xl', 'border', 'border-black/5', 'bg-white', 'p-5', 'shadow-sm', 'dark:border-white/5', 'dark:bg-slate-900',
            'hidden' => $activeTab !== 'selesai',
        ])
    >
        {{-- Same shared-external-form shape as Job Gagal's own bulk actions (see
             #failed-bulk-form's doc comment). --}}
        <form method="POST" id="completed-bulk-form" data-disable-on-submit>@csrf</form>

        {{-- Title-left / bulk-actions-right — same shape as every other panel (see #job-gagal's
             own header comment); select-all sits in the row list's own header bar below, not
             here, matching Job Gagal's select-all living in its <thead> rather than beside the
             title. --}}
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <p class="font-bold text-slate-900 dark:text-white">{{ __('queue.completed_title') }}</p>
            @if ($completedBatches->isNotEmpty())
                <div class="flex items-center gap-3">
                    <button
                        type="submit"
                        form="completed-bulk-form"
                        formaction="{{ route('queue.delete-batches-batch') }}"
                        data-bulk-delete-completed-button
                        data-confirm-template="{{ __('queue.completed_delete_selected_confirm', ['count' => ':count']) }}"
                        disabled
                        title="{{ __('queue.completed_delete_selected') }}"
                        aria-label="{{ __('queue.completed_delete_selected') }}"
                        class="inline-flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-lg text-red-600 hover:bg-red-50 hover:text-red-700 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent dark:text-red-400 dark:hover:bg-red-950/40 dark:hover:text-red-300"
                    >
                        <x-icon name="trash" class="h-5 w-5" />
                    </button>
                </div>
            @endif
        </div>

        @if ($completedBatches->isEmpty())
            <x-empty-state variant="inline">{{ __('queue.completed_empty') }}</x-empty-state>
        @else
            {{-- Same "mimics a <thead>" header bar as Batch Aktif's own, with the same real
                 column labels (see that section's own comment for why) — mirroring this row's
                 own two pieces of info (name+status left, finished date right), plus Aksi
                 mirroring the row's own date+Delete-button grouping, same as every real table's
                 own Aksi column on this page. --}}
            <div class="mb-1 flex items-center justify-between gap-2 bg-slate-50 py-2 text-sm font-semibold text-slate-600 dark:bg-slate-800/60 dark:text-slate-300">
                <div class="flex items-center gap-3">
                    <input type="checkbox" data-select-all-completed aria-label="{{ __('queue.select_all') }}" title="{{ __('queue.select_all') }}" class="h-4 w-4 shrink-0 cursor-pointer rounded border-black/20 text-blue-600 focus:ring-blue-500">
                    <span>{{ __('queue.completed_col_name') }}</span>
                </div>
                <div class="flex shrink-0 items-center gap-3">
                    <span>{{ __('queue.completed_col_date') }}</span>
                    <span>{{ __('common.action') }}</span>
                </div>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($completedBatches as $batch)
                    <div class="flex flex-wrap items-center justify-between gap-3 py-2 text-sm first:pt-0 last:pb-0">
                        <div class="flex min-w-0 items-start gap-3">
                            <input type="checkbox" name="ids[]" value="{{ $batch['id'] }}" form="completed-bulk-form" data-completed-batch-checkbox class="mt-1 h-4 w-4 shrink-0 cursor-pointer rounded border-black/20 text-blue-600 focus:ring-blue-500">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium">{{ $batch['name'] }}</span>
                                    @if ($batch['cancelled'])
                                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                            <x-icon name="x-circle" class="h-3 w-3" />
                                            {{ __('queue.completed_cancelled') }}
                                        </span>
                                    @elseif ($batch['failed'] > 0)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-950 dark:text-amber-400">
                                            <x-icon name="x-circle" class="h-3 w-3" />
                                            {{ __('queue.completed_partial') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400">
                                            <x-icon name="check-circle" class="h-3 w-3" />
                                            {{ __('queue.completed_success') }}
                                        </span>
                                    @endif
                                </div>
                                <p class="text-xs text-slate-400 dark:text-slate-500">
                                    {{ __('queue.completed_summary', ['processed' => $batch['processed'], 'total' => $batch['total'], 'failed' => $batch['failed']]) }}
                                </p>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            <span class="text-xs text-slate-400 dark:text-slate-500">
                                {{ $batch['finishedAt']->translatedFormat('d M Y, H:i') }}
                            </span>
                            <form method="POST" action="{{ route('queue.delete-batch', $batch['id']) }}" data-confirm="{{ __('queue.delete_batch_confirm') }}">
                                @csrf
                                <button type="submit" title="{{ __('common.delete') }}" aria-label="{{ __('common.delete') }}" class="inline-flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-lg text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-950/40 dark:hover:text-red-300">
                                    <x-icon name="trash" class="h-5 w-5" />
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>

            <x-pagination :paginator="$completedBatches" />
        @endif
    </div>

    <div
        id="job-gagal"
        data-tab-panel="gagal"
        @class([
            'scroll-mt-20', 'rounded-2xl', 'border', 'border-black/5', 'bg-white', 'p-5', 'shadow-sm', 'dark:border-white/5', 'dark:bg-slate-900',
            'hidden' => $activeTab !== 'gagal',
        ])
    >
        {{-- No fixed action of its own — checkboxes below reference it purely via the form="..."
             attribute (they can't be nested inside it: each row's own Retry/Delete are already
             their own <form>, and HTML doesn't allow nested forms), and its two submit buttons
             each override where THIS same set of checked ids actually goes via their own
             formaction — same "one form, several submitters" shape confirm-dialog.blade.php's
             own e.submitter handling already supports (see its doc comment). --}}
        <form method="POST" id="failed-bulk-form" data-disable-on-submit>@csrf</form>

        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <p class="font-bold text-slate-900 dark:text-white">{{ __('queue.failed_title') }}</p>
            <div class="flex items-center gap-3">
                @if ($failedJobs->isNotEmpty())
                    <button
                        type="submit"
                        form="failed-bulk-form"
                        formaction="{{ route('queue.retry-failed-batch') }}"
                        data-bulk-retry-button
                        data-confirm-template="{{ __('queue.retry_selected_confirm', ['count' => ':count']) }}"
                        disabled
                        title="{{ __('queue.retry_selected') }}"
                        aria-label="{{ __('queue.retry_selected') }}"
                        class="inline-flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-lg text-blue-600 hover:bg-blue-50 hover:text-blue-700 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent dark:text-blue-400 dark:hover:bg-blue-950/40 dark:hover:text-blue-300"
                    >
                        <x-icon name="arrow-path" class="h-5 w-5" />
                    </button>
                    <button
                        type="submit"
                        form="failed-bulk-form"
                        formaction="{{ route('queue.delete-failed-batch') }}"
                        data-bulk-delete-button
                        data-confirm-template="{{ __('queue.delete_selected_confirm', ['count' => ':count']) }}"
                        disabled
                        title="{{ __('queue.delete_selected') }}"
                        aria-label="{{ __('queue.delete_selected') }}"
                        class="inline-flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-lg text-red-600 hover:bg-red-50 hover:text-red-700 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent dark:text-red-400 dark:hover:bg-red-950/40 dark:hover:text-red-300"
                    >
                        <x-icon name="trash" class="h-5 w-5" />
                    </button>
                @endif
            </div>
        </div>

        @if ($failedJobs->isEmpty())
            <x-empty-state variant="inline">{{ __('queue.failed_empty') }}</x-empty-state>
        @else
            {{--
                table-fixed + explicit widths on every column except Error (which takes
                whatever's left) — the error column's content is arbitrary-length text (and
                sometimes an unbroken URL), so without a fixed layout the browser was letting it
                grow the whole table past its container, forcing the horizontal scroll that
                dragged the action icons out of reach with it. With a fixed layout there's
                nothing to scroll: Error just wraps (break-words) within its own share of the
                row instead of expanding it, so the icons stay in the same place at all times.
                Akun also wraps (not truncated) so a long church/person name is fully readable
                instead of being cut off with an ellipsis.
            --}}
            <div class="overflow-x-auto">
                <table class="w-full table-fixed text-left text-sm" id="failed-jobs-table">
                    <thead>
                        <tr class="bg-slate-50 text-slate-600 dark:bg-slate-800/60 dark:text-slate-300">
                            <th class="w-8 py-2 pr-2">
                                <input type="checkbox" data-select-all-failed aria-label="{{ __('queue.select_all') }}" title="{{ __('queue.select_all') }}" class="h-4 w-4 cursor-pointer rounded border-black/20 text-blue-600 focus:ring-blue-500">
                            </th>
                            <th class="w-20 py-2 pr-2 font-semibold">{{ __('queue.failed_queue_col') }}</th>
                            <th class="w-36 py-2 pr-2 font-semibold">{{ __('queue.failed_account_col') }}</th>
                            <th class="w-36 py-2 pr-2 font-semibold">{{ __('queue.failed_date_col') }}</th>
                            <th class="py-2 pr-2 font-semibold">{{ __('queue.failed_error_col') }}</th>
                            <th class="w-16 py-2 text-right font-semibold">{{ __('common.action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($failedJobs as $job)
                            <tr>
                                <td class="py-2 pr-2 align-top">
                                    <input type="checkbox" name="ids[]" value="{{ $job['id'] }}" form="failed-bulk-form" data-failed-job-checkbox class="h-4 w-4 cursor-pointer rounded border-black/20 text-blue-600 focus:ring-blue-500">
                                </td>
                                <td class="truncate py-2 pr-2 align-top font-medium">{{ $job['queue'] }}</td>
                                <td class="py-2 pr-2 align-top break-words">{{ $job['account'] ?? '—' }}</td>
                                <td class="truncate py-2 pr-2 align-top tabular-nums text-slate-500 dark:text-slate-400">
                                    {{ \Illuminate\Support\Carbon::parse($job['failedAt'])->translatedFormat('d M Y, H:i') }}
                                </td>
                                <td class="py-2 pr-2 align-top break-words text-red-600 dark:text-red-400">{{ $job['message'] }}</td>
                                <td class="py-2 align-top text-right">
                                    <div class="flex flex-nowrap items-center justify-end gap-3">
                                        <form method="POST" action="{{ route('queue.retry-failed', $job['id']) }}" data-confirm="{{ __('queue.retry_failed_confirm') }}">
                                            @csrf
                                            <button
                                                type="submit"
                                                title="{{ __('queue.retry') }}"
                                                aria-label="{{ __('queue.retry') }}"
                                                class="inline-flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-lg text-blue-600 hover:bg-blue-50 hover:text-blue-700 dark:text-blue-400 dark:hover:bg-blue-950/40 dark:hover:text-blue-300"
                                            >
                                                <x-icon name="arrow-path" class="h-5 w-5" />
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('queue.delete-failed', $job['id']) }}" data-confirm="{{ __('queue.delete_failed_confirm') }}">
                                            @csrf
                                            <button
                                                type="submit"
                                                title="{{ __('common.delete') }}"
                                                aria-label="{{ __('common.delete') }}"
                                                class="inline-flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-lg text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-950/40 dark:hover:text-red-300"
                                            >
                                                <x-icon name="trash" class="h-5 w-5" />
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <x-pagination :paginator="$failedJobs" />
            </div>
        @endif
    </div>

    {{--
        Unlike the floating refresh-progress widget (layouts/app.blade.php), this page has no
        single batch id to poll a JSON status endpoint for — it shows several independent lists
        (pending queue, active/completed batches, failed jobs) all computed together in
        QueueMonitorController::index(). Re-fetching the same page as HTML and swapping each
        section's already-stable id (#fetch-per-uni/#antrean-pending/#batch-aktif)
        avoids duplicating that controller's query/formatting logic in JS. Confirm-dialog clicks
        on swapped-in forms still work with no extra wiring — partials/confirm-dialog.blade.php
        listens on `document`, not on each form, so newly-injected forms are covered automatically.
    --}}
    <script>
        (function () {
            // batch-aktif/batch-selesai/job-gagal deliberately excluded — they're tabs now (see
            // their own markup comment), not always-visible sections, and all three now hold
            // real interactive state (bulk-select checkboxes, a possibly-open confirm dialog)
            // that a background swap would silently wipe out (or worse, yank a form out from
            // under an open confirm dialog) from under whoever's mid-selection.
            var sectionIds = ['fetch-per-uni', 'antrean-pending'];

            function refresh() {
                if (document.visibilityState !== 'visible') return;

                fetch(window.location.href, { headers: { 'Accept': 'text/html' } })
                    .then(function (res) { return res.text(); })
                    .then(function (html) {
                        var fresh = new DOMParser().parseFromString(html, 'text/html');

                        sectionIds.forEach(function (id) {
                            var current = document.getElementById(id);
                            var updated = fresh.getElementById(id);
                            if (current && updated) current.innerHTML = updated.innerHTML;
                        });
                    })
                    .catch(function () {});
            }

            setInterval(refresh, 3000);
        })();
    </script>

    {{-- Bulk-select checkboxes/select-all/bulk-action buttons, shared by all three tabs that
         have them (Job Gagal's own retry-or-delete was the original; Batch Aktif's bulk-cancel
         and Batch Selesai's bulk-delete now follow the exact same shape). window.initBulkSelect()
         itself now lives in partials/bulk-select.blade.php (extracted once Kelola Pengguna's own
         "Semua User" tab needed the identical pattern a second time) — attached to each tab's own
         stable wrapper element (#job-gagal etc.) via delegation rather than to the
         checkboxes/buttons themselves, since Job Tertunda's poll-and-swap script above would
         otherwise silently drop a listener bound directly to an about-to-be-replaced element —
         moot for these three specifically (they're excluded from that poll for exactly this
         reason, see its own comment) but delegation costs nothing extra and keeps this robust
         either way. --}}
    @include('partials.bulk-select')
    <script>
        window.initBulkSelect('job-gagal', 'failed-bulk-form', '[data-failed-job-checkbox]', '[data-select-all-failed]', '[data-bulk-retry-button], [data-bulk-delete-button]');
        window.initBulkSelect('batch-aktif', 'active-bulk-form', '[data-active-batch-checkbox]', '[data-select-all-active]', '[data-bulk-cancel-button]');
        window.initBulkSelect('batch-selesai', 'completed-bulk-form', '[data-completed-batch-checkbox]', '[data-select-all-completed]', '[data-bulk-delete-completed-button]');
    </script>

    @include('partials.tab-script', ['activeTab' => $activeTab])
@endsection
