<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Collection;

class TrendyolBoosterStorePortfolioService
{
    /**
     * @param Collection<int, mixed> $items
     * @return array<string, mixed>
     */
    public function analyze(Collection $items): array
    {
        $active = $items->reject(fn ($item): bool => (bool) data_get($item, 'is_removed', false))->values();
        $removedCount = $items->where('is_removed', true)->count();
        $total = $active->count();
        $prices = $active->map(fn ($item): float => (float) data_get($item, 'sale_price', 0))->filter(fn (float $price): bool => $price > 0)->sort()->values();
        $campaignCount = $active->filter(fn ($item): bool => count((array) data_get($item, 'campaign_badges', [])) > 0)->count();
        $newCount = $active->where('is_new', true)->count();
        $topSellerCount = $active->where('is_first_seller', true)->count();
        $positiveMomentum = $active->filter(fn ($item): bool => (int) data_get($item, 'review_delta', 0) > 0 || (bool) data_get($item, 'is_new', false))->count();
        $estimatedDailySales = round((float) $active->sum(fn ($item): float => (float) data_get($item, 'store_sales_signal.estimated_daily_sales', 0)), 2);
        $categories = $active
            ->groupBy(fn ($item): string => trim((string) data_get($item, 'category_name', '')) ?: 'Kategorisiz')
            ->map(function (Collection $group, string $category) use ($total): array {
                $categoryPrices = $group->map(fn ($item): float => (float) data_get($item, 'sale_price', 0))->filter(fn (float $price): bool => $price > 0);

                return [
                    'category' => $category,
                    'product_count' => $group->count(),
                    'share_percent' => $total > 0 ? round(($group->count() / $total) * 100, 1) : 0.0,
                    'average_price' => $categoryPrices->isNotEmpty() ? round((float) $categoryPrices->avg(), 2) : null,
                    'review_count' => (int) $group->sum(fn ($item): int => (int) data_get($item, 'review_count', 0)),
                    'new_count' => $group->where('is_new', true)->count(),
                    'campaign_count' => $group->filter(fn ($item): bool => count((array) data_get($item, 'campaign_badges', [])) > 0)->count(),
                    'estimated_daily_sales' => round((float) $group->sum(fn ($item): float => (float) data_get($item, 'store_sales_signal.estimated_daily_sales', 0)), 2),
                ];
            })
            ->sortByDesc('product_count')
            ->values()
            ->all();
        $dominantShare = (float) data_get($categories, '0.share_percent', 0);

        $coverageScore = $total > 0 ? min(25, (int) round(($prices->count() / $total) * 25)) : 0;
        $momentumScore = $total > 0 ? min(25, (int) round(($positiveMomentum / $total) * 25)) : 0;
        $campaignScore = $total > 0 ? min(20, (int) round(($campaignCount / $total) * 20)) : 0;
        $stabilityScore = ($total + $removedCount) > 0 ? max(0, (int) round((1 - ($removedCount / ($total + $removedCount))) * 15)) : 0;
        $sellerScore = $total > 0 ? min(15, (int) round(($topSellerCount / $total) * 15)) : 0;
        $score = min(100, $coverageScore + $momentumScore + $campaignScore + $stabilityScore + $sellerScore);

        return [
            'score' => $score,
            'score_label' => match (true) {
                $score >= 75 => 'Yüksek hareket',
                $score >= 55 => 'Aktif katalog',
                $score >= 35 => 'Dengeli katalog',
                default => 'Sınırlı sinyal',
            },
            'active_product_count' => $total,
            'new_product_count' => $newCount,
            'removed_product_count' => $removedCount,
            'campaign_count' => $campaignCount,
            'campaign_share_percent' => $total > 0 ? round(($campaignCount / $total) * 100, 1) : 0.0,
            'top_seller_share_percent' => $total > 0 ? round(($topSellerCount / $total) * 100, 1) : 0.0,
            'median_price' => $this->median($prices),
            'minimum_price' => $prices->isNotEmpty() ? (float) $prices->first() : null,
            'maximum_price' => $prices->isNotEmpty() ? (float) $prices->last() : null,
            'estimated_daily_sales' => $estimatedDailySales > 0 ? $estimatedDailySales : null,
            'dominant_category' => data_get($categories, '0.category'),
            'dominant_category_share_percent' => $dominantShare,
            'concentration_label' => $dominantShare >= 60 ? 'Yüksek yoğunlaşma' : ($dominantShare >= 35 ? 'Odaklı portföy' : 'Dağıtılmış portföy'),
            'categories' => $categories,
            'evidence_note' => 'Ürün, fiyat, kampanya ve katalog değişimleri gözlenen veridir. Günlük satış sinyali yorum/favori hareketinden türetilen tahmindir.',
        ];
    }

    /** @param Collection<int, float> $values */
    protected function median(Collection $values): ?float
    {
        if ($values->isEmpty()) {
            return null;
        }

        $middle = intdiv($values->count(), 2);
        if ($values->count() % 2 === 1) {
            return round((float) $values[$middle], 2);
        }

        return round(((float) $values[$middle - 1] + (float) $values[$middle]) / 2, 2);
    }
}
