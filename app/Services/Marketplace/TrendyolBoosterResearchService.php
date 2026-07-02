<?php

namespace App\Services\Marketplace;

use App\Models\TrendyolBoosterProduct;
use Illuminate\Support\Collection;

class TrendyolBoosterResearchService
{
    public function __construct(
        protected TrendyolProductPageReader $reader,
        protected TrendyolBoosterProductAnalysisService $analysisService,
    ) {
    }

    /**
     * @param  array<int, string>  $urls
     * @return array{products: array<int, array<string, mixed>>, summary: array<string, mixed>, group_key: string}
     */
    public function compareUrls(array $urls): array
    {
        $payloads = collect($urls)
            ->map(fn (string $url): array => $this->payloadFromUrl($url))
            ->all();

        return $this->comparePayloads($payloads);
    }

    /**
     * @param  array<int, array<string, mixed>>  $payloads
     * @return array{products: array<int, array<string, mixed>>, summary: array<string, mixed>, group_key: string}
     */
    public function comparePayloads(array $payloads): array
    {
        $products = collect($payloads)
            ->filter(fn ($payload): bool => is_array($payload))
            ->map(fn (array $payload): array => $this->normalizePayload($payload))
            ->filter(fn (array $payload): bool => trim((string) data_get($payload, 'page.trendyol_product_id', '')) !== '')
            ->unique(fn (array $payload): string => (string) data_get($payload, 'page.trendyol_product_id'))
            ->values();

        if ($products->count() < 2) {
            throw new \InvalidArgumentException('Karşılaştırma için en az iki geçerli Trendyol ürünü gerekir.');
        }

        $prices = $products->pluck('page.sale_price')->map(fn ($value): float => (float) $value)->filter(fn (float $value): bool => $value > 0);
        $favorites = $products->pluck('metrics.favorite_count')->filter(fn ($value): bool => is_numeric($value))->map(fn ($value): int => (int) $value);
        $evaluations = $products->pluck('metrics.evaluation_count')->filter(fn ($value): bool => is_numeric($value))->map(fn ($value): int => (int) $value);
        $minimumPrice = (float) ($prices->min() ?? 0);
        $maximumFavorite = (int) ($favorites->max() ?? 0);
        $maximumEvaluation = (int) ($evaluations->max() ?? 0);

        $ranked = $products->map(function (array $payload) use ($minimumPrice, $maximumFavorite, $maximumEvaluation): array {
            $price = (float) data_get($payload, 'page.sale_price', 0);
            $rating = (float) data_get($payload, 'metrics.average_rating', 0);
            $favorite = (int) data_get($payload, 'metrics.favorite_count', 0);
            $evaluation = (int) data_get($payload, 'metrics.evaluation_count', 0);
            $sellerScore = (float) data_get($payload, 'metrics.seller_score', 0);
            $stock = data_get($payload, 'page.total_stock');
            $score = 0.0;
            $score += $price > 0 && $minimumPrice > 0 ? ($minimumPrice / $price) * 30 : 0;
            $score += ($rating / 5) * 20;
            $score += $maximumFavorite > 0 ? ($favorite / $maximumFavorite) * 20 : 0;
            $score += $maximumEvaluation > 0 ? ($evaluation / $maximumEvaluation) * 15 : 0;
            $score += ($sellerScore / 10) * 10;
            $score += is_numeric($stock) && (int) $stock > 0 ? 5 : 0;
            $payload['comparison_score'] = (int) round(max(0, min(100, $score)));
            $payload['price_index'] = $minimumPrice > 0 && $price > 0 ? round(($price / $minimumPrice) * 100, 1) : null;

            return $payload;
        })->sortByDesc('comparison_score')->values();

        return [
            'products' => $ranked->all(),
            'summary' => $this->marketSummary($ranked),
            'group_key' => $this->groupKey($ranked),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{product: TrendyolBoosterProduct, analysis: array<string, mixed>}
     */
    public function track(int $userId, array $payload, string $source, ?string $groupKey = null): array
    {
        $normalized = $this->normalizePayload($payload);
        $result = $this->analysisService->store($userId, $normalized, 'manual_refresh');
        $product = $result['product'];
        $sources = collect((array) $product->tracking_sources)
            ->push($source)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $product->forceFill([
            'tracking_status' => 'active',
            'tracking_sources' => $sources,
            'tracking_group_key' => $groupKey ?: $product->tracking_group_key,
            'tracking_started_at' => $product->tracking_started_at ?: now(),
            'tracking_paused_at' => null,
            'watch_stock' => true,
            'analysis_auto_refresh_enabled' => true,
            'analysis_refresh_interval_minutes' => $this->trackingIntervalMinutes(),
            'next_analysis_refresh_at' => now(),
        ])->save();

        return ['product' => $product->fresh() ?: $product, 'analysis' => $result['analysis']];
    }

    /** @return array<string, mixed> */
    protected function payloadFromUrl(string $url): array
    {
        $result = $this->reader->fetch(trim($url));
        if (! $result['ok']) {
            throw new \RuntimeException($result['message']);
        }

        $page = (array) $result['data'];

        return [
            'source_url' => (string) ($page['source_url'] ?? $url),
            'page' => $page,
            'metrics' => $this->metricsFromPage($page),
            'recent_reviews' => [],
        ];
    }

    /** @param array<string, mixed> $payload */
    protected function normalizePayload(array $payload): array
    {
        $page = (array) ($payload['page'] ?? $payload);
        $metrics = array_merge($this->metricsFromPage($page), (array) ($payload['metrics'] ?? []));

        return [
            'source_url' => (string) ($payload['source_url'] ?? $page['source_url'] ?? ''),
            'page' => $page,
            'metrics' => $metrics,
            'recent_reviews' => array_values((array) ($payload['recent_reviews'] ?? [])),
        ];
    }

    /** @param array<string, mixed> $page */
    protected function metricsFromPage(array $page): array
    {
        return collect([
            'evaluation_count',
            'review_count',
            'average_rating',
            'favorite_count',
            'favorite_precision',
            'basket_count',
            'view_count_24h',
            'question_count',
            'category_rank',
            'seller_score',
            'seller_follower_count',
            'campaign_count',
        ])->mapWithKeys(fn (string $key): array => [$key => $page[$key] ?? null])->all();
    }

    /**
     * @param Collection<int, array<string, mixed>> $products
     * @return array<string, mixed>
     */
    protected function marketSummary(Collection $products): array
    {
        $prices = $products->pluck('page.sale_price')->map(fn ($value): float => (float) $value)->filter(fn (float $value): bool => $value > 0);
        $ratings = $products->pluck('metrics.average_rating')->filter(fn ($value): bool => is_numeric($value));
        $favorites = $products->pluck('metrics.favorite_count')->filter(fn ($value): bool => is_numeric($value));
        $totalFavorites = (int) $favorites->sum();
        $topFavoriteShare = $totalFavorites > 0 ? round(((int) $favorites->max() / $totalFavorites) * 100, 1) : null;

        return [
            'product_count' => $products->count(),
            'minimum_price' => $prices->isNotEmpty() ? round((float) $prices->min(), 2) : null,
            'maximum_price' => $prices->isNotEmpty() ? round((float) $prices->max(), 2) : null,
            'average_price' => $prices->isNotEmpty() ? round((float) $prices->avg(), 2) : null,
            'average_rating' => $ratings->isNotEmpty() ? round((float) $ratings->avg(), 2) : null,
            'total_favorites' => $totalFavorites ?: null,
            'top_favorite_share' => $topFavoriteShare,
            'price_spread_percent' => $prices->count() > 1 && (float) $prices->min() > 0
                ? round((((float) $prices->max() - (float) $prices->min()) / (float) $prices->min()) * 100, 1)
                : null,
            'leader_product_id' => data_get($products->first(), 'page.trendyol_product_id'),
        ];
    }

    /** @param Collection<int, array<string, mixed>> $products */
    protected function groupKey(Collection $products): string
    {
        $ids = $products->pluck('page.trendyol_product_id')->filter()->sort()->implode('|');

        return substr(hash('sha256', $ids), 0, 40);
    }

    protected function trackingIntervalMinutes(): int
    {
        return max(60, min(1440, (int) config('marketplace.trendyol_booster.tracking_interval_minutes', 60)));
    }
}
