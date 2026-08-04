@props(['label', 'year', 'target', 'current', 'percent'])

{{--
    items-end (not items-center) bottom-aligns every direct child to the same baseline, so the
    target number (the second line of the icon's label block), the progress bar, and the
    current number all land level with each other — items-center left the single-line current
    number vertically centered on the whole (taller, two-line) row instead of level with the
    target number beside it, per the user's explicit call. The icon gets self-center back since
    it has no line of its own to align to.

    The label block and current-number column both get a fixed width (w-44 / w-48) instead of
    shrink-of-content sizing — otherwise a longer/shorter target or current figure on one goal
    row shifts where the bar starts and ends versus another goal row's bar, so across multiple
    goal-cards the bars end up different lengths starting at different x positions. Fixed
    widths make every bar's left edge and length identical across rows, and the current number
    always starts flush-left at the same x, per the user's explicit call. w-48 (12rem) on the
    current number specifically comfortably fits a 10-digit number_format()'d figure (up to
    "9,999,999,999"), per the user's explicit call.
--}}
<div class="flex flex-wrap items-end gap-4 rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900 sm:flex-nowrap">
    <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center self-center rounded-2xl bg-blue-50 text-blue-600 shadow-sm dark:bg-blue-950/50 dark:text-blue-300">
        <x-icon name="flag" class="h-5 w-5" />
    </span>

    <div class="w-44 shrink-0">
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('goals.goal_title', ['year' => $year, 'metric' => $label]) }}</p>
        <p class="text-2xl font-bold tabular-nums text-slate-900 dark:text-white">{{ number_format($target) }}</p>
    </div>

    {{-- h-8 matches the target/current numbers' own line height (text-2xl), so the bar reads as
         a peer-sized element in the row instead of a thin sliver dwarfed by the bold numbers. --}}
    <div class="relative h-8 min-w-[8rem] flex-1">
        <div class="absolute inset-0 rounded-full bg-slate-100 dark:bg-slate-800"></div>
        <div
            class="absolute inset-y-0 left-0 flex items-center justify-end rounded-full bg-blue-600 pr-3"
            style="width: max(3.5rem, {{ $percent }}%)"
        >
            <span class="text-sm font-semibold text-white">{{ $percent }}%</span>
        </div>
    </div>

    <p class="w-48 shrink-0 text-left text-2xl font-bold tabular-nums text-slate-900 dark:text-white">{{ number_format($current) }}</p>
</div>
