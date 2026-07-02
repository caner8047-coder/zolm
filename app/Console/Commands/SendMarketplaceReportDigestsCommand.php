<?php

namespace App\Console\Commands;

use App\Models\MarketplaceReportSubscription;
use App\Services\Marketplace\MarketplaceReportDigestService;
use Illuminate\Console\Command;

class SendMarketplaceReportDigestsCommand extends Command
{
    protected $signature = 'marketplace:send-report-digests
        {--user= : Yalnızca belirtilen kullanıcı}
        {--subscription= : Yalnızca belirtilen abonelik}
        {--force : Zamanı gelmemiş olsa bile gönder}
        {--dry-run : Gönderim yapmadan uygun abonelikleri say}';

    protected $description = 'Pazaryeri kâr/risk özet raporlarını aboneliklere göre e-posta ile gönderir';

    public function handle(MarketplaceReportDigestService $digests): int
    {
        if (! config('marketplace.features.report_digest_enabled', false)) {
            $this->components->warn('Otomatik rapor feature flag kapalı.');

            return self::SUCCESS;
        }

        $subscriptionId = (int) ($this->option('subscription') ?: 0);
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        if ($subscriptionId > 0) {
            $subscription = MarketplaceReportSubscription::query()->findOrFail($subscriptionId);

            if ($dryRun) {
                $this->line("Abonelik #{$subscription->id}: {$subscription->name}");
                $this->components->info('Dry-run tamamlandı.');

                return self::SUCCESS;
            }

            $result = $digests->sendSubscription($subscription, now(), $force);
        } else {
            $result = $digests->sendDue(
                now(),
                $this->option('user') ? (int) $this->option('user') : null,
                $force,
                $dryRun,
            );
        }

        $this->components->info(sprintf(
            'Rapor digest sonucu: %d işlendi, %d gönderildi, %d atlandı, %d başarısız.',
            $result['processed'] ?? 1,
            $result['sent'],
            $result['skipped'],
            $result['failed'],
        ));

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
