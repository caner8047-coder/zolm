<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$runInlineCommand = function (string $command, array $parameters = []): int {
    $exitCode = Artisan::call($command, $parameters);
    $output = trim(Artisan::output());

    if ($output !== '') {
        echo $output.PHP_EOL;
    }

    return $exitCode;
};

Schedule::call(fn () => $runInlineCommand('marketplace:dispatch-due-syncs'))
    ->name('marketplace-dispatch-due-syncs')
    ->everyMinute()
    ->withoutOverlapping(10);

// Gece onarım sync: 03:00'te çalışır, nightly_repair_sync_enabled olan mağazalar için
// eksik finans, snapshot ve eşleşme sorunlarını onarır.
Schedule::call(fn () => $runInlineCommand('marketplace:nightly-repair'))
    ->name('marketplace-nightly-repair')
    ->dailyAt('03:00')
    ->withoutOverlapping();

// Bildirim merkezi temizliği: okunmuş 24 saat, okunmamış 7 gün eşiğini geçenleri siler.
Schedule::call(fn () => $runInlineCommand('notifications:prune-expired'))
    ->name('notifications-prune-expired')
    ->hourly()
    ->withoutOverlapping();

// Sürat Kargo takip hareketleri: aktif gönderileri düzenli yeniler.
Schedule::call(fn () => $runInlineCommand('cargo:sync-tracking', ['--limit' => 100, '--stale-minutes' => 120]))
    ->name('cargo-sync-tracking')
    ->everyThirtyMinutes()
    ->withoutOverlapping();
