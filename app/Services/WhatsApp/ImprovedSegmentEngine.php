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

/**
 * Geliştirilmiş Segment Motoru.
 * Gelişmiş filtreleme, zaman bazlı segmentasyon ve davranışsal segmentasyon desteği.
 */
class ImprovedSegmentEngine
{
    /**
     * Segment kurallarını çalıştır ve uygun contact ID'lerini döndür
     */
    public function evaluate(WaSegment $segment): array
    {
        $rules = $segment->rules_json ?? [];
        $storeId = $segment->store_id;

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

        // Segment sayısını güncelle
        $segment->update([
            'estimated_count' => count($contactIds),
            'last_calculated_at' => now(),
        ]);

        return $contactIds;
    }

    private function applyFilter($query, array $filter, int $storeId): void
    {
        $field = $filter['field'] ?? '';
        $operator = $filter['operator'] ?? '=';
        $value = $filter['value'] ?? null;

        switch ($field) {
            // ── Müşteri Filtreleri ──
            case 'registered_days_ago':
                $query->where('created_at', '<=', now()->subDays((int) $value));
                break;

            case 'last_seen_days_ago':
                $query->where('last_seen_at', '>=', now()->subDays((int) $value));
                break;

            case 'has_phone':
                if ($value === true) {
                    $query->whereNotNull('phone_hash')->where('phone_hash', '!=', '');
                } else {
                    $query->where(function ($q) {
                        $q->whereNull('phone_hash')->orWhere('phone_hash', '=', '');
                    });
                }
                break;

            // ── Sipariş Filtreleri ──
            case 'total_orders_min':
                $phoneHashes = $this->getPhoneHashesForStore($storeId);
                $qualifyingPhones = $this->getPhonesWithOrderCount($phoneHashes, $storeId, '>=', (int) $value);
                $query->whereIn('phone_hash', $qualifyingPhones);
                break;

            case 'total_orders_max':
                $phoneHashes = $this->getPhoneHashesForStore($storeId);
                $qualifyingPhones = $this->getPhonesWithOrderCount($phoneHashes, $storeId, '<=', (int) $value);
                $query->whereIn('phone_hash', $qualifyingPhones);
                break;

            case 'last_order_days_ago':
                $phoneHashes = $this->getPhoneHashesForStore($storeId);
                $qualifyingPhones = $this->getPhonesWithRecentOrder($phoneHashes, $storeId, (int) $value);
                $query->whereIn('phone_hash', $qualifyingPhones);
                break;

            case 'never_ordered':
                $phoneHashes = $this->getPhoneHashesForStore($storeId);
                $qualifyingPhones = $this->getPhonesWithRecentOrder($phoneHashes, $storeId, 365 * 10);
                $query->whereNotIn('phone_hash', $qualifyingPhones);
                break;

            case 'order_in_date_range':
                $from = $filter['from'] ?? null;
                $to = $filter['to'] ?? null;
                if ($from || $to) {
                    $phoneHashes = $this->getPhoneHashesForStore($storeId);
                    $qualifyingPhones = $this->getPhonesWithOrderInDateRange($phoneHashes, $storeId, $from, $to);
                    $query->whereIn('phone_hash', $qualifyingPhones);
                }
                break;

            case 'total_revenue_min':
                $phoneHashes = $this->getPhoneHashesForStore($storeId);
                $qualifyingPhones = $this->getPhonesWithRevenue($phoneHashes, $storeId, '>=', (float) $value);
                $query->whereIn('phone_hash', $qualifyingPhones);
                break;

            case 'avg_order_value_min':
                $phoneHashes = $this->getPhoneHashesForStore($storeId);
                $qualifyingPhones = $this->getPhonesWithAvgOrderValue($phoneHashes, $storeId, '>=', (float) $value);
                $query->whereIn('phone_hash', $qualifyingPhones);
                break;

            // ── WhatsApp Davranış Filtreleri ──
            case 'has_active_cart':
                $cartContactIds = WaAbandonedCart::active()
                    ->where('store_id', $storeId)
                    ->whereNotNull('contact_id')
                    ->pluck('contact_id')
                    ->toArray();
                $query->whereIn('id', $cartContactIds);
                break;

            case 'no_active_cart':
                $cartContactIds = WaAbandonedCart::active()
                    ->where('store_id', $storeId)
                    ->whereNotNull('contact_id')
                    ->pluck('contact_id')
                    ->toArray();
                $query->whereNotIn('id', $cartContactIds);
                break;

            case 'last_campaign_clicked':
                $clickContactIds = WaCampaignAudience::where('store_id', $storeId)
                    ->whereNotNull('clicked_at')
                    ->where('clicked_at', '>=', now()->subDays((int) $value))
                    ->pluck('contact_id')
                    ->toArray();
                $query->whereIn('id', $clickContactIds);
                break;

            case 'last_campaign_not_clicked':
                $days = (int) $value;
                $recentlyMessaged = WaOutbox::where('automation_key', 'bulk_campaign')
                    ->where('store_id', $storeId)
                    ->where('created_at', '>=', now()->subDays($days))
                    ->pluck('contact_id')
                    ->unique()
                    ->toArray();
                $clicked = WaCampaignAudience::where('store_id', $storeId)
                    ->whereNotNull('clicked_at')
                    ->where('clicked_at', '>=', now()->subDays($days))
                    ->pluck('contact_id')
                    ->toArray();
                $notClicked = array_diff($recentlyMessaged, $clicked);
                $query->whereIn('id', $notClicked);
                break;

            case 'no_message_days':
                $days = (int) $value;
                $recentlyMessaged = WaOutbox::where('store_id', $storeId)
                    ->where('created_at', '>=', now()->subDays($days))
                    ->pluck('contact_id')
                    ->unique()
                    ->toArray();
                $query->whereNotIn('id', $recentlyMessaged);
                break;

            case 'has_stock_waitlist':
                $waitlistContactIds = \App\Models\WaStockWaitlist::where('store_id', $storeId)
                    ->where('status', 'waiting')
                    ->whereNotNull('contact_id')
                    ->pluck('contact_id')
                    ->toArray();
                $query->whereIn('id', $waitlistContactIds);
                break;

            default:
                Log::warning("Bilinmeyen segment filtresi: {$field}");
                break;
        }
    }

