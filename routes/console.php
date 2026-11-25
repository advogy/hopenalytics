<?php

use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\LoginLog;
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
        Schedule::call(fn () => Artisan::call('church-stats:fetch-all'))
            ->name('church-stats:fetch-all')
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
//
// Schedule::call() + Artisan::call(), not Schedule::command(), everywhere in this file: command()
// starts each entry as a child process through Symfony Process, which needs proc_open — and
// Hostinger's PHP has proc_open disabled (seen in production 2026-11-25: every minute's
// queue:work threw "The Process class relies on proc_open" and 624 jobs sat in the queue
// untouched). call() runs the command inside the schedule:run process itself, no proc_open.
Schedule::call(fn () => Artisan::call('queue:work', ['--stop-when-empty' => true, '--tries' => 3, '--max-time' => 50]))
    ->name('queue:work')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

// Retention for the Log Audit page's two tables — see AuditLog/LoginLog::RETENTION_MONTHS
// (1 year of actions, 6 months of logins, per the user's explicit call). Rides on the same
// cron-triggered `schedule:run` as everything above.
Schedule::call(fn () => Artisan::call('model:prune', ['--model' => [AuditLog::class, LoginLog::class]]))
    ->name('model:prune')
    ->dailyAt('02:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer();
