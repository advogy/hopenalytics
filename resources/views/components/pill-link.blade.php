{{-- One pill in a horizontal nav (metric/platform switchers on leaderboard/comparison pages) — active state is solid blue, inactive is a plain bordered pill. --}}
@props(['href', 'active' => false])

<a
    href="{{ $href }}"
    {{ $attributes->merge(['class' => 'rounded-full border px-3 py-1.5 text-sm font-medium transition '.($active ? 'border-blue-600 bg-blue-600 text-white' : 'border-black/10 bg-white text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700')]) }}
>
    {{ $slot }}
</a>
