{{-- The "Semua Kategori / Akun Gereja / Akun Umum" filter pill — church-scope pages only (gereja vs umum accounts), submits its enclosing form on change. --}}
@props(['selectedCategory' => null])

<label class="relative">
    <x-icon name="building-office" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
    <select
        name="category"
        onchange="this.form.submit()"
        class="appearance-none rounded-full border border-black/10 bg-slate-50 py-2.5 pr-10 pl-9 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-100 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
    >
        <option value="">{{ __('common.all_categories') }}</option>
        <option value="gereja" @selected($selectedCategory === 'gereja')>{{ __('directory.church_accounts') }}</option>
        <option value="umum" @selected($selectedCategory === 'umum')>{{ __('directory.general_accounts') }}</option>
    </select>
    <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
</label>
