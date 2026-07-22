<?php

namespace Tests\Feature;

use App\Models\IntegrationConnection;
use App\Models\IntegrationSyncProfile;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\MarketplaceConnectionReadinessService;
use Tests\TestCase;

class MarketplaceConnectionReadinessServiceTest extends TestCase
{
    public function test_it_marks_trendyol_ready_when_required_fields_exist(): void
    {
        $store = $this->makeStore('trendyol', '12345', [
            'api_key' => 'key',
            'api_secret' => 'secret',
        ], 'https://apigw.trendyol.com/');

        $result = app(MarketplaceConnectionReadinessService::class)->inspect($store);

        $this->assertTrue($result['is_ready']);
        $this->assertEmpty($result['failures']);
    }

    public function test_it_marks_hepsiburada_ready_with_service_key_and_user_agent(): void
    {
        $store = $this->makeStore('hepsiburada', '654321', [
            'api_key' => 'service-key',
            'extra_user' => 'zem_dev',
        ], 'https://oms-external.hepsiburada.com/');

        $result = app(MarketplaceConnectionReadinessService::class)->inspect($store);

        $this->assertTrue($result['is_ready']);
        $this->assertEmpty($result['failures']);
        $this->assertNotEmpty($result['checks']);
    }

    public function test_it_marks_hepsiburada_not_ready_when_merchant_id_and_auth_are_missing(): void
    {
        $store = $this->makeStore('hepsiburada', null, [], 'https://oms-external.hepsiburada.com/');

        $result = app(MarketplaceConnectionReadinessService::class)->inspect($store);

        $this->assertFalse($result['is_ready']);
        $this->assertNotEmpty($result['failures']);
    }

    public function test_it_marks_connection_not_ready_when_last_live_verification_failed(): void
    {
        $store = $this->makeStore('trendyol', '12345', [
            'api_key' => 'key',
            'api_secret' => 'secret',
        ], 'https://apigw.trendyol.com/');

        $store->connection->last_verified_at = now();
        $store->connection->last_error = '401 Unauthorized';

        $result = app(MarketplaceConnectionReadinessService::class)->inspect($store);

        $this->assertFalse($result['is_ready']);
        $this->assertTrue(collect($result['failures'])->contains(
            fn (string $failure) => str_contains($failure, '401 Unauthorized')
        ));
    }

    public function test_it_keeps_connection_ready_when_last_live_verification_only_hit_ciceksepeti_rate_limit(): void
    {
        $store = $this->makeStore('ciceksepeti', '1500041287', [
            'api_key' => 'ciceksepeti_key',
            'extra_user' => 'ZOLM',
        ], '');

        $store->connection->last_verified_at = now();
        $store->connection->last_error = 'HTTP request returned status code 400: {"Message":"Limit aşımı! Bu endpointe farklı istekleri 5 saniyede 1 kez atabilirsiniz. Kalan Süre: 2 saniye"}';

        $result = app(MarketplaceConnectionReadinessService::class)->inspect($store);

        $this->assertTrue($result['is_ready']);
        $this->assertEmpty($result['failures']);
        $this->assertTrue(collect($result['warnings'])->contains(
            fn (string $warning) => str_contains($warning, 'geçici istek limitine takıldı')
        ));
    }

    public function test_it_keeps_pazarama_ready_when_last_live_verification_was_stale_404(): void
    {
        $store = $this->makeStore('pazarama', 'pazarama-test', [
            'api_key' => 'pazarama_key',
            'api_secret' => 'pazarama_secret',
        ], 'https://isortagimapi.pazarama.com');

        $store->connection->last_verified_at = now();
        $store->connection->last_error = 'HTTP request returned status code 404';

        $result = app(MarketplaceConnectionReadinessService::class)->inspect($store);

        $this->assertTrue($result['is_ready']);
        $this->assertEmpty($result['failures']);
        $this->assertTrue(collect($result['warnings'])->contains(
            fn (string $warning) => str_contains($warning, 'Pazarama canlı doğrulaması 404')
        ));
    }

    public function test_it_marks_woocommerce_ready_with_consumer_credentials(): void
    {
        $store = $this->makeStore('woocommerce', 'woo-test', [
            'api_key' => 'ck_test',
            'api_secret' => 'cs_test',
        ], 'https://shop.example.com');

        $store->store_url = 'https://shop.example.com';
        $store->connection->webhook_secret = 'woo-secret';

        $result = app(MarketplaceConnectionReadinessService::class)->inspect($store);

        $this->assertTrue($result['is_ready']);
        $this->assertEmpty($result['failures']);
    }

