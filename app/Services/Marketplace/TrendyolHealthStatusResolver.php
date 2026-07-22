<?php

namespace App\Services\Marketplace;

use App\Models\CargoInvoiceLine;
use App\Models\IntegrationPushRun;
use App\Models\IntegrationSyncRun;
use App\Models\MarketplaceStore;
use App\Models\MpBrand;
use App\Models\MpBuyboxListing;
use App\Models\MpCategory;
use App\Models\MpClaimReason;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TrendyolHealthStatusResolver
{
    /**
     * Durum sabitleri
     */
    const STATUS_HEALTHY = 'healthy';
    const STATUS_WARNING = 'warning';
    const STATUS_CRITICAL = 'critical';
    const STATUS_DISABLED = 'disabled';
    const STATUS_NEVER_SYNCED = 'never_synced';

    /**
     * Scheduler gecikmesi için çarpan: son başarılı çalışma beklenen interval'in bu katını aşarsa uyarı üretilir.
     */
    protected int $warningMultiplier = 2;
    protected int $criticalMultiplier = 4;

    public function __construct()
    {
        $this->warningMultiplier = (int) config('marketplace.trendyol.health.warning_multiplier', 2);
        $this->criticalMultiplier = (int) config('marketplace.trendyol.health.critical_multiplier', 4);
    }

    /**
     * Belirtilen mağaza için tam sağlık raporu döndür.
     */
    public function resolve(MarketplaceStore $store): array
    {
        return [
            'api' => $this->apiConnectionMetrics($store),
            'order_stream' => $this->orderStreamMetrics($store),
            'buybox' => $this->buyboxMetrics($store),
            'batch' => $this->batchMetrics($store),
            'cargo_invoice' => $this->cargoInvoiceMetrics($store),
            'reference_data' => $this->referenceDataMetrics($store),
        ];
    }

    /**
     * API bağlantı durumu
     */
    public function apiConnectionMetrics(MarketplaceStore $store): array
    {
        // Son tamamlanan sync run üzerinden API sağlığını çek
        $lastRun = IntegrationSyncRun::where('store_id', $store->id)
            ->whereIn('status', ['completed', 'failed'])
            ->orderByDesc('finished_at')
            ->first();

        if (! $lastRun) {
            return [
                'status' => self::STATUS_NEVER_SYNCED,
                'last_successful_at' => null,
                'last_http_status' => null,
                'last_error' => null,
            ];
        }

        $lastSuccessful = IntegrationSyncRun::where('store_id', $store->id)
            ->where('status', 'completed')
            ->orderByDesc('finished_at')
            ->value('finished_at');

        $isRecent = $lastSuccessful && Carbon::parse($lastSuccessful)->diffInMinutes(now()) < 120;

        return [
            'status' => match (true) {
                $lastRun->status === 'failed' && ! $isRecent => self::STATUS_CRITICAL,
                $lastRun->status === 'failed' => self::STATUS_WARNING,
                $isRecent => self::STATUS_HEALTHY,
                default => self::STATUS_WARNING,
            },
            'last_successful_at' => $lastSuccessful,
            'last_http_status' => null, // Connector'dan gelecek
            'last_error' => $lastRun->status === 'failed'
                ? ($lastRun->notes_json['error'] ?? 'Bilinmeyen hata')
                : null,
        ];
    }

    /**
     * Sipariş Stream metrikleri
     */
    public function orderStreamMetrics(MarketplaceStore $store): array
    {
        $enabled = config('marketplace.trendyol.order_stream_enabled', true);

        if (! $enabled) {
            return ['status' => self::STATUS_DISABLED, 'feature_enabled' => false];
        }

        $lastStreamRun = IntegrationSyncRun::where('store_id', $store->id)
            ->where('sync_type', 'orders')
            ->orderByDesc('finished_at')
            ->first();

        if (! $lastStreamRun) {
            return [
                'status' => self::STATUS_NEVER_SYNCED,
                'feature_enabled' => true,
                'last_synced_at' => null,
                'cursor_state' => null,
                'items_received' => 0,
            ];
        }

        // Beklenen polling aralığı: 15 dakika (config'den alınır)
        $expectedIntervalMinutes = (int) config('marketplace.trendyol.health.order_stream_interval_minutes', 15);
        $delayMinutes = $lastStreamRun->finished_at
            ? Carbon::parse($lastStreamRun->finished_at)->diffInMinutes(now())
            : PHP_INT_MAX;

        $status = $this->calcSchedulerStatus($delayMinutes, $expectedIntervalMinutes);

        $notes = $lastStreamRun->notes_json ?? [];

        return [
            'status' => $status,
            'feature_enabled' => true,
            'last_synced_at' => $lastStreamRun->finished_at,
            'cursor_before' => $lastStreamRun->cursor_before,
            'cursor_after' => $lastStreamRun->cursor_after,
            'items_received' => $lastStreamRun->items_received ?? 0,
            'delay_minutes' => $delayMinutes,
            'expected_interval_minutes' => $expectedIntervalMinutes,
        ];
    }

    /**
     * Buybox senkronizasyon metrikleri
     */
    public function buyboxMetrics(MarketplaceStore $store): array
    {
        $enabled = config('marketplace.trendyol.buybox_sync_enabled', false);

        if (! $enabled) {
            return ['status' => self::STATUS_DISABLED, 'feature_enabled' => false];
        }

        $lastRun = IntegrationSyncRun::where('store_id', $store->id)
            ->where('sync_type', 'buybox')
            ->orderByDesc('finished_at')
            ->first();

        $expectedIntervalMinutes = (int) config('marketplace.trendyol.health.buybox_interval_minutes', 30);

        if (! $lastRun) {
            return ['status' => self::STATUS_NEVER_SYNCED, 'feature_enabled' => true];
        }

        $delayMinutes = Carbon::parse($lastRun->finished_at)->diffInMinutes(now());

        // DB stats (tek sorgu)
        $stats = MpBuyboxListing::where('store_id', $store->id)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN seller_rank = 1 THEN 1 ELSE 0 END) as winning,
                SUM(CASE WHEN seller_rank != 1 THEN 1 ELSE 0 END) as losing,
                SUM(CASE WHEN retrieved_at < ? THEN 1 ELSE 0 END) as stale
            ', [now()->subMinutes(60)->toDateTimeString()])
            ->first();

        return [
            'status' => $this->calcSchedulerStatus($delayMinutes, $expectedIntervalMinutes),
            'feature_enabled' => true,
            'last_synced_at' => $lastRun->finished_at,
            'total_products' => (int) ($stats->total ?? 0),
            'winning_count' => (int) ($stats->winning ?? 0),
            'losing_count' => (int) ($stats->losing ?? 0),
            'stale_count' => (int) ($stats->stale ?? 0),
            'delay_minutes' => $delayMinutes,
        ];
    }

    /**
     * Batch işlem metrikleri
     */
    public function batchMetrics(MarketplaceStore $store): array
    {
        $enabled = config('marketplace.trendyol.batch_tracking_enabled', false);

        if (! $enabled) {
            return ['status' => self::STATUS_DISABLED, 'feature_enabled' => false];
        }

        // Tek sorguda tüm durum sayıları
        $counts = IntegrationPushRun::where('store_id', $store->id)
            ->whereDate('created_at', '>=', now()->subDays(7))
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $failed = (int) ($counts['failed'] ?? 0);
        $total = $counts->sum();

        $status = match(true) {
            $failed > 0 && $total > 0 && ($failed / $total) > 0.3 => self::STATUS_CRITICAL,
            $failed > 0 => self::STATUS_WARNING,
            default => self::STATUS_HEALTHY,
        };

        return [
            'status' => $status,
            'feature_enabled' => true,
            'pending' => (int) ($counts['pending'] ?? 0),
            'processing' => (int) ($counts['processing'] ?? 0),
            'success' => (int) ($counts['completed'] ?? 0),
            'partial_success' => (int) ($counts['partial_success'] ?? 0),
            'failed' => $failed,
            'expired' => (int) ($counts['expired'] ?? 0),
        ];
    }

    /**
     * Kargo faturası metrikleri
     */
    public function cargoInvoiceMetrics(MarketplaceStore $store): array
    {
        $enabled = config('marketplace.trendyol.cargo_invoice_sync_enabled', false);

        if (! $enabled) {
            return ['status' => self::STATUS_DISABLED, 'feature_enabled' => false];
        }

        $lastRun = IntegrationSyncRun::where('store_id', $store->id)
            ->where('sync_type', 'cargo_invoice')
            ->orderByDesc('finished_at')
            ->first();

        $stats = CargoInvoiceLine::where('store_id', $store->id)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN order_number IS NOT NULL THEN 1 ELSE 0 END) as matched,
                SUM(CASE WHEN order_number IS NULL THEN 1 ELSE 0 END) as unmatched
            ')
            ->first();

        $expectedIntervalMinutes = (int) config('marketplace.trendyol.health.cargo_invoice_interval_minutes', 1440); // 1 gün

        $delayMinutes = $lastRun?->finished_at
            ? Carbon::parse($lastRun->finished_at)->diffInMinutes(now())
            : PHP_INT_MAX;

        return [
            'status' => $lastRun
                ? $this->calcSchedulerStatus($delayMinutes, $expectedIntervalMinutes)
                : self::STATUS_NEVER_SYNCED,
            'feature_enabled' => true,
            'last_synced_at' => $lastRun?->finished_at,
            'total_lines' => (int) ($stats->total ?? 0),
            'matched_lines' => (int) ($stats->matched ?? 0),
            'unmatched_lines' => (int) ($stats->unmatched ?? 0),
            'last_error' => $lastRun?->status === 'failed'
                ? ($lastRun->notes_json['error'] ?? 'Hata')
                : null,
        ];
    }

    /**
     * Referans veri metrikleri (marka, kategori, iade nedeni)
     */
    public function referenceDataMetrics(MarketplaceStore $store): array
    {
        $enabled = config('marketplace.trendyol.reference_sync_enabled', false);

        if (! $enabled) {
            return ['status' => self::STATUS_DISABLED, 'feature_enabled' => false];
        }

        $lastRun = IntegrationSyncRun::where('store_id', $store->id)
            ->where('sync_type', 'reference')
            ->orderByDesc('finished_at')
            ->first();

        // Brands and categories are marketplace-scoped, not store-scoped
        $brandCount = MpBrand::where('marketplace', $store->marketplace)->count();
        $categoryCount = MpCategory::where('marketplace', $store->marketplace)->count();
        $leafCategoryCount = MpCategory::where('marketplace', $store->marketplace)->where('is_leaf', true)->count();
        $claimReasonCount = MpClaimReason::where('store_id', $store->id)->count();

        $expectedIntervalMinutes = (int) config('marketplace.trendyol.health.reference_interval_minutes', 10080); // 1 hafta

        $delayMinutes = $lastRun?->finished_at
            ? Carbon::parse($lastRun->finished_at)->diffInMinutes(now())
            : PHP_INT_MAX;

        return [
            'status' => $lastRun
                ? $this->calcSchedulerStatus($delayMinutes, $expectedIntervalMinutes)
                : ($brandCount > 0 ? self::STATUS_HEALTHY : self::STATUS_NEVER_SYNCED),
            'feature_enabled' => true,
            'last_synced_at' => $lastRun?->finished_at,
            'brand_count' => $brandCount,
            'category_count' => $categoryCount,
            'leaf_category_count' => $leafCategoryCount,
            'claim_reason_count' => $claimReasonCount,
        ];
    }

    /**
     * Scheduler gecikmesine göre durum hesapla.
     */
    protected function calcSchedulerStatus(int $delayMinutes, int $expectedIntervalMinutes): string
    {
        if ($delayMinutes === PHP_INT_MAX) {
            return self::STATUS_NEVER_SYNCED;
        }

        if ($delayMinutes > $expectedIntervalMinutes * $this->criticalMultiplier) {
            return self::STATUS_CRITICAL;
        }

        if ($delayMinutes > $expectedIntervalMinutes * $this->warningMultiplier) {
            return self::STATUS_WARNING;
        }

        return self::STATUS_HEALTHY;
    }
}
