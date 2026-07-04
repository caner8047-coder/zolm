<?php

namespace App\Services\WhatsApp;

use App\Models\ChannelOrder;
use App\Models\WaContact;
use App\Models\WaContactPreference;
use App\Models\WaConversation;
use App\Models\WaCustomerProfile;
use App\Models\WaOutbox;
use App\Models\WaCoupon;
use App\Models\WaAbandonedCart;
use App\Models\WaStockWaitlist;
use App\Models\MarketplaceStore;

class CustomerProfileService
{
    /**
     * Müşteri profilini hesapla ve güncelle
     */
    public function buildProfile(WaContact $contact): WaCustomerProfile
    {
        $storeId = $contact->store_id;

        // Sipariş istatistikleri
        $orders = ChannelOrder::where('store_id', $storeId)
            ->whereRaw('LOWER(REPLACE(REPLACE(REPLACE(customer_phone, " ", ""), "-", ""), ".", "")) = ?', [$contact->phone_hash])
            ->get();

        $totalOrders = $orders->count();
        $totalRevenue = $orders->sum('raw_payload->total') ?? 0;
        $avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;
        $firstOrderAt = $orders->min('ordered_at');
        $lastOrderAt = $orders->max('ordered_at');

        // Mesaj istatistikleri
        $messageStats = WaOutbox::where('contact_id', $contact->id)
            ->selectRaw('
                COUNT(*) as total_sent,
                SUM(CASE WHEN status IN ("sent","delivered","read") THEN 1 ELSE 0 END) as total_delivered,
                SUM(CASE WHEN status = "read" THEN 1 ELSE 0 END) as total_read
            ')
            ->first();

        // Tıklama ve kupon
        $totalClicks = \App\Models\WaCampaignAudience::where('contact_id', $contact->id)
            ->whereNotNull('clicked_at')
            ->count();

        $totalCouponsUsed = WaCoupon::where('contact_id', $contact->id)
            ->whereNotNull('used_at')
            ->count();

        $lastMessageAt = WaOutbox::where('contact_id', $contact->id)
            ->max('created_at');

        $lastClickAt = \App\Models\WaCampaignAudience::where('contact_id', $contact->id)
            ->whereNotNull('clicked_at')
            ->max('clicked_at');

        // Engagement score
        $engagementScore = $this->calculateEngagementScore(
            $totalOrders, $totalRevenue, $messageStats->total_read ?? 0,
            $totalClicks, $totalCouponsUsed
        );

        // Segment etiketleri
        $segmentTags = $this->calculateSegmentTags($contact, $orders, $totalRevenue);

        return WaCustomerProfile::updateOrCreate(
            ['contact_id' => $contact->id, 'store_id' => $storeId],
            [
                'total_orders' => $totalOrders,
                'total_revenue' => $totalRevenue,
                'avg_order_value' => $avgOrderValue,
                'total_messages_sent' => $messageStats->total_sent ?? 0,
                'total_messages_delivered' => $messageStats->total_delivered ?? 0,
                'total_messages_read' => $messageStats->total_read ?? 0,
                'total_clicks' => $totalClicks,
                'total_coupons_used' => $totalCouponsUsed,
                'first_order_at' => $firstOrderAt,
                'last_order_at' => $lastOrderAt,
                'last_message_at' => $lastMessageAt,
                'last_click_at' => $lastClickAt,
                'engagement_score' => $engagementScore,
                'segment_tags' => $segmentTags,
            ]
        );
    }

    /**
     * Müşteri profil sayfası verisi
     */
    public function getProfilePageData(int $contactId, int $storeId): array
    {
        $contact = WaContact::with('preferences')->find($contactId);
        if (!$contact) {
            return ['error' => 'Müşteri bulunamadı'];
        }

        $profile = WaCustomerProfile::where('contact_id', $contactId)
            ->where('store_id', $storeId)
            ->first();

        if (!$profile) {
            $profile = $this->buildProfile($contact);
        }

        // Son mesajlar
        $recentMessages = WaOutbox::where('contact_id', $contactId)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'automation_key', 'template_name', 'status', 'created_at']);

        // Kuponlar
        $coupons = WaCoupon::where('contact_id', $contactId)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['code', 'discount_type', 'discount_value', 'expires_at', 'used_at']);

        // Aktif sepet
        $activeCart = WaAbandonedCart::where('contact_id', $contactId)
            ->active()
            ->first();

        // Stok bildirimleri
        $stockAlerts = WaStockWaitlist::where('contact_id', $contactId)
            ->orderByDesc('requested_at')
            ->limit(5)
            ->get(['wc_product_id', 'status', 'requested_at', 'notified_at']);

        // İzinler
        $preferences = $contact->preferences->map(fn ($p) => [
            'purpose' => $p->purpose,
            'status' => $p->status,
        ])->toArray();

        return [
            'contact' => [
                'id' => $contact->id,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'status' => $contact->status,
                'last_seen_at' => $contact->last_seen_at,
            ],
            'profile' => $profile->toArray(),
            'preferences' => $preferences,
            'recent_messages' => $recentMessages->toArray(),
            'coupons' => $coupons->toArray(),
            'active_cart' => $activeCart ? ['status' => $activeCart->status, 'total' => $activeCart->cart_total_snapshot] : null,
            'stock_alerts' => $stockAlerts->toArray(),
        ];
    }

    private function calculateEngagementScore(int $orders, float $revenue, int $reads, int $clicks, int $coupons): string
    {
        $score = 0;

        if ($orders > 5) $score += 3;
        elseif ($orders > 0) $score += 1;

        if ($revenue > 10000) $score += 3;
        elseif ($revenue > 2000) $score += 1;

        if ($reads > 10) $score += 2;
        elseif ($reads > 0) $score += 1;

        if ($clicks > 5) $score += 2;
        elseif ($clicks > 0) $score += 1;

        if ($coupons > 0) $score += 1;

        return match (true) {
            $score >= 6 => 'high',
            $score >= 3 => 'medium',
            default => 'low',
        };
    }

    private function calculateSegmentTags(WaContact $contact, $orders, float $totalRevenue): array
    {
        $tags = [];

        if ($orders->isEmpty()) {
            $tags[] = 'no_orders';
        } else {
            $tags[] = 'has_orders';

            if ($orders->count() >= 3) {
                $tags[] = 'repeat_buyer';
            }

            if ($totalRevenue > 5000) {
                $tags[] = 'high_value';
            }
        }

        $consentMarketing = WaContactPreference::where('contact_id', $contact->id)
            ->where('purpose', 'marketing')
            ->where('status', 'granted')
            ->exists();

        if ($consentMarketing) {
            $tags[] = 'marketing_opted_in';
        }

        $hasActiveCart = WaAbandonedCart::where('contact_id', $contact->id)->active()->exists();
        if ($hasActiveCart) {
            $tags[] = 'has_abandoned_cart';
        }

        return $tags;
    }
}