    public function test_it_requires_ideasoft_oauth_tokens_after_client_credentials_are_saved(): void
    {
        $draft = $this->makeStore('ideasoft', null, [
            'api_key' => 'idea-client-id',
            'api_secret' => 'idea-client-secret',
            'store_url' => 'https://zem.myideasoft.com',
        ], 'https://zem.myideasoft.com');
        $draftResult = app(MarketplaceConnectionReadinessService::class)->inspect($draft);

        $this->assertFalse($draftResult['is_ready']);
        $this->assertTrue(collect($draftResult['failures'])->contains(
            fn (string $failure) => str_contains($failure, 'OAuth')
        ));

        $ready = $this->makeStore('ideasoft', null, [
            'api_key' => 'idea-client-id',
            'api_secret' => 'idea-client-secret',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'store_url' => 'https://zem.myideasoft.com',
        ], 'https://zem.myideasoft.com');
        $readyResult = app(MarketplaceConnectionReadinessService::class)->inspect($ready);

        $this->assertTrue($readyResult['is_ready']);
        $this->assertEmpty($readyResult['failures']);
    }

    public function test_it_marks_woocommerce_ready_when_store_url_is_only_in_credentials(): void
    {
        $store = $this->makeStore('woocommerce', 'woo-test', [
            'api_key' => 'ck_test',
            'api_secret' => 'cs_test',
            'store_url' => 'https://shop.example.com',
        ], '');

        $result = app(MarketplaceConnectionReadinessService::class)->inspect($store);

        $this->assertTrue($result['is_ready']);
        $this->assertEmpty($result['failures']);
    }

