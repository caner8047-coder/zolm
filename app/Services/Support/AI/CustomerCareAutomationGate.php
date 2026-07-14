<?php

namespace App\Services\Support\AI;

use App\Models\SupportConversation;
use App\Models\SupportChannel;
use App\Models\MarketplaceStore;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;

class CustomerCareAutomationGate
{
    private CustomerCareAiProviderInterface $aiProvider;

    public function __construct(
        CustomerCareAiProviderInterface $aiProvider
    ) {
        $this->aiProvider = $aiProvider;
    }

    /**
     * AI'ın otomatik yanıt göndermesine (Automatic Mode) izin verilip verilmediğini kontrol eder.
     */
    public function canAutomate(SupportConversation $conversation, ?int $confidenceScore = null): array
    {
        // 1. Master Kill-Switch
        if (!Config::get('customer-care.enabled', false)) {
            return [
                'allowed' => false,
                'reason' => 'Master Kill-Switch: Modül genel olarak kapalı.'
            ];
        }

        // 2. Auto Reply Flag
        if (!Config::get('customer-care.auto_reply_enabled', false)) {
            return [
                'allowed' => false,
                'reason' => 'Auto Reply Feature Flag: Otomatik cevaplama devre dışı.'
            ];
        }

        // Manuel/acil durdurma kilidi, otomatik circuit-breaker metriği kapalı
        // olsa bile her zaman bağlayıcıdır.
        if (Cache::get("circuit_breaker_forced_open_{$conversation->store_id}", false)) {
            return [
                'allowed' => false,
                'reason' => 'Circuit Breaker OPEN: Manual Override (manuel acil durdurma kilidi) aktif.'
            ];
        }

        // Güvenilirlik anahtarı aşağıdaki deterministik konuşma/politika
        // kapılarından sonra kuyruk sağlığıyla birlikte fail-closed doğrulanır.
        $reliabilityEnabled = (bool) config('customer-care.reliability_enabled', false);

        // 3. Store Allowlist Kontrolü
        $allowlist = Config::get('customer-care.pilot_store_allowlist', []);
        if (!in_array($conversation->store_id, $allowlist)) {
            return [
                'allowed' => false,
                'reason' => "Pilot Store Allowlist: Mağaza ({$conversation->store_id}) pilot izin listesinde değil."
            ];
        }

        // 4. Human Ownership (Lock)
        if ($conversation->ownership_status === 'human') {
            return [
                'allowed' => false,
                'reason' => 'Human Ownership Lock: Temsilci konuşmayı sahiplenmiş.'
            ];
        }

        if ($conversation->ai_mode !== 'automatic') {
            return [
                'allowed' => false,
                'reason' => "AI Mode Gate: Konuşma automatic modda değil ({$conversation->ai_mode})."
            ];
        }

        $intent = $this->inferIntent($conversation);
        $intentModes = (array) data_get($conversation->channel?->config_json, 'automation_settings.intent_modes', []);
        $intentMode = $intentModes[$intent] ?? null;
        if ($intentMode !== null && $intentMode !== 'automatic') {
            return [
                'allowed' => false,
                'reason' => "Intent Mode Gate: {$intent} niyeti {$intentMode} moduyla sınırlandırılmış.",
            ];
        }
        if ($intent === 'product') {
            $catalogVerified = \App\Models\SupportOnboardingState::where('store_id', $conversation->store_id)
                ->where('catalog_verified_at', '>=', now()->subDay())->exists();
            if (!$catalogVerified) {
                return ['allowed' => false, 'reason' => 'Catalog Gate: Güncel stok/fiyat dry-run doğrulaması tamamlanmadan ürün otomasyonu açılamaz.'];
            }
        }

        // Güven puanı ucuz ve deterministik bir kapıdır; dış/kalite kayıtlarına
        // bakmadan önce düşük güvenli isteği reddeder.
        if ($confidenceScore === null) {
            return [
                'allowed' => false,
                'reason' => 'Confidence Threshold: Güven skoru eksik; fail-closed.'
            ];
        }
        $threshold = $this->resolveConfidenceThreshold($conversation);
        if ($confidenceScore < $threshold) {
            return [
                'allowed' => false,
                'reason' => "Confidence Threshold: Güven skoru ({$confidenceScore}) otomasyon limiti ({$threshold}) altında."
            ];
        }

        // Mesai penceresi de dil/eval sorgularından önce uygulanan deterministik
        // bir gönderim kuralıdır.
        $businessHoursAutoEnabled = Config::get('customer-care.business_hours_auto_reply_enabled', false);
        if (!$businessHoursAutoEnabled) {
            $now = \Carbon\Carbon::now();
            $isWeekend  = $now->isWeekend();
            $isOutsideHours = $now->hour < 9 || $now->hour >= 18;

            if ($isWeekend || $isOutsideHours) {
                return [
                    'allowed' => false,
                    'reason' => 'Business Hours Gate: Mesai dışı otomatik cevap allowlist kapalı — fail-closed.'
                ];
            }
        }

        $latestCustomerText = (string) $conversation->messages()
            ->where('sender_type', 'customer')->latest('id')->value('body_encrypted');
        $languageService = app(CustomerCareLanguageService::class);
        $languageDetection = $languageService->detect($latestCustomerText);
        $language = $languageDetection['language'];
        if ((float) $languageDetection['confidence'] < CustomerCareLanguageService::MIN_DETECTION_CONFIDENCE || $language === 'und') {
            return ['allowed' => false, 'reason' => 'Language Gate: Müşteri dili düşük güvenle tespit edildi; insan doğrulaması gerekli.'];
        }
        if (!in_array($language, $languageService->supportedLanguages($conversation->channel), true)) {
            return ['allowed' => false, 'reason' => "Language Gate: {$language} dili bu kanalın marka profilinde yapılandırılmamış."];
        }
        if (!$languageService->hasPassedAutomationGate((int) $conversation->store_id, $language)) {
            return ['allowed' => false, 'reason' => "Language Gate: {$language} dili için güncel golden kalite kapısı geçilmemiş."];
        }

        // 4.1. Meta Social Comment & Google Business Reviews Restriction
        if ($conversation->source_reference_json && ($conversation->source_reference_json['thread_type'] ?? null) === 'comment') {
            return [
                'allowed' => false,
                'reason' => 'Automation Gate Blocked: Sosyal medya yorumları için otomatik yanıt kapalıdır.'
            ];
        }

        if ($conversation->source_type === 'google_business') {
            $ref = $conversation->source_reference_json ?? [];
            $rating = (int)($ref['rating'] ?? 0);
            if ($rating <= 2) {
                return [
                    'allowed' => false,
                    'reason' => 'Automation Gate Blocked: Düşük yıldızlı Google yorumları için otomatik yanıt kapalıdır.'
                ];
            }
            if (!config('customer-care.google_reviews_auto_reply_enabled', false)) {
                return [
                    'allowed' => false,
                    'reason' => 'Automation Gate Blocked: Google yorumları otomatik yanıtı devre dışı.'
                ];
            }
        }

        // 6. Eval Gate (Golden Dataset Değerlendirme Eşiği - DB'den okunur ve eskilik kontrolü yapılır)
        $evalEvidence = app(CustomerCareGoldenEvalGateService::class)
            ->evaluate($conversation->store_id, 'tr');
        if (!$evalEvidence['passed']) {
            return [
                'allowed' => false,
                'reason' => 'Eval Gate Failure: ' . $evalEvidence['detail'],
            ];
        }

        // 7. Circuit Breaker ve Rate Limit Kontrolleri
        $maxPerHour = (int) Config::get('customer-care.auto_reply_max_per_hour', 0);
        if ($maxPerHour <= 0) {
            return [
                'allowed' => false,
                'reason' => 'Rate Limit Fail-Closed: Saatlik otomatik yanıt limiti pozitif tanımlanmamış.'
            ];
        }

        $autoReplyCount1h = \App\Models\SupportMessage::whereHas('conversation', function ($q) use ($conversation) {
            $q->where('store_id', $conversation->store_id);
        })
        ->where('sender_type', 'ai')
        ->where('direction', 'outbound')
        // Kuyruktaki mesajları da rezervasyon olarak say; aksi halde aynı dakika
        // içinde çok sayıda istek worker teslim etmeden limiti aşabilir.
        ->whereIn('delivery_status', ['queued', 'sending', 'pending', 'accepted', 'sent'])
        ->where('created_at', '>=', now()->subHour())
        ->count();

        if ($autoReplyCount1h >= $maxPerHour) {
            return [
                'allowed' => false,
                'reason' => "Rate Limit Exceeded: Saatlik otomatik yanıt limitine ({$maxPerHour}) ulaşıldı."
            ];
        }

        if (!$reliabilityEnabled) {
            return [
                'allowed' => false,
                'reason' => 'Reliability Gate: Kuyruk sağlığı izleme özelliği kapalı; otomasyon fail-closed.',
            ];
        }

        $backpressure = app(\App\Services\Support\Reliability\CustomerCareQueueHealthService::class)
            ->checkBackpressure($conversation->store_id);
        if (($backpressure['status'] ?? 'unknown') !== 'healthy') {
            return [
                'allowed' => false,
                'reason' => 'Queue Health Gate: ' . ($backpressure['reason'] ?? 'Kuyruk sağlığı doğrulanamadı.'),
            ];
        }

        if (!Config::get('customer-care.circuit_breaker_enabled', false)) {
            return [
                'allowed' => false,
                'reason' => 'Circuit Breaker Gate: Devre kesici izleme özelliği kapalı; otomasyon fail-closed.',
            ];
        }

        $monitorService = app(\App\Services\Support\CustomerCarePilotMonitorService::class);
        $metrics = $monitorService->getStoreMetrics($conversation->store_id);
        if (($metrics['circuit_breaker_status'] ?? 'unknown') !== 'closed') {
            return [
                'allowed' => false,
                'reason' => "Circuit Breaker OPEN: " . ($metrics['trip_reason'] ?? 'Güvenli kapalı durum doğrulanamadı.')
            ];
        }

        return [
            'allowed' => true,
            'reason' => 'Tüm pilot güvenlik kapısı kriterleri başarıyla geçildi.'
        ];
    }

