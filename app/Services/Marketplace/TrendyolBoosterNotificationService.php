<?php

namespace App\Services\Marketplace;

use App\Models\AppNotification;
use App\Models\TrendyolBoosterKeyword;
use App\Models\TrendyolBoosterSnapshot;
use App\Models\TrendyolBoosterStockCheck;
use App\Models\TrendyolBoosterStoreWatch;
use App\Services\NotificationCenterService;
use Illuminate\Support\Str;

class TrendyolBoosterNotificationService
{
    public function __construct(
        protected NotificationCenterService $notificationCenter,
    ) {}

    public function notifyPriceSnapshot(TrendyolBoosterSnapshot $snapshot): ?AppNotification
    {
        $delta = (float) $snapshot->price_delta;
        $deltaPercent = (float) $snapshot->price_delta_percent;

        if ((float) $snapshot->previous_sale_price <= 0 || ! $this->passesPriceThreshold($delta, $deltaPercent)) {
            return null;
        }

        $snapshot->loadMissing('trackedProduct');
        $tracked = $snapshot->trackedProduct;
        $isDrop = $delta < 0;

        return $this->notificationCenter->createForUser((int) $snapshot->user_id, [
            'type' => $isDrop ? 'booster_price_drop' : 'booster_price_rise',
            'severity' => $isDrop ? 'info' : 'warning',
            'event_key' => "trendyol-booster-price:{$snapshot->id}",
            'title' => $isDrop ? 'Booster fiyat düşüşü yakaladı' : 'Booster fiyat artışı yakaladı',
            'body' => $this->joinBody([
                $tracked?->title ?: 'Trendyol ürünü',
                $this->money((float) $snapshot->previous_sale_price).' -> '.$this->money((float) $snapshot->sale_price),
                $this->signedMoney($delta),
                $this->signedPercent((float) $snapshot->price_delta_percent),
            ]),
            'subject_type' => get_class($snapshot),
            'subject_id' => $snapshot->id,
            'data_json' => [
                'tracked_product_id' => $tracked?->id,
                'snapshot_id' => $snapshot->id,
                'source_url' => $tracked?->source_url,
                'sale_price' => (float) $snapshot->sale_price,
                'previous_sale_price' => (float) $snapshot->previous_sale_price,
                'price_delta' => $delta,
                'price_delta_percent' => $deltaPercent,
            ],
            'action_url' => $this->boosterUrl('price', $tracked?->id),
            'triggered_at' => $snapshot->checked_at ?: now(),
        ]);
    }

    public function notifyStockCheck(TrendyolBoosterStockCheck $check): ?AppNotification
    {
        if ($check->previous_total_stock === null) {
            return null;
        }

        $delta = (int) $check->stock_delta;
        $estimatedSales = (int) $check->estimated_sales;

        if ($delta === 0 && $estimatedSales === 0) {
            return null;
        }

        if (! $this->passesStockThreshold($delta, $estimatedSales)) {
            return null;
        }

        $check->loadMissing('trackedProduct');
        $type = $estimatedSales > 0 ? 'booster_stock_sales' : 'booster_stock_change';
        $title = $estimatedSales > 0 ? 'Booster stok erimesi yakaladı' : 'Booster stok değişimi yakaladı';

        return $this->notificationCenter->createForUser((int) $check->user_id, [
            'type' => $type,
            'severity' => $estimatedSales > 0 ? 'warning' : 'info',
            'event_key' => "trendyol-booster-stock:{$check->id}",
            'title' => $title,
            'body' => $this->joinBody([
                $check->title ?: $check->trackedProduct?->title ?: 'Trendyol ürünü',
                'stok '.(int) $check->previous_total_stock.' -> '.(int) $check->total_stock,
                $estimatedSales > 0 ? "tahmini {$estimatedSales} satış" : 'değişim '.$this->signedNumber($delta),
                $check->seller_count > 0 ? "{$check->seller_count} satıcı" : null,
            ]),
            'subject_type' => get_class($check),
            'subject_id' => $check->id,
            'data_json' => [
                'tracked_product_id' => $check->trendyol_booster_product_id,
                'stock_check_id' => $check->id,
                'source_url' => $check->source_url,
                'total_stock' => (int) $check->total_stock,
                'previous_total_stock' => (int) $check->previous_total_stock,
                'stock_delta' => $delta,
                'estimated_sales' => $estimatedSales,
                'seller_count' => (int) $check->seller_count,
            ],
            'action_url' => $this->boosterUrl('stock', $check->trendyol_booster_product_id),
            'triggered_at' => $check->checked_at ?: now(),
        ]);
    }

