{{-- One region (or the total) in the Keterlibatan Anggota table on goals.index. --}}
@php
    $progress = $row['progress_percent'];
@endphp
<tr class="align-middle">
    <td class="px-4 py-3">
        @if (! empty($row['url']))
            <a href="{{ $row['url'] }}" class="font-medium hover:text-blue-600 dark:hover:text-blue-400">{{ $row['name'] }}</a>
        @else
            <span class="font-medium">{{ $row['name'] }}</span>
        @endif
        @isset($row['churches'])
            <span class="block text-xs font-normal text-slate-400">
                {{ __('engagement.filled', ['filled' => $row['filled'], 'total' => $row['churches']]) }}
            </span>
        @endisset
    </td>
    <td class="px-4 py-3 text-right tabular-nums">
        @if ($row['members'] === null)
            <span class="text-xs font-normal text-slate-400">{{ __('engagement.not_filled') }}</span>
        @else
            {{ $num($row['members']) }}
        @endif
    </td>
    <td class="px-4 py-3 text-right tabular-nums">{{ $num($row['target']) }}</td>
    <td class="px-4 py-3 text-right tabular-nums">{{ $num($row['active']) }}</td>
    <td class="px-4 py-3 text-right tabular-nums">{{ $row['active_percent'] === null ? '—' : $row['active_percent'].'%' }}</td>
    <td class="px-4 py-3 text-right tabular-nums">
        @php $growth = $row['growth'] ?? null; @endphp
        @if ($growth === null || $growth['score'] === null)
            <span class="text-slate-400">—</span>
        @else
            <span
                title="{{ __('engagement.growth_tooltip', ['own' => $growth['ownCount'], 'personal' => $growth['personalCount']]) }}"
                class="{{ $growth['score'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : ($growth['score'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-500') }}"
            >{{ $growth['score'] > 0 ? '+' : '' }}{{ number_format($growth['score'], 1) }}%</span>
            <span class="block text-xs font-normal text-slate-400">{{ __('engagement.growth_counts', ['own' => $growth['ownCount'], 'personal' => $growth['personalCount']]) }}</span>
        @endif
    </td>
    <td class="px-4 py-3">
        @if ($progress === null)
            <span class="text-slate-400">—</span>
        @else
            <div class="flex items-center gap-2">
                <div class="h-2 min-w-24 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                    <div
                        @class(['h-full rounded-full', 'bg-emerald-500' => $progress >= 100, 'bg-blue-600' => $progress < 100])
                        style="width: {{ min($progress, 100) }}%"
                    ></div>
                </div>
                <span @class(['w-12 shrink-0 text-right text-xs tabular-nums', 'font-semibold text-emerald-600 dark:text-emerald-400' => $progress >= 100])>{{ $progress }}%</span>
            </div>
        @endif
    </td>
</tr>
