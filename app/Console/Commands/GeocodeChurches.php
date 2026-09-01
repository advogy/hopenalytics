<?php

namespace App\Console\Commands;

use App\Models\Church;
use App\Services\GeocodingService;
use Illuminate\Console\Command;

class GeocodeChurches extends Command
{
    protected $signature = 'churches:geocode {--force : Also re-check churches that were previously auto-geocoded (e.g. to fix ones caught by a since-fixed geocoding bug) — never touches a manually-typed coordinate either way}';

    protected $description = 'Look up latitude/longitude for churches via OpenStreetMap Nominatim';

    public function handle(GeocodingService $geocoding): int
    {
        // geocoded_at is the one signal telling a manual coordinate apart from an auto-geocoded
        // one: both can leave latitude/longitude set, but only an auto-geocoded row also has
        // geocoded_at. A manual entry (geocoded_at null, latitude already set) is never a target
        // here, force or not — it was typed in on purpose and this command has no way to know
        // whether a fresh lookup would actually still agree with it.
        $query = Church::query()->where('is_active', true);

        if ($this->option('force')) {
            $query->where(fn ($q) => $q->whereNotNull('geocoded_at')->orWhereNull('latitude'));
        } else {
            $query->whereNull('latitude');
        }

        $churches = $query->get();

        if ($churches->isEmpty()) {
            $this->info('Nothing to geocode.');

            return self::SUCCESS;
        }

        $requestCount = 0;

        foreach ($churches as $church) {
            // No city AND country, no attempt — per the user's explicit call, a bare city name
            // alone is still ambiguous across countries, and guessing a place from the church's
            // own name (the old "GMAHK <Place>" fallback) risked landing in the wrong country
            // entirely with nothing to disambiguate it (confirmed happening). Left
            // blank/unchanged until both are filled in, rather than risk a wrong marker. No API
            // call made, so this doesn't count against the rate-limit pacing below.
            if (empty($church->city) || empty($church->country)) {
                $this->warn("⊘ {$church->name} → kota/negara belum diisi, dilewati");

                continue;
            }

            if ($requestCount > 0) {
                // Nominatim's usage policy caps free lookups at 1 request per second.
                sleep(1);
            }
            $requestCount++;

            $placeQuery = $geocoding->placeQueryFor($church->city, $church->country);
            $result = $geocoding->geocode($placeQuery);

            if ($result) {
                $church->update([
                    'latitude' => $result['lat'],
                    'longitude' => $result['lon'],
                    'geocoded_at' => now(),
                ]);
                $this->line("✓ {$church->name} ({$placeQuery}) → {$result['lat']}, {$result['lon']}");
            } elseif ($church->latitude === null) {
                // Nothing to lose — was already blank.
                $this->warn("✗ {$church->name} ({$placeQuery}) → tidak ditemukan");
            } else {
                // A --force re-check that comes back empty (a transient Nominatim hiccup, a
                // place name it genuinely can't resolve, etc.) leaves the existing coordinate
                // untouched rather than wiping it — losing a possibly-still-fine value to a
                // failed lookup would defeat the point of a remediation run.
                $this->warn("✗ {$church->name} ({$placeQuery}) → tidak ditemukan, koordinat lama dipertahankan");
            }
        }

        return self::SUCCESS;
    }
}
