{{--
    Divisi > Union > Conference > row nested table body — the "Direktori Akun" tabs' shared
    grouping shape (Organisasi/Gereja/Institusi/Personal all group the same way). Renders
    <x-analytics-group-row> toggle headers plus each entity row via the caller's own row partial,
    building up the toggle-id/ancestors chain so collapsing a Divisi/Uni also collapses everything
    nested under it (see x-group-toggle-all-button / partials/directory-*-row.blade.php's own
    data-ancestors consumer in the page's inline collapse script).

    Props:
    - grouped: the $groupedX collection from BuildsLeaderboards::groupByRegion() — divisionKey =>
      ['label' => ..., 'rows' => (entities owned directly by that Divisi, e.g. a Divisi's own
      social account — the Organisasi tab is the only caller where this is ever non-empty),
      'unions' => [unionKey => ['label' => ..., 'rows' => (Uni-owned entities), 'conferences' =>
      [confKey => ['label' => ..., 'rows' => (Daerah-owned/leaf entities)]]]]]
    - prefix: toggle-id/ancestors namespace, e.g. 'directory-organization' (must be unique per table on the page)
    - colspan: passed straight through to <x-analytics-group-row>
    - rowView: the row partial's view name, e.g. 'partials.directory-organization-row'
    - rowKey: the variable name that partial expects its entity under, e.g. 'organization'
    - rowExtra: any additional fixed data merged into every row-partial include (e.g. a shared maxReach)
    - showDivisionHeader: false (default) skips the Divisi tier entirely — its own 'rows' and every
      union under it render as if they were top-level, un-nested under any Divisi header. Row
      partials get the SAME depth values either way (0 for Divisi-owned, 1 for Uni-owned, 2 for
      Daerah-owned/leaf) — only the ancestors chain gains an extra id when this is on, so no row
      partial needs its own visual indent step added for this tier.
    - showUnionHeader: false skips the Uni tier's own group-row entirely, for callers whose
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
    'showDivisionHeader' => false,
    'showUnionHeader' => true,
])

@php
    $chain = fn (...$parts) => trim(implode(' ', array_filter($parts)));
@endphp

@foreach ($grouped as $divisionKey => $divisionGroup)
    @php
        $divisionId = $prefix.'-division-'.$divisionKey;
        $divisionChain = $showDivisionHeader ? $divisionId : null;
    @endphp

    @if ($showDivisionHeader)
        <x-analytics-group-row
            :label="$divisionGroup['label']"
            :count="$divisionGroup['rows']->count() + $divisionGroup['unions']->sum(fn ($u) => $u['rows']->count() + $u['conferences']->sum(fn ($c) => $c['rows']->count()))"
            :colspan="$colspan"
            :toggle-id="$divisionId"
        />
    @endif

    @foreach ($divisionGroup['rows'] as $i => $row)
        @include($rowView, [$rowKey => $row, 'index' => $i, 'depth' => 0, 'ancestors' => $divisionChain] + $rowExtra)
    @endforeach

    @foreach ($divisionGroup['unions'] as $unionKey => $unionGroup)
        @php
            $unionId = $divisionId.'-union-'.$unionKey;
            $unionChain = $showUnionHeader ? $chain($divisionChain, $unionId) : $divisionChain;
        @endphp

        @if ($showUnionHeader)
            <x-analytics-group-row
                :label="$unionGroup['label']"
                :count="$unionGroup['conferences']->sum(fn ($c) => $c['rows']->count()) + $unionGroup['rows']->count()"
                :colspan="$colspan"
                :toggle-id="$unionId"
                :ancestors="$divisionChain"
                :depth="$showDivisionHeader ? 1 : 0"
            />
        @endif

        @foreach ($unionGroup['rows'] as $i => $row)
            @include($rowView, [$rowKey => $row, 'index' => $i, 'depth' => 1, 'ancestors' => $unionChain] + $rowExtra)
        @endforeach

        @foreach ($unionGroup['conferences'] as $conferenceKey => $conferenceGroup)
            @php $conferenceId = $unionId.'-conf-'.$conferenceKey; @endphp

            <x-analytics-group-row
                :label="$conferenceGroup['label']"
                :count="$conferenceGroup['rows']->count()"
                :colspan="$colspan"
                :toggle-id="$conferenceId"
                :ancestors="$unionChain"
                :depth="($showDivisionHeader ? 1 : 0) + ($showUnionHeader ? 1 : 0)"
            />

            @foreach ($conferenceGroup['rows'] as $i => $row)
                @include($rowView, [$rowKey => $row, 'index' => $i, 'depth' => 2, 'ancestors' => $chain($unionChain, $conferenceId)] + $rowExtra)
            @endforeach
        @endforeach
    @endforeach
@endforeach
