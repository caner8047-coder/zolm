<?php

namespace App\Services\Support;

use App\Models\MarketplaceStore;
use App\Models\SupportChannel;
use App\Models\IntegrationConnection;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * CustomerCareChannelProvisioningService
 *
 * Mevcut MarketplaceStore kayıtlarından SupportChannel oluşturur.
 *
 * Tasarım kararları:
 * - Aynı store + key için duplicate oluşturmaz (firstOrCreate benzeri güvenli yazma).
 * - Otomatik yanıtı (ai_mode) asla açmaz; varsayılan 'manual'.
 * - Auto reply flag açılmaz.
 * - Canlı dış API çağrısı yapılmaz.
 * - Store izolasyonu: servis seviyesinde fail-closed doğrulanır.
 */
class CustomerCareChannelProvisioningService
{
    /**
     * Pazaryeri → kanal anahtar ve görünen ad eşlemesi.
     * Bilinmeyen pazaryerler sessizce atlanır (fail-closed).
     */
    protected const MARKETPLACE_CHANNEL_MAP = [
        'trendyol'    => ['key' => 'trendyol',    'name' => 'Trendyol'],
        'hepsiburada' => ['key' => 'hepsiburada',  'name' => 'Hepsiburada'],
        'n11'         => ['key' => 'n11',           'name' => 'N11'],
        'shopify'     => ['key' => 'web_chat',      'name' => 'Web Chat'],
        'woocommerce' => ['key' => 'web_chat',      'name' => 'Web Chat'],
    ];

    protected const INTEGRATION_CHANNEL_MAP = [
        'web_chat'        => ['key' => 'web_chat',        'name' => 'Web Chat'],
        'meta'            => ['key' => 'meta_social',     'name' => 'Meta Social'],
        'meta_social'     => ['key' => 'meta_social',     'name' => 'Meta Social'],
        'google'          => ['key' => 'google_business', 'name' => 'Google Business'],
        'google_business' => ['key' => 'google_business', 'name' => 'Google Business'],
        'whatsapp'        => null, // waAccount üzerinden yönetilir
    ];

    /**
     * Tek bir mağaza için eksik kanalları provizyon eder.
     *
     * @param  int  $storeId   Mağaza ID
     * @param  User|null  $actor   İşlemi yapan kullanıcı; null ise aktif oturum kullanılır.
     * @return array{created: SupportChannel[], existing: SupportChannel[], skipped: string[]}
     */
    public function provisionForStore(int $storeId, ?User $actor = null): array
    {
        $this->enforceStoreAccess($storeId, $actor);

        $store = MarketplaceStore::findOrFail($storeId);

        $created  = [];
        $existing = [];
        $skipped  = [];

        // 1. Pazaryeri kanalı
        $marketplace = strtolower($store->marketplace ?? '');
        if (isset(self::MARKETPLACE_CHANNEL_MAP[$marketplace])) {
            $def    = self::MARKETPLACE_CHANNEL_MAP[$marketplace];
            $result = $this->upsertChannel($store->id, $def['key'], $def['name'], [
                'source' => 'marketplace',
                'marketplace' => $marketplace,
            ]);
            $result['created'] ? $created[] = $result['channel'] : $existing[] = $result['channel'];
        } else {
            $skipped[] = "Bilinmeyen pazaryeri: {$marketplace}";
        }

        // 2. WhatsApp kanalı — waAccount ilişkisi aktifse
        $waAccount = $store->waAccount;
        if ($waAccount && $waAccount->is_active) {
            $result = $this->upsertChannel($store->id, 'whatsapp', 'WhatsApp', [
                'source' => 'whatsapp_account',
            ]);
            $result['created'] ? $created[] = $result['channel'] : $existing[] = $result['channel'];
        }

        // 3. IntegrationConnection tabanlı ek kanallar (web_chat, meta vb.)
        // IntegrationConnection store_id unique — her mağaza için tek kayıt
        $conn = IntegrationConnection::where('store_id', $store->id)
            ->whereIn('status', ['active', 'configured'])
            ->first();

        if ($conn) {
            $provider = strtolower($conn->provider ?? '');

            if (array_key_exists($provider, self::INTEGRATION_CHANNEL_MAP) && self::INTEGRATION_CHANNEL_MAP[$provider] !== null) {
                $def    = self::INTEGRATION_CHANNEL_MAP[$provider];
                $result = $this->upsertChannel($store->id, $def['key'], $def['name'], [
                    'source' => 'integration_connection',
                    'provider' => $provider,
                ]);
                $result['created'] ? $created[] = $result['channel'] : $existing[] = $result['channel'];
            }
        }

        return compact('created', 'existing', 'skipped');
    }

