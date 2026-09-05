@extends('layouts.app')

@section('title', __('queue.title') . ' — ' . config('app.name'))

@section('content')
    <div class="mb-6">
        <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('queue.title') }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('queue.subtitle') }}</p>
    </div>

    <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card href="#antrean-pending" icon="clock" :label="__('queue.stat_pending')" :value="$totalPending" />
        <x-stat-card href="#batch-aktif" icon="arrow-path" :label="__('queue.stat_active_batches')" :value="$activeBatches->count()" />
        <x-stat-card href="#batch-selesai" icon="check-circle" :label="__('queue.stat_completed_batches')" :value="$completedBatches->count()" />
        <x-stat-card href="#job-gagal" icon="x-circle" :label="__('queue.stat_failed')" :value="$totalFailed" />
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
                    <tr class="text-slate-500 dark:text-slate-400">
                        <th class="py-2 pr-2 font-medium">{{ __('queue.fetch_uni_col_union') }}</th>
                        <th class="py-2 pr-2 text-right font-medium">{{ __('queue.fetch_uni_col_accounts') }}</th>
                        <th class="py-2 pr-2 font-medium">{{ __('queue.fetch_uni_col_last_fetched') }}</th>
                        <th class="py-2"></th>
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
                                        <span title="{{ __('queue.fetch_uni_running') }}" aria-label="{{ __('queue.fetch_uni_running') }}" class="inline-flex shrink-0 text-slate-400 dark:text-slate-500">
                                            <x-icon name="arrow-path" class="h-5 w-5 animate-spin" />
                                        </span>
                                    @elseif ($row['accountCount'] > 0)
                                        <form method="POST" action="{{ route('socials.refresh-union', $row['union']) }}" data-confirm="{{ __('queue.fetch_uni_confirm', ['count' => $row['accountCount'], 'union' => $row['union']->name]) }}" data-disable-on-submit>
                                            @csrf
                                            <button
                                                type="submit"
                                                title="{{ __('queue.fetch_uni_button') }}"
                                                aria-label="{{ __('queue.fetch_uni_button') }}"
                                                class="shrink-0 text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                                            >
                                                <x-icon name="arrow-path" class="h-5 w-5" />
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
                                        <span title="{{ __('queue.fetch_uni_running') }}" aria-label="{{ __('queue.fetch_uni_running') }}" class="inline-flex shrink-0 text-slate-400 dark:text-slate-500">
                                            <x-icon name="arrow-path" class="h-5 w-5 animate-spin" />
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
                                                title="{{ __('queue.fetch_uni_button') }}"
                                                aria-label="{{ __('queue.fetch_uni_button') }}"
                                                class="shrink-0 text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                                            >
                                                <x-icon name="arrow-path" class="h-5 w-5" />
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

    <div id="antrean-pending" class="mb-8 scroll-mt-20 rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <div class="mb-4 flex items-center justify-between gap-2">
            <p class="font-bold text-slate-900 dark:text-white">{{ __('queue.pending_title') }}</p>
            @if ($pendingByQueue->isNotEmpty())
                <form method="POST" action="{{ route('queue.clear') }}" data-confirm="{{ __('queue.clear_all_confirm') }}">
                    @csrf
                    <button type="submit" title="{{ __('queue.clear_all') }}" aria-label="{{ __('queue.clear_all') }}" class="shrink-0 text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300">
                        <x-icon name="trash" class="h-5 w-5" />
                    </button>
                </form>
            @endif
        </div>

        @if ($pendingByQueue->isEmpty())
            <x-empty-state variant="inline">{{ __('queue.pending_empty') }}</x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-slate-500 dark:text-slate-400">
                            <th class="py-2 pr-2 font-medium">{{ __('queue.pending_queue_col') }}</th>
                            <th class="py-2 pr-2 text-right font-medium">{{ __('queue.pending_count_col') }}</th>
                            <th class="py-2"></th>
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
                                        <button type="submit" title="{{ __('queue.clear_queue') }}" aria-label="{{ __('queue.clear_queue') }}" class="shrink-0 text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300">
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

    <div id="batch-aktif" class="mb-8 scroll-mt-20 rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <p class="mb-4 font-bold text-slate-900 dark:text-white">{{ __('queue.batches_title') }}</p>

        @if ($activeBatches->isEmpty())
            <x-empty-state variant="inline">{{ __('queue.batches_empty') }}</x-empty-state>
        @else
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($activeBatches as $batch)
                    <div class="py-3 first:pt-0 last:pb-0">
                        <div class="mb-1 flex items-center justify-between gap-2 text-sm">
                            <span class="min-w-0 truncate font-medium">{{ $batch['name'] }}</span>
                            <div class="flex shrink-0 items-center gap-3">
                                <span class="text-slate-500 dark:text-slate-400">
                                    {{ __('queue.batches_progress', ['processed' => $batch['processed'], 'total' => $batch['total'], 'percent' => $batch['percent']]) }}
                                </span>
                                <form method="POST" action="{{ route('queue.cancel-batch', $batch['id']) }}" data-confirm="{{ __('queue.batches_cancel_confirm') }}">
                                    @csrf
                                    <button type="submit" title="{{ __('queue.batches_cancel') }}" aria-label="{{ __('queue.batches_cancel') }}" class="shrink-0 text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300">
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
                @endforeach
            </div>
        @endif
    </div>

    <div id="batch-selesai" class="mb-8 scroll-mt-20 rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <div class="mb-4 flex items-center justify-between gap-2">
            <p class="font-bold text-slate-900 dark:text-white">{{ __('queue.completed_title') }}</p>
            @if ($completedBatches->isNotEmpty())
                <form method="POST" action="{{ route('queue.clear-completed-batches') }}" data-confirm="{{ __('queue.clear_completed_confirm') }}">
                    @csrf
                    <button type="submit" title="{{ __('queue.clear_all') }}" aria-label="{{ __('queue.clear_all') }}" class="shrink-0 text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300">
                        <x-icon name="trash" class="h-5 w-5" />
                    </button>
                </form>
            @endif
        </div>

        @if ($completedBatches->isEmpty())
            <x-empty-state variant="inline">{{ __('queue.completed_empty') }}</x-empty-state>
        @else
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($completedBatches as $batch)
                    <div class="flex items-center justify-between gap-3 py-3 text-sm first:pt-0 last:pb-0">
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
                        <div class="flex shrink-0 items-center gap-3">
                            <span class="text-xs text-slate-400 dark:text-slate-500">
                                {{ $batch['finishedAt']->translatedFormat('d M Y, H:i') }}
                            </span>
                            <form method="POST" action="{{ route('queue.delete-batch', $batch['id']) }}" data-confirm="{{ __('queue.delete_batch_confirm') }}">
                                @csrf
                                <button type="submit" title="{{ __('common.delete') }}" aria-label="{{ __('common.delete') }}" class="shrink-0 text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300">
                                    <x-icon name="trash" class="h-5 w-5" />
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div id="job-gagal" class="scroll-mt-20 rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
        {{-- No fixed action of its own — checkboxes below reference it purely via the form="..."
             attribute (they can't be nested inside it: each row's own Retry/Delete are already
             their own <form>, and HTML doesn't allow nested forms), and its two submit buttons
             each override where THIS same set of checked ids actually goes via their own
             formaction — same "one form, several submitters" shape confirm-dialog.blade.php's
             own e.submitter handling already supports (see its doc comment). --}}
        <form method="POST" id="failed-bulk-form" data-disable-on-submit></form>

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
                        class="shrink-0 text-blue-600 hover:text-blue-700 disabled:cursor-not-allowed disabled:opacity-40 dark:text-blue-400 dark:hover:text-blue-300"
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
                        class="shrink-0 text-red-600 hover:text-red-700 disabled:cursor-not-allowed disabled:opacity-40 dark:text-red-400 dark:hover:text-red-300"
                    >
                        <x-icon name="trash" class="h-5 w-5" />
                    </button>
                    {{-- x-circle (same glyph "Batalkan" already uses on this same page, for the
                         same "circle fills its box the same way trash/arrow-path do" reason)
                         rather than the bare x-mark glyph tried first — x-mark's own ink only
                         covers the middle of its 20x20 box, so even at an identical h-5 w-5 it
                         reads visibly smaller and sits higher than trash/arrow-path's fuller
                         glyphs, confirmed live. A divider alone (tried before that) still left
                         two near-identical trash icons side by side with nothing to tell them
                         apart. --}}
                    <form method="POST" action="{{ route('queue.clear-failed') }}" data-confirm="{{ __('queue.clear_failed_confirm') }}">
                        @csrf
                        <button type="submit" title="{{ __('queue.clear_all') }}" aria-label="{{ __('queue.clear_all') }}" class="shrink-0 text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300">
                            <x-icon name="x-circle" class="h-5 w-5" />
                        </button>
                    </form>
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
                        <tr class="text-slate-500 dark:text-slate-400">
                            <th class="w-8 py-2 pr-2">
                                <input type="checkbox" data-select-all-failed aria-label="{{ __('queue.select_all') }}" class="h-4 w-4 rounded border-black/20 text-blue-600 focus:ring-blue-500">
                            </th>
                            <th class="w-20 py-2 pr-2 font-medium">{{ __('queue.failed_queue_col') }}</th>
                            <th class="w-36 py-2 pr-2 font-medium">{{ __('queue.failed_account_col') }}</th>
                            <th class="w-36 py-2 pr-2 font-medium">{{ __('queue.failed_date_col') }}</th>
                            <th class="py-2 pr-2 font-medium">{{ __('queue.failed_error_col') }}</th>
                            <th class="w-16 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($failedJobs as $job)
                            <tr>
                                <td class="py-2 pr-2 align-top">
                                    <input type="checkbox" name="ids[]" value="{{ $job['id'] }}" form="failed-bulk-form" data-failed-job-checkbox class="h-4 w-4 rounded border-black/20 text-blue-600 focus:ring-blue-500">
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
                                                class="shrink-0 text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
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
                                                class="shrink-0 text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
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
        section's already-stable id (#antrean-pending/#batch-aktif/#batch-selesai/#job-gagal)
        avoids duplicating that controller's query/formatting logic in JS. Confirm-dialog clicks
        on swapped-in forms still work with no extra wiring — partials/confirm-dialog.blade.php
        listens on `document`, not on each form, so newly-injected forms are covered automatically.
    --}}
    <script>
        (function () {
            var sectionIds = ['fetch-per-uni', 'antrean-pending', 'batch-aktif', 'batch-selesai', 'job-gagal'];

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

    {{-- Job Gagal's checkboxes/select-all/bulk-retry-or-delete buttons — attached to the stable
         #job-gagal element via delegation rather than to the checkboxes/buttons themselves,
         since the poll-and-swap script above replaces #job-gagal's innerHTML every 3s (which
         would silently drop any listener bound directly to an element the moment a refresh swaps
         it out from under it). The wrapper div itself is never replaced, only its children, so a
         delegated listener on it keeps working across every refresh with no re-initialization
         needed.

         Both bulk buttons share one #failed-bulk-form (see its own doc comment) and so share one
         data-confirm attribute too — since confirm-dialog.blade.php reads that off the FORM, not
         the button that was clicked, each button's own resolved confirm text (with the current
         checked-count substituted in) is written onto the form at click time, just before the
         submit event fires and confirm-dialog.blade.php reads it. --}}
    <script>
        (function () {
            var jobGagal = document.getElementById('job-gagal');
            if (! jobGagal) return;

            function checkedCount() {
                return jobGagal.querySelectorAll('[data-failed-job-checkbox]:checked').length;
            }

            function updateBulkButtons() {
                var count = checkedCount();
                jobGagal.querySelectorAll('[data-bulk-retry-button], [data-bulk-delete-button]').forEach(function (button) {
                    button.disabled = count === 0;
                });
            }

            jobGagal.addEventListener('change', function (e) {
                if (e.target.matches('[data-select-all-failed]')) {
                    jobGagal.querySelectorAll('[data-failed-job-checkbox]').forEach(function (checkbox) {
                        checkbox.checked = e.target.checked;
                    });
                    updateBulkButtons();
                } else if (e.target.matches('[data-failed-job-checkbox]')) {
                    updateBulkButtons();
                }
            });

            jobGagal.addEventListener('click', function (e) {
                var button = e.target.closest('[data-bulk-retry-button], [data-bulk-delete-button]');
                if (! button) return;

                var form = document.getElementById('failed-bulk-form');
                var template = button.getAttribute('data-confirm-template');
                form.setAttribute('data-confirm', template.replace(':count', checkedCount()));
            });
        })();
    </script>
@endsection
