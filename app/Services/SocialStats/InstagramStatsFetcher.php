<?php

namespace App\Services\SocialStats;

class InstagramStatsFetcher
{
    // Actor resmi Apify: https://apify.com/apify/instagram-profile-scraper
    private const ACTOR_ID = 'apify~instagram-profile-scraper';

    public function __construct(
        private readonly ApifyClient $apify,
    ) {}

    /**
     * Many profiles in one actor run (see PrefetchSocialStats), keyed by lowercase username —
     * one Apify run for a whole group instead of one per account. Profiles missing from the
     * result simply aren't in the array; their own fetch() then runs as before.
     *
     * @param  list<string>  $usernames
     * @return array<string, array> raw profile items
     */
    public function fetchBatch(array $usernames): array
    {
        $items = $this->apify->runActorSyncAll(self::ACTOR_ID, ['usernames' => array_values($usernames)], ApifyClient::BATCH_TIMEOUT_SECONDS);

        return collect($items)
            ->filter(fn ($item) => isset($item['username']) && isset($item['followersCount']))
            ->keyBy(fn ($item) => strtolower(ltrim($item['username'], '@')))
            ->all();
    }

    /** $prefetched: this profile's item from fetchBatch(), when the batch already got it. */
    public function fetch(string $username, ?array $prefetched = null): array
    {
        $item = $prefetched ?? $this->apify->runActorSync(self::ACTOR_ID, [
            'usernames' => [$username],
        ]);

        // Reels are posts of productType "clips"; only the ~12 most recent posts are
        // returned here, so this is a recent-content sample, not a lifetime total.
        $reels = collect($item['latestPosts'] ?? [])->filter(fn ($post) => ($post['productType'] ?? null) === 'clips');

        return [
            'followers_count' => (int) ($item['followersCount'] ?? 0),
            'following_count' => (int) ($item['followsCount'] ?? 0),
            'posts_count' => (int) ($item['postsCount'] ?? 0),
            'recent_reels_count' => $reels->count(),
            'recent_reels_views' => (int) $reels->sum('videoViewCount'),
            'recent_reels_comments' => (int) $reels->sum('commentsCount'),
            'raw_payload' => $item,
        ];
    }
}
