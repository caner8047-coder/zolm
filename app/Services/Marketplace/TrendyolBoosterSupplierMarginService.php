<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Collection;

class TrendyolBoosterSupplierMarginService
{
    /**
     * @param iterable<int, mixed> $offers
     * @return array<string, mixed>
     */
    public function scenarios(
        iterable $offers,
        float $targetSalePrice,
        float $commissionRate,
        float $shippingCost,
        float $packagingCost,
        float $targetMargin,
    ): array {
        $targetSalePrice = max(0, $targetSalePrice);
        $commissionRate = max(0, min(100, $commissionRate));
        $targetMargin = max(-100, min(100, $targetMargin));
        $fixedCost = max(0, $shippingCost) + max(0, $packagingCost);
        $commission = round($targetSalePrice * ($commissionRate / 100), 2);
        $maxPurchaseCost = round(max(0, $targetSalePrice - $commission - $fixedCost - ($targetSalePrice * ($targetMargin / 100))), 2);

        $rows = collect($offers)->map(function (mixed $offer) use ($targetSalePrice, $commission, $fixedCost, $maxPurchaseCost, $targetMargin): array {
            $purchaseCost = max(0, (float) data_get($offer, 'sale_price', 0));
            $profit = round($targetSalePrice - $purchaseCost - $commission - $fixedCost, 2);
            $margin = $targetSalePrice > 0 ? round(($profit / $targetSalePrice) * 100, 2) : null;
            $matchScore = (int) data_get($offer, 'match_score', 0);
            $ready = $targetSalePrice > 0 && $purchaseCost > 0 && $matchScore >= 85;
            $decision = ! $ready
                ? 'verify'
                : ($profit <= 0 ? 'reject' : (($margin ?? -100) >= $targetMargin ? 'go' : 'negotiate'));

            return [
                'offer_id' => data_get($offer, 'id'),
                'platform' => (string) data_get($offer, 'platform_label', data_get($offer, 'platform', 'Kanal')),
                'seller' => (string) data_get($offer, 'seller_name', 'Satıcı'),
                'source_url' => (string) data_get($offer, 'source_url', ''),
                'match_score' => $matchScore,
                'purchase_cost' => $purchaseCost,
                'target_sale_price' => $targetSalePrice,
                'commission' => $commission,
                'fixed_cost' => $fixedCost,
                'net_profit' => $profit,
                'margin' => $margin,
                'max_purchase_cost' => $maxPurchaseCost,
                'negotiation_gap' => round($purchaseCost - $maxPurchaseCost, 2),
                'decision' => $decision,
                'decision_label' => match ($decision) {
                    'go' => 'Marj hedefinde',
                    'negotiate' => 'Pazarlık gerekli',
                    'reject' => 'Zarar riski',
                    default => 'Eşleşmeyi doğrula',
                },
            ];
        })->sortBy(fn (array $row): array => [
            ['go' => 0, 'negotiate' => 1, 'reject' => 2, 'verify' => 3][$row['decision']] ?? 4,
            -($row['margin'] ?? -999),
        ])->values();

        return [
            'target_sale_price' => $targetSalePrice,
            'commission_rate' => $commissionRate,
            'target_margin' => $targetMargin,
            'max_purchase_cost' => $maxPurchaseCost,
            'best_profit' => $rows->where('decision', '!=', 'verify')->max('net_profit'),
            'go_count' => $rows->where('decision', 'go')->count(),
            'negotiate_count' => $rows->where('decision', 'negotiate')->count(),
            'rows' => $rows->take(20)->all(),
            'evidence_note' => 'Teklif fiyatı alış maliyeti varsayımıyla hesaplanır; KDV, iade, reklam ve iskonto doğrulanmadan kesin kâr değildir.',
        ];
    }
}
