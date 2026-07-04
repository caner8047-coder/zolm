<?php

namespace App\Services\WhatsApp;

use App\Models\WaDailyMetric;
use App\Models\WaOutbox;
use App\Models\WaMessageDelivery;
use App\Models\WaAbandonedCart;
use App\Models\WaStockWaitlist;
use App\Models\WaAiRun;
use App\Models\WaHandoff;
use App\Models\WaCampaign;
use App\Models\WaCampaignAudience;
use App\Models\WaCoupon;
use App\Models\SupportConversation;
use App\Models\MarketplaceStore;
use Illuminate\Support\Carbon;

class AnalyticsService
{
    /**
     * Günlük metrikleri hesapla ve kaydet
     */
    public function calculateDailyMetrics(?int $storeId = null, string $channel = 'all'): void
    {
        $today = today();
        $stores = $storeId
            ? MarketplaceStore::where('id', $storeId)->get()
            : MarketplaceStore::where('marketplace', 'woocommerce')->where('is_active', true)->get();

        foreach ($stores as $store) {
            $metrics = $this->collectMetrics($store, $today, $channel);

            WaDailyMetric::updateOrCreate(
                ['store_id' => $store->id, 'metric_date' => $today, 'channel' => $channel],
                $metrics,
            );
        }
    }

    /**
     * Son X günün metriklerini getir
     */
    public function getRecentMetrics(int $storeId, int $days = 7, string $channel = 'all'): array
    {
        return WaDailyMetric::where('store_id', $storeId)
            ->where('channel', $channel)
            ->where('metric_date', '>=', now()->subDays($days))
            ->orderBy('metric_date')
            ->get()
            ->toArray();
    }

    /**
     * Genel bakış özeti
     */
    public function getOverviewSummary(int $storeId): array
    {
        $today = today();
        $last7Days = now()->subDays(7);

        $todayMetrics = WaDailyMetric::where('store_id', $storeId)
            ->where('metric_date', $today)
            ->where('channel', 'all')
            ->first();

        $weeklyMetrics = WaDailyMetric::where('store_id', $storeId)
            ->where('metric_date', '>=', $last7Days)
            ->where('channel', 'all')
            ->get();

        $activeCampaigns = WaCampaign::where('store_id', $storeId)
            ->whereIn('status', ['running', 'scheduled'])
            ->count();

        $openConversations = SupportConversation::where('store_id', $storeId)
            ->where('status', 'open')
            ->count();

        return [
            'today' => [
                'sent' => $todayMetrics->messages_sent ?? 0,
                'delivered' => $todayMetrics->messages_delivered ?? 0,
                'read' => $todayMetrics->messages_read ?? 0,
                'failed' => $todayMetrics->messages_failed ?? 0,
                'revenue' => $todayMetrics->revenue_attributed ?? 0,
                'clicks' => $todayMetrics->clicks ?? 0,
                'orders' => $todayMetrics->orders_attributed ?? 0,
            ],
            'weekly' => [
                'total_sent' => $weeklyMetrics->sum('messages_sent'),
                'total_delivered' => $weeklyMetrics->sum('messages_delivered'),
                'total_read' => $weeklyMetrics->sum('messages_read'),
                'total_failed' => $weeklyMetrics->sum('messages_failed'),
                'total_revenue' => $weeklyMetrics->sum('revenue_attributed'),
                'total_clicks' => $weeklyMetrics->sum('clicks'),
                'total_orders' => $weeklyMetrics->sum('orders_attributed'),
                'avg_delivery_rate' => $this->calculateRate($weeklyMetrics->sum('messages_delivered'), $weeklyMetrics->sum('messages_sent')),
                'avg_read_rate' => $this->calculateRate($weeklyMetrics->sum('messages_read'), $weeklyMetrics->sum('messages_delivered')),
            ],
            'active_campaigns' => $activeCampaigns,
            'open_conversations' => $openConversations,
        ];
    }

