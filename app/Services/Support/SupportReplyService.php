<?php

namespace App\Services\Support;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportAgentAction;
use App\Services\Support\TenantContext;

class SupportReplyService
{
    public function __construct(
        protected \App\Services\Support\AI\CustomerCareAutomationGate $gate,
        protected \App\Services\Support\Policy\SupportPolicyEngine $policyEngine
    ) {
    }

    /**
     * Temsilci yanıtı gönder
     */
    public function sendAgentReply(SupportConversation $conversation, string $message, int $userId): array
    {
        // Enforce Master Kill Switch
        if (!config('customer-care.enabled')) {
            throw new \RuntimeException('Müşteri İletişim Merkezi modülü devre dışı.');
        }

        // Enforce Tenant Access
        $user = \App\Models\User::find($userId);
        if (!$user) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Geçersiz kullanıcı.');
        }
        TenantContext::enforceConversationAccess($conversation->id, $user);

        // Enforce RBAC Permission Guard
        $rbacService = app(\App\Services\Support\Security\SupportRbacService::class);
        $rbacService->enforcePermission($user, $conversation->store_id, 'agent_reply_send');

        // Enforce Consent Check
        $consentService = app(\App\Services\Support\Compliance\CustomerCareConsentService::class);
        if (!$consentService->hasConsent($conversation->store_id, $conversation->external_customer_id, $conversation->channel->key, 'operational')) {
            return ['success' => false, 'message' => 'Consent Blocked: Müşteri izni bulunamadı.'];
        }

        // Gönderim Öncesi Policy Guard
        $policyResult = app(\App\Services\Support\Policy\CustomerCareChannelPolicyService::class)
            ->validate($conversation, $message, null, $userId);
        if (!$policyResult['allowed']) {
            SupportAgentAction::create([
                'conversation_id' => $conversation->id,
                'user_id' => $userId,
                'action' => 'policy_block',
                'details_json' => [
                    'reason' => $policyResult['reason'],
                    'channel' => $conversation->channel->key,
                    'sender_type' => 'agent',
                ]
            ]);
            return [
                'success' => false,
                'message' => 'Politika İhlali: ' . $policyResult['reason']
            ];
        }

        // Enforce Channel Kill Switch
        if (!$conversation->channel->is_enabled) {
            return ['success' => false, 'message' => 'Kanal devre dışı bırakılmış'];
        }

        $adapter = app(SupportChannelManager::class)->resolveForChannel($conversation->channel);

        if (!$adapter->canReply($conversation->channel)) {
            return ['success' => false, 'message' => 'Bu kanalda mesaj gönderme yetkisi yok'];
        }

        // Reopen if resolved or closed using proper domain service and logging
        if (in_array($conversation->status, ['resolved', 'closed'])) {
            $convService = app(SupportConversationService::class);
            $convService->reopen($conversation, $user);
        }

        // Shadow Mode: Eğer aktif bir AI taslağı varsa insan cevabıyla karşılaştır ve benzerlik skorunu kaydet
        $aiDraft = SupportMessage::where('conversation_id', $conversation->id)
            ->where('sender_type', 'ai')
            ->where('delivery_status', 'draft')
            ->orderBy('id', 'desc')
            ->first();

        if ($aiDraft) {
            $evalService = app(\App\Services\Support\AI\CustomerCareEvalService::class);
            $shadowScore = $evalService->calculateShadowMatchScore($aiDraft->body_encrypted, $message);

            $aiRun = \App\Models\SupportAiRun::where('conversation_id', $conversation->id)
                ->where('message_id', $aiDraft->id)
                ->first();
            if ($aiRun) {
                $aiRun->update(['shadow_match_score' => $shadowScore]);
            }

            // Taslağı temizle
            $aiDraft->delete();
        }

