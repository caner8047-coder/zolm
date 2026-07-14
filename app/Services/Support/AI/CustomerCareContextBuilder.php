<?php

namespace App\Services\Support\AI;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Services\Support\CustomerCareKnowledgeGroundingService;
use App\Services\Support\BrandVoiceService;
use App\Services\Support\TenantContext;
use App\Services\Support\CustomerCareCustomerSummaryService;

class CustomerCareContextBuilder
{
    private CustomerCareKnowledgeGroundingService $groundingService;
    private BrandVoiceService $bvService;
    private CustomerCareCustomerSummaryService $summaryService;

    public function __construct(
        CustomerCareKnowledgeGroundingService $groundingService,
        BrandVoiceService $bvService,
        CustomerCareCustomerSummaryService $summaryService
    ) {
        $this->groundingService = $groundingService;
        $this->bvService = $bvService;
        $this->summaryService = $summaryService;
    }

    public function buildContext(SupportConversation $conversation): array
    {
        $storeId = $conversation->store_id;
        $store = $conversation->store;

        // Tenant Güvenlik Kontrolü
        if (!$store) {
            throw new \InvalidArgumentException('Geçersiz mağaza bağlamı.');
        }

        // 1. Konuşma Geçmişi (Son 10 mesaj)
        $messages = SupportMessage::where('conversation_id', $conversation->id)
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get()
            ->reverse();

        $historyList = [];
        $historyText = "";
        foreach ($messages as $msg) {
            $sender = $msg->sender_type === 'customer' ? 'user' : 'model';
            $historyList[] = [
                'role' => $sender,
                'text' => $msg->body_encrypted
            ];
            $senderLabel = $msg->sender_type === 'customer' ? 'Müşteri' : 'Temsilci/AI';
            $historyText .= "[{$senderLabel}]: " . $msg->body_encrypted . "\n";
        }

        // Son müşteri mesajı sorgu olarak kullanılır
        $lastCustomerMsg = SupportMessage::where('conversation_id', $conversation->id)
            ->where('sender_type', 'customer')
            ->orderBy('id', 'desc')
            ->first();
        $query = $lastCustomerMsg ? trim($lastCustomerMsg->body_encrypted) : '';
        $sourceReference = $conversation->source_reference_json ?? [];
        $groundingQuery = trim(implode(' ', array_filter([
            $query,
            $sourceReference['product_name'] ?? null,
            $sourceReference['product_sku'] ?? null,
            $sourceReference['product_barcode'] ?? null,
        ])));

        // Son kullanıcı girdisi prompt enjeksiyonu taşıyorsa sağlayıcıya hiçbir
        // içerik göndermeden fail-closed dur. Grounding servisinin boş sonuç
        // fallback'i arama kullanımları için korunur; taslak üretimi daha sıkıdır.
        if ($query !== '' && $this->groundingService->containsPromptInjection($query)) {
            throw new \InvalidArgumentException('Potansiyel prompt injection tespiti nedeniyle AI taslağı engellendi.');
        }

        // 2. Bilgi Grounding (Knowledge & Products)
        $groundContext = [
            'kb' => '',
            'products' => '',
            'has_stale_data' => false,
            'citations' => [],
        ];
        if (!empty($groundingQuery)) {
            $groundContext = $this->groundingService->ground($storeId, $groundingQuery, $historyText);
        }

        $kbText = $groundContext['kb'] ?? '';
        $productsText = $groundContext['products'] ?? '';
        $hasStaleData = $groundContext['has_stale_data'] ?? false;
        $citations = $groundContext['citations'] ?? [];

        // 3. Marka Sesi ve Kuralları (Brand Voice)
        $brandVoice = $this->bvService->getBrandVoice($conversation->channel);
        $tone = $brandVoice['tone'] ?? 'kibar ve yardımsever';
        $promptContext = $brandVoice['prompt_context'] ?? '';
        $returnPolicy = $brandVoice['return_policy'] ?? '';

        // 4. Sipariş geçmişi yalnız doğrulanmış müşteri/order eşleşmesi üzerinden gelir.
        // Eşleşme yoksa fail-closed olarak hiçbir sipariş prompt'a eklenmez.
        $customerSummary = $this->summaryService->getSummary($conversation);
        $safeOrders = $customerSummary['recent_orders'] ?? [];
        $ordersText = '';
        foreach ($safeOrders as $order) {
            $ordersText .= "Sipariş No: {$order['order_number']}, Tarih: {$order['date']}, Durum: {$order['status']}, Ürün: {$order['product_name']}, Adet: {$order['quantity']}\n";
            $citations[] = [
                'type' => 'order',
                'name' => 'Sipariş Kaydı',
                'record_id' => $order['source_record_id'],
                'freshness_at' => $order['freshness_at'],
                'verified_customer_match' => true,
            ];
            if (!empty($order['shipment'])) {
                $shipment = $order['shipment'];
                $ordersText .= "Kargo kaydı: Firma: " . ($shipment['carrier'] ?: 'kayıtta yok')
                    . ", Takip: " . ($shipment['tracking_number'] ?: 'kayıtta yok')
                    . ", Durum: " . ($shipment['status'] ?: 'kayıtta yok') . "\n";
                $citations[] = [
                    'type' => $shipment['source_type'] ?? 'shipment',
                    'name' => 'Canlı Kargo/Takip Kaydı',
                    'record_id' => $shipment['source_record_id'],
                    'freshness_at' => $shipment['freshness_at'],
                    'verified_customer_match' => true,
                ];
                if (!empty($shipment['freshness_at']) && \Carbon\Carbon::parse($shipment['freshness_at'])->lt(now()->subHour())) {
                    $hasStaleData = true;
                }
            }
            if (!empty($order['delivery'])) {
                $label = $order['delivery']['kind'] === 'actual' ? 'Gerçekleşen teslim tarihi' : 'Tahmini teslim tarihi (kesin vaat değildir)';
                $ordersText .= "{$label}: {$order['delivery']['date']}\n";
            } else {
                $ordersText .= "Teslim tarihi: Doğrulanmış tahmin/gerçek tarih yok; kesin tarih söylenemez.\n";
            }
        }

        // 5. Müşteri Özeti — store-scoped, kimlik eşleşmeli ve PII-masked.
        $customerSummaryText = $customerSummary['ai_safe_context'] ?? '';

        $matchedSources = [];
        if (!empty($kbText)) $matchedSources[] = 'Knowledge Base';
        if (!empty($ordersText)) $matchedSources[] = 'Orders Ledger';
        if (!empty($productsText)) $matchedSources[] = 'Product Catalog';
        if (!empty($customerSummaryText)) $matchedSources[] = 'Customer Summary';

        return [
            'history_list' => $historyList,
            'history_text' => $historyText,
            'kb' => $kbText,
            'tone' => $tone,
            'prompt_context' => $promptContext,
            'return_policy' => $returnPolicy,
            'brand_voice' => $brandVoice,
            'orders' => $ordersText,
            'products' => $productsText,
            'customer_summary' => $customerSummaryText,
            'query' => $query,
            'matched_sources' => $matchedSources,
            'has_stale_data' => $hasStaleData,
            'citations' => $citations,
        ];
    }
}
