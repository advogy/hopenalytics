@props(['title', 'subtitle', 'rows', 'platformLabels', 'scope', 'viewAllUrl' => null, 'showMetrics' => true])

@php
    $metricLabels = ['reach' => 'Reach', 'views' => 'Views', 'likes' => 'Likes', 'posts' => 'Post / Video'];
@endphp

<div class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
    <div class="mb-1 flex items-start justify-between gap-2">
        <p class="font-bold text-slate-900 dark:text-white">{{ $title }}</p>
        @if ($viewAllUrl)
            <a href="{{ $viewAllUrl }}" class="shrink-0 text-sm text-blue-600 hover:underline dark:text-blue-400">
                {{ __('common.view_all') }}
            </a>
        @endif
    </div>
    <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>

    @if ($rows->isEmpty())
        <p class="py-6 text-center text-sm text-slate-400 dark:text-slate-500">
            {{ __('common.no_growth_data') }}
        </p>
    @else
        <div class="divide-y divide-slate-100 dark:divide-slate-800">
            @foreach ($rows as $i => $row)
                @php $platform = $row['platform']; $score = $row['score']; @endphp
                <div class="flex items-center gap-4 py-3 first:pt-0 last:pb-0">
                    <span class="w-6 shrink-0 text-right text-sm font-semibold text-slate-400 dark:text-slate-500">{{ $i + 1 }}</span>

                    <x-platform-icon :platform="$platform" class="h-9 w-9 shrink-0" />

                    <div class="min-w-0 flex-1">
                        <a
                            href="{{ $scope->platformComparisonUrl(['platform' => $platform]) }}"
                            class="block truncate font-medium hover:text-blue-600 dark:hover:text-blue-400"
                        >
                            {{ $platformLabels[$platform] }}
                        </a>
                        <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                            {{ __('comparison.accounts_count', ['count' => $row['accountCount']]) }}
                        </p>
                    </div>

                    @if ($showMetrics)
                        <div class="hidden shrink-0 items-center gap-3 text-sm sm:flex">
                            @foreach ($metricLabels as $key => $label)
                                @php $value = $row['metrics'][$key] ?? null; @endphp
                                <span class="inline-flex items-center gap-1">
                                    <span class="text-slate-400 dark:text-slate-500">{{ $label }}</span>
                                    @if ($value === null)
                                        <span class="text-slate-300 dark:text-slate-600">&ndash;</span>
                                    @else
                                        <span class="{{ $value > 0 ? 'text-emerald-600 dark:text-emerald-400' : ($value < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-400 dark:text-slate-500') }}">
                                            {{ $value > 0 ? '+' : '' }}{{ number_format($value, 1) }}%
                                        </span>
                                    @endif
                                </span>
                            @endforeach
                        </div>
                    @endif

                    @if ($score === null)
                        <span class="w-20 shrink-0 text-right text-xs text-slate-400 dark:text-slate-500">
                            {{ __('common.no_data_yet') }}
                        </span>
                    @else
                        <span class="w-20 shrink-0 text-right text-lg font-bold tabular-nums {{ $score > 0 ? 'text-emerald-600 dark:text-emerald-400' : ($score < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-400 dark:text-slate-500') }}">
                            {{ $score > 0 ? '+' : '' }}{{ number_format($score, 1) }}%
                        </span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
