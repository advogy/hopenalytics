{{--
    Kelola Akun's per-tab filter form: a GET form inside x-filter-card with a hidden tab field,
    an optional region-filter slot (rendered BEFORE the search field — analytics-entity-filter/
    analytics-region-filter includes, when a tab has one), a search input, and a sort select.
    Ends with a sr-only submit button so Enter reliably submits a form with 2+ text-type fields
    (no defined "Enter submits" behavior otherwise — see the comment this replaced).

    $sortOptions: ordered [value => label] pairs for the sort <select>.
    $formId: only needed when a region-filter partial submits this form via JS on pick (see
    partials/analytics-entity-filter.blade.php's onChange) — omitted tabs (e.g. Divisi, which has
    no region filter at all) don't need one.
--}}
@props([
    'tab',
    'activeTab',
    'hasFilters',
    'searchName',
    'searchValue',
    'searchPlaceholder',
    'sortName',
    'sortValue',
    'sortOptions',
    'formId' => null,
])

@php
    $filterActiveClass = 'border-blue-600 bg-blue-50 text-blue-900 dark:border-blue-500 dark:bg-blue-950/40 dark:text-blue-200';
    $filterInactiveClass = 'border-black/10 bg-slate-50 text-slate-700 hover:bg-slate-100 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700';
@endphp

<x-filter-card :clear-url="$hasFilters ? route('admin.accounts.index', ['tab' => $tab]) : null">
    <form method="GET" @if ($formId) id="{{ $formId }}" @endif class="flex flex-wrap items-stretch gap-3">
        <input type="hidden" name="tab" data-tab-hidden-field value="{{ $activeTab }}">

        {{ $slot }}

        <label class="relative flex-1 min-w-[200px]">
            <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
                type="search"
                name="{{ $searchName }}"
                value="{{ $searchValue }}"
                placeholder="{{ $searchPlaceholder }}"
                class="w-full rounded-full border py-2.5 pr-4 pl-9 text-sm font-medium shadow-sm transition placeholder:font-normal placeholder:text-slate-400 focus:bg-white focus:outline-none dark:placeholder:text-slate-500 dark:focus:bg-slate-800 {{ $searchValue !== '' ? $filterActiveClass : $filterInactiveClass }}"
            >
        </label>
        <label class="relative flex-1 min-w-[180px]">
            <select
                name="{{ $sortName }}"
                onchange="this.form.submit()"
                class="w-full appearance-none rounded-full border py-2.5 pr-10 pl-4 text-sm font-medium shadow-sm focus:border-blue-500 focus:outline-none {{ $sortValue !== 'name_asc' ? $filterActiveClass : $filterInactiveClass }}"
            >
                @foreach ($sortOptions as $value => $label)
                    <option value="{{ $value }}" @selected($sortValue === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
        </label>
        {{-- Gives the browser an explicit default button so pressing Enter in any of this
             form's text fields reliably submits it — without one, a form with more than one
             text-type field has no defined "Enter submits" behavior at all. --}}
        <button type="submit" class="sr-only">{{ __('common.search') }}</button>
    </form>
</x-filter-card>
