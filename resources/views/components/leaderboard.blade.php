{{--
    $groupedRows/$groupPrefix/$isNasionalView are opt-in — leave them out and this renders the
    same flat table it always has (used as-is by other leaderboard pages). Passing $groupedRows
    (shaped by BuildsLeaderboards::groupByRegion()) switches to the collapsible Uni/Daerah
    grouping used on the per-metric leaderboard pages — see partials/leaderboard-row.blade.php
    for the shared row markup and components/analytics-group-row.blade.php + partials/analytics-
    group-toggle.blade.php for the group headers/click handling.
--}}
@props(['title', 'subtitle', 'rows', 'valueLabel', 'viewAllUrl' => null, 'exportUrl' => null, 'nameLabel' => null, 'groupedRows' => null, 'groupPrefix' => null, 'isNasionalView' => false])

@php $nameLabel ??= __('common.name'); @endphp

<div class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
    <div class="mb-1 flex items-start justify-between gap-2">
        <p class="font-bold text-slate-900 dark:text-white">{{ $title }}</p>
        <div class="flex shrink-0 items-center gap-3 text-sm">
            @if ($groupedRows !== null)
                <x-group-toggle-all-button :scope="$groupPrefix" />
            @endif
            @if ($exportUrl)
                <a href="{{ $exportUrl }}" data-export-trigger class="text-blue-600 hover:underline dark:text-blue-400">
                    {{ __('common.export') }}
                </a>
            @endif
            @if ($viewAllUrl)
                <a href="{{ $viewAllUrl }}" class="text-blue-600 hover:underline dark:text-blue-400">
                    {{ __('common.view_all') }}
                </a>
            @endif
        </div>
    </div>
    <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>

    @if ($rows->isEmpty())
        <p class="py-6 text-center text-sm text-slate-400 dark:text-slate-500">
            {{ __('common.no_growth_data') }}
        </p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="text-slate-500 dark:text-slate-400">
                        <th class="py-2 pr-2 font-medium">#</th>
                        <th class="py-2 pr-2 font-medium">{{ $nameLabel }}</th>
                        <th class="py-2 pr-2 font-medium">{{ __('common.account') }}</th>
                        <th class="py-2 pr-2 text-right font-medium">{{ __('comparison.growth') }}</th>
                        <th class="py-2 text-right font-medium">{{ $valueLabel }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @if ($groupedRows !== null)
                        @foreach ($groupedRows as $unionKey => $unionGroup)
                            @if ($isNasionalView)
                                <x-analytics-group-row
                                    :label="$unionGroup['label']"
                                    :count="$unionGroup['conferences']->sum(fn ($c) => $c['rows']->count()) + $unionGroup['rows']->count()"
                                    :colspan="5"
                                    :toggle-id="$groupPrefix.'-union-'.$unionKey"
                                />
                            @endif
                            @foreach ($unionGroup['rows'] as $i => $row)
                                @include('partials.leaderboard-row', [
                                    'row' => $row,
                                    'index' => $i,
                                    'depth' => 1,
                                    'ancestors' => $isNasionalView ? $groupPrefix.'-union-'.$unionKey : null,
                                ])
                            @endforeach
                            @foreach ($unionGroup['conferences'] as $conferenceKey => $conferenceGroup)
                                <x-analytics-group-row
                                    :label="$conferenceGroup['label']"
                                    :count="$conferenceGroup['rows']->count()"
                                    :colspan="5"
                                    :toggle-id="$groupPrefix.'-conf-'.$unionKey.'-'.$conferenceKey"
                                    :ancestors="$isNasionalView ? $groupPrefix.'-union-'.$unionKey : null"
                                    :depth="1"
                                />
                                @foreach ($conferenceGroup['rows'] as $i => $row)
                                    @include('partials.leaderboard-row', [
                                        'row' => $row,
                                        'index' => $i,
                                        'depth' => 2,
                                        'ancestors' => ($isNasionalView ? $groupPrefix.'-union-'.$unionKey.' ' : '').$groupPrefix.'-conf-'.$unionKey.'-'.$conferenceKey,
                                    ])
                                @endforeach
                            @endforeach
                        @endforeach
                    @else
                        @foreach ($rows as $i => $row)
                            @include('partials.leaderboard-row', ['row' => $row, 'index' => $i])
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>
    @endif
</div>
