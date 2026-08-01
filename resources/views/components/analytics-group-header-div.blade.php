{{--
    Div-based counterpart to components/analytics-group-row.blade.php (which is <tr>-based, for
    table layouts) — same data-group-toggle/data-group-ancestors/data-group-chevron conventions,
    so partials/analytics-group-toggle.blade.php's script drives both without modification. Used
    by div-list layouts like components/growth-score-card.blade.php. $depth (0 = Uni, 1 = Daerah/
    Konferens) increases the left indent so nested groups visually read as part of the level above.
--}}
@props(['label', 'count', 'toggleId', 'ancestors' => null, 'depth' => 0])

@php
    $paddingClass = match ($depth) {
        1 => 'pl-6',
        2 => 'pl-10',
        default => 'pl-3',
    };
@endphp

<div
    class="flex cursor-pointer items-center gap-2 rounded-lg bg-slate-50 {{ $paddingClass }} pr-3 py-2 text-sm font-semibold text-slate-700 dark:bg-slate-800/60 dark:text-slate-200"
    data-group-toggle="{{ $toggleId }}"
    @if ($ancestors) data-group-ancestors="{{ $ancestors }}" @endif
>
    <x-icon name="chevron-down" data-group-chevron class="h-4 w-4 shrink-0 -rotate-90 transition-transform" />
    {{ $label }}
    <span class="text-xs font-normal text-slate-400 dark:text-slate-500">({{ $count }})</span>
</div>
