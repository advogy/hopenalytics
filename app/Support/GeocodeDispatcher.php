<?php

namespace App\Support;

use App\Jobs\GeocodeEntity;
use Illuminate\Database\Eloquent\Builder;

/**
 * Staggers GeocodeEntity jobs across a query's results, one second apart — same 1 request/second
 * pacing GeocodeChurches (and friends) already sleep() between lookups for, just spread across
 * queued jobs instead of a single blocking foreground loop. Used by BulkDataImportController,
 * the one web-reachable way city/country gets bulk-filled in (the separate, narrower
 * LocationImportController this used to also serve was retired once Bulk Import's own "Data"
 * sheet already covered the exact same city/country fields, per the user's explicit call).
 */
class GeocodeDispatcher
{
    public static function dispatchFor(Builder $query): int
    {
        $count = 0;
        $delaySeconds = 0;

        $query->clone()
            ->whereNull('latitude')
            ->whereNotNull('city')
            ->whereNotNull('country')
            ->chunkById(50, function ($entities) use (&$count, &$delaySeconds) {
                foreach ($entities as $entity) {
                    GeocodeEntity::dispatch($entity::class, $entity->id)->delay(now()->addSeconds($delaySeconds));
                    $delaySeconds++;
                    $count++;
                }
            });

        return $count;
    }
}
