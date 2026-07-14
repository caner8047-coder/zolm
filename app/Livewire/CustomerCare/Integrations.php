<?php

namespace App\Livewire\CustomerCare;

use Livewire\Component;
use App\Models\MarketplaceStore;
use App\Models\SupportChannel;
use App\Models\SupportIntegrationDelivery;
use App\Models\IntegrationConnection;
use App\Services\Support\Integration\CustomerCareIntegrationHubService;
use App\Services\Support\Integration\CustomerCareHttpConnector;

class Integrations extends Component
{
    public int $selectedStoreId;
    public string $webhookUrl = '';
    public string $webhookSecret = '';
    public string $externalProvider = 'crm';
    public string $externalBaseUrl = '';
    public string $externalAuthType = 'bearer';
    public string $externalAccessToken = '';
    public string $externalHealthPath = '/health';
    public string $externalResourcePath = '/v1/contacts';

    // Status check
    public bool $isConfigured = false;

    // Messages
    public string $successMessage = '';
    public string $errorMessage = '';

    public function mount()
    {
        // Hub check
        if (!config('customer-care.integration_hub_enabled', false)) {
            abort(404, 'Enterprise Integration Hub aktif değil.');
        }

        $user = auth()->user();
        if (!$user || $user->role !== 'admin') {
            abort(403, 'Bu sayfaya erişim yetkiniz bulunmamaktadır.');
        }

        $store = MarketplaceStore::first();
        $this->selectedStoreId = $store ? $store->id : 0;
        $this->loadSettings();
    }

    public function updatedSelectedStoreId()
    {
        $this->loadSettings();
    }

    public function loadSettings()
    {
        $this->enforceAdminAccess();

        $channel = SupportChannel::where('store_id', $this->selectedStoreId)
            ->where('key', 'webhook_outbound')
            ->first();

        if ($channel && isset($channel->config_json['webhook_url'])) {
            $this->webhookUrl = $channel->config_json['webhook_url'];
            $this->isConfigured = !empty($channel->config_json['webhook_secret']);
        } else {
            $this->webhookUrl = '';
            $this->isConfigured = false;
        }
        $this->webhookSecret = '';
        $this->loadExternalConnection();
    }

    public function updatedExternalProvider(): void
    {
        $this->externalResourcePath = $this->externalProvider === 'erp' ? '/v1/orders' : '/v1/contacts';
        $this->loadExternalConnection();
    }

    private function loadExternalConnection(): void
    {
        $this->enforceAdminAccess();

        $connection = IntegrationConnection::where('store_id', $this->selectedStoreId)
            ->where('provider', $this->externalProvider)
            ->first();
        $credentials = $connection?->credentials_encrypted ?? [];
        $this->externalBaseUrl = (string) ($connection?->api_base_url ?? '');
        $this->externalAuthType = (string) ($connection?->auth_type ?? 'bearer');
        $this->externalHealthPath = (string) ($credentials['health_path'] ?? '/health');
        $this->externalResourcePath = (string) ($credentials[$this->externalProvider === 'erp' ? 'orders_path' : 'contacts_path']
            ?? ($this->externalProvider === 'erp' ? '/v1/orders' : '/v1/contacts'));
        $this->externalAccessToken = '';
    }

