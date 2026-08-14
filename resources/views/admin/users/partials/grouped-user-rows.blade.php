{{--
    Divisi > Uni > Daerah collapsible grouping for the Kelola Pengguna Admin/Pemimpin tables (see
    the $groupUsersByRegion closure at the top of admin/users/index.blade.php for the shape of
    $grouped, and components/analytics-group-row.blade.php + partials/analytics-group-toggle.blade.php
    for the header/collapse mechanics this reuses).

    Expected: $grouped (['ungrouped' => Collection, 'divisions' => Collection]), $colspan,
    $prefix (toggle-id namespace, unique per table on the page), $canBootstrapAnyLevel, $tab.
--}}
@foreach ($grouped['ungrouped'] as $i => $user)
    @include('admin.users.partials.user-row', ['user' => $user, 'index' => $i + 1, 'canBootstrapAnyLevel' => $canBootstrapAnyLevel, 'tab' => $tab])
@endforeach

@foreach ($grouped['divisions'] as $divisionKey => $division)
    @php $divisionAncestors = $prefix.'-division-'.$divisionKey; @endphp

    <x-analytics-group-row
        :label="$division['label']"
        :count="$division['rows']->count() + $division['unions']->sum(fn ($u) => $u['rows']->count() + $u['conferences']->sum(fn ($c) => $c['rows']->count()))"
        :colspan="$colspan"
        :toggle-id="$divisionAncestors"
    />

    @foreach ($division['rows'] as $i => $user)
        @include('admin.users.partials.user-row', [
            'user' => $user, 'index' => $i + 1, 'depth' => 1, 'ancestors' => $divisionAncestors,
            'canBootstrapAnyLevel' => $canBootstrapAnyLevel, 'tab' => $tab,
        ])
    @endforeach

    @foreach ($division['unions'] as $unionKey => $union)
        @php $unionAncestors = $prefix.'-union-'.$divisionKey.'-'.$unionKey; @endphp

        <x-analytics-group-row
            :label="$union['label']"
            :count="$union['rows']->count() + $union['conferences']->sum(fn ($c) => $c['rows']->count())"
            :colspan="$colspan"
            :toggle-id="$unionAncestors"
            :ancestors="$divisionAncestors"
            :depth="1"
        />

        @foreach ($union['rows'] as $i => $user)
            @include('admin.users.partials.user-row', [
                'user' => $user, 'index' => $i + 1, 'depth' => 2,
                'ancestors' => trim($divisionAncestors.' '.$unionAncestors),
                'canBootstrapAnyLevel' => $canBootstrapAnyLevel, 'tab' => $tab,
            ])
        @endforeach

        @foreach ($union['conferences'] as $conferenceKey => $conference)
            @php $conferenceAncestors = $prefix.'-conf-'.$divisionKey.'-'.$unionKey.'-'.$conferenceKey; @endphp
            @php $conferenceParentChain = trim($divisionAncestors.' '.$unionAncestors); @endphp

            <x-analytics-group-row
                :label="$conference['label']"
                :count="$conference['rows']->count()"
                :colspan="$colspan"
                :toggle-id="$conferenceAncestors"
                :ancestors="$conferenceParentChain"
                :depth="2"
            />

            @foreach ($conference['rows'] as $i => $user)
                @include('admin.users.partials.user-row', [
                    'user' => $user, 'index' => $i + 1, 'depth' => 3,
                    'ancestors' => trim($conferenceParentChain.' '.$conferenceAncestors),
                    'canBootstrapAnyLevel' => $canBootstrapAnyLevel, 'tab' => $tab,
                ])
            @endforeach
        @endforeach
    @endforeach
@endforeach
