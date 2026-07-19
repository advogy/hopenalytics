@props(['url'])

<a
    href="{{ $url }}"
    data-export-trigger
    {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-lg border border-black/10 bg-white px-4 py-2 text-sm font-medium shadow-sm transition hover:bg-slate-50 dark:border-white/10 dark:bg-slate-800 dark:hover:bg-slate-700']) }}
>
    <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
        <path fill-rule="evenodd" d="M10 12.5a.75.75 0 0 1-.53-.22l-3-3a.75.75 0 1 1 1.06-1.06l1.72 1.72V3a.75.75 0 0 1 1.5 0v6.94l1.72-1.72a.75.75 0 1 1 1.06 1.06l-3 3a.75.75 0 0 1-.53.22ZM3.5 12.75a.75.75 0 0 1 1.5 0v2.5c0 .414.336.75.75.75h8.5a.75.75 0 0 0 .75-.75v-2.5a.75.75 0 0 1 1.5 0v2.5A2.25 2.25 0 0 1 14.25 17.5h-8.5A2.25 2.25 0 0 1 3.5 15.25v-2.5Z" clip-rule="evenodd"/>
    </svg>
    {{ $slot->isEmpty() ? __('common.export') : $slot }}
</a>
