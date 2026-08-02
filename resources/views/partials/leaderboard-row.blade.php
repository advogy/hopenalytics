{{--
    One row of components/leaderboard.blade.php's table — extracted so both its flat and grouped
    rendering branches share the same markup.

    Expected: $row, $index (0-based position — restarts per Uni/Daerah group when grouped), $ancestors (optional).
--}}
@php
    $entity = $row['social']->church ?? $row['social']->person ?? $row['social']->institution
        ?? $row['social']->union ?? $row['social']->conference;
    $entityUrl = match (true) {
        (bool) $row['social']->church => route('churches.show', $entity),
        (bool) $row['social']->person => route('people.show', $entity),
        (bool) $row['social']->institution => route('institutions.show', $entity),
        (bool) $row['social']->union => route('unions.show', $entity),
        (bool) $row['social']->conference => route('conferences.show', $entity),
        default => null,
    };
    $namePaddingClass = match ($depth ?? 0) {
        1 => 'pl-8',
        2 => 'pl-12',
        default => 'pl-0',
    };
@endphp
<tr @if ($ancestors ?? null) data-group-ancestors="{{ $ancestors }}" @endif>
    <td class="{{ $namePaddingClass }} py-2 pr-2 text-slate-400 dark:text-slate-500">{{ $index + 1 }}</td>
    <td class="py-2 pr-2">
        @if ($entityUrl)
            <a href="{{ $entityUrl }}" class="font-medium hover:text-blue-600 dark:hover:text-blue-400">
                {{ $row['social']->display_name }}
            </a>
        @else
            <span class="font-medium">{{ $row['social']->display_name }}</span>
        @endif
    </td>
    <td class="py-2 pr-2">
        <span class="inline-flex items-center gap-1.5 whitespace-nowrap">
            <x-platform-icon :platform="$row['social']->platform" class="h-4.5 w-4.5" />
            {{ $row['social']->display_handle }}
        </span>
    </td>
    <td class="py-2 pr-2 text-right font-medium tabular-nums {{ $row['delta'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : ($row['delta'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-400 dark:text-slate-500') }}">
        <span class="inline-flex items-center gap-1">
            <x-icon :name="$row['delta'] > 0 ? 'arrow-trending-up' : ($row['delta'] < 0 ? 'arrow-trending-down' : 'minus-small')" class="h-3.5 w-3.5" />
            {{ $row['delta'] > 0 ? '+' : '' }}{{ number_format($row['delta']) }}
        </span>
    </td>
    <td class="py-2 text-right tabular-nums">{{ number_format($row['latest']) }}</td>
</tr>
