{{-- $hint: optional small caption under the value — for a card whose number alone doesn't say
     what it's actually counting/comparing (e.g. "Pertumbuhan minggu ini: +372" on its own never
     says +372 of WHAT, or compared to what), spell that out here instead of leaving it to guesswork. --}}
@props(['href' => null, 'icon', 'label', 'value', 'hint' => null])

@if ($href)
<a {{ $attributes->merge(['href' => $href, 'class' => 'flex items-center gap-4 rounded-2xl border border-black/5 bg-white p-5 shadow-sm transition hover:shadow-md dark:border-white/5 dark:bg-slate-900']) }}>
    <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 shadow-sm dark:bg-blue-950/50 dark:text-blue-300">
        <x-icon :name="$icon" class="h-5 w-5" />
    </span>
    <div class="min-w-0">
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $label }}</p>
        <p class="text-3xl font-bold tabular-nums text-slate-900 dark:text-white">{{ $value }}</p>
        @if ($hint)
            <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">{{ $hint }}</p>
        @endif
    </div>
</a>
@else
{{-- No href: purely informational (no drill-down page exists), so it's not styled as clickable. --}}
<div {{ $attributes->merge(['class' => 'flex items-center gap-4 rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900']) }}>
    <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 shadow-sm dark:bg-blue-950/50 dark:text-blue-300">
        <x-icon :name="$icon" class="h-5 w-5" />
    </span>
    <div class="min-w-0">
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $label }}</p>
        <p class="text-3xl font-bold tabular-nums text-slate-900 dark:text-white">{{ $value }}</p>
        @if ($hint)
            <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">{{ $hint }}</p>
        @endif
    </div>
</div>
@endif
