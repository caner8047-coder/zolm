<?php

namespace App\Services\WhatsApp;

use App\Models\ChannelOrder;
use App\Models\WaAttributionEvent;
use App\Models\WaCampaign;
use App\Models\WaCampaignAudience;
use App\Models\WaContact;
use App\Models\WaCoupon;
use App\Models\WaOutbox;

class AttributionService
{
    /**
     * Tıklama olayını kaydet
     */
    public function recordClick(WaCampaignAudience $audience, ?int $outboxId = null): void
    {
        WaAttributionEvent::create([
            'contact_id' => $audience->contact_id,
            'store_id' => $audience->store_id,
            'campaign_id' => $audience->campaign_id,
            'audience_id' => $audience->id,
            'message_delivery_id' => $outboxId,
            'event_type' => 'click',
            'attribution_window' => 'click',
            'attributed_at' => now(),
        ]);

        $audience->update(['clicked_at' => now()]);
    }

    /**
     * Kupon kullanımı olayını kaydet
     */
    public function recordCouponUsed(WaCoupon $coupon, ChannelOrder $order): void
    {
        $audience = WaCampaignAudience::where('coupon_id', $coupon->id)->first();

        WaAttributionEvent::create([
            'contact_id' => $coupon->contact_id,
            'store_id' => $coupon->store_id,
            'campaign_id' => $audience?->campaign_id,
            'audience_id' => $audience?->id,
            'event_type' => 'coupon_used',
            'order_id' => $order->id,
            'revenue' => $order->raw_payload['total'] ?? 0,
            'attribution_window' => 'coupon',
            'attributed_at' => now(),
        ]);

        if ($audience) {
            $audience->update([
                'eligibility_status' => 'converted',
                'converted_at' => now(),
            ]);
        }
    }

    /**
     * Sipariş oluştuğunda atfı kontrol et
     */
    public function attributeOrder(ChannelOrder $order, WaContact $contact): void
    {
        // Click-through attribution
        $lastClick = WaCampaignAudience::where('contact_id', $contact->id)
            ->whereNotNull('clicked_at')
            ->where('clicked_at', '>=', now()->subDays(
                config('whatsapp.attribution.click_days', 7)
            ))
            ->orderByDesc('clicked_at')
            ->first();

        if ($lastClick) {
            WaAttributionEvent::create([
                'contact_id' => $contact->id,
                'store_id' => $order->store_id,
                'campaign_id' => $lastClick->campaign_id,
                'audience_id' => $lastClick->id,
                'event_type' => 'order_created',
                'order_id' => $order->id,
                'revenue' => $order->raw_payload['total'] ?? 0,
                'attribution_window' => 'click',
                'attributed_at' => now(),
            ]);

            $lastClick->update(['converted_at' => now()]);

            // Kampanya metriklerini güncelle
            if ($lastClick->campaign_id) {
                $this->updateCampaignMetrics($lastClick->campaign_id, $order);
            }
        }
    }

    /**
     * Atıf raporu
     */
    public function getAttributionReport(int $storeId, int $days = 30): array
    {
        $events = WaAttributionEvent::where('store_id', $storeId)
            ->where('attributed_at', '>=', now()->subDays($days))
            ->get();

        $byType = $events->groupBy('event_type')->map(fn ($e) => [
            'count' => $e->count(),
            'revenue' => $e->sum('revenue'),
        ])->toArray();

        $byWindow = $events->groupBy('attribution_window')->map(fn ($e) => [
            'count' => $e->count(),
            'revenue' => $e->sum('revenue'),
        ])->toArray();

        $byCampaign = $events->where('campaign_id')
            ->groupBy('campaign_id')
            ->map(fn ($e) => [
                'count' => $e->count(),
                'revenue' => $e->sum('revenue'),
            ])->toArray();

        return [
            'total_events' => $events->count(),
            'total_revenue' => $events->sum('revenue'),
            'by_event_type' => $byType,
            'by_attribution_window' => $byWindow,
            'by_campaign' => $byCampaign,
        ];
    }

    private function updateCampaignMetrics(int $campaignId, ChannelOrder $order): void
    {
        $campaign = WaCampaign::find($campaignId);
        if (!$campaign) {
            return;
        }

        $campaign->increment('total_converted');
        $campaign->increment('total_revenue', $order->raw_payload['total'] ?? 0);
    }
}
