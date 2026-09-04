@extends('layouts.app')

@section('title', __('email_broadcasts.index_title') . ' — ' . config('app.name'))

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('email_broadcasts.index_title') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('email_broadcasts.index_subtitle') }}</p>
        </div>
        <a href="{{ route('admin.email-broadcasts.create') }}" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
            {{ __('email_broadcasts.new_button') }}
        </a>
    </div>

    <div class="rounded-2xl border border-black/5 bg-white shadow-sm dark:border-white/5 dark:bg-slate-900">
        @if ($broadcasts->isEmpty())
            <div class="p-12 text-center">
                <p class="text-slate-500 dark:text-slate-400">{{ __('email_broadcasts.none_yet') }}</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800/60">
                        <tr>
                            <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('email_broadcasts.col_subject') }}</th>
                            <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('email_broadcasts.col_sender') }}</th>
                            <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('email_broadcasts.col_scope') }}</th>
                            <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('email_broadcasts.col_groups') }}</th>
                            <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('email_broadcasts.col_sent_at') }}</th>
                            <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('email_broadcasts.col_progress') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($broadcasts as $broadcast)
                            @php($batch = $broadcast->batch())
                            <tr
                                data-broadcast-row="{{ $broadcast->id }}"
                                data-status-url="{{ route('admin.email-broadcasts.status', $broadcast) }}"
                                data-finished="{{ ($batch?->finished() ?? true) ? '1' : '0' }}"
                            >
                                <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ $broadcast->subject }}</td>
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $broadcast->sender?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $broadcast->division?->name ?? __('email_broadcasts.scope_nationwide') }}</td>
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400">
                                    {{ collect($broadcast->groups)->map(fn ($group) => $groupLabels[$group] ?? $group)->implode(', ') }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-slate-500 dark:text-slate-400">{{ $broadcast->created_at->translatedFormat('d M Y H:i') }}</td>
                                <td class="px-4 py-3">
                                    <div class="min-w-[160px]">
                                        <div class="mb-1 h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                            <div
                                                data-progress-bar
                                                class="h-full rounded-full bg-blue-600 transition-all"
                                                style="width: {{ $batch && $batch->totalJobs > 0 ? min(100, (int) round(($batch->processedJobs() / $batch->totalJobs) * 100)) : 100 }}%"
                                            ></div>
                                        </div>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">
                                            <span data-progress-count>{{ __('email_broadcasts.progress_label', ['processed' => $batch?->processedJobs() ?? $broadcast->total_recipients, 'total' => $batch?->totalJobs ?? $broadcast->total_recipients]) }}</span>
                                            <span data-progress-failed class="text-red-600 dark:text-red-400" @if (! $batch || $batch->failedJobs === 0) hidden @endif>
                                                · {{ __('email_broadcasts.progress_failed', ['count' => $batch?->failedJobs ?? 0]) }}
                                            </span>
                                            — <span data-progress-state>{{ ($batch?->finished() ?? true) ? __('email_broadcasts.progress_done') : __('email_broadcasts.progress_in_progress') }}</span>
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-4">
                <x-pagination :paginator="$broadcasts" />
            </div>
        @endif
    </div>

    <script>
        (function () {
            // Self-contained polling for this page only — deliberately not wired into the
            // global refresh-progress tracker in layouts.app (that one's hardcoded to the
            // socials.refresh-* routes), since this history list only ever needs to update
            // while an admin happens to have it open.
            var rows = document.querySelectorAll('[data-broadcast-row][data-finished="0"]');
            if (! rows.length) return;

            function poll(row) {
                fetch(row.dataset.statusUrl, { headers: { Accept: 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        var percent = data.total > 0 ? Math.min(100, Math.round((data.processed / data.total) * 100)) : 100;
                        row.querySelector('[data-progress-bar]').style.width = percent + '%';

                        var countLabel = @json(__('email_broadcasts.progress_label', ['processed' => ':processed', 'total' => ':total']));
                        row.querySelector('[data-progress-count]').textContent = countLabel.replace(':processed', data.processed).replace(':total', data.total);

                        var failedEl = row.querySelector('[data-progress-failed]');
                        if (data.failed > 0) {
                            var failedLabel = @json(__('email_broadcasts.progress_failed', ['count' => ':count']));
                            failedEl.textContent = '· ' + failedLabel.replace(':count', data.failed);
                            failedEl.hidden = false;
                        }

                        var stateEl = row.querySelector('[data-progress-state]');
                        if (data.finished) {
                            stateEl.textContent = @json(__('email_broadcasts.progress_done'));
                            row.dataset.finished = '1';
                        } else {
                            setTimeout(function () { poll(row); }, 4000);
                        }
                    })
                    .catch(function () {
                        setTimeout(function () { poll(row); }, 8000);
                    });
            }

            rows.forEach(poll);
        })();
    </script>
@endsection
