<?php

namespace App\Services\Ads;

use App\Models\AdProfitabilitySnapshot;
use App\Models\AdCampaignSnapshot;
use App\Models\AdCampaign;
use App\Enums\CalculationStatus;

class ProfitabilityService
{
    /**
     * Kârlılık hesaplaması yap
     */
    public function calculate(int $userId, int $campaignId, array $costData): AdProfitabilitySnapshot
    {
        $campaign = AdCampaign::findOrFail($campaignId);
        $snapshot = AdCampaignSnapshot::where('campaign_id', $campaignId)
            ->latest('captured_at')
            ->first();

        if (!$snapshot) {
            throw new \RuntimeException('Kampanya için snapshot bulunamadı.');
        }

        // Eksik girdileri kontrol et
        $missingInputs = $this->detectMissingInputs($costData);
        $calculationStatus = $this->determineCalculationStatus($missingInputs);

        // Hesaplamalar
        $netRevenue = $snapshot->revenue_total;
        $productCost = $costData['product_cost'] ?? 0;
        $marketplaceCommission = $costData['marketplace_commission'] ?? 0;
        $shippingCost = $costData['shipping_cost'] ?? 0;
        $packagingCost = $costData['packaging_cost'] ?? 0;
        $discountCost = $costData['discount_cost'] ?? 0;
        $returnCost = $costData['return_cost'] ?? 0;
        $adSpend = $snapshot->spend;
        $influencerCost = $costData['influencer_cost'] ?? 0;

        $grossProfit = $netRevenue - $productCost - $marketplaceCommission - $shippingCost - $packagingCost - $discountCost - $returnCost;
        $contributionProfitBeforeAds = $grossProfit;
        $netContributionProfit = $grossProfit - $adSpend - $influencerCost;
        $netMarginPercent = $netRevenue > 0 ? ($netContributionProfit / $netRevenue) * 100 : 0;

        // Başabaş ROAS
        $maxAdSpend = max($contributionProfitBeforeAds - ($costData['target_min_profit'] ?? 0), 0);
        $breakEvenRoas = $maxAdSpend > 0 ? $netRevenue / $maxAdSpend : null;

        return AdProfitabilitySnapshot::create([
            'user_id' => $userId,
            'campaign_id' => $campaignId,
            'period_start' => $snapshot->period_start,
            'period_end' => $snapshot->period_end,
            'net_revenue' => $netRevenue,
            'product_cost' => $productCost,
            'marketplace_commission' => $marketplaceCommission,
            'shipping_cost' => $shippingCost,
            'packaging_cost' => $packagingCost,
            'discount_cost' => $discountCost,
            'return_cost' => $returnCost,
            'ad_spend' => $adSpend,
            'influencer_cost' => $influencerCost,
            'gross_profit' => $grossProfit,
            'contribution_profit_before_ads' => $contributionProfitBeforeAds,
            'net_contribution_profit' => $netContributionProfit,
            'net_margin_percent' => $netMarginPercent,
            'break_even_roas' => $breakEvenRoas,
            'calculation_status' => $calculationStatus->value,
            'missing_inputs' => $missingInputs,
        ]);
    }

    /**
     * Eksik girdileri tespit et
     */
    protected function detectMissingInputs(array $costData): array
    {
        $required = [
            'product_cost' => 'Ürün Maliyeti',
            'marketplace_commission' => 'Pazaryeri Komisyonu',
            'shipping_cost' => 'Kargo Maliyeti',
        ];

        $optional = [
            'packaging_cost' => 'Paketleme Maliyeti',
            'discount_cost' => 'İndirim Katkısı',
            'return_cost' => 'İade/İptal Etkisi',
        ];

        $missing = [];

        foreach ($required as $field => $label) {
            if (!isset($costData[$field]) || $costData[$field] === null) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * Hesaplama durumunu belirle
     */
    protected function determineCalculationStatus(array $missingInputs): CalculationStatus
    {
        if (empty($missingInputs)) {
            return CalculationStatus::Complete;
        }

        $criticalMissing = array_intersect($missingInputs, ['product_cost', 'marketplace_commission', 'shipping_cost']);

        if (!empty($criticalMissing)) {
            return CalculationStatus::InsufficientData;
        }

        return CalculationStatus::Partial;
    }

    /**
     * Kârlılık özetini hesapla
     */
    public function getProfitabilitySummary(int $userId): array
    {
        $query = AdProfitabilitySnapshot::where('user_id', $userId);

        $stats = (clone $query)->selectRaw('
            COUNT(*) as total_calculations,
            SUM(CASE WHEN calculation_status = "complete" THEN 1 ELSE 0 END) as complete_calculations,
            SUM(CASE WHEN calculation_status = "partial" THEN 1 ELSE 0 END) as partial_calculations,
            SUM(CASE WHEN calculation_status = "insufficient_data" THEN 1 ELSE 0 END) as insufficient_calculations,
            COALESCE(SUM(net_contribution_profit), 0) as total_net_profit,
            COALESCE(AVG(net_margin_percent), 0) as avg_margin
        ')->first();

        return [
            'total_calculations' => $stats->total_calculations ?? 0,
            'complete_calculations' => $stats->complete_calculations ?? 0,
            'partial_calculations' => $stats->partial_calculations ?? 0,
            'insufficient_calculations' => $stats->insufficient_calculations ?? 0,
            'total_net_profit' => $stats->total_net_profit ?? 0,
            'avg_margin' => $stats->avg_margin ?? 0,
        ];
    }
}
