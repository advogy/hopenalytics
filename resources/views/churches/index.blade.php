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

    <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
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

    <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-stat-card
            :href="route('churches.directory', ['tab' => 'gereja'])"
            icon="globe-alt"
            :label="__('dashboard.stat_church_socials')"
            :value="$totalSocials"
        />
        <x-stat-card
            :href="route('churches.directory', ['tab' => 'institusi'])"
            icon="globe-alt"
            :label="__('dashboard.stat_institution_socials')"
            :value="$totalInstitutionSocials"
        />
        <x-stat-card
            :href="route('churches.directory', ['tab' => 'personal'])"
            icon="globe-alt"
            :label="__('dashboard.stat_personal_socials')"
            :value="$totalPersonalSocials"
        />
    </div>

    <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-stat-card
            :href="route('churches.leaderboard', ['metric' => 'reach', 'sort' => 'value'])"
            icon="arrow-trending-up"
            :label="__('dashboard.stat_church_reach')"
            :value="number_format($totalReachChurch)"
        />
        <x-stat-card
            :href="route('institutions.leaderboard', ['metric' => 'reach', 'sort' => 'value'])"
            icon="arrow-trending-up"
            :label="__('dashboard.stat_institution_reach')"
            :value="number_format($totalReachInstitution)"
        />
        <x-stat-card
            :href="route('people.leaderboard', ['metric' => 'reach', 'sort' => 'value'])"
            icon="arrow-trending-up"
            :label="__('dashboard.stat_personal_reach')"
            :value="number_format($totalReachPersonal)"
        />
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
        <div class="lg:col-span-2">
            <x-platform-score-card
                :title="__('comparison.platform_score_title')"
                :subtitle="__('dashboard.platform_score_subtitle')"
                :rows="$platformScoreRows"
                :platform-labels="$platformLabels"
                :scope="\App\Support\ComparisonScope::church()"
                :view-all-url="\App\Support\ComparisonScope::church()->platformComparisonUrl()"
                :show-metrics="false"
            />
        </div>
    </div>

    <div class="mb-8 rounded-2xl border border-black/5 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <div class="mb-4 flex flex-wrap items-start justify-between gap-2">
            <div>
                <h2 class="font-bold text-slate-900 dark:text-white">{{ __('dashboard.map_title') }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400" data-map-summary="gereja">
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

        <div class="mb-4 flex gap-2 border-b border-black/5 dark:border-white/5">
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

                var dataByTab = { gereja: churches, personal: people, institusi: institutions, gabungan: combined };
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

                function activateMapTab(tab) {
                    if (activeLayer) map.removeLayer(activeLayer);
                    activeLayer = layersByTab[tab];
                    map.addLayer(activeLayer);

                    var items = dataByTab[tab];
                    if (items.length > 0) {
                        var bounds = L.latLngBounds(items.map(function (i) { return [i.lat, i.lng]; }));
                        map.fitBounds(bounds, { padding: [24, 24] });
                    } else {
                        map.setView(fallbackCenter, fallbackZoom);
                    }

                    document.querySelectorAll('[data-map-summary]').forEach(function (el) {
                        el.classList.toggle('hidden', el.dataset.mapSummary !== tab);
                    });

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

                activateMapTab('gereja');
            });
        </script>
    @endif
@endsection
