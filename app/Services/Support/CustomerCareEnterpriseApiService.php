<?php

namespace App\Services\Support;

use App\Models\SupportApiClient;
use App\Models\SupportApiToken;
use App\Models\SupportApiAccessLog;
use App\Models\User;
use App\Models\MarketplaceStore;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportDispatch;
use App\Services\Support\TenantContext;
use App\Services\Support\CustomerCareOrganizationContext;
use App\Services\Support\Security\SupportRbacService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Access\AuthorizationException;

class CustomerCareEnterpriseApiService
{
    /**
     * API Token üretir. Sadece oluşturulma anında plain token döner.
     */
    public function createToken(
        int $clientId,
        string $prefix,
        array $scopes,
        array $storeIds,
        ?int $expiresInDays = null,
        ?User $actor = null
    ): array
    {
        if (!config('customer-care.enabled', false) || !config('customer-care.enterprise_api_enabled', false)) {
            throw new AuthorizationException('Enterprise API veya müşteri iletişim merkezi devre dışı.');
        }

        $client = SupportApiClient::findOrFail($clientId);
        if (!$client->is_active) {
            throw new AuthorizationException('Aktif olmayan API client için token üretilemez.');
        }

        $actor = $actor ?? Auth::user() ?? TenantContext::getSystemActor();
        CustomerCareOrganizationContext::enforceOrganizationAccess((int) $client->legal_entity_id, $actor);

        if (!preg_match('/^[a-z0-9_-]{2,20}$/i', $prefix)) {
            throw new \InvalidArgumentException('Token öneki 2-20 karakter olmalı; yalnız harf, rakam, tire ve alt çizgi içerebilir.');
        }

        $allowedScopes = ['conversations:read', 'messages:read', 'replies:create', 'analytics:read'];
        $scopes = collect($scopes)->map(fn ($scope) => (string) $scope)->unique()->values()->all();
        if (empty($scopes) || !empty(array_diff($scopes, $allowedScopes))) {
            throw new AuthorizationException('API token scope listesi boş veya izin verilmeyen bir scope içeriyor.');
        }
        if ($expiresInDays !== null && ($expiresInDays < 1 || $expiresInDays > 365)) {
            throw new \InvalidArgumentException('Token geçerlilik süresi 1-365 gün arasında olmalıdır.');
        }

        $storeIds = collect($storeIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($storeIds)) {
            throw new AuthorizationException('API token için en az bir mağaza seçilmelidir.');
        }

        $validStoreCount = MarketplaceStore::whereIn('id', $storeIds)
            ->where('legal_entity_id', $client->legal_entity_id)
            ->where('is_active', true)
            ->count();

        if ($validStoreCount !== count($storeIds)) {
            throw new AuthorizationException('API token mağaza kapsamı client organizasyonu dışına çıkamaz.');
        }

        $rbac = app(SupportRbacService::class);
        foreach ($storeIds as $storeId) {
            $rbac->enforcePermission($actor, $storeId, 'manage_webhooks');
        }

        $random = Str::random(40);
        $plainToken = "cc_{$prefix}_{$random}";
        $hash = hash('sha256', $plainToken);

        $token = SupportApiToken::create([
            'api_client_id' => $client->id,
            'token_prefix'  => "cc_{$prefix}",
            'token_hash'    => $hash,
            'scopes'        => $scopes,
            'store_ids'     => $storeIds,
            'expires_at'    => $expiresInDays ? now()->addDays($expiresInDays) : null,
        ]);

        return [
            'token'       => $token,
            'plain_token' => $plainToken,
        ];
    }

    /**
     * Gelen token'ı doğrular ve ilişkili Token nesnesini döner.
     */
    public function authenticateToken(string $plainToken): ?SupportApiToken
    {
        $hash = hash('sha256', $plainToken);
        $token = SupportApiToken::where('token_hash', $hash)->first();

        if (!$token || !$token->isValid()) {
            return null;
        }

        // Last used güncelle
        $token->update(['last_used_at' => now()]);

        return $token;
    }

    /**
     * Token'ın istenen scope'a sahip olup olmadığını ve mağaza erişim yetkisini doğrular.
     */
    public function checkAccess(SupportApiToken $token, string $scope, int $storeId): void
    {
        // 1. Client aktif mi?
        if (!$token->apiClient || !$token->apiClient->is_active) {
            throw new AuthorizationException('API Client aktif değil.');
        }

        // 2. Scope kontrolü
        $scopes = $token->scopes ?? [];
        if (!in_array($scope, $scopes, true)) {
            throw new AuthorizationException("Bu işlem için yetersiz API yetkisi. Gerekli scope: {$scope}");
        }

        // 3. Store ID sınır kontrolü
        $storeIds = $token->store_ids ?? [];
        if (!in_array($storeId, $storeIds, true)) {
            throw new AuthorizationException("Bu API token'ının bu mağaza (Store ID={$storeId}) verilerine erişim yetkisi yoktur.");
        }

        // 4. Organizasyon sınır kontrolü
        $store = \App\Models\MarketplaceStore::find($storeId);
        if (!$store || (int)$store->legal_entity_id !== (int)$token->apiClient->legal_entity_id) {
            throw new AuthorizationException('Bu mağaza, API Client organizasyon sınırları dışındadır.');
        }
    }

    /**
     * API Erişim logu oluşturur. PII maskeler.
     */
    public function logAccess(
        ?SupportApiClient $client,
        ?SupportApiToken $token,
        ?int $storeId,
        string $method,
        string $uri,
        int $status,
        ?string $ip,
        ?array $payload
    ): SupportApiAccessLog {
        // payload içindeki PII (e-posta, şifre, TCKN vb.) maskele
        $redactedPayload = [];
        if ($payload) {
            foreach ($payload as $key => $value) {
                if (in_array(strtolower($key), ['email', 'password', 'token', 'secret', 'phone', 'tckn', 'body'], true)) {
                    $redactedPayload[$key] = '[REDACTED]';
                } else {
                    $redactedPayload[$key] = $value;
                }
            }
        }

        return SupportApiAccessLog::create([
            'api_client_id'            => $client?->id,
            'api_token_id'             => $token?->id,
            'store_id'                 => $storeId,
            'method'                   => $method,
            'uri'                      => $uri,
            'response_status'          => $status,
            'ip_address'               => $ip,
            'request_payload_redacted' => json_encode($redactedPayload, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * API üzerinden dışarıdan güvenli cevap gönderilmesini sağlar (POST /api/customer-care/v1/conversations/{id}/reply).
     */
    public function sendApiReply(int $conversationId, string $body, SupportApiToken $token): SupportMessage
    {
        $conv = SupportConversation::findOrFail($conversationId);
        $this->checkAccess($token, 'replies:create', $conv->store_id);

        // Human lock / ownership kontrolü
        if ($conv->ownership_status === 'human') {
            throw new AuthorizationException('Bu konuşma bir müşteri temsilcisi tarafından sahiplenilmiştir (Locked).');
        }

        // System actor olarak gönderimi yap
        $systemUser = CustomerCareOrganizationContext::getSystemActor($token->apiClient->legal_entity_id);

        $replyService = app(\App\Services\Support\SupportReplyService::class);
        $result = $replyService->sendAgentReply($conv, $body, $systemUser->id);

        if (empty($result['message_id'])) {
            throw new \RuntimeException($result['message'] ?? 'API yanıtı gönderilemedi.');
        }

        return SupportMessage::findOrFail($result['message_id']);
    }
}
