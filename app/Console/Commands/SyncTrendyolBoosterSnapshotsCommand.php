<?php

namespace App\Console\Commands;

use App\Services\Marketplace\TrendyolBoosterMonitorService;
use App\Services\Marketplace\TrendyolBoosterCompetitorService;
use App\Services\Marketplace\TrendyolBoosterKeywordService;
use App\Services\Marketplace\TrendyolBoosterStoreWatchService;
use App\Services\Marketplace\TrendyolBoosterScheduledAnalysisService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SyncTrendyolBoosterSnapshotsCommand extends Command
{
    protected $signature = 'marketplace:sync-trendyol-booster
        {--user= : Yalnızca belirtilen kullanıcıyı çalıştır}
        {--limit= : Geriye uyumlu genel limit}
        {--product-limit= : Ürün fiyat/stok kontrol limiti}
        {--analysis-limit= : Tam ürün analizi otomatik yenileme limiti}
        {--competitor-limit= : Rakip ürün kontrol limiti}
        {--keyword-limit= : Anahtar kelime kontrol limiti}
        {--store-limit= : Rakip mağaza kontrol limiti}
        {--stale-minutes= : Tüm alanlar için ortak bekleme süresi}
        {--product-stale-minutes= : Ürün kontrol bekleme süresi}
        {--competitor-stale-minutes= : Rakip kontrol bekleme süresi}
        {--keyword-stale-minutes= : Anahtar kelime kontrol bekleme süresi}
        {--store-stale-minutes= : Rakip mağaza kontrol bekleme süresi}
        {--dry-run : HTTP isteği atmadan uygun kayıtları say}';

    protected $description = 'Trendyol Booster takip ürünleri için fiyat/stok snapshot kontrolü yapar';

    public function handle(
        TrendyolBoosterMonitorService $monitor,
        TrendyolBoosterScheduledAnalysisService $scheduledAnalysis,
        TrendyolBoosterCompetitorService $competitors,
        TrendyolBoosterKeywordService $keywords,
        TrendyolBoosterStoreWatchService $storeWatches
    ): int
    {
        if (! config('marketplace.features.trendyol_booster_enabled', false)) {
            $this->components->warn('Trendyol Booster feature flag kapalı.');

            return self::SUCCESS;
        }

        if (! Schema::hasTable('trendyol_booster_products') || ! Schema::hasTable('trendyol_booster_snapshots')) {
            $this->components->warn('Trendyol Booster tabloları hazır değil.');

            return self::SUCCESS;
        }

        $userId = (int) $this->option('user');
        $userFilter = $userId > 0 ? $userId : null;
        $legacyLimit = $this->nullableIntOption('limit', 1, 500);
        $productLimit = $this->intOption('product-limit', $legacyLimit ?? (int) config('marketplace.trendyol_booster.sync.product_limit', 50), 1, 500);
        $analysisLimit = $this->intOption('analysis-limit', $legacyLimit ?? (int) config('marketplace.trendyol_booster.sync.analysis_limit', 25), 1, 100);
        $competitorLimit = $this->intOption('competitor-limit', $legacyLimit ?? (int) config('marketplace.trendyol_booster.sync.competitor_limit', 50), 1, 500);
        $keywordLimit = $this->intOption('keyword-limit', $legacyLimit ?? (int) config('marketplace.trendyol_booster.sync.keyword_limit', 50), 1, 500);
        $storeLimit = $this->intOption('store-limit', $legacyLimit ?? (int) config('marketplace.trendyol_booster.sync.store_limit', 25), 1, 100);
        $staleOverride = $this->nullableIntOption('stale-minutes', 1, 10080);
        $dryRun = (bool) $this->option('dry-run');

        $summary = $monitor->checkDue(
            $productLimit,
            $userFilter,
            $this->staleMinutes('product', $staleOverride),
            $dryRun,
        );
        $analysisSummary = Schema::hasColumn('trendyol_booster_products', 'analysis_auto_refresh_enabled')
            ? $scheduledAnalysis->refreshDue($analysisLimit, $userFilter, $dryRun)
            : $this->emptySummary($dryRun);
        $competitorSummary = Schema::hasTable('trendyol_booster_competitors')
            ? $competitors->refreshDue($competitorLimit, $userFilter, $this->staleMinutes('competitor', $staleOverride), $dryRun)
            : $this->emptySummary($dryRun);
        $keywordSummary = Schema::hasTable('trendyol_booster_keywords')
            ? $keywords->refreshDue($keywordLimit, $userFilter, $this->staleMinutes('keyword', $staleOverride), $dryRun)
            : $this->emptySummary($dryRun);
        $storeSummary = Schema::hasTable('trendyol_booster_store_watches')
            ? $storeWatches->refreshDue($storeLimit, $userFilter, $this->staleMinutes('store', $staleOverride), $dryRun)
            : $this->emptySummary($dryRun);

        Cache::put('marketplace:trendyol-booster:last-scheduler-run-at', now()->toIso8601String(), now()->addHours(3));

        $this->table(
            ['Alan', 'Değer'],
            [
                ['Mod', $dryRun ? 'dry-run' : 'aktif'],
                ['Ürün limit / stale', $productLimit . ' / ' . $this->staleMinutes('product', $staleOverride) . ' dk'],
                ['Ürün işlenen', (string) $summary['processed']],
                ['Ürün başarılı', (string) $summary['succeeded']],
                ['Ürün hatalı', (string) $summary['failed']],
                ['Ürün atlanan', (string) ($summary['skipped'] ?? 0)],
                ['Snapshot', (string) $summary['snapshots']],
                ['Tam analiz limit', (string) $analysisLimit],
                ['Tam analiz işlenen', (string) $analysisSummary['processed']],
                ['Tam analiz başarılı', (string) $analysisSummary['succeeded']],
                ['Tam analiz hatalı', (string) $analysisSummary['failed']],
                ['Tam analiz atlanan', (string) ($analysisSummary['skipped'] ?? 0)],
                ['Rakip limit / stale', $competitorLimit . ' / ' . $this->staleMinutes('competitor', $staleOverride) . ' dk'],
                ['Rakip işlenen', (string) $competitorSummary['processed']],
                ['Rakip başarılı', (string) $competitorSummary['succeeded']],
                ['Rakip hatalı', (string) $competitorSummary['failed']],
                ['Rakip atlanan', (string) ($competitorSummary['skipped'] ?? 0)],
                ['Kelime limit / stale', $keywordLimit . ' / ' . $this->staleMinutes('keyword', $staleOverride) . ' dk'],
                ['Kelime işlenen', (string) $keywordSummary['processed']],
                ['Kelime başarılı', (string) $keywordSummary['succeeded']],
                ['Kelime hatalı', (string) $keywordSummary['failed']],
                ['Kelime atlanan', (string) ($keywordSummary['skipped'] ?? 0)],
                ['Mağaza limit / stale', $storeLimit . ' / ' . $this->staleMinutes('store', $staleOverride) . ' dk'],
                ['Mağaza işlenen', (string) $storeSummary['processed']],
                ['Mağaza başarılı', (string) $storeSummary['succeeded']],
                ['Mağaza hatalı', (string) $storeSummary['failed']],
                ['Mağaza atlanan', (string) ($storeSummary['skipped'] ?? 0)],
            ]
        );

        return self::SUCCESS;
    }

    protected function intOption(string $name, int $default, int $min, int $max): int
    {
        $value = $this->option($name);

        if ($value === null || $value === '') {
            return max($min, min($max, $default));
        }

        return max($min, min($max, (int) $value));
    }

    protected function nullableIntOption(string $name, int $min, int $max): ?int
    {
        $value = $this->option($name);

        if ($value === null || $value === '') {
            return null;
        }

        return max($min, min($max, (int) $value));
    }

    protected function staleMinutes(string $area, ?int $override): int
    {
        $areaOverride = $this->nullableIntOption("{$area}-stale-minutes", 1, 10080);

        if ($areaOverride !== null) {
            return $areaOverride;
        }

        if ($override !== null) {
            return $override;
        }

        return max(1, (int) config("marketplace.trendyol_booster.sync.{$area}_stale_minutes", 120));
    }

    /**
     * @return array{processed: int, succeeded: int, failed: int, skipped: int, snapshots: int, dry_run: bool}
     */
    protected function emptySummary(bool $dryRun): array
    {
        return [
            'processed' => 0,
            'succeeded' => 0,
            'failed' => 0,
            'skipped' => 0,
            'snapshots' => 0,
            'dry_run' => $dryRun,
        ];
    }
}
