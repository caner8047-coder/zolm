<?php

namespace App\Services\WhatsApp;

use App\Models\WaCampaign;
use App\Models\WaCampaignDailyMetric;
use App\Models\WaCampaignAudience;
use App\Models\MarketplaceStore;
use Carbon\Carbon;

class ReportingService
{
    /**
     * Kampanya günlük metriklerini hesapla
     */
    public function calculateCampaignMetrics(WaCampaign $campaign): void
    {
        $audiences = $campaign->audiences();

        $metrics = [
            'recipients_queued' => $audiences->where('eligibility_status', 'queued')->count(),
            'recipients_sent' => $audiences->where('eligibility_status', 'queued')->whereNotNull('sent_at')->count(),
            'recipients_delivered' => $audiences->where('eligibility_status', 'sent')->count(),
            'recipients_read' => $audiences->where('eligibility_status', 'sent')->whereNotNull('clicked_at')->count(),
            'recipients_clicked' => $audiences->whereNotNull('clicked_at')->count(),
            'recipients_converted' => $audiences->where('eligibility_status', 'converted')->count(),
            'recipients_skipped' => $audiences->where('eligibility_status', 'skipped')->count(),
            'recipients_failed' => $audiences->where('eligibility_status', 'failed')->count(),
            'coupons_used' => $audiences->whereNotNull('coupon_id')->count(),
        ];

        WaCampaignDailyMetric::updateOrCreate(
            ['campaign_id' => $campaign->id, 'metric_date' => today()],
            $metrics,
        );
    }

    /**
     * Kampanya detay raporu
     */
    public function getCampaignReport(WaCampaign $campaign): array
    {
        $audiences = $campaign->audiences();

        $statusCounts = $audiences->select('eligibility_status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('eligibility_status')
            ->pluck('count', 'eligibility_status')
            ->toArray();

        $dailyMetrics = WaCampaignDailyMetric::where('campaign_id', $campaign->id)
            ->orderBy('metric_date')
            ->get();

        return [
            'campaign' => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'status' => $campaign->status,
                'started_at' => $campaign->started_at?->toDateTimeString(),
                'completed_at' => $campaign->completed_at?->toDateTimeString(),
            ],
            'audience_summary' => [
                'total' => $campaign->total_recipients,
                'sent' => $campaign->total_sent,
                'delivered' => $campaign->total_delivered,
                'read' => $campaign->total_read,
                'clicked' => $campaign->total_clicked,
                'converted' => $campaign->total_converted,
                'revenue' => $campaign->total_revenue,
                'by_status' => $statusCounts,
            ],
            'daily_metrics' => $dailyMetrics->toArray(),
        ];
    }

    /**
     * Kanal bazlı rapor
     */
    public function getChannelReport(int $storeId, string $channel, int $days = 30): array
    {
        $analyticsService = app(AnalyticsService::class);
        $metrics = $analyticsService->getRecentMetrics($storeId, $days, $channel);

        return [
            'store_id' => $storeId,
            'channel' => $channel,
            'period_days' => $days,
            'total_sent' => array_sum(array_column($metrics, 'messages_sent')),
            'total_delivered' => array_sum(array_column($metrics, 'messages_delivered')),
            'total_read' => array_sum(array_column($metrics, 'messages_read')),
            'total_failed' => array_sum(array_column($metrics, 'messages_failed')),
            'total_revenue' => array_sum(array_column($metrics, 'revenue_attributed')),
            'total_clicks' => array_sum(array_column($metrics, 'clicks')),
            'total_orders' => array_sum(array_column($metrics, 'orders_attributed')),
            'daily_breakdown' => $metrics,
        ];
    }

    /**
     * Dönüşüm funnel raporu
     */
    public function getConversionFunnel(int $storeId, int $days = 30): array
    {
        $analyticsService = app(AnalyticsService::class);
        $metrics = $analyticsService->getRecentMetrics($storeId, $days);

        return [
            'queued' => array_sum(array_column($metrics, 'messages_queued')),
            'sent' => array_sum(array_column($metrics, 'messages_sent')),
            'delivered' => array_sum(array_column($metrics, 'messages_delivered')),
            'read' => array_sum(array_column($metrics, 'messages_read')),
            'clicked' => array_sum(array_column($metrics, 'clicks')),
            'coupon_used' => array_sum(array_column($metrics, 'coupon_used')),
            'orders' => array_sum(array_column($metrics, 'orders_attributed')),
            'revenue' => array_sum(array_column($metrics, 'revenue_attributed')),
        ];
    }
}
