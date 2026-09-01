<?php

namespace App\Support;

use App\Jobs\GeocodeEntity;
use Illuminate\Database\Eloquent\Builder;

/**
 * Staggers GeocodeEntity jobs across a query's results, one second apart — same 1 request/second
 * pacing GeocodeChurches (and friends) already sleep() between lookups for, just spread across
 * queued jobs instead of a single blocking foreground loop. Shared by LocationImportController
 * and BulkDataImportController, the two web-reachable ways city/country gets bulk-filled in.
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
                    GeocodeEntity::dispatch($entity)->delay(now()->addSeconds($delaySeconds));
                    $delaySeconds++;
                    $count++;
                }
            });

        return $count;
    }
}
