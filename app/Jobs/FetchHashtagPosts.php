<?php

namespace App\Jobs;

use App\Enums\SocialPlatform;
use App\Models\Hashtag;
use App\Models\HashtagPost;
use App\Services\SocialStats\ApifyCreditsExhaustedException;
use App\Services\SocialStats\InstagramHashtagFetcher;
use App\Services\SocialStats\TikTokHashtagFetcher;
use App\Services\SocialStats\YouTubeHashtagFetcher;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * One job per (hashtag, platform) pair — mirrors FetchSingleChurchData's shape, but a single
 * run here can upsert many HashtagPost rows instead of one stat row, since a hashtag search
 * returns a batch of matching posts rather than one account's own numbers.
 */
class FetchHashtagPosts implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;
    public int $timeout = 90;

    public function __construct(
        public readonly Hashtag $hashtag,
        public readonly SocialPlatform $platform,
    ) {}

    /** Lets cancelling a batch actually stop its remaining un-run jobs — same as FetchSingleChurchData. */
    public function middleware(): array
    {
        return [new SkipIfBatchCancelled];
    }

    public function handle(
        InstagramHashtagFetcher $instagramFetcher,
        TikTokHashtagFetcher $tikTokFetcher,
        YouTubeHashtagFetcher $youTubeFetcher,
    ): void {
        try {
            $posts = match ($this->platform) {
                SocialPlatform::Instagram => $instagramFetcher->fetch($this->hashtag->tag),
                SocialPlatform::TikTok => $tikTokFetcher->fetch($this->hashtag->tag),
                SocialPlatform::YouTube => $youTubeFetcher->fetch($this->hashtag->tag),
                // hashtags:fetch-all never dispatches this platform — Meta has locked down
                // Facebook hashtag/keyword search, so no fetcher exists for it (see the
                // hashtag-tracking plan). Kept here only so this match stays exhaustive.
                SocialPlatform::Facebook => throw new RuntimeException('Facebook hashtag search is not supported.'),
            };

            $now = now();

            foreach ($posts as $post) {
                HashtagPost::updateOrCreate(
                    [
                        'hashtag_id' => $this->hashtag->id,
                        'platform' => $this->platform->value,
                        'external_post_id' => $post['external_post_id'],
                    ],
                    [
                        'post_url' => $post['post_url'],
                        'author_handle' => $post['author_handle'],
                        'caption' => $post['caption'],
                        'likes_count' => $post['likes_count'],
                        'comments_count' => $post['comments_count'],
                        'views_count' => $post['views_count'],
                        'posted_at' => $post['posted_at'],
                        'last_seen_at' => $now,
                        'raw_payload' => $post['raw_payload'],
                    ],
                );
            }
        } catch (ApifyCreditsExhaustedException $e) {
            // Same reasoning as FetchSingleChurchData — retrying won't resolve a credit
            // shortfall within seconds, so fail() outright rather than burn more attempts.
            Log::warning('Apify credits exhausted while fetching hashtag posts', [
                'hashtag_id' => $this->hashtag->id,
                'tag' => $this->hashtag->tag,
                'platform' => $this->platform->value,
                'error' => $e->getMessage(),
            ]);

            $this->fail($e);
        } catch (Throwable $e) {
            Log::warning('Failed to fetch hashtag posts', [
                'hashtag_id' => $this->hashtag->id,
                'tag' => $this->hashtag->tag,
                'platform' => $this->platform->value,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
