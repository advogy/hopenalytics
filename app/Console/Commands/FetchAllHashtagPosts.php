<?php

namespace App\Console\Commands;

use App\Enums\SocialPlatform;
use App\Jobs\FetchHashtagPosts;
use App\Models\Hashtag;
use Illuminate\Console\Command;

class FetchAllHashtagPosts extends Command
{
    protected $signature = 'hashtags:fetch-all';

    protected $description = 'Dispatch post-fetching jobs for every active tracked hashtag, across every platform with a working hashtag-search fetcher';

    // Facebook has no working hashtag-search fetcher — see FetchHashtagPosts.
    private const PLATFORMS = [SocialPlatform::Instagram, SocialPlatform::TikTok, SocialPlatform::YouTube];

    public function handle(): int
    {
        $delaySeconds = 0;

        Hashtag::where('is_active', true)->chunkById(50, function ($hashtags) use (&$delaySeconds) {
            foreach ($hashtags as $hashtag) {
                foreach (self::PLATFORMS as $platform) {
                    FetchHashtagPosts::dispatch($hashtag, $platform)
                        ->delay(now()->addSeconds($delaySeconds));

                    $delaySeconds += 5;
                }
            }
        });

        $this->info("Dispatched jobs, total stagger window: {$delaySeconds}s");

        return self::SUCCESS;
    }
}
