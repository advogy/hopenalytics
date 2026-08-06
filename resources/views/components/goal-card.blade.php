@props(['label', 'year', 'target', 'current', 'percent'])

{{--
    Two explicit rows instead of one wrapping flex row: icon + target on top, bar + current
    number together underneath, per the user's explicit call. This also gives the bar far more
    width to grow into at every size, since on the old single-row layout the bar (flex-1) had to
    share leftover space with the icon AND the target column too, not just the current-number
    column — on small/medium screens that left it stuck near its 8rem floor.

    The current-number column keeps a min-width (steps up sm:/lg:) rather than a flat width or
    shrink-of-content sizing — a flat w-28/w-36 was too narrow for a 10-digit
    number_format()'d figure (up to "9,999,999,999") and wrapped it onto two lines; min-w-*
    plus whitespace-nowrap keeps the usual-length figure's column narrow and consistent across
    stacked goal-cards (reach/views/likes/posts), but lets the box grow past its floor — never
    wrapping — on the rare card whose figure actually needs the room, per the user's explicit
    call.
--}}
<div class="min-w-0 rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
    <div class="flex items-center gap-4">
        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 shadow-sm dark:bg-blue-950/50 dark:text-blue-300">
            <x-icon name="flag" class="h-5 w-5" />
        </span>

        <div>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('goals.goal_title', ['year' => $year, 'metric' => $label]) }}</p>
            <p class="text-2xl font-bold tabular-nums text-slate-900 dark:text-white">{{ number_format($target) }}</p>
        </div>
    </div>

    {{-- h-8 matches the target/current numbers' own line height (text-2xl), so the bar reads as
         a peer-sized element in the row instead of a thin sliver dwarfed by the bold numbers. --}}
    <div class="mt-4 flex flex-wrap items-center gap-4">
        <div class="relative h-8 min-w-[8rem] flex-1">
            <div class="absolute inset-0 rounded-full bg-slate-100 dark:bg-slate-800"></div>
            <div
                class="absolute inset-y-0 left-0 flex items-center justify-end rounded-full bg-blue-600 pr-3"
                style="width: max(3.5rem, {{ $percent }}%)"
            >
                <span class="text-sm font-semibold text-white">{{ $percent }}%</span>
            </div>
        </div>

        <p class="mr-2 min-w-28 shrink-0 whitespace-nowrap text-left text-2xl font-bold tabular-nums text-slate-900 dark:text-white sm:min-w-36 lg:min-w-48">{{ number_format($current) }}</p>
    </div>
</div>
