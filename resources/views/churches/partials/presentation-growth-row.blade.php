@php
    $metricLabels = \App\Models\AppSetting::filterEnabledMetrics(['reach' => __('common.metric_reach_short'), 'views' => __('common.metric_views'), 'likes' => __('common.metric_likes'), 'posts' => __('common.metric_posts'), 'comments' => __('common.metric_comments'), 'shares' => __('common.metric_shares')]);
    $hasScore = $row['score'] !== null;
@endphp
<div class="flex items-center gap-4 rounded-xl border px-4 py-3 {{ $i === 0 ? 'border-blue-500/40 bg-blue-600/10 ring-1 ring-blue-500/40 dark:bg-blue-600/20' : 'border-black/5 bg-white dark:border-white/5 dark:bg-[#0f1e33]' }} {{ $hasScore ? '' : 'opacity-40' }}">
    <span class="w-6 shrink-0 text-right text-lg font-semibold text-slate-500 dark:text-slate-400">{{ $i + 1 }}</span>

    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#f7cd9a] text-sm font-bold text-blue-600 dark:bg-violet-950/60 dark:text-[#f7cd9a]">
        {{ mb_substr($row['entity']->name, 0, 1) }}
    </span>

    <div class="min-w-0 flex-1">
        <p class="truncate font-medium">{{ $row['entity']->name }}</p>
        @if ($row['entity']->city)
            <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $row['entity']->city }}</p>
        @endif
    </div>

    {{-- lg: (not sm:) and flex-wrap — up to 6 metric badges now (Settings can enable
         Comment/Share alongside Reach/Views/Likes/Post), which no longer reliably fits beside
         the avatar/name/score on a merely-sm-width viewport without wrapping. --}}
    <div class="hidden shrink-0 flex-wrap items-center justify-end gap-3 lg:flex">
        @foreach ($metricLabels as $key => $label)
            @php $value = $row['metrics'][$key] ?? null; @endphp
            <span class="inline-flex items-center gap-1 text-sm {{ $value === null ? 'text-slate-400 dark:text-slate-600' : 'text-slate-700 dark:text-slate-200' }}">
                <span class="text-slate-500 dark:text-slate-500">{{ $label }}</span>
                @if ($value === null)
                    &ndash;
                @else
                    <span class="{{ $value > 0 ? 'text-emerald-600 dark:text-emerald-400' : ($value < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-500 dark:text-slate-400') }}">
                        {{ $value > 0 ? '+' : '' }}{{ number_format($value, 1) }}%
                    </span>
                @endif
            </span>
        @endforeach
    </div>

    <span class="w-20 shrink-0 text-right text-xl font-bold tabular-nums {{ $hasScore && $row['score'] < 0 ? 'text-red-600 dark:text-red-400' : '' }}">
        @if ($hasScore)
            {{ $row['score'] > 0 ? '+' : '' }}{{ number_format($row['score'], 1) }}%
        @else
            &ndash;
        @endif
    </span>
</div>
