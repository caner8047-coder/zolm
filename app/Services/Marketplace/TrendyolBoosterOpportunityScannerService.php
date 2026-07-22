<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Collection;

class TrendyolBoosterOpportunityScannerService
{
    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, mixed>
     */
    public function scan(array $items): array
    {
        $rows = collect($items)->take(40)->values();
        $prices = $rows->pluck('sale_price')->filter(fn ($value): bool => is_numeric($value) && (float) $value > 0)->map(fn ($value): float => (float) $value)->sort()->values();
        $reviews = $rows->pluck('review_count')->filter(fn ($value): bool => is_numeric($value) && (int) $value >= 0)->map(fn ($value): int => (int) $value)->sort()->values();
        $medianPrice = $this->median($prices);
        $medianReviews = $this->median($reviews);
        $brandCounts = $rows->groupBy(fn (array $item): string => trim((string) ($item['brand'] ?? '')) ?: 'Bilinmeyen')->map->count();

        $ranked = $rows->map(function (array $item, int $index) use ($medianPrice, $medianReviews, $brandCounts): array {
            $price = max(0, (float) ($item['sale_price'] ?? 0));
            $rating = max(0, min(5, (float) ($item['rating'] ?? 0)));
            $reviewCount = max(0, (int) ($item['review_count'] ?? 0));
            $brand = trim((string) ($item['brand'] ?? '')) ?: 'Bilinmeyen';
            $priceScore = $medianPrice && $price > 0
                ? max(0, min(100, (int) round(65 + (($medianPrice - $price) / $medianPrice) * 70)))
                : 35;
            $qualityScore = $rating > 0 ? (int) round(($rating / 5) * 100) : 20;
            $demandScore = $medianReviews && $medianReviews > 0
                ? max(0, min(100, (int) round(($reviewCount / max(1, $medianReviews)) * 55)))
                : ($reviewCount > 0 ? 45 : 15);
            $competitionScore = $medianReviews && $medianReviews > 0
                ? max(0, min(100, (int) round(100 - (($reviewCount / max(1, $medianReviews * 2)) * 70))))
                : 55;
            $brandDiversityScore = (int) max(20, 100 - (((int) ($brandCounts[$brand] ?? 1) - 1) * 15));
            $completeness = collect([$price > 0, $rating > 0, $reviewCount > 0, $brand !== 'Bilinmeyen'])->filter()->count();
            $confidence = $completeness * 20 + 10;
            $score = (int) round(
                ($priceScore * .24)
                + ($qualityScore * .22)
                + ($demandScore * .20)
                + ($competitionScore * .20)
                + ($brandDiversityScore * .14)
            );
            if ($confidence < 70) {
                $score = min($score, 64);
            }

            $reasons = collect([
                $medianPrice && $price > 0 && $price < $medianPrice * .9 ? 'Liste medyanının altında fiyat' : null,
                $rating >= 4.5 ? 'Yüksek kullanıcı puanı' : null,
                $reviewCount > 0 && $medianReviews && $reviewCount < $medianReviews ? 'Görece düşük yorum rekabeti' : null,
                ((int) ($brandCounts[$brand] ?? 0)) <= 1 ? 'Listede düşük marka yoğunluğu' : null,
                $confidence < 70 ? 'Eksik kart verisi nedeniyle skor sınırlandı' : null,
            ])->filter()->values()->all();

            return [
                'trendyol_product_id' => (string) ($item['trendyol_product_id'] ?? ''),
                'source_url' => (string) ($item['source_url'] ?? ''),
                'title' => (string) ($item['title'] ?? 'Ürün'),
                'brand' => $brand,
                'sale_price' => $price > 0 ? $price : null,
                'rating' => $rating > 0 ? $rating : null,
                'review_count' => $reviewCount,
                'source_rank' => $index + 1,
                'opportunity_score' => max(0, min(100, $score)),
                'confidence_score' => max(0, min(100, $confidence)),
                'priority' => $score >= 75 ? 'high' : ($score >= 58 ? 'medium' : 'watch'),
                'priority_label' => $score >= 75 ? 'Güçlü aday' : ($score >= 58 ? 'İncelenecek' : 'İzle'),
                'signals' => [
                    'price' => $priceScore,
                    'quality' => $qualityScore,
                    'demand' => $demandScore,
                    'competition' => $competitionScore,
                    'brand_diversity' => $brandDiversityScore,
                ],
                'reasons' => $reasons,
            ];
        })->sortByDesc(fn (array $row): array => [$row['opportunity_score'], $row['confidence_score']])->values();

        return [
            'scanned_count' => $ranked->count(),
            'high_opportunity_count' => $ranked->where('priority', 'high')->count(),
            'median_price' => $medianPrice,
            'median_review_count' => $medianReviews,
            'results' => $ranked->all(),
            'method_note' => 'Skor; görünür fiyat, puan, yorum rekabeti, marka yoğunluğu ve veri tamlığından hesaplanır. Kesin satış veya kâr sonucu değildir.',
        ];
    }

    /** @param Collection<int, int|float> $values */
    protected function median(Collection $values): ?float
    {
        if ($values->isEmpty()) {
            return null;
        }

        $middle = intdiv($values->count(), 2);
        $value = $values->count() % 2 === 1
            ? (float) $values[$middle]
            : ((float) $values[$middle - 1] + (float) $values[$middle]) / 2;

        return round($value, 2);
    }
}
