<?php

namespace App\Services\Support;

use App\Models\SupportConversation;
use App\Models\MpOrder;
use App\Models\SlaTrack;
use App\Models\User;
use App\Services\Support\Security\PiiRedactor;
use App\Models\Shipment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Dalga T — Seçili conversation için store-scoped, PII-masked müşteri özeti üretir.
 *
 * Tasarım kararları:
 * - Veri yoksa null döner; uydurma/fabrication yapılmaz.
 * - Tüm PII alanları maskelenir.
 * - Cross-store sızma engellenir.
 * - AI context için yalnızca store-scoped, redacted veri girer.
 */
class CustomerCareCustomerSummaryService
{
    private PiiRedactor $piiRedactor;
    private CustomerCareIdentityResolver $identityResolver;

    public function __construct(PiiRedactor $piiRedactor, CustomerCareIdentityResolver $identityResolver)
    {
        $this->piiRedactor = $piiRedactor;
        $this->identityResolver = $identityResolver;
    }

    /**
     * Conversation bazlı müşteri özeti üretir.
     *
     * @return array{
     *   identity: array|null,
     *   recent_orders: array,
     *   recent_conversations: array,
     *   open_sla_count: int,
     *   has_recent_return_signal: bool,
     *   ai_safe_context: string,
     *   data_available: bool
     * }|null
     */
    public function getSummary(SupportConversation $conversation, ?User $actor = null): ?array
    {
        $storeId = (int)$conversation->store_id;

        // Store-bound güvenlik: conversation başka store'a aitso null dön
        if (!$storeId) {
            return null;
        }
        $actor = $actor ?? Auth::user() ?? TenantContext::getSystemActor();
        TenantContext::enforceConversationAccess((int) $conversation->id, $actor);

        // Kimlik çözme
        $identity = $this->identityResolver->resolveForConversation($conversation);

        // Son siparişler — kesinlikle store-scoped, müşteri ID'si varsa filtrele
        $recentOrders = $this->getRecentOrders($storeId, $conversation);

        // Son konuşmalar — aynı kanaldan, aynı external_customer_id
        $recentConversations = $this->getRecentConversations($conversation);

        // Açık SLA sayısı
        $openSlaCount = $this->getOpenSlaCount($conversation);

        // İade/iptal sinyali
        $hasReturnSignal = $this->detectReturnSignal($storeId, $conversation);

        // AI için güvenli bağlam metni
        $aiSafeContext = $this->buildAiSafeContext($recentOrders, $openSlaCount, $hasReturnSignal);

        $dataAvailable = !empty($recentOrders) || !empty($recentConversations) || $openSlaCount > 0;

        return [
            'identity' => $identity,
            'recent_orders' => $recentOrders,
            'recent_conversations' => $recentConversations,
            'open_sla_count' => $openSlaCount,
            'has_recent_return_signal' => $hasReturnSignal,
            'ai_safe_context' => $aiSafeContext,
            'data_available' => $dataAvailable,
        ];
    }

    /**
     * AI context için yalnızca store-scoped, redacted özet.
     * getSummary() null ise boş string döner; uydurma yapılmaz.
     */
    public function buildAiContextString(SupportConversation $conversation, ?User $actor = null): string
    {
        $summary = $this->getSummary($conversation, $actor);

        if (!$summary || !$summary['data_available']) {
            return ''; // Veri yoksa boş — uydurma yapmıyoruz
        }

        return $summary['ai_safe_context'];
    }

