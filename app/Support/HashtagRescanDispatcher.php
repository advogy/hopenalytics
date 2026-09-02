<?php

namespace App\Support;

use App\Jobs\MatchAccountHashtags;
use App\Models\ChurchSocial;

/**
 * Staggers MatchAccountHashtags jobs across every eligible account, 5 seconds apart — shared by
 * the `hashtags:rescan` CLI command and HashtagController's own "Scan Ulang Sekarang" button, so
 * triggering a fresh scan isn't limited to SSH access (per the user's explicit call for a
 * flexible, on-demand way to get fresher hashtag data around a specific event — e.g. monitoring
 * a coordinated hashtag launch hour by hour — rather than a fixed, unattended schedule). Always
 * fetches live (real Apify credits/YouTube quota spent per account, unlike the weekly auto-fetch's
 * free ride off each account's own regular stats fetch) — see MatchAccountHashtags's own doc
 * comment.
 */
class HashtagRescanDispatcher
{
    public static function dispatch(): int
    {
        $delaySeconds = 0;
        $total = 0;

        ChurchSocial::query()
            ->where('is_active', true)
            ->where('is_auto_fetch', true)
            ->ownerActive()
            ->chunkById(50, function ($socials) use (&$delaySeconds, &$total) {
                foreach ($socials as $social) {
                    MatchAccountHashtags::dispatch($social)->delay(now()->addSeconds($delaySeconds));

                    $delaySeconds += 5;
                    $total++;
                }
            });

        return $total;
    }
}
