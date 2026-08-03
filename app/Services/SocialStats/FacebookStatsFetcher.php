<?php

namespace App\Services\SocialStats;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class FacebookStatsFetcher
{
    // Actor resmi Apify: https://apify.com/apify/facebook-pages-scraper
    private const ACTOR_ID = 'apify~facebook-pages-scraper';

    // Actor resmi Apify: https://apify.com/apify/facebook-posts-scraper
    private const POSTS_ACTOR_ID = 'apify~facebook-posts-scraper';

    // How many of the most recent posts to sample for likes/shares (not a lifetime total —
    // unlike Instagram's postsCount/TikTok's video count, Facebook exposes no lifetime post
    // count via scraping, only individual posts).
    private const RECENT_POSTS_SAMPLE = 10;

    public function __construct(
        private readonly ApifyClient $apify,
    ) {}

    public function fetch(string $pageUrl): array
    {
        $item = $this->apify->runActorSync(self::ACTOR_ID, [
            'startUrls' => [['url' => $pageUrl]],
        ]);

        if (! isset($item['followers']) && ! isset($item['likes'])) {
            throw new RuntimeException("Facebook page not found: {$pageUrl}");
        }

        return [
            'followers_count' => (int) ($item['followers'] ?? $item['likes'] ?? 0),
            'raw_payload' => $item,
            ...$this->fetchRecentPosts($pageUrl),
        ];
    }

    /**
     * Best-effort: a failure here (rate limit, actor hiccup, a page with no public posts) never
     * fails the whole fetch, since the follower count above is the metric everything else
     * (growth score, leaderboards) actually depends on — recent post stats are a bonus. Credit
     * exhaustion is the one exception, re-thrown so the caller's existing fallback-to-manual
     * handling (see FetchSingleChurchData) still kicks in instead of silently retrying forever.
     */
    private function fetchRecentPosts(string $pageUrl): array
    {
        try {
            $posts = $this->apify->runActorSyncAll(self::POSTS_ACTOR_ID, [
                'startUrls' => [['url' => $pageUrl]],
                'resultsLimit' => self::RECENT_POSTS_SAMPLE,
            ]);
        } catch (ApifyCreditsExhaustedException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::warning('Facebook recent-posts fetch failed, skipping', [
                'page_url' => $pageUrl,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        $posts = collect($posts);

        return [
            'recent_posts_count' => $posts->count(),
            'recent_posts_likes' => (int) $posts->sum('likes'),
            'recent_posts_shares' => (int) $posts->sum('shares'),
        ];
    }
}
