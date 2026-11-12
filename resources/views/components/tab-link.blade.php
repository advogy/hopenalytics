{{--
    A tab that opens another page, styled like x-tab-button so both can sit in one x-tab-bar
    (e.g. Kelola Akun's in-page tabs next to its Impor Massal page).
--}}
@props(['href', 'active' => false, 'badge' => 0])

<a
    href="{{ $href }}"
    @if ($active) aria-current="page" @endif
    @class([
        'inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-medium transition',
        'border-blue-600 text-blue-600' => $active,
        'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' => ! $active,
    ])
>
    {{ $slot }}
    @if ($badge > 0)
        <span class="rounded-full bg-amber-100 px-1.5 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-950 dark:text-amber-300">{{ $badge }}</span>
    @endif
</a>
