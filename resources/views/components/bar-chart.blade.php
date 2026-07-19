@props(['rows'])

@php
    $max = collect($rows)->max('value') ?: 1;
@endphp

@if (empty($rows))
    <div class="flex h-40 items-center justify-center rounded-xl border border-dashed border-slate-200 px-6 text-center text-sm text-slate-400 dark:border-slate-700 dark:text-slate-500">
        {{ __('common.no_platform_data') }}
    </div>
@else
    <div class="space-y-3">
        @foreach ($rows as $row)
            <div>
                <div class="mb-1 flex items-center justify-between gap-3 text-sm">
                    <span class="truncate font-medium">{{ $row['label'] }}</span>
                    <span class="shrink-0 tabular-nums">
                        {{ number_format($row['value']) }}
                        @if (($row['delta'] ?? null) !== null)
                            <span class="ml-1 text-xs font-medium {{ $row['delta'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : ($row['delta'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-400 dark:text-slate-500') }}">
                                ({{ $row['delta'] > 0 ? '+' : '' }}{{ number_format($row['delta']) }})
                            </span>
                        @endif
                    </span>
                </div>
                <div class="h-3 w-full rounded-full bg-slate-100 dark:bg-slate-800">
                    <div class="h-3 rounded-full bg-blue-500 dark:bg-blue-400" style="width: {{ $max > 0 ? round($row['value'] / $max * 100, 1) : 0 }}%"></div>
                </div>
            </div>
        @endforeach
    </div>
@endif
