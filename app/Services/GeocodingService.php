<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    private const ENDPOINT = 'https://nominatim.openstreetmap.org/search';

    /**
     * Geocode a place name via OpenStreetMap Nominatim.
     *
     * @return array{lat: float, lon: float}|null
     */
    public function geocode(string $query): ?array
    {
        // No country/region restriction — coverage now spans the whole world (Southeast Asia
        // especially), so anything hard-coded to Indonesia/Jabodetabek (a leftover from when
        // every church really was in Greater Jakarta) would silently return nothing — or worse,
        // force-match a wrong place — for anywhere outside that box. Free-text place names
        // remain inherently ambiguous across countries with no geographic hint to disambiguate
        // by (Church/Union has no stored "country" column) — callers should keep the query as
        // specific as possible (city name, not just a generic district) to compensate.
        $response = Http::withHeaders([
            'User-Agent' => 'Hopenalytics/1.0 (church social media dashboard)',
        ])->get(self::ENDPOINT, [
            'q' => $query,
            'format' => 'json',
            'limit' => 1,
        ]);

        if (! $response->successful()) {
            Log::warning("Geocoding failed for \"{$query}\": HTTP {$response->status()}");

            return null;
        }

        $results = $response->json();

        if (empty($results)) {
            return null;
        }

        return [
            'lat' => (float) $results[0]['lat'],
            'lon' => (float) $results[0]['lon'],
        ];
    }

    /**
     * Build the best-effort place-name query for a church that has no explicit city.
     * Church names commonly follow "GMAHK <Place>" — the place portion usually IS
     * a real, geocodable neighbourhood/district name (e.g. "GMAHK Cawang" → "Cawang").
     */
    public function placeQueryFor(?string $city, string $name): string
    {
        if ($city) {
            return $city;
        }

        return preg_replace('/^GMAHK\s+/i', '', $name);
    }
}
