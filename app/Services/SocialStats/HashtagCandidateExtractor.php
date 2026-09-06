<?php

namespace App\Services\SocialStats;

use App\Enums\SocialPlatform;
use Illuminate\Support\Collection;

/**
 * Normalizes each platform's regular weekly stats-fetch response into the common "candidate
 * post" shape MatchAccountHashtags checks against tracked hashtags — Instagram's and TikTok's
 * profile-scrape actors, Facebook's recent-posts actor, and Threads' own recent-posts actor call
 * (see ThreadsStatsFetcher), already return a sample of an account's own recent content as part
 * of the SAME call FetchSingleChurchData makes for its follower-count numbers, so hashtag
 * matching for these four platforms costs no extra API call at all; this class only picks that
 * content back out instead of letting it go to waste.
 *
 * YouTube and X have no such free ride — their regular stats call is channel/profile-level only,
 * with no post list — so their own fetchers (YouTubeStatsFetcher::fetchRecentVideos(),
 * XPostsFetcher::fetch()) return already-normalized candidates directly and never go through
 * this class.
 */
class HashtagCandidateExtractor
{
    /**
     * @return array<int, array{external_post_id: string, post_url: string, author_handle: ?string, caption: ?string, likes_count: ?int, comments_count: ?int, views_count: ?int, shares_count: ?int, posted_at: ?string}>
     */
    public function extract(SocialPlatform $platform, array $data, string $fallbackHandle): array
    {
        return match ($platform) {
            SocialPlatform::Instagram => $this->fromInstagram($data, $fallbackHandle),
            SocialPlatform::TikTok => $this->fromTikTok($data, $fallbackHandle),
            SocialPlatform::Facebook => $this->fromFacebook($data, $fallbackHandle),
            SocialPlatform::Threads => $this->fromThreads($data, $fallbackHandle),
            default => [],
        };
    }

    private function fromInstagram(array $data, string $fallbackHandle): array
    {
        return collect($data['raw_payload']['latestPosts'] ?? [])
            ->map(fn ($post) => [
                'external_post_id' => (string) ($post['id'] ?? $post['shortCode'] ?? ''),
                'post_url' => (string) ($post['url'] ?? ''),
                'author_handle' => $fallbackHandle,
                'caption' => $post['caption'] ?? null,
                'likes_count' => (int) ($post['likesCount'] ?? 0),
                'comments_count' => (int) ($post['commentsCount'] ?? 0),
                'views_count' => isset($post['videoViewCount']) ? (int) $post['videoViewCount'] : null,
                // Instagram's scraper/public data exposes no share count at all (unlike
                // TikTok/Facebook below) — left null rather than guessed at 0, same convention
                // as views_count above for a photo post with no video view count.
                'shares_count' => null,
                'posted_at' => $post['timestamp'] ?? null,
            ])
            ->pipe(fn (Collection $posts) => $this->rejectMissingId($posts));
    }

    private function fromTikTok(array $data, string $fallbackHandle): array
    {
        return collect($data['_recent_posts_raw'] ?? [])
            ->map(fn ($item) => [
                'external_post_id' => (string) ($item['id'] ?? ''),
                'post_url' => (string) ($item['webVideoUrl'] ?? ''),
                'author_handle' => $item['authorMeta']['name'] ?? $fallbackHandle,
                'caption' => $item['text'] ?? null,
                'likes_count' => (int) ($item['diggCount'] ?? 0),
                'comments_count' => (int) ($item['commentCount'] ?? 0),
                'views_count' => (int) ($item['playCount'] ?? 0),
                // Same field TikTokStatsFetcher::fetch() already sums across this same raw
                // sample for its own account-level 'recent_video_shares' total.
                'shares_count' => (int) ($item['shareCount'] ?? 0),
                'posted_at' => $item['createTimeISO'] ?? null,
            ])
            ->pipe(fn (Collection $posts) => $this->rejectMissingId($posts));
    }

    private function fromFacebook(array $data, string $fallbackHandle): array
    {
        return collect($data['_recent_posts_raw'] ?? [])
            ->map(fn ($item) => [
                'external_post_id' => (string) ($item['postId'] ?? ''),
                'post_url' => (string) ($item['url'] ?? ''),
                'author_handle' => $item['pageName'] ?? $fallbackHandle,
                'caption' => $item['text'] ?? null,
                'likes_count' => (int) ($item['likes'] ?? 0),
                'comments_count' => (int) ($item['comments'] ?? 0),
                'views_count' => null,
                // Same field FacebookStatsFetcher::fetchRecentPosts() already sums across this
                // same raw sample for its own account-level 'recent_posts_shares' total.
                'shares_count' => (int) ($item['shares'] ?? 0),
                'posted_at' => $item['time'] ?? null,
            ])
            ->pipe(fn (Collection $posts) => $this->rejectMissingId($posts));
    }

    // Field names match automation-lab/threads-scraper's "posts" mode output per its own docs
    // — see ThreadsStatsFetcher's own doc comment for why this is unverified against a live
    // response.
    private function fromThreads(array $data, string $fallbackHandle): array
    {
        return collect($data['_recent_posts_raw'] ?? [])
            ->map(fn ($item) => [
                'external_post_id' => (string) ($item['postId'] ?? ''),
                'post_url' => (string) ($item['url'] ?? ''),
                'author_handle' => $item['username'] ?? $fallbackHandle,
                'caption' => $item['text'] ?? null,
                'likes_count' => (int) ($item['likeCount'] ?? 0),
                'comments_count' => (int) ($item['replyCount'] ?? 0),
                'views_count' => null,
                // Unconfirmed field name, same caveat as this whole file's own doc comment — left
                // null rather than guessed at, until a real fetch confirms what (if anything) a
                // repost/share count is called in this actor's response.
                'shares_count' => null,
                'posted_at' => $item['date'] ?? null,
            ])
            ->pipe(fn (Collection $posts) => $this->rejectMissingId($posts));
    }

    /** @return array<int, array<string, mixed>> */
    private function rejectMissingId(Collection $posts): array
    {
        return $posts->filter(fn ($post) => $post['external_post_id'] !== '' && $post['post_url'] !== '')->values()->all();
    }
}
