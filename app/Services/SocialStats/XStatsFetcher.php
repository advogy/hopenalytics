<?php

namespace App\Services\SocialStats;

class XStatsFetcher
{
    // Verified live on the Apify store: https://apify.com/apidojo/twitter-user-scraper
    // — single-call profile lookup, same shape as InstagramStatsFetcher (not Facebook's
    // two-actor workaround). statusesCount is a lifetime cumulative total, no "recent
    // tweets in this fetch" count — see social-account-card.blade.php's performance
    // border, which handles this the same way it already does for YouTube's videoCount
    // (compares against the previous recorded point instead).
    private const ACTOR_ID = 'apidojo~twitter-user-scraper';

    public function __construct(
        private readonly ApifyClient $apify,
    ) {}

    public function fetch(string $username): array
    {
        $item = $this->apify->runActorSync(self::ACTOR_ID, [
            'twitterHandles' => [$username],
        ]);

        return [
            'followers_count' => (int) ($item['followers'] ?? 0),
            'following_count' => (int) ($item['following'] ?? 0),
            'posts_count' => (int) ($item['statusesCount'] ?? 0),
            'raw_payload' => $item,
        ];
    }
}
