<?php

namespace App\Services\SocialStats;

class TikTokHashtagFetcher
{
    // Actor: https://apify.com/clockworks/tiktok-scraper — the general-purpose scraper (not
    // clockworks~tiktok-profile-scraper, which TikTokStatsFetcher uses for a single account's
    // own stats); this one accepts a `hashtags` input for keyword/tag search.
    private const ACTOR_ID = 'clockworks~tiktok-scraper';

    // How many videos to sample per hashtag — a recent/top sample, not exhaustive.
    private const RESULTS_LIMIT = 30;

    public function __construct(
        private readonly ApifyClient $apify,
    ) {}

    /** @return array<int, array{external_post_id: string, post_url: string, author_handle: ?string, caption: ?string, likes_count: int, comments_count: int, views_count: int, posted_at: ?string, raw_payload: array}> */
    public function fetch(string $tag): array
    {
        $items = $this->apify->runActorSyncAll(self::ACTOR_ID, [
            'hashtags' => [$tag],
            'resultsPerPage' => self::RESULTS_LIMIT,
        ]);

        return collect($items)
            ->map(fn ($item) => [
                'external_post_id' => (string) ($item['id'] ?? ''),
                'post_url' => (string) ($item['webVideoUrl'] ?? ''),
                'author_handle' => $item['authorMeta']['name'] ?? null,
                'caption' => $item['text'] ?? null,
                'likes_count' => (int) ($item['diggCount'] ?? 0),
                'comments_count' => (int) ($item['commentCount'] ?? 0),
                'views_count' => (int) ($item['playCount'] ?? 0),
                'posted_at' => $item['createTimeISO'] ?? null,
                'raw_payload' => $item,
            ])
            ->filter(fn ($post) => $post['external_post_id'] !== '' && $post['post_url'] !== '')
            ->values()
            ->all();
    }
}
