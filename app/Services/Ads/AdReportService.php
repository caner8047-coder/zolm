<?php

namespace App\Services\Ads;

use App\Models\AdCampaign;
use App\Models\AdCampaignSnapshot;
use App\Models\AdProductSnapshot;
use App\Models\AdKeywordSnapshot;
use App\Models\InfluencerCreatorSnapshot;
use App\Enums\AdChannelCode;
use Illuminate\Support\Facades\DB;

class AdReportService
{
    /**
     * Tüm kanallar için özet rapor
     */
    public function getOverallSummary(int $userId): array
    {
        $channels = [
            AdChannelCode::ProductAds->value,
            AdChannelCode::StoreAds->value,
            AdChannelCode::InfluencerAds->value,
        ];

        $campaignStats = AdCampaign::where('user_id', $userId)
            ->whereIn('channel_code', $channels)
            ->selectRaw('
                channel_code,
                COUNT(*) as total_campaigns,
                SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active_campaigns
            ')
            ->groupBy('channel_code')
            ->get()
            ->keyBy('channel_code');

        $snapshotStats = AdCampaignSnapshot::whereHas('campaign', function ($q) use ($userId, $channels) {
            $q->where('user_id', $userId)
              ->whereIn('channel_code', $channels);
        })->selectRaw('
            campaign_id,
            (SELECT channel_code FROM ad_campaigns WHERE id = campaign_id) as channel_code,
            SUM(spend) as total_spend,
            SUM(revenue_total) as total_revenue,
            SUM(revenue_direct) as total_direct_revenue,
            SUM(revenue_indirect) as total_indirect_revenue,
            SUM(sales_total) as total_sales,
            AVG(CASE WHEN spend > 0 THEN revenue_total / spend ELSE 0 END) as avg_roas
        ')
        ->groupBy('campaign_id')
        ->get()
        ->groupBy('channel_code');

        $summary = [];
        foreach ($channels as $channel) {
            $channelName = AdChannelCode::from($channel)->label();
            $campaigns = $campaignStats->get($channel);
            $snapshots = $snapshotStats->get($channel);

            $totalSpend = $snapshots?->sum('total_spend') ?? 0;
            $totalRevenue = $snapshots?->sum('total_revenue') ?? 0;
            $totalDirectRevenue = $snapshots?->sum('total_direct_revenue') ?? 0;
            $totalIndirectRevenue = $snapshots?->sum('total_indirect_revenue') ?? 0;
            $totalSales = $snapshots?->sum('total_sales') ?? 0;
            $avgRoas = $totalSpend > 0 ? $totalRevenue / $totalSpend : 0;

            $summary[$channel] = [
                'name' => $channelName,
                'total_campaigns' => $campaigns->total_campaigns ?? 0,
                'active_campaigns' => $campaigns->active_campaigns ?? 0,
                'total_spend' => $totalSpend,
                'total_revenue' => $totalRevenue,
                'total_direct_revenue' => $totalDirectRevenue,
                'total_indirect_revenue' => $totalIndirectRevenue,
                'total_sales' => $totalSales,
                'avg_roas' => $avgRoas,
            ];
        }

        return $summary;
    }

    /**
     * CSV olarak dışa aktar
     */
    public function exportToCsv(int $userId, string $type): string
    {
        $callback = function () use ($userId, $type) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            switch ($type) {
                case 'product_campaigns':
                    $this->exportProductCampaigns($userId, $handle);
                    break;
                case 'store_keywords':
                    $this->exportStoreKeywords($userId, $handle);
                    break;
                case 'influencer_creators':
                    $this->exportInfluencerCreators($userId, $handle);
                    break;
            }

            fclose($handle);
        };

        return $callback;
    }

    protected function exportProductCampaigns(int $userId, $handle): void
    {
        fputcsv($handle, ['Kampanya', 'Durum', 'Harcama', 'Gösterim', 'Tıklama', 'Satış', 'D. Ciro', 'Dl. Ciro', 'Toplam Ciro', 'ROAS'], ';');

        $campaigns = AdCampaign::where('user_id', $userId)
            ->where('channel_code', AdChannelCode::ProductAds->value)
            ->with('latestSnapshot')
            ->get();

        foreach ($campaigns as $campaign) {
            $snap = $campaign->latestSnapshot;
            fputcsv($handle, [
                $campaign->name,
                $campaign->status,
                $snap->spend ?? 0,
                $snap->impressions ?? 0,
                $snap->clicks ?? 0,
                $snap->sales_total ?? 0,
                $snap->revenue_direct ?? 0,
                $snap->revenue_indirect ?? 0,
                $snap->revenue_total ?? 0,
                $snap->roas ?? 0,
            ], ';');
        }
    }

    protected function exportStoreKeywords(int $userId, $handle): void
    {
        fputcsv($handle, ['Kelime', 'Kampanya', 'Harcama', 'Gösterim', 'Tıklama', 'Satış', 'Ciro', 'ROAS', 'GBM'], ';');

        $keywords = AdKeywordSnapshot::whereHas('campaign', function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->where('channel_code', AdChannelCode::StoreAds->value);
        })->with('campaign')
          ->get();

        foreach ($keywords as $kw) {
            fputcsv($handle, [
                $kw->keyword,
                $kw->campaign->name ?? '',
                $kw->spend,
                $kw->impressions,
                $kw->clicks,
                $kw->sales_total,
                $kw->revenue_total,
                $kw->roas,
                $kw->actual_gbm ?? 0,
            ], ';');
        }
    }

    protected function exportInfluencerCreators(int $userId, $handle): void
    {
        fputcsv($handle, ['Creator', 'Platform', 'Ziyaret', 'Satış', 'Ciro', 'Yeni Müşteri'], ';');

        $creators = \App\Models\InfluencerProfile::where('user_id', $userId)
            ->with(['creatorSnapshots' => function ($q) use ($userId) {
                $q->whereHas('campaign', function ($cq) use ($userId) {
                    $cq->where('user_id', $userId)
                       ->where('channel_code', AdChannelCode::InfluencerAds->value);
                });
            }])
            ->get();

        foreach ($creators as $creator) {
            $totalVisits = $creator->creatorSnapshots->sum('link_visits');
            $totalSales = $creator->creatorSnapshots->sum('sales_total');
            $totalRevenue = $creator->creatorSnapshots->sum('revenue_total');
            $totalNewCustomers = $creator->creatorSnapshots->sum('new_customers');

            fputcsv($handle, [
                $creator->handle,
                $creator->platform,
                $totalVisits,
                $totalSales,
                $totalRevenue,
                $totalNewCustomers,
            ], ';');
        }
    }
}
