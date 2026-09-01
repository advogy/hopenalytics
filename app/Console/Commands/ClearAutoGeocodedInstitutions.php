<?php

namespace App\Console\Commands;

use App\Models\Institution;
use Illuminate\Console\Command;

class ClearAutoGeocodedInstitutions extends Command
{
    protected $signature = 'institutions:clear-auto-geocoded {--force : Skip the confirmation prompt}';

    protected $description = 'Wipe latitude/longitude for institutions that were auto-geocoded, never a manually-entered coordinate';

    public function handle(): int
    {
        // Same reasoning as ClearAutoGeocodedChurches — see that command.
        $count = Institution::whereNotNull('geocoded_at')->count();

        if ($count === 0) {
            $this->info('Nothing to clear — no auto-geocoded institutions found.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("This will clear latitude/longitude for {$count} auto-geocoded institution(s). Manually-entered coordinates are never touched. Continue?")) {
            $this->warn('Cancelled.');

            return self::SUCCESS;
        }

        $updated = Institution::whereNotNull('geocoded_at')->update([
            'latitude' => null,
            'longitude' => null,
            'geocoded_at' => null,
        ]);

        $this->info("Cleared {$updated} auto-geocoded institution coordinate(s). Run `php artisan institutions:geocode` next to re-populate any that already have city and country filled in.");

        return self::SUCCESS;
    }
}
