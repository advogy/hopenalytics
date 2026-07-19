<?php

namespace App\Services\SocialStats;

use RuntimeException;

class FacebookStatsFetcher
{
    // Actor resmi Apify: https://apify.com/apify/facebook-pages-scraper
    private const ACTOR_ID = 'apify~facebook-pages-scraper';

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
        ];
    }
}