        // Mesajı kaydet
        $supportMessage = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'agent',
            'message_type' => 'text',
            'body_encrypted' => $message,
            'body_preview' => mb_substr($message, 0, 100),
            'sent_at' => now(),
            'delivery_status' => 'queued',
        ]);

        // Log Data Lineage Event
        app(\App\Services\Support\Compliance\CustomerCareComplianceService::class)->logLineageEvent(
            $conversation->store_id,
            $conversation->external_customer_id,
            $supportMessage->id,
            'agent_reply_created',
            'support_messages',
            $supportMessage->id
        );

        // Outbox'a al (Generic dispatch kuyruğu)
        $outboxService = app(SupportOutboxService::class);
        $dispatch = $outboxService->enqueue($supportMessage);

        // Haricî kanal çağrısı HTTP isteği içinde yapılmaz. Kalıcı outbox worker'ı
        // gönderimi üstlenir; böylece timeout sahte başarının veya mesaj kaybının
        // nedeni olamaz.
        SupportAgentAction::create([
            'conversation_id' => $conversation->id,
            'message_id' => $supportMessage->id,
            'user_id' => $userId,
            'action' => 'reply_queued',
            'details_json' => [
                'length' => mb_strlen($message),
                'dispatch_id' => $dispatch->id,
                'idempotency_key' => $dispatch->idempotency_key,
            ],
        ]);

        return [
            'success' => true,
            'queued' => true,
            'dispatch_id' => $dispatch->id,
            'message_id' => $supportMessage->id,
        ];
    }

    /**
     * AI otomatik yanıtı gönder
     */
    public function sendAiReply(SupportConversation $conversation, string $message, ?int $confidenceScore = null): array
    {
        // Enforce Master Kill Switch
        if (!config('customer-care.enabled')) {
            return ['success' => false, 'message' => 'Müşteri İletişim Merkezi modülü devre dışı.'];
        }

        $healthService = app(\App\Services\Support\CustomerCareAiProviderHealthService::class);

        // 1. AI Provider Health Check (P0-3)
        if (!$healthService->isProviderHealthy('Gemini')) {
            return [
                'success' => false,
                'message' => 'AI sağlayıcısı devre dışı veya API anahtarı yapılandırılmamış.'
            ];
        }

        // 2. Budget Guard Check (P0-3)
        if ($healthService->hasExceededBudget($conversation->store_id)) {
            return [
                'success' => false,
                'message' => 'Bu mağaza için günlük veya aylık AI bütçe limiti aşıldı.'
            ];
        }

        // Confidence Score check (fail-closed if null)
        if ($confidenceScore === null) {
            return ['success' => false, 'message' => 'AI güven skoru eksik. Fail-closed gereği otomasyon engellendi.'];
        }

        // Mesaj içeriği deterministik ve yerel olarak doğrulanabilir. Geçersiz
        // içeriği pahalı runtime/kanıt kapılarına ulaşmadan engelle.
        $policyResult = app(\App\Services\Support\Policy\CustomerCareChannelPolicyService::class)
            ->validate($conversation, $message);
        if (!$policyResult['allowed']) {
            return [
                'success' => false,
                'message' => 'AI Politika İhlali: ' . $policyResult['reason']
            ];
        }

        // Otomasyon Kapısı Kontrolü
        $gateResult = $this->gate->canAutomate($conversation, $confidenceScore);
        if (!$gateResult['allowed']) {
            return [
                'success' => false,
                'message' => 'Otomasyon Kapısı Engeli: ' . $gateResult['reason']
            ];
        }

        // Auto Reply yetkisi kontrolü
        if (!config('customer-care.auto_reply_enabled')) {
            return ['success' => false, 'message' => 'Otomatik yanıt özelliği devre dışı.'];
        }

        // Kanal kontrolü
        if (!$conversation->channel->is_enabled) {
            return ['success' => false, 'message' => 'Kanal devre dışı bırakılmış.'];
        }

        // Mode ve Ownership Kontrol Matrisi
        if ($conversation->ownership_status === 'human') {
            return ['success' => false, 'message' => 'Konuşma temsilci tarafından sahiplenilmiş (Locked).'];
        }

        if (in_array($conversation->ai_mode, ['manual', 'suggestion_only', 'handoff'])) {
            return ['success' => false, 'message' => 'Mevcut otomasyon modu otomatik gönderime izin vermiyor.'];
        }

        if ($conversation->ai_mode !== 'automatic') {
            return ['success' => false, 'message' => 'Geçersiz otomasyon modu.'];
        }

        // Enforce Consent Check for AI reply
        $consentService = app(\App\Services\Support\Compliance\CustomerCareConsentService::class);
        if (!$consentService->hasConsent($conversation->store_id, $conversation->external_customer_id, $conversation->channel->key, 'operational')) {
            return ['success' => false, 'message' => 'Consent Blocked: Müşteri izni bulunamadı.'];
        }

        // Closed/resolved konuşmalara otomatik yanıt gönderilemez
        if (in_array($conversation->status, ['resolved', 'closed'])) {
            return ['success' => false, 'message' => 'Kapatılmış konuşmalara otomatik yanıt gönderilemez.'];
        }

        $previousRun = \App\Models\SupportAiRun::where('conversation_id', $conversation->id)
            ->whereIn('status', ['draft', 'automatic_approved'])
            ->latest('id')
            ->first();
        $sources = (array) ($previousRun?->sources_used_json ?? []);
        if ($sources === []) {
            $sources = [[
                'type' => 'policy_validation',
                'name' => 'Deterministik düşük risk/politika kontrolü',
                'record_id' => 'policy:' . \App\Services\Support\Policy\SupportPolicyEngine::VERSION,
                'version' => \App\Services\Support\Policy\SupportPolicyEngine::VERSION,
                'freshness_at' => now()->toIso8601String(),
                'is_stale' => false,
            ]];
        }
        $sourceLedger = app(\App\Services\Support\AI\CustomerCareSourceLedgerService::class);
        $sourceValidation = $sourceLedger->validate($sources);
        if (!$sourceValidation['valid'] || !$sourceLedger->containsRequiredClaimSource($message, $sources)) {
            return ['success' => false, 'message' => 'Kaynak Defteri Engeli: ' . ($sourceValidation['reason'] ?? 'Kesin iddia doğrulanmış kayda bağlı değil.')];
        }

        // Mesajı kaydet
        $supportMessage = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'ai',
            'message_type' => 'text',
            'body_encrypted' => $message,
            'body_preview' => mb_substr($message, 0, 100),
            'sent_at' => now(),
            'delivery_status' => 'queued',
        ]);

        // Her otomatik gönderim karar defteri kaydına bağlı olmalıdır. Böylece outbox
        // çağıranın taşıdığı skora körü körüne güvenmez ve eksik ledger'da fail-closed kalır.
        $latestCustomerMessage = $conversation->messages()
            ->where('sender_type', 'customer')
            ->latest('id')
            ->value('body_encrypted');
        \App\Models\SupportAiRun::create([
            'store_id' => $conversation->store_id,
            'conversation_id' => $conversation->id,
            'message_id' => $supportMessage->id,
            'prompt_template_key' => 'automatic_dispatch_v1',
            'prompt_raw' => $latestCustomerMessage,
            'response_raw' => $message,
            'confidence_score' => $confidenceScore,
            'sources_used_json' => $sources,
            'status' => 'automatic_approved',
        ]);

        // Log Data Lineage Event
        app(\App\Services\Support\Compliance\CustomerCareComplianceService::class)->logLineageEvent(
            $conversation->store_id,
            $conversation->external_customer_id,
            $supportMessage->id,
            'ai_reply_created',
            'support_messages',
            $supportMessage->id
        );

        // Outbox'a al
        $outboxService = app(SupportOutboxService::class);
        $dispatch = $outboxService->enqueue($supportMessage);

        return [
            'success' => true,
            'queued' => true,
            'dispatch_id' => $dispatch->id,
            'message_id' => $supportMessage->id,
        ];
    }

    /**
     * AI taslak (draft) oluştur
     */
    public function generateAiDraft(SupportConversation $conversation): array
    {
        // Enforce Master Kill Switch
        if (!config('customer-care.enabled')) {
            return ['success' => false, 'message' => 'Müşteri İletişim Merkezi modülü devre dışı.'];
        }

        // Enforce Tenant Access
        $user = auth()->user();
        if ($user) {
            TenantContext::enforceConversationAccess($conversation->id, $user);
        }

        // Quota check
        $usageService = app(\App\Services\Support\CustomerCareUsageService::class);
        $limitCheck = $usageService->checkLimit($conversation->store_id, 'ai_drafts');
        if (!$limitCheck['allowed']) {
            return [
                'success' => false,
                'message' => 'AI taslak üretimi limit aşımı nedeniyle engellendi: ' . $limitCheck['reason']
            ];
        }

        $orchestrator = app(\App\Services\Support\AI\CustomerCareAiOrchestrator::class);
        $result = $orchestrator->generateDraft($conversation);

        if ($result && isset($result['success']) && $result['success']) {
            $usageService->incrementUsage($conversation->store_id, 'ai_drafts');
        }

        return $result;
    }
}
