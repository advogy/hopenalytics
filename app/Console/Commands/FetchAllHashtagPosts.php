<?php

namespace App\Console\Commands;

use App\Models\Hashtag;
use App\Support\HashtagRescanDispatcher;
use Illuminate\Console\Command;

/**
 * Manual "rescan every registered account now" trigger — e.g. right after adding a new tracked
 * hashtag, without waiting for each account's next scheduled auto-fetch to naturally pick it up
 * (see MatchAccountHashtags, which this same command's normal path piggybacks on automatically
 * every week). Every dispatch here fetches live (no pre-fetched posts to reuse, unlike the
 * automatic path), so — unlike the weekly auto-fetch — this always spends real Apify credits/
 * YouTube quota across every account, not just Instagram/TikTok/Facebook's "free" ones. Not
 * scheduled by default; run manually from the CLI, or via HashtagController::rescan()'s own
 * "Scan Ulang Sekarang" button — both share HashtagRescanDispatcher's own dispatch logic.
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

        $total = HashtagRescanDispatcher::dispatch();

        $this->info("Dispatched {$total} jobs, total stagger window: ".($total * 5).'s');

        return self::SUCCESS;
    }
}
