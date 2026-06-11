<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('returns:run-auto-policies {--item=} {--date=} {--limit=} {--dry-run} {--allow-marketplace}', function () {
    $date = filled($this->option('date'))
        ? \Carbon\Carbon::parse((string) $this->option('date'))
        : null;
    $itemId = filled($this->option('item')) ? (int) $this->option('item') : null;
    $limit = filled($this->option('limit')) ? (int) $this->option('limit') : null;
    $allowMarketplace = (bool) $this->option('allow-marketplace') ? true : null;

    $summary = app(\App\Services\Returns\ReturnAutoDecisionPolicyService::class)->run(
        dryRun: (bool) $this->option('dry-run'),
        date: $date,
        itemId: $itemId,
        limit: $limit,
        allowMarketplace: $allowMarketplace,
    );

    $this->info('Iade auto policy sonucu');
    $this->line('Uygun: '.$summary['eligible']);
    $this->line('Islenen: '.$summary['processed']);
    $this->line('Stoga alinan: '.$summary['restocked']);
    $this->line('Hurdaya ayrilan: '.$summary['scrapped']);
    $this->line('Manuel inceleme: '.$summary['manual_review']);
    $this->line('Engellenen: '.$summary['blocked']);
    $this->line('Hata: '.$summary['errors']);

    return (int) ($summary['errors'] > 0 ? 1 : 0);
})->purpose('Run automatic return decision policies');

Artisan::command('returns:daily-report {--date=} {--persist}', function () {
    $date = filled($this->option('date'))
        ? \Carbon\Carbon::parse((string) $this->option('date'))
        : today();
    $service = app(\App\Services\Returns\ReturnDailyReportService::class);
    $report = (bool) $this->option('persist')
        ? $service->persist($date)->toArray()
        : $service->build($date);
    $totals = $report['totals_json'] ?? $report['totals'] ?? [];

    $this->info('Gunluk iade raporu');
    $this->line('Tarih: '.($report['report_date'] ?? $report['date'] ?? $date->toDateString()));
    $this->line('Toplam: '.(int) ($totals['submitted'] ?? 0));
    $this->line('Hasar orani: %'.number_format((float) ($totals['damage_rate'] ?? 0), 1, ',', '.'));

    return 0;
})->purpose('Build or persist the daily return report');

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
