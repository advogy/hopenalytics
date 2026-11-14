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

    /**
     * Many profiles in one actor run (see PrefetchSocialStats): each profile's videos, keyed by
     * lowercase username. resultsPerPage is per profile, so each still gets its own sample.
     *
     * @param  list<string>  $usernames
     * @return array<string, list<array>> video items per profile
     */
    public function fetchBatch(array $usernames): array
    {
        $items = $this->apify->runActorSyncAll(self::ACTOR_ID, [
            'profiles' => array_values($usernames),
            'profileScrapeSections' => ['videos'],
            'resultsPerPage' => self::RECENT_VIDEOS_SAMPLE,
        ], ApifyClient::BATCH_TIMEOUT_SECONDS);

        return collect($items)
            ->filter(fn ($item) => isset($item['authorMeta']['name']))
            ->groupBy(fn ($item) => strtolower(ltrim($item['authorMeta']['name'], '@')))
            ->map(fn ($group) => $group->values()->all())
            ->all();
    }

    /** $prefetched: this profile's video items from fetchBatch(), when the batch already got them. */
    public function fetch(string $username, ?array $prefetched = null): array
    {
        $items = $prefetched ?? $this->apify->runActorSyncAll(self::ACTOR_ID, [
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
            'recent_video_comments' => (int) $videos->sum('commentCount'),
            'raw_payload' => $items[0],
            // The full video sample (not just $items[0]) — a free ride on the same call above,
            // picked back out by HashtagCandidateExtractor for hashtag matching. Never persisted
            // to church_stats itself; FetchSingleChurchData strips this key before saving.
            '_recent_posts_raw' => $videos->all(),
        ];
    }
}
