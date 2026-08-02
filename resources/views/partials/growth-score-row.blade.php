{{--
    One row of components/growth-score-card.blade.php's list — extracted so both its flat and
    grouped rendering branches share the same markup instead of duplicating it.

    Expected: $row, $index (0-based position — restarts per Uni/Daerah group when grouped, same
    as an ungrouped list numbering 1..N), $showMetrics, $metricLabels, $ancestors (optional),
    $scope (optional — omitted by the Dashboard's Top5/Bottom5 widgets, which are always
    church-scoped; passed by metric-comparison.blade.php, which can be any of the four scopes —
    see ComparisonScope::showUrl()).
--}}
@php
    $entity = $row['entity'];
    $score = $row['score'];
    $entityUrl = isset($scope) && $scope
        ? $scope->showUrl($entity)
        : ($entity instanceof \App\Models\Church ? route('churches.show', $entity) : route('people.show', $entity));
    $indentClass = match ($depth ?? 0) {
        1 => 'pl-6',
        2 => 'pl-10',
        default => '',
    };
@endphp
<div
    class="flex items-center gap-4 py-3 first:pt-0 last:pb-0 {{ $indentClass }}"
    @if ($ancestors ?? null) data-group-ancestors="{{ $ancestors }}" @endif
>
    <span class="w-6 shrink-0 text-right text-sm font-semibold text-slate-400 dark:text-slate-500">{{ $index + 1 }}</span>

    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#f7cd9a] text-sm font-bold text-blue-600 dark:bg-violet-950/60 dark:text-[#f7cd9a]">
        {{ mb_substr($entity->name, 0, 1) }}
    </span>

    <div class="min-w-0 flex-1">
        @if ($entityUrl)
            <a href="{{ $entityUrl }}" class="block truncate font-medium hover:text-blue-600 dark:hover:text-blue-400">
                {{ $entity->name }}
            </a>
        @else
            <p class="truncate font-medium">{{ $entity->name }}</p>
        @endif
        <p class="truncate text-xs text-slate-500 dark:text-slate-400">
            @if ($entity->city)
                {{ $entity->city }} &middot;
            @endif
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

    <span class="w-20 shrink-0 text-right text-lg font-bold tabular-nums {{ $score > 0 ? 'text-emerald-600 dark:text-emerald-400' : ($score < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-400 dark:text-slate-500') }}">
        {{ $score > 0 ? '+' : '' }}{{ number_format($score, 1) }}%
    </span>
</div>