    public function saveExternalConnection(): void
    {
        $this->enforceCredentialPermission();
        $this->validate([
            'externalProvider' => ['required', 'in:crm,erp'],
            'externalBaseUrl' => ['required', 'url', 'max:255', 'starts_with:https://'],
            'externalAuthType' => ['required', 'in:bearer,api_key'],
            'externalAccessToken' => ['nullable', 'string', 'min:8', 'max:2000'],
            'externalHealthPath' => ['required', 'regex:/^\/(?!.*\.\.)[A-Za-z0-9_\-\/\.]+$/', 'max:120'],
            'externalResourcePath' => ['required', 'regex:/^\/(?!.*\.\.)[A-Za-z0-9_\-\/\.]+$/', 'max:120'],
        ]);

        $connection = IntegrationConnection::where('store_id', $this->selectedStoreId)
            ->where('provider', $this->externalProvider)
            ->first();
        $credentials = $connection?->credentials_encrypted ?? [];
        if ($this->externalAccessToken !== '') {
            $credentials[$this->externalAuthType === 'bearer' ? 'access_token' : 'api_key'] = $this->externalAccessToken;
            unset($credentials[$this->externalAuthType === 'bearer' ? 'api_key' : 'access_token']);
        }
        if (empty($credentials['access_token']) && empty($credentials['api_key'])) {
            $this->errorMessage = 'CRM/ERP erişim anahtarı zorunludur.';
            return;
        }
        $credentials['health_path'] = $this->externalHealthPath;
        $credentials[$this->externalProvider === 'erp' ? 'orders_path' : 'contacts_path'] = $this->externalResourcePath;

        $oldFingerprint = $connection ? hash('sha256', json_encode([
            $connection->provider, $connection->auth_type, $connection->api_base_url,
            array_keys($connection->credentials_encrypted ?? []),
        ])) : null;
        $saved = IntegrationConnection::updateOrCreate([
            'store_id' => $this->selectedStoreId,
            'provider' => $this->externalProvider,
        ], [
            'auth_type' => $this->externalAuthType,
            'credentials_encrypted' => $credentials,
            'api_base_url' => rtrim($this->externalBaseUrl, '/'),
            'status' => 'pending_verification',
            'last_verified_at' => null,
            'last_error' => null,
        ]);

        \App\Models\SupportAgentAction::create([
            'conversation_id' => null,
            'user_id' => auth()->id(),
            'action' => 'integration_credentials_changed',
            'details_json' => [
                'store_id' => $this->selectedStoreId,
                'provider' => $this->externalProvider,
                'connection_id' => $saved->id,
                'secret_rotated' => $this->externalAccessToken !== '',
                'old_fingerprint' => $oldFingerprint,
                'new_fingerprint' => hash('sha256', json_encode([$saved->provider, $saved->auth_type, $saved->api_base_url, array_keys($credentials)])),
                'reason' => 'integration_settings_save',
            ],
        ]);

        $this->externalAccessToken = '';
        $this->successMessage = strtoupper($this->externalProvider) . ' bağlantısı kaydedildi; sağlık testi bekleniyor.';
        $this->errorMessage = '';
    }

    public function testExternalConnection(): void
    {
        $this->enforceCredentialPermission();

        $connection = IntegrationConnection::where('store_id', $this->selectedStoreId)
            ->where('provider', $this->externalProvider)
            ->first();
        if (!$connection) {
            $this->errorMessage = 'Önce entegrasyon bağlantısını kaydedin.';
            return;
        }

        $result = app(CustomerCareHttpConnector::class)->healthCheck($connection);
        if ($result['success'] ?? false) {
            $connection->update(['status' => 'active']);
            $this->successMessage = strtoupper($this->externalProvider) . ' sağlık kontrolü başarılı; bağlantı aktif.';
            $this->errorMessage = '';
        } else {
            $connection->update(['status' => 'error']);
            $this->errorMessage = 'Sağlık kontrolü başarısız: ' . ($result['message'] ?? 'Bilinmeyen hata');
            $this->successMessage = '';
        }
    }

    public function saveWebhook()
    {
        $user = auth()->user();
        if (!$user || $user->role !== 'admin') {
            abort(403);
        }
        $this->enforceCredentialPermission();

        $this->validate([
            'webhookUrl' => ['required', 'url', 'max:2048', 'starts_with:https://'],
            'webhookSecret' => 'nullable|string|min:8',
        ]);

        try {
            app(CustomerCareHttpConnector::class)->assertSafeEndpointUrl($this->webhookUrl);
        } catch (\InvalidArgumentException $e) {
            $this->errorMessage = $e->getMessage();
            return;
        }

        $channel = SupportChannel::where('store_id', $this->selectedStoreId)
            ->where('key', 'webhook_outbound')
            ->first();

        $secret = $this->webhookSecret;
        $shouldEncrypt = true;
        if (empty($secret) && $channel && isset($channel->config_json['webhook_secret'])) {
            $secret = $channel->config_json['webhook_secret'];
            $shouldEncrypt = false;
        }

        if (empty($secret)) {
            $this->errorMessage = 'Webhook imzalama anahtarı (secret) belirtilmelidir.';
            return;
        }

        if (!$shouldEncrypt) {
            try {
                \Illuminate\Support\Facades\Crypt::decryptString($secret);
            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                $this->errorMessage = 'Mevcut webhook anahtarı çözülemiyor (Geçersiz/Plaintext Secret). Lütfen yeni bir anahtar girin.';
                return;
            }
        }

        $encryptedSecret = $shouldEncrypt ? \Illuminate\Support\Facades\Crypt::encryptString($secret) : $secret;

        $savedChannel = SupportChannel::updateOrCreate([
            'store_id' => $this->selectedStoreId,
            'key' => 'webhook_outbound',
        ], [
            'name' => 'Outbound Webhook Hub',
            'is_enabled' => true,
            'config_json' => [
                'webhook_url' => $this->webhookUrl,
                'webhook_secret' => $encryptedSecret,
            ],
        ]);

        \App\Models\SupportAgentAction::create([
            'conversation_id' => null,
            'user_id' => $user->id,
            'action' => 'integration_credentials_changed',
            'details_json' => [
                'store_id' => $this->selectedStoreId,
                'provider' => 'webhook_outbound',
                'channel_id' => $savedChannel->id,
                'secret_rotated' => $shouldEncrypt,
                'endpoint_hash' => hash('sha256', $this->webhookUrl),
                'reason' => 'webhook_settings_save',
            ],
        ]);

        $this->successMessage = 'Webhook ayarları başarıyla kaydedildi.';
        $this->webhookSecret = '';
        $this->loadSettings();
    }

