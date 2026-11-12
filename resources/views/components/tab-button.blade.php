{{-- One button inside x-tab-bar. $tabKey matches the data-tab-panel it activates (see partials/tab-script.blade.php), which also sets aria-selected on the active one. --}}
@aware(['variant' => 'underline'])
@props(['tabKey', 'note' => null])

<button
    type="button"
    role="tab"
    data-tab-button="{{ $tabKey }}"
    @if ($note) data-tab-note="{{ $note }}" @endif
    @class([
        'border-b-2 px-4 py-2.5 text-sm font-medium transition' => $variant !== 'segmented',
        'shrink-0 whitespace-nowrap rounded-md px-3 py-1.5 font-medium text-slate-500 transition aria-selected:bg-blue-600 aria-selected:text-white aria-[selected=false]:hover:text-slate-700 dark:text-slate-400 dark:aria-[selected=false]:hover:text-slate-200' => $variant === 'segmented',
    ])
>
    {{ $slot }}
</button>
