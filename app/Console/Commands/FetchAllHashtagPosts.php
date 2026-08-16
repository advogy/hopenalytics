<?php

namespace App\Console\Commands;

use App\Jobs\MatchAccountHashtags;
use App\Models\ChurchSocial;
use App\Models\Hashtag;
use Illuminate\Console\Command;

/**
 * Manual "rescan every registered account now" trigger — e.g. right after adding a new tracked
 * hashtag, without waiting for each account's next scheduled auto-fetch to naturally pick it up
 * (see MatchAccountHashtags, which this same command's normal path piggybacks on automatically
 * every week). Every dispatch here fetches live (no pre-fetched posts to reuse, unlike the
 * automatic path), so — unlike the weekly auto-fetch — this always spends real Apify credits/
 * YouTube quota across every account, not just Instagram/TikTok/Facebook's "free" ones. Not
 * scheduled; run manually from the CLI.
 */
class FetchAllHashtagPosts extends Command
{
    protected $signature = 'hashtags:rescan';

    protected $description = 'Rescan every registered social account\'s recent content for actively-tracked hashtags';

    public function handle(): int
    {
        if (! Hashtag::where('is_active', true)->exists()) {
            $this->info('No active hashtags tracked — nothing to rescan.');

            return self::SUCCESS;
        }

        $delaySeconds = 0;
        $total = 0;

        ChurchSocial::query()
            ->where('is_active', true)
            ->where('is_auto_fetch', true)
            ->ownerActive()
            ->chunkById(50, function ($socials) use (&$delaySeconds, &$total) {
                foreach ($socials as $social) {
                    MatchAccountHashtags::dispatch($social)->delay(now()->addSeconds($delaySeconds));

                    $delaySeconds += 5;
                    $total++;
                }
            });

        $this->info("Dispatched {$total} jobs, total stagger window: {$delaySeconds}s");

        return self::SUCCESS;
    }
}
