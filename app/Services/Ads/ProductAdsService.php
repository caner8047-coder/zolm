<?php

namespace App\Services\Ads;

use App\Models\AdCampaign;
use App\Models\AdCampaignSnapshot;
use App\Models\AdProductSnapshot;
use App\Models\AdReconciliation;
use App\Enums\AdChannelCode;
use App\Enums\ReconciliationStatus;
use Illuminate\Support\Facades\DB;

class ProductAdsService
{
    /**
     * Kampanya listesi için istatistik hesapla
     */
    public function getCampaignStats(int $userId): array
    {
        $snapshotQuery = AdCampaignSnapshot::whereHas('campaign', function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->where('channel_code', AdChannelCode::ProductAds->value);
        });

        // Son snapshot'ları al
        $latestSnapshots = AdCampaignSnapshot::whereIn('id', function ($q) {
            $q->selectRaw('MAX(id)')
                ->from('ad_campaign_snapshots')
                ->groupBy('campaign_id');
        })->whereHas('campaign', function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->where('channel_code', AdChannelCode::ProductAds->value);
        });

        $stats = (clone $latestSnapshots)->selectRaw('
            COUNT(*) as total_campaigns,
            COALESCE(SUM(spend), 0) as total_spend,
            COALESCE(SUM(revenue_total), 0) as total_revenue,
            COALESCE(AVG(CASE WHEN spend > 0 THEN revenue_total / spend ELSE 0 END), 0) as avg_roas,
            COALESCE(AVG(CASE WHEN spend > 0 THEN revenue_direct / spend ELSE 0 END), 0) as direct_roas,
            COALESCE(AVG(CASE WHEN spend > 0 THEN revenue_indirect / spend ELSE 0 END), 0) as indirect_roas,
            COALESCE(SUM(CASE WHEN sales_total = 0 AND spend > 0 THEN spend ELSE 0 END), 0) as zero_sale_spend
        ')->first();

        $activeCount = AdCampaign::where('user_id', $userId)
            ->where('channel_code', AdChannelCode::ProductAds->value)
            ->where('status', 'active')
            ->count();

        return [
            'total_campaigns' => $stats->total_campaigns ?? 0,
            'active_campaigns' => $activeCount,
            'total_spend' => $stats->total_spend ?? 0,
            'total_revenue' => $stats->total_revenue ?? 0,
            'avg_roas' => $stats->avg_roas ?? 0,
            'direct_roas' => $stats->direct_roas ?? 0,
            'indirect_roas' => $stats->indirect_roas ?? 0,
            'zero_sale_spend' => $stats->zero_sale_spend ?? 0,
        ];
    }

    /**
     * Kampanya detay verilerini yükle
     */
    public function getCampaignDetail(int $campaignId, int $userId): array
    {
        $campaign = AdCampaign::where('id', $campaignId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $snapshots = AdCampaignSnapshot::where('campaign_id', $campaignId)
            ->orderByDesc('captured_at')
            ->take(10)
            ->get();

        $productSnapshots = AdProductSnapshot::where('campaign_id', $campaignId)
            ->with('adCampaignProduct')
            ->orderByDesc('captured_at')
            ->take(50)
            ->get();

        $reconciliations = AdReconciliation::where('campaign_id', $campaignId)
            ->orderByDesc('calculated_at')
            ->take(10)
            ->get();

        $latestSnapshot = $snapshots->first();

        $stats = [
            'total_spend' => $latestSnapshot->spend ?? 0,
            'total_revenue_direct' => $latestSnapshot->revenue_direct ?? 0,
            'total_revenue_indirect' => $latestSnapshot->revenue_indirect ?? 0,
            'total_revenue' => $latestSnapshot->revenue_total ?? 0,
            'total_sales' => $latestSnapshot->sales_total ?? 0,
            'avg_roas' => $latestSnapshot->roas ?? 0,
            'direct_roas' => ($latestSnapshot && $latestSnapshot->spend > 0)
                ? $latestSnapshot->revenue_direct / $latestSnapshot->spend
                : 0,
            'indirect_roas' => ($latestSnapshot && $latestSnapshot->spend > 0)
                ? $latestSnapshot->revenue_indirect / $latestSnapshot->spend
                : 0,
        ];

        return compact('campaign', 'snapshots', 'productSnapshots', 'reconciliations', 'stats');
    }

    /**
     * Sıfır satışlı harcama yapan kampanyaları bul
     */
    public function findZeroSaleCampaigns(int $userId): array
    {
        return AdCampaign::where('user_id', $userId)
            ->where('channel_code', AdChannelCode::ProductAds->value)
            ->whereHas('latestSnapshot', function ($q) {
                $q->where('sales_total', 0)
                  ->where('spend', '>', 0);
            })
            ->with('latestSnapshot')
            ->get()
            ->toArray();
    }

    /**
     * Mutabakat hesapla
     */
    public function calculateReconciliation(int $campaignId, int $summaryBatchId, int $detailBatchId): AdReconciliation
    {
        $summarySnapshot = AdCampaignSnapshot::where('campaign_id', $campaignId)
            ->where('import_batch_id', $summaryBatchId)
            ->first();

        $detailSnapshots = AdProductSnapshot::where('campaign_id', $campaignId)
            ->where('import_batch_id', $detailBatchId)
            ->get();

        $detailSpend = $detailSnapshots->sum('spend');
        $detailRevenue = $detailSnapshots->sum('revenue_total');
        $detailSales = $detailSnapshots->sum('sales_total');

        $campaignSpend = $summarySnapshot->spend ?? 0;
        $campaignRevenue = $summarySnapshot->revenue_total ?? 0;
        $campaignSales = $summarySnapshot->sales_total ?? 0;

        $spendDiff = abs($campaignSpend - $detailSpend);
        $revenueDiff = abs($campaignRevenue - $detailRevenue);
        $salesDiff = abs($campaignSales - $detailSales);

        $maxDiff = max($campaignSpend, $detailSpend, 1);
        $diffPercent = ($spendDiff / $maxDiff) * 100;

        $status = match (true) {
            $diffPercent <= 0.5 => ReconciliationStatus::Compatible,
            $diffPercent <= 2 => ReconciliationStatus::CheckNeeded,
            default => ReconciliationStatus::Critical,
        };

        return AdReconciliation::create([
            'user_id' => auth()->id(),
            'campaign_id' => $campaignId,
            'summary_import_batch_id' => $summaryBatchId,
            'detail_import_batch_id' => $detailBatchId,
            'comparison_type' => 'campaign_vs_product',
            'campaign_spend' => $campaignSpend,
            'detail_spend' => $detailSpend,
            'spend_difference' => $spendDiff,
            'campaign_revenue' => $campaignRevenue,
            'detail_revenue' => $detailRevenue,
            'revenue_difference' => $revenueDiff,
            'campaign_sales' => $campaignSales,
            'detail_sales' => $detailSales,
            'sales_difference' => $salesDiff,
            'difference_percent' => $diffPercent,
            'status' => $status->value,
            'calculated_at' => now(),
        ]);
    }
}
