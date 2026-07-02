<?php

namespace App\Services\Marketplace;

use App\Models\TrendyolBoosterProduct;
use App\Models\TrendyolBoosterSnapshot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class TrendyolBoosterScheduledAnalysisService
{
    public function __construct(
        protected TrendyolProductPageReader $reader,
        protected TrendyolBoosterProductAnalysisService $analysisService,
    ) {
    }

    /**
     * @return array{ok: bool, message: string, product: TrendyolBoosterProduct, snapshot: TrendyolBoosterSnapshot, analysis: array<string, mixed>}
     */
    public function refresh(TrendyolBoosterProduct $tracked, string $source = 'manual_refresh'): array
    {
        $tracked->loadMissing(['analysisSnapshots' => fn ($query) => $query->latest('checked_at')->limit(1)]);
        $productId = trim((string) $tracked->trendyol_product_id);

        if ($productId === '') {
            throw new RuntimeException('Trendyol ürün ID bulunamadığı için analiz yenilenemedi.');
        }

        try {
            $pageResult = $this->reader->fetch((string) $tracked->source_url);
            $pageData = (array) ($pageResult['data'] ?? []);
            $latest = $tracked->analysisSnapshots->first();
            $fetchErrors = [];

            try {
                $socialMetrics = $this->fetchSocialProof($productId);
            } catch (\Throwable $exception) {
                $socialMetrics = [];
                $fetchErrors[] = $exception->getMessage();
            }

            try {
                $reviewData = $this->fetchReviews($productId);
            } catch (\Throwable $exception) {
                $reviewData = ['metrics' => [], 'reviews' => []];
                $fetchErrors[] = $exception->getMessage();
            }

            if (count($fetchErrors) === 2 && ! $latest && ! $this->hasPageMetrics($pageData)) {
                throw new RuntimeException(implode(' ', $fetchErrors));
            }

            $metrics = $this->mergeMetrics($latest, $pageData, $reviewData['metrics'], $socialMetrics);
            $reviews = $reviewData['reviews'] !== []
                ? $reviewData['reviews']
                : array_values((array) $latest?->recent_reviews);

            $result = $this->analysisService->store((int) $tracked->user_id, [
                'source_url' => (string) $tracked->source_url,
                'page' => [
                    'trendyol_product_id' => $productId,
                    'title' => $this->filledText($pageData['title'] ?? null, (string) $tracked->title),
                    'brand' => $this->filledText($pageData['brand'] ?? null, (string) $tracked->brand),
                    'category_name' => $this->filledText($pageData['category_name'] ?? null, (string) $tracked->category_name),
                    'sale_price' => (float) ($pageData['sale_price'] ?? 0) > 0 ? (float) $pageData['sale_price'] : (float) $tracked->sale_price,
                    'currency' => (string) ($pageData['currency'] ?? $tracked->currency ?? 'TRY'),
                    'image_url' => $this->filledText($pageData['image_url'] ?? null, (string) $tracked->image_url),
                    'availability' => (string) ($pageData['availability'] ?? ''),
                    'stock_status' => (string) ($pageData['stock_status'] ?? 'unknown'),
                    'total_stock' => $pageData['total_stock'] ?? null,
                    'sellers' => $pageData['sellers'] ?? [],
                    'question_count' => $pageData['question_count'] ?? null,
                    'category_rank' => $pageData['category_rank'] ?? null,
                    'seller_score' => $pageData['seller_score'] ?? null,
                    'seller_id' => $pageData['seller_id'] ?? null,
                    'seller_follower_count' => $pageData['seller_follower_count'] ?? null,
                    'campaign_count' => $pageData['campaign_count'] ?? null,
                    'campaign_signature' => $pageData['campaign_signature'] ?? null,
                    'promotions' => $pageData['promotions'] ?? [],
                    'favorite_precision' => $pageData['favorite_precision'] ?? null,
                    'data_sources' => $pageData['data_sources'] ?? ['scheduled_page_reader'],
                ],
                'metrics' => $metrics,
                'recent_reviews' => $reviews,
            ], $source);

            $product = $result['product'];
            $product->forceFill([
                'last_analysis_refresh_at' => now(),
                'last_analysis_refresh_status' => $fetchErrors === [] ? 'success' : 'partial',
                'last_analysis_refresh_error' => $fetchErrors === [] ? null : Str::limit(implode(' ', $fetchErrors), 1000, ''),
                'next_analysis_refresh_at' => $product->analysis_auto_refresh_enabled
                    ? now()->addMinutes($this->interval($product))
                    : null,
            ])->save();

            return [
                'ok' => true,
                'message' => $fetchErrors === []
                    ? 'Anlık analiz yenilendi ve önceki kayıtla karşılaştırıldı.'
                    : 'Analiz kısmen yenilendi; alınamayan metriklerde son güvenilir değerler korundu.',
                'product' => $product->fresh() ?: $product,
                'snapshot' => $result['snapshot'],
                'analysis' => $result['analysis'],
            ];
        } catch (\Throwable $exception) {
            $tracked->forceFill([
                'last_analysis_refresh_at' => now(),
                'last_analysis_refresh_status' => 'error',
                'last_analysis_refresh_error' => Str::limit($exception->getMessage(), 1000, ''),
                'next_analysis_refresh_at' => $tracked->analysis_auto_refresh_enabled
                    ? now()->addMinutes($this->interval($tracked))
                    : null,
            ])->save();

            throw $exception;
        }
    }

    /**
     * @return array{processed: int, succeeded: int, failed: int, skipped: int, snapshots: int, dry_run: bool}
     */
    public function refreshDue(int $limit = 25, ?int $userId = null, bool $dryRun = false): array
    {
        $query = TrendyolBoosterProduct::query()
            ->where('analysis_auto_refresh_enabled', true)
            ->when(
                \Illuminate\Support\Facades\Schema::hasColumn('trendyol_booster_products', 'tracking_status'),
                fn (Builder $query) => $query->where('tracking_status', 'active'),
            )
            ->where(function (Builder $query): void {
                $query->whereNull('next_analysis_refresh_at')
                    ->orWhere('next_analysis_refresh_at', '<=', now());
            })
            ->when($userId !== null, fn (Builder $query) => $query->where('user_id', $userId))
            ->orderBy('next_analysis_refresh_at')
            ->orderBy('id')
            ->limit(max(1, min(100, $limit)));

        $summary = [
            'processed' => 0,
            'succeeded' => 0,
            'failed' => 0,
            'skipped' => 0,
            'snapshots' => 0,
            'dry_run' => $dryRun,
        ];

        foreach ($query->get() as $tracked) {
            $summary['processed']++;

            if ($dryRun) {
                $summary['skipped']++;
                continue;
            }

            try {
                $this->refresh($tracked, 'scheduled_refresh');
                $summary['succeeded']++;
                $summary['snapshots']++;
            } catch (\Throwable) {
                $summary['failed']++;
            }
        }

        return $summary;
    }

    /**
     * @return array<string, int|string|null>
     */
    protected function fetchSocialProof(string $productId): array
    {
        $response = $this->request()->get(
            'https://apigw.trendyol.com/discovery-storefront-trproductgw-service/api/social-proof/',
            ['contentIds' => $productId, 'channelId' => 1]
        );

        if (! $response->successful()) {
            throw new RuntimeException('Trendyol sosyal kanıt servisi yanıt vermedi.');
        }

        $rows = (array) data_get($response->json(), $productId . '.socialProofs', []);
        $values = collect($rows)->mapWithKeys(fn ($row) => [
            (string) ($row['id'] ?? '') => $this->compactCount($row['count'] ?? null),
        ]);

        return [
            'favorite_count' => $values->get('favorite-count'),
            'favorite_precision' => collect($rows)->contains(fn ($row): bool => (string) ($row['id'] ?? '') === 'favorite-count') ? 'rounded' : null,
            'basket_count' => $values->get('basket-count'),
            'view_count_24h' => $values->get('page-view-count'),
        ];
    }

    /**
     * @return array{metrics: array<string, int|float|null>, reviews: array<int, array<string, mixed>>}
     */
    protected function fetchReviews(string $productId): array
    {
        $response = $this->request()->get(
            'https://apigw.trendyol.com/discovery-storefront-trproductgw-service/api/review-read/product-reviews/detailed',
            [
                'contentId' => $productId,
                'page' => 0,
                'pageSize' => 10,
                'order' => 'DESC',
                'orderBy' => 'LastModifiedDate',
                'channelId' => 1,
            ]
        );

        if (! $response->successful()) {
            throw new RuntimeException('Trendyol yorum servisi yanıt vermedi.');
        }

        $json = (array) $response->json();
        $result = (array) ($json['result'] ?? $json);
        $summary = (array) ($result['summary'] ?? []);
        $reviews = collect((array) ($result['reviews'] ?? []))
            ->take(10)
            ->map(fn (array $review): array => [
                'review_id' => (string) ($review['id'] ?? ''),
                'user_name' => (string) ($review['userFullName'] ?? 'Anonim'),
                'rate' => max(0, min(5, (int) ($review['rate'] ?? 0))),
                'comment' => (string) ($review['comment'] ?? ''),
                'seller_name' => (string) data_get($review, 'seller.name', ''),
                'reviewed_at' => $review['lastModifiedAt'] ?? $review['lastModifiedDate'] ?? null,
            ])
            ->filter(fn (array $review): bool => trim($review['comment']) !== '')
            ->values()
            ->all();

        return [
            'metrics' => [
                'evaluation_count' => $this->nullableInteger($summary['totalRatingCount'] ?? null),
                'review_count' => $this->nullableInteger($summary['totalCommentCount'] ?? null),
                'average_rating' => is_numeric($summary['averageRating'] ?? null) ? (float) $summary['averageRating'] : null,
            ],
            'reviews' => $reviews,
        ];
    }

    /**
     * @param  array<string, mixed>  $page
     * @param  array<string, mixed>  $review
     * @param  array<string, mixed>  $social
     * @return array<string, int|float|null>
     */
    protected function mergeMetrics(?TrendyolBoosterSnapshot $latest, array $page, array $review, array $social): array
    {
        return [
            'evaluation_count' => $review['evaluation_count'] ?? $page['evaluation_count'] ?? $latest?->evaluation_count,
            'review_count' => $review['review_count'] ?? $page['review_count'] ?? $latest?->review_count,
            'average_rating' => $review['average_rating'] ?? $page['average_rating'] ?? $latest?->average_rating,
            'favorite_count' => $page['favorite_count'] ?? $social['favorite_count'] ?? $latest?->favorite_count,
            'favorite_precision' => $page['favorite_precision'] ?? $social['favorite_precision'] ?? $latest?->favorite_precision,
            'basket_count' => $social['basket_count'] ?? $latest?->basket_count,
            'view_count_24h' => $social['view_count_24h'] ?? $latest?->view_count_24h,
            'stock_quantity' => $page['total_stock'] ?? $latest?->stock_quantity,
            'question_count' => $page['question_count'] ?? $latest?->question_count,
            'category_rank' => $page['category_rank'] ?? $latest?->category_rank,
            'seller_score' => $page['seller_score'] ?? $latest?->seller_score,
            'seller_follower_count' => $page['seller_follower_count'] ?? $latest?->seller_follower_count,
            'campaign_count' => $page['campaign_count'] ?? $latest?->campaign_count,
        ];
    }

    protected function request()
    {
        return Http::withHeaders([
            'Accept' => 'application/json',
            'User-Agent' => 'Mozilla/5.0 (compatible; ZOLM-Trendyol-Booster/1.0)',
            'Accept-Language' => 'tr-TR,tr;q=0.9',
        ])->timeout((int) config('marketplace.trendyol_booster.request_timeout', 12))
            ->retry(
                (int) config('marketplace.trendyol_booster.request_retries', 1),
                (int) config('marketplace.trendyol_booster.request_retry_delay_ms', 250)
            );
    }

    protected function compactCount(mixed $value): ?int
    {
        $text = Str::lower(trim((string) ($value ?? '')));

        if ($text === '') {
            return null;
        }

        $multiplier = preg_match('/(?:b|bin)$/iu', $text) ? 1000 : 1;
        $numeric = preg_replace('/[^0-9,.-]/u', '', str_replace('.', '', preg_replace('/(?:b|bin)$/iu', '', $text) ?: '')) ?: '';
        $number = (float) str_replace(',', '.', $numeric);

        return $number >= 0 ? (int) round($number * $multiplier) : null;
    }

    protected function nullableInteger(mixed $value): ?int
    {
        return is_numeric($value) ? max(0, (int) $value) : null;
    }

    protected function interval(TrendyolBoosterProduct $product): int
    {
        return max(60, min(10080, (int) ($product->analysis_refresh_interval_minutes ?: 1440)));
    }

    /**
     * @param  array<string, mixed>  $page
     */
    protected function hasPageMetrics(array $page): bool
    {
        return collect(['evaluation_count', 'review_count', 'average_rating', 'favorite_count'])
            ->contains(fn (string $key): bool => isset($page[$key]) && is_numeric($page[$key]));
    }

    protected function filledText(mixed $value, string $fallback): string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : $fallback;
    }
}
