<?php

namespace App\Console\Commands;

use App\Models\Church;
use Illuminate\Console\Command;

class ClearAutoGeocodedChurches extends Command
{
    protected $signature = 'churches:clear-auto-geocoded {--force : Skip the confirmation prompt}';

    protected $description = 'Wipe latitude/longitude for churches that were auto-geocoded, never a manually-entered coordinate';

    public function handle(): int
    {
        // geocoded_at IS NOT NULL is the same manual-vs-auto signal GeocodeChurches relies on —
        // see that command for the full reasoning. This exists because --force there can only
        // fix a wrong auto-geocoded coordinate by successfully looking it up again; a row with
        // no city/country to look up just gets skipped, leaving the old wrong coordinate in
        // place forever. Clearing it here first, then re-running churches:geocode, is how those
        // get fixed: once latitude is null they're picked up by the plain (non---force) query
        // too, and stay blank (not wrong) until someone fills in city and country.
        $count = Church::whereNotNull('geocoded_at')->count();

        if ($count === 0) {
            $this->info('Nothing to clear — no auto-geocoded churches found.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("This will clear latitude/longitude for {$count} auto-geocoded church(es). Manually-entered coordinates are never touched. Continue?")) {
            $this->warn('Cancelled.');

            return self::SUCCESS;
        }

        $updated = Church::whereNotNull('geocoded_at')->update([
            'latitude' => null,
            'longitude' => null,
            'geocoded_at' => null,
        ]);

        $this->info("Cleared {$updated} auto-geocoded church coordinate(s). Run `php artisan churches:geocode` next to re-populate any that already have city and country filled in.");

        return self::SUCCESS;
    }
}
