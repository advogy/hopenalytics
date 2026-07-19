<?php

namespace App\Console\Commands;

use App\Models\Church;
use App\Services\GeocodingService;
use Illuminate\Console\Command;

class GeocodeChurches extends Command
{
    protected $signature = 'churches:geocode {--force : Re-geocode churches that already have coordinates}';

    protected $description = 'Look up latitude/longitude for churches via OpenStreetMap Nominatim';

    public function handle(GeocodingService $geocoding): int
    {
        $churches = Church::query()
            ->where('is_active', true)
            ->when(! $this->option('force'), fn ($query) => $query->whereNull('geocoded_at'))
            ->get();

        if ($churches->isEmpty()) {
            $this->info('Nothing to geocode.');

            return self::SUCCESS;
        }

        foreach ($churches as $i => $church) {
            $query = $geocoding->placeQueryFor($church->city, $church->name);

            $result = $geocoding->geocode($query);

            if ($result) {
                $church->update([
                    'latitude' => $result['lat'],
                    'longitude' => $result['lon'],
                    'geocoded_at' => now(),
                ]);
                $this->line("✓ {$church->name} ({$query}) → {$result['lat']}, {$result['lon']}");
            } else {
                // Clear any stale coordinates from a previous (possibly wrong) match.
                $church->update(['latitude' => null, 'longitude' => null, 'geocoded_at' => null]);
                $this->warn("✗ {$church->name} ({$query}) → tidak ditemukan");
            }

            // Nominatim's usage policy caps free lookups at 1 request per second.
            if ($i < $churches->count() - 1) {
                sleep(1);
            }
        }

        return self::SUCCESS;
    }
}
