<?php

namespace App\Services\SocialStats;

class InstagramStatsFetcher
{
    // Actor resmi Apify: https://apify.com/apify/instagram-profile-scraper
    private const ACTOR_ID = 'apify~instagram-profile-scraper';

    public function __construct(
        private readonly ApifyClient $apify,
    ) {}

    public function fetch(string $username): array
    {
        $item = $this->apify->runActorSync(self::ACTOR_ID, [
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
            'raw_payload' => $item,
        ];
    }
}
