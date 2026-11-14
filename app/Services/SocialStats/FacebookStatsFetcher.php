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

    /**
     * Many pages in one actor run (see PrefetchSocialStats), keyed by the page URL as passed in
     * — one Apify run for a whole group instead of one per page. A page is matched back by its
     * normalized URL (the result's facebookUrl/pageUrl, or its page name as the URL path);
     * unmatched pages simply aren't in the array, and their own fetch() runs as before. Only
     * the page itself: its recent posts are still fetched per page inside fetch().
     *
     * @param  list<string>  $pageUrls
     * @return array<string, array> raw page items keyed by the original URL
     */
    public function fetchPagesBatch(array $pageUrls): array
    {
        $byKey = collect($pageUrls)->mapWithKeys(fn ($url) => [$this->matchKey($this->normalizeUrl($url)) => $url]);

        $items = $this->apify->runActorSyncAll(self::ACTOR_ID, [
            'startUrls' => $byKey->keys()->unique()->map(fn ($key, $i) => ['url' => $this->normalizeUrl($byKey[$key])])->values()->all(),
        ], ApifyClient::BATCH_TIMEOUT_SECONDS);

        $matched = [];

        foreach ($items as $item) {
            if (! isset($item['followers']) && ! isset($item['likes'])) {
                continue;
            }

            foreach ([$item['facebookUrl'] ?? null, $item['pageUrl'] ?? null, isset($item['pageName']) ? 'https://www.facebook.com/'.$item['pageName'] : null] as $candidate) {
                $key = $candidate ? $this->matchKey($candidate) : null;

                if ($key && $byKey->has($key) && ! isset($matched[$byKey[$key]])) {
                    $matched[$byKey[$key]] = $item;
                    break;
                }
            }
        }

        return $matched;
    }

    /** facebook.com/Foo/, https://www.facebook.com/foo and m.facebook.com/foo all match. */
    private function matchKey(string $url): string
    {
        $url = strtolower(trim($url));
        $url = preg_replace('#^https?://#', '', $url) ?? $url;
        $url = preg_replace('#^(www\.|m\.|web\.)#', '', $url) ?? $url;

        return rtrim($url, '/');
    }

    /** $prefetchedPage: this page's item from fetchPagesBatch(), when the batch already got it. */
    public function fetch(string $pageUrl, ?array $prefetchedPage = null): array
    {
        $pageUrl = $this->normalizeUrl($pageUrl);

        $item = $prefetchedPage ?? $this->apify->runActorSync(self::ACTOR_ID, [
            'startUrls' => [['url' => $pageUrl]],
        ]);

        if (! isset($item['followers']) && ! isset($item['likes'])) {
            throw new RuntimeException("Facebook page not found: {$pageUrl}");
        }

        return [
            'followers_count' => (int) ($item['followers'] ?? $item['likes'] ?? 0),
            'following_count' => (int) ($item['followings'] ?? 0),
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
            'recent_posts_comments' => (int) $posts->sum('comments'),
            // The full post sample — a free ride on the same call above, picked back out by
            // HashtagCandidateExtractor for hashtag matching. Never persisted to church_stats
            // itself; FetchSingleChurchData strips this key before saving.
            '_recent_posts_raw' => $posts->all(),
        ];
    }

    /**
     * Strips share-link tracking noise off a pasted Facebook URL before it ever reaches Apify.
     * Mobile "Share" buttons produce URLs like profile.php?id=X&rdid=...&share_url=...# — the
     * extra params (rdid, a URL-encoded share_url nested inside the query string, a trailing
     * bare #) are enough to make both actors fail with "page not found" even though the id=X is
     * right there and perfectly valid on its own. Only profile.php?id=X and a plain
     * facebook.com/pagename path are normalized; anything else (e.g. a facebook.com/share/...
     * short link, which needs a login-gated redirect to resolve to a real page) is passed
     * through unchanged rather than guessed at.
     */
    private function normalizeUrl(string $url): string
    {
        $parts = parse_url(trim($url));

        if (! isset($parts['host'], $parts['path']) || ! str_contains($parts['host'], 'facebook.com')) {
            return $url;
        }

        if (str_ends_with($parts['path'], 'profile.php')) {
            parse_str($parts['query'] ?? '', $query);

            if (isset($query['id'])) {
                return "https://www.facebook.com/profile.php?id={$query['id']}";
            }

            return $url;
        }

        return 'https://www.facebook.com'.rtrim($parts['path'], '/');
    }
}
