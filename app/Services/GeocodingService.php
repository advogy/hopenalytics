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
        // remain inherently ambiguous across countries — placeQueryFor() below always pairs a
        // city with its country for that reason, never a bare city alone.
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
     * Build the geocoding query for an entity's city + country — both required (not just city),
     * per the user's explicit call: a bare city name alone is still ambiguous across countries
     * (many towns share a name), and the old "guess a place from the name when city is blank"
     * fallback (a church's "GMAHK <Place>" name portion used to feed this) risked landing in the
     * wrong country entirely with nothing to disambiguate it (confirmed happening — e.g. "GMAHK
     * Salemba" resolved to Sulawesi, "GMAHK Taman Harapan" to Malaysia). Every caller checks for
     * both city AND country themselves and skips geocoding without either, leaving it blank
     * until someone fills both in, rather than risk a wrong marker.
     */
    public function placeQueryFor(string $city, string $country): string
    {
        return "{$city}, {$country}";
    }
}
