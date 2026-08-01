{{--
    A collapsible section-header <tr> for the Data Per * tables (see partials/analytics-group-
    toggle.blade.php for the click-handling script). $toggleId is this group's own id — clicking
    the row toggles it. $ancestors (space-separated ids, or null for a top-level group) lists
    every ancestor group this row itself is nested under, so it starts hidden until all of them
    are expanded too. $depth (0 = Uni, 1 = Daerah/Konferens) increases the left indent so nested
    groups visually read as part of the level above them.
--}}
@props(['label', 'count', 'colspan', 'toggleId', 'ancestors' => null, 'depth' => 0])

@php
    $paddingClass = match ($depth) {
        1 => 'pl-8',
        2 => 'pl-12',
        default => 'pl-4',
    };
@endphp

<tr
    class="cursor-pointer bg-slate-50 hover:bg-slate-100 dark:bg-slate-800/60 dark:hover:bg-slate-800"
    data-group-toggle="{{ $toggleId }}"
    @if ($ancestors) data-group-ancestors="{{ $ancestors }}" @endif
>
    <td colspan="{{ $colspan }}" class="{{ $paddingClass }} pr-4 py-2.5">
        <div class="flex items-center gap-2 font-semibold text-slate-700 dark:text-slate-200">
            <x-icon name="chevron-down" data-group-chevron class="h-4 w-4 shrink-0 -rotate-90 transition-transform" />
            {{ $label }}
            <span class="text-xs font-normal text-slate-400 dark:text-slate-500">({{ $count }})</span>
        </div>
    </td>
</tr>
