<?php

namespace App\Services\Marketplace;

use App\Models\IntegrationSyncProfile;
use App\Models\MarketplaceStore;
use Illuminate\Support\Collection;

class MarketplaceConnectionReadinessService
{
    /**
     * @return array{
     *     provider: string,
     *     is_ready: bool,
     *     checks: array<int, array{label: string, state: string, message: string}>,
     *     warnings: array<int, string>,
     *     failures: array<int, string>,
     *     summary: string
     * }
     */
    public function inspect(MarketplaceStore $store): array
    {
        $provider = MarketplaceProviderRegistry::normalize((string) $store->marketplace);
        $connection = $store->connection;

        if ($connection?->isDemo()) {
            return [
                'provider' => $provider,
                'is_ready' => true,
                'checks' => [
                    $this->check('Demo bağlantısı', true, 'Güvenli demo connector etkin; harici API isteği gönderilmez.'),
                ],
                'warnings' => [],
                'failures' => [],
                'summary' => 'Demo bağlantısı test akışları için hazır.',
            ];
        }

        $credentials = $connection?->credentials_encrypted ?? [];

        $checks = [
            $this->check('Bağlantı kaydı', $connection !== null, $connection ? 'Bağlantı kaydı mevcut.' : 'Bağlantı kaydı henüz oluşturulmamış.'),
            $this->check('API base URL', filled($connection?->api_base_url), filled($connection?->api_base_url) ? 'Base URL tanımlı.' : 'Base URL boş.'),
        ];

        [$providerChecks, $warnings, $failures] = match ($provider) {
            'trendyol' => $this->inspectTrendyol($store, $credentials),
            'hepsiburada' => $this->inspectHepsiburada($store, $credentials),
            'n11' => $this->inspectN11($store, $credentials),
            'koctas' => $this->inspectKoctas($store, $credentials),
            'pazarama' => $this->inspectPazarama($store, $credentials),
            'amazon' => $this->inspectAmazon($store, $credentials),
            'ciceksepeti' => $this->inspectCiceksepeti($store, $credentials),
            'woocommerce' => $this->inspectWooCommerce($store, $credentials),
            'shopify' => $this->inspectShopify($store, $credentials),
            default => $this->inspectGeneric($store, $credentials),
        };

        [$liveChecks, $liveWarnings, $liveFailures] = $this->inspectLiveVerificationState($store);

        $warnings = array_merge($warnings, $this->unsupportedProfileWarnings($store, $provider));
        $warnings = array_merge($warnings, $liveWarnings);
        $failures = array_merge($failures, $liveFailures);
        $checks = array_merge($checks, $providerChecks);
        $checks = array_merge($checks, $liveChecks);
        $isReady = $failures === [];

        return [
            'provider' => $provider,
            'is_ready' => $isReady,
            'checks' => $checks,
            'warnings' => $warnings,
            'failures' => $failures,
            'summary' => $isReady
                ? 'Bağlantı temel smoke test için hazır görünüyor.'
                : 'Bağlantı hazır değil. Eksik zorunlu alanlar tamamlanmalı.',
        ];
    }