    private function getRecentOrders(int $storeId, SupportConversation $conversation): array
    {
        $identity = $this->identityResolver->resolveForConversation($conversation);
        $reference = $conversation->source_reference_json ?? [];
        $verifiedOrderNumbers = collect([
            $reference['verified_order_number'] ?? null,
            $reference['order_number'] ?? null,
            ...((array) ($reference['verified_order_numbers'] ?? [])),
        ])->filter(fn ($value) => is_scalar($value) && trim((string) $value) !== '')
            ->map(fn ($value) => trim((string) $value))
            ->unique()
            ->values();

        $identityKey = trim((string) ($identity['identity_key'] ?? ''));
        if ($identityKey === '' && $verifiedOrderNumbers->isEmpty()) {
            return [];
        }

        // JSON kolon yapıları kanal bazında değişebildiği için önce store ile daraltıp
        // ardından yalnız deterministik kimlik/order referansı eşleşen kayıtları kabul et.
        $orders = MpOrder::where('store_id', $storeId)
            ->orderBy('order_date', 'desc')
            ->limit(100)
            ->get()
            ->filter(function (MpOrder $order) use ($identityKey, $verifiedOrderNumbers): bool {
                if ($verifiedOrderNumbers->contains((string) $order->order_number)) {
                    return true;
                }

                if ($identityKey === '') {
                    return false;
                }

                $raw = $order->raw_data ?? [];
                if (!is_array($raw)) {
                    return false;
                }

                $candidateKeys = [
                    data_get($raw, 'customer_external_id'),
                    data_get($raw, 'customerId'),
                    data_get($raw, 'customer.id'),
                    data_get($raw, 'customer_id'),
                    data_get($raw, 'buyerId'),
                    data_get($raw, 'buyer.id'),
                    data_get($raw, 'buyer_id'),
                ];

                return collect($candidateKeys)
                    ->filter(fn ($value) => is_scalar($value))
                    ->contains(fn ($value) => hash_equals($identityKey, trim((string) $value)));
            })
            ->take(3)
            ->values();

        if ($orders->isEmpty()) {
            return []; // Uydurma yok
        }

        return $orders->map(function (MpOrder $order) use ($storeId) {
            $shipment = Shipment::where('store_id', $storeId)
                ->where('order_number', $order->order_number)
                ->latest('last_tracked_at')->latest('id')->first();
            $raw = is_array($order->raw_data) ? $order->raw_data : [];
            $carrier = $shipment?->carrier_name ?: $order->cargo_company ?: data_get($raw, 'cargoCompany') ?: data_get($raw, 'shipment.carrier');
            $tracking = $shipment?->tracking_number ?: data_get($raw, 'cargoTrackingNumber') ?: data_get($raw, 'cargo_tracking_number') ?: data_get($raw, 'shipment.tracking_number');
            $shipmentFreshness = $shipment?->last_tracked_at ?: $shipment?->updated_at;
            $deliveryKind = null;
            $deliveryDate = null;
            if ($order->delivery_date && preg_match('/teslim|delivered/i', (string) $order->status)) {
                $deliveryKind = 'actual';
                $deliveryDate = $order->delivery_date->toDateString();
            } else {
                $estimated = data_get($raw, 'estimatedDeliveryDate') ?: data_get($raw, 'estimated_delivery_date') ?: data_get($raw, 'shipment.estimated_delivery_date');
                if ($estimated) {
                    try {
                        $deliveryKind = 'estimated';
                        $deliveryDate = Carbon::parse($estimated)->toDateString();
                    } catch (\Throwable) {
                        $deliveryDate = null;
                    }
                }
            }

            return [
                'source_record_id' => $order->id,
                'order_number' => $this->piiRedactor->maskPii($order->order_number ?? ''),
                'date' => $order->order_date ? $order->order_date->format('Y-m-d') : null,
                'status' => $shipment?->status_label ?: $shipment?->status ?: $order->status,
                'product_name' => $order->product_name,
                'quantity' => $order->quantity,
                'freshness_at' => ($order->updated_at ?? $order->order_date)?->toIso8601String(),
                'shipment' => ($carrier || $tracking || $shipment) ? [
                    'source_record_id' => $shipment?->id ?: $order->id,
                    'source_type' => $shipment ? 'shipment' : 'order',
                    'carrier' => $carrier ? mb_substr(strip_tags((string) $carrier), 0, 120) : null,
                    'tracking_number' => $tracking ? mb_substr(strip_tags((string) $tracking), 0, 160) : null,
                    'status' => $shipment?->status_label ?: $shipment?->status ?: $order->status,
                    'freshness_at' => ($shipmentFreshness ?: $order->updated_at)?->toIso8601String(),
                ] : null,
                'delivery' => $deliveryDate ? ['kind' => $deliveryKind, 'date' => $deliveryDate] : null,
            ];
        })->toArray();
    }

    private function getRecentConversations(SupportConversation $currentConversation): array
    {
        // Aynı store + aynı support_channel_id + aynı external_customer_id
        if (!$currentConversation->external_customer_id) {
            return [];
        }

        $conversations = SupportConversation::where('store_id', $currentConversation->store_id)
            ->where('support_channel_id', $currentConversation->support_channel_id)
            ->where('external_customer_hash', hash('sha256', (string) $currentConversation->external_customer_id))
            ->where('id', '!=', $currentConversation->id)
            ->orderBy('last_message_at', 'desc')
            ->limit(3)
            ->get();

        if ($conversations->isEmpty()) {
            return [];
        }

        return $conversations->map(fn ($conv) => [
            'id' => $conv->id,
            'status' => $conv->status,
            'source_type' => $conv->source_type,
            'last_message_at' => $conv->last_message_at?->format('Y-m-d H:i'),
        ])->toArray();
    }

    private function getOpenSlaCount(SupportConversation $conversation): int
    {
        return SlaTrack::where('conversation_id', $conversation->id)
            ->where('status', 'open')
            ->count();
    }

    private function detectReturnSignal(int $storeId, SupportConversation $conversation): bool
    {
        // Son konuşmanın son inbound mesajında "iade" veya "iptal" anahtar kelimesi var mı?
        $lastMsg = $conversation->messages()
            ->where('sender_type', 'customer')
            ->orderBy('id', 'desc')
            ->first();

        if (!$lastMsg || empty($lastMsg->body_encrypted)) {
            return false;
        }

        $keywords = ['iade', 'iptal', 'red', 'geri', 'return', 'cancel'];
        $body = mb_strtolower($lastMsg->body_encrypted);

        foreach ($keywords as $kw) {
            if (str_contains($body, $kw)) {
                return true;
            }
        }

        return false;
    }

    private function buildAiSafeContext(array $recentOrders, int $openSlaCount, bool $hasReturnSignal): string
    {
        $parts = [];

        if (!empty($recentOrders)) {
            $parts[] = 'Son siparişler:';
            foreach ($recentOrders as $o) {
                $parts[] = "- Sipariş: {$o['order_number']}, Tarih: {$o['date']}, Durum: {$o['status']}";
            }
        }

        if ($openSlaCount > 0) {
            $parts[] = "Açık SLA sayısı: {$openSlaCount}";
        }

        if ($hasReturnSignal) {
            $parts[] = 'Müşteri iade/iptal talebi sinyali alındı.';
        }

        return implode("\n", $parts);
    }
}
