@props(['href', 'icon', 'label', 'value'])

<a {{ $attributes->merge(['href' => $href, 'class' => 'flex items-center gap-4 rounded-2xl border border-black/5 bg-white p-5 shadow-sm transition hover:shadow-md dark:border-white/5 dark:bg-slate-900']) }}>
    <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 shadow-sm dark:bg-blue-950/50 dark:text-blue-300">
        <x-icon :name="$icon" class="h-5 w-5" />
    </span>
    <div>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $label }}</p>
        <p class="text-3xl font-bold tabular-nums text-slate-900 dark:text-white">{{ $value }}</p>
    </div>
</a>
