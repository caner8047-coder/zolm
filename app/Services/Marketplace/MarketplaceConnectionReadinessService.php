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

        $warnings = array_merge($warnings, $this->unsupportedProfileWarnings($store, $provider));
        $checks = array_merge($checks, $providerChecks);
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
            $this->check('StoreFrontCode', $storeFrontCodePresent, $storeFrontCodePresent ? 'StoreFrontCode mevcut.' : 'StoreFrontCode boş. Her mağaza için zorunlu olmayabilir.'),
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

        if (!$storeFrontCodePresent) {
            $warnings[] = 'StoreFrontCode boş. Bazı mağazalarda gerekli olabilir.';
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

        $checks = [
            $this->check('Merchant ID', $merchantIdPresent, $merchantIdPresent ? 'Merchant ID tanımlı.' : 'Hepsiburada merchantId eksik.'),
            $this->check('Service Key', $serviceKeyPresent, $serviceKeyPresent ? 'Service key tanımlı.' : 'Hepsiburada service key eksik.'),
            $this->check('User-Agent / entegratör kullanıcı', $userAgentPresent, $userAgentPresent ? 'User-Agent için kullanıcı alanı dolu.' : 'extraUser alanı boş. Yeni auth akışında önerilir.'),
            $this->check('Legacy kullanıcı/şifre', $hasLegacyAuth, $hasLegacyAuth ? 'Legacy auth fallback mevcut.' : 'Legacy auth alanları boş. Zorunlu değil.'),
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

        $baseUrlPresent = filled($store->connection?->api_base_url) || filled($store->store_url);
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
            $this->check('Mağaza / API URL', $baseUrlPresent, $baseUrlPresent ? 'WooCommerce mağaza URL tanımlı.' : 'WooCommerce mağaza URL eksik.'),
            $this->check('Consumer key', $consumerKeyPresent, $consumerKeyPresent ? 'Consumer key tanımlı.' : 'WooCommerce consumer key eksik.'),
            $this->check('Consumer secret', $consumerSecretPresent, $consumerSecretPresent ? 'Consumer secret tanımlı.' : 'WooCommerce consumer secret eksik.'),
            $this->check('Webhook secret', $webhookSecretPresent, $webhookSecretPresent ? 'Webhook secret tanımlı.' : 'Webhook secret boş. Okuma akışı için zorunlu değil, webhook için gereklidir.'),
            $this->check('Webhook-first akış', $webhookModeHealthy, $webhookModeMessage),
            $this->check('Webhook topic seti', $topicSetHealthy, $topicSetMessage),
        ];

        if (!$baseUrlPresent) {
            $failures[] = 'WooCommerce mağaza URL veya API base URL eksik.';
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
            $this->check('Satıcı / mağaza kodu', $sellerIdPresent, $sellerIdPresent ? 'Satıcı / mağaza kodu tanımlı.' : 'Satıcı / mağaza kodu boş. Bazı akışlarda gerekli olabilir.'),
            $this->check('API base URL', $baseUrlPresent, $baseUrlPresent ? 'API base URL tanımlı.' : 'API base URL boş. Resmi endpoint doğrulanınca doldurulmalı.'),
        ];

        if (!$apiKeyPresent) {
            $failures[] = 'Koçtaş API key eksik.';
        }

        if (!$sellerIdPresent) {
            $warnings[] = 'Koçtaş mağaza / shop ID boş. Birden fazla shop erişiminde shop_id seçimi için gerekli olabilir.';
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
        $baseUrlPresent = filled($store->connection?->api_base_url);

        $checks = [
            $this->check('API key', $apiKeyPresent, $apiKeyPresent ? 'Pazarama API key tanımlı.' : 'Pazarama API key eksik.'),
            $this->check('API secret', $apiSecretPresent, $apiSecretPresent ? 'Pazarama API secret tanımlı.' : 'Pazarama API secret eksik.'),
            $this->check('Satıcı / mağaza kodu', $sellerIdPresent, $sellerIdPresent ? 'Satıcı / mağaza kodu tanımlı.' : 'Satıcı / mağaza kodu boş. Bazı akışlarda gerekli olabilir.'),
            $this->check('API base URL', $baseUrlPresent, $baseUrlPresent ? 'API base URL tanımlı.' : 'API base URL boş. Resmi endpoint doğrulanınca doldurulmalı.'),
        ];

        if (!$apiKeyPresent) {
            $failures[] = 'Pazarama API key eksik.';
        }

        if (!$apiSecretPresent) {
            $failures[] = 'Pazarama API secret eksik.';
        }

        if (!$sellerIdPresent) {
            $warnings[] = 'Pazarama mağaza / satıcı kodu boş. Sipariş eşleme sırasında gerekli olabilir.';
        }

        if (!$baseUrlPresent) {
            $warnings[] = 'Pazarama API base URL boş. Resmi endpoint bilgisi onaylandığında doldurulmalıdır.';
        }

        $warnings[] = 'Pazarama bağlayıcısı şimdilik güvenli skeleton aşamasında. Resmi doküman ve canlı credential gelmeden smoke test ile veri çekimi açılmayacaktır.';

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
        $apiSecretPresent = filled($credentials['api_secret'] ?? null);
        $sellerIdPresent = filled($store->seller_id);
        $baseUrlPresent = filled($store->connection?->api_base_url);

        $checks = [
            $this->check('API key', $apiKeyPresent, $apiKeyPresent ? 'Çiçeksepeti API key tanımlı.' : 'Çiçeksepeti API key eksik.'),
            $this->check('API secret', $apiSecretPresent, $apiSecretPresent ? 'Çiçeksepeti API secret tanımlı.' : 'Çiçeksepeti API secret eksik.'),
            $this->check('Satıcı / mağaza kodu', $sellerIdPresent, $sellerIdPresent ? 'Satıcı / mağaza kodu tanımlı.' : 'Satıcı / mağaza kodu boş. Bazı akışlarda gerekli olabilir.'),
            $this->check('API base URL', $baseUrlPresent, $baseUrlPresent ? 'API base URL tanımlı.' : 'API base URL boş. Resmi endpoint doğrulanınca doldurulmalı.'),
        ];

        if (!$apiKeyPresent) {
            $failures[] = 'Çiçeksepeti API key eksik.';
        }

        if (!$apiSecretPresent) {
            $failures[] = 'Çiçeksepeti API secret eksik.';
        }

        if (!$sellerIdPresent) {
            $warnings[] = 'Çiçeksepeti mağaza / satıcı kodu boş. Sipariş eşleme sırasında gerekli olabilir.';
        }

        if (!$baseUrlPresent) {
            $warnings[] = 'Çiçeksepeti API base URL boş. Resmi endpoint bilgisi onaylandığında doldurulmalıdır.';
        }

        $warnings[] = 'Çiçeksepeti bağlayıcısı şimdilik güvenli skeleton aşamasında. Resmi doküman ve canlı credential gelmeden smoke test ile veri çekimi açılmayacaktır.';

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
}