    public function test_it_warns_when_woocommerce_webhook_topics_are_missing_while_webhook_is_enabled(): void
    {
        $store = $this->makeStore('woocommerce', 'woo-topic-test', [
            'api_key' => 'ck_test',
            'api_secret' => 'cs_test',
        ], 'https://shop.example.com');

        $store->store_url = 'https://shop.example.com';
        $store->connection->webhook_secret = 'woo-secret';
        $store->setRelation('syncProfile', new IntegrationSyncProfile([
            'webhook_enabled' => true,
            'extra_settings' => [
                'webhook_topics' => [],
            ],
        ]));

        $service = app(MarketplaceConnectionReadinessService::class);
        $result = $service->inspect($store);
        $summary = $service->inspectCollection([$store]);

        $this->assertTrue($result['is_ready']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertTrue(collect($result['warnings'])->contains(fn (string $warning) => str_contains(mb_strtolower($warning), 'topic')));
        $this->assertSame('warning', $summary['rows'][0]['state']);
    }

    public function test_it_warns_when_unsupported_woocommerce_finance_sync_is_enabled_in_profile(): void
    {
        $store = $this->makeStore('woocommerce', 'woo-finance-test', [
            'api_key' => 'ck_test',
            'api_secret' => 'cs_test',
        ], 'https://shop.example.com');

        $store->store_url = 'https://shop.example.com';
        $store->connection->webhook_secret = 'woo-secret';
        $store->setRelation('syncProfile', new IntegrationSyncProfile([
            'finance_enabled' => true,
            'orders_enabled' => true,
            'products_enabled' => true,
            'webhook_enabled' => true,
        ]));

        $result = app(MarketplaceConnectionReadinessService::class)->inspect($store);

        $this->assertTrue($result['is_ready']);
        $this->assertTrue(collect($result['warnings'])->contains(
            fn (string $warning) => str_contains(mb_strtolower($warning), 'finans sync')
                && str_contains(mb_strtolower($warning), 'capability pasif')
        ));
    }

    public function test_it_marks_woocommerce_not_ready_when_placeholder_url_is_used(): void
    {
        $store = $this->makeStore('woocommerce', 'woo-test', [
            'api_key' => 'ck_test',
            'api_secret' => 'cs_test',
        ], 'https://example.com/wp-json/wc/v3/');

        $result = app(MarketplaceConnectionReadinessService::class)->inspect($store);

        $this->assertFalse($result['is_ready']);
        $this->assertTrue(collect($result['failures'])->contains(
            fn (string $failure) => str_contains(mb_strtolower($failure), 'placeholder')
        ));
    }

    public function test_it_marks_shopify_ready_with_warnings_when_webhook_secrets_are_missing(): void
    {
        $store = $this->makeStore('shopify', 'shopify-test', [
            'api_key' => 'shpat_test_token',
            'api_secret' => 'shopify_app_secret',
            'store_url' => 'https://ornek.myshopify.com',
        ], 'https://ornek.myshopify.com');

        $result = app(MarketplaceConnectionReadinessService::class)->inspect($store);
        $summary = app(MarketplaceConnectionReadinessService::class)->inspectCollection([$store]);

        $this->assertTrue($result['is_ready']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertSame(1, $summary['totals']['warning']);
        $this->assertSame('warning', $summary['rows'][0]['state']);
    }

    public function test_it_warns_when_shopify_webhook_topics_are_missing_while_webhook_is_enabled(): void
    {
        $store = $this->makeStore('shopify', 'shopify-topic-test', [
            'api_key' => 'shpat_test_token',
            'api_secret' => 'shopify_app_secret',
            'store_url' => 'https://ornek.myshopify.com',
        ], 'https://ornek.myshopify.com');

        $store->store_url = 'https://ornek.myshopify.com';
        $store->connection->webhook_secret = 'shopify_app_secret';
        $store->setRelation('syncProfile', new IntegrationSyncProfile([
            'webhook_enabled' => true,
            'extra_settings' => [
                'webhook_topics' => [],
            ],
        ]));

        $service = app(MarketplaceConnectionReadinessService::class);
        $result = $service->inspect($store);
        $summary = $service->inspectCollection([$store]);

        $this->assertTrue($result['is_ready']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertTrue(collect($result['warnings'])->contains(fn (string $warning) => str_contains(mb_strtolower($warning), 'shopify topic')));
        $this->assertSame('warning', $summary['rows'][0]['state']);
    }

    public function test_it_marks_n11_as_ready_with_rest_credentials(): void
    {
        $store = $this->makeStore('n11', 'n11-test', [
            'api_key' => 'n11_key',
            'api_secret' => 'n11_secret',
        ], '');

        $result = app(MarketplaceConnectionReadinessService::class)->inspect($store);
        $summary = app(MarketplaceConnectionReadinessService::class)->inspectCollection([$store]);

        $this->assertTrue($result['is_ready']);
        $this->assertEmpty($result['failures']);
        $this->assertFalse(collect($result['warnings'])->contains(fn (string $warning) => str_contains(mb_strtolower($warning), 'skeleton')));
        $this->assertSame(1, $summary['totals']['ready']);
        $this->assertSame('ready', $summary['rows'][0]['state']);
    }

    public function test_it_marks_koctas_as_ready_with_mirakl_credentials(): void
    {
        $store = $this->makeStore('koctas', '778', [
            'api_key' => 'koctas_key',
            'api_secret' => 'koctas_secret',
        ], '');

        $result = app(MarketplaceConnectionReadinessService::class)->inspect($store);
        $summary = app(MarketplaceConnectionReadinessService::class)->inspectCollection([$store]);

        $this->assertTrue($result['is_ready']);
        $this->assertEmpty($result['failures']);
        $this->assertFalse(collect($result['warnings'])->contains(fn (string $warning) => str_contains(mb_strtolower($warning), 'skeleton')));
        $this->assertSame(1, $summary['totals']['ready']);
        $this->assertSame('ready', $summary['rows'][0]['state']);
    }

    public function test_it_marks_koctas_as_ready_with_only_api_key(): void
    {
        $store = $this->makeStore('koctas', null, [
            'api_key' => 'koctas_key',
        ], '');

        $result = app(MarketplaceConnectionReadinessService::class)->inspect($store);
        $summary = app(MarketplaceConnectionReadinessService::class)->inspectCollection([$store]);

        $this->assertTrue($result['is_ready']);
        $this->assertEmpty($result['warnings']);
        $this->assertEmpty($result['failures']);
        $this->assertSame(1, $summary['totals']['ready']);
        $this->assertSame('ready', $summary['rows'][0]['state']);
    }

    public function test_it_marks_pazarama_as_ready_when_client_credentials_exist(): void
    {
        $store = $this->makeStore('pazarama', 'pazarama-test', [
            'api_key' => 'pazarama_key',
            'api_secret' => 'pazarama_secret',
        ], '');

        $result = app(MarketplaceConnectionReadinessService::class)->inspect($store);
        $summary = app(MarketplaceConnectionReadinessService::class)->inspectCollection([$store]);

        $this->assertTrue($result['is_ready']);
        $this->assertEmpty($result['warnings']);
        $this->assertSame(1, $summary['totals']['ready']);
        $this->assertSame('ready', $summary['rows'][0]['state']);
    }

    public function test_it_keeps_amazon_not_ready_until_sp_api_connector_is_implemented(): void
    {
        $store = $this->makeStore('amazon', 'amazon-test', [
            'api_key' => 'amazon_key',
            'api_secret' => 'amazon_secret',
        ], '');

        $result = app(MarketplaceConnectionReadinessService::class)->inspect($store);
        $summary = app(MarketplaceConnectionReadinessService::class)->inspectCollection([$store]);

        $this->assertFalse($result['is_ready']);
        $this->assertNotEmpty($result['failures']);
        $this->assertTrue(collect($result['failures'])->contains(fn (string $failure) => str_contains(mb_strtolower($failure), 'sp-api')));
        $this->assertSame(1, $summary['totals']['missing']);
        $this->assertSame('missing', $summary['rows'][0]['state']);
    }

    public function test_it_marks_ciceksepeti_as_ready_when_api_key_and_seller_id_exist(): void
    {
        $store = $this->makeStore('ciceksepeti', 'ciceksepeti-test', [
            'api_key' => 'ciceksepeti_key',
            'extra_user' => 'ZOLM',
        ], '');

        $result = app(MarketplaceConnectionReadinessService::class)->inspect($store);
        $summary = app(MarketplaceConnectionReadinessService::class)->inspectCollection([$store]);

        $this->assertTrue($result['is_ready']);
        $this->assertEmpty($result['warnings']);
        $this->assertSame(1, $summary['totals']['ready']);
        $this->assertSame('ready', $summary['rows'][0]['state']);
    }

    public function test_it_marks_ikas_ready_with_client_credentials_and_warns_about_live_scopes(): void
    {
        $store = $this->makeStore('ikas', null, [
            'api_key' => 'ikas-client-id',
            'api_secret' => 'ikas-client-secret',
        ], 'https://api.myikas.com/api/v2/admin/graphql');

        $result = app(MarketplaceConnectionReadinessService::class)->inspect($store);
        $summary = app(MarketplaceConnectionReadinessService::class)->inspectCollection([$store]);

        $this->assertTrue($result['is_ready']);
        $this->assertEmpty($result['failures']);
        $this->assertTrue(collect($result['warnings'])->contains(fn (string $warning) => str_contains($warning, 'Orders, Products')));
        $this->assertSame(1, $summary['totals']['warning']);
        $this->assertSame('warning', $summary['rows'][0]['state']);
    }

    public function test_it_marks_ticimax_ready_with_store_url_and_membership_code(): void
    {
        $store = $this->makeStore('ticimax', null, [
            'api_secret' => 'ticimax-member-code',
            'store_url' => 'https://magaza.example',
        ], 'https://magaza.example');

        $result = app(MarketplaceConnectionReadinessService::class)->inspect($store);
        $summary = app(MarketplaceConnectionReadinessService::class)->inspectCollection([$store]);

        $this->assertTrue($result['is_ready']);
        $this->assertEmpty($result['failures']);
        $this->assertTrue(collect($result['warnings'])->contains(fn (string $warning) => str_contains($warning, 'Detaylı Web Servis')));
        $this->assertSame('warning', $summary['rows'][0]['state']);
    }

    public function test_it_rejects_ticimax_without_membership_code_or_https_store_url(): void
    {
        $store = $this->makeStore('ticimax', null, [], 'http://magaza.example');

        $result = app(MarketplaceConnectionReadinessService::class)->inspect($store);

        $this->assertFalse($result['is_ready']);
        $this->assertTrue(collect($result['failures'])->contains(fn (string $failure) => str_contains($failure, 'Üye Kodu')));
        $this->assertTrue(collect($result['failures'])->contains(fn (string $failure) => str_contains($failure, 'HTTPS')));
    }

    public function test_it_marks_tsoft_ready_with_store_url_and_service_user(): void
    {
        $store = $this->makeStore('tsoft', null, [
            'api_key' => 'zolm-service',
            'api_secret' => 'service-password',
            'store_url' => 'https://magaza.example',
        ], 'https://magaza.example');

        $result = app(MarketplaceConnectionReadinessService::class)->inspect($store);
        $summary = app(MarketplaceConnectionReadinessService::class)->inspectCollection([$store]);

        $this->assertTrue($result['is_ready']);
        $this->assertEmpty($result['failures']);
        $this->assertTrue(collect($result['warnings'])->contains(fn (string $warning) => str_contains($warning, 'REST1')));
        $this->assertSame('warning', $summary['rows'][0]['state']);
    }

    public function test_it_rejects_tsoft_without_service_password_or_https_store_url(): void
    {
        $store = $this->makeStore('tsoft', null, ['api_key' => 'zolm-service'], 'http://magaza.example');

        $result = app(MarketplaceConnectionReadinessService::class)->inspect($store);

        $this->assertFalse($result['is_ready']);
        $this->assertTrue(collect($result['failures'])->contains(fn (string $failure) => str_contains($failure, 'parolası')));
        $this->assertTrue(collect($result['failures'])->contains(fn (string $failure) => str_contains($failure, 'HTTPS')));
    }

    public function test_it_marks_magento_paas_ready_with_store_url_and_access_token(): void
    {
        $store = $this->makeStore('magento', null, [
            'api_secret' => 'integration-access-token',
            'store_url' => 'https://magento.example',
            'store_front_code' => 'all',
            'extra_user' => 'default',
        ], 'https://magento.example');

        $result = app(MarketplaceConnectionReadinessService::class)->inspect($store);
        $summary = app(MarketplaceConnectionReadinessService::class)->inspectCollection([$store]);

        $this->assertTrue($result['is_ready']);
        $this->assertEmpty($result['failures']);
        $this->assertTrue(collect($result['warnings'])->contains(fn (string $warning) => str_contains($warning, 'Credit Memo')));
        $this->assertSame('warning', $summary['rows'][0]['state']);
    }

    public function test_it_rejects_magento_saas_or_missing_access_token(): void
    {
        $store = $this->makeStore('magento', null, [
            'store_front_code' => 'all',
        ], 'https://server.api.commerce.adobe.com');

        $result = app(MarketplaceConnectionReadinessService::class)->inspect($store);

        $this->assertFalse($result['is_ready']);
        $this->assertTrue(collect($result['failures'])->contains(fn (string $failure) => str_contains($failure, 'Access Token')));
        $this->assertTrue(collect($result['failures'])->contains(fn (string $failure) => str_contains($failure, 'IMS')));
    }

    public function test_it_summarizes_store_readiness_states(): void
    {
        $readyStore = $this->makeStore('trendyol', '12345', [
            'api_key' => 'key',
            'api_secret' => 'secret',
            'store_front_code' => '12345',
        ], 'https://apigw.trendyol.com/');
        $readyStore->id = 10;

        $warningStore = $this->makeStore('hepsiburada', '654321', [
            'api_key' => 'service-key',
        ], 'https://oms-external.hepsiburada.com/');
        $warningStore->id = 11;

        $missingStore = $this->makeStore('woocommerce', null, [], 'https://shop.example.com');
        $missingStore->id = 12;

        $summary = app(MarketplaceConnectionReadinessService::class)->inspectCollection([
            $readyStore,
            $warningStore,
            $missingStore,
        ]);

        $this->assertSame(3, $summary['totals']['stores']);
        $this->assertSame(1, $summary['totals']['ready']);
        $this->assertSame(1, $summary['totals']['warning']);
        $this->assertSame(1, $summary['totals']['missing']);
        $this->assertSame('warning', $summary['rows'][1]['state']);
        $this->assertSame('missing', $summary['rows'][2]['state']);
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    protected function makeStore(string $provider, ?string $sellerId, array $credentials, string $apiBaseUrl): MarketplaceStore
    {
        $store = new MarketplaceStore([
            'marketplace' => $provider,
            'store_name' => strtoupper($provider).' Test',
            'seller_id' => $sellerId,
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
        ]);

        $connection = new IntegrationConnection([
            'provider' => $provider,
            'auth_type' => 'api_key_secret',
            'credentials_encrypted' => $credentials,
            'api_base_url' => $apiBaseUrl,
            'status' => 'configured',
        ]);

        $store->setRelation('connection', $connection);

        return $store;
    }
}
