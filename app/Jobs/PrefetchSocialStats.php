<?php

namespace App\Jobs;

use App\Enums\SocialPlatform;
use App\Models\ChurchSocial;
use App\Services\SocialStats\FacebookStatsFetcher;
use App\Services\SocialStats\InstagramStatsFetcher;
use App\Services\SocialStats\TikTokStatsFetcher;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One Apify run for a whole group of accounts on one platform, instead of one run each — per
 * the user's explicit call to cut Apify costs (every run has its own start-up cost). Each
 * account's result is parked in the cache, where its own FetchSingleChurchData job picks it up
 * instead of calling Apify again. Anything not prefetched (missing from the result, a failed
 * run, a job that ran first) is fetched by that job on its own as before, so nothing is lost —
 * at worst the saving is.
 *
 * Covers Facebook pages, Instagram and TikTok profiles: one result per account, matched back by
 * URL or username. Facebook's recent posts are still fetched per page (see FacebookStatsFetcher).
 */
class PrefetchSocialStats implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable;

    /** Accounts per Apify run, per platform: small enough to finish within one sync run. */
    public const GROUP_SIZE = ['facebook' => 25, 'instagram' => 50, 'tiktok' => 20];

    /** How long a prefetched result waits for its account's job (the weekly fetch is staggered over a few hours). */
    private const CACHE_HOURS = 12;

    public int $tries = 1;

    public int $timeout = 300;

    /** @param  list<int>  $socialIds */
    public function __construct(
        public readonly string $platform,
        public readonly array $socialIds,
    ) {}

    public static function cacheKey(int $socialId): string
    {
        return "apify-prefetch:{$socialId}";
    }

    /**
     * Prefetch jobs for every batchable account in $socials, one per group. Queue these BEFORE
     * the accounts' own jobs: the worker takes jobs in order, so the results are ready in time.
     *
     * @param  Collection<int, ChurchSocial>  $socials
     * @return list<self>
     */
    public static function jobsFor(Collection $socials): array
    {
        return $socials
            ->filter(fn (ChurchSocial $s) => isset(self::GROUP_SIZE[$s->platform->value]))
            ->groupBy(fn (ChurchSocial $s) => $s->platform->value)
            ->flatMap(fn (Collection $group, string $platform) => $group->pluck('id')
                ->chunk(self::GROUP_SIZE[$platform])
                ->map(fn ($ids) => new self($platform, $ids->values()->all())))
            ->values()->all();
    }

    public function middleware(): array
    {
        return [new SkipIfBatchCancelled];
    }

    public function handle(FacebookStatsFetcher $facebook, InstagramStatsFetcher $instagram, TikTokStatsFetcher $tikTok): void
    {
        $socials = ChurchSocial::query()->whereIn('id', $this->socialIds)
            ->where('platform', SocialPlatform::from($this->platform))->get();

        try {
            $found = match ($this->platform) {
                'facebook' => $this->prefetch($socials->filter(fn ($s) => $s->profile_url), fn ($s) => $s->profile_url, fn ($keys) => $facebook->fetchPagesBatch($keys)),
                'instagram' => $this->prefetch($socials->filter(fn ($s) => $s->handle), fn ($s) => strtolower(ltrim($s->handle, '@')), fn ($keys) => $instagram->fetchBatch($keys)),
                'tiktok' => $this->prefetch($socials->filter(fn ($s) => $s->handle), fn ($s) => strtolower(ltrim($s->handle, '@')), fn ($keys) => $tikTok->fetchBatch($keys)),
            };

            Log::info('Prefetched social stats in one Apify run', ['platform' => $this->platform, 'accounts' => count($this->socialIds), 'found' => $found]);
        } catch (Throwable $e) {
            // Never fails the batch: each account's own job fetches it individually instead.
            Log::warning('Prefetching social stats failed; accounts will be fetched one by one', [
                'platform' => $this->platform, 'accounts' => count($this->socialIds), 'error' => $e->getMessage(),
            ]);
        }
    }

    /** Runs one batch fetch and caches each account's result; returns how many were found. */
    private function prefetch(Collection $socials, callable $keyFor, callable $fetchBatch): int
    {
        if ($socials->isEmpty()) {
            return 0;
        }

        $keys = $socials->mapWithKeys(fn ($s) => [$s->id => $keyFor($s)]);
        $results = $fetchBatch($keys->unique()->values()->all());
        $found = 0;

        foreach ($keys as $socialId => $key) {
            if (isset($results[$key])) {
                Cache::put(self::cacheKey($socialId), $results[$key], now()->addHours(self::CACHE_HOURS));
                $found++;
            }
        }

        return $found;
    }
}
