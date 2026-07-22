<?php

namespace App\Services\Marketplace;

use App\Models\TrendyolBoosterProduct;
use App\Models\TrendyolBoosterSnapshot;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TrendyolBoosterProductAnalysisService
{
    public function __construct(
        protected TrendyolBoosterAnalysisService $analysisService,
        protected TrendyolBoosterIntelligenceService $intelligenceService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{product: TrendyolBoosterProduct, snapshot: TrendyolBoosterSnapshot, previous: ?TrendyolBoosterSnapshot, analysis: array<string, mixed>}
     */
    public function store(int $userId, array $payload, string $source = 'browser_companion'): array
    {
        return DB::transaction(function () use ($userId, $payload, $source): array {
            $sourceUrl = $this->normalizeUrl((string) ($payload['source_url'] ?? ''));
            $page = (array) ($payload['page'] ?? []);
            $metrics = (array) ($payload['metrics'] ?? []);
            $reviews = $this->normalizeReviews((array) ($payload['recent_reviews'] ?? []));
            $trendyolProductId = trim((string) ($page['trendyol_product_id'] ?? '')) ?: $this->extractProductId($sourceUrl);
            $sourceUrlHash = hash('sha256', $sourceUrl);

            $tracked = TrendyolBoosterProduct::query()
                ->where('user_id', $userId)
                ->when(
                    $trendyolProductId !== '',
                    fn ($query) => $query->where('trendyol_product_id', $trendyolProductId),
                    fn ($query) => $query->where('source_url_hash', $sourceUrlHash),
                )
                ->latest('updated_at')
                ->first();

            $previous = $tracked?->snapshots()
                ->whereNotNull('analysis_source')
                ->latest('checked_at')
                ->latest('id')
                ->first();
            $previousPrice = (float) ($previous?->sale_price ?? $tracked?->sale_price ?? 0);
            $analysisUrl = $tracked?->source_url ?: $sourceUrl;
            $storedInput = (array) data_get($tracked?->simulation_json, 'input', []);

            $tracked = $this->analysisService->store($userId, array_merge($storedInput, [
                'user_id' => $userId,
                'source_url' => $analysisUrl,
                'trendyol_product_id' => $trendyolProductId,
                'mp_product_id' => $tracked?->mp_product_id,
                'channel_listing_id' => $tracked?->channel_listing_id,
                'title' => $this->filledText($page['title'] ?? null, (string) ($tracked?->title ?? '')),
                'brand' => $this->filledText($page['brand'] ?? null, (string) ($tracked?->brand ?? '')),
                'category_name' => $this->filledText($page['category_name'] ?? null, (string) ($tracked?->category_name ?? '')),
                'sale_price' => $this->money($page['sale_price'] ?? $tracked?->sale_price ?? 0),
                'cogs' => (float) ($tracked?->cogs ?? 0),
                'packaging_cost' => (float) ($tracked?->packaging_cost ?? 0),
                'cargo_cost' => (float) ($tracked?->cargo_cost ?? 0),
                'return_cargo_cost' => (float) ($storedInput['return_cargo_cost'] ?? $tracked?->cargo_cost ?? 0),
                'commission_rate' => (float) ($tracked?->commission_rate ?? 0),
                'service_fee_rate' => (float) ($storedInput['service_fee_rate'] ?? 0),
                'advertising_rate' => (float) ($storedInput['advertising_rate'] ?? 0),
                'return_rate' => (float) ($tracked?->return_rate ?? 0),
                'vat_enabled' => (bool) ($storedInput['vat_enabled'] ?? false),
                'withholding_enabled' => (bool) ($storedInput['withholding_enabled'] ?? false),
                'vat_rate' => (float) ($tracked?->vat_rate ?? 20),
                'cost_vat_rate' => (float) ($tracked?->cost_vat_rate ?? 20),
                'expense_vat_rate' => (float) ($storedInput['expense_vat_rate'] ?? 20),
                'withholding_rate' => (float) ($storedInput['withholding_rate'] ?? 1),
                'target_margin_percent' => (float) ($storedInput['target_margin_percent'] ?? 20),
                'watch_price' => (bool) ($tracked?->watch_price ?? true),
                'watch_stock' => (bool) ($tracked?->watch_stock ?? false),
                'watch_keyword' => (bool) ($tracked?->watch_keyword ?? false),
            ]));

            $imageUrl = $this->safeUrl($page['image_url'] ?? '');
            if ($imageUrl !== '') {
                $tracked->forceFill(['image_url' => $imageUrl])->save();
            }

            $salePrice = (float) $tracked->sale_price;
            $delta = round($salePrice - $previousPrice, 2);
            $deltaPercent = $previousPrice > 0 ? round(($delta / $previousPrice) * 100, 2) : 0.0;

            $snapshot = TrendyolBoosterSnapshot::query()->create([
                'trendyol_booster_product_id' => $tracked->id,
                'user_id' => $userId,
                'sale_price' => $salePrice,
                'previous_sale_price' => $previousPrice > 0 ? $previousPrice : null,
                'price_delta' => $delta,
                'price_delta_percent' => $deltaPercent,
                'stock_status' => (string) ($page['stock_status'] ?? 'unknown'),
                'availability' => $this->filledText($page['availability'] ?? null, ''),
                'stock_quantity' => $this->nullableIntegerValue($page['total_stock'] ?? $metrics['stock_quantity'] ?? null),
                'evaluation_count' => $this->nullableInteger($metrics, 'evaluation_count'),
                'review_count' => $this->nullableInteger($metrics, 'review_count'),
                'average_rating' => $this->nullableDecimal($metrics, 'average_rating'),
                'favorite_count' => $this->nullableInteger($metrics, 'favorite_count'),
                'favorite_precision' => $this->precision($metrics['favorite_precision'] ?? $page['favorite_precision'] ?? null),
                'basket_count' => $this->nullableInteger($metrics, 'basket_count'),
                'view_count_24h' => $this->nullableInteger($metrics, 'view_count_24h'),
                'question_count' => $this->nullableIntegerValue($metrics['question_count'] ?? $page['question_count'] ?? null),
                'category_rank' => $this->nullableIntegerValue($metrics['category_rank'] ?? $page['category_rank'] ?? null),
                'seller_score' => $this->nullableDecimalValue($metrics['seller_score'] ?? $page['seller_score'] ?? null, 10),
                'seller_follower_count' => $this->nullableIntegerValue($metrics['seller_follower_count'] ?? $page['seller_follower_count'] ?? null),
                'campaign_count' => $this->nullableIntegerValue($metrics['campaign_count'] ?? $page['campaign_count'] ?? null),
                'recent_reviews' => $reviews,
                'analysis_source' => in_array($source, ['browser_companion', 'manual_refresh', 'scheduled_refresh'], true)
                    ? $source
                    : 'browser_companion',
                'data_sources' => array_values(array_unique(array_filter((array) ($page['data_sources'] ?? [$source])))),
                'opportunity_score' => (int) $tracked->opportunity_score,
                'decision_status' => (string) $tracked->decision_status,
                'net_profit' => (float) $tracked->net_profit,
                'profit_margin_percent' => (float) $tracked->profit_margin_percent,
                'raw_payload' => [
                    'page' => $page,
                    'metrics' => $metrics,
                    'review_count' => count($reviews),
                ],
                'checked_at' => now(),
            ]);

            $tracked->forceFill(['last_checked_at' => $snapshot->checked_at])->save();
            $this->intelligenceService->calculate($tracked, $snapshot);
            $snapshot->refresh();
            $tracked->refresh();

            return [
                'product' => $tracked,
                'snapshot' => $snapshot,
                'previous' => $previous,
                'analysis' => $this->present($tracked, $snapshot, $previous),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function present(
        TrendyolBoosterProduct $product,
        TrendyolBoosterSnapshot $snapshot,
        ?TrendyolBoosterSnapshot $previous = null,
    ): array {
        return [
            'tracked_product_id' => $product->id,
            'trendyol_product_id' => $product->trendyol_product_id,
            'source_url' => $product->source_url,
            'title' => $product->title,
            'brand' => $product->brand,
            'category_name' => $product->category_name,
            'image_url' => $product->image_url,
            'is_favorite' => (bool) $product->is_favorite,
            'tracking_status' => (string) ($product->tracking_status ?: 'candidate'),
            'tracking_started_at' => $product->tracking_started_at?->toIso8601String(),
            'decision' => [
                'status' => (float) $product->cogs > 0 && (float) $product->commission_rate > 0
                    ? (string) $product->decision_status
                    : 'needs_cost',
                'label' => (float) $product->cogs <= 0
                    ? 'Maliyet gerekli'
                    : ((float) $product->commission_rate <= 0 ? 'Komisyon gerekli' : match ((string) $product->decision_status) {
                        'go' => 'Satışa uygun',
                        'watch' => 'İzle ve doğrula',
                        'risk' => 'Riskli',
                        'loss' => 'Zarar riski',
                        default => 'Veri topla',
                    }),
                'score' => (int) $product->opportunity_score,
                'finance_ready' => (float) $product->cogs > 0 && (float) $product->commission_rate > 0,
                'net_profit' => (float) $product->cogs > 0 && (float) $product->commission_rate > 0
                    ? (float) $product->net_profit
                    : null,
                'profit_margin_percent' => (float) $product->cogs > 0 && (float) $product->commission_rate > 0
                    ? (float) $product->profit_margin_percent
                    : null,
                'reasons' => array_values((array) $product->decision_reasons),
            ],
            'product_scores' => [
                'interest' => (int) $product->interest_score,
                'competition' => (int) $product->competition_score,
                'risk' => (int) $product->risk_score,
                'data_quality' => (int) $product->data_quality_score,
            ],
            'current' => $this->snapshotData($snapshot),
            'previous' => $previous ? $this->snapshotData($previous) : null,
            'evidence' => $this->evidence($snapshot),
            'recent_reviews' => array_values((array) $snapshot->recent_reviews),
        ];
    }

    /**
     * Kullanıcıya gözlenen veri ile ZOLM'un hesapladığı sinyalleri açıkça ayıran
     * kanıt özeti. Tahminleri kesin veri gibi göstermemek Booster'ın temel güven
     * prensibidir.
     *
     * @return array<string, mixed>
     */
    protected function evidence(TrendyolBoosterSnapshot $snapshot): array
    {
        $observedMetricCount = collect([
            $snapshot->sale_price,
            $snapshot->stock_quantity,
            $snapshot->evaluation_count,
            $snapshot->review_count,
            $snapshot->average_rating,
            $snapshot->favorite_count,
            $snapshot->basket_count,
            $snapshot->view_count_24h,
            $snapshot->question_count,
            $snapshot->category_rank,
        ])->filter(fn ($value): bool => $value !== null)->count();

        $salesEstimate = (array) data_get($snapshot->metrics_json, 'sales_estimate', []);
        $salesStatus = (string) ($salesEstimate['status'] ?? 'unavailable');
        $confidence = max(0, min(100, (int) $snapshot->confidence_score));
        $quality = max(0, min(100, (int) $snapshot->data_quality_score));

        return [
            'source_label' => match ((string) $snapshot->analysis_source) {
                'browser_companion' => 'Tarayıcıdan canlı okundu',
                'manual_refresh' => 'Kullanıcı yenilemesi',
                'scheduled_refresh' => 'Zamanlanmış kontrol',
                default => 'Kaydedilmiş kontrol',
            },
            'source_tone' => (string) $snapshot->analysis_source === 'browser_companion' ? 'success' : 'info',
            'checked_at' => $snapshot->checked_at?->toIso8601String(),
            'data_sources' => array_values(array_filter((array) $snapshot->data_sources)),
            'observed_metric_count' => $observedMetricCount,
            'confidence_score' => $confidence,
            'confidence_label' => $confidence >= 75 ? 'Yüksek güven' : ($confidence >= 50 ? 'Yeterli güven' : 'Sınırlı güven'),
            'confidence_tone' => $confidence >= 75 ? 'success' : ($confidence >= 50 ? 'warning' : 'danger'),
            'data_quality_score' => $quality,
            'sales_status' => $salesStatus,
            'sales_label' => match ($salesStatus) {
                'observed' => 'Stok hareketinden hesaplandı',
                'proxy' => 'İlgi sinyallerinden tahmin edildi',
                default => 'Satış tahmini hazır değil',
            },
            'sales_tone' => match ($salesStatus) {
                'observed' => 'success',
                'proxy' => 'warning',
                default => 'default',
            },
            'direct_note' => 'Fiyat, görünür stok ve etkileşim metrikleri Trendyol sayfasından gözlenir.',
            'derived_note' => 'Satış hızı, stok günü, risk ve fırsat skorları ZOLM modelinin hesapladığı karar sinyalleridir; kesin sipariş verisi değildir.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshotData(TrendyolBoosterSnapshot $snapshot): array
    {
        return [
            'sale_price' => (float) $snapshot->sale_price,
            'evaluation_count' => $snapshot->evaluation_count,
            'review_count' => $snapshot->review_count,
            'average_rating' => $snapshot->average_rating !== null ? (float) $snapshot->average_rating : null,
            'favorite_count' => $snapshot->favorite_count,
            'basket_count' => $snapshot->basket_count,
            'view_count_24h' => $snapshot->view_count_24h,
            'stock_quantity' => $snapshot->stock_quantity,
            'question_count' => $snapshot->question_count,
            'category_rank' => $snapshot->category_rank,
            'seller_score' => $snapshot->seller_score !== null ? (float) $snapshot->seller_score : null,
            'seller_follower_count' => $snapshot->seller_follower_count,
            'campaign_count' => $snapshot->campaign_count,
            'favorite_precision' => $snapshot->favorite_precision,
            'estimated_hourly_sales' => $snapshot->estimated_hourly_sales !== null ? (float) $snapshot->estimated_hourly_sales : null,
            'estimated_daily_sales' => $snapshot->estimated_daily_sales !== null ? (float) $snapshot->estimated_daily_sales : null,
            'estimated_days_of_stock' => $snapshot->estimated_days_of_stock !== null ? (float) $snapshot->estimated_days_of_stock : null,
            'estimated_daily_revenue' => $snapshot->estimated_daily_revenue !== null ? (float) $snapshot->estimated_daily_revenue : null,
            'estimated_conversion_rate' => $snapshot->estimated_conversion_rate !== null ? (float) $snapshot->estimated_conversion_rate : null,
            'sentiment_score' => $snapshot->sentiment_score !== null ? (float) $snapshot->sentiment_score : null,
            'positive_topics' => (array) $snapshot->positive_topics,
            'negative_topics' => (array) $snapshot->negative_topics,
            'interest_score' => (int) $snapshot->interest_score,
            'competition_score' => (int) $snapshot->competition_score,
            'risk_score' => (int) $snapshot->risk_score,
            'confidence_score' => (int) $snapshot->confidence_score,
            'data_quality_score' => (int) $snapshot->data_quality_score,
            'sales_estimate' => (array) data_get($snapshot->metrics_json, 'sales_estimate', []),
            'metrics' => (array) $snapshot->metrics_json,
            'analysis_source' => (string) $snapshot->analysis_source,
            'data_sources' => (array) $snapshot->data_sources,
            'checked_at' => $snapshot->checked_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<int, mixed>  $reviews
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeReviews(array $reviews): array
    {
        return collect($reviews)
            ->filter(fn ($review) => is_array($review) && trim((string) ($review['comment'] ?? '')) !== '')
            ->take(10)
            ->map(function (array $review): array {
                return [
                    'review_id' => Str::limit(trim((string) ($review['review_id'] ?? '')), 80, ''),
                    'user_name' => Str::limit(trim((string) ($review['user_name'] ?? 'Anonim')), 180, ''),
                    'rate' => max(0, min(5, (int) ($review['rate'] ?? 0))),
                    'comment' => Str::limit(trim((string) ($review['comment'] ?? '')), 2000, ''),
                    'seller_name' => Str::limit(trim((string) ($review['seller_name'] ?? '')), 180, ''),
                    'reviewed_at' => $this->normalizeDate($review['reviewed_at'] ?? null),
                ];
            })
            ->values()
            ->all();
    }

    protected function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                $timestamp = (float) $value;
                $timestamp = $timestamp > 9999999999 ? $timestamp / 1000 : $timestamp;

                return Carbon::createFromTimestamp($timestamp)->toIso8601String();
            }

            return Carbon::parse((string) $value)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function nullableInteger(array $metrics, string $key): ?int
    {
        if (! array_key_exists($key, $metrics) || $metrics[$key] === null || $metrics[$key] === '') {
            return null;
        }

        return max(0, (int) $metrics[$key]);
    }

    protected function nullableDecimal(array $metrics, string $key): ?float
    {
        if (! array_key_exists($key, $metrics) || $metrics[$key] === null || $metrics[$key] === '') {
            return null;
        }

        return round(max(0, (float) $metrics[$key]), 2);
    }

    protected function nullableIntegerValue(mixed $value): ?int
    {
        return is_numeric($value) ? max(0, (int) $value) : null;
    }

    protected function nullableDecimalValue(mixed $value, float $maximum): ?float
    {
        return is_numeric($value) ? round(max(0, min($maximum, (float) $value)), 2) : null;
    }

    protected function precision(mixed $value): ?string
    {
        $precision = Str::lower(trim((string) ($value ?? '')));

        return in_array($precision, ['exact', 'rounded', 'unknown'], true) ? $precision : null;
    }

    protected function money(mixed $value): float
    {
        return round(max(0, (float) ($value ?? 0)), 2);
    }

    protected function filledText(mixed $value, string $fallback): string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : $fallback;
    }

    protected function normalizeUrl(string $url): string
    {
        return Str::limit(trim(preg_replace('/\s+/u', '', $url) ?: ''), 1000, '');
    }

    protected function safeUrl(mixed $value): string
    {
        $url = trim((string) $value);

        if ($url === '' || ! Str::startsWith($url, ['http://', 'https://'])) {
            return '';
        }

        return Str::limit($url, 1000, '');
    }

    protected function extractProductId(string $url): string
    {
        return preg_match('/-p-(\d+)/iu', $url, $matches) ? $matches[1] : '';
    }
}
