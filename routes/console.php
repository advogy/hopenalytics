<?php

use App\Models\AppSetting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// This file is loaded on every artisan command, including the very first `migrate` on a brand
// new database — before that command creates app_settings, querying it here would throw and
// migrate could never run at all on a fresh clone. Nothing needs scheduling yet on a database
// that doesn't even have app_settings.
if (Schema::hasTable('app_settings')) {
    $settings = AppSetting::current();

    if ($settings->auto_fetch_enabled) {
        // Hashtag matching is no longer a separate weekly job here — each account's own
        // FetchSingleChurchData dispatches MatchAccountHashtags right after a successful fetch (see
        // its own doc comment), so it already runs on this exact schedule without a second entry.
        Schedule::command('church-stats:fetch-all')
            ->weeklyOn($settings->auto_fetch_day, $settings->auto_fetch_time)
            ->timezone('Asia/Jakarta')
            ->withoutOverlapping()
            ->onOneServer();
    }
}

// Hostinger (shared hosting, no SSH/persistent processes) has no long-running `queue:work`
// daemon like a local `composer dev` does — every job dispatched onto the "database" queue
// (the bulk Refresh button's Bus::batch(), and the weekly church-stats:fetch-all above) would
// otherwise sit in the `jobs` table forever, silently doing nothing. This piggybacks on the
// same cron-triggered `schedule:run` the weekly job above already needs, draining whatever's
// queued once a minute instead of running a persistent worker. --stop-when-empty exits
// immediately on a quiet minute; --max-time keeps a busy run well under the next minute's tick
// so it can't overlap and pile up.
Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=50')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();
