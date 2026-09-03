<?php

namespace App\Jobs;

use App\Enums\SocialPlatform;
use App\Models\ChurchSocial;
use App\Models\Hashtag;
use App\Models\HashtagPost;
use App\Services\SocialStats\ApifyCreditsExhaustedException;
use App\Services\SocialStats\FacebookStatsFetcher;
use App\Services\SocialStats\HashtagCandidateExtractor;
use App\Services\SocialStats\InstagramStatsFetcher;
use App\Services\SocialStats\ThreadsStatsFetcher;
use App\Services\SocialStats\TikTokStatsFetcher;
use App\Services\SocialStats\XPostsFetcher;
use App\Services\SocialStats\YouTubeStatsFetcher;
use App\Support\HashtagMatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Checks one registered account's recent posts/videos for any actively-tracked hashtag —
 * replaces the old global platform-wide hashtag search (per the user's explicit call: only
 * accounts already registered in the system should ever surface here, never an unrelated
 * account elsewhere on the platform).
 *
 * Dispatched automatically after every successful FetchSingleChurchData (see its handle()),
 * passing $preFetchedPosts already extracted from that same fetch for Instagram/TikTok/Facebook/
 * Threads — those four platforms' regular stats-fetch actors already return a sample of the
 * account's own recent content, so matching them costs no extra API call at all (see
 * HashtagCandidateExtractor). YouTube and X have no such free ride, so $preFetchedPosts is null
 * for those and this job fetches their recent content itself, but ONLY once it's confirmed below
 * that at least one hashtag is actually being tracked — no point spending YouTube quota or Apify
 * credits checking for a hashtag nobody's watching for.
 *
 * Also reachable directly (with $preFetchedPosts always null) from the `hashtags:rescan` console
 * command, which re-checks every registered account's live content on demand — e.g. right after
 * a hashtag is first added, without waiting for each account's next scheduled fetch.
 */
class MatchAccountHashtags implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 10;
    public int $timeout = 60;

    public function __construct(
        public readonly ChurchSocial $churchSocial,
        public readonly ?array $preFetchedPosts = null,
        public readonly ?string $youtubeUploadsPlaylistId = null,
    ) {}

    public function handle(
        InstagramStatsFetcher $instagramStatsFetcher,
        TikTokStatsFetcher $tikTokStatsFetcher,
        FacebookStatsFetcher $facebookStatsFetcher,
        YouTubeStatsFetcher $youTubeStatsFetcher,
        XPostsFetcher $xPostsFetcher,
        ThreadsStatsFetcher $threadsStatsFetcher,
        HashtagCandidateExtractor $extractor,
    ): void {
        $activeHashtags = Hashtag::where('is_active', true)->get()->keyBy(fn ($h) => mb_strtolower($h->tag));

        if ($activeHashtags->isEmpty()) {
            return;
        }

        try {
            $posts = $this->preFetchedPosts ?? $this->fetchLive(
                $instagramStatsFetcher, $tikTokStatsFetcher, $facebookStatsFetcher, $youTubeStatsFetcher, $xPostsFetcher, $threadsStatsFetcher, $extractor,
            );
        } catch (ApifyCreditsExhaustedException $e) {
            Log::warning('Apify credits exhausted while matching account hashtags', [
                'church_social_id' => $this->churchSocial->id,
                'platform' => $this->churchSocial->platform->value,
                'error' => $e->getMessage(),
            ]);

            $this->fail($e);

            return;
        } catch (Throwable $e) {
            Log::warning('Failed to fetch content for hashtag matching', [
                'church_social_id' => $this->churchSocial->id,
                'platform' => $this->churchSocial->platform->value,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $lowercaseTags = $activeHashtags->keys()->all();
        $now = now();

        foreach ($posts as $post) {
            foreach (HashtagMatcher::match($post['caption'] ?? null, $lowercaseTags) as $tag) {
                HashtagPost::updateOrCreate(
                    [
                        'hashtag_id' => $activeHashtags[$tag]->id,
                        'platform' => $this->churchSocial->platform->value,
                        'external_post_id' => $post['external_post_id'],
                    ],
                    [
                        'church_social_id' => $this->churchSocial->id,
                        'post_url' => $post['post_url'],
                        'author_handle' => $post['author_handle'] ?? $this->churchSocial->handle,
                        'caption' => $post['caption'],
                        'likes_count' => $post['likes_count'],
                        'comments_count' => $post['comments_count'],
                        'views_count' => $post['views_count'],
                        'posted_at' => $post['posted_at'],
                        'last_seen_at' => $now,
                    ],
                );
            }
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchLive(
        InstagramStatsFetcher $instagramStatsFetcher,
        TikTokStatsFetcher $tikTokStatsFetcher,
        FacebookStatsFetcher $facebookStatsFetcher,
        YouTubeStatsFetcher $youTubeStatsFetcher,
        XPostsFetcher $xPostsFetcher,
        ThreadsStatsFetcher $threadsStatsFetcher,
        HashtagCandidateExtractor $extractor,
    ): array {
        $handle = $this->churchSocial->handle;

        return match ($this->churchSocial->platform) {
            SocialPlatform::Instagram => $extractor->extract(SocialPlatform::Instagram, $instagramStatsFetcher->fetch($handle), $handle),
            SocialPlatform::TikTok => $extractor->extract(SocialPlatform::TikTok, $tikTokStatsFetcher->fetch($handle), $handle),
            SocialPlatform::Facebook => $this->churchSocial->profile_url
                ? $extractor->extract(SocialPlatform::Facebook, $facebookStatsFetcher->fetch($this->churchSocial->profile_url), $handle)
                : [],
            SocialPlatform::Threads => $handle ? $extractor->extract(SocialPlatform::Threads, $threadsStatsFetcher->fetch($handle), $handle) : [],
            SocialPlatform::YouTube => $this->fetchYouTubeLive($youTubeStatsFetcher),
            SocialPlatform::X => $handle ? $xPostsFetcher->fetch($handle) : [],
        };
    }

    private function fetchYouTubeLive(YouTubeStatsFetcher $fetcher): array
    {
        $uploadsPlaylistId = $this->youtubeUploadsPlaylistId;

        if (! $uploadsPlaylistId) {
            $data = $this->churchSocial->platform_account_id
                ? $fetcher->fetch($this->churchSocial->platform_account_id)
                : ($this->churchSocial->handle ? $fetcher->fetchByHandle($this->churchSocial->handle) : null);

            if (! $data) {
                throw new RuntimeException("Missing YouTube channel ID or handle for church_social #{$this->churchSocial->id}");
            }

            $uploadsPlaylistId = $data['raw_payload']['contentDetails']['relatedPlaylists']['uploads'] ?? null;
        }

        return $uploadsPlaylistId ? $fetcher->fetchRecentVideos($uploadsPlaylistId) : [];
    }
}
