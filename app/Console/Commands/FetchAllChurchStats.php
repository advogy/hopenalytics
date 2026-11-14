<?php

namespace App\Console\Commands;

use App\Jobs\FetchSingleChurchData;
use App\Jobs\PrefetchSocialStats;
use App\Models\ChurchSocial;
use Illuminate\Console\Command;

class FetchAllChurchStats extends Command
{
    protected $signature = 'church-stats:fetch-all';

    protected $description = 'Dispatch stat-fetching jobs for every active, auto-fetchable social account (church, personal, institution, union, or conference)';

    public function handle(): int
    {
        ChurchSocial::disableAutoFetchForMissingFacebookProfileUrl();

        $delaySeconds = 0;

        $accounts = ChurchSocial::query()
            ->where('is_active', true)
            ->where('is_auto_fetch', true)
            ->ownerActive()
            ->consentGranted()
            ->readyToFetch();

        // Group Apify runs first (see PrefetchSocialStats): dispatched ahead of the accounts' own
        // jobs, which are staggered behind them, so most find their result ready.
        foreach (PrefetchSocialStats::jobsFor((clone $accounts)->get(['id', 'platform'])) as $prefetch) {
            dispatch($prefetch);
        }

        $accounts
            ->chunkById(50, function ($churchSocials) use (&$delaySeconds) {
                foreach ($churchSocials as $churchSocial) {
                    FetchSingleChurchData::dispatch($churchSocial)
                        ->delay(now()->addSeconds($delaySeconds));

                    $delaySeconds += 5;
                }
            });

        $this->info("Dispatched jobs, total stagger window: {$delaySeconds}s");

        return self::SUCCESS;
    }
}
