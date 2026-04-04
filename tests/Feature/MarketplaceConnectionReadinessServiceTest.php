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

    public function test_it_marks_pazarama_as_ready_with_warnings_in_skeleton_mode_when_api_credentials_exist(): void
    {
        $store = $this->makeStore('pazarama', 'pazarama-test', [
            'api_key' => 'pazarama_key',
            'api_secret' => 'pazarama_secret',
        ], '');

        $result = app(MarketplaceConnectionReadinessService::class)->inspect($store);
        $summary = app(MarketplaceConnectionReadinessService::class)->inspectCollection([$store]);

        $this->assertTrue($result['is_ready']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertTrue(collect($result['warnings'])->contains(fn (string $warning) => str_contains(mb_strtolower($warning), 'skeleton')));
        $this->assertSame(1, $summary['totals']['warning']);
        $this->assertSame('warning', $summary['rows'][0]['state']);
    }

    public function test_it_marks_amazon_as_ready_with_warnings_in_skeleton_mode_when_api_credentials_exist(): void
    {
        $store = $this->makeStore('amazon', 'amazon-test', [
            'api_key' => 'amazon_key',
            'api_secret' => 'amazon_secret',
        ], '');

        $result = app(MarketplaceConnectionReadinessService::class)->inspect($store);
        $summary = app(MarketplaceConnectionReadinessService::class)->inspectCollection([$store]);

        $this->assertTrue($result['is_ready']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertTrue(collect($result['warnings'])->contains(fn (string $warning) => str_contains(mb_strtolower($warning), 'skeleton')));
        $this->assertSame(1, $summary['totals']['warning']);
        $this->assertSame('warning', $summary['rows'][0]['state']);
    }

    public function test_it_marks_ciceksepeti_as_ready_with_warnings_in_skeleton_mode_when_api_credentials_exist(): void
    {
        $store = $this->makeStore('ciceksepeti', 'ciceksepeti-test', [
            'api_key' => 'ciceksepeti_key',
            'api_secret' => 'ciceksepeti_secret',
        ], '');

        $result = app(MarketplaceConnectionReadinessService::class)->inspect($store);
        $summary = app(MarketplaceConnectionReadinessService::class)->inspectCollection([$store]);

        $this->assertTrue($result['is_ready']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertTrue(collect($result['warnings'])->contains(fn (string $warning) => str_contains(mb_strtolower($warning), 'skeleton')));
        $this->assertSame(1, $summary['totals']['warning']);
        $this->assertSame('warning', $summary['rows'][0]['state']);
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