    public function retryDelivery(int $deliveryId)
    {
        $this->enforceCredentialPermission();

        // P1-1: Enforce store boundary check for retryDelivery
        $delivery = SupportIntegrationDelivery::whereHas('event', function ($q) {
            $q->where('store_id', $this->selectedStoreId);
        })->find($deliveryId);

        if (!$delivery) {
            $this->errorMessage = 'Teslimat kaydı bulunamadı.';
            return;
        }

        $channel = SupportChannel::where('store_id', $this->selectedStoreId)
            ->where('key', 'webhook_outbound')
            ->first();

        // P0-1: Decrypt webhook secret before sending, fail-closed if invalid
        $rawSecret = $channel ? ($channel->config_json['webhook_secret'] ?? '') : '';
        if (empty($rawSecret)) {
            $delivery->update([
                'status' => 'failed',
                'last_error' => 'Webhook imzalama anahtarı (secret) eksik veya boş. Gönderim iptal edildi.',
            ]);
            $this->errorMessage = 'Tekrar deneme başarısız oldu: Webhook imzalama anahtarı eksik veya boş.';
            return;
        }

        try {
            $secret = \Illuminate\Support\Facades\Crypt::decryptString($rawSecret);
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            $delivery->update([
                'status' => 'failed',
                'last_error' => 'Şifrelenmiş webhook anahtarı çözülemedi (Invalid/Plaintext Secret). Gönderim iptal edildi.',
            ]);
            $this->errorMessage = 'Tekrar deneme başarısız oldu: Şifrelenmiş webhook anahtarı çözülemedi.';
            return;
        }

        $hubService = app(CustomerCareIntegrationHubService::class);
        $success = $hubService->deliver($delivery, $secret);

        if ($success) {
            $this->successMessage = 'Teslimat başarıyla tekrarlandı.';
        } else {
            $this->errorMessage = 'Tekrar deneme başarısız oldu: ' . $delivery->last_error;
        }
    }

    public function render()
    {
        $this->enforceAdminAccess();

        $stores = MarketplaceStore::all();

        // Fetch recent deliveries and dead-letter items
        $deliveries = SupportIntegrationDelivery::whereHas('event', function ($q) {
                $q->where('store_id', $this->selectedStoreId);
            })
            ->latest()
            ->limit(30)
            ->get();

        return view('livewire.customer-care.integrations', [
            'stores' => $stores,
            'deliveries' => $deliveries,
            'externalConnections' => IntegrationConnection::where('store_id', $this->selectedStoreId)
                ->whereIn('provider', ['crm', 'erp'])->get()->keyBy('provider'),
        ])->layout('layouts.app');
    }

    private function enforceCredentialPermission(): void
    {
        $this->enforceAdminAccess();
        \App\Services\Support\TenantContext::enforceStoreAccess($this->selectedStoreId, auth()->user());
        app(\App\Services\Support\Security\SupportRbacService::class)
            ->enforcePermission(auth()->user(), $this->selectedStoreId, 'secret_rotate');
    }

    private function enforceAdminAccess(): void
    {
        $user = auth()->user();
        if (!$user || $user->role !== 'admin') {
            abort(403, 'Bu sayfaya erişim yetkiniz bulunmamaktadır.');
        }
    }
}
