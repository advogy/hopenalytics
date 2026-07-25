{{--
    One row of churches/platform-comparison.blade.php's table — extracted so both its flat and
    grouped rendering branches share the same markup.

    Expected: $row (['label' => ..., 'value' => ..., 'delta' => ..., $scope->rowKey() => entity]),
    $index, $scope, $ancestors (optional).
--}}
<tr @if ($ancestors ?? null) data-group-ancestors="{{ $ancestors }}" @endif>
    <td class="px-4 py-2.5 text-slate-400 dark:text-slate-500">{{ $index + 1 }}</td>
    <td class="px-4 py-2.5">
        <a href="{{ $scope->showUrl($row[$scope->rowKey()]) }}" class="font-medium hover:text-blue-600 dark:hover:text-blue-400">
            {{ $row['label'] }}
        </a>
    </td>
    <td class="px-4 py-2.5 text-right font-medium tabular-nums">{{ number_format($row['value']) }}</td>
    <td class="px-4 py-2.5 text-right tabular-nums">
        @if ($row['delta'] === null)
            <span class="text-slate-300 dark:text-slate-600">—</span>
        @else
            <span class="inline-flex items-center gap-1 font-medium {{ $row['delta'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : ($row['delta'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-400 dark:text-slate-500') }}">
                <x-icon :name="$row['delta'] > 0 ? 'arrow-trending-up' : ($row['delta'] < 0 ? 'arrow-trending-down' : 'minus-small')" class="h-3.5 w-3.5" />
                {{ $row['delta'] > 0 ? '+' : '' }}{{ number_format($row['delta']) }}
            </span>
        @endif
    </td>
</tr>