    public function notifyStoreWatch(TrendyolBoosterStoreWatch $watch): ?AppNotification
    {
        $newProducts = (int) $watch->new_product_count;
        $priceChanges = (int) $watch->price_change_count;

        if ((int) $watch->scan_count <= 1 || ! $this->passesStoreThreshold($newProducts, $priceChanges)) {
            return null;
        }

        $eventStamp = $watch->last_checked_at?->format('YmdHis') ?: now()->format('YmdHis');

        return $this->notificationCenter->createForUser((int) $watch->user_id, [
            'type' => 'booster_store_change',
            'severity' => $priceChanges > 0 ? 'warning' : 'info',
            'event_key' => "trendyol-booster-store:{$watch->id}:{$eventStamp}:{$newProducts}:{$priceChanges}",
            'title' => 'Booster rakip mağaza değişimi yakaladı',
            'body' => $this->joinBody([
                $watch->store_name ?: 'Rakip mağaza',
                $newProducts > 0 ? "{$newProducts} yeni ürün" : null,
                $priceChanges > 0 ? "{$priceChanges} fiyat değişimi" : null,
                $watch->total_products > 0 ? "{$watch->total_products} ürün izlendi" : null,
            ]),
            'subject_type' => get_class($watch),
            'subject_id' => $watch->id,
            'data_json' => [
                'store_watch_id' => $watch->id,
                'store_url' => $watch->store_url,
                'store_id' => $watch->store_id,
                'store_name' => $watch->store_name,
                'new_product_count' => $newProducts,
                'price_change_count' => $priceChanges,
                'total_products' => (int) $watch->total_products,
            ],
            'action_url' => $this->boosterUrl('competitor'),
            'triggered_at' => $watch->last_checked_at ?: now(),
        ]);
    }

    public function notifyKeywordVisibility(
        TrendyolBoosterKeyword $keyword,
        ?int $previousRank,
        ?string $previousStatus,
    ): ?AppNotification {
        if ($previousRank === null && $previousStatus === null) {
            return null;
        }

        $currentRank = $keyword->observed_rank;
        $currentStatus = $keyword->visibility_status;

        if ($previousRank === $currentRank && $previousStatus === $currentStatus) {
            return null;
        }

        if (! $this->passesKeywordThreshold($previousRank, $currentRank, $previousStatus, $currentStatus)) {
            return null;
        }

        $keyword->loadMissing('trackedProduct');
        $worse = $this->keywordBecameRiskier($previousRank, $currentRank, $previousStatus, $currentStatus);

        return $this->notificationCenter->createForUser((int) $keyword->user_id, [
            'type' => 'booster_keyword_change',
            'severity' => $worse ? 'warning' : 'info',
            'event_key' => 'trendyol-booster-keyword:'.$keyword->id.':'.($keyword->last_checked_at?->format('YmdHis') ?: now()->format('YmdHis')),
            'title' => 'Booster kelime sırası değişti',
            'body' => $this->joinBody([
                $keyword->keyword,
                $keyword->trackedProduct?->title,
                'sıra '.$this->rankLabel($previousRank).' -> '.$this->rankLabel($currentRank),
                $keyword->visibility_note,
            ]),
            'subject_type' => get_class($keyword),
            'subject_id' => $keyword->id,
            'data_json' => [
                'tracked_product_id' => $keyword->trendyol_booster_product_id,
                'keyword_id' => $keyword->id,
                'keyword' => $keyword->keyword,
                'previous_rank' => $previousRank,
                'observed_rank' => $currentRank,
                'previous_status' => $previousStatus,
                'visibility_status' => $currentStatus,
                'target_rank' => (int) $keyword->target_rank,
            ],
            'action_url' => $this->boosterUrl('decision', $keyword->trendyol_booster_product_id),
            'triggered_at' => $keyword->last_checked_at ?: now(),
        ]);
    }

