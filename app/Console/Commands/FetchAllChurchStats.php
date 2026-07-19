<?php

namespace App\Console\Commands;

use App\Jobs\FetchSingleChurchData;
use App\Models\ChurchSocial;
use Illuminate\Console\Command;

class FetchAllChurchStats extends Command
{
    protected $signature = 'church-stats:fetch-all';

    protected $description = 'Dispatch stat-fetching jobs for every active church social account';

    public function handle(): int
    {
        $delaySeconds = 0;

        ChurchSocial::query()
            ->where('is_active', true)
            ->where('is_auto_fetch', true)
            ->whereHas('church', fn ($q) => $q->where('is_active', true))
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
