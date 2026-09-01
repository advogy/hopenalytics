<?php

namespace App\Services;

/**
 * Matches a lat/lng against Southeast Asia's admin-1 (province/state) boundaries — used by the
 * dashboard map's regional growth choropleth to figure out which region a given church/personal/
 * institution actually falls in, since the app only ever stores a raw coordinate, never a
 * region name.
 *
 * public/data/sea-admin1.geojson is a simplified (mapshaper -simplify dp 3%) merge of
 * geoBoundaries' (CC-BY 4.0, https://www.geoboundaries.org) ADM1 layer for the 11 Southeast
 * Asian countries (ID, MY, PH, TH, VN, MM, LA, KH, SG, BN, TL) — trimmed from ~11MB of raw
 * boundary data down to ~400KB, small enough to ship to the browser for rendering the same
 * shapes this class matches against server-side.
 */
class GeoBoundaryMatcher
{
    /** @var array<int, array{name: string, country: string, geometry: array, bbox: array{0: float, 1: float, 2: float, 3: float}}> */
    private array $regions;

    public function __construct()
    {
        $data = json_decode(file_get_contents(public_path('data/sea-admin1.geojson')), true);

        $this->regions = array_map(function (array $feature) {
            return [
                'name' => $feature['properties']['shapeName'],
                'country' => $feature['properties']['shapeGroup'],
                'geometry' => $feature['geometry'],
                'bbox' => $this->boundingBoxOf($feature['geometry']['coordinates']),
            ];
        }, $data['features']);
    }

    /** @return array{name: string, country: string}|null */
    public function regionFor(float $lat, float $lng): ?array
    {
        foreach ($this->regions as $region) {
            // Cheap bounding-box rejection before the much more expensive ray-cast below —
            // most of the 287 regions can be ruled out from four comparisons alone.
            [$minLng, $minLat, $maxLng, $maxLat] = $region['bbox'];
            if ($lng < $minLng || $lng > $maxLng || $lat < $minLat || $lat > $maxLat) {
                continue;
            }

            if ($this->pointInGeometry($lat, $lng, $region['geometry'])) {
                return ['name' => $region['name'], 'country' => $region['country']];
            }
        }

        return null;
    }

    private function pointInGeometry(float $lat, float $lng, array $geometry): bool
    {
        if ($geometry['type'] === 'Polygon') {
            return $this->pointInPolygon($lat, $lng, $geometry['coordinates']);
        }

        if ($geometry['type'] === 'MultiPolygon') {
            foreach ($geometry['coordinates'] as $polygon) {
                if ($this->pointInPolygon($lat, $lng, $polygon)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** $rings[0] is the outer boundary, every ring after it is a hole cut out of it. */
    private function pointInPolygon(float $lat, float $lng, array $rings): bool
    {
        if (! $this->rayCast($lat, $lng, $rings[0])) {
            return false;
        }

        for ($i = 1; $i < count($rings); $i++) {
            if ($this->rayCast($lat, $lng, $rings[$i])) {
                return false;
            }
        }

        return true;
    }

    /** Standard even-odd ray-casting test. GeoJSON coordinates are [lng, lat], not [lat, lng]. */
    private function rayCast(float $lat, float $lng, array $ring): bool
    {
        $inside = false;
        $count = count($ring);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $xi = $ring[$i][0];
            $yi = $ring[$i][1];
            $xj = $ring[$j][0];
            $yj = $ring[$j][1];

            $intersects = (($yi > $lat) !== ($yj > $lat))
                && ($lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi);

            if ($intersects) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    private function boundingBoxOf(array $coordinates): array
    {
        $minLng = INF;
        $minLat = INF;
        $maxLng = -INF;
        $maxLat = -INF;

        $walk = function (array $node) use (&$walk, &$minLng, &$minLat, &$maxLng, &$maxLat): void {
            if (is_numeric($node[0])) {
                $minLng = min($minLng, $node[0]);
                $maxLng = max($maxLng, $node[0]);
                $minLat = min($minLat, $node[1]);
                $maxLat = max($maxLat, $node[1]);

                return;
            }

            foreach ($node as $child) {
                $walk($child);
            }
        };

        $walk($coordinates);

        return [$minLng, $minLat, $maxLng, $maxLat];
    }
}
