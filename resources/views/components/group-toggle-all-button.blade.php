{{--
    "Buka Semua"/"Tutup Semua" button for one Data Per */Direktori table's Uni/Daerah groups —
    see partials/analytics-group-toggle.blade.php for the click-handling script this pairs with.
    $scope must match the prefix used before the "-" in that table's :toggle-id values (e.g.
    "organization" for toggle ids like "organization-union-3").
--}}
@props(['scope'])

<button
    type="button"
    data-group-toggle-all="{{ $scope }}"
    data-label-expand="{{ __('common.expand_all') }}"
    data-label-collapse="{{ __('common.collapse_all') }}"
    class="inline-flex items-center gap-1.5 rounded-full border border-black/10 bg-slate-50 px-3 py-1.5 text-sm font-medium text-slate-600 shadow-sm transition hover:bg-slate-100 disabled:cursor-not-allowed dark:border-white/10 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
>
    {{ __('common.expand_all') }}
</button>