    private function resolveConfidenceThreshold(SupportConversation $conversation): int
    {
        $base = (int) Config::get('customer-care.confidence_threshold', 80);
        $storeThreshold = (int) Config::get("customer-care.confidence_thresholds.stores.{$conversation->store_id}", $base);
        $settings = $conversation->channel?->config_json['automation_settings'] ?? [];
        $channelThreshold = (int) ($settings['min_confidence'] ?? $storeThreshold);
        $intent = $this->inferIntent($conversation);
        $intentThresholds = (array) ($settings['intent_thresholds'] ?? []);
        $intentThreshold = (int) ($intentThresholds[$intent] ?? match ($intent) {
            'order_status' => 90,
            'return_or_cancel', 'health_or_legal' => 95,
            'product' => 85,
            default => $channelThreshold,
        });

        return max(0, min(100, max($base, $storeThreshold, $channelThreshold, $intentThreshold)));
    }

    private function inferIntent(SupportConversation $conversation): string
    {
        $message = mb_strtolower((string) $conversation->messages()
            ->where('sender_type', 'customer')
            ->latest('id')
            ->value('body_encrypted'));

        return match (true) {
            preg_match('/sağlık|saglik|ilaç|ilac|doktor|hukuk|dava|yasal/u', $message) === 1 => 'health_or_legal',
            preg_match('/iade|iptal|geri gönder/u', $message) === 1 => 'return_or_cancel',
            preg_match('/kargo|sipariş|siparis|teslim|takip/u', $message) === 1 => 'order_status',
            preg_match('/ürün|urun|beden|stok|fiyat|uyumlu/u', $message) === 1 => 'product',
            default => 'general',
        };
    }
}
