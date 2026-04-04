<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('marketplace:dispatch-due-syncs')->everyFiveMinutes();

// Gece onarım sync: 03:00'te çalışır, nightly_repair_sync_enabled olan mağazalar için
// eksik finans, snapshot ve eşleşme sorunlarını onarır.
Schedule::command('marketplace:nightly-repair')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground();

