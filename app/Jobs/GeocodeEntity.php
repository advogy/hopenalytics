<?php

namespace App\Jobs;

use App\Services\GeocodingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

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
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 30;

    /**
     * Deliberately takes a class+ID pair rather than the model itself: Illuminate's
     * SerializesModels trait re-hydrates a serialized model via firstOrFail() when a delayed
     * job comes back off the queue, so a row deleted (or deactivated churches never get deleted,
     * but a mistaken/duplicate row might be) in the window between dispatch and this actually
     * running would throw ModelNotFoundException and land the whole job in failed_jobs — a
     * needless failure notification for something this job already knows how to shrug off
     * gracefully. Looking it up with find() (never firstOrFail()) here instead makes a
     * since-deleted row a normal no-op, same as any other "nothing to do" case below.
     */
    public function __construct(
        public readonly string $entityClass,
        public readonly int $entityId,
    ) {}

    public function handle(GeocodingService $geocoding): void
    {
        $entity = $this->entityClass::find($this->entityId);

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
