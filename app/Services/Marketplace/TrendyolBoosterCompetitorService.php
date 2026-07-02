<?php

namespace App\Services\Marketplace;

use App\Models\TrendyolBoosterCompetitor;
use App\Models\TrendyolBoosterProduct;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class TrendyolBoosterCompetitorService
{
    public function __construct(
        protected TrendyolProductPageReader $reader,
        protected TrendyolBoosterActivityLogger $activityLogger,
    ) {
    }

    /**
     * @return array{ok: bool, message: string, competitor: ?TrendyolBoosterCompetitor}
     */
    public function addFromUrl(TrendyolBoosterProduct $tracked, string $url): array
    {
        $result = $this->reader->fetch($url);

        if (! $result['ok']) {
            return [
                'ok' => false,
                'message' => $result['message'],
                'competitor' => null,
            ];
        }

        return [
            'ok' => true,
            'message' => 'Rakip radarına eklendi.',
            'competitor' => $this->persist($tracked, $result['data']),
        ];
    }

    /**
     * @return array{ok: bool, message: string, competitor: TrendyolBoosterCompetitor}
     */
    public function refresh(TrendyolBoosterCompetitor $competitor): array
    {
        $competitor->loadMissing('trackedProduct');
        $tracked = $competitor->trackedProduct;
        $result = $this->reader->fetch((string) $competitor->source_url);

        if (! $result['ok'] || ! $tracked) {
            $competitor->forceFill(['last_checked_at' => now()])->save();
            $this->logSyncIssue($competitor, 'sync_error', 'Rakip ürün kontrolü başarısız', $result['message'] ?? 'Rakip kaydı yenilenemedi.');

            return [
                'ok' => false,
                'message' => $result['message'] ?? 'Rakip kaydı yenilenemedi.',
                'competitor' => $competitor->fresh() ?: $competitor,
            ];
        }

        $updated = $this->persist($tracked, $result['data'], $competitor);

        if ($this->isFallbackMessage($result['message'])) {
            $this->logSyncIssue($updated, 'sync_fallback', 'Rakip ürün kontrolü fallback kullandı', $result['message']);
        }

        return [
            'ok' => true,
            'message' => 'Rakip radarı güncellendi.',
            'competitor' => $updated,
        ];
    }

    /**
     * @return array{processed: int, succeeded: int, failed: int, skipped: int, dry_run: bool}
     */
    public function refreshDue(int $limit = 50, ?int $userId = null, ?int $staleMinutes = null, bool $dryRun = false): array
    {
        $query = TrendyolBoosterCompetitor::query()
            ->with('trackedProduct')
            ->where('is_active', true)
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
            'skipped' => 0,
            'dry_run' => $dryRun,
        ];

        foreach ($query->get() as $competitor) {
            $summary['processed']++;

            if ($dryRun) {
                $summary['skipped']++;

                continue;
            }

            try {
                $result = $this->refresh($competitor);
            } catch (\Throwable $exception) {
                $summary['failed']++;
                $this->logSyncIssue($competitor, 'sync_error', 'Rakip ürün kontrolü hata verdi', $exception->getMessage(), [
                    'exception' => get_class($exception),
                ]);

                continue;
            }

            $summary[$result['ok'] ? 'succeeded' : 'failed']++;
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function persist(TrendyolBoosterProduct $tracked, array $data, ?TrendyolBoosterCompetitor $existing = null): TrendyolBoosterCompetitor
    {
        $sourceUrl = $this->normalizeUrl((string) ($data['source_url'] ?? $existing?->source_url ?? ''));
        $comparison = $this->comparison($tracked, $data);
        $attributes = [
            'user_id' => $tracked->user_id,
            'source_url' => $sourceUrl,
            'source_url_hash' => hash('sha256', $sourceUrl),
            'trendyol_product_id' => $data['trendyol_product_id'] ?? null,
            'title' => $this->filledText($data['title'] ?? null, (string) ($existing?->title ?? '')),
            'brand' => $this->filledText($data['brand'] ?? null, (string) ($existing?->brand ?? '')),
            'sale_price' => (float) ($data['sale_price'] ?? $existing?->sale_price ?? 0),
            'currency' => $this->filledText($data['currency'] ?? null, 'TRY'),
            'stock_status' => $this->filledText($data['stock_status'] ?? null, 'unknown'),
            'availability' => $this->filledText($data['availability'] ?? null, ''),
            'price_delta_vs_own' => $comparison['price_delta_vs_own'],
            'price_gap_percent' => $comparison['price_gap_percent'],
            'opportunity_type' => $comparison['opportunity_type'],
            'opportunity_note' => $comparison['opportunity_note'],
            'is_active' => true,
            'last_checked_at' => now(),
        ];

        if ($existing) {
            $existing->forceFill($attributes)->save();

            return $existing->fresh() ?: $existing;
        }

        return TrendyolBoosterCompetitor::query()->updateOrCreate(
            [
                'trendyol_booster_product_id' => $tracked->id,
                'source_url_hash' => $attributes['source_url_hash'],
            ],
            $attributes + [
                'trendyol_booster_product_id' => $tracked->id,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{price_delta_vs_own: float, price_gap_percent: float, opportunity_type: string, opportunity_note: string}
     */
    protected function comparison(TrendyolBoosterProduct $tracked, array $data): array
    {
        $ownPrice = (float) $tracked->sale_price;
        $competitorPrice = (float) ($data['sale_price'] ?? 0);
        $delta = $competitorPrice > 0 ? round($ownPrice - $competitorPrice, 2) : 0.0;
        $gapPercent = $competitorPrice > 0 ? round(($delta / $competitorPrice) * 100, 2) : 0.0;
        $stockStatus = (string) ($data['stock_status'] ?? 'unknown');

        if ($stockStatus === 'out_of_stock') {
            return [
                'price_delta_vs_own' => $delta,
                'price_gap_percent' => $gapPercent,
                'opportunity_type' => 'stock_gap',
                'opportunity_note' => 'Rakip stok dışı; görünürlük ve fiyat koruma fırsatı var.',
            ];
        }

        if ($competitorPrice <= 0 || $ownPrice <= 0) {
            return [
                'price_delta_vs_own' => $delta,
                'price_gap_percent' => $gapPercent,
                'opportunity_type' => 'watch',
                'opportunity_note' => 'Fiyat karşılaştırması için veri eksik.',
            ];
        }

        if ($ownPrice > $competitorPrice * 1.02) {
            return [
                'price_delta_vs_own' => $delta,
                'price_gap_percent' => $gapPercent,
                'opportunity_type' => 'price_pressure',
                'opportunity_note' => 'Kendi fiyatınız rakibin üzerinde; marj etkisiyle yeniden fiyat kontrolü önerilir.',
            ];
        }

        if ($competitorPrice > $ownPrice * 1.05) {
            return [
                'price_delta_vs_own' => $delta,
                'price_gap_percent' => $gapPercent,
                'opportunity_type' => 'pricing_power',
                'opportunity_note' => 'Rakip fiyatı daha yüksek; fiyat artırma veya reklam payı fırsatı olabilir.',
            ];
        }

        return [
            'price_delta_vs_own' => $delta,
            'price_gap_percent' => $gapPercent,
            'opportunity_type' => 'parity',
            'opportunity_note' => 'Rakip fiyatı yakın bantta; takipte kalın.',
        ];
    }

    protected function normalizeUrl(string $url): string
    {
        $url = trim($url);
        $url = preg_replace('/\s+/u', '', $url) ?: '';

        return Str::limit($url, 1000, '');
    }

    protected function filledText(mixed $value, string $fallback): string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : $fallback;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function logSyncIssue(TrendyolBoosterCompetitor $competitor, string $type, string $title, string $message, array $payload = []): void
    {
        $this->activityLogger->log(
            (int) $competitor->user_id,
            $type,
            $title,
            $competitor->title ?: $competitor->source_url,
            Str::limit($message, 600, ''),
            'durum',
            null,
            $payload + [
                'source' => 'competitor_monitor',
                'competitor_id' => $competitor->id,
                'source_url' => $competitor->source_url,
            ],
            $competitor->trendyol_booster_product_id,
        );
    }

    protected function isFallbackMessage(string $message): bool
    {
        $message = Str::lower($message);

        return str_contains($message, 'erişimi sınırladı')
            || str_contains($message, 'fallback');
    }
}
