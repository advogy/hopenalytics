<?php

namespace App\Jobs;

use App\Enums\SocialPlatform;
use App\Models\AppSetting;
use App\Models\ChurchSocial;
use App\Models\ChurchStat;
use App\Services\SocialStats\ApifyCreditsExhaustedException;
use App\Services\SocialStats\FacebookStatsFetcher;
use App\Services\SocialStats\HashtagCandidateExtractor;
use App\Services\SocialStats\InstagramStatsFetcher;
use App\Services\SocialStats\TikTokStatsFetcher;
use App\Services\SocialStats\XStatsFetcher;
use App\Services\SocialStats\YouTubeStatsFetcher;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class FetchSingleChurchData implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Shortened from 60s — Hostinger has no persistent queue:work daemon, only a cron-triggered
    // `queue:work --stop-when-empty` every minute (see routes/console.php), so a 60s backoff
    // meant a retried job often missed that minute's run entirely and had to wait for the next
    // cron tick, making a bulk batch with a few problem accounts sit stuck well below 100% for
    // several minutes. 10s still gives a struggling API a moment before hammering it again, but
    // is short enough that the SAME queue:work invocation (which keeps looping until the queue
    // is genuinely empty) can usually pick the retry back up itself.
    public int $tries = 3;
    public int $backoff = 10;
    public int $timeout = 90;

    public function __construct(
        public readonly ChurchSocial $churchSocial,
    ) {}

    /**
     * Lets cancelling a batch (from the queue monitor page) actually stop its
     * remaining un-run jobs, instead of just marking the batch cancelled while
     * the queue worker keeps executing every job already dispatched.
     */
    public function middleware(): array
    {
        return [new SkipIfBatchCancelled];
    }

    public function handle(
        YouTubeStatsFetcher $youTubeStatsFetcher,
        InstagramStatsFetcher $instagramStatsFetcher,
        TikTokStatsFetcher $tikTokStatsFetcher,
        FacebookStatsFetcher $facebookStatsFetcher,
        XStatsFetcher $xStatsFetcher,
        HashtagCandidateExtractor $hashtagCandidateExtractor,
    ): void {
        try {
            $data = match ($this->churchSocial->platform) {
                SocialPlatform::YouTube => $this->fetchYouTube($youTubeStatsFetcher),
                SocialPlatform::Instagram => $instagramStatsFetcher->fetch($this->churchSocial->handle),
                SocialPlatform::TikTok => $tikTokStatsFetcher->fetch($this->churchSocial->handle),
                SocialPlatform::Facebook => $this->fetchFacebook($facebookStatsFetcher),
                SocialPlatform::X => $xStatsFetcher->fetch($this->churchSocial->handle),
            };

            // Instagram/TikTok/Facebook's regular fetch above already returned a sample of the
            // account's own recent content as a side effect (see HashtagCandidateExtractor's own
            // doc comment) — pulled out here, before _recent_posts_raw (never a real ChurchStat
            // column) would otherwise just get silently dropped by mass assignment below.
            $hashtagCandidates = $hashtagCandidateExtractor->extract($this->churchSocial->platform, $data, (string) $this->churchSocial->handle);
            $youtubeUploadsPlaylistId = $data['raw_payload']['contentDetails']['relatedPlaylists']['uploads'] ?? null;
            unset($data['_recent_posts_raw']);

            ChurchStat::updateOrCreate(
                [
                    'church_social_id' => $this->churchSocial->id,
                    'recorded_at' => now()->toDateString(),
                ],
                $data,
            );

            $this->churchSocial->update([
                'last_fetched_at' => now(),
                'last_fetch_status' => 'success',
                'last_fetch_error' => null,
            ]);

            // Instagram/TikTok/Facebook pass their already-fetched sample straight through (zero
            // extra API calls); YouTube/X get null here and fetch their own recent content
            // inside the job instead — but only once it's actually confirmed there's a hashtag
            // to check for, since unlike the other three, that fetch always costs real quota/
            // credits (see MatchAccountHashtags).
            MatchAccountHashtags::dispatch(
                $this->churchSocial,
                in_array($this->churchSocial->platform, [SocialPlatform::Instagram, SocialPlatform::TikTok, SocialPlatform::Facebook], true) ? $hashtagCandidates : null,
                $youtubeUploadsPlaylistId,
            )->afterCommit();
        } catch (ApifyCreditsExhaustedException $e) {
            // Retrying won't help — the credit shortfall won't resolve itself within a few
            // seconds — so this skips FetchSingleChurchData's own retries via fail() (still
            // counts toward the batch's failed total, same "let it finish at 100%" behavior as
            // any other unrecoverable account) rather than throw(), which would just burn two
            // more attempts against an integration that's already known to be out of budget.
            $fallbackToManual = AppSetting::current()->apify_fallback_to_manual;

            $this->churchSocial->update([
                'last_fetched_at' => now(),
                'last_fetch_status' => 'failed',
                'last_fetch_error' => $fallbackToManual
                    ? 'Kredit Apify habis — auto-fetch dinonaktifkan, isi data secara manual.'
                    : 'Kredit Apify habis.',
                'is_auto_fetch' => $fallbackToManual ? false : $this->churchSocial->is_auto_fetch,
            ]);

            Log::warning('Apify credits exhausted while fetching church social stats', [
                'church_social_id' => $this->churchSocial->id,
                'platform' => $this->churchSocial->platform->value,
                'fell_back_to_manual' => $fallbackToManual,
                'error' => $e->getMessage(),
            ]);

            $this->fail($e);
        } catch (Throwable $e) {
            $this->churchSocial->update([
                'last_fetched_at' => now(),
                'last_fetch_status' => 'failed',
                'last_fetch_error' => $e->getMessage(),
            ]);

            Log::warning('Failed to fetch church social stats', [
                'church_social_id' => $this->churchSocial->id,
                'platform' => $this->churchSocial->platform->value,
                'error' => $e->getMessage(),
            ]);

            // "Not found"/misconfigured-account errors are permanent — the page/handle/channel
            // genuinely doesn't exist right now, or the account was never given an ID/URL to
            // fetch — so the default retry (tries=3, 10s backoff apiece) can only ever fail the
            // same way again. Failing immediately here instead means a bulk batch's progress
            // isn't held up waiting out retries that were never going to succeed — per the
            // user's explicit call, "100% done" should track every account having been
            // attempted, not stall on the ones doomed to fail regardless of how many tries.
            if ($this->isPermanentFailure($e)) {
                $this->fail($e);

                return;
            }

            throw $e;
        }
    }

    /**
     * Matches the specific "this will never succeed without a human fixing the data first"
     * messages this app's own fetchers throw (see app/Services/SocialStats/*.php) — anything
     * else (rate limits, a temporary Apify hiccup, a YouTube API 5xx) stays on the normal retry
     * path, since those genuinely might succeed on a second or third attempt.
     */
    private function isPermanentFailure(Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'not found')
            || str_contains($message, 'returned no data')
            || str_contains($message, 'Missing YouTube channel ID or handle')
            || str_contains($message, 'Missing Facebook page URL');
    }

    private function fetchYouTube(YouTubeStatsFetcher $fetcher): array
    {
        if ($this->churchSocial->platform_account_id) {
            return $fetcher->fetch($this->churchSocial->platform_account_id);
        }

        if ($this->churchSocial->handle) {
            return $fetcher->fetchByHandle($this->churchSocial->handle);
        }

        throw new RuntimeException("Missing YouTube channel ID or handle for church_social #{$this->churchSocial->id}");
    }

    private function fetchFacebook(FacebookStatsFetcher $fetcher): array
    {
        if (! $this->churchSocial->profile_url) {
            throw new RuntimeException("Missing Facebook page URL for church_social #{$this->churchSocial->id}");
        }

        return $fetcher->fetch($this->churchSocial->profile_url);
    }
}
