<?php

namespace App\Services\SocialStats;

use RuntimeException;

class TikTokStatsFetcher
{
    // Actor: https://apify.com/clockworks/tiktok-profile-scraper
    private const ACTOR_ID = 'clockworks~tiktok-profile-scraper';

    public function __construct(
        private readonly ApifyClient $apify,
    ) {}

    // How many of the most recent videos to sample for plays/shares (not a lifetime total).
    private const RECENT_VIDEOS_SAMPLE = 10;

    public function fetch(string $username): array
    {
        $items = $this->apify->runActorSyncAll(self::ACTOR_ID, [
            'profiles' => [$username],
            'profileScrapeSections' => ['videos'],
            'resultsPerPage' => self::RECENT_VIDEOS_SAMPLE,
        ]);

        if (! $items) {
            throw new RuntimeException("TikTok profile not found: {$username}");
        }

        $author = $items[0]['authorMeta'] ?? null;

        if (! $author) {
            throw new RuntimeException("TikTok profile not found: {$username}");
        }

        $videos = collect($items);

        return [
            'followers_count' => (int) ($author['fans'] ?? 0),
            'following_count' => (int) ($author['following'] ?? 0),
            'posts_count' => (int) ($author['video'] ?? 0),
            'likes_count' => (int) ($author['heart'] ?? 0),
            'recent_video_count' => $videos->count(),
            'recent_video_plays' => (int) $videos->sum('playCount'),
            'recent_video_shares' => (int) $videos->sum('shareCount'),
            'raw_payload' => $items[0],
            // The full video sample (not just $items[0]) — a free ride on the same call above,
            // picked back out by HashtagCandidateExtractor for hashtag matching. Never persisted
            // to church_stats itself; FetchSingleChurchData strips this key before saving.
            '_recent_posts_raw' => $videos->all(),
        ];
    }
}
