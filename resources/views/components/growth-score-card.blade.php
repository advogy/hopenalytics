{{--
    $groupedRows/$groupPrefix/$isNasionalView are opt-in — leave them out (as the Dashboard's
    Top5/Bottom5 widgets do) and this renders the same flat list it always has. Passing
    $groupedRows (shaped by BuildsLeaderboards::groupByRegion()) switches to the collapsible
    Uni/Daerah grouping used on Perbandingan Metrik — see partials/growth-score-row.blade.php for
    the shared row markup and partials/analytics-group-toggle.blade.php for the click handling.
--}}
@props(['title', 'subtitle', 'rows', 'viewAllUrl' => null, 'showMetrics' => true, 'groupedRows' => null, 'groupPrefix' => null, 'isNasionalView' => false, 'showDivisionHeader' => false, 'scope' => null])

@php
    $metricLabels = ['reach' => 'Reach', 'views' => 'Views', 'likes' => 'Likes', 'posts' => 'Post / Video'];
@endphp

<div class="min-w-0 rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
    <div class="mb-1 flex flex-wrap items-start justify-between gap-2">
        <p class="min-w-0 flex-1 truncate font-bold text-slate-900 dark:text-white">{{ $title }}</p>
        <div class="flex shrink-0 items-center gap-3">
            @if ($groupedRows !== null)
                <x-group-toggle-all-button :scope="$groupPrefix" />
            @endif
            @if ($viewAllUrl)
                <a href="{{ $viewAllUrl }}" class="text-sm text-blue-600 hover:underline dark:text-blue-400">
                    {{ __('common.view_all') }}
                </a>
            @endif
        </div>
    </div>
    <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>

    @if ($rows->isEmpty())
        <x-empty-state variant="inline">{{ __('common.no_growth_data') }}</x-empty-state>
    @else
        <div class="divide-y divide-slate-100 dark:divide-slate-800">
            @if ($groupedRows !== null)
                @php $chain = fn (...$parts) => trim(implode(' ', array_filter($parts))); @endphp

                @foreach ($groupedRows as $divisionKey => $divisionGroup)
                    @php
                        $divisionId = $groupPrefix.'-division-'.$divisionKey;
                        $divisionChain = $showDivisionHeader ? $divisionId : null;
                    @endphp

                    @if ($showDivisionHeader)
                        <x-analytics-group-header-div
                            :label="$divisionGroup['label']"
                            :count="$divisionGroup['rows']->count() + $divisionGroup['unions']->sum(fn ($u) => $u['rows']->count() + $u['conferences']->sum(fn ($c) => $c['rows']->count()))"
                            :toggle-id="$divisionId"
                        />
                    @endif

                    @foreach ($divisionGroup['rows'] as $i => $row)
                        @include('partials.growth-score-row', [
                            'row' => $row, 'index' => $i, 'depth' => 0,
                            'showMetrics' => $showMetrics, 'metricLabels' => $metricLabels, 'scope' => $scope,
                            'ancestors' => $divisionChain,
                        ])
                    @endforeach

                    @foreach ($divisionGroup['unions'] as $unionKey => $unionGroup)
                        @php
                            $unionId = $divisionId.'-union-'.$unionKey;
                            $unionChain = $isNasionalView ? $chain($divisionChain, $unionId) : $divisionChain;
                        @endphp

                        @if ($isNasionalView)
                            <x-analytics-group-header-div
                                :label="$unionGroup['label']"
                                :count="$unionGroup['conferences']->sum(fn ($c) => $c['rows']->count()) + $unionGroup['rows']->count()"
                                :toggle-id="$unionId"
                                :ancestors="$divisionChain"
                                :depth="$showDivisionHeader ? 1 : 0"
                            />
                        @endif

                        @foreach ($unionGroup['rows'] as $i => $row)
                            @include('partials.growth-score-row', [
                                'row' => $row, 'index' => $i, 'depth' => 1,
                                'showMetrics' => $showMetrics, 'metricLabels' => $metricLabels, 'scope' => $scope,
                                'ancestors' => $unionChain,
                            ])
                        @endforeach

                        @foreach ($unionGroup['conferences'] as $conferenceKey => $conferenceGroup)
                            @php $conferenceId = $unionId.'-conf-'.$conferenceKey; @endphp

                            <x-analytics-group-header-div
                                :label="$conferenceGroup['label']"
                                :count="$conferenceGroup['rows']->count()"
                                :toggle-id="$conferenceId"
                                :ancestors="$unionChain"
                                :depth="($showDivisionHeader ? 1 : 0) + ($isNasionalView ? 1 : 0)"
                            />
                            @foreach ($conferenceGroup['rows'] as $i => $row)
                                @include('partials.growth-score-row', [
                                    'row' => $row, 'index' => $i, 'depth' => 2,
                                    'showMetrics' => $showMetrics, 'metricLabels' => $metricLabels, 'scope' => $scope,
                                    'ancestors' => $chain($unionChain, $conferenceId),
                                ])
                            @endforeach
                        @endforeach
                    @endforeach
                @endforeach
            @else
                @foreach ($rows as $i => $row)
                    @include('partials.growth-score-row', ['row' => $row, 'index' => $i, 'showMetrics' => $showMetrics, 'metricLabels' => $metricLabels, 'scope' => $scope])
                @endforeach
            @endif
        </div>
    @endif
</div>
