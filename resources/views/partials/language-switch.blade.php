@php $otherLocale = app()->getLocale() === 'id' ? 'en' : 'id'; @endphp
<a
    href="{{ route('locale.switch', $otherLocale) }}"
    title="{{ __('nav.language') }}"
    class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-black/5 bg-white text-xs font-bold text-slate-600 shadow-sm transition hover:bg-slate-50 dark:border-white/5 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
>
    {{ strtoupper($otherLocale) }}
</a>
