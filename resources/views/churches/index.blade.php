@extends('layouts.app')

@section('title', config('app.name') . ' — ' . __('dashboard.title'))

@section('content')
    <div class="mb-6">
        <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('dashboard.heading') }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $scopeLabel }}</p>
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
        <div class="min-w-0 rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 shadow-sm dark:bg-blue-950/50 dark:text-blue-300">
                        <x-icon name="users" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
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

        <div class="min-w-0 rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
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

    {{-- Hidden until at least one hashtag is tracked and has matched data — no empty-state
         clutter for accounts that never turn this feature on. --}}
    @if ($hashtagSummary['total'] > 0)
        <div class="mb-8 rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="font-bold text-slate-900 dark:text-white">{{ __('hashtag.dashboard_summary_title') }}</p>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ __('hashtag.dashboard_summary_subtitle', ['count' => number_format($hashtagSummary['total'])]) }}
                        @if ($hashtagSummary['topHashtag'])
                            — {{ __('hashtag.dashboard_top_hashtag', ['tag' => $hashtagSummary['topHashtag']->display_tag, 'count' => number_format($hashtagSummary['topHashtagCount'])]) }}
                        @endif
                    </p>
                </div>
                <a href="{{ \App\Support\ComparisonScope::church()->hashtagComparisonUrl() }}" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">
                    {{ __('hashtag.view_all') }}
                </a>
            </div>

            <div class="flex flex-wrap items-center gap-6">
                @foreach (['youtube', 'instagram', 'tiktok'] as $platform)
                    <div class="flex items-center gap-1.5">
                        <x-platform-icon :platform="$platform" class="h-9 w-9" />
                        <span class="text-2xl font-bold tabular-nums text-slate-900 dark:text-white">{{ number_format($hashtagSummary['byPlatform'][$platform] ?? 0) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="mb-3 flex items-center justify-between">
        <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('dashboard.map_title') }}</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $mapScopeLabel }}</p>
    </div>

    <div class="mb-8 rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <div class="mb-4 flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0 flex-1">
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
                <p class="hidden text-sm text-slate-500 dark:text-slate-400" data-map-summary="heatmap">
                    {{ __('dashboard.map_summary_heatmap', ['count' => $mapGrowthDataCount, 'noDataCount' => $mapNoGrowthDataCount]) }}
                </p>
                <p class="hidden text-sm text-slate-500 dark:text-slate-400" data-map-summary="wilayah">
                    {{ __('dashboard.map_summary_region_heatmap', ['count' => $mapRegionGrowth->count()]) }}
                </p>
            </div>
            @if ($unmappedCount > 0 || $unmappedPeopleCount > 0 || $unmappedInstitutionsCount > 0)
                <div class="shrink-0 text-right text-xs text-slate-400 dark:text-slate-500">
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
            <button type="button" data-map-tab="heatmap" class="border-b-2 px-4 py-2 text-sm font-medium transition">
                {{ __('dashboard.map_tab_heatmap') }}
            </button>
            <button type="button" data-map-tab="wilayah" class="border-b-2 px-4 py-2 text-sm font-medium transition">
                {{ __('dashboard.map_tab_region_heatmap') }}
            </button>
        </div>

        <div id="church-map-heatmap-legend" class="mb-3 hidden flex-wrap gap-x-4 gap-y-1.5">
            <span class="inline-flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-300">
                <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-full" style="background:#059669"></span>
                {{ __('dashboard.map_heatmap_legend_growth') }}
            </span>
            <span class="inline-flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-300">
                <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-full" style="background:#dc2626"></span>
                {{ __('dashboard.map_heatmap_legend_decline') }}
            </span>
        </div>

        {{-- Discrete buckets (not a continuous gradient) — same idea as the reference "$/G"
             legend this tab was modeled after: fixed, always-the-same ranges, since a
             choropleth's whole point is comparing regions against a stable scale rather than
             each other's relative extremes the way the dot map's tab does. --}}
        <div id="church-map-region-legend" class="mb-3 hidden flex-wrap gap-x-4 gap-y-1.5">
            <span class="inline-flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-300">
                <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-sm" style="background:#065f46"></span> &ge; 5%
            </span>
            <span class="inline-flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-300">
                <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-sm" style="background:#16a34a"></span> 1% – 5%
            </span>
            <span class="inline-flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-300">
                <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-sm" style="background:#86efac"></span> 0% – 1%
            </span>
            <span class="inline-flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-300">
                <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-sm" style="background:#fecaca"></span> -1% – 0%
            </span>
            <span class="inline-flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-300">
                <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-sm" style="background:#dc2626"></span> -5% – -1%
            </span>
            <span class="inline-flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-300">
                <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-sm" style="background:#7f1d1d"></span> &le; -5%
            </span>
            <span class="inline-flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-300">
                <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-sm border border-slate-300 dark:border-slate-600" style="background:#e2e8f0"></span>
                {{ __('dashboard.map_region_legend_no_data') }}
            </span>
        </div>

        @if ($mapChurches->isEmpty() && $mapPeople->isEmpty() && $mapInstitutions->isEmpty())
            <div class="flex h-[350px] items-center justify-center rounded-xl border border-dashed border-slate-200 text-sm text-slate-400 sm:h-[450px] lg:h-[650px] dark:border-slate-700 dark:text-slate-500">
                {{ __('dashboard.map_empty') }}
            </div>
        @else
            <div id="church-map-union-legend" class="mb-3 flex flex-wrap gap-x-4 gap-y-1.5"></div>
            <div id="church-map" class="relative z-0 h-[350px] w-full rounded-xl sm:h-[450px] lg:h-[650px]"></div>
        @endif
    </div>

    @if ($mapChurches->isNotEmpty() || $mapPeople->isNotEmpty() || $mapInstitutions->isNotEmpty())
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var mapI18n = {
                    reachLabel: @json(__('dashboard.map_reach_label')),
                    weeklyGrowthLabel: @json(__('dashboard.stat_weekly_growth')),
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
                    regionNoDataLabel: @json(__('dashboard.map_region_legend_no_data')),
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
                var mapDivisions = @json($mapDivisions);
                var mapUnions = @json($mapUnions);
                var mapConferences = @json($mapConferences);
                var unionColorPalette = ['#2563eb', '#dc2626', '#059669', '#d97706', '#7c3aed', '#db2777', '#0891b2', '#65a30d', '#ea580c', '#4f46e5'];
                // Divisi gets its own independent color assignment (not inherited by Uni/Daerah
                // below it) — every Uni keeps a visually distinct color at its own zoom tier
                // exactly as before Divisi existed; only the Divisi tier itself, one zoom step
                // further out, uses this separate palette.
                var divisionColorById = {};
                mapDivisions.forEach(function (d, i) { divisionColorById[d.id] = unionColorPalette[i % unionColorPalette.length]; });
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

                // The default "Standard" basemap's own greens (forest/park tiles) compete
                // visually with the growth map's green dots — auto-switching to the muted
                // "Light" layer on that tab specifically (never overriding a base layer the
                // viewer already picked some other way) fixes that without touching the other
                // tabs' basemap at all. The layer control still lets them pick anything else
                // afterward — this only ever nudges the very first time.
                var currentBaseLayerName = mapI18n.layerStandard;
                map.on('baselayerchange', function (e) { currentBaseLayerName = e.name; });

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

                var divisionRegionLayers = mapDivisions.map(function (d) {
                    return buildRegionLayer(organizationItems.filter(function (item) { return item.divisionId === d.id; }), d.name, divisionColorById[d.id]);
                }).filter(function (layer) { return layer !== null; });

                // No office markers for this tier — Division has no latitude/longitude of its own.
                var divisionChurchPointMarkers = [];
                mapDivisions.forEach(function (d) {
                    divisionChurchPointMarkers = divisionChurchPointMarkers.concat(
                        buildChurchPointMarkers(organizationItems.filter(function (item) { return item.divisionId === d.id; }), divisionColorById[d.id])
                    );
                });

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

                var divisionLayer = L.layerGroup(divisionRegionLayers.concat(divisionChurchPointMarkers));
                var unionLayer = L.layerGroup(unionRegionLayers.concat(unionOfficeMarkers).concat(unionChurchPointMarkers));
                var conferenceLayer = L.layerGroup(conferenceRegionLayers.concat(conferenceOfficeMarkers).concat(conferenceChurchPointMarkers));
                // Below DIVISION_ZOOM_THRESHOLD: one region per Divisi. Between the two
                // thresholds: one region per Uni (unchanged from before Divisi existed). At or
                // above ORGANIZATION_ZOOM_THRESHOLD: one region per Daerah.
                var DIVISION_ZOOM_THRESHOLD = 6;
                var ORGANIZATION_ZOOM_THRESHOLD = 9;

                var legendEl = document.getElementById('church-map-union-legend');

                // Swaps the legend's contents to match whichever tier is currently on screen —
                // called from refreshOrganizationLayer() below, since the visible tier (and thus
                // which colors are actually on the map) changes with zoom.
                function renderLegend(items, colorById, labelKey) {
                    if (! legendEl) return;

                    var legendItems = items.map(function (item) {
                        return { color: colorById[item.id], label: item[labelKey] || item.name };
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
                // the office marker into view. Divisi has no office coordinates of its own (see
                // $mapDivisions above), so there's nothing to add for that tier here.
                var organizationBoundsItems = organizationItems.concat(
                    mapUnions.filter(function (u) { return u.officeLat != null && u.officeLng != null; })
                        .map(function (u) { return { lat: u.officeLat, lng: u.officeLng }; }),
                    mapConferences.filter(function (c) { return c.officeLat != null && c.officeLng != null; })
                        .map(function (c) { return { lat: c.officeLat, lng: c.officeLng }; })
                );

                // Weekly growth map — solid colored dots (green = growth, red = decline) rather
                // than a blurred heat layer. A real density heatmap only reads well with many
                // overlapping points; with a few dozen scattered churches/personal/institutions,
                // a blurred layer either washes out to near-invisible or, tuned solid enough to
                // see, just renders as isolated blobs with no actual blending between them —
                // confirmed looking wrong both ways. Individual dots are what this data actually
                // is: one clear, clickable value per location, same marker language the map's own
                // Uni/Daerah tab already uses for its church-point dots.
                //
                // Shade (not size) carries the magnitude — a fixed-radius dot avoids a big bubble
                // in a growing area visually swallowing its smaller neighbors, which matters here
                // since real churches can sit close together (e.g. within the same city).
                // An item with no growthScore yet (fewer than 2 weeks of tracking — see
                // growthScoreRows()) is left out entirely rather than counted as flat growth.
                var growthMapBuild = null;
                function buildGrowthMap(items) {
                    if (growthMapBuild) return growthMapBuild;

                    var withGrowth = items.filter(function (item) { return item.growthScore !== null && item.growthScore !== undefined; });
                    var maxAbsGrowth = withGrowth.reduce(function (max, item) { return Math.max(max, Math.abs(item.growthScore)); }, 0) || 1;

                    // sqrt rather than a straight ratio: a plain value/max only ever puts the
                    // single most extreme account in the darkest shade, leaving every other point
                    // — the vast majority of them — bunched into the palest bucket. Square-rooting
                    // the ratio pulls the middle of the range up disproportionately (0.25 → 0.5,
                    // 0.5 → 0.71), so a solidly-average point still reads as clearly colored.
                    var growthShades = ['#bbf7d0', '#4ade80', '#16a34a', '#065f46'];
                    var declineShades = ['#fecaca', '#f87171', '#dc2626', '#7f1d1d'];
                    function shadeFor(value) {
                        var t = Math.sqrt(Math.abs(value) / maxAbsGrowth);
                        var shades = value > 0 ? growthShades : declineShades;
                        var index = Math.min(shades.length - 1, Math.floor(t * shades.length));
                        return shades[index];
                    }

                    var markers = withGrowth.map(function (item) {
                        var popup = '<p class="font-semibold">' + item.name + '</p>' +
                            (item.city ? '<p class="text-xs text-slate-500">' + item.city + '</p>' : '') +
                            '<p class="mt-1 text-xs">' + mapI18n.weeklyGrowthLabel + ': ' +
                                (item.growthScore > 0 ? '+' : '') + item.growthScore.toFixed(1) + '%</p>' +
                            '<a href="' + item.url + '" class="text-xs text-blue-600">' + mapI18n.viewDetail + '</a>';

                        return L.circleMarker([item.lat, item.lng], {
                            radius: 5, color: '#ffffff', weight: 1,
                            fillColor: shadeFor(item.growthScore), fillOpacity: 0.9,
                        }).bindPopup(popup);
                    });

                    growthMapBuild = { layer: L.layerGroup(markers), points: withGrowth };
                    return growthMapBuild;
                }

                // Experimental regional choropleth — colors each Southeast Asian province/state
                // by its own average growth score (computed server-side, see
                // ChurchDashboardController::index()'s $mapRegionGrowth) rather than plotting
                // individual points. A separate tab from "Peta Panas Pertumbuhan" above, not a
                // replacement — per the user's own "untuk pembanding" framing, both stay
                // available to compare side by side.
                //
                // Fixed buckets (not scaled to whatever data happens to be on screen, unlike the
                // dot map) — a choropleth's whole point is a stable scale to compare regions
                // against, matching the legend's own always-the-same ranges.
                var REGION_GROWTH_BUCKETS = [
                    { min: 5, color: '#065f46' },
                    { min: 1, color: '#16a34a' },
                    { min: 0, color: '#86efac' },
                    { min: -1, color: '#fecaca' },
                    { min: -5, color: '#dc2626' },
                    { min: -Infinity, color: '#7f1d1d' },
                ];
                var REGION_NO_DATA_COLOR = '#e2e8f0';

                function colorForRegionScore(score) {
                    if (score === null || score === undefined) return REGION_NO_DATA_COLOR;
                    for (var i = 0; i < REGION_GROWTH_BUCKETS.length; i++) {
                        if (score >= REGION_GROWTH_BUCKETS[i].min) return REGION_GROWTH_BUCKETS[i].color;
                    }
                    return REGION_NO_DATA_COLOR;
                }

                var regionScoreByKey = {};
                @json($mapRegionGrowth).forEach(function (r) { regionScoreByKey[r.country + '|' + r.name] = r; });

                var regionChoroplethPromise = null;
                function loadRegionChoropleth() {
                    if (regionChoroplethPromise) return regionChoroplethPromise;

                    regionChoroplethPromise = fetch('/data/sea-admin1.geojson')
                        .then(function (res) { return res.json(); })
                        .then(function (geojson) {
                            return L.geoJSON(geojson, {
                                style: function (feature) {
                                    var key = feature.properties.shapeGroup + '|' + feature.properties.shapeName.trim();
                                    var entry = regionScoreByKey[key];
                                    return {
                                        color: '#94a3b8', weight: 1,
                                        fillColor: colorForRegionScore(entry ? entry.score : null), fillOpacity: 0.7,
                                    };
                                },
                                onEachFeature: function (feature, layer) {
                                    var key = feature.properties.shapeGroup + '|' + feature.properties.shapeName.trim();
                                    var entry = regionScoreByKey[key];
                                    var popup = '<p class="font-semibold">' + feature.properties.shapeName.trim() + '</p>';
                                    popup += entry
                                        ? '<p class="mt-1 text-xs">' + mapI18n.weeklyGrowthLabel + ': ' + (entry.score > 0 ? '+' : '') + entry.score.toFixed(1) + '% (' + entry.count + ')</p>'
                                        : '<p class="mt-1 text-xs text-slate-400">' + mapI18n.regionNoDataLabel + '</p>';
                                    layer.bindPopup(popup);
                                },
                            });
                        })
                        // A failed fetch (offline, asset missing, …) degrades to an empty layer
                        // rather than leaving this tab permanently broken/stuck loading.
                        .catch(function (err) {
                            console.error('Failed to load the region choropleth boundary data', err);
                            return L.layerGroup([]);
                        });

                    return regionChoroplethPromise;
                }

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

                // The "Uni/Daerah" tab swaps between divisionLayer/unionLayer/conferenceLayer as
                // the viewer zooms, instead of being one fixed entry in layersByTab like every
                // other tab — guarded by currentTab so the zoomend listener is a no-op on every
                // other tab. The legend swaps in step, since the colors actually on screen change
                // with the tier too.
                var organizationSubLayer = null;

                function refreshOrganizationLayer() {
                    if (currentTab !== 'organisasi') return;

                    var zoom = map.getZoom();
                    var desired = zoom >= ORGANIZATION_ZOOM_THRESHOLD
                        ? conferenceLayer
                        : (zoom >= DIVISION_ZOOM_THRESHOLD ? unionLayer : divisionLayer);

                    if (organizationSubLayer !== desired) {
                        if (organizationSubLayer) map.removeLayer(organizationSubLayer);
                        organizationSubLayer = desired;
                        map.addLayer(organizationSubLayer);
                    }

                    if (desired === divisionLayer) {
                        renderLegend(mapDivisions, divisionColorById, 'name');
                    } else if (desired === unionLayer) {
                        renderLegend(mapUnions, unionColorById, 'name');
                    }
                    // Conference tier reuses its parent Union's colors — no separate legend swap,
                    // the Uni legend from the tier just zoomed past stays correct.
                }

                map.on('zoomend', refreshOrganizationLayer);

                function activateMapTab(tab) {
                    if (activeLayer) map.removeLayer(activeLayer);
                    if (organizationSubLayer) { map.removeLayer(organizationSubLayer); organizationSubLayer = null; }

                    currentTab = tab;

                    var items;
                    if (tab === 'organisasi') {
                        activeLayer = null;
                        items = dataByTab.organisasi;
                    } else if (tab === 'heatmap') {
                        if (currentBaseLayerName === mapI18n.layerStandard) {
                            map.removeLayer(baseLayers[mapI18n.layerStandard]);
                            map.addLayer(baseLayers[mapI18n.layerLight]);
                            currentBaseLayerName = mapI18n.layerLight;
                        }

                        var built = buildGrowthMap(combined);
                        activeLayer = built.layer;
                        items = built.points;
                        map.addLayer(activeLayer);
                    } else if (tab === 'wilayah') {
                        if (currentBaseLayerName === mapI18n.layerStandard) {
                            map.removeLayer(baseLayers[mapI18n.layerStandard]);
                            map.addLayer(baseLayers[mapI18n.layerLight]);
                            currentBaseLayerName = mapI18n.layerLight;
                        }

                        // The boundary file is fetched (once, then cached) rather than bundled —
                        // items stays empty for now, so the fallback view below shows immediately
                        // instead of blocking on the network; fitBounds() re-runs once the real
                        // shapes are in, giving the full Southeast Asia extent instead.
                        activeLayer = null;
                        items = [];

                        loadRegionChoropleth().then(function (layer) {
                            if (currentTab !== 'wilayah') return; // switched away before this resolved

                            activeLayer = layer;
                            map.addLayer(layer);

                            var layerBounds = layer.getBounds();
                            if (layerBounds.isValid()) {
                                map.fitBounds(layerBounds, { padding: [24, 24] });
                            }
                        });
                    } else {
                        activeLayer = layersByTab[tab];
                        items = dataByTab[tab];
                        map.addLayer(activeLayer);
                    }

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

                    var heatmapLegendEl = document.getElementById('church-map-heatmap-legend');
                    if (heatmapLegendEl) {
                        heatmapLegendEl.classList.toggle('hidden', tab !== 'heatmap');
                        heatmapLegendEl.classList.toggle('flex', tab === 'heatmap');
                    }

                    var regionLegendEl = document.getElementById('church-map-region-legend');
                    if (regionLegendEl) {
                        regionLegendEl.classList.toggle('hidden', tab !== 'wilayah');
                        regionLegendEl.classList.toggle('flex', tab === 'wilayah');
                    }

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
