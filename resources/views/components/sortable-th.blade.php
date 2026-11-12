{{--
    A table header that sorts the table by its column through the query string (?urut=&arah=),
    keeping every other filter. Clicking the active column flips the direction; another column
    starts from highest first (names from A).
--}}
@props(['column', 'sort', 'direction', 'align' => 'left', 'anchor' => null])

@php
    $isActive = $sort === $column;
    $next = $isActive ? ($direction === 'asc' ? 'desc' : 'asc') : ($column === 'name' ? 'asc' : 'desc');
    $url = request()->fullUrlWithQuery(['urut' => $column, 'arah' => $next]).($anchor ? '#'.$anchor : '');
@endphp

<th
    {{ $attributes->merge(['class' => 'px-4 py-3 font-medium '.($align === 'right' ? 'text-right' : '')]) }}
    @if ($isActive) aria-sort="{{ $direction === 'asc' ? 'ascending' : 'descending' }}" @endif
>
    <a
        href="{{ $url }}"
        @class([
            'inline-flex items-center gap-1 hover:text-blue-600 dark:hover:text-blue-400',
            'flex-row-reverse' => $align === 'right',
            'text-blue-600 dark:text-blue-400' => $isActive,
            'text-slate-500 dark:text-slate-400' => ! $isActive,
        ])
    >
        <span>{{ $slot }}</span>
        <span aria-hidden="true" @class(['text-xs', 'opacity-0' => ! $isActive])>{{ $isActive && $direction === 'asc' ? '▲' : '▼' }}</span>
    </a>
</th>
