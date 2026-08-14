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
                @if ($showDivisionHeader)
                    {{-- preserveKeys: true — see components/grouped-rows.blade.php's identical note. --}}
                    @php $divisionGroups = $groupedRows->groupBy('divisionId', true)->sortBy(fn ($unions) => $unions->first()['divisionName']); @endphp

                    @foreach ($divisionGroups as $divisionKey => $unionsInDivision)
                        @php
                            $divisionAncestors = $groupPrefix.'-division-'.$divisionKey;
                            $divisionCount = $unionsInDivision->sum(fn ($u) => $u['rows']->count() + $u['conferences']->sum(fn ($c) => $c['rows']->count()));
                        @endphp

                        <x-analytics-group-header-div
                            :label="$unionsInDivision->first()['divisionName']"
                            :count="$divisionCount"
                            :toggle-id="$divisionAncestors"
                        />

                        @foreach ($unionsInDivision as $unionKey => $unionGroup)
                            @php $unionAncestors = $divisionAncestors.'-union-'.$unionKey; @endphp

                            <x-analytics-group-header-div
                                :label="$unionGroup['label']"
                                :count="$unionGroup['conferences']->sum(fn ($c) => $c['rows']->count()) + $unionGroup['rows']->count()"
                                :toggle-id="$unionAncestors"
                                :ancestors="$divisionAncestors"
                                :depth="1"
                            />

                            @foreach ($unionGroup['rows'] as $i => $row)
                                @include('partials.growth-score-row', [
                                    'row' => $row,
                                    'index' => $i,
                                    'depth' => 1,
                                    'showMetrics' => $showMetrics,
                                    'metricLabels' => $metricLabels,
                                    'scope' => $scope,
                                    'ancestors' => trim($divisionAncestors.' '.$unionAncestors),
                                ])
                            @endforeach

                            @foreach ($unionGroup['conferences'] as $conferenceKey => $conferenceGroup)
                                @php $conferenceAncestors = $unionAncestors.'-conf-'.$conferenceKey; @endphp
                                @php $conferenceParentChain = trim($divisionAncestors.' '.$unionAncestors); @endphp

                                <x-analytics-group-header-div
                                    :label="$conferenceGroup['label']"
                                    :count="$conferenceGroup['rows']->count()"
                                    :toggle-id="$conferenceAncestors"
                                    :ancestors="$conferenceParentChain"
                                    :depth="2"
                                />
                                @foreach ($conferenceGroup['rows'] as $i => $row)
                                    @include('partials.growth-score-row', [
                                        'row' => $row,
                                        'index' => $i,
                                        'depth' => 2,
                                        'showMetrics' => $showMetrics,
                                        'metricLabels' => $metricLabels,
                                        'scope' => $scope,
                                        'ancestors' => trim($conferenceParentChain.' '.$conferenceAncestors),
                                    ])
                                @endforeach
                            @endforeach
                        @endforeach
                    @endforeach
                @else
                    @foreach ($groupedRows as $unionKey => $unionGroup)
                        @if ($isNasionalView)
                            <x-analytics-group-header-div
                                :label="$unionGroup['label']"
                                :count="$unionGroup['conferences']->sum(fn ($c) => $c['rows']->count()) + $unionGroup['rows']->count()"
                                :toggle-id="$groupPrefix.'-union-'.$unionKey"
                            />
                        @endif
                        @foreach ($unionGroup['rows'] as $i => $row)
                            @include('partials.growth-score-row', [
                                'row' => $row,
                                'index' => $i,
                                'depth' => 1,
                                'showMetrics' => $showMetrics,
                                'metricLabels' => $metricLabels,
                                'scope' => $scope,
                                'ancestors' => $isNasionalView ? $groupPrefix.'-union-'.$unionKey : null,
                            ])
                        @endforeach
                        @foreach ($unionGroup['conferences'] as $conferenceKey => $conferenceGroup)
                            <x-analytics-group-header-div
                                :label="$conferenceGroup['label']"
                                :count="$conferenceGroup['rows']->count()"
                                :toggle-id="$groupPrefix.'-conf-'.$unionKey.'-'.$conferenceKey"
                                :ancestors="$isNasionalView ? $groupPrefix.'-union-'.$unionKey : null"
                                :depth="1"
                            />
                            @foreach ($conferenceGroup['rows'] as $i => $row)
                                @include('partials.growth-score-row', [
                                    'row' => $row,
                                    'index' => $i,
                                    'depth' => 2,
                                    'showMetrics' => $showMetrics,
                                    'metricLabels' => $metricLabels,
                                    'scope' => $scope,
                                    'ancestors' => ($isNasionalView ? $groupPrefix.'-union-'.$unionKey.' ' : '').$groupPrefix.'-conf-'.$unionKey.'-'.$conferenceKey,
                                ])
                            @endforeach
                        @endforeach
                    @endforeach
                @endif
            @else
                @foreach ($rows as $i => $row)
                    @include('partials.growth-score-row', ['row' => $row, 'index' => $i, 'showMetrics' => $showMetrics, 'metricLabels' => $metricLabels, 'scope' => $scope])
                @endforeach
            @endif
        </div>
    @endif
</div>
