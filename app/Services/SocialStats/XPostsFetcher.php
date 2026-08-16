<?php

namespace App\Services\SocialStats;

class XPostsFetcher
{
    // Same publisher/family as XStatsFetcher's profile-lookup actor
    // (apidojo~twitter-user-scraper), but this one paginates a user's own tweet timeline instead
    // of just their profile numbers — https://apify.com/apidojo/twitter-scraper-lite. Only
    // called by MatchAccountHashtags when hashtags are actively tracked, since (unlike
    // Instagram/TikTok/Facebook) X's regular weekly stats fetch never touches tweet content, so
    // this is always a genuinely extra, credit-spending call.
    private const ACTOR_ID = 'apidojo~twitter-scraper-lite';

    // A recent/top sample, not exhaustive.
    private const RESULTS_LIMIT = 15;

    public function __construct(
        private readonly ApifyClient $apify,
    ) {}

    /** @return array<int, array{external_post_id: string, post_url: string, author_handle: ?string, caption: ?string, likes_count: int, comments_count: int, views_count: null, posted_at: ?string}> */
    public function fetch(string $username): array
    {
        $items = $this->apify->runActorSyncAll(self::ACTOR_ID, [
            'twitterHandles' => [$username],
            'maxItems' => self::RESULTS_LIMIT,
        ]);

        return collect($items)
            ->map(fn ($item) => [
                'external_post_id' => (string) ($item['id'] ?? ''),
                'post_url' => (string) ($item['url'] ?? $item['twitterUrl'] ?? ''),
                'author_handle' => $item['author']['userName'] ?? $username,
                'caption' => $item['text'] ?? null,
                'likes_count' => (int) ($item['likeCount'] ?? 0),
                'comments_count' => (int) ($item['replyCount'] ?? 0),
                'views_count' => null,
                'posted_at' => $item['createdAt'] ?? null,
            ])
            ->filter(fn ($post) => $post['external_post_id'] !== '' && $post['post_url'] !== '')
            ->values()
            ->all();
    }
}
