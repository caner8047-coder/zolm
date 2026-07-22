<?php

namespace Tests\Feature;

use App\Jobs\SyncMarketplaceDataJob;
use App\Models\IntegrationConnection;
use App\Models\IntegrationSyncProfile;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MarketplaceWebhookControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.host', 'mysql');
        config()->set('database.connections.mysql.port', '3306');
        config()->set('database.connections.mysql.database', $this->mysqlTestDatabaseName());
        config()->set('database.connections.mysql.username', 'sail');
        config()->set('database.connections.mysql.password', 'password');
        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::setDefaultConnection('mysql');
    }

    public function test_it_routes_woocommerce_product_webhooks_to_product_sync(): void
    {
        Queue::fake();

        $store = $this->createWooStore('WOO-WEBHOOK-PRODUCT');
        $payload = ['id' => 501, 'sku' => 'WC-STK-501'];
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $secret = 'woo-secret';
        $signature = base64_encode(hash_hmac('sha256', $json, $secret, true));

        $response = $this->call(
            'POST',
            route('marketplace.webhooks.receive', ['provider' => 'woocommerce', 'store' => $store->id]),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_WC_WEBHOOK_SIGNATURE' => $signature,
                'HTTP_X_WC_WEBHOOK_TOPIC' => 'product.updated',
                'HTTP_X_WC_WEBHOOK_DELIVERY_ID' => 'woo-product-1',
            ],
            $json
        );

        $response->assertOk()
            ->assertJson([
                'ok' => true,
                'status' => 'received',
                'sync_type' => 'products',
                'sync_debounced' => false,
            ]);

        $this->assertDatabaseHas('integration_sync_runs', [
            'store_id' => $store->id,
            'sync_type' => 'products',
            'trigger_type' => 'webhook',
            'status' => 'queued',
        ]);

        Queue::assertPushed(SyncMarketplaceDataJob::class, 1);
    }

    public function test_it_debounces_bursty_woocommerce_order_webhooks(): void
    {
        Queue::fake();

        $store = $this->createWooStore('WOO-WEBHOOK-DEBOUNCE');
        $secret = 'woo-secret';

        $firstPayload = ['id' => 901, 'status' => 'processing'];
        $firstJson = json_encode($firstPayload, JSON_THROW_ON_ERROR);
        $firstSignature = base64_encode(hash_hmac('sha256', $firstJson, $secret, true));

        $firstResponse = $this->call(
            'POST',
            route('marketplace.webhooks.receive', ['provider' => 'woocommerce', 'store' => $store->id]),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_WC_WEBHOOK_SIGNATURE' => $firstSignature,
                'HTTP_X_WC_WEBHOOK_TOPIC' => 'order.updated',
                'HTTP_X_WC_WEBHOOK_DELIVERY_ID' => 'woo-order-1',
            ],
            $firstJson
        );

        $firstResponse->assertOk()
            ->assertJson([
                'sync_type' => 'webhook_refresh',
                'sync_debounced' => false,
            ]);

        $secondPayload = ['id' => 901, 'status' => 'completed'];
        $secondJson = json_encode($secondPayload, JSON_THROW_ON_ERROR);
        $secondSignature = base64_encode(hash_hmac('sha256', $secondJson, $secret, true));

        $secondResponse = $this->call(
            'POST',
            route('marketplace.webhooks.receive', ['provider' => 'woocommerce', 'store' => $store->id]),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_WC_WEBHOOK_SIGNATURE' => $secondSignature,
                'HTTP_X_WC_WEBHOOK_TOPIC' => 'order.updated',
                'HTTP_X_WC_WEBHOOK_DELIVERY_ID' => 'woo-order-2',
            ],
            $secondJson
        );

        $secondResponse->assertOk()
            ->assertJson([
                'status' => 'debounced',
                'sync_type' => null,
                'sync_debounced' => true,
            ]);

        $this->assertSame(1, $store->syncRuns()->count());
        $this->assertDatabaseHas('integration_webhook_events', [
            'store_id' => $store->id,
            'provider' => 'woocommerce',
            'status' => 'debounced',
        ]);

        Queue::assertPushed(SyncMarketplaceDataJob::class, 1);
    }

    public function test_it_ignores_woocommerce_topics_not_enabled_in_profile(): void
    {
        Queue::fake();

        $store = $this->createWooStore('WOO-WEBHOOK-IGNORE');
        $store->syncProfile()->update([
            'extra_settings' => [
                'webhook_topics' => ['order.updated'],
            ],
        ]);

        $payload = ['id' => 777, 'sku' => 'WC-777'];
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = base64_encode(hash_hmac('sha256', $json, 'woo-secret', true));

        $response = $this->call(
            'POST',
            route('marketplace.webhooks.receive', ['provider' => 'woocommerce', 'store' => $store->id]),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_WC_WEBHOOK_SIGNATURE' => $signature,
                'HTTP_X_WC_WEBHOOK_TOPIC' => 'product.updated',
                'HTTP_X_WC_WEBHOOK_DELIVERY_ID' => 'woo-ignore-1',
            ],
            $json
        );

        $response->assertOk()
            ->assertJson([
                'status' => 'ignored',
                'sync_type' => null,
                'sync_ignored' => true,
            ]);

        $this->assertSame(0, $store->syncRuns()->count());
        $this->assertDatabaseHas('integration_webhook_events', [
            'store_id' => $store->id,
            'provider' => 'woocommerce',
            'status' => 'ignored',
        ]);

        Queue::assertNothingPushed();
    }

    public function test_it_ignores_shopify_topics_not_enabled_in_profile(): void
    {
        Queue::fake();

        $store = $this->createShopifyStore('SHOPIFY-WEBHOOK-IGNORE');
        $store->syncProfile()->update([
            'extra_settings' => [
                'webhook_topics' => ['orders/create'],
            ],
        ]);

        $payload = ['id' => 501, 'title' => 'Shopify Product'];
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $secret = 'shopify_app_secret';
        $signature = base64_encode(hash_hmac('sha256', $json, $secret, true));

        $response = $this->call(
            'POST',
            route('marketplace.webhooks.receive', ['provider' => 'shopify', 'store' => $store->id]),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SHOPIFY_HMAC_SHA256' => $signature,
                'HTTP_X_SHOPIFY_TOPIC' => 'products/update',
                'HTTP_X_SHOPIFY_WEBHOOK_ID' => 'shopify-ignore-1',
                'HTTP_X_SHOPIFY_SHOP_DOMAIN' => 'ornek.myshopify.com',
            ],
            $json
        );

        $response->assertOk()
            ->assertJson([
                'status' => 'ignored',
                'sync_type' => null,
                'sync_ignored' => true,
            ]);

        $this->assertSame(0, $store->syncRuns()->count());
        $this->assertDatabaseHas('integration_webhook_events', [
            'store_id' => $store->id,
            'provider' => 'shopify',
            'status' => 'ignored',
        ]);

        Queue::assertNothingPushed();
    }

    public function test_it_routes_trendyol_webhooks_using_shipment_package_id_as_external_event_id(): void
    {
        Queue::fake();

        $store = $this->createTrendyolStore('TY-WEBHOOK-PACKAGE');
        $payload = [
            'eventType' => 'order_created',
            'shipmentPackageId' => 'TY-PKG-501',
            'orderNumber' => 'TY-ORD-501',
            'id' => 'legacy-event-id',
        ];
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $secret = 'trendyol-secret';
        $signature = hash_hmac('sha256', $json, $secret);

        $response = $this->call(
            'POST',
            route('marketplace.webhooks.receive', ['provider' => 'trendyol', 'store' => $store->id]),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_TRENDYOL_SIGNATURE' => $signature,
            ],
            $json
        );

        $response->assertOk()
            ->assertJson([
                'ok' => true,
                'status' => 'received',
                'sync_type' => 'webhook_refresh',
                'sync_debounced' => false,
            ]);

        $this->assertDatabaseHas('integration_sync_runs', [
            'store_id' => $store->id,
            'sync_type' => 'webhook_refresh',
            'trigger_type' => 'webhook',
            'status' => 'queued',
        ]);

        $this->assertDatabaseHas('integration_webhook_events', [
            'store_id' => $store->id,
            'provider' => 'trendyol',
            'external_event_id' => 'TY-PKG-501',
            'event_type' => 'order_created',
            'status' => 'received',
            'signature_valid' => true,
        ]);

        Queue::assertPushed(SyncMarketplaceDataJob::class, 1);
    }

    protected function createWooStore(string $prefix): MarketplaceStore
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Woo Webhook Ltd.',
            'tax_number' => '3'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'woocommerce',
            'store_name' => $prefix,
            'store_code' => $prefix.'-'.$suffix,
            'seller_id' => $prefix.'-'.$suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        IntegrationConnection::query()->create([
            'store_id' => $store->id,
            'provider' => 'woocommerce',
            'auth_type' => 'consumer_key_secret',
            'credentials_encrypted' => [
                'api_key' => 'ck_test',
                'api_secret' => 'cs_test',
                'store_url' => 'https://woo.example.com',
            ],
            'webhook_secret' => 'woo-secret',
            'api_base_url' => 'https://woo.example.com',
            'status' => 'configured',
        ]);

        IntegrationSyncProfile::query()->create(array_merge(
            ['store_id' => $store->id],
            IntegrationSyncProfile::defaultsForMarketplace('woocommerce'),
            ['webhook_enabled' => true]
        ));

        return $store;
    }

    protected function createTrendyolStore(string $prefix): MarketplaceStore
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Trendyol Webhook Ltd.',
            'tax_number' => '5'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => $prefix,
            'store_code' => $prefix.'-'.$suffix,
            'seller_id' => $prefix.'-'.$suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        IntegrationConnection::query()->create([
            'store_id' => $store->id,
            'provider' => 'trendyol',
            'auth_type' => 'api_key_secret',
            'credentials_encrypted' => [
                'seller_id' => $prefix.'-'.$suffix,
                'api_key' => 'ty_test_key',
                'api_secret' => 'ty_test_secret',
            ],
            'webhook_secret' => 'trendyol-secret',
            'api_base_url' => 'https://apigw.trendyol.com',
            'status' => 'configured',
        ]);

        IntegrationSyncProfile::query()->create(array_merge(
            ['store_id' => $store->id],
            IntegrationSyncProfile::defaultsForMarketplace('trendyol'),
            ['webhook_enabled' => true]
        ));

        return $store;
    }

    protected function createShopifyStore(string $prefix): MarketplaceStore
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Shopify Webhook Ltd.',
            'tax_number' => '4'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'shopify',
            'store_name' => $prefix,
            'store_code' => $prefix.'-'.$suffix,
            'seller_id' => $prefix.'-'.$suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        IntegrationConnection::query()->create([
            'store_id' => $store->id,
            'provider' => 'shopify',
            'auth_type' => 'access_token_app_secret',
            'credentials_encrypted' => [
                'api_key' => 'shpat_test_token',
                'api_secret' => 'shopify_app_secret',
                'store_url' => 'https://ornek.myshopify.com',
            ],
            'webhook_secret' => 'shopify_app_secret',
            'api_base_url' => 'https://ornek.myshopify.com',
            'status' => 'configured',
        ]);

        IntegrationSyncProfile::query()->create(array_merge(
            ['store_id' => $store->id],
            IntegrationSyncProfile::defaultsForMarketplace('shopify'),
            ['webhook_enabled' => true]
        ));

        return $store;
    }
}
