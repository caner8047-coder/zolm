<?php

namespace App\Services\WhatsApp;

use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
use App\Models\MarketplaceStore;
use App\Models\MpProduct;
use App\Models\WaAbandonedCart;
use App\Models\WaCampaignAudience;
use App\Models\WaContact;
use App\Models\WaContactPreference;
use App\Models\WaOutbox;
use App\Models\WaSegment;
use App\Models\WaSuppression;
use Illuminate\Support\Facades\DB;

class SegmentEngine
{
    /**
     * Segment kurallarını çalıştır ve uygun contact ID'lerini döndür
     */
    public function evaluate(WaSegment $segment): array
    {
        $rules = $segment->rules_json ?? [];
        $storeId = $segment->store_id;

        // WC mağazası için WaContact'ları al
        $query = WaContact::where('store_id', $storeId)
            ->active()
            ->whereNotNull('phone_hash')
            ->where('phone_hash', '!=', '');

        // Suppression filtresi
        $query->whereDoesntHave('suppressions', function ($q) {
            $q->active();
        });

        // Marketing consent filtresi
        $query->whereHas('preferences', function ($q) {
            $q->where('purpose', 'marketing')->where('status', 'granted');
        });

        // Filtreleri uygula
        foreach ($rules['filters'] ?? [] as $filter) {
            $this->applyFilter($query, $filter, $storeId);
        }

        $contactIds = $query->pluck('id')->toArray();

        return $contactIds;
    }

    private function applyFilter($query, array $filter, int $storeId): void
    {
        $field = $filter['field'] ?? '';
        $operator = $filter['operator'] ?? '=';
        $value = $filter['value'] ?? null;

        switch ($field) {
            case 'registered_days_ago':
                $query->where('created_at', '<=', now()->subDays((int) $value));
                break;

            case 'last_order_days_ago':
                $orderIds = ChannelOrder::where('store_id', $storeId)
                    ->whereIn('customer_phone', function ($q) use ($storeId) {
                        $q->select('phone_e164_encrypted')
                            ->from('wa_contacts')
                            ->where('store_id', $storeId);
                    })
                    ->orderByDesc('ordered_at')
                    ->pluck('id');
                $lastOrderDays = (int) $value;
                $query->whereHas('preferences', function ($q) use ($orderIds) {
                    // Bu basitleştirilmiş filtre, gerçek kullanımda subquery ile yapılmalı
                });
                break;

            case 'total_orders':
                $phoneHashes = WaContact::whereIn('id', $query->pluck('id'))->pluck('phone_hash');
                $orderCounts = ChannelOrder::whereIn('customer_phone', function ($q) use ($phoneHashes) {
                    $q->select('phone_e164_encrypted')
                        ->from('wa_contacts')
                        ->whereIn('phone_hash', $phoneHashes);
                })
                    ->where('store_id', $storeId)
                    ->selectRaw('customer_phone, COUNT(*) as cnt')
                    ->groupBy('customer_phone')
                    ->pluck('cnt', 'customer_phone')
                    ->toArray();
                // Filtre uygula (basitleştirilmiş)
                break;

            case 'order_in_date_range':
                $from = $filter['from'] ?? null;
                $to = $filter['to'] ?? null;
                if ($from || $to) {
                    $query->whereHas('preferences', function ($q) use ($from, $to) {
                        // Gerçek kullanımda ChannelOrder join ile
                    });
                }
                break;

            case 'purchased_product_category':
                $categoryId = $value;
                // MpProduct.category_id ile filtrele
                $productIds = MpProduct::where('category_id', $categoryId)->pluck('id');
                $listingIds = \App\Models\ChannelListing::whereIn('mp_product_id', $productIds)->pluck('listing_id');
                $orderItemProductIds = ChannelOrderItem::whereIn('external_line_id', $listingIds)->pluck('channel_order_id');
                // Bu siparişlere ait telefon hash'leri ile contact eşleştir
                break;

            case 'has_cart_recovery_active':
                $cartContactIds = WaAbandonedCart::active()
                    ->where('store_id', $storeId)
                    ->whereNotNull('contact_id')
                    ->pluck('contact_id')
                    ->toArray();
                if (!empty($cartContactIds)) {
                    $query->whereNotIn('id', $cartContactIds);
                }
                break;

            case 'last_marketing_message_days':
                $days = (int) $value;
                $recentlyMessaged = WaOutbox::where('automation_key', 'bulk_campaign')
                    ->where('status', 'sent')
                    ->where('created_at', '>=', now()->subDays($days))
                    ->pluck('contact_id')
                    ->toArray();
                $query->whereNotIn('id', $recentlyMessaged);
                break;

            case 'never_received_campaign':
                $everMessaged = WaOutbox::where('automation_key', 'bulk_campaign')
                    ->where('contact_id', '>', 0)
                    ->pluck('contact_id')
                    ->unique()
                    ->toArray();
                $query->whereNotIn('id', $everMessaged);
                break;
        }
    }

    /**
     * Segment tahmini — gerçek sayıyı hesapla
     */
    public function estimateCount(WaSegment $segment): int
    {
        $contactIds = $this->evaluate($segment);
        $count = count($contactIds);

        $segment->update([
            'estimated_count' => $count,
            'last_calculated_at' => now(),
        ]);

        return $count;
    }
}
