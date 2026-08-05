@extends('layouts.app')

@section('title', config('app.name') . ' — ' . __('dashboard.title'))

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Dashboard</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $scopeLabel }}</p>
        </div>

        @can('trigger-refresh')
            <div class="flex items-center gap-3">
                <form
                    method="POST"
                    action="{{ route('socials.refresh-all') }}"
                    data-confirm="{{ __('dashboard.refresh_confirm', ['count' => $totalSocials]) }}"
                    data-progress-form
                >
                    @csrf
                    <button
                        type="submit"
                        data-progress-button
                        class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-70"
                    >
                        <x-icon name="arrow-path" class="h-4 w-4" />
                        {{ __('dashboard.refresh_button') }}
                    </button>
                </form>
            </div>
        @endcan
    </div>

    <div class="mb-3 flex items-center justify-between">
        <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('dashboard.section_overview') }}</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $regionScopeLabel }}</p>
    </div>

    <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-stat-card
            :href="route('churches.directory', ['tab' => 'gereja'])"
            icon="building-office"
            :label="__('dashboard.stat_churches')"
            :value="$totalChurches"
        />
        <x-stat-card
            :href="route('churches.directory', ['tab' => 'institusi'])"
            icon="building-office"
            :label="__('dashboard.stat_institutions')"
            :value="$totalInstitutions"
        />
        <x-stat-card
            :href="route('churches.directory', ['tab' => 'personal'])"
            icon="user"
            :label="__('dashboard.stat_people')"
            :value="$totalPeople"
        />
    </div>

    @if ($goalRows->isNotEmpty())
        <div class="mb-8">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('goals.section_title') }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $goalRows->first()['scopeLabel'] }}</p>
            </div>
            <div class="grid grid-cols-1 gap-4">
                @foreach ($goalRows as $row)
                    <x-goal-card
                        :label="$row['label']"
                        :year="$row['year']"
                        :target="$row['target']"
                        :current="$row['current']"
                        :percent="$row['percent']"
                    />
                @endforeach
            </div>
        </div>
    @endif

    <div class="mb-3 flex items-center justify-between">
        <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('dashboard.section_growth') }}</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $regionScopeLabel }}</p>
    </div>

    <div class="mb-8 grid grid-cols-1 gap-4">
        <x-stat-card
            href="#top-pertumbuhan"
            icon="arrow-trending-up"
            :label="__('dashboard.stat_weekly_growth')"
            :value="($weeklyGrowth > 0 ? '+' : '') . number_format($weeklyGrowth)"
        />
    </div>

    <div id="top-pertumbuhan" class="mb-8 grid gap-6 scroll-mt-20 lg:grid-cols-2">
        <x-growth-score-card
            :title="__('dashboard.top_growth_title')"
            :subtitle="__('dashboard.top_growth_subtitle')"
            :rows="$topGrowthScores"
            :view-all-url="route('churches.metric-comparison')"
            :show-metrics="false"
        />
        <x-growth-score-card
            :title="__('dashboard.bottom_growth_title')"
            :subtitle="__('dashboard.bottom_growth_subtitle')"
            :rows="$bottomGrowthScores"
            :view-all-url="route('churches.metric-comparison')"
            :show-metrics="false"
        />
    </div>

    <div class="mb-3 flex items-center justify-between">
        <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('dashboard.section_total_accounts_reach') }}</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $regionScopeLabel }}</p>
    </div>

    <div class="mb-8 grid gap-6 lg:grid-cols-2">
        <div class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 shadow-sm dark:bg-blue-950/50 dark:text-blue-300">
                        <x-icon name="users" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('common.total') }}</p>
                        <p class="text-3xl font-bold tabular-nums text-slate-900 dark:text-white">{{ number_format($totalSocialAccounts) }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-4">
                    @foreach ($distributionChannels as $row)
                        <div class="flex items-center gap-1.5">
                            <x-platform-icon :platform="$row['platform']" class="h-9 w-9" />
                            <span class="text-2xl font-bold tabular-nums text-slate-900 dark:text-white">{{ $row['count'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-5 flex flex-wrap items-center gap-6 border-t border-black/5 pt-5 dark:border-white/5">
                @foreach ($accountsByOwnerType as $row)
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-50 text-slate-400 dark:bg-slate-800 dark:text-slate-500">
                            <x-icon :name="$row['icon']" class="h-4 w-4" />
                        </span>
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $row['label'] }}</p>
                            <p class="text-xl font-bold tabular-nums text-slate-900 dark:text-white">{{ number_format($row['count']) }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
            <p class="mb-4 font-bold text-slate-900 dark:text-white">{{ __('dashboard.reach_by_category_title') }}</p>
            <x-pie-chart :rows="$reachByOwnerType" />
        </div>
    </div>

    <div class="mb-8 grid gap-6 lg:grid-cols-2">
        <x-platform-score-card
            :title="__('comparison.platform_score_title')"
            :subtitle="__('dashboard.platform_score_subtitle')"
            :rows="$platformScoreRows"
            :platform-labels="$platformLabels"
            :scope="\App\Support\ComparisonScope::church()"
            :view-all-url="\App\Support\ComparisonScope::church()->platformComparisonUrl()"
            :show-metrics="false"
        />
        <x-distribution-channels-card
            :rows="$distributionChannels"
            :scope="\App\Support\ComparisonScope::church()"
            :view-all-url="\App\Support\ComparisonScope::church()->platformComparisonUrl()"
        />
    </div>

    <div class="mb-3 flex items-center justify-between">
        <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('dashboard.map_title') }}</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $mapScopeLabel }}</p>
    </div>

    <div class="mb-8 rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <div class="mb-4 flex flex-wrap items-start justify-between gap-2">
            <div>
                <p class="text-sm text-slate-500 dark:text-slate-400" data-map-summary="organisasi">
                    {{ __('dashboard.map_summary_organization', ['count' => $mapOrganizationCount, 'unionCount' => $mapUnions->count()]) }}
                </p>
                <p class="hidden text-sm text-slate-500 dark:text-slate-400" data-map-summary="gereja">
                    {{ __('dashboard.map_summary_church', ['count' => $mapChurches->count()]) }}
                </p>
                <p class="hidden text-sm text-slate-500 dark:text-slate-400" data-map-summary="personal">
                    {{ __('dashboard.map_summary_personal', ['count' => $mapPeople->count()]) }}
                </p>
                <p class="hidden text-sm text-slate-500 dark:text-slate-400" data-map-summary="institusi">
                    {{ __('dashboard.map_summary_institution', ['count' => $mapInstitutions->count()]) }}
                </p>
                <p class="hidden text-sm text-slate-500 dark:text-slate-400" data-map-summary="gabungan">
                    {{ __('dashboard.map_summary_combined', ['churchCount' => $mapChurches->count(), 'peopleCount' => $mapPeople->count(), 'institutionCount' => $mapInstitutions->count()]) }}
                </p>
            </div>
            @if ($unmappedCount > 0 || $unmappedPeopleCount > 0 || $unmappedInstitutionsCount > 0)
                <div class="text-right text-xs text-slate-400 dark:text-slate-500">
                    @if ($unmappedCount > 0)
                        <p>{{ __('dashboard.map_unmapped_church', ['count' => $unmappedCount]) }}</p>
                    @endif
                    @if ($unmappedPeopleCount > 0)
                        <p>{{ __('dashboard.map_unmapped_personal', ['count' => $unmappedPeopleCount]) }}</p>
                    @endif
                    @if ($unmappedInstitutionsCount > 0)
                        <p>{{ __('dashboard.map_unmapped_institution', ['count' => $unmappedInstitutionsCount]) }}</p>
                    @endif
                </div>
            @endif
        </div>

        <div class="mb-4 flex gap-2 overflow-x-auto border-b border-black/5 dark:border-white/5">
            <button type="button" data-map-tab="organisasi" class="border-b-2 px-4 py-2 text-sm font-medium transition">
                {{ __('comparison.organization_label') }}
            </button>
            <button type="button" data-map-tab="gereja" class="border-b-2 px-4 py-2 text-sm font-medium transition">
                {{ __('common.church') }}
            </button>
            <button type="button" data-map-tab="personal" class="border-b-2 px-4 py-2 text-sm font-medium transition">
                {{ __('common.personal') }}
            </button>
            <button type="button" data-map-tab="institusi" class="border-b-2 px-4 py-2 text-sm font-medium transition">
                {{ __('common.institution') }}
            </button>
            <button type="button" data-map-tab="gabungan" class="border-b-2 px-4 py-2 text-sm font-medium transition">
                {{ __('common.combined') }}
            </button>
        </div>

        @if ($mapChurches->isEmpty() && $mapPeople->isEmpty() && $mapInstitutions->isEmpty())
            <div class="flex h-[650px] items-center justify-center rounded-xl border border-dashed border-slate-200 text-sm text-slate-400 dark:border-slate-700 dark:text-slate-500">
                {{ __('dashboard.map_empty') }}
            </div>
        @else
            <div id="church-map-union-legend" class="mb-3 flex flex-wrap gap-x-4 gap-y-1.5"></div>
            <div id="church-map" class="relative z-0 h-[650px] w-full rounded-xl"></div>
        @endif
    </div>

    @if ($mapChurches->isNotEmpty() || $mapPeople->isNotEmpty() || $mapInstitutions->isNotEmpty())
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var mapI18n = {
                    reachLabel: @json(__('dashboard.map_reach_label')),
                    viewDetail: @json(__('dashboard.map_view_detail')),
                    layerStandard: @json(__('dashboard.map_layer_standard')),
                    layerLight: @json(__('dashboard.map_layer_light')),
                    layerDark: @json(__('dashboard.map_layer_dark')),
                    layerTopo: @json(__('dashboard.map_layer_topo')),
                    layerSatellite: @json(__('dashboard.map_layer_satellite')),
                    numberLocale: @json(str_replace('_', '-', app()->getLocale()) === 'id' ? 'id-ID' : 'en-US'),
                    churchLabel: @json(__('common.church')),
                    personalLabel: @json(__('common.personal')),
                    institutionLabel: @json(__('common.institution')),
                    officeLabel: @json(__('dashboard.map_office_label')),
                    editCoordinatesLabel: @json(__('dashboard.map_edit_coordinates_label')),
                };

                {{--
                    JSON_INVALID_UTF8_SUBSTITUTE (on top of Blade's default hex-escaping flags):
                    without it, a single invalid-UTF-8 byte anywhere in a name/city — easy to pick
                    up from copy-pasted real-world data — makes json_encode() return false, which
                    prints as nothing, turning "var churches = .map(...)" into a syntax error that
                    kills this whole inline script silently (map never renders, no console clue).
                --}}
                var churches = @json($mapChurches, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE).map(function (c) { c.type = 'gereja'; return c; });
                var people = @json($mapPeople, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE).map(function (p) { p.type = 'personal'; return p; });
                var institutions = @json($mapInstitutions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE).map(function (i) { i.type = 'institusi'; return i; });
                var combined = churches.concat(people).concat(institutions);

                // "Uni/Daerah" tab: no per-church markers (Union/Conference have no lat/lng of
                // their own, and church-level detail already lives in the Gereja tab) — zoomed
                // out it shows one region per Union, zoomed in one region per Daerah, each just
                // an outline (still built from that region's own churches' coordinates) plus a
                // summary popup — see buildRegionLayer()/refreshOrganizationLayer() below.
                var mapUnions = @json($mapUnions);
                var mapConferences = @json($mapConferences);
                var unionColorPalette = ['#2563eb', '#dc2626', '#059669', '#d97706', '#7c3aed', '#db2777', '#0891b2', '#65a30d', '#ea580c', '#4f46e5'];
                var unionColorById = {};
                mapUnions.forEach(function (u, i) { unionColorById[u.id] = unionColorPalette[i % unionColorPalette.length]; });
                var conferenceColorById = {};
                mapConferences.forEach(function (c) { conferenceColorById[c.id] = unionColorById[c.unionId]; });

                // Monotone chain convex hull (Andrew's algorithm) — draws each Union's rough
                // service-area outline from its own churches' coordinates, since there's no
                // official administrative boundary data to draw against. Returns null under 3
                // points (no polygon is meaningful yet).
                function convexHull(points) {
                    if (points.length < 3) return null;
                    var pts = points.slice().sort(function (a, b) { return a[0] - b[0] || a[1] - b[1]; });
                    function cross(o, a, b) { return (a[0] - o[0]) * (b[1] - o[1]) - (a[1] - o[1]) * (b[0] - o[0]); }
                    var lower = [];
                    for (var i = 0; i < pts.length; i++) {
                        while (lower.length >= 2 && cross(lower[lower.length - 2], lower[lower.length - 1], pts[i]) <= 0) lower.pop();
                        lower.push(pts[i]);
                    }
                    var upper = [];
                    for (var i = pts.length - 1; i >= 0; i--) {
                        while (upper.length >= 2 && cross(upper[upper.length - 2], upper[upper.length - 1], pts[i]) <= 0) upper.pop();
                        upper.push(pts[i]);
                    }
                    lower.pop();
                    upper.pop();
                    var hull = lower.concat(upper);
                    return hull.length >= 3 ? hull : null;
                }

                var map = L.map('church-map');

                var osmAttribution = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

                var baseLayers = {};
                baseLayers[mapI18n.layerStandard] = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: osmAttribution,
                });
                baseLayers[mapI18n.layerLight] = L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
                    maxZoom: 20,
                    attribution: osmAttribution + ' &copy; <a href="https://carto.com/attributions">CARTO</a>',
                });
                baseLayers[mapI18n.layerDark] = L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
                    maxZoom: 20,
                    attribution: osmAttribution + ' &copy; <a href="https://carto.com/attributions">CARTO</a>',
                });
                baseLayers[mapI18n.layerTopo] = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
                    maxZoom: 17,
                    attribution: osmAttribution + ', SRTM | style: OpenTopoMap (CC-BY-SA)',
                });
                baseLayers[mapI18n.layerSatellite] = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                    maxZoom: 19,
                    attribution: 'Tiles &copy; Esri',
                });

                baseLayers[mapI18n.layerStandard].addTo(map);
                L.control.layers(baseLayers, null, { position: 'topright' }).addTo(map);

                function buildMarker(item) {
                    var popup = '<p class="font-semibold">' + item.name + '</p>' +
                        (item.city ? '<p class="text-xs text-slate-500">' + item.city + '</p>' : '') +
                        '<p class="mt-1 text-xs">' + mapI18n.reachLabel + item.reach.toLocaleString(mapI18n.numberLocale) + '</p>' +
                        '<a href="' + item.url + '" class="text-xs text-blue-600">' + mapI18n.viewDetail + '</a>';

                    var marker = L.marker([item.lat, item.lng]).bindPopup(popup);
                    marker.itemReach = item.reach;
                    marker.itemType = item.type;
                    return marker;
                }

                function buildClusterGroup(items, color) {
                    var group = L.markerClusterGroup({
                        maxClusterRadius: 80,
                        iconCreateFunction: function (cluster) {
                            var children = cluster.getAllChildMarkers();
                            var totalReach = children.reduce(function (sum, marker) {
                                return sum + marker.itemReach;
                            }, 0);
                            var churchCount = children.filter(function (m) { return m.itemType === 'gereja'; }).length;
                            var personCount = children.filter(function (m) { return m.itemType === 'personal'; }).length;
                            var institutionCount = children.filter(function (m) { return m.itemType === 'institusi'; }).length;

                            var parts = [];
                            if (churchCount > 0) parts.push(churchCount + ' ' + mapI18n.churchLabel);
                            if (personCount > 0) parts.push(personCount + ' ' + mapI18n.personalLabel);
                            if (institutionCount > 0) parts.push(institutionCount + ' ' + mapI18n.institutionLabel);

                            var isMixed = parts.length > 1;
                            var size = isMixed ? 76 : 60;
                            var labelFontSize = isMixed ? '10px' : '12px';
                            var labelHtml = parts.join('<br>');

                            return L.divIcon({
                                html:
                                    '<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;' +
                                        'width:' + size + 'px;height:' + size + 'px;border-radius:9999px;padding:4px;box-sizing:border-box;' +
                                        'background:' + color + ';color:#fff;box-shadow:0 2px 6px rgba(0,0,0,.3);text-align:center;">' +
                                        '<span style="font-size:' + labelFontSize + ';font-weight:700;line-height:1.2;">' + labelHtml + '</span>' +
                                        '<span style="font-size:11px;line-height:1.2;margin-top:1px;">' + totalReach.toLocaleString(mapI18n.numberLocale) + '</span>' +
                                    '</div>',
                                className: 'church-cluster-icon',
                                iconSize: [size, size],
                            });
                        },
                    });

                    items.forEach(function (item) {
                        group.addLayer(buildMarker(item));
                    });

                    return group;
                }

                // Items with no resolvable Union are left out of this tab entirely (not lumped
                // into a catch-all "Other" bucket) — showing them risked visually overlapping
                // real Union/Daerah territory they don't actually belong to.
                var organizationItems = combined.filter(function (item) { return item.unionId && unionColorById[item.unionId]; });

                function churchPointsOf(items) {
                    return items.filter(function (item) { return item.type === 'gereja'; }).map(function (item) { return [item.lat, item.lng]; });
                }

                function regionCentroid(points) {
                    var totals = points.reduce(function (acc, p) { return [acc[0] + p[0], acc[1] + p[1]]; }, [0, 0]);
                    return [totals[0] / points.length, totals[1] / points.length];
                }

                function regionSummaryPopup(name, items) {
                    var churchCount = items.filter(function (item) { return item.type === 'gereja'; }).length;
                    var personCount = items.filter(function (item) { return item.type === 'personal'; }).length;
                    var institutionCount = items.filter(function (item) { return item.type === 'institusi'; }).length;
                    var totalReach = items.reduce(function (sum, item) { return sum + item.reach; }, 0);

                    var summaryParts = [churchCount + ' ' + mapI18n.churchLabel];
                    if (personCount > 0) summaryParts.push(personCount + ' ' + mapI18n.personalLabel);
                    if (institutionCount > 0) summaryParts.push(institutionCount + ' ' + mapI18n.institutionLabel);

                    return '<p class="font-semibold">' + name + '</p>' +
                        '<p class="mt-1 text-xs">' + summaryParts.join(', ') + '</p>' +
                        '<p class="text-xs">' + mapI18n.reachLabel + totalReach.toLocaleString(mapI18n.numberLocale) + '</p>';
                }

                // The region's outline is still only ever built from its own churches' coordinates
                // (personal/institution pins don't factor into the shape, only into the popup's
                // summary counts) — a region with zero mapped churches renders nothing at all,
                // rather than falling back to some other point source.
                function buildRegionLayer(items, name, color) {
                    var churchPoints = churchPointsOf(items);
                    if (churchPoints.length === 0) return null;

                    var hullPoints = convexHull(churchPoints);
                    var shape = hullPoints
                        ? L.polygon(hullPoints, { color: color, weight: 2, fillColor: color, fillOpacity: 0.15 })
                        : L.circleMarker(regionCentroid(churchPoints), { radius: 10, color: color, weight: 2, fillColor: color, fillOpacity: 0.5 });

                    return shape.bindPopup(regionSummaryPopup(name, items));
                }

                // A distinct small solid marker for the region's actual office location (set via
                // Kelola Akun → Uni/Daerah) — separate from buildRegionLayer()'s territory outline
                // (built purely from church coordinates) and its own centroid-fallback marker, so
                // the three never get visually confused for one another.
                function buildOfficeMarker(region, color) {
                    if (region.officeLat == null || region.officeLng == null) return null;

                    return L.circleMarker([region.officeLat, region.officeLng], {
                        radius: 6,
                        color: '#ffffff',
                        weight: 2,
                        fillColor: color,
                        fillOpacity: 1,
                    }).bindPopup('<p class="font-semibold">' + mapI18n.officeLabel + ' ' + region.name + '</p>');
                }

                // The individual church points a region's outline is built from — shown as their
                // own small dots (not full detail, that's the Gereja tab's job) specifically so a
                // wrongly-placed one is identifiable and fixable: the popup names the church and,
                // for whoever's allowed to, links straight to its coordinate fields.
                function buildChurchPointMarker(item, color) {
                    var popup = '<p class="font-semibold">' + item.name + '</p>' +
                        (item.city ? '<p class="text-xs text-slate-500">' + item.city + '</p>' : '') +
                        (item.editUrl ? '<a href="' + item.editUrl + '" class="text-xs text-blue-600">' + mapI18n.editCoordinatesLabel + '</a>' : '');

                    return L.circleMarker([item.lat, item.lng], {
                        radius: 3,
                        color: '#ffffff',
                        weight: 1,
                        fillColor: color,
                        fillOpacity: 0.9,
                    }).bindPopup(popup);
                }

                function buildChurchPointMarkers(items, color) {
                    return items
                        .filter(function (item) { return item.type === 'gereja'; })
                        .map(function (item) { return buildChurchPointMarker(item, color); });
                }

                var unionRegionLayers = mapUnions.map(function (u) {
                    return buildRegionLayer(organizationItems.filter(function (item) { return item.unionId === u.id; }), u.name, unionColorById[u.id]);
                }).filter(function (layer) { return layer !== null; });

                var unionOfficeMarkers = mapUnions.map(function (u) {
                    return buildOfficeMarker(u, unionColorById[u.id]);
                }).filter(function (marker) { return marker !== null; });

                var unionChurchPointMarkers = [];
                mapUnions.forEach(function (u) {
                    unionChurchPointMarkers = unionChurchPointMarkers.concat(
                        buildChurchPointMarkers(organizationItems.filter(function (item) { return item.unionId === u.id; }), unionColorById[u.id])
                    );
                });

                var conferenceRegionLayers = mapConferences.map(function (c) {
                    return buildRegionLayer(organizationItems.filter(function (item) { return item.conferenceId === c.id; }), c.name, conferenceColorById[c.id]);
                }).filter(function (layer) { return layer !== null; });

                var conferenceOfficeMarkers = mapConferences.map(function (c) {
                    return buildOfficeMarker(c, conferenceColorById[c.id]);
                }).filter(function (marker) { return marker !== null; });

                var conferenceChurchPointMarkers = [];
                mapConferences.forEach(function (c) {
                    conferenceChurchPointMarkers = conferenceChurchPointMarkers.concat(
                        buildChurchPointMarkers(organizationItems.filter(function (item) { return item.conferenceId === c.id; }), conferenceColorById[c.id])
                    );
                });

                var unionLayer = L.layerGroup(unionRegionLayers.concat(unionOfficeMarkers).concat(unionChurchPointMarkers));
                var conferenceLayer = L.layerGroup(conferenceRegionLayers.concat(conferenceOfficeMarkers).concat(conferenceChurchPointMarkers));
                var ORGANIZATION_ZOOM_THRESHOLD = 9;

                var legendEl = document.getElementById('church-map-union-legend');
                if (legendEl) {
                    var legendItems = mapUnions.map(function (u) {
                        return { color: unionColorById[u.id], label: u.name };
                    });
                    legendEl.innerHTML = legendItems.map(function (item) {
                        return '<span class="inline-flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-300">' +
                            '<span class="inline-block h-2.5 w-2.5 shrink-0 rounded-full" style="background:' + item.color + '"></span>' +
                            item.label + '</span>';
                    }).join('');
                }

                // Office coordinates can sit well outside where any of that region's mapped
                // church/personal/institution pins happen to be, so they're folded into this
                // tab's own bounds-fit too — otherwise switching to it wouldn't necessarily bring
                // the office marker into view.
                var organizationBoundsItems = organizationItems.concat(
                    mapUnions.filter(function (u) { return u.officeLat != null && u.officeLng != null; })
                        .map(function (u) { return { lat: u.officeLat, lng: u.officeLng }; }),
                    mapConferences.filter(function (c) { return c.officeLat != null && c.officeLng != null; })
                        .map(function (c) { return { lat: c.officeLat, lng: c.officeLng }; })
                );

                var dataByTab = { gereja: churches, personal: people, institusi: institutions, gabungan: combined, organisasi: organizationBoundsItems };
                var layersByTab = {
                    gereja: buildClusterGroup(churches, '#2563eb'),
                    personal: buildClusterGroup(people, '#7c3aed'),
                    institusi: buildClusterGroup(institutions, '#d97706'),
                    gabungan: buildClusterGroup(combined, '#0f172a'),
                };

                // Jabodetabek fallback view for a tab with no mapped items yet.
                var fallbackCenter = [-6.2, 106.8];
                var fallbackZoom = 10;

                var activeLayer = null;
                var currentTab = null;

                // The "Uni/Daerah" tab swaps between unionLayer/conferenceLayer as the viewer
                // zooms, instead of being one fixed entry in layersByTab like every other tab —
                // guarded by currentTab so the zoomend listener is a no-op on every other tab.
                var organizationSubLayer = null;

                function refreshOrganizationLayer() {
                    if (currentTab !== 'organisasi') return;

                    var desired = map.getZoom() >= ORGANIZATION_ZOOM_THRESHOLD ? conferenceLayer : unionLayer;
                    if (organizationSubLayer === desired) return;

                    if (organizationSubLayer) map.removeLayer(organizationSubLayer);
                    organizationSubLayer = desired;
                    map.addLayer(organizationSubLayer);
                }

                map.on('zoomend', refreshOrganizationLayer);

                function activateMapTab(tab) {
                    if (activeLayer) map.removeLayer(activeLayer);
                    if (organizationSubLayer) { map.removeLayer(organizationSubLayer); organizationSubLayer = null; }

                    currentTab = tab;

                    if (tab === 'organisasi') {
                        activeLayer = null;
                    } else {
                        activeLayer = layersByTab[tab];
                        map.addLayer(activeLayer);
                    }

                    var items = dataByTab[tab];
                    if (items.length > 0) {
                        var bounds = L.latLngBounds(items.map(function (i) { return [i.lat, i.lng]; }));
                        map.fitBounds(bounds, { padding: [24, 24] });
                    } else {
                        map.setView(fallbackCenter, fallbackZoom);
                    }

                    // fitBounds()/setView() above only fire 'zoomend' if the zoom level actually
                    // changes — this covers the case where it doesn't (e.g. switching tabs
                    // without the view moving), so the right sub-layer still shows immediately.
                    refreshOrganizationLayer();

                    document.querySelectorAll('[data-map-summary]').forEach(function (el) {
                        el.classList.toggle('hidden', el.dataset.mapSummary !== tab);
                    });

                    if (legendEl) legendEl.classList.toggle('hidden', tab !== 'organisasi');

                    document.querySelectorAll('[data-map-tab]').forEach(function (btn) {
                        var isActive = btn.dataset.mapTab === tab;
                        btn.classList.toggle('border-blue-600', isActive);
                        btn.classList.toggle('text-blue-600', isActive);
                        btn.classList.toggle('border-transparent', !isActive);
                        btn.classList.toggle('text-slate-500', !isActive);
                        btn.classList.toggle('dark:text-slate-400', !isActive);
                    });
                }

                document.querySelectorAll('[data-map-tab]').forEach(function (btn) {
                    btn.addEventListener('click', function () { activateMapTab(btn.dataset.mapTab); });
                });

                activateMapTab('organisasi');
            });
        </script>
    @endif
@endsection