    /**
     * @param  iterable<int, MarketplaceStore>  $stores
     * @return array{
     *     totals: array{stores: int, ready: int, warning: int, missing: int},
     *     rows: array<int, array{
     *         store_id: int|null,
     *         store_name: string,
     *         marketplace: string,
     *         state: string,
     *         is_ready: bool,
     *         warning_count: int,
     *         failure_count: int,
     *         summary: string,
     *         first_failure: string|null,
     *         first_warning: string|null
     *     }>
     * }
     */
    public function inspectCollection(iterable $stores): array
    {
        $rows = Collection::make($stores)
            ->map(function (MarketplaceStore $store): array {
                $result = $this->inspect($store);
                $warningCount = count($result['warnings']);
                $failureCount = count($result['failures']);

                $state = match (true) {
                    $failureCount > 0 => 'missing',
                    $warningCount > 0 => 'warning',
                    default => 'ready',
                };

                return [
                    'store_id' => $store->id,
                    'store_name' => $store->store_name,
                    'marketplace' => MarketplaceProviderRegistry::normalize((string) $store->marketplace),
                    'state' => $state,
                    'is_ready' => (bool) $result['is_ready'],
                    'warning_count' => $warningCount,
                    'failure_count' => $failureCount,
                    'summary' => (string) $result['summary'],
                    'first_failure' => $result['failures'][0] ?? null,
                    'first_warning' => $result['warnings'][0] ?? null,
                ];
            })
            ->values();

        return [
            'totals' => [
                'stores' => $rows->count(),
                'ready' => $rows->where('state', 'ready')->count(),
                'warning' => $rows->where('state', 'warning')->count(),
                'missing' => $rows->where('state', 'missing')->count(),
            ],
            'rows' => $rows->all(),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function unsupportedProfileWarnings(MarketplaceStore $store, string $provider): array
    {
        $profile = $store->relationLoaded('syncProfile')
            ? $store->getRelation('syncProfile')
            : ($store->exists ? $store->syncProfile : null);

        if (!$profile) {
            return [];
        }

        $supports = MarketplaceProviderRegistry::get($provider)['supports'] ?? [];
        $providerLabel = (string) (MarketplaceProviderRegistry::get($provider)['label'] ?? $provider);
        $warnings = [];
        $map = [
            'orders_enabled' => ['capability' => 'orders', 'label' => 'Sipariş sync'],
            'finance_enabled' => ['capability' => 'finance', 'label' => 'Finans sync'],
            'products_enabled' => ['capability' => 'products', 'label' => 'Ürün sync'],
            'webhook_enabled' => ['capability' => 'webhooks', 'label' => 'Webhook'],
            'claims_enabled' => ['capability' => 'claims', 'label' => 'İade sync'],
            'questions_enabled' => ['capability' => 'questions', 'label' => 'Soru sync'],
            'price_push_enabled' => ['capability' => 'price_push', 'label' => 'Fiyat gönderimi'],
            'stock_push_enabled' => ['capability' => 'stock_push', 'label' => 'Stok gönderimi'],
        ];

        foreach ($map as $profileKey => $definition) {
            $enabled = (bool) $profile->{$profileKey};
            $supported = (bool) ($supports[$definition['capability']] ?? false);

            if ($enabled && !$supported) {
                $warnings[] = $definition['label'] . ' açık görünüyor ancak ' . $providerLabel . ' kanalında bu capability pasif.';
            }
        }

        return $warnings;
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{0: array<int, array{label: string, state: string, message: string}>, 1: array<int, string>, 2: array<int, string>}
     */
    protected function inspectTrendyol(MarketplaceStore $store, array $credentials): array
    {
        $warnings = [];
        $failures = [];

        $sellerIdPresent = filled($store->seller_id) || filled($credentials['seller_id'] ?? null);
        $apiKeyPresent = filled($credentials['api_key'] ?? null);
        $apiSecretPresent = filled($credentials['api_secret'] ?? null);
        $storeFrontCodePresent = filled($credentials['store_front_code'] ?? null) || filled($credentials['storefront_code'] ?? null);

        $checks = [
            $this->check('Seller ID', $sellerIdPresent, $sellerIdPresent ? 'Seller ID tanımlı.' : 'Trendyol seller ID eksik.'),
            $this->check('API key', $apiKeyPresent, $apiKeyPresent ? 'API key tanımlı.' : 'Trendyol API key eksik.'),
            $this->check('API secret', $apiSecretPresent, $apiSecretPresent ? 'API secret tanımlı.' : 'Trendyol API secret eksik.'),
            $this->optionalCheck('StoreFrontCode', $storeFrontCodePresent, $storeFrontCodePresent ? 'StoreFrontCode mevcut.' : 'StoreFrontCode boş. Türkiye mağazalarında çoğu test için zorunlu değildir.'),
        ];

        if (!$sellerIdPresent) {
            $failures[] = 'Trendyol seller ID eksik.';
        }

        if (!$apiKeyPresent) {
            $failures[] = 'Trendyol API key eksik.';
        }

        if (!$apiSecretPresent) {
            $failures[] = 'Trendyol API secret eksik.';
        }

        return [$checks, $warnings, $failures];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{0: array<int, array{label: string, state: string, message: string}>, 1: array<int, string>, 2: array<int, string>}
     */
    protected function inspectHepsiburada(MarketplaceStore $store, array $credentials): array
    {
        $warnings = [];
        $failures = [];

        $merchantIdPresent = filled($store->seller_id) || filled($credentials['merchant_id'] ?? null);
        $serviceKeyPresent = filled($credentials['api_key'] ?? null);
        $legacyUserPresent = filled($credentials['extra_user'] ?? null);
        $legacyPasswordPresent = filled($credentials['extra_password'] ?? null) || filled($credentials['api_secret'] ?? null);
        $hasNewAuth = $merchantIdPresent && $serviceKeyPresent;
        $hasLegacyAuth = $legacyUserPresent && $legacyPasswordPresent;
        $userAgentPresent = $legacyUserPresent;

        $refSyncGate = (bool) config('marketplace.hepsiburada.p0_reference_sync_enabled', false);
        $catalogSyncGate = (bool) config('marketplace.hepsiburada.p0_catalog_sync_enabled', false);
        $batchSyncGate = (bool) config('marketplace.hepsiburada.p0_batch_status_sync_enabled', false);
        $connProbeGate = (bool) config('marketplace.hepsiburada.p0_connection_probe_enabled', false);

        $checks = [
            $this->check('Merchant ID', $merchantIdPresent, $merchantIdPresent ? 'Merchant ID tanımlı.' : 'Hepsiburada merchantId eksik.'),
            $this->check('Service Key', $serviceKeyPresent, $serviceKeyPresent ? 'Service key tanımlı.' : 'Hepsiburada service key eksik.'),
            $this->check('User-Agent / entegratör kullanıcı', $userAgentPresent || $hasNewAuth, $userAgentPresent ? 'Yetkili entegratör kullanıcı alanı dolu.' : 'Boşsa ZOLM varsayılan User-Agent gönderilir; Hepsiburada tarafında yetki gerekebilir.'),
            $this->check('Legacy kullanıcı/şifre', $hasNewAuth || $hasLegacyAuth, $hasLegacyAuth ? 'Legacy auth fallback mevcut.' : 'Yeni service key akışı kullanılıyor. Legacy alanlar zorunlu değil.'),
            $this->check('Connection Probe Rollout Gate', $connProbeGate, $connProbeGate ? 'Aktif (Canlı bağlantı testi açık)' : 'Kapalı (Bağlantı probe engellendi)'),
            $this->check('Reference Rollout Gate', $refSyncGate, $refSyncGate ? 'Aktif (Kategori ve nitelik senkronu açık)' : 'Kapalı (Senkron engellendi)'),
            $this->check('Catalog Rollout Gate', $catalogSyncGate, $catalogSyncGate ? 'Aktif (Katalog ürün çekimi açık)' : 'Kapalı (Katalog çekimi engellendi)'),
            $this->check('Batch Status Rollout Gate', $batchSyncGate, $batchSyncGate ? 'Aktif (Batch kuyruğu takibi açık)' : 'Kapalı (Batch sorgulama engellendi)'),
            $this->check('Yapılandırma Durumu', $hasNewAuth || $hasLegacyAuth, ($hasNewAuth || $hasLegacyAuth) ? 'Hazır (configured_not_verified)' : 'Eksik'),
        ];

        if (!$merchantIdPresent) {
            $failures[] = 'Hepsiburada merchantId eksik.';
        }

        if (!$hasNewAuth && !$hasLegacyAuth) {
            $failures[] = 'Hepsiburada için service key veya legacy kullanıcı/şifre bulunmuyor.';
        }

        if ($serviceKeyPresent && !$userAgentPresent) {
            $warnings[] = 'Service key var ama extraUser boş. Hepsiburada Basic Auth yanında User-Agent bekleyebilir.';
        }

        if (!$hasNewAuth && $hasLegacyAuth) {
            $warnings[] = 'Legacy auth fallback kullanılacak. Yeni akışta merchantId + serviceKey tercih edilmeli.';
        }

        return [$checks, $warnings, $failures];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{0: array<int, array{label: string, state: string, message: string}>, 1: array<int, string>, 2: array<int, string>}
     */
    protected function inspectWooCommerce(MarketplaceStore $store, array $credentials): array
    {
        $warnings = [];
        $failures = [];
        $defaults = IntegrationSyncProfile::defaultsForMarketplace('woocommerce');
        $syncProfile = $store->relationLoaded('syncProfile')
            ? $store->getRelation('syncProfile')
            : ($store->exists ? $store->syncProfile : null);

        $sellerId = trim((string) ($store->seller_id ?? ''));
        $legacyStoreUrlPresent = $sellerId !== '' && filter_var($sellerId, FILTER_VALIDATE_URL) !== false;
        $storeUrl = collect([
            trim((string) ($store->connection?->api_base_url ?? '')),
            trim((string) ($credentials['store_url'] ?? '')),
            trim((string) ($store->store_url ?? '')),
            $legacyStoreUrlPresent ? $sellerId : '',
        ])->first(fn ($value) => $value !== '');
        $placeholderUrl = $this->looksLikePlaceholderUrl($storeUrl);
        $baseUrlPresent = filled($store->connection?->api_base_url)
            || filled($credentials['store_url'] ?? null)
            || filled($store->store_url)
            || $legacyStoreUrlPresent;
        $consumerKeyPresent = filled($credentials['api_key'] ?? null);
        $consumerSecretPresent = filled($credentials['api_secret'] ?? null);
        $webhookSecretPresent = filled($store->connection?->webhook_secret);
        $webhookEnabled = (bool) ($syncProfile?->webhook_enabled ?? $defaults['webhook_enabled']);
        $topicAudit = IntegrationSyncProfile::auditWooWebhookTopics(
            data_get($syncProfile?->extra_settings ?? [], 'webhook_topics', data_get($defaults, 'extra_settings.webhook_topics', []))
        );

        $webhookModeHealthy = $webhookEnabled;
        $webhookModeMessage = $webhookEnabled
            ? 'Webhook-first akış aktif.'
            : 'Webhook kapalı. WooCommerce için yükü azaltmak adına webhook-first akış önerilir.';

        $topicSetHealthy = !$webhookEnabled || $topicAudit['matches_recommended'];
        $topicSetMessage = match (true) {
            !$webhookEnabled => 'Webhook kapalı olduğu için topic seti şu an devre dışı.',
            $topicAudit['is_empty'] => 'Webhook açık ama topic seçimi boş. Gelen eventler filtrelenip ignored durumuna düşer.',
            $topicAudit['extra'] !== [] => 'Webhook topic setinde önerilmeyen başlıklar var: '.implode(', ', $topicAudit['extra']),
            $topicAudit['missing'] !== [] => 'Önerilen topiclerin bir kısmı kapalı: '.implode(', ', $topicAudit['missing']),
            default => 'Önerilen WooCommerce webhook topic seti aktif.',
        };

        $checks = [
            $this->check(
                'Mağaza / API URL',
                $baseUrlPresent && !$placeholderUrl,
                match (true) {
                    !$baseUrlPresent => 'WooCommerce mağaza URL eksik.',
                    $placeholderUrl => 'WooCommerce mağaza URL örnek / placeholder görünüyor.',
                    default => 'WooCommerce mağaza URL tanımlı.',
                }
            ),
            $this->check('Consumer key', $consumerKeyPresent, $consumerKeyPresent ? 'Consumer key tanımlı.' : 'WooCommerce consumer key eksik.'),
            $this->check('Consumer secret', $consumerSecretPresent, $consumerSecretPresent ? 'Consumer secret tanımlı.' : 'WooCommerce consumer secret eksik.'),
            $this->check('Webhook secret', $webhookSecretPresent, $webhookSecretPresent ? 'Webhook secret tanımlı.' : 'Webhook secret boş. Okuma akışı için zorunlu değil, webhook için gereklidir.'),
            $this->check('Webhook-first akış', $webhookModeHealthy, $webhookModeMessage),
            $this->check('Webhook topic seti', $topicSetHealthy, $topicSetMessage),
        ];

        if (!$baseUrlPresent) {
            $failures[] = 'WooCommerce mağaza URL veya API base URL eksik.';
        }

        if ($placeholderUrl) {
            $failures[] = 'WooCommerce mağaza URL örnek / placeholder görünüyor. Gerçek site URL girilmelidir.';
        }

        if (!$consumerKeyPresent) {
            $failures[] = 'WooCommerce consumer key eksik.';
        }

        if (!$consumerSecretPresent) {
            $failures[] = 'WooCommerce consumer secret eksik.';
        }

        if (!$webhookSecretPresent) {
            $warnings[] = 'Webhook secret boş. WooCommerce webhook imza doğrulaması için doldurulmalıdır.';
        }

        if (!$webhookEnabled) {
            $warnings[] = 'Webhook kapalı. WooCommerce mağazasını yormamak için webhook-first, polling-fallback akışı önerilir.';
        }

        if ($webhookEnabled && $topicAudit['is_empty']) {
            $warnings[] = 'Webhook açık ama hiçbir topic seçili değil. Gelen WooCommerce eventleri filtrelenip ignored olarak loglanır.';
        }

        if ($webhookEnabled && $topicAudit['missing'] !== []) {
            $warnings[] = 'Önerilen WooCommerce webhook topiclerinin bir kısmı kapalı: '.implode(', ', $topicAudit['missing']);
        }

        if ($webhookEnabled && $topicAudit['extra'] !== []) {
            $warnings[] = 'Önerilen set dışında webhook topic tanımlı: '.implode(', ', $topicAudit['extra']);
        }

        return [$checks, $warnings, $failures];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{0: array<int, array{label: string, state: string, message: string}>, 1: array<int, string>, 2: array<int, string>}
     */
    protected function inspectN11(MarketplaceStore $store, array $credentials): array
    {
        $warnings = [];
        $failures = [];

        $apiKeyPresent = filled($credentials['api_key'] ?? null);
        $apiSecretPresent = filled($credentials['api_secret'] ?? null);
        $sellerIdPresent = filled($store->seller_id);
        $baseUrlPresent = filled($store->connection?->api_base_url ?: config('marketplace.n11.base_url'));

        $checks = [
            $this->check('API key', $apiKeyPresent, $apiKeyPresent ? 'N11 API key tanımlı.' : 'N11 API key eksik.'),
            $this->check('API secret', $apiSecretPresent, $apiSecretPresent ? 'N11 API secret tanımlı.' : 'N11 API secret eksik.'),
            $this->check('Satıcı / mağaza kodu', $sellerIdPresent, $sellerIdPresent ? 'Satıcı / mağaza kodu tanımlı.' : 'Satıcı / mağaza kodu boş. Bazı akışlarda gerekli olabilir.'),
            $this->check('API base URL', $baseUrlPresent, $baseUrlPresent ? 'API base URL tanımlı.' : 'API base URL boş. Resmi endpoint doğrulanınca doldurulmalı.'),
        ];

        if (!$apiKeyPresent) {
            $failures[] = 'N11 API key eksik.';
        }

        if (!$apiSecretPresent) {
            $failures[] = 'N11 API secret eksik.';
        }

        if (!$sellerIdPresent) {
            $warnings[] = 'N11 mağaza / satıcı kodu boş. Sipariş eşleme sırasında gerekli olabilir.';
        }

        if (!$baseUrlPresent) {
            $failures[] = 'N11 API base URL eksik.';
        }

        return [$checks, $warnings, $failures];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{0: array<int, array{label: string, state: string, message: string}>, 1: array<int, string>, 2: array<int, string>}
     */
    protected function inspectKoctas(MarketplaceStore $store, array $credentials): array
    {
        $warnings = [];
        $failures = [];

        $apiKeyPresent = filled($credentials['api_key'] ?? null);
        $apiSecretPresent = filled($credentials['api_secret'] ?? null);
        $sellerIdPresent = filled($store->seller_id);
        $baseUrlPresent = filled($store->connection?->api_base_url ?: config('marketplace.koctas.base_url'));

        $checks = [
            $this->check('API key', $apiKeyPresent, $apiKeyPresent ? 'Koçtaş API key tanımlı.' : 'Koçtaş API key eksik.'),
            $this->check(
                'API secret (opsiyonel)',
                true,
                $apiSecretPresent
                    ? 'Koçtaş API secret tanımlı.'
                    : 'Opsiyonel alan boş. Mirakl seller API bağlantısı API key ile kurulabilir.'
            ),
            $this->check(
                'Shop ID (opsiyonel)',
                true,
                $sellerIdPresent
                    ? 'Koçtaş shop ID tanımlı.'
                    : 'Koçtaş panelinde yalnız API anahtarı varsa bu alan boş bırakılabilir.'
            ),
            $this->check('API base URL', $baseUrlPresent, $baseUrlPresent ? 'API base URL tanımlı.' : 'API base URL boş. Resmi endpoint doğrulanınca doldurulmalı.'),
        ];

        if (!$apiKeyPresent) {
            $failures[] = 'Koçtaş API key eksik.';
        }

        if (!$baseUrlPresent) {
            $failures[] = 'Koçtaş API base URL eksik.';
        }

        return [$checks, $warnings, $failures];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{0: array<int, array{label: string, state: string, message: string}>, 1: array<int, string>, 2: array<int, string>}
     */
    protected function inspectPazarama(MarketplaceStore $store, array $credentials): array
    {
        $warnings = [];
        $failures = [];

        $apiKeyPresent = filled($credentials['api_key'] ?? null);
        $apiSecretPresent = filled($credentials['api_secret'] ?? null);
        $sellerIdPresent = filled($store->seller_id);
        $baseUrlPresent = filled($store->connection?->api_base_url ?: config('marketplace.pazarama.base_url'));

        $checks = [
            $this->check('Client ID / API key', $apiKeyPresent, $apiKeyPresent ? 'Pazarama client ID tanımlı.' : 'Pazarama client ID eksik.'),
            $this->check('Client secret', $apiSecretPresent, $apiSecretPresent ? 'Pazarama client secret tanımlı.' : 'Pazarama client secret eksik.'),
            $this->check('Satıcı / mağaza kodu', $sellerIdPresent, $sellerIdPresent ? 'Satıcı / mağaza kodu tanımlı.' : 'Satıcı / mağaza kodu boş. Sipariş eşleme ve operasyon ekranlarında faydalıdır.'),
            $this->check('API base URL', $baseUrlPresent, $baseUrlPresent ? 'Pazarama API URL tanımlı.' : 'Pazarama API URL eksik.'),
        ];

        if (!$apiKeyPresent) {
            $failures[] = 'Pazarama client ID / API key eksik.';
        }

        if (!$apiSecretPresent) {
            $failures[] = 'Pazarama client secret / API secret eksik.';
        }

        if (!$sellerIdPresent) {
            $warnings[] = 'Pazarama mağaza / satıcı kodu boş. Sipariş eşleme ve operasyon loglarında görünür bir anahtar olması önerilir.';
        }

        if (!$baseUrlPresent) {
            $failures[] = 'Pazarama API base URL eksik.';
        }

        return [$checks, $warnings, $failures];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{0: array<int, array{label: string, state: string, message: string}>, 1: array<int, string>, 2: array<int, string>}
     */
    protected function inspectAmazon(MarketplaceStore $store, array $credentials): array
    {
        $warnings = [];
        $failures = [];

        $apiKeyPresent = filled($credentials['api_key'] ?? null);
        $apiSecretPresent = filled($credentials['api_secret'] ?? null);
        $sellerIdPresent = filled($store->seller_id);
        $baseUrlPresent = filled($store->connection?->api_base_url);

        $checks = [
            $this->check('API key / access key', $apiKeyPresent, $apiKeyPresent ? 'Amazon erişim anahtarı tanımlı.' : 'Amazon erişim anahtarı eksik.'),
            $this->check('API secret', $apiSecretPresent, $apiSecretPresent ? 'Amazon API secret tanımlı.' : 'Amazon API secret eksik.'),
            $this->check('Seller / merchant kodu', $sellerIdPresent, $sellerIdPresent ? 'Seller / merchant kodu tanımlı.' : 'Seller / merchant kodu boş.'),
            $this->check('API base URL', $baseUrlPresent, $baseUrlPresent ? 'API base URL tanımlı.' : 'API base URL boş. Region ve endpoint doğrulanınca doldurulmalı.'),
        ];

        if (!$apiKeyPresent) {
            $failures[] = 'Amazon erişim anahtarı eksik.';
        }

        if (!$apiSecretPresent) {
            $failures[] = 'Amazon API secret eksik.';
        }

        if (!$sellerIdPresent) {
            $warnings[] = 'Amazon seller / merchant kodu boş. Sipariş ve stok akışlarında gerekli olabilir.';
        }

        if (!$baseUrlPresent) {
            $warnings[] = 'Amazon API base URL boş. Region ve resmi endpoint bilgisi onaylandığında doldurulmalıdır.';
        }

        $warnings[] = 'Amazon bağlayıcısı şimdilik güvenli skeleton aşamasında. SP-API region, rol ve credential modeli netleşmeden smoke test ile veri çekimi açılmayacaktır.';

        return [$checks, $warnings, $failures];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{0: array<int, array{label: string, state: string, message: string}>, 1: array<int, string>, 2: array<int, string>}
     */
    protected function inspectCiceksepeti(MarketplaceStore $store, array $credentials): array
    {
        $warnings = [];
        $failures = [];

        $apiKeyPresent = filled($credentials['api_key'] ?? null);
        $sellerIdPresent = filled($store->seller_id);
        $integratorNamePresent = filled($credentials['extra_user'] ?? null);
        $baseUrlPresent = filled($store->connection?->api_base_url ?: config('marketplace.ciceksepeti.base_url'));

        $checks = [
            $this->check('API key', $apiKeyPresent, $apiKeyPresent ? 'Çiçeksepeti API key tanımlı.' : 'Çiçeksepeti API key eksik.'),
            $this->check('Satıcı / mağaza kodu', $sellerIdPresent, $sellerIdPresent ? 'Satıcı / mağaza kodu tanımlı.' : 'Çiçeksepeti user-agent için satıcı kodu eksik.'),
            $this->check(
                'User-Agent / entegratör adı',
                true,
                $integratorNamePresent
                    ? 'Entegratör adı tanımlı. User-Agent satıcıId-entegratörAdı şeklinde kurulabilir.'
                    : 'Entegratör adı boş. Kendi yazılımınızı kullanıyorsanız yalnızca satıcı ID ile devam edilebilir.'
            ),
            $this->check('API base URL', $baseUrlPresent, $baseUrlPresent ? 'Çiçeksepeti API URL tanımlı.' : 'Çiçeksepeti API URL eksik.'),
        ];

        if (!$apiKeyPresent) {
            $failures[] = 'Çiçeksepeti API key eksik.';
        }

        if (!$sellerIdPresent) {
            $failures[] = 'Çiçeksepeti satıcı / mağaza kodu eksik.';
        }

        if (!$baseUrlPresent) {
            $failures[] = 'Çiçeksepeti API base URL eksik.';
        }

        return [$checks, $warnings, $failures];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{0: array<int, array{label: string, state: string, message: string}>, 1: array<int, string>, 2: array<int, string>}
     */
    protected function inspectShopify(MarketplaceStore $store, array $credentials): array
    {
        $warnings = [];
        $failures = [];
        $defaults = IntegrationSyncProfile::defaultsForMarketplace('shopify');
        $syncProfile = $store->relationLoaded('syncProfile')
            ? $store->getRelation('syncProfile')
            : ($store->exists ? $store->syncProfile : null);

        $storeUrlPresent = filled($credentials['store_url'] ?? null) || filled($store->connection?->api_base_url) || filled($store->store_url);
        $apiKeyPresent = filled($credentials['access_token'] ?? null) || filled($credentials['api_key'] ?? null);
        $apiSecretPresent = filled($credentials['api_secret'] ?? null);
        $webhookSecretPresent = filled($store->connection?->webhook_secret);
        $webhookEnabled = (bool) ($syncProfile?->webhook_enabled ?? $defaults['webhook_enabled']);
        $topicAudit = IntegrationSyncProfile::auditWebhookTopics(
            'shopify',
            data_get($syncProfile?->extra_settings ?? [], 'webhook_topics', data_get($defaults, 'extra_settings.webhook_topics', []))
        );

        $webhookModeHealthy = $webhookEnabled;
        $webhookModeMessage = $webhookEnabled
            ? 'Webhook-first akış aktif.'
            : 'Webhook kapalı. Shopify için yükü azaltmak adına webhook-first akış önerilir.';

        $topicSetHealthy = !$webhookEnabled || $topicAudit['matches_recommended'];
        $topicSetMessage = match (true) {
            !$webhookEnabled => 'Webhook kapalı olduğu için topic seti şu an devre dışı.',
            $topicAudit['is_empty'] => 'Webhook açık ama topic seçimi boş. Gelen eventler filtrelenip ignored durumuna düşer.',
            $topicAudit['extra'] !== [] => 'Webhook topic setinde önerilmeyen başlıklar var: '.implode(', ', $topicAudit['extra']),
            $topicAudit['missing'] !== [] => 'Önerilen topiclerin bir kısmı kapalı: '.implode(', ', $topicAudit['missing']),
            default => 'Önerilen Shopify webhook topic seti aktif.',
        };

        $checks = [
            $this->check('Mağaza URL / API URL', $storeUrlPresent, $storeUrlPresent ? 'Shopify mağaza URL tanımlı.' : 'Shopify mağaza URL eksik.'),
            $this->check('Admin API access token', $apiKeyPresent, $apiKeyPresent ? 'Admin API access token tanımlı.' : 'Shopify Admin API access token eksik.'),
            $this->check('App secret key', $apiSecretPresent, $apiSecretPresent ? 'App secret key tanımlı.' : 'Shopify app secret key boş.'),
            $this->check('Webhook secret', $webhookSecretPresent, $webhookSecretPresent ? 'Webhook secret tanımlı.' : 'Webhook secret boş. Shopify HMAC doğrulaması için önerilir.'),
            $this->check('Webhook-first akış', $webhookModeHealthy, $webhookModeMessage),
            $this->check('Webhook topic seti', $topicSetHealthy, $topicSetMessage),
        ];

        if (!$storeUrlPresent) {
            $failures[] = 'Shopify mağaza URL veya API base URL eksik.';
        }

        if (!$apiKeyPresent) {
            $failures[] = 'Shopify Admin API access token eksik.';
        }

        if (!$apiSecretPresent) {
            $warnings[] = 'Shopify app secret key boş. Webhook HMAC doğrulaması için doldurulmalıdır.';
        }

        if (!$webhookSecretPresent && $apiSecretPresent) {
            $warnings[] = 'Webhook secret boş. Shopify için webhook secret alanına app secret key ile aynı değer girilmesi önerilir.';
        }

        if (!$webhookEnabled) {
            $warnings[] = 'Webhook kapalı. Shopify mağazasını yormamak için webhook-first, polling-fallback akışı önerilir.';
        }

        if ($webhookEnabled && $topicAudit['is_empty']) {
            $warnings[] = 'Webhook açık ama hiçbir Shopify topic seçili değil. Gelen eventler filtrelenip ignored olarak loglanır.';
        }

        if ($webhookEnabled && $topicAudit['missing'] !== []) {
            $warnings[] = 'Önerilen Shopify webhook topiclerinin bir kısmı kapalı: '.implode(', ', $topicAudit['missing']);
        }

        if ($webhookEnabled && $topicAudit['extra'] !== []) {
            $warnings[] = 'Önerilen set dışında Shopify webhook topic tanımlı: '.implode(', ', $topicAudit['extra']);
        }

        return [$checks, $warnings, $failures];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{0: array<int, array{label: string, state: string, message: string}>, 1: array<int, string>, 2: array<int, string>}
     */
    protected function inspectGeneric(MarketplaceStore $store, array $credentials): array
    {
        $warnings = [];
        $failures = [];

        $sellerIdPresent = filled($store->seller_id);
        $apiKeyPresent = filled($credentials['api_key'] ?? null);
        $apiSecretPresent = filled($credentials['api_secret'] ?? null);

        $checks = [
            $this->check('Seller / mağaza ID', $sellerIdPresent, $sellerIdPresent ? 'Satıcı kimliği tanımlı.' : 'Satıcı kimliği boş.'),
            $this->check('API key', $apiKeyPresent, $apiKeyPresent ? 'API key tanımlı.' : 'API key boş.'),
            $this->check('API secret', $apiSecretPresent, $apiSecretPresent ? 'API secret tanımlı.' : 'API secret boş.'),
        ];

        if (!$apiKeyPresent && !$apiSecretPresent) {
            $warnings[] = 'Bu sağlayıcı için credential modeli henüz net değil; smoke test öncesi sağlayıcı dokümanı ile alanları doğrulayın.';
        }

        return [$checks, $warnings, $failures];
    }

    protected function check(string $label, bool $condition, string $message): array
    {
        return [
            'label' => $label,
            'state' => $condition ? 'ok' : 'missing',
            'message' => $message,
        ];
    }

    protected function optionalCheck(string $label, bool $condition, string $message): array
    {
        return [
            'label' => $label,
            'state' => $condition ? 'ok' : 'warning',
            'message' => $message,
        ];
    }

    /**
     * @return array{0: array<int, array{label: string, state: string, message: string}>, 1: array<int, string>, 2: array<int, string>}
     */
    protected function inspectLiveVerificationState(MarketplaceStore $store): array
    {
        $connection = $store->connection;

        if (!$connection) {
            return [[], [], []];
        }

        $checks = [];
        $warnings = [];
        $failures = [];
        $lastVerifiedAt = $connection->last_verified_at;
        $lastError = trim((string) ($connection->last_error ?? ''));
        $isTransientRateLimit = $this->isTransientRateLimitError($lastError);
        $isIgnorableProviderError = $this->isIgnorableLiveVerificationError($store, $lastError);

        if ($lastVerifiedAt) {
            $checks[] = match (true) {
                $isTransientRateLimit => $this->optionalCheck('Son canlı doğrulama', false, 'Son canlı doğrulama geçici limit uyarısı verdi: '.$lastError),
                $isIgnorableProviderError => $this->optionalCheck('Son canlı doğrulama', false, 'Son canlı doğrulama eski Pazarama 404 uyarısı verdi: '.$lastError),
                default => $this->check(
                    'Son canlı doğrulama',
                    $lastError === '',
                    $lastError === ''
                        ? 'Son canlı doğrulama başarılı.'
                        : 'Son canlı doğrulama hata verdi: '.$lastError
                ),
            };
        }

        if ($lastError !== '') {
            if ($isTransientRateLimit) {
                $warnings[] = 'Son canlı doğrulama Çiçeksepeti geçici istek limitine takıldı. Birkaç saniye sonra senkron tekrar denenebilir.';
            } elseif ($isIgnorableProviderError) {
                $warnings[] = 'Son Pazarama canlı doğrulaması 404 döndü; bu eski doğrulama hatası sipariş senkronunu engellemeyecek. Sipariş endpointi tekrar denenecek.';
            } else {
                $failures[] = 'Son canlı doğrulama başarısız: '.$lastError;
            }
        }

        return [$checks, $warnings, $failures];
    }

    protected function isTransientRateLimitError(string $message): bool
    {
        $normalized = mb_strtolower($message);

        return str_contains($normalized, 'limit aşımı')
            && str_contains($normalized, '5 saniyede 1 kez');
    }

    protected function isIgnorableLiveVerificationError(MarketplaceStore $store, string $message): bool
    {
        if (MarketplaceProviderRegistry::normalize((string) $store->marketplace) !== 'pazarama') {
            return false;
        }

        return str_contains(mb_strtolower($message), 'status code 404');
    }

    protected function looksLikePlaceholderUrl(?string $url): bool
    {
        $host = (string) parse_url((string) $url, PHP_URL_HOST);
        $host = mb_strtolower($host);

        if ($host === '') {
            return false;
        }

        return in_array($host, ['example.com', 'www.example.com', 'example.org', 'localhost'], true);
    }
}