    protected function boosterUrl(string $module, ?int $trackedProductId = null): string
    {
        return route('mp.trendyol-booster', array_filter([
            'booster' => $module,
            'product' => $trackedProductId,
        ]));
    }

    /**
     * @param  array<int, mixed>  $parts
     */
    protected function joinBody(array $parts): string
    {
        return collect($parts)
            ->map(fn (mixed $part): string => trim((string) $part))
            ->filter()
            ->map(fn (string $part): string => Str::limit($part, 90))
            ->implode(' · ');
    }

    protected function money(float $value): string
    {
        return number_format($value, 2, ',', '.').' TL';
    }

    protected function signedMoney(float $value): string
    {
        $prefix = $value > 0 ? '+' : '';

        return $prefix.$this->money($value);
    }

    protected function signedPercent(float $value): string
    {
        $prefix = $value > 0 ? '+' : '';

        return $prefix.number_format($value, 2, ',', '.').'%';
    }

    protected function signedNumber(int $value): string
    {
        return ($value > 0 ? '+' : '').(string) $value;
    }

    protected function passesPriceThreshold(float $delta, float $deltaPercent): bool
    {
        $minAmount = max(0.01, (float) config('marketplace.trendyol_booster.notifications.price_min_delta_amount', 0.01));
        $minPercent = max(0.0, (float) config('marketplace.trendyol_booster.notifications.price_min_delta_percent', 0));

        if (abs($delta) >= $minAmount) {
            return true;
        }

        return $minPercent > 0 && abs($deltaPercent) >= $minPercent;
    }

    protected function passesStockThreshold(int $delta, int $estimatedSales): bool
    {
        $minDelta = max(1, (int) config('marketplace.trendyol_booster.notifications.stock_min_delta_units', 1));
        $minSales = max(1, (int) config('marketplace.trendyol_booster.notifications.stock_min_estimated_sales', 1));

        return abs($delta) >= $minDelta || $estimatedSales >= $minSales;
    }

    protected function passesStoreThreshold(int $newProducts, int $priceChanges): bool
    {
        $minNewProducts = max(1, (int) config('marketplace.trendyol_booster.notifications.store_min_new_products', 1));
        $minPriceChanges = max(1, (int) config('marketplace.trendyol_booster.notifications.store_min_price_changes', 1));

        return $newProducts >= $minNewProducts || $priceChanges >= $minPriceChanges;
    }

    protected function passesKeywordThreshold(?int $previousRank, ?int $currentRank, ?string $previousStatus, ?string $currentStatus): bool
    {
        if ($previousStatus !== $currentStatus) {
            return true;
        }

        if ($previousRank === null || $currentRank === null) {
            return true;
        }

        $minRankDelta = max(1, (int) config('marketplace.trendyol_booster.notifications.keyword_min_rank_delta', 1));

        return abs($currentRank - $previousRank) >= $minRankDelta;
    }

    protected function rankLabel(?int $rank): string
    {
        return $rank !== null ? (string) $rank : 'yok';
    }

    protected function keywordBecameRiskier(?int $previousRank, ?int $currentRank, ?string $previousStatus, ?string $currentStatus): bool
    {
        $riskOrder = [
            'visible' => 1,
            'near' => 2,
            'tracking' => 2,
            'low_visibility' => 3,
            'missing' => 4,
        ];

        $previousRisk = $riskOrder[$previousStatus ?? 'tracking'] ?? 2;
        $currentRisk = $riskOrder[$currentStatus ?? 'tracking'] ?? 2;

        if ($currentRisk > $previousRisk) {
            return true;
        }

        return $previousRank !== null && $currentRank !== null && $currentRank > $previousRank;
    }
}
