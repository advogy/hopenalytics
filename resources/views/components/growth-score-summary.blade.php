{{-- Single-entity weekly growth score sparkline card — shared by churches/show,
     institutions/show, and people/show. Not to be confused with growth-score-card.blade.php,
     which renders a ranked list of many entities (the dashboard's Top 5/Bottom 5), not one
     entity's own trend. $anchored adds the #growth-score anchor + scroll offset that
     churches.show/institutions.show's own stat cards link to ("Pertumbuhan Minggu Ini") —
     people.show has no such stat card pointing here, so it stays unanchored. --}}
@props(['scoreHistory' => [], 'anchored' => false])

<div
    @if ($anchored) id="growth-score" @endif
    class="mb-8 rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900 {{ $anchored ? 'scroll-mt-20' : '' }}"
>
    @if (empty($scoreHistory))
        <p class="font-bold text-slate-900 dark:text-white">{{ __('entity.growth_score_title') }}</p>
        <p class="mt-1 text-sm text-slate-400 dark:text-slate-500">{{ __('entity.growth_score_no_data') }}</p>
    @else
        @php $currentScore = end($scoreHistory); @endphp
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="font-bold text-slate-900 dark:text-white">{{ __('entity.growth_score_title') }}</p>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('entity.growth_score_subtitle') }}</p>
            </div>
            <div class="flex shrink-0 items-center gap-4">
                <x-sparkline :values="$scoreHistory" class="h-8 w-28" />
                <span class="text-2xl font-bold tabular-nums {{ $currentScore > 0 ? 'text-emerald-600 dark:text-emerald-400' : ($currentScore < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-400 dark:text-slate-500') }}">
                    {{ $currentScore > 0 ? '+' : '' }}{{ number_format($currentScore, 1) }}%
                </span>
            </div>
        </div>
    @endif
</div>
