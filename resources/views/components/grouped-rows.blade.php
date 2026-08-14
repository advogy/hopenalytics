{{--
    (Divisi >) Union > Conference > row nested table body — the "Direktori Akun" tabs' shared
    grouping shape (Organisasi/Gereja/Institusi/Personal all group the same way). Renders
    <x-analytics-group-row> toggle headers plus each entity row via the caller's own row partial,
    building up the toggle-id/ancestors chain so collapsing a Uni also collapses every Daerah
    nested under it (see x-group-toggle-all-button / partials/directory-*-row.blade.php's own
    data-ancestors consumer in the page's inline collapse script).

    Props:
    - grouped: the $groupedX collection — unionKey => ['label' => ..., 'divisionId' => ..., 'divisionName' => ..., 'rows' => ..., 'conferences' => [confKey => ['label' => ..., 'rows' => ...]]]
      (divisionId/divisionName come from BuildsLeaderboards::groupByRegion(), always present even
      when showDivisionHeader is off — this component just ignores them in that case)
    - prefix: toggle-id/ancestors namespace, e.g. 'directory-organization' (must be unique per table on the page)
    - colspan: passed straight through to <x-analytics-group-row>
    - rowView: the row partial's view name, e.g. 'partials.directory-organization-row'
    - rowKey: the variable name that partial expects its entity under, e.g. 'organization'
    - rowExtra: any additional fixed data merged into every row-partial include (e.g. a shared maxReach)
    - showUnionHeader: false skips the top-level Uni group-row entirely, for callers whose
      viewer is already scoped to a single Union and would only ever see their own one group
      (see churches/analytics.blade.php's $isNasionalView-gated usage)
    - showDivisionHeader: true re-buckets $grouped's top-level (Union-keyed) entries by their
      divisionId for an extra Divisi tier above Uni — a Union not yet placed under any Divisi
      falls into the 'no-division' bucket. Row partials keep the SAME depth values (1/2) as the
      non-Divisi case either way — only their ancestors chain grows one id longer — so no row
      partial needs its own indent step added for this tier.
--}}
@props([
    'grouped',
    'prefix',
    'colspan',
    'rowView',
    'rowKey',
    'rowExtra' => [],
    'showUnionHeader' => true,
    'showDivisionHeader' => false,
])

@if ($showDivisionHeader)
    @php
        // preserveKeys: true — groupBy() re-indexes numerically from 0 by default, which would
        // lose each Union's real id as the key (needed both for the toggle-id and to @include the
        // right row-view data further down).
        $divisionGroups = $grouped->groupBy('divisionId', true)->sortBy(fn ($unions) => $unions->first()['divisionName']);
    @endphp

    @foreach ($divisionGroups as $divisionKey => $unionsInDivision)
        @php
            $divisionAncestors = $prefix.'-division-'.$divisionKey;
            $divisionCount = $unionsInDivision->sum(fn ($u) => $u['rows']->count() + $u['conferences']->sum(fn ($c) => $c['rows']->count()));
        @endphp

        <x-analytics-group-row
            :label="$unionsInDivision->first()['divisionName']"
            :count="$divisionCount"
            :colspan="$colspan"
            :toggle-id="$divisionAncestors"
        />

        @foreach ($unionsInDivision as $unionKey => $unionGroup)
            @php $unionAncestors = $divisionAncestors.'-union-'.$unionKey; @endphp

            <x-analytics-group-row
                :label="$unionGroup['label']"
                :count="$unionGroup['conferences']->sum(fn ($c) => $c['rows']->count()) + $unionGroup['rows']->count()"
                :colspan="$colspan"
                :toggle-id="$unionAncestors"
                :ancestors="$divisionAncestors"
                :depth="1"
            />

            @foreach ($unionGroup['rows'] as $i => $row)
                @include($rowView, [$rowKey => $row, 'index' => $i, 'depth' => 1, 'ancestors' => trim($divisionAncestors.' '.$unionAncestors)] + $rowExtra)
            @endforeach

            @foreach ($unionGroup['conferences'] as $conferenceKey => $conferenceGroup)
                @php $conferenceAncestors = $unionAncestors.'-conf-'.$conferenceKey; @endphp
                @php $conferenceParentChain = trim($divisionAncestors.' '.$unionAncestors); @endphp

                <x-analytics-group-row
                    :label="$conferenceGroup['label']"
                    :count="$conferenceGroup['rows']->count()"
                    :colspan="$colspan"
                    :toggle-id="$conferenceAncestors"
                    :ancestors="$conferenceParentChain"
                    :depth="2"
                />

                @foreach ($conferenceGroup['rows'] as $i => $row)
                    @include($rowView, [$rowKey => $row, 'index' => $i, 'depth' => 2, 'ancestors' => trim($conferenceParentChain.' '.$conferenceAncestors)] + $rowExtra)
                @endforeach
            @endforeach
        @endforeach
    @endforeach
@else
    @foreach ($grouped as $unionKey => $unionGroup)
        @php $unionAncestors = $showUnionHeader ? $prefix.'-union-'.$unionKey : null; @endphp

        @if ($showUnionHeader)
            <x-analytics-group-row
                :label="$unionGroup['label']"
                :count="$unionGroup['conferences']->sum(fn ($c) => $c['rows']->count()) + $unionGroup['rows']->count()"
                :colspan="$colspan"
                :toggle-id="$unionAncestors"
            />
        @endif

        @foreach ($unionGroup['rows'] as $i => $row)
            @include($rowView, [$rowKey => $row, 'index' => $i, 'depth' => 1, 'ancestors' => $unionAncestors] + $rowExtra)
        @endforeach

        @foreach ($unionGroup['conferences'] as $conferenceKey => $conferenceGroup)
            @php $conferenceAncestors = $prefix.'-conf-'.$unionKey.'-'.$conferenceKey; @endphp

            <x-analytics-group-row
                :label="$conferenceGroup['label']"
                :count="$conferenceGroup['rows']->count()"
                :colspan="$colspan"
                :toggle-id="$conferenceAncestors"
                :ancestors="$unionAncestors"
                :depth="1"
            />

            @foreach ($conferenceGroup['rows'] as $i => $row)
                @include($rowView, [$rowKey => $row, 'index' => $i, 'depth' => 2, 'ancestors' => trim(($unionAncestors ? $unionAncestors.' ' : '').$conferenceAncestors)] + $rowExtra)
            @endforeach
        @endforeach
    @endforeach
@endif
