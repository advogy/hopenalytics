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
        $response = Http::withHeaders([
            'User-Agent' => 'Churchnalytics/1.0 (church social media dashboard)',
        ])->get(self::ENDPOINT, [
            'q' => $query,
            'format' => 'json',
            'limit' => 1,
            'countrycodes' => 'id',
            // Hard-restrict to Greater Jakarta (Jabodetabek), where these churches are based — a
            // soft bias (bounded=0) still let a same-named-but-wrong match through from Maluku.
            'viewbox' => '106.2,-5.9,107.2,-6.6',
            'bounded' => 1,
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
            return "{$city}, Indonesia";
        }

        $place = preg_replace('/^GMAHK\s+/i', '', $name);

        return "{$place}, Indonesia";
    }
}
