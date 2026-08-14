{{--
    Union > Conference > row nested table body — the "Direktori Akun" tabs' shared grouping
    shape (Organisasi/Gereja/Institusi/Personal all group the same way). Renders <x-analytics-
    group-row> toggle headers plus each entity row via the caller's own row partial, building
    up the toggle-id/ancestors chain so collapsing a Uni also collapses every Daerah nested
    under it (see x-group-toggle-all-button / partials/directory-*-row.blade.php's own
    data-ancestors consumer in the page's inline collapse script).

    Props:
    - grouped: the $groupedX collection — unionKey => ['label' => ..., 'rows' => ..., 'conferences' => [confKey => ['label' => ..., 'rows' => ...]]]
    - prefix: toggle-id/ancestors namespace, e.g. 'directory-organization' (must be unique per table on the page)
    - colspan: passed straight through to <x-analytics-group-row>
    - rowView: the row partial's view name, e.g. 'partials.directory-organization-row'
    - rowKey: the variable name that partial expects its entity under, e.g. 'organization'
    - rowExtra: any additional fixed data merged into every row-partial include (e.g. a shared maxReach)
    - showUnionHeader: false skips the top-level Uni group-row entirely, for callers whose
      viewer is already scoped to a single Union and would only ever see their own one group
      (see churches/analytics.blade.php's $isNasionalView-gated usage)
--}}
@props([
    'grouped',
    'prefix',
    'colspan',
    'rowView',
    'rowKey',
    'rowExtra' => [],
    'showUnionHeader' => true,
])

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
