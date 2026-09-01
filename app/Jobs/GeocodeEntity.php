<?php

namespace App\Jobs;

use App\Models\Church;
use App\Models\Institution;
use App\Models\Person;
use App\Services\GeocodingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Looks up one Church/Person/Institution's coordinates from its city+country, one entity at a
 * time — dispatched staggered (see GeocodeDispatcher) rather than run inline, since Nominatim's
 * usage policy caps free lookups at 1 request/second and a bulk import can touch hundreds of
 * rows at once, which has no business running inside a single web request a shared-hosting PHP
 * process could time out on. Picked up automatically by the existing cron-triggered
 * `queue:work --stop-when-empty` (see routes/console.php) — no SSH or persistent worker needed,
 * which is what makes this reachable for admin_uni/admin_daerah through the Bulk Import/Import
 * Lokasi pages, not just a superadmin running the `{type}:geocode` artisan commands directly.
 */
class GeocodeEntity implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 30;

    public function __construct(
        public readonly Church|Person|Institution $entity,
    ) {}

    public function handle(GeocodingService $geocoding): void
    {
        // Re-fetch rather than trust the serialized snapshot — city/country (or the coordinate
        // itself) may have changed in the time this sat queued/delayed.
        $entity = $this->entity->fresh();

        if (! $entity || empty($entity->city) || empty($entity->country)) {
            return;
        }

        // Never overwrite a manually-entered coordinate (geocoded_at null, latitude already
        // set) — same signal GeocodeChurches and friends rely on. GeocodeDispatcher's own query
        // already excludes these, but re-checking here closes the same race FetchSingleChurchData
        // guards against: whatever was true when this was dispatched might not be true anymore.
        if ($entity->latitude !== null && $entity->geocoded_at === null) {
            return;
        }

        $result = $geocoding->geocode($geocoding->placeQueryFor($entity->city, $entity->country));

        if ($result) {
            $entity->update([
                'latitude' => $result['lat'],
                'longitude' => $result['lon'],
                'geocoded_at' => now(),
            ]);
        }
        // No match: leave whatever coordinate (or lack of one) already there untouched, same as
        // the artisan commands' own failed-lookup branch.
    }
}
