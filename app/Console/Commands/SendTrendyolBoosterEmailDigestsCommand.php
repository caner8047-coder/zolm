<?php

namespace App\Console\Commands;

use App\Services\Marketplace\TrendyolBoosterEmailDigestService;
use Illuminate\Console\Command;

class SendTrendyolBoosterEmailDigestsCommand extends Command
{
    protected $signature = 'marketplace:send-trendyol-booster-digests
        {--user= : Yalnızca belirtilen kullanıcı}
        {--limit=100 : Tek çalışmada işlenecek azami bildirim}
        {--force : Feature flag kapalı olsa bile manuel gönder}
        {--dry-run : Gönderim yapmadan uygun bildirimleri say}';

    protected $description = 'Trendyol Booster fiyat, stok, rakip ve kelime sinyallerini e-posta özeti olarak gönderir';

    public function handle(TrendyolBoosterEmailDigestService $digests): int
    {
        if (! config('marketplace.features.trendyol_booster_enabled', false)) {
            $this->components->warn('Trendyol Booster feature flag kapalı.');

            return self::SUCCESS;
        }

        if (! config('marketplace.trendyol_booster.email_digest_enabled', false) && ! $this->option('force')) {
            $this->components->warn('Trendyol Booster e-posta özeti kapalı.');

            return self::SUCCESS;
        }

        $result = $digests->sendPending(
            userId: $this->option('user') ? (int) $this->option('user') : null,
            limit: max(1, min(1000, (int) $this->option('limit'))),
            dryRun: (bool) $this->option('dry-run'),
        );

        $this->components->info(sprintf(
            'Booster e-posta digest sonucu: %d kullanıcı işlendi, %d gönderildi, %d atlandı, %d başarısız, %d bildirim.',
            $result['processed'],
            $result['sent'],
            $result['skipped'],
            $result['failed'],
            $result['notifications'],
        ));

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
