<?php

namespace App\Jobs;

use App\Enums\SocialPlatform;
use App\Models\ChurchSocial;
use App\Models\ChurchStat;
use App\Services\SocialStats\FacebookStatsFetcher;
use App\Services\SocialStats\InstagramStatsFetcher;
use App\Services\SocialStats\TikTokStatsFetcher;
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

    public int $tries = 3;
    public int $backoff = 60;
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
    ): void {
        try {
            $data = match ($this->churchSocial->platform) {
                SocialPlatform::YouTube => $this->fetchYouTube($youTubeStatsFetcher),
                SocialPlatform::Instagram => $instagramStatsFetcher->fetch($this->churchSocial->handle),
                SocialPlatform::TikTok => $tikTokStatsFetcher->fetch($this->churchSocial->handle),
                SocialPlatform::Facebook => $this->fetchFacebook($facebookStatsFetcher),
            };

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

            throw $e;
        }
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
