<?php

use App\Models\AppSetting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$settings = AppSetting::current();

if ($settings->auto_fetch_enabled) {
    Schedule::command('church-stats:fetch-all')
        ->weeklyOn($settings->auto_fetch_day, $settings->auto_fetch_time)
        ->timezone('Asia/Jakarta')
        ->withoutOverlapping()
        ->onOneServer();
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
