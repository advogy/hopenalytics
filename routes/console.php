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
