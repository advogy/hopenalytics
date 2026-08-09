<?php

namespace App\Services\SocialStats;

class InstagramHashtagFetcher
{
    // Actor resmi Apify: https://apify.com/apify/instagram-hashtag-scraper
    private const ACTOR_ID = 'apify~instagram-hashtag-scraper';

    // How many posts to sample per hashtag — a recent/top sample, not exhaustive.
    private const RESULTS_LIMIT = 30;

    public function __construct(
        private readonly ApifyClient $apify,
    ) {}

    /** @return array<int, array{external_post_id: string, post_url: string, author_handle: ?string, caption: ?string, likes_count: int, comments_count: int, views_count: ?int, posted_at: ?string, raw_payload: array}> */
    public function fetch(string $tag): array
    {
        $items = $this->apify->runActorSyncAll(self::ACTOR_ID, [
            'hashtags' => [$tag],
            'resultsType' => 'posts',
            'resultsLimit' => self::RESULTS_LIMIT,
        ]);

        return collect($items)
            ->map(fn ($item) => [
                'external_post_id' => (string) ($item['id'] ?? $item['shortCode'] ?? ''),
                'post_url' => (string) ($item['url'] ?? ''),
                'author_handle' => $item['ownerUsername'] ?? null,
                'caption' => $item['caption'] ?? null,
                'likes_count' => (int) ($item['likesCount'] ?? 0),
                'comments_count' => (int) ($item['commentsCount'] ?? 0),
                'views_count' => isset($item['videoViewCount']) ? (int) $item['videoViewCount'] : null,
                'posted_at' => $item['timestamp'] ?? null,
                'raw_payload' => $item,
            ])
            ->filter(fn ($post) => $post['external_post_id'] !== '' && $post['post_url'] !== '')
            ->values()
            ->all();
    }
}
