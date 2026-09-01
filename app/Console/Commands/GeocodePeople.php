<?php

namespace App\Console\Commands;

use App\Models\Person;
use App\Services\GeocodingService;
use Illuminate\Console\Command;

class GeocodePeople extends Command
{
    protected $signature = 'people:geocode {--force : Also re-check people that were previously auto-geocoded (e.g. to fix ones caught by a since-fixed geocoding bug) — never touches a manually-typed coordinate either way}';

    protected $description = 'Look up latitude/longitude for Personal accounts via OpenStreetMap Nominatim';

    public function handle(GeocodingService $geocoding): int
    {
        // Same geocoded_at-as-manual-vs-auto distinction as GeocodeChurches — see that file for
        // the full reasoning, mirrored here verbatim since Person has the identical
        // city/country/latitude/longitude/geocoded_at shape.
        $query = Person::query()->where('is_active', true);

        if ($this->option('force')) {
            $query->where(fn ($q) => $q->whereNotNull('geocoded_at')->orWhereNull('latitude'));
        } else {
            $query->whereNull('latitude');
        }

        $people = $query->get();

        if ($people->isEmpty()) {
            $this->info('Nothing to geocode.');

            return self::SUCCESS;
        }

        $requestCount = 0;

        foreach ($people as $person) {
            // No city AND country, no attempt — see GeocodeChurches for the full reasoning
            // (guessing a place from an entity's own name risked landing in the wrong country
            // entirely, confirmed happening). No API call made, so this doesn't count against
            // the rate-limit pacing below.
            if (empty($person->city) || empty($person->country)) {
                $this->warn("⊘ {$person->name} → kota/negara belum diisi, dilewati");

                continue;
            }

            if ($requestCount > 0) {
                // Nominatim's usage policy caps free lookups at 1 request per second.
                sleep(1);
            }
            $requestCount++;

            $placeQuery = $geocoding->placeQueryFor($person->city, $person->country);
            $result = $geocoding->geocode($placeQuery);

            if ($result) {
                $person->update([
                    'latitude' => $result['lat'],
                    'longitude' => $result['lon'],
                    'geocoded_at' => now(),
                ]);
                $this->line("✓ {$person->name} ({$placeQuery}) → {$result['lat']}, {$result['lon']}");
            } elseif ($person->latitude === null) {
                // Nothing to lose — was already blank.
                $this->warn("✗ {$person->name} ({$placeQuery}) → tidak ditemukan");
            } else {
                // A --force re-check that comes back empty leaves the existing coordinate
                // untouched rather than wiping it.
                $this->warn("✗ {$person->name} ({$placeQuery}) → tidak ditemukan, koordinat lama dipertahankan");
            }
        }

        return self::SUCCESS;
    }
}
