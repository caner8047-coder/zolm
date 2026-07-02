<?php

namespace App\Services\Marketplace;

use App\Models\TrendyolBoosterProduct;
use App\Models\TrendyolBoosterSnapshot;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TrendyolBoosterIntelligenceService
{
    /** @var array<string, array<int, string>> */
    protected array $positiveTopics = [
        'kalite' => ['kaliteli', 'kalitesi', 'sağlam', 'dayanıklı'],
        'fiyat/performans' => ['fiyat performans', 'fiyatına göre', 'uygun fiyat'],
        'tasarım' => ['şık', 'güzel', 'zarif', 'tatlı', 'dekoratif'],
        'teslimat' => ['hızlı teslim', 'erken geldi', 'hızlı kargo'],
        'paketleme' => ['iyi paket', 'özenli paket', 'sağlam paket'],
        'memnuniyet' => ['memnunum', 'beğendim', 'tavsiye ederim'],
    ];

    /** @var array<string, array<int, string>> */
    protected array $negativeTopics = [
        'kalite' => ['kalitesiz', 'dandik', 'kırıldı', 'söküldü', 'sağlam değil'],
        'ölçü' => ['küçük geldi', 'beklediğimden küçük', 'ölçü yanlış'],
        'renk' => ['renk farklı', 'rengi farklı', 'görseldeki gibi değil'],
        'teslimat' => ['geç geldi', 'gecikti', 'kargo kötü'],
        'paketleme' => ['kötü paket', 'hasarlı geldi', 'ezik geldi'],
        'iade' => ['iade ettim', 'geri gönderdim'],
    ];

    /**
     * Yeni snapshot'ı geçmiş ve rakip sinyalleriyle zenginleştirir.
     *
     * @return array<string, mixed>
     */
    public function calculate(TrendyolBoosterProduct $product, TrendyolBoosterSnapshot $current): array
    {
        $previous = $product->snapshots()
            ->whereKeyNot($current->getKey())
            ->latest('checked_at')
            ->latest('id')
            ->first();

        $history = $product->snapshots()
            ->whereKeyNot($current->getKey())
            ->latest('checked_at')
            ->latest('id')
            ->limit(200)
            ->get();

        $elapsedHours = $previous?->checked_at
            ? max(0.25, $previous->checked_at->floatDiffInHours($current->checked_at ?: now()))
            : null;

        $favoriteComparable = $this->isComparablePrecision($current, $previous);
        $deltas = [
            'price' => $this->delta($current->sale_price, $previous?->sale_price),
            'stock' => $this->delta($current->stock_quantity, $previous?->stock_quantity),
            'favorite' => $favoriteComparable ? $this->delta($current->favorite_count, $previous?->favorite_count) : null,
            'evaluation' => $this->delta($current->evaluation_count, $previous?->evaluation_count),
            'review' => $this->delta($current->review_count, $previous?->review_count),
            'question' => $this->delta($current->question_count, $previous?->question_count),
            'category_rank' => $this->delta($current->category_rank, $previous?->category_rank),
            'seller_score' => $this->delta($current->seller_score, $previous?->seller_score),
        ];

        $stockVelocity = $this->stockVelocity24Hours($current, $history);
        $engagementVelocity = $this->engagementVelocity24Hours($current, $history);
        $salesEstimate = $this->estimateSales(
            $stockVelocity,
            $engagementVelocity,
            $current,
        );
        $hourlySales = $salesEstimate['hourly'];
        $dailySales = $salesEstimate['daily'];
        $salesConfidence = $salesEstimate['confidence'];
        $salesMethod = $salesEstimate['method'];
        $dailyRevenue = $dailySales !== null ? round($dailySales * (float) $current->sale_price, 2) : null;
        $daysOfStock = $dailySales !== null && $dailySales > 0 && $current->stock_quantity !== null
            ? round($current->stock_quantity / $dailySales, 2)
            : null;
        $conversion = $dailySales !== null && $current->view_count_24h !== null && $current->view_count_24h > 0
            ? round(min(100, ($dailySales / $current->view_count_24h) * 100), 2)
            : null;

        $sentiment = $this->sentiment((array) $current->recent_reviews);
        $interestScore = $this->interestScore($current, $deltas, $elapsedHours, $sentiment['score']);
        $competitionScore = $this->competitionScore($product, (float) $current->sale_price);
        $riskScore = $this->riskScore($current, $history, $sentiment['score'], $competitionScore, $daysOfStock);
        $dataQuality = $this->dataQualityScore($current);
        $confidence = min(100, (int) round(($dataQuality * 0.65) + ($salesConfidence * 0.35)));
        $campaignPerformance = $this->campaignPerformance($current, $previous, $dailySales);

        $metrics = [
            'version' => 3,
            'elapsed_hours' => $elapsedHours !== null ? round($elapsedHours, 2) : null,
            'deltas' => $deltas,
            'growth_per_day' => $this->growthPerDay($deltas, $elapsedHours),
            'sales_method' => $salesMethod,
            'sales_estimate' => $salesEstimate,
            'stock_velocity_24h' => $stockVelocity,
            'engagement_velocity_24h' => $engagementVelocity,
            'favorite_comparable' => $favoriteComparable,
            'campaign_performance' => $campaignPerformance,
            'price_statistics' => $this->priceStatistics($current, $history),
            'generated_at' => now()->toIso8601String(),
        ];

        $current->forceFill([
            'data_quality_score' => $dataQuality,
            'confidence_score' => $confidence,
            'estimated_hourly_sales' => $hourlySales,
            'estimated_daily_sales' => $dailySales,
            'estimated_days_of_stock' => $daysOfStock,
            'estimated_daily_revenue' => $dailyRevenue,
            'estimated_conversion_rate' => $conversion,
            'sentiment_score' => $sentiment['score'],
            'positive_topics' => $sentiment['positive_topics'],
            'negative_topics' => $sentiment['negative_topics'],
            'interest_score' => $interestScore,
            'competition_score' => $competitionScore,
            'risk_score' => $riskScore,
            'metrics_json' => $metrics,
        ])->save();

        $product->forceFill([
            'data_quality_score' => $dataQuality,
            'interest_score' => $interestScore,
            'competition_score' => $competitionScore,
            'risk_score' => $riskScore,
            'estimated_daily_sales' => $dailySales,
            'estimated_daily_revenue' => $dailyRevenue,
            'metrics_calculated_at' => now(),
        ])->save();

        return array_merge($metrics, [
            'data_quality_score' => $dataQuality,
            'confidence_score' => $confidence,
            'estimated_hourly_sales' => $hourlySales,
            'estimated_daily_sales' => $dailySales,
            'estimated_days_of_stock' => $daysOfStock,
            'estimated_daily_revenue' => $dailyRevenue,
            'estimated_conversion_rate' => $conversion,
            'sentiment_score' => $sentiment['score'],
            'positive_topics' => $sentiment['positive_topics'],
            'negative_topics' => $sentiment['negative_topics'],
            'interest_score' => $interestScore,
            'competition_score' => $competitionScore,
            'risk_score' => $riskScore,
        ]);
    }

    /**
     * Stok hareketini birincil, 24 saatlik ilgi hareketini ikincil sinyal olarak kullanır.
     * Aralık kesin bir istatistiksel güven aralığı değil; veri kapsamına göre karar bandıdır.
     *
     * @param  array<string, mixed>  $stockVelocity
     * @param  array<string, mixed>  $engagementVelocity
     * @return array{hourly: ?float, daily: ?float, low: ?float, high: ?float, confidence: int, method: string, status: string, signal_count: int}
     */
    protected function estimateSales(
        array $stockVelocity,
        array $engagementVelocity,
        TrendyolBoosterSnapshot $current,
    ): array {
        $observedHours = $stockVelocity['observed_hours'] ?? null;
        $observedDrop = $stockVelocity['observed_drop_units'] ?? null;
        if (is_numeric($observedHours) && (float) $observedHours >= 0.5 && is_numeric($observedDrop)) {
            $hourly = max(0, (float) $observedDrop) / (float) $observedHours;
            $daily = round($hourly * 24, 2);
            $coverage = (float) ($stockVelocity['coverage_percent'] ?? 0);
            $samples = (int) ($stockVelocity['sample_count'] ?? 0);
            $sampleScore = min(100, ($samples / 24) * 100);
            $restocked = (int) ($stockVelocity['restocked_units'] ?? 0);
            $restockPenalty = $restocked > 0 ? min(20, 8 + log10(1 + $restocked) * 4) : 0;
            $hasObservedSales = (float) $observedDrop > 0;
            $confidence = $hasObservedSales
                ? (int) round(min(95, max(20, 35 + ($coverage * 0.4) + ($sampleScore * 0.2) - $restockPenalty)))
                : (int) round(min(80, max(15, 15 + ($coverage * 0.5) + ($sampleScore * 0.15) - $restockPenalty)));

            if ($hasObservedSales) {
                $uncertainty = max(0.20, 0.75 - (($coverage / 100) * 0.55));
                if ($restocked > 0) {
                    $uncertainty = min(0.90, $uncertainty + 0.15);
                }

                return [
                    'hourly' => round($hourly, 3),
                    'daily' => $daily,
                    'low' => round(max(0, $daily * (1 - $uncertainty)), 2),
                    'high' => round($daily * (1 + $uncertainty), 2),
                    'confidence' => $confidence,
                    'method' => 'stock_velocity_24h',
                    'status' => $restocked > 0 ? 'observed_with_restock' : 'observed',
                    'signal_count' => 1,
                ];
            }

            return [
                'hourly' => 0.0,
                'daily' => 0.0,
                'low' => 0.0,
                'high' => round(24 / max(0.5, (float) $observedHours), 2),
                'confidence' => $confidence,
                'method' => 'stock_velocity_24h',
                'status' => $restocked > 0 ? 'no_movement_after_restock' : 'no_movement',
                'signal_count' => 1,
            ];
        }

        $signals = [];
        $engagementHours = $engagementVelocity['observed_hours'] ?? null;
        if (is_numeric($engagementHours) && (float) $engagementHours >= 0.5) {
            if (($engagementVelocity['evaluation_delta'] ?? 0) > 0) {
                $signals[] = ((float) $engagementVelocity['evaluation_delta'] * 5) * (24 / (float) $engagementHours);
            }
            if (($engagementVelocity['review_delta'] ?? 0) > 0) {
                $signals[] = ((float) $engagementVelocity['review_delta'] * 10) * (24 / (float) $engagementHours);
            }
            if (($engagementVelocity['favorite_delta'] ?? 0) > 0) {
                $signals[] = ((float) $engagementVelocity['favorite_delta'] * 0.03) * (24 / (float) $engagementHours);
            }
        }

        if ($signals !== []) {
            $daily = array_sum($signals) / count($signals);
            $coverage = (float) ($engagementVelocity['coverage_percent'] ?? 0);
            $samples = (int) ($engagementVelocity['sample_count'] ?? 0);
            $confidence = (int) round(min(60, 20 + ($coverage * 0.25) + (count($signals) * 5) + min(10, $samples / 2)));

            return [
                'hourly' => round($daily / 24, 3),
                'daily' => round($daily, 2),
                'low' => round(max(0, $daily * 0.35), 2),
                'high' => round($daily * 1.65, 2),
                'confidence' => $confidence,
                'method' => 'engagement_velocity_24h',
                'status' => 'proxy',
                'signal_count' => count($signals),
            ];
        }

        $hasPublishedStock = is_numeric($current->stock_quantity);
        $hasHistory = (int) ($stockVelocity['sample_count'] ?? 0) >= 2
            || (int) ($engagementVelocity['sample_count'] ?? 0) >= 2;

        return [
            'hourly' => null,
            'daily' => null,
            'low' => null,
            'high' => null,
            'confidence' => $hasHistory ? 15 : 0,
            'method' => $hasPublishedStock ? 'insufficient_history' : 'source_unavailable',
            'status' => $hasPublishedStock ? 'warming_up' : 'unavailable',
            'signal_count' => 0,
        ];
    }

    /**
     * Son 24 saatteki stok snapshot'larını kayan pencere olarak işler.
     * Stok takviyesi satış sayılmaz; yalnızca ardışık düşüşler toplanır.
     *
     * @return array<string, int|float|bool|string|null>
     */
    protected function stockVelocity24Hours(TrendyolBoosterSnapshot $current, Collection $history): array
    {
        $currentAt = $current->checked_at ?: now();
        $windowStartsAt = $currentAt->copy()->subHours(24);
        $samples = $history
            ->filter(fn (TrendyolBoosterSnapshot $snapshot): bool => $snapshot->checked_at !== null
                && $snapshot->checked_at->greaterThanOrEqualTo($windowStartsAt)
                && is_numeric($snapshot->stock_quantity))
            ->push($current)
            ->filter(fn (TrendyolBoosterSnapshot $snapshot): bool => $snapshot->checked_at !== null
                && is_numeric($snapshot->stock_quantity))
            ->unique('id')
            ->sortBy(fn (TrendyolBoosterSnapshot $snapshot): string => $snapshot->checked_at->format('Y-m-d H:i:s.u'))
            ->values();

        if ($samples->count() < 2) {
            return [
                'window_hours' => 24,
                'sample_count' => $samples->count(),
                'observed_hours' => null,
                'coverage_percent' => 0,
                'window_complete' => false,
                'observed_drop_units' => null,
                'projected_drop_24h' => null,
                'restocked_units' => 0,
                'net_stock_change' => null,
                'first_stock' => $samples->first()?->stock_quantity,
                'current_stock' => $current->stock_quantity,
                'first_sample_at' => $samples->first()?->checked_at?->toIso8601String(),
                'last_sample_at' => $currentAt->toIso8601String(),
                'last_drop_at' => null,
            ];
        }

        $drop = 0;
        $restocked = 0;
        $lastDropAt = null;
        foreach ($samples->sliding(2) as $pair) {
            $before = (int) $pair->first()->stock_quantity;
            $afterSnapshot = $pair->last();
            $after = (int) $afterSnapshot->stock_quantity;
            if ($after < $before) {
                $drop += $before - $after;
                $lastDropAt = $afterSnapshot->checked_at?->toIso8601String();
            } elseif ($after > $before) {
                $restocked += $after - $before;
            }
        }

        $first = $samples->first();
        $last = $samples->last();
        $observedHours = max(0, $first->checked_at->floatDiffInHours($last->checked_at));
        $coverage = min(100, ($observedHours / 24) * 100);
        $projected = $observedHours >= 0.5 ? ($drop / $observedHours) * 24 : null;

        return [
            'window_hours' => 24,
            'sample_count' => $samples->count(),
            'observed_hours' => round($observedHours, 2),
            'coverage_percent' => (int) round($coverage),
            'window_complete' => $observedHours >= 23,
            'observed_drop_units' => $drop,
            'projected_drop_24h' => $projected !== null ? round($projected, 2) : null,
            'restocked_units' => $restocked,
            'net_stock_change' => (int) $last->stock_quantity - (int) $first->stock_quantity,
            'first_stock' => (int) $first->stock_quantity,
            'current_stock' => (int) $last->stock_quantity,
            'first_sample_at' => $first->checked_at->toIso8601String(),
            'last_sample_at' => $last->checked_at->toIso8601String(),
            'last_drop_at' => $lastDropAt,
        ];
    }

    /**
     * Stok yayınlanmadığında kullanılmak üzere son 24 saatteki değerlendirme,
     * yorum ve yalnızca kesin favori sayılarını kayan pencere olarak işler.
     *
     * @return array<string, int|float|bool|string|null>
     */
    protected function engagementVelocity24Hours(TrendyolBoosterSnapshot $current, Collection $history): array
    {
        $currentAt = $current->checked_at ?: now();
        $windowStartsAt = $currentAt->copy()->subHours(24);
        $samples = $history
            ->filter(fn (TrendyolBoosterSnapshot $snapshot): bool => $snapshot->checked_at !== null
                && $snapshot->checked_at->greaterThanOrEqualTo($windowStartsAt))
            ->push($current)
            ->filter(fn (TrendyolBoosterSnapshot $snapshot): bool => $snapshot->checked_at !== null)
            ->unique('id')
            ->sortBy(fn (TrendyolBoosterSnapshot $snapshot): string => $snapshot->checked_at->format('Y-m-d H:i:s.u'))
            ->values();

        $first = $samples->first();
        $last = $samples->last();
        $observedHours = $samples->count() >= 2
            ? max(0, $first->checked_at->floatDiffInHours($last->checked_at))
            : null;

        return [
            'window_hours' => 24,
            'sample_count' => $samples->count(),
            'observed_hours' => $observedHours !== null ? round($observedHours, 2) : null,
            'coverage_percent' => $observedHours !== null ? (int) round(min(100, ($observedHours / 24) * 100)) : 0,
            'window_complete' => $observedHours !== null && $observedHours >= 23,
            'evaluation_delta' => $this->metricDelta($samples, 'evaluation_count'),
            'review_delta' => $this->metricDelta($samples, 'review_count'),
            'favorite_delta' => $this->metricDelta($samples, 'favorite_count', true),
            'first_sample_at' => $first?->checked_at?->toIso8601String(),
            'last_sample_at' => $last?->checked_at?->toIso8601String(),
        ];
    }

    /** @param Collection<int, TrendyolBoosterSnapshot> $samples */
    protected function metricDelta(Collection $samples, string $field, bool $exactFavoriteOnly = false): float|int|null
    {
        $comparable = $samples->filter(function (TrendyolBoosterSnapshot $snapshot) use ($field, $exactFavoriteOnly): bool {
            if (! is_numeric($snapshot->{$field})) {
                return false;
            }

            return ! $exactFavoriteOnly || $snapshot->favorite_precision === 'exact';
        })->values();

        if ($comparable->count() < 2) {
            return null;
        }

        return round((float) $comparable->last()->{$field} - (float) $comparable->first()->{$field}, 3);
    }

    /**
     * @param  array<int, mixed>  $reviews
     * @return array{score: ?float, positive_topics: array<string, int>, negative_topics: array<string, int>}
     */
    protected function sentiment(array $reviews): array
    {
        $texts = collect($reviews)
            ->filter(fn ($review) => is_array($review))
            ->map(fn (array $review): string => Str::lower((string) ($review['comment'] ?? '')))
            ->filter();

        if ($texts->isEmpty()) {
            return ['score' => null, 'positive_topics' => [], 'negative_topics' => []];
        }

        $positive = $this->topicCounts($texts, $this->positiveTopics);
        $negative = $this->topicCounts($texts, $this->negativeTopics);
        $positiveHits = array_sum($positive);
        $negativeHits = array_sum($negative);
        $lexiconScore = ($positiveHits + $negativeHits) > 0
            ? (($positiveHits - $negativeHits) / ($positiveHits + $negativeHits)) * 100
            : 0;
        $ratingScore = collect($reviews)
            ->filter(fn ($review) => is_array($review) && is_numeric($review['rate'] ?? null))
            ->avg(fn (array $review): float => (float) $review['rate']);
        $ratingNormalized = $ratingScore !== null ? (($ratingScore - 3) / 2) * 100 : 0;

        return [
            'score' => round(max(-100, min(100, ($lexiconScore * 0.55) + ($ratingNormalized * 0.45))), 2),
            'positive_topics' => $positive,
            'negative_topics' => $negative,
        ];
    }

    /**
     * @param  Collection<int, string>  $texts
     * @param  array<string, array<int, string>>  $dictionary
     * @return array<string, int>
     */
    protected function topicCounts(Collection $texts, array $dictionary): array
    {
        $counts = [];
        foreach ($dictionary as $topic => $needles) {
            $count = $texts->sum(function (string $text) use ($needles): int {
                foreach ($needles as $needle) {
                    if (Str::contains($text, $needle, true)) {
                        return 1;
                    }
                }

                return 0;
            });
            if ($count > 0) {
                $counts[$topic] = $count;
            }
        }

        arsort($counts);

        return array_slice($counts, 0, 5, true);
    }

    /** @param array<string, float|int|null> $deltas */
    protected function interestScore(TrendyolBoosterSnapshot $current, array $deltas, ?float $elapsedHours, ?float $sentiment): int
    {
        $score = 0.0;
        $score += min(25, log10(max(1, (int) ($current->favorite_count ?? 0))) * 6);
        $score += min(20, log10(max(1, (int) ($current->evaluation_count ?? 0))) * 6);
        $score += min(15, max(0, ((float) ($current->average_rating ?? 0) - 3) * 7.5));
        $score += $current->category_rank !== null ? max(0, 15 - min(15, ($current->category_rank - 1) * 1.5)) : 0;
        if ($elapsedHours !== null) {
            $dailyFavoriteGrowth = max(0, (float) ($deltas['favorite'] ?? 0)) * (24 / $elapsedHours);
            $score += min(15, log10(1 + $dailyFavoriteGrowth) * 5);
        }
        $score += $sentiment !== null ? max(0, $sentiment) * 0.1 : 0;

        return (int) round(max(0, min(100, $score)));
    }

    protected function competitionScore(TrendyolBoosterProduct $product, float $currentPrice): int
    {
        $competitors = $product->competitors()->whereNotNull('sale_price')->get();
        if ($competitors->isEmpty() || $currentPrice <= 0) {
            return 0;
        }

        $cheaper = $competitors->filter(fn ($row) => (float) $row->sale_price < $currentPrice)->count();
        $average = (float) $competitors->avg('sale_price');
        $pricePressure = $average > 0 ? max(0, (($currentPrice - $average) / $average) * 100) : 0;

        return (int) round(min(100, ($cheaper / max(1, $competitors->count())) * 70 + min(30, $pricePressure)));
    }

    protected function riskScore(
        TrendyolBoosterSnapshot $current,
        Collection $history,
        ?float $sentiment,
        int $competition,
        ?float $daysOfStock,
    ): int {
        $score = $competition * 0.3;
        if ($current->stock_status === 'out_of_stock') {
            $score += 35;
        } elseif ($daysOfStock !== null && $daysOfStock < 3) {
            $score += 20;
        }
        if ($current->average_rating !== null && (float) $current->average_rating < 4) {
            $score += min(20, (4 - (float) $current->average_rating) * 20);
        }
        if ($sentiment !== null && $sentiment < 0) {
            $score += min(20, abs($sentiment) * 0.2);
        }
        if ($current->seller_score !== null && (float) $current->seller_score < 8) {
            $score += min(10, (8 - (float) $current->seller_score) * 5);
        }
        $stats = $this->priceStatistics($current, $history);
        $score += min(15, (float) ($stats['volatility_percent'] ?? 0) * 0.5);

        return (int) round(max(0, min(100, $score)));
    }

    protected function dataQualityScore(TrendyolBoosterSnapshot $snapshot): int
    {
        $weights = [
            'sale_price' => 15,
            'stock_quantity' => 15,
            'evaluation_count' => 10,
            'review_count' => 10,
            'average_rating' => 10,
            'favorite_count' => 10,
            'question_count' => 5,
            'category_rank' => 5,
            'seller_score' => 5,
            'basket_count' => 5,
            'view_count_24h' => 10,
        ];
        $score = 0;
        foreach ($weights as $field => $weight) {
            if ($snapshot->{$field} !== null) {
                $score += $weight;
            }
        }

        if ($snapshot->favorite_count !== null && $snapshot->favorite_precision === 'rounded') {
            $score -= 4;
        }

        return max(0, min(100, $score));
    }

    /** @return array<string, float|null> */
    protected function priceStatistics(TrendyolBoosterSnapshot $current, Collection $history): array
    {
        $prices = $history->pluck('sale_price')
            ->push($current->sale_price)
            ->map(fn ($value): float => (float) $value)
            ->filter(fn (float $value): bool => $value > 0);

        if ($prices->isEmpty()) {
            return ['minimum' => null, 'maximum' => null, 'average' => null, 'volatility_percent' => null];
        }

        $average = (float) $prices->avg();
        $variance = $prices->avg(fn (float $price): float => ($price - $average) ** 2);
        $deviation = sqrt((float) $variance);

        return [
            'minimum' => round((float) $prices->min(), 2),
            'maximum' => round((float) $prices->max(), 2),
            'average' => round($average, 2),
            'volatility_percent' => $average > 0 ? round(($deviation / $average) * 100, 2) : null,
        ];
    }

    /** @param array<string, float|int|null> $deltas */
    protected function growthPerDay(array $deltas, ?float $elapsedHours): array
    {
        if ($elapsedHours === null) {
            return collect($deltas)->map(fn () => null)->all();
        }

        return collect($deltas)->map(fn ($value) => $value !== null ? round((float) $value * (24 / $elapsedHours), 2) : null)->all();
    }

    protected function campaignPerformance(
        TrendyolBoosterSnapshot $current,
        ?TrendyolBoosterSnapshot $previous,
        ?float $dailySales,
    ): array {
        $currentCampaign = data_get($current->raw_payload, 'page.campaign_signature');
        $previousCampaign = data_get($previous?->raw_payload, 'page.campaign_signature');
        $changed = $previous !== null && $currentCampaign !== $previousCampaign;

        return [
            'campaign_changed' => $changed,
            'previous_signature' => $previousCampaign,
            'current_signature' => $currentCampaign,
            'estimated_daily_sales_after_change' => $changed ? $dailySales : null,
        ];
    }

    protected function isComparablePrecision(TrendyolBoosterSnapshot $current, ?TrendyolBoosterSnapshot $previous): bool
    {
        if ($previous === null || $current->favorite_count === null || $previous->favorite_count === null) {
            return false;
        }

        $currentPrecision = $current->favorite_precision ?: 'unknown';
        $previousPrecision = $previous->favorite_precision ?: 'unknown';

        return $currentPrecision === $previousPrecision && $currentPrecision !== 'rounded';
    }

    protected function delta(mixed $current, mixed $previous): float|int|null
    {
        if (! is_numeric($current) || ! is_numeric($previous)) {
            return null;
        }

        return round((float) $current - (float) $previous, 3);
    }
}
