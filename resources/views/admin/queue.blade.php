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

    <div id="antrean-pending" class="mb-8 scroll-mt-20 rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <div class="mb-4 flex items-center justify-between gap-2">
            <p class="font-bold text-slate-900 dark:text-white">{{ __('queue.pending_title') }}</p>
            @if ($pendingByQueue->isNotEmpty())
                <form method="POST" action="{{ route('queue.clear') }}" data-confirm="{{ __('queue.clear_all_confirm') }}">
                    @csrf
                    <button type="submit" class="text-sm font-medium text-red-600 hover:underline dark:text-red-400">
                        {{ __('queue.clear_all') }}
                    </button>
                </form>
            @endif
        </div>

        @if ($pendingByQueue->isEmpty())
            <p class="py-6 text-center text-sm text-slate-400 dark:text-slate-500">{{ __('queue.pending_empty') }}</p>
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
                                        <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">
                                            {{ __('queue.clear_queue') }}
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
            <p class="py-6 text-center text-sm text-slate-400 dark:text-slate-500">{{ __('queue.batches_empty') }}</p>
        @else
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($activeBatches as $batch)
                    <div class="py-3 first:pt-0 last:pb-0">
                        <div class="mb-1 flex items-center justify-between gap-2 text-sm">
                            <span class="font-medium">{{ $batch['name'] }}</span>
                            <div class="flex shrink-0 items-center gap-3">
                                <span class="text-slate-500 dark:text-slate-400">
                                    {{ __('queue.batches_progress', ['processed' => $batch['processed'], 'total' => $batch['total'], 'percent' => $batch['percent']]) }}
                                </span>
                                <form method="POST" action="{{ route('queue.cancel-batch', $batch['id']) }}" data-confirm="{{ __('queue.batches_cancel_confirm') }}">
                                    @csrf
                                    <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">
                                        {{ __('queue.batches_cancel') }}
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
                    <button type="submit" class="text-sm font-medium text-red-600 hover:underline dark:text-red-400">
                        {{ __('queue.clear_all') }}
                    </button>
                </form>
            @endif
        </div>

        @if ($completedBatches->isEmpty())
            <p class="py-6 text-center text-sm text-slate-400 dark:text-slate-500">{{ __('queue.completed_empty') }}</p>
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
                                <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">
                                    {{ __('queue.delete') }}
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div id="job-gagal" class="scroll-mt-20 rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <div class="mb-4 flex items-center justify-between gap-2">
            <p class="font-bold text-slate-900 dark:text-white">{{ __('queue.failed_title') }}</p>
            @if ($failedJobs->isNotEmpty())
                <form method="POST" action="{{ route('queue.clear-failed') }}" data-confirm="{{ __('queue.clear_failed_confirm') }}">
                    @csrf
                    <button type="submit" class="text-sm font-medium text-red-600 hover:underline dark:text-red-400">
                        {{ __('queue.clear_all') }}
                    </button>
                </form>
            @endif
        </div>

        @if ($failedJobs->isEmpty())
            <p class="py-6 text-center text-sm text-slate-400 dark:text-slate-500">{{ __('queue.failed_empty') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-slate-500 dark:text-slate-400">
                            <th class="py-2 pr-2 font-medium">{{ __('queue.failed_queue_col') }}</th>
                            <th class="py-2 pr-2 font-medium">{{ __('queue.failed_date_col') }}</th>
                            <th class="py-2 pr-2 font-medium">{{ __('queue.failed_error_col') }}</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($failedJobs as $job)
                            <tr>
                                <td class="py-2 pr-2 align-top font-medium whitespace-nowrap">{{ $job['queue'] }}</td>
                                <td class="py-2 pr-2 align-top whitespace-nowrap tabular-nums text-slate-500 dark:text-slate-400">
                                    {{ \Illuminate\Support\Carbon::parse($job['failedAt'])->translatedFormat('d M Y, H:i') }}
                                </td>
                                <td class="py-2 pr-2 align-top text-red-600 dark:text-red-400">{{ $job['message'] }}</td>
                                <td class="py-2 align-top text-right">
                                    <form method="POST" action="{{ route('queue.delete-failed', $job['id']) }}" data-confirm="{{ __('queue.delete_failed_confirm') }}">
                                        @csrf
                                        <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400 whitespace-nowrap">
                                            {{ __('queue.delete') }}
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
@endsection
