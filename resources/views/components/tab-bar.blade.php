{{--
    Wrapper for a row of x-tab-button's — pairs with partials/tab-script.blade.php's
    data-tab-button/data-tab-panel JS. A tab-button's note="..." shows under the bar while that
    tab is active (see components/tab-note).

    Which style, per the user's call: underlined tabs (the default) move between sections of a
    page (Analitik & Grafik, Pengaturan, Kelola Akun); variant="segmented" — one pill-shaped
    button group like the dashboard's Gereja · Personal · Gabungan switch — only re-cuts the
    same data, with 2-4 short options.
--}}
@props(['variant' => 'underline'])

<div data-tab-group>
    @if ($variant === 'segmented')
        <div class="mb-6 max-w-full overflow-x-auto">
            <div role="tablist" class="inline-flex rounded-lg border border-black/10 bg-white p-0.5 text-sm shadow-sm dark:border-white/10 dark:bg-slate-900">
                {{ $slot }}
            </div>
        </div>
    @else
        <div role="tablist" class="mb-6 flex gap-2 overflow-x-auto border-b border-black/5 dark:border-white/5">
            {{ $slot }}
        </div>
    @endif

    <x-tab-note live />
</div>