    /**
     * Mağaza için telefon hash'lerini getir
     */
    private function getPhoneHashesForStore(int $storeId): array
    {
        return WaContact::where('store_id', $storeId)
            ->active()
            ->pluck('phone_hash')
            ->toArray();
    }

    /**
     * Belirli sayıda siparişi olan müşterilerin telefon hash'lerini getir
     */
    private function getPhonesWithOrderCount(array $phoneHashes, int $storeId, string $operator, int $count): array
    {
        if (empty($phoneHashes)) {
            return [];
        }

        return ChannelOrder::where('store_id', $storeId)
            ->whereIn('customer_phone', function ($q) use ($phoneHashes) {
                $q->select('phone_e164_encrypted')
                    ->from('wa_contacts')
                    ->whereIn('phone_hash', $phoneHashes);
            })
            ->selectRaw('customer_phone, COUNT(*) as cnt')
            ->groupBy('customer_phone')
            ->having('cnt', $operator, $count)
            ->pluck('customer_phone')
            ->toArray();
    }

    /**
     * Son X günde siparişi olan müşterilerin telefon hash'lerini getir
     */
    private function getPhonesWithRecentOrder(array $phoneHashes, int $storeId, int $days): array
    {
        if (empty($phoneHashes)) {
            return [];
        }

        return ChannelOrder::where('store_id', $storeId)
            ->whereIn('customer_phone', function ($q) use ($phoneHashes) {
                $q->select('phone_e164_encrypted')
                    ->from('wa_contacts')
                    ->whereIn('phone_hash', $phoneHashes);
            })
            ->where('ordered_at', '>=', now()->subDays($days))
            ->pluck('customer_phone')
            ->unique()
            ->toArray();
    }

    /**
     * Belirli tarih aralığında siparişi olan müşterilerin telefon hash'lerini getir
     */
    private function getPhonesWithOrderInDateRange(array $phoneHashes, int $storeId, ?string $from, ?string $to): array
    {
        if (empty($phoneHashes)) {
            return [];
        }

        $query = ChannelOrder::where('store_id', $storeId)
            ->whereIn('customer_phone', function ($q) use ($phoneHashes) {
                $q->select('phone_e164_encrypted')
                    ->from('wa_contacts')
                    ->whereIn('phone_hash', $phoneHashes);
            });

        if ($from) {
            $query->where('ordered_at', '>=', $from);
        }
        if ($to) {
            $query->where('ordered_at', '<=', $to);
        }

        return $query->pluck('customer_phone')->unique()->toArray();
    }

    /**
     * Belirli gelirde olan müşterilerin telefon hash'lerini getir
     */
    private function getPhonesWithRevenue(array $phoneHashes, int $storeId, string $operator, float $revenue): array
    {
        if (empty($phoneHashes)) {
            return [];
        }

        return ChannelOrder::where('store_id', $storeId)
            ->whereIn('customer_phone', function ($q) use ($phoneHashes) {
                $q->select('phone_e164_encrypted')
                    ->from('wa_contacts')
                    ->whereIn('phone_hash', $phoneHashes);
            })
            ->selectRaw('customer_phone, SUM(CAST(JSON_EXTRACT(raw_payload, "$.total") AS DECIMAL(10,2))) as total_revenue')
            ->groupBy('customer_phone')
            ->having('total_revenue', $operator, $revenue)
            ->pluck('customer_phone')
            ->toArray();
    }

    /**
     * Belirli ortalama sipariş tutarına olan müşterilerin telefon hash'lerini getir
     */
    private function getPhonesWithAvgOrderValue(array $phoneHashes, int $storeId, string $operator, float $avgValue): array
    {
        if (empty($phoneHashes)) {
            return [];
        }

        return ChannelOrder::where('store_id', $storeId)
            ->whereIn('customer_phone', function ($q) use ($phoneHashes) {
                $q->select('phone_e164_encrypted')
                    ->from('wa_contacts')
                    ->whereIn('phone_hash', $phoneHashes);
            })
            ->selectRaw('customer_phone, AVG(CAST(JSON_EXTRACT(raw_payload, "$.total") AS DECIMAL(10,2))) as avg_value')
            ->groupBy('customer_phone')
            ->having('avg_value', $operator, $avgValue)
            ->pluck('customer_phone')
            ->toArray();
    }
}
