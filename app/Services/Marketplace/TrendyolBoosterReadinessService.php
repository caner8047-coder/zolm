<?php

namespace App\Services\Marketplace;

use App\Models\TrendyolBoosterProduct;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class TrendyolBoosterReadinessService
{
    protected const LAST_RUN_CACHE_KEY = 'marketplace:trendyol-booster:last-scheduler-run-at';

    public function __construct(
        protected TrendyolBoosterRetentionReportService $retentionReportService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function audit(?int $userId = null): array
    {
        $checks = collect([
            $this->featureFlagCheck(),
            $this->releaseRingCheck(),
            $this->routeCheck(),
            $this->schemaCheck(),
            $this->schedulerCheck($userId),
            $this->queueCheck(),
            $this->cacheCheck(),
            $this->extensionVersionCheck(),
            $this->companionSecurityCheck(),
            $this->syncLimitCheck(),
            $this->notificationCheck(),
            $this->retentionCheck(),
        ]);
        $blockingCount = $checks->where('status', 'fail')->count();
        $warningCount = $checks->where('status', 'warning')->count();
        $status = $blockingCount > 0 ? 'blocked' : ($warningCount > 0 ? 'warning' : 'ready');

        return [
            'ready' => $blockingCount === 0,
            'status' => $status,
            'label' => match ($status) {
                'blocked' => 'Canlıya geçiş engelli',
                'warning' => 'Kontrollü canlıya geçiş',
                default => 'Canlıya geçiş hazır',
            },
            'user_id' => $userId,
            'generated_at' => now()->toIso8601String(),
            'summary' => [
                'check_count' => $checks->count(),
                'pass_count' => $checks->where('status', 'pass')->count(),
                'warning_count' => $warningCount,
                'blocking_count' => $blockingCount,
            ],
            'checks' => $checks->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function featureFlagCheck(): array
    {
        $enabled = (bool) config('marketplace.features.trendyol_booster_enabled', false);

        return $this->check(
            'feature_flag',
            'Yayın kontrolü',
            $enabled ? 'pass' : 'fail',
            'Trendyol Booster feature flag',
            $enabled ? 'Booster erişime açık.' : 'Booster feature flag kapalı.',
            'Canlıya geçişte MARKETPLACE_TRENDYOL_BOOSTER_ENABLED=true olmalı.',
        );
    }

    /** @return array<string, string> */
    protected function releaseRingCheck(): array
    {
        $ring = strtolower((string) config('marketplace.trendyol_booster.release.ring', 'ga'));
        $valid = in_array($ring, ['off', 'beta', 'ga'], true);
        $betaIds = collect(config('marketplace.trendyol_booster.release.beta_user_ids', []))->filter();
        $status = ! $valid || $ring === 'off' ? 'fail' : ($ring === 'beta' && $betaIds->isEmpty() ? 'warning' : 'pass');

        return $this->check(
            'release_ring',
            'Yayın kontrolü',
            $status,
            'Beta / GA yayın halkası',
            match ($status) {
                'fail' => 'Yayın halkası kapalı veya geçersiz: '.$ring.'.',
                'warning' => 'Beta halkası açık; yalnız yöneticiler erişebilir, kullanıcı izin listesi boş.',
                default => strtoupper($ring).' halkası yapılandırıldı.',
            },
            'Ring değerini off, beta veya ga olarak ayarla; beta için pilot kullanıcı ID listesi tanımla.',
        );
    }

    /**
     * @return array<string, string>
     */
    protected function routeCheck(): array
    {
        $available = Route::has('mp.trendyol-booster')
            && Route::has('mp.trendyol-booster.companion.status');

        return $this->check(
            'routes',
            'Yayın kontrolü',
            $available ? 'pass' : 'fail',
            'Panel ve companion route zinciri',
            $available ? 'Panel ve companion status route kayıtlı.' : 'Booster route zinciri eksik.',
            'Route cache ve deploy paketini kontrol et.',
        );
    }

    /**
     * @return array<string, string>
     */
    protected function schemaCheck(): array
    {
        $missing = collect($this->requiredTables())
            ->reject(fn (string $table): bool => Schema::hasTable($table))
            ->values();

        return $this->check(
            'schema',
            'Veri tabanı',
            $missing->isEmpty() ? 'pass' : 'fail',
            'Booster modül şeması',
            $missing->isEmpty()
                ? count($this->requiredTables()).' tablo hazır.'
                : 'Eksik tablolar: '.$missing->implode(', '),
            'Migration durumunu doğrula; eksik şemayla canlıya çıkma.',
        );
    }

    /**
     * @return array<string, string>
     */
    protected function schedulerCheck(?int $userId): array
    {
        if (! Schema::hasTable('trendyol_booster_products')) {
            return $this->check(
                'scheduler',
                'Otomasyon',
                'fail',
                'Scheduler çalışma izi',
                'Ürün tablosu olmadığı için scheduler doğrulanamadı.',
                'Önce migration durumunu düzelt.',
            );
        }

        $activeCount = TrendyolBoosterProduct::query()
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->when(
                Schema::hasColumn('trendyol_booster_products', 'tracking_status'),
                fn ($query) => $query->where('tracking_status', 'active'),
            )
            ->count();

        if ($activeCount === 0) {
            return $this->check(
                'scheduler',
                'Otomasyon',
                'warning',
                'Scheduler çalışma izi',
                'Aktif takip olmadığı için uçtan uca scheduler doğrulanamadı.',
                'Pilot bir ürünü takibe alıp sync dry-run ve gerçek koşuyu doğrula.',
            );
        }

        $lastRunAt = $this->lastSchedulerRunAt();
        $recentMinutes = max(15, (int) config('marketplace.trendyol_booster.sync.scheduler_recent_minutes', 15));
        $recent = $lastRunAt?->greaterThanOrEqualTo(now()->subMinutes($recentMinutes)) ?? false;
        $lastRunAgeMinutes = $lastRunAt ? (int) floor($lastRunAt->diffInMinutes(now())) : null;

        return $this->check(
            'scheduler',
            'Otomasyon',
            $recent ? 'pass' : 'fail',
            'Scheduler çalışma izi',
            $recent
                ? 'Son sync '.$lastRunAgeMinutes.' dakika önce çalıştı.'
                : ($lastRunAt ? 'Son sync '.$lastRunAgeMinutes.' dakika önce çalıştı.' : 'Scheduler çalışma izi bulunamadı.'),
            'Scheduler container/cron ve marketplace:sync-trendyol-booster koşusunu kontrol et.',
        );
    }

    /**
     * @return array<string, string>
     */
    protected function queueCheck(): array
    {
        $driver = (string) config('queue.default', 'sync');
        $durable = ! in_array($driver, ['sync', 'null'], true);

        return $this->check(
            'queue',
            'Altyapı',
            $durable ? 'pass' : 'warning',
            'Queue sürücüsü',
            'Aktif queue: '.$driver.'.',
            'Canlıda database veya redis queue ve çalışan worker kullan.',
        );
    }

    /**
     * @return array<string, string>
     */
    protected function cacheCheck(): array
    {
        $store = (string) config('cache.default', 'array');
        $shared = ! in_array($store, ['array', 'null'], true);

        return $this->check(
            'cache',
            'Altyapı',
            $shared ? 'pass' : 'warning',
            'Paylaşımlı cache',
            'Aktif cache store: '.$store.'.',
            'Scheduler izi ve kısa dashboard cache için database veya redis kullan.',
        );
    }

    /**
     * @return array<string, string>
     */
    protected function extensionVersionCheck(): array
    {
        $manifestPath = base_path('browser-extensions/trendyol-booster-companion/manifest.json');
        $readmePath = base_path('browser-extensions/trendyol-booster-companion/README.md');

        if (! is_file($manifestPath) || ! is_file($readmePath)) {
            return $this->check(
                'companion_version',
                'Companion',
                'fail',
                'Companion paket bütünlüğü',
                'Manifest veya README bulunamadı.',
                'Extension kaynaklarının deploy paketinde olduğunu doğrula.',
            );
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $readme = (string) file_get_contents($readmePath);
        $version = is_array($manifest) ? (string) ($manifest['version'] ?? '') : '';
        $valid = preg_match('/^\d+\.\d+\.\d+$/', $version) === 1
            && str_contains($readme, '`'.$version.'`')
            && (int) ($manifest['manifest_version'] ?? 0) === 3;

        return $this->check(
            'companion_version',
            'Companion',
            $valid ? 'pass' : 'fail',
            'Companion paket bütünlüğü',
            $valid ? 'Manifest ve README sürümü eşleşiyor: '.$version.'.' : 'Companion sürüm zinciri tutarsız.',
            'npm run extension:check çalıştır ve sürüm zincirini düzelt.',
        );
    }

    /** @return array<string, string> */
    protected function companionSecurityCheck(): array
    {
        $route = Route::getRoutes()->getByName('mp.trendyol-booster.companion.product-analysis');
        $middleware = $route?->gatherMiddleware() ?? [];
        $secured = collect($middleware)->contains(fn (string $item): bool => str_contains($item, 'AdminMiddleware'))
            && in_array('throttle:booster-companion', $middleware, true)
            && in_array('mp.feature:trendyol_booster_enabled', $middleware, true)
            && in_array('booster.release', $middleware, true)
            && in_array('booster.metric', $middleware, true);
        $packageScript = base_path('scripts/package-trendyol-booster-extension.mjs');
        $packagePolicy = is_file($packageScript)
            && str_contains((string) file_get_contents($packageScript), 'https://m.zolm.com.tr')
            && str_contains((string) file_get_contents($packageScript), 'productionManifest');

        return $this->check(
            'companion_security',
            'Güvenlik',
            $secured && $packagePolicy ? 'pass' : 'fail',
            'Companion erişim ve mağaza manifesti',
            $secured && $packagePolicy
                ? 'Kimlik, feature flag, hız sınırı ve production-origin paket politikası hazır.'
                : 'Companion route veya production manifest bariyeri eksik.',
            'Admin/feature/throttle middleware zincirini ve production paket dönüşümünü doğrula.',
        );
    }

    /**
     * @return array<string, string>
     */
    protected function syncLimitCheck(): array
    {
        $limits = [
            ['path' => 'marketplace.trendyol_booster.sync.product_limit', 'min' => 1, 'max' => 500],
            ['path' => 'marketplace.trendyol_booster.sync.analysis_limit', 'min' => 1, 'max' => 100],
            ['path' => 'marketplace.trendyol_booster.sync.competitor_limit', 'min' => 1, 'max' => 500],
            ['path' => 'marketplace.trendyol_booster.sync.keyword_limit', 'min' => 1, 'max' => 500],
            ['path' => 'marketplace.trendyol_booster.sync.store_limit', 'min' => 1, 'max' => 100],
            ['path' => 'marketplace.trendyol_booster.companion.max_stock_sellers', 'min' => 1, 'max' => 100],
            ['path' => 'marketplace.trendyol_booster.companion.max_store_items', 'min' => 1, 'max' => 500],
        ];
        $invalid = collect($limits)->filter(function (array $limit): bool {
            $value = (int) config($limit['path'], 0);

            return $value < $limit['min'] || $value > $limit['max'];
        });

        return $this->check(
            'sync_limits',
            'Kapasite',
            $invalid->isEmpty() ? 'pass' : 'fail',
            'Sync ve payload limitleri',
            $invalid->isEmpty() ? 'Tüm işlem limitleri güvenli aralıkta.' : 'Güvensiz limit: '.$invalid->pluck('path')->implode(', '),
            'Limitleri doğrulanmış güvenli aralıklara çek.',
        );
    }

    /**
     * @return array<string, string>
     */
    protected function notificationCheck(): array
    {
        $enabled = (bool) config('marketplace.features.notifications_enabled', false);
        $thresholds = [
            'marketplace.trendyol_booster.notifications.price_min_delta_amount',
            'marketplace.trendyol_booster.notifications.price_min_delta_percent',
            'marketplace.trendyol_booster.notifications.stock_min_delta_units',
            'marketplace.trendyol_booster.notifications.stock_min_estimated_sales',
            'marketplace.trendyol_booster.notifications.store_min_new_products',
            'marketplace.trendyol_booster.notifications.store_min_price_changes',
            'marketplace.trendyol_booster.notifications.keyword_min_rank_delta',
        ];
        $invalid = collect($thresholds)->filter(fn (string $path): bool => (float) config($path, -1) < 0);
        $status = $invalid->isNotEmpty() ? 'fail' : ($enabled ? 'pass' : 'warning');

        return $this->check(
            'notifications',
            'Bildirim',
            $status,
            'Bildirim merkezi ve eşikler',
            $invalid->isNotEmpty()
                ? 'Negatif bildirim eşiği var: '.$invalid->implode(', ')
                : ($enabled ? 'Bildirim merkezi açık ve eşikler geçerli.' : 'Bildirim merkezi kapalı.'),
            'Eşikleri negatif olmayan değerlere ayarla; canlı uyarılar için bildirim merkezini aç.',
        );
    }

    /**
     * @return array<string, string>
     */
    protected function retentionCheck(): array
    {
        $cleanupEnabled = (bool) config('marketplace.trendyol_booster.retention.cleanup_enabled', false);
        $batchSize = (int) config('marketplace.trendyol_booster.retention.cleanup_batch_size', 500);
        $maxDelete = (int) config('marketplace.trendyol_booster.retention.cleanup_max_delete_per_run', 10000);
        $unsafeWindows = collect($this->retentionReportService->datasetDefinitions())
            ->filter(function (array $dataset): bool {
                $days = (int) config(
                    'marketplace.trendyol_booster.retention.'.$dataset['retention_key'],
                    $dataset['default_days'],
                );

                return $days < 30;
            });
        $safeLimits = $batchSize >= 50 && $batchSize <= 2000 && $maxDelete >= 1 && $maxDelete <= 100000;
        $status = ! $safeLimits || $unsafeWindows->isNotEmpty() ? 'fail' : ($cleanupEnabled ? 'warning' : 'pass');

        return $this->check(
            'retention',
            'Veri güvenliği',
            $status,
            'Retention güvenlik bariyerleri',
            match ($status) {
                'fail' => 'Retention süresi veya silme limitleri güvenli aralık dışında.',
                'warning' => 'Gerçek retention temizliği açık; kullanıcı bazlı pilot ve dry-run kanıtı gerekli.',
                default => 'Gerçek temizlik kapalı; retention süreleri ve limitler güvenli.',
            },
            'İlk yayında cleanup kapalı kalsın; dry-run sonrası kullanıcı bazlı ve sınırlı aç.',
        );
    }

    /**
     * @return array<string, string>
     */
    protected function check(
        string $key,
        string $group,
        string $status,
        string $label,
        string $detail,
        string $action,
    ): array {
        return compact('key', 'group', 'status', 'label', 'detail', 'action');
    }

    protected function lastSchedulerRunAt(): ?Carbon
    {
        $value = Cache::get(self::LAST_RUN_CACHE_KEY);

        if (! filled($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, string>
     */
    protected function requiredTables(): array
    {
        return [
            'trendyol_booster_products',
            'trendyol_booster_snapshots',
            'trendyol_booster_competitors',
            'trendyol_booster_keywords',
            'trendyol_booster_campaign_scenarios',
            'trendyol_booster_cost_presets',
            'trendyol_booster_stock_checks',
            'trendyol_booster_stock_sellers',
            'trendyol_booster_keyword_lookups',
            'trendyol_booster_store_watches',
            'trendyol_booster_store_watch_items',
            'trendyol_booster_trend_keywords',
            'trendyol_booster_commission_rates',
            'trendyol_booster_activity_logs',
            'trendyol_booster_shipping_rates',
            'trendyol_booster_cost_recommendations',
            'trendyol_booster_supplier_researches',
            'trendyol_booster_supplier_offers',
            'trendyol_booster_keyword_observations',
            'trendyol_booster_store_item_histories',
            'trendyol_booster_store_watch_snapshots',
            'trendyol_booster_action_states',
            'trendyol_booster_action_audits',
            'trendyol_booster_collections',
            'trendyol_booster_collection_items',
            'trendyol_booster_operation_metrics',
            'trendyol_bestseller_reports',
            'trendyol_bestseller_report_runs',
            'trendyol_bestseller_report_items',
        ];
    }
}
