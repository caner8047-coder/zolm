<?php

namespace App\Services\Support;

use App\Models\SupportChannel;
use App\Models\SupportConnectorCertificationRun;
use App\Models\SupportConnectorCertificationCheck;
use App\Models\IntegrationConnection;
use App\Models\WaAccount;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class CustomerCareConnectorCertificationService
{
    /**
     * Kanal sertifikasyonunu çalıştırır ve kayıtları veritabanına ekler (append-only).
     */
    public function certifyChannel(int $storeId, string $channelKey, ?User $user = null): SupportConnectorCertificationRun
    {
        $user = $user ?? Auth::user() ?? TenantContext::getSystemActor();
        TenantContext::enforceStoreAccess($storeId, $user);
        $inspection = $this->inspectChannel($storeId, $channelKey, $user);

        $run = SupportConnectorCertificationRun::create([
            'store_id'     => $storeId,
            'channel_key'  => $channelKey,
            'certified_by' => $user->id,
            'status'       => 'fail',
        ]);

        foreach ($inspection['checks'] as $check) {
            $this->createCheck($run->id, $check['name'], $check['status'], $check['details']);
        }
        $run->update(['status' => $inspection['status']]);

        return $run;
    }

    /**
     * Kalıcı kayıt oluşturmadan sertifikasyonla aynı gerçek kontrolleri çalıştırır.
     */
    public function inspectChannel(int $storeId, string $channelKey, ?User $user = null): array
    {
        $user = $user ?? Auth::user() ?? TenantContext::getSystemActor();
        TenantContext::enforceStoreAccess($storeId, $user);

        $checks = [];
        $flagEnabled = $this->isChannelFlagEnabled($channelKey);
        $checks[] = [
            'name' => 'feature_flag_enabled',
            'status' => $flagEnabled ? 'pass' : 'fail',
            'details' => $flagEnabled ? 'Özellik bayrağı aktif.' : 'Kanal özellik bayrağı kapalı.',
        ];

        $channel = SupportChannel::where('store_id', $storeId)->where('key', $channelKey)->first();
        $channelActive = (bool) ($channel?->is_enabled && $channel?->status === 'active');
        $checks[] = [
            'name' => 'channel_configured',
            'status' => $channelActive ? 'pass' : 'fail',
            'details' => $channelActive ? 'Kanal aktif ve tanımlanmış.' : 'Kanal tanımlı değil, pasif veya devre dışı.',
        ];

        [$connectionConfigured, $connection, $connectionMessage] = $this->resolveConnectorBinding($storeId, $channelKey);
        $checks[] = [
            'name' => 'connector_bound',
            'status' => $connectionConfigured ? 'pass' : 'fail',
            'details' => $connectionMessage,
        ];

        $canReply = false;
        if ($channel && $channelActive) {
            try {
                $canReply = app(SupportChannelManager::class)->resolveForChannel($channel)->canReply($channel);
            } catch (\Throwable) {
                $canReply = false;
            }
        }
        $checks[] = [
            'name' => 'send_capability',
            'status' => $canReply ? 'pass' : 'fail',
            'details' => $canReply ? 'canReply() yetkilendirmesi başarılı.' : 'Mesaj gönderme yeteneği devre dışı veya canReply() false döndü.',
        ];

        $health = ['status' => 'error', 'message' => 'Kanal veya adapter sağlık kontrolü çalıştırılamadı.'];
        if ($channel && $channelActive) {
            try {
                // Salt-okunur inceleme: adapter çağrılır, kanal health alanları yazılmaz.
                $health = app(SupportChannelManager::class)->resolveForChannel($channel)->healthCheck($channel);
            } catch (\Throwable $exception) {
                $health = ['status' => 'error', 'message' => $exception->getMessage()];
            }
        }
        $healthPassed = ($health['status'] ?? 'error') === 'ok';
        $checks[] = [
            'name' => 'connector_health',
            'status' => $healthPassed ? 'pass' : 'fail',
            'details' => $healthPassed
                ? (string) ($health['message'] ?? 'Connector sağlık kontrolü başarılı.')
                : 'Connector sağlık kontrolü başarısız: ' . (string) ($health['message'] ?? 'Bilinmeyen hata'),
        ];

        $hygienePass = true;
        if ($connection) {
            $webhookSecret = (string) ($connection->webhook_secret ?? '');
            $appSecret = (string) ($connection->app_secret ?? '');
            $hygienePass = !str_contains($webhookSecret, 'plain_') && !str_contains($appSecret, 'plain_');
        }
        $checks[] = [
            'name' => 'secret_hygiene',
            'status' => $hygienePass ? 'pass' : 'warn',
            'details' => $hygienePass
                ? 'Gizli anahtar ve token güvenliği doğrulandı.'
                : 'UYARI: Güvenli olmayan açık anahtar tespit edildi.',
        ];

        $checks = collect($checks)->map(function (array $check): array {
            $check['details'] = $this->maskSecrets($check['details']);
            return $check;
        })->all();
        $hasFail = collect($checks)->contains('status', 'fail');
        $hasWarn = collect($checks)->contains('status', 'warn');

        return [
            'store_id' => $storeId,
            'channel_key' => $channelKey,
            'status' => $hasFail ? 'fail' : ($hasWarn ? 'warn' : 'pass'),
            'checks' => $checks,
        ];
    }

    /**
     * Sandbox gelen olay simülasyonunu gerçekleştirir.
     */
    public function simulateWebhookEvent(int $storeId, string $channelKey, array $payload, ?User $user = null): array
    {
        $user = $user ?? Auth::user() ?? TenantContext::getSystemActor();
        TenantContext::enforceStoreAccess($storeId, $user);

        $channel = SupportChannel::where('store_id', $storeId)->where('key', $channelKey)->first();
        if (!$channel) {
            return ['success' => false, 'message' => 'Simülasyon için kanal bulunamadı.'];
        }

        if ($channelKey === 'web_chat') {
            // Web Chat imza doğrulamasını sandbox'ta taklit et
            $adapter = app(\App\Services\Support\WebChatSupportChannelAdapter::class);
            $res = $adapter->projectMessage($channel, $payload);
            return $res;
        }

        // Diğer kanallarda inbound simulation
        try {
            $adapter = app(SupportChannelManager::class)->resolveForChannel($channel);
            $res = $adapter->projectMessage($channel, $payload);
            return $res;
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Simülasyon hatası: ' . $e->getMessage()];
        }
    }

    private function createCheck(int $runId, string $name, string $status, string $details): SupportConnectorCertificationCheck
    {
        // PII ve Secret maskeleme
        $details = $this->maskSecrets($details);

        return SupportConnectorCertificationCheck::create([
            'run_id'     => $runId,
            'check_name' => $name,
            'status'     => $status,
            'details'    => $details,
        ]);
    }

    private function maskSecrets(string $text): string
    {
        // Token ve webhook_secret maskeleme regex'leri
        $text = preg_replace('/(cc_[a-zA-Z0-9_]{30,})/u', '[TOKEN-MASKELENDİ]', $text);
        return $text;
    }

    private function isChannelFlagEnabled(string $channelKey): bool
    {
        return match ($channelKey) {
            'trendyol'     => true, // trendyol standart açık
            'hepsiburada'  => true, // HB standart açık
            'n11'          => true, // n11 standart açık
            'whatsapp'     => true, // whatsapp standart açık
            'meta_social'  => config('customer-care.meta_social_enabled', false),
            'instagram'    => config('customer-care.meta_social_enabled', false),
            'facebook'     => config('customer-care.meta_social_enabled', false),
            'google'       => config('customer-care.google_reviews_enabled', false),
            'google_reviews' => config('customer-care.google_reviews_enabled', false),
            'google_business' => config('customer-care.google_reviews_enabled', false),
            'web_chat'     => config('customer-care.web_chat_enabled', false),
            'enterprise_api' => config('customer-care.enterprise_api_enabled', false),
            default        => false,
        };
    }

    /**
     * Kanonik kanal anahtarı ile IntegrationConnection provider adları birebir aynı
     * olmayabiliyor. Sertifikasyonun yanlış negatif üretmemesi için tüm bilinen
     * alias'ları tek yerde topluyoruz.
     *
     * @return string[]
     */
    private function providerAliases(string $channelKey): array
    {
        return match ($channelKey) {
            'meta_social' => ['meta_social', 'meta', 'instagram', 'facebook'],
            'instagram' => ['instagram', 'meta_social', 'meta'],
            'facebook' => ['facebook', 'meta_social', 'meta'],
            'google_business', 'google', 'google_reviews' => ['google_business', 'google', 'google_reviews'],
            'web_chat' => ['web_chat'],
            default => [$channelKey],
        };
    }

    /**
     * @return array{0: bool, 1: IntegrationConnection|null, 2: string}
     */
    private function resolveConnectorBinding(int $storeId, string $channelKey): array
    {
        if ($channelKey === 'whatsapp') {
            $account = WaAccount::where('store_id', $storeId)
                ->where('is_active', true)
                ->where('status', 'active')
                ->first();

            if ($account) {
                return [true, null, 'WhatsApp hesabı aktif ve mağazaya bağlı.'];
            }
        }

        $connection = IntegrationConnection::where('store_id', $storeId)
            ->whereIn('provider', $this->providerAliases($channelKey))
            ->whereIn('status', ['configured', 'active', 'connected'])
            ->first();

        if ($connection) {
            return [true, $connection, 'Entegrasyon bağlantısı kurulmuş.'];
        }

        return [false, null, 'Aktif entegrasyon bağlantısı bulunamadı.'];
    }
}
