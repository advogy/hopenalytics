{{-- Single-entity weekly growth score sparkline card — shared by churches/show,
     institutions/show, organizations/show (union/conference), and people/show. Not to be
     confused with growth-score-card.blade.php, which renders a ranked list of many entities
     (the dashboard's Top 5/Bottom 5), not one entity's own trend. $anchored adds the
     #growth-score anchor + scroll offset that churches.show/institutions.show's own stat cards
     link to ("Pertumbuhan Minggu Ini") — people.show has no such stat card pointing here, so it
     stays unanchored. $scoreMetrics is the reach/views/likes/posts breakdown behind the overall
     score — same shape and same per-metric averaging as growth-score-row.blade.php's dashboard
     leaderboard rows (see BuildsLeaderboards::growthScoreHistory()), so this card always shows
     "where the number comes from" consistently with Perbandingan Metrik and the dashboard.
     $scoreBreakdown/$scoreSampleCount/$scoreSampleSum are the underlying per-account samples
     behind $scoreMetrics — the info button opens a dialog walking through the full calculation,
     account by account, down to the final average, so nothing about the score is a black box. --}}
@props(['scoreHistory' => [], 'scoreMetrics' => [], 'scoreBreakdown' => [], 'scoreSampleCount' => 0, 'scoreSampleSum' => 0, 'anchored' => false])

@php
    $metricLabels = ['reach' => 'Reach', 'views' => 'Views', 'likes' => 'Likes', 'posts' => 'Post / Video'];
    $dialogId = 'growth-score-detail-'.\Illuminate\Support\Str::random(8);
@endphp

<div
    @if ($anchored) id="growth-score" @endif
    class="mb-8 rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900 {{ $anchored ? 'scroll-mt-20' : '' }}"
>
    @if (empty($scoreHistory))
        <p class="font-bold text-slate-900 dark:text-white">{{ __('entity.growth_score_title') }}</p>
        <p class="mt-1 text-sm text-slate-400 dark:text-slate-500">{{ __('entity.growth_score_no_data') }}</p>
    @else
        @php $currentScore = end($scoreHistory); @endphp
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="flex items-center gap-1.5 font-bold text-slate-900 dark:text-white">
                    {{ __('entity.growth_score_title') }}
                    <button
                        type="button"
                        data-growth-score-detail-trigger="{{ $dialogId }}"
                        title="{{ __('entity.growth_score_detail_trigger') }}"
                        aria-label="{{ __('entity.growth_score_detail_trigger') }}"
                        class="inline-flex h-5 w-5 cursor-pointer items-center justify-center rounded-full text-blue-600 transition hover:bg-slate-100 dark:text-blue-400 dark:hover:bg-slate-800"
                    >
                        <x-icon name="arrow-trending-up" class="h-4 w-4" />
                    </button>
                </p>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('entity.growth_score_subtitle') }}</p>
            </div>
            <div class="flex shrink-0 items-center gap-4">
                <x-sparkline :values="$scoreHistory" class="h-8 w-28" />
                <button
                    type="button"
                    data-growth-score-detail-trigger="{{ $dialogId }}"
                    title="{{ __('entity.growth_score_detail_trigger') }}"
                    class="cursor-pointer text-2xl font-bold tabular-nums transition hover:opacity-70 {{ $currentScore > 0 ? 'text-emerald-600 dark:text-emerald-400' : ($currentScore < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-400 dark:text-slate-500') }}"
                >
                    {{ $currentScore > 0 ? '+' : '' }}{{ number_format($currentScore, 1) }}%
                </button>
            </div>
        </div>

        @if (! empty(array_filter($scoreMetrics, fn ($v) => $v !== null)))
            <div class="mt-4 flex flex-wrap items-center justify-between gap-x-5 gap-y-2 border-t border-slate-100 pt-4 text-sm dark:border-slate-800">
                <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
                    @foreach ($metricLabels as $key => $label)
                        @php $value = $scoreMetrics[$key] ?? null; @endphp
                        <button
                            type="button"
                            data-growth-score-detail-trigger="{{ $dialogId }}"
                            title="{{ __('entity.growth_score_detail_trigger') }}"
                            class="inline-flex cursor-pointer items-center gap-1 transition hover:opacity-70"
                        >
                            <span class="text-slate-400 dark:text-slate-500">{{ $label }}</span>
                            @if ($value === null)
                                <span class="text-slate-300 dark:text-slate-600">&ndash;</span>
                            @else
                                <span class="font-medium {{ $value > 0 ? 'text-emerald-600 dark:text-emerald-400' : ($value < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-400 dark:text-slate-500') }}">
                                    {{ $value > 0 ? '+' : '' }}{{ number_format($value, 1) }}%
                                </span>
                            @endif
                        </button>
                    @endforeach
                </div>

                <a
                    href="{{ route('about') }}#cara-hitung-skor"
                    class="inline-flex shrink-0 items-center gap-1 text-xs font-medium text-blue-600 hover:underline dark:text-blue-400"
                >
                    {{ __('entity.growth_score_learn_more') }}
                    <x-icon name="question-mark-circle" class="h-3 w-3" />
                </a>
            </div>
        @endif

        @if (! empty($scoreBreakdown))
            <dialog id="{{ $dialogId }}" data-growth-score-detail-dialog class="bg-white dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                    <p class="font-medium text-slate-900 dark:text-white">{{ __('entity.growth_score_detail_title') }}</p>
                    <button
                        type="button"
                        data-growth-score-detail-close
                        aria-label="{{ __('entity.growth_score_detail_close') }}"
                        class="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800"
                    >
                        <x-icon name="x-circle" class="h-5 w-5" />
                    </button>
                </div>

                <div class="max-h-[70vh] overflow-y-auto p-6">
                    <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">{{ __('entity.growth_score_detail_intro') }}</p>

                    {{-- Same averages, same markup as the card above — so what's shown here is
                         visibly the same numbers, not a second, differently-styled summary. --}}
                    <div class="mb-5 flex flex-wrap items-center gap-x-5 gap-y-2 rounded-xl bg-slate-50 p-4 text-sm dark:bg-slate-800/60">
                        @foreach ($metricLabels as $key => $label)
                            @php $value = $scoreMetrics[$key] ?? null; @endphp
                            <span class="inline-flex items-center gap-1">
                                <span class="text-slate-400 dark:text-slate-500">{{ $label }}</span>
                                @if ($value === null)
                                    <span class="text-slate-300 dark:text-slate-600">&ndash;</span>
                                @else
                                    <span class="font-medium {{ $value > 0 ? 'text-emerald-600 dark:text-emerald-400' : ($value < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-400 dark:text-slate-500') }}">
                                        {{ $value > 0 ? '+' : '' }}{{ number_format($value, 1) }}%
                                    </span>
                                @endif
                            </span>
                        @endforeach
                    </div>

                    @foreach ($metricLabels as $key => $label)
                        @continue (empty($scoreBreakdown[$key]))
                        <div class="mb-5 last:mb-0">
                            <p class="mb-2 text-sm font-semibold text-slate-900 dark:text-white">{{ $label }}</p>
                            <div class="overflow-x-auto rounded-xl border border-slate-100 dark:border-slate-800">
                                <table class="w-full text-left text-sm">
                                    <thead>
                                        <tr class="border-b border-slate-100 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                                            <th class="px-3 py-2 font-medium">{{ __('entity.col_account') }}</th>
                                            <th class="px-3 py-2 text-right font-medium">{{ __('entity.growth_score_detail_previous') }}</th>
                                            <th class="px-3 py-2 text-right font-medium">{{ __('entity.growth_score_detail_current') }}</th>
                                            <th class="px-3 py-2 text-right font-medium">{{ __('entity.growth_score_detail_change') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                        @foreach ($scoreBreakdown[$key] as $sample)
                                            <tr>
                                                <td class="px-3 py-2 text-slate-700 dark:text-slate-200">{{ $sample['label'] }}</td>
                                                <td class="px-3 py-2 text-right tabular-nums text-slate-500 dark:text-slate-400">{{ number_format($sample['previous']) }}</td>
                                                <td class="px-3 py-2 text-right tabular-nums text-slate-500 dark:text-slate-400">{{ number_format($sample['current']) }}</td>
                                                <td class="px-3 py-2 text-right tabular-nums font-medium {{ $sample['percent'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : ($sample['percent'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-400 dark:text-slate-500') }}">
                                                    {{ $sample['percent'] > 0 ? '+' : '' }}{{ number_format($sample['percent'], 2) }}%
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach

                    <div class="mt-6 rounded-xl bg-slate-50 p-4 text-sm dark:bg-slate-800/60">
                        <p class="mb-2 font-semibold text-slate-900 dark:text-white">{{ __('entity.growth_score_detail_formula_title') }}</p>
                        <dl class="space-y-1">
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-slate-500 dark:text-slate-400">{{ __('entity.growth_score_detail_total_samples') }}</dt>
                                <dd class="tabular-nums font-medium">{{ $scoreSampleCount }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-slate-500 dark:text-slate-400">{{ __('entity.growth_score_detail_sum') }}</dt>
                                <dd class="tabular-nums font-medium">{{ $scoreSampleSum > 0 ? '+' : '' }}{{ number_format($scoreSampleSum, 2) }}%</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3 border-t border-slate-200 pt-1 dark:border-slate-700">
                                <dt class="font-medium text-slate-700 dark:text-slate-200">{{ __('entity.growth_score_detail_final') }}</dt>
                                <dd class="tabular-nums text-base font-bold {{ $currentScore > 0 ? 'text-emerald-600 dark:text-emerald-400' : ($currentScore < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-400 dark:text-slate-500') }}">
                                    {{ $currentScore > 0 ? '+' : '' }}{{ number_format($currentScore, 1) }}%
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </dialog>

            <style>
                dialog[data-growth-score-detail-dialog] {
                    position: fixed;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    margin: 0;
                    padding: 0;
                    border: none;
                    border-radius: 1rem;
                    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
                    max-width: 40rem;
                    width: calc(100% - 2rem);
                    max-height: calc(100vh - 4rem);
                }
                dialog[data-growth-score-detail-dialog]::backdrop {
                    background: rgba(15, 23, 42, 0.4);
                    backdrop-filter: blur(2px);
                }
            </style>

            <script>
                // Delegated on document (not bound per-instance) so this stays a no-op if this
                // component is ever included more than once on the same page — every trigger
                // just opens whichever dialog its own data attribute names.
                if (! window.__growthScoreDetailBound) {
                    window.__growthScoreDetailBound = true;

                    document.addEventListener('click', function (e) {
                        var trigger = e.target.closest('[data-growth-score-detail-trigger]');
                        if (trigger) {
                            var dialog = document.getElementById(trigger.getAttribute('data-growth-score-detail-trigger'));
                            if (dialog) dialog.showModal();
                            return;
                        }

                        var closeBtn = e.target.closest('[data-growth-score-detail-close]');
                        if (closeBtn) {
                            closeBtn.closest('dialog').close();
                        }
                    });
                }
            </script>
        @endif
    @endif
</div>
