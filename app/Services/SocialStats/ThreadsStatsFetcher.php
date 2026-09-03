<?php

namespace App\Services\SocialStats;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * IMPORTANT — unlike every other fetcher in this directory, the exact field names below were
 * never confirmed against a live Apify run (no Apify token was available while building this):
 * they're taken from the actor's own published input/output docs at
 * https://apify.com/automation-lab/threads-scraper, not a real response. Threads support ships
 * disabled by default (see the add_threads_enabled_to_app_settings_table migration) for exactly
 * this reason — flip it on in Settings only after confirming a real fetch against this actor
 * succeeds and the numbers look right, and adjust the field names below first if it doesn't.
 *
 * Threads' own public profile page exposes no following-count and no lifetime post-count the
 * way Instagram/X do — only a follower count — so, like FacebookStatsFetcher, "posts" here is a
 * recent-sample count from a second, paired actor call (same actor, different `mode`), not a
 * lifetime total, and that same sample doubles as HashtagCandidateExtractor's free ride (see its
 * own fromThreads()) — no separate ThreadsPostsFetcher needed the way X needed XPostsFetcher.
 */
class ThreadsStatsFetcher
{
    private const ACTOR_ID = 'automation-lab~threads-scraper';

    // A recent/top sample, not exhaustive — same reasoning as FacebookStatsFetcher's own.
    private const RECENT_POSTS_SAMPLE = 10;

    public function __construct(
        private readonly ApifyClient $apify,
    ) {}

    public function fetch(string $username): array
    {
        $username = ltrim($username, '@');

        $item = $this->apify->runActorSync(self::ACTOR_ID, [
            'mode' => 'profile',
            'usernames' => [$username],
        ]);

        return [
            'followers_count' => (int) ($item['followerCount'] ?? 0),
            'raw_payload' => $item,
            ...$this->fetchRecentPosts($username),
        ];
    }

    /**
     * Best-effort, same reasoning as FacebookStatsFetcher::fetchRecentPosts() — a failure here
     * never fails the whole fetch, since the follower count above is what growth score/
     * leaderboards actually depend on; the recent-post sample (and hashtag matching riding on
     * it) is a bonus. Credit exhaustion is the one exception, re-thrown so the caller's existing
     * fallback-to-manual handling still kicks in.
     */
    private function fetchRecentPosts(string $username): array
    {
        try {
            $posts = $this->apify->runActorSyncAll(self::ACTOR_ID, [
                'mode' => 'posts',
                'usernames' => [$username],
                'maxPosts' => self::RECENT_POSTS_SAMPLE,
            ]);
        } catch (ApifyCreditsExhaustedException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::warning('Threads recent-posts fetch failed, skipping', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        $posts = collect($posts);

        return [
            'recent_posts_count' => $posts->count(),
            // The full post sample — a free ride on the same call above, picked back out by
            // HashtagCandidateExtractor for hashtag matching. Never persisted to church_stats
            // itself; FetchSingleChurchData strips this key before saving.
            '_recent_posts_raw' => $posts->all(),
        ];
    }
}