    private function collectMetrics(MarketplaceStore $store, Carbon $date, string $channel): array
    {
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        $outboxQuery = WaOutbox::where('store_id', $store->id)
            ->whereBetween('created_at', [$startOfDay, $endOfDay]);

        $sent = (clone $outboxQuery)->where('status', 'sent')->count();
        $delivered = (clone $outboxQuery)->where('status', 'delivered')->count();
        $read = (clone $outboxQuery)->where('status', 'read')->count();
        $failed = (clone $outboxQuery)->where('status', 'failed')->count();
        $queued = (clone $outboxQuery)->where('status', 'queued')->count();

        $clicks = WaCampaignAudience::where('store_id', $store->id)
            ->where('clicked_at', '>=', $startOfDay)
            ->where('clicked_at', '<=', $endOfDay)
            ->count();

        $orders = WaCampaignAudience::where('store_id', $store->id)
            ->where('converted_at', '>=', $startOfDay)
            ->where('converted_at', '<=', $endOfDay)
            ->count();

        $revenue = WaCampaignAudience::where('store_id', $store->id)
            ->where('converted_at', '>=', $startOfDay)
            ->where('converted_at', '<=', $endOfDay)
            ->count(); // Basitleştirilmiş

        $couponsUsed = WaCoupon::where('store_id', $store->id)
            ->where('used_at', '>=', $startOfDay)
            ->where('used_at', '<=', $endOfDay)
            ->count();

        $shippingNotifs = WaOutbox::where('store_id', $store->id)
            ->where('automation_key', 'shipping_notification')
            ->where('created_at', '>=', $startOfDay)
            ->where('created_at', '<=', $endOfDay)
            ->where('status', 'sent')
            ->count();

        $orderConf = WaOutbox::where('store_id', $store->id)
            ->where('automation_key', 'order_confirmation')
            ->where('created_at', '>=', $startOfDay)
            ->where('created_at', '<=', $endOfDay)
            ->where('status', 'sent')
            ->count();

        $returnNotifs = WaOutbox::where('store_id', $store->id)
            ->where('automation_key', 'return_notification')
            ->where('created_at', '>=', $startOfDay)
            ->where('created_at', '<=', $endOfDay)
            ->where('status', 'sent')
            ->count();

        $cartRecoverySent = WaOutbox::where('store_id', $store->id)
            ->where('automation_key', 'cart_recovery')
            ->where('created_at', '>=', $startOfDay)
            ->where('created_at', '<=', $endOfDay)
            ->where('status', 'sent')
            ->count();

        $cartRecovered = WaAbandonedCart::where('store_id', $store->id)
            ->where('recovered_at', '>=', $startOfDay)
            ->where('recovered_at', '<=', $endOfDay)
            ->count();

        $stockAlertsSent = WaOutbox::where('store_id', $store->id)
            ->where('automation_key', 'stock_alert')
            ->where('created_at', '>=', $startOfDay)
            ->where('created_at', '<=', $endOfDay)
            ->where('status', 'sent')
            ->count();

        $stockConverted = WaStockWaitlist::where('store_id', $store->id)
            ->where('status', 'converted')
            ->where('notified_at', '>=', $startOfDay)
            ->where('notified_at', '<=', $endOfDay)
            ->count();

        $aiRuns = WaAiRun::where('store_id', $store->id)
            ->where('created_at', '>=', $startOfDay)
            ->where('created_at', '<=', $endOfDay)
            ->count();

        $aiHandoffs = WaHandoff::where('store_id', $store->id)
            ->where('created_at', '>=', $startOfDay)
            ->where('created_at', '<=', $endOfDay)
            ->count();

        $aiAvgResponse = WaAiRun::where('store_id', $store->id)
            ->where('created_at', '>=', $startOfDay)
            ->where('created_at', '<=', $endOfDay)
            ->whereNotNull('response_time_ms')
            ->avg('response_time_ms');

        $convOpened = SupportConversation::where('store_id', $store->id)
            ->where('created_at', '>=', $startOfDay)
            ->where('created_at', '<=', $endOfDay)
            ->count();

        $convResolved = SupportConversation::where('store_id', $store->id)
            ->where('status', 'resolved')
            ->where('resolved_at', '>=', $startOfDay)
            ->where('resolved_at', '<=', $endOfDay)
            ->count();

        return [
            'messages_queued' => $queued,
            'messages_sent' => $sent,
            'messages_delivered' => $delivered,
            'messages_read' => $read,
            'messages_failed' => $failed,
            'clicks' => $clicks,
            'coupon_used' => $couponsUsed,
            'orders_attributed' => $orders,
            'revenue_attributed' => $revenue,
            'shipping_notifications' => $shippingNotifs,
            'order_confirmations' => $orderConf,
            'return_notifications' => $returnNotifs,
            'cart_recovery_sent' => $cartRecoverySent,
            'cart_recovery_recovered' => $cartRecovered,
            'stock_alerts_sent' => $stockAlertsSent,
            'stock_alerts_converted' => $stockConverted,
            'ai_runs' => $aiRuns,
            'ai_handoffs' => $aiHandoffs,
            'avg_response_time_ms' => $aiAvgResponse,
            'support_conversations_opened' => $convOpened,
            'support_conversations_resolved' => $convResolved,
        ];
    }

    private function calculateRate(float $numerator, float $denominator): float
    {
        return $denominator > 0 ? round(($numerator / $denominator) * 100, 1) : 0;
    }
}
