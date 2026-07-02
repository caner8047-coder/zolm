<?php

namespace App\Services\Marketplace;

use App\Models\TrendyolBoosterProduct;
use App\Models\TrendyolBoosterSnapshot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TrendyolBoosterMonitorService
{
    public function __construct(
        protected TrendyolProductPageReader $reader,
        protected TrendyolBoosterAnalysisService $analysisService,
        protected TrendyolBoosterNotificationService $notificationService,
        protected TrendyolBoosterActivityLogger $activityLogger,
        protected TrendyolBoosterIntelligenceService $intelligenceService,
    ) {
    }

    /**
     * @return array{ok: bool, message: string, product: TrendyolBoosterProduct, snapshot: ?TrendyolBoosterSnapshot}
     */
    public function check(TrendyolBoosterProduct $tracked): array
    {
        $tracked->loadMissing(['latestSnapshot']);
        $previousPrice = (float) ($tracked->latestSnapshot?->sale_price ?? $tracked->sale_price);
        $result = $this->reader->fetch((string) $tracked->source_url);

        if (! $result['ok']) {
            $tracked->forceFill(['last_checked_at' => now()])->save();
            $this->logSyncIssue($tracked, 'sync_error', 'Otomatik ürün kontrolü başarısız', $result['message'], [
                'source' => 'product_monitor',
            ]);

            return [
                'ok' => false,
                'message' => $result['message'],
                'product' => $tracked->fresh() ?: $tracked,
                'snapshot' => null,
            ];
        }

        $pageData = $result['data'];
        $salePrice = (float) ($pageData['sale_price'] ?? 0) > 0
            ? (float) $pageData['sale_price']
            : (float) $tracked->sale_price;
        $updated = $this->analysisService->store($tracked->user_id, $this->analysisInput($tracked, $pageData, $salePrice));
        $snapshot = $this->createSnapshot($updated, $previousPrice, $pageData);
        $this->intelligenceService->calculate($updated, $snapshot);
        $snapshot->refresh();
        $this->notificationService->notifyPriceSnapshot($snapshot);

        if ($this->isFallbackMessage($result['message'])) {
            $this->logSyncIssue($updated, 'sync_fallback', 'Otomatik ürün kontrolü fallback kullandı', $result['message'], [
                'source' => 'product_monitor',
                'snapshot_id' => $snapshot->id,
            ]);
        }

        return [
            'ok' => true,
            'message' => $this->resultMessage($snapshot),
            'product' => $updated,
            'snapshot' => $snapshot,
        ];
    }

    /**
     * @return array{processed: int, succeeded: int, failed: int, snapshots: int, skipped: int, dry_run: bool}
     */
    public function checkDue(int $limit = 50, ?int $userId = null, ?int $staleMinutes = null, bool $dryRun = false): array
    {
        $query = TrendyolBoosterProduct::query()
            ->when(
                Schema::hasColumn('trendyol_booster_products', 'tracking_status'),
                fn (Builder $query) => $query->where('tracking_status', 'active'),
            )
            ->when(
                Schema::hasColumn('trendyol_booster_products', 'analysis_auto_refresh_enabled'),
                fn (Builder $query) => $query->where('analysis_auto_refresh_enabled', false),
            )
            ->where(function (Builder $query): void {
                $query->where('watch_price', true)->orWhere('watch_stock', true);
            })
            ->when($userId !== null, fn (Builder $query) => $query->where('user_id', $userId))
            ->when($staleMinutes !== null && $staleMinutes > 0, function (Builder $query) use ($staleMinutes): void {
                $query->where(function (Builder $staleQuery) use ($staleMinutes): void {
                    $staleQuery
                        ->whereNull('last_checked_at')
                        ->orWhere('last_checked_at', '<=', now()->subMinutes($staleMinutes));
                });
            })
            ->orderBy('last_checked_at')
            ->orderBy('id')
            ->limit(max(1, min(500, $limit)));

        $summary = [
            'processed' => 0,
            'succeeded' => 0,
            'failed' => 0,
            'snapshots' => 0,
            'skipped' => 0,
            'dry_run' => $dryRun,
        ];

        foreach ($query->get() as $tracked) {
            $summary['processed']++;

            if ($dryRun) {
                $summary['skipped']++;

                continue;
            }

            try {
                $result = $this->check($tracked);
            } catch (\Throwable $exception) {
                $summary['failed']++;
                $this->logSyncIssue($tracked, 'sync_error', 'Otomatik ürün kontrolü hata verdi', $exception->getMessage(), [
                    'source' => 'product_monitor',
                    'exception' => get_class($exception),
                ]);

                continue;
            }

            if ($result['ok']) {
                $summary['succeeded']++;
                $summary['snapshots'] += $result['snapshot'] ? 1 : 0;
            } else {
                $summary['failed']++;
            }
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $pageData
     * @return array<string, mixed>
     */
    protected function analysisInput(TrendyolBoosterProduct $tracked, array $pageData, float $salePrice): array
    {
        $storedInput = (array) data_get($tracked->simulation_json, 'input', []);

        return array_merge($storedInput, [
            'user_id' => $tracked->user_id,
            'source_url' => $tracked->source_url,
            'mp_product_id' => $tracked->mp_product_id,
            'channel_listing_id' => $tracked->channel_listing_id,
            'title' => $this->filledText($pageData['title'] ?? null, (string) $tracked->title),
            'brand' => $this->filledText($pageData['brand'] ?? null, (string) $tracked->brand),
            'category_name' => $this->filledText($pageData['category_name'] ?? null, (string) $tracked->category_name),
            'sale_price' => $salePrice,
            'cogs' => (float) $tracked->cogs,
            'packaging_cost' => (float) $tracked->packaging_cost,
            'cargo_cost' => (float) $tracked->cargo_cost,
            'return_cargo_cost' => (float) ($storedInput['return_cargo_cost'] ?? $tracked->cargo_cost),
            'commission_rate' => (float) $tracked->commission_rate,
            'service_fee_rate' => (float) ($storedInput['service_fee_rate'] ?? 0),
            'advertising_rate' => (float) ($storedInput['advertising_rate'] ?? 0),
            'return_rate' => (float) $tracked->return_rate,
            'vat_enabled' => (bool) ($storedInput['vat_enabled'] ?? false),
            'withholding_enabled' => (bool) ($storedInput['withholding_enabled'] ?? false),
            'vat_rate' => (float) $tracked->vat_rate,
            'cost_vat_rate' => (float) $tracked->cost_vat_rate,
            'expense_vat_rate' => (float) ($storedInput['expense_vat_rate'] ?? 20),
            'withholding_rate' => (float) ($storedInput['withholding_rate'] ?? 1),
            'target_margin_percent' => (float) ($storedInput['target_margin_percent'] ?? 20),
            'watch_price' => (bool) $tracked->watch_price,
            'watch_stock' => (bool) $tracked->watch_stock,
            'watch_keyword' => (bool) $tracked->watch_keyword,
        ]);
    }

    /**
     * @param  array<string, mixed>  $pageData
     */
    protected function createSnapshot(TrendyolBoosterProduct $updated, float $previousPrice, array $pageData): TrendyolBoosterSnapshot
    {
        $salePrice = (float) $updated->sale_price;
        $delta = round($salePrice - $previousPrice, 2);
        $deltaPercent = $previousPrice > 0 ? round(($delta / $previousPrice) * 100, 2) : 0.0;

        return TrendyolBoosterSnapshot::query()->create([
            'trendyol_booster_product_id' => $updated->id,
            'user_id' => $updated->user_id,
            'sale_price' => $salePrice,
            'previous_sale_price' => $previousPrice > 0 ? $previousPrice : null,
            'price_delta' => $delta,
            'price_delta_percent' => $deltaPercent,
            'stock_status' => (string) ($pageData['stock_status'] ?? 'unknown'),
            'availability' => $this->filledText($pageData['availability'] ?? null, ''),
            'stock_quantity' => is_numeric($pageData['total_stock'] ?? null) ? max(0, (int) $pageData['total_stock']) : null,
            'evaluation_count' => is_numeric($pageData['evaluation_count'] ?? null) ? max(0, (int) $pageData['evaluation_count']) : null,
            'review_count' => is_numeric($pageData['review_count'] ?? null) ? max(0, (int) $pageData['review_count']) : null,
            'average_rating' => is_numeric($pageData['average_rating'] ?? null) ? max(0, min(5, (float) $pageData['average_rating'])) : null,
            'favorite_count' => is_numeric($pageData['favorite_count'] ?? null) ? max(0, (int) $pageData['favorite_count']) : null,
            'favorite_precision' => $pageData['favorite_precision'] ?? null,
            'question_count' => is_numeric($pageData['question_count'] ?? null) ? max(0, (int) $pageData['question_count']) : null,
            'category_rank' => is_numeric($pageData['category_rank'] ?? null) ? max(0, (int) $pageData['category_rank']) : null,
            'seller_score' => is_numeric($pageData['seller_score'] ?? null) ? max(0, min(10, (float) $pageData['seller_score'])) : null,
            'seller_follower_count' => is_numeric($pageData['seller_follower_count'] ?? null) ? max(0, (int) $pageData['seller_follower_count']) : null,
            'campaign_count' => is_numeric($pageData['campaign_count'] ?? null) ? max(0, (int) $pageData['campaign_count']) : null,
            'analysis_source' => 'product_monitor',
            'data_sources' => $pageData['data_sources'] ?? ['product_page_reader'],
            'opportunity_score' => (int) $updated->opportunity_score,
            'decision_status' => (string) $updated->decision_status,
            'net_profit' => (float) $updated->net_profit,
            'profit_margin_percent' => (float) $updated->profit_margin_percent,
            'raw_payload' => [
                'page' => $pageData,
            ],
            'checked_at' => now(),
        ]);
    }

    protected function resultMessage(TrendyolBoosterSnapshot $snapshot): string
    {
        $delta = (float) $snapshot->price_delta;

        return match (true) {
            $delta < 0 => 'Kontrol tamamlandı. Fiyat düştü: ' . number_format(abs($delta), 2, ',', '.') . ' TL',
            $delta > 0 => 'Kontrol tamamlandı. Fiyat arttı: ' . number_format($delta, 2, ',', '.') . ' TL',
            default => 'Kontrol tamamlandı. Fiyat değişmedi.',
        };
    }

    protected function filledText(mixed $value, string $fallback): string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : $fallback;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function logSyncIssue(TrendyolBoosterProduct $tracked, string $type, string $title, string $message, array $payload = []): void
    {
        $this->activityLogger->log(
            (int) $tracked->user_id,
            $type,
            $title,
            $tracked->title ?: $tracked->source_url,
            Str::limit($message, 600, ''),
            'durum',
            null,
            $payload + [
                'source_url' => $tracked->source_url,
                'trendyol_product_id' => $tracked->trendyol_product_id,
            ],
            $tracked->id,
        );
    }

    protected function isFallbackMessage(string $message): bool
    {
        $message = Str::lower($message);

        return str_contains($message, 'erişimi sınırladı')
            || str_contains($message, 'fallback');
    }
}
