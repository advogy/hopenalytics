<?php

namespace App\Console\Commands;

use App\Models\Person;
use Illuminate\Console\Command;

class ClearAutoGeocodedPeople extends Command
{
    protected $signature = 'people:clear-auto-geocoded {--force : Skip the confirmation prompt}';

    protected $description = 'Wipe latitude/longitude for people that were auto-geocoded, never a manually-entered coordinate';

    public function handle(): int
    {
        // Same reasoning as ClearAutoGeocodedChurches — see that command.
        $count = Person::whereNotNull('geocoded_at')->count();

        if ($count === 0) {
            $this->info('Nothing to clear — no auto-geocoded people found.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("This will clear latitude/longitude for {$count} auto-geocoded people. Manually-entered coordinates are never touched. Continue?")) {
            $this->warn('Cancelled.');

            return self::SUCCESS;
        }

        $updated = Person::whereNotNull('geocoded_at')->update([
            'latitude' => null,
            'longitude' => null,
            'geocoded_at' => null,
        ]);

        $this->info("Cleared {$updated} auto-geocoded person coordinate(s). Run `php artisan people:geocode` next to re-populate any that already have city and country filled in.");

        return self::SUCCESS;
    }
}