    /**
     * Birden fazla mağaza için provizyon çalıştırır (CLI komutu için).
     *
     * @param  int[]  $storeIds
     */
    public function provisionForStores(array $storeIds, ?User $actor = null): array
    {
        $summary = ['created' => [], 'existing' => [], 'skipped' => []];
        foreach ($storeIds as $storeId) {
            $result = $this->provisionForStore($storeId, $actor);
            $summary['created']  = array_merge($summary['created'],  $result['created']);
            $summary['existing'] = array_merge($summary['existing'], $result['existing']);
            $summary['skipped']  = array_merge($summary['skipped'],  $result['skipped']);
        }
        return $summary;
    }

    /**
     * Kanal yoksa oluştur, varsa döndür.
     * ai_mode her zaman 'manual', is_enabled = false (kullanıcı açar).
     */
    protected function upsertChannel(int $storeId, string $key, string $name, array $meta = []): array
    {
        $existing = SupportChannel::where('store_id', $storeId)
            ->where('key', $key)
            ->first();

        if ($existing) {
            return ['created' => false, 'channel' => $existing];
        }

        $channel = SupportChannel::create([
            'store_id'   => $storeId,
            'key'        => $key,
            'public_key' => $key === 'web_chat' ? Str::random(48) : null,
            'name'       => $name,
            'status'     => 'active',
            'is_enabled' => false, // Kullanıcı manuel olarak açar
            'config_json' => [
                'automation_settings' => [
                    'ai_mode'       => 'manual', // Asla otomatik açılmaz
                    'min_confidence' => 80,
                    'auto_reply'     => false,
                ],
                'provisioning_meta' => $meta,
                'web_chat' => $key === 'web_chat' ? [
                    'allowed_origins' => [],
                    'privacy_notice_version' => 'v1',
                    'privacy_notice_text' => 'Mesajlarınız talebinizi yanıtlamak amacıyla işlenir.',
                    'consent_required' => true,
                ] : null,
            ],
        ]);

        return ['created' => true, 'channel' => $channel];
    }

    /**
     * Bir mağazanın hangi kanalları provizyon edilebileceğini (oluşturulabilir)
     * döndürür — DB'de henüz olmayan potansiyel kanalların listesi.
     */
    public function availableToProvision(int $storeId, ?User $actor = null): Collection
    {
        $this->enforceStoreAccess($storeId, $actor);

        $store = MarketplaceStore::findOrFail($storeId);
        $existing = SupportChannel::where('store_id', $storeId)
            ->pluck('key')
            ->toArray();

        $available = collect();

        $marketplace = strtolower($store->marketplace ?? '');
        if (isset(self::MARKETPLACE_CHANNEL_MAP[$marketplace])) {
            $def = self::MARKETPLACE_CHANNEL_MAP[$marketplace];
            if (!in_array($def['key'], $existing)) {
                $available->push(['key' => $def['key'], 'name' => $def['name']]);
            }
        }

        $waAccount = $store->waAccount;
        if ($waAccount && $waAccount->is_active && !in_array('whatsapp', $existing)) {
            $available->push(['key' => 'whatsapp', 'name' => 'WhatsApp']);
        }

        $conn = IntegrationConnection::where('store_id', $store->id)
            ->whereIn('status', ['active', 'configured'])
            ->first();

        if ($conn) {
            $provider = strtolower($conn->provider ?? '');
            if (array_key_exists($provider, self::INTEGRATION_CHANNEL_MAP) && self::INTEGRATION_CHANNEL_MAP[$provider] !== null) {
                $def = self::INTEGRATION_CHANNEL_MAP[$provider];
                if (!in_array($def['key'], $existing, true)) {
                    $available->push(['key' => $def['key'], 'name' => $def['name']]);
                }
            }
        }

        return $available;
    }

    protected function enforceStoreAccess(int $storeId, ?User $actor = null): void
    {
        $actor = $actor ?? auth()->user();

        if (!$actor) {
            throw new AuthorizationException('Kanal oluşturmak için yetkili kullanıcı bağlamı gereklidir.');
        }

        if (
            TenantContext::validateStoreAccess($storeId, $actor)
            || CustomerCareOrganizationContext::validateStoreOrganizationAccess($storeId, $actor)
        ) {
            return;
        }

        throw new AuthorizationException('Bu mağaza için kanal oluşturma yetkiniz bulunmamaktadır.');
    }
}
