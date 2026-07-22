<?php

namespace Tests\Feature;

use App\Models\ChannelListing;
use App\Models\ChannelProduct;
use App\Models\IntegrationConnection;
use App\Models\MarketplaceStore;
use App\Models\User;
use App\Services\Marketplace\Connectors\IdeaSoftConnector;
use App\Services\Marketplace\MarketplaceConnectorManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IdeaSoftConnectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_resolves_ideasoft_with_truthful_capabilities(): void
    {
        $connector = app(MarketplaceConnectorManager::class)->resolve('ideasoft');

        $this->assertInstanceOf(IdeaSoftConnector::class, $connector);
        $this->assertTrue($connector->capabilities()['orders']);
        $this->assertTrue($connector->capabilities()['products']);
        $this->assertTrue($connector->capabilities()['finance']);
        $this->assertTrue($connector->capabilities()['webhooks']);
        $this->assertTrue($connector->capabilities()['price_push']);
        $this->assertTrue($connector->capabilities()['stock_push']);
        $this->assertTrue($connector->capabilities()['claims']);
        $this->assertFalse($connector->capabilities()['questions']);
    }

    public function test_it_verifies_connection_with_bearer_token(): void
    {
        Http::fake([
            'https://zem.myideasoft.com/admin-api/orders*' => Http::response([]),
        ]);

        $result = app(IdeaSoftConnector::class)->testConnection($this->makeStore());

        $this->assertTrue($result['ok']);
        Http::assertSent(fn ($request) => $request->url() === 'https://zem.myideasoft.com/admin-api/orders?limit=1&page=1&sort=-id'
            && $request->hasHeader('Authorization', 'Bearer access-token'));
    }

    public function test_it_pulls_and_normalizes_orders_products_payments_and_refunds(): void
    {
        Http::fake(function ($request) {
            $path = parse_url($request->url(), PHP_URL_PATH);

            return match ($path) {
                '/admin-api/orders' => Http::response([[
                    'id' => 1001,
                    'customerFirstname' => 'Ayşe',
                    'customerSurname' => 'Test',
                    'customerEmail' => 'ayse@example.com',
                    'customerPhone' => '5551112233',
                    'currency' => 'TRY',
                    'status' => 'fulfilled',
                    'paymentStatus' => 'success',
                    'shippingCompanyName' => 'Yurtiçi Kargo',
                    'shippingTrackingCode' => 'YK123',
                    'createdAt' => '2026-07-20T10:00:00+03:00',
                    'updatedAt' => '2026-07-21T10:00:00+03:00',
                    'shippingAddress' => ['country' => 'Türkiye', 'location' => 'İstanbul', 'subLocation' => 'Kadıköy'],
                    'billingAddress' => ['firstname' => 'Ayşe', 'surname' => 'Test', 'invoiceType' => 'individual'],
                    'orderItems' => [[
                        'id' => 7,
                        'productName' => 'Masa',
                        'productSku' => 'MASA-1',
                        'productBarcode' => '8690001',
                        'productPrice' => 1500,
                        'productQuantity' => 2,
                        'productTax' => 20,
                        'discount' => 100,
                    ]],
                ]]),
                '/admin-api/products' => Http::response([[
                    'id' => 20,
                    'name' => 'Sandalye',
                    'fullName' => 'Ahşap Sandalye',
                    'sku' => 'SAN-ANA',
                    'status' => 1,
                    'brand' => ['name' => 'ZOLM'],
                    'categories' => [['name' => 'Mobilya'], ['name' => 'Sandalye']],
                    'children' => [[
                        'id' => 21,
                        'fullName' => 'Ahşap Sandalye / Ceviz',
                        'sku' => 'SAN-CEVIZ',
                        'barcode' => '8690021',
                        'stockAmount' => 8,
                        'price1' => 2499.90,
                        'status' => 1,
                    ]],
                    'currency' => ['abbr' => 'TRY'],
                    'images' => [['filename' => 'sandalye.jpg']],
                    'createdAt' => '2026-07-01T12:00:00+03:00',
                ]]),
                '/admin-api/payments' => Http::response([[
                    'id' => 301,
                    'transactionId' => 'TX-301',
                    'status' => 'approved',
                    'amount' => 2499.90,
                    'currency' => 'TRY',
                    'paymentTypeName' => 'Kredi Kartı',
                    'paymentProviderName' => 'Iyzico',
                    'createdAt' => '2026-07-20T10:05:00+03:00',
                ]]),
                '/admin-api/order_refund_requests' => Http::response([[
                    'id' => 401,
                    'code' => 'REF-401',
                    'status' => 'waiting_for_approval',
                    'cancellationReason' => 'Hasarlı ürün',
                    'createdAt' => '2026-07-21T11:00:00+03:00',
                    'order' => ['id' => 1001, 'customerFirstname' => 'Ayşe', 'customerSurname' => 'Test'],
                    'refundRequestItems' => [[
                        'id' => 402,
                        'quantity' => 1,
                        'orderItem' => ['id' => 7, 'productName' => 'Masa', 'productSku' => 'MASA-1'],
                    ]],
                ]]),
                default => Http::response([], 404),
            };
        });

        $connector = app(IdeaSoftConnector::class);
        $store = $this->makeStore();
        $orders = $connector->pullOrders($store);
        $products = $connector->pullProducts($store);
        $finance = $connector->pullFinancialEvents($store);
        $claims = $connector->pullClaims($store);

        $this->assertSame('shipped', data_get($orders, 'items.0.order.order_status'));
        $this->assertSame('YK123', data_get($orders, 'items.0.package.cargo_tracking_number'));
        $this->assertSame('MASA-1', data_get($orders, 'items.0.items.0.stock_code'));
        $this->assertSame('21', data_get($products, 'items.0.product.external_product_id'));
        $this->assertSame('20', data_get($products, 'items.0.product.external_parent_id'));
        $this->assertSame(8, data_get($products, 'items.0.listing.stock_quantity'));
        $this->assertSame('ideasoft_payment', data_get($finance, 'items.0.event_source'));
        $this->assertSame(2499.90, data_get($finance, 'items.0.amount'));
        $this->assertSame('401', data_get($claims, 'items.0.external_claim_id'));
        $this->assertSame('MASA-1', data_get($claims, 'items.0.items.0.stock_code'));
    }

    public function test_it_refreshes_expired_access_token_and_persists_rotated_refresh_token(): void
    {
        $user = User::factory()->create(['role' => 'operator']);
        $store = MarketplaceStore::factory()->create([
            'user_id' => $user->id,
            'marketplace' => 'ideasoft',
            'seller_id' => 'idea-refresh',
        ]);
        IntegrationConnection::create([
            'store_id' => $store->id,
            'provider' => 'ideasoft',
            'auth_type' => 'authorization_code',
            'api_base_url' => 'https://zem.myideasoft.com',
            'credentials_encrypted' => [
                'api_key' => 'client-id',
                'api_secret' => 'client-secret',
                'access_token' => 'expired-token',
                'refresh_token' => 'refresh-old',
                'token_expires_at' => now()->subMinute()->toIso8601String(),
                'store_url' => 'https://zem.myideasoft.com',
            ],
            'status' => 'configured',
        ]);
        $store->load('connection');

        Http::fake([
            'https://zem.myideasoft.com/oauth/v2/token' => Http::response([
                'access_token' => 'fresh-token',
                'refresh_token' => 'refresh-new',
                'expires_in' => 86400,
            ]),
            'https://zem.myideasoft.com/admin-api/orders*' => Http::response([]),
        ]);

        $result = app(IdeaSoftConnector::class)->testConnection($store);

        $this->assertTrue($result['ok']);
        $this->assertSame('refresh-new', $store->connection->fresh()->credentials_encrypted['refresh_token']);
        Http::assertSent(fn ($request) => $request->url() === 'https://zem.myideasoft.com/oauth/v2/token'
            && data_get($request->data(), 'grant_type') === 'refresh_token');
    }

    public function test_it_pushes_price_and_stock_with_product_update_scope(): void
    {
        Http::fake([
            'https://zem.myideasoft.com/admin-api/products/21' => Http::response(['id' => 21, 'status' => 1]),
        ]);
        $connector = app(IdeaSoftConnector::class);
        $listing = $this->makeListing();

        $price = $connector->pushPrice($listing, 2799.90);
        $stock = $connector->pushStock($listing, 12);

        $this->assertSame('completed', $price['status']);
        $this->assertSame('completed', $stock['status']);
        Http::assertSent(fn ($request) => data_get($request->data(), 'price1') === 2799.90);
        Http::assertSent(fn ($request) => data_get($request->data(), 'stockAmount') === 12);
    }

    public function test_it_verifies_official_base64_webhook_hmac(): void
    {
        $body = json_encode(['id' => 1001, 'topic' => 'order/update'], JSON_THROW_ON_ERROR);
        $signature = base64_encode(hash_hmac('sha256', $body, 'client-secret', true));
        $request = Request::create('/webhook', 'POST', [], [], [], [], $body);
        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('X-Ideashop-Hmac-Sha256', $signature);
        $connector = app(IdeaSoftConnector::class);
        $connection = $this->makeStore()->connection;

        $this->assertTrue($connector->verifyWebhookSignature($request, $connection));
        $metadata = $connector->extractWebhookMetadata($request);
        $this->assertSame('order/update', $metadata['event_type']);
        $this->assertSame('1001', $metadata['external_event_id']);
    }

    public function test_oauth_redirect_and_callback_exchange_code_and_store_tokens(): void
    {
        config(['marketplace.features.integrations_enabled' => true]);
        $user = User::factory()->create(['role' => 'operator', 'is_active' => true]);
        $store = MarketplaceStore::factory()->create([
            'user_id' => $user->id,
            'marketplace' => 'ideasoft',
            'seller_id' => 'idea-oauth',
        ]);
        IntegrationConnection::create([
            'store_id' => $store->id,
            'provider' => 'ideasoft',
            'auth_type' => 'authorization_code',
            'api_base_url' => 'https://zem.myideasoft.com',
            'credentials_encrypted' => [
                'api_key' => 'client-id',
                'api_secret' => 'client-secret',
                'store_url' => 'https://zem.myideasoft.com',
            ],
            'status' => 'draft',
        ]);

        Http::fake([
            'https://zem.myideasoft.com/oauth/v2/token' => Http::response([
                'access_token' => 'oauth-access',
                'refresh_token' => 'oauth-refresh',
                'expires_in' => 86400,
                'scope' => 'order_read product_read payment_read order_refund_request_read',
            ]),
        ]);

        $redirect = $this->actingAs($user)->get(route('mp.integrations.ideasoft.authorize', $store));
        $redirect->assertRedirectContains('https://zem.myideasoft.com/panel/auth?');
        parse_str((string) parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $query);

        $callback = $this->get(route('mp.integrations.ideasoft.callback', [
            'state' => $query['state'],
            'code' => 'authorization-code',
        ]));

        $callback->assertRedirect(route('mp.integrations', ['store' => $store->id, 'ideasoft_oauth' => 'success']));
        $credentials = $store->connection->fresh()->credentials_encrypted;
        $this->assertSame('oauth-access', $credentials['access_token']);
        $this->assertSame('oauth-refresh', $credentials['refresh_token']);
        $this->assertSame('configured', $store->connection->fresh()->status);
        Http::assertSent(fn ($request) => data_get($request->data(), 'grant_type') === 'authorization_code'
            && data_get($request->data(), 'code') === 'authorization-code'
            && data_get($request->data(), 'redirect_uri') === route('mp.integrations.ideasoft.callback'));
    }

    public function test_oauth_callback_does_not_expose_provider_error_details(): void
    {
        config(['marketplace.features.integrations_enabled' => true]);
        $user = User::factory()->create(['role' => 'operator', 'is_active' => true]);
        $store = MarketplaceStore::factory()->create([
            'user_id' => $user->id,
            'marketplace' => 'ideasoft',
            'seller_id' => 'idea-oauth-failure',
        ]);
        IntegrationConnection::create([
            'store_id' => $store->id,
            'provider' => 'ideasoft',
            'auth_type' => 'authorization_code',
            'api_base_url' => 'https://zem.myideasoft.com',
            'credentials_encrypted' => [
                'api_key' => 'client-id',
                'api_secret' => 'client-secret',
                'store_url' => 'https://zem.myideasoft.com',
            ],
            'status' => 'draft',
        ]);
        Http::fake([
            'https://zem.myideasoft.com/oauth/v2/token' => Http::response([
                'error' => 'invalid_client',
                'internal_detail' => 'provider-secret-diagnostic',
            ], 401),
        ]);

        $redirect = $this->actingAs($user)->get(route('mp.integrations.ideasoft.authorize', $store));
        parse_str((string) parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $query);
        $callback = $this->get(route('mp.integrations.ideasoft.callback', [
            'state' => $query['state'],
            'code' => 'invalid-code',
        ]));

        $callback->assertRedirect(route('mp.integrations', ['store' => $store->id, 'ideasoft_oauth' => 'failed']));
        $callback->assertSessionHas('error', 'IdeaSoft token alınamadı. Bilgileri ve Redirect URI ayarını kontrol edip tekrar deneyin.');
        $this->assertStringNotContainsString('provider-secret-diagnostic', (string) session('error'));
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'ideasoft_oauth_failed',
            'entity_type' => 'MarketplaceStore',
            'entity_id' => $store->id,
        ]);
    }

    protected function makeStore(): MarketplaceStore
    {
        $store = new MarketplaceStore([
            'marketplace' => 'ideasoft',
            'store_name' => 'IdeaSoft Test',
            'seller_id' => 'idea-test',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
        ]);
        $connection = new IntegrationConnection([
            'provider' => 'ideasoft',
            'auth_type' => 'authorization_code',
            'credentials_encrypted' => [
                'api_key' => 'client-id',
                'api_secret' => 'client-secret',
                'access_token' => 'access-token',
                'refresh_token' => 'refresh-token',
                'token_expires_at' => now()->addHour()->toIso8601String(),
                'store_url' => 'https://zem.myideasoft.com',
            ],
            'api_base_url' => 'https://zem.myideasoft.com',
            'status' => 'configured',
        ]);
        $connection->id = 20;
        $store->setRelation('connection', $connection);

        return $store;
    }

    protected function makeListing(): ChannelListing
    {
        $store = $this->makeStore();
        $product = new ChannelProduct([
            'external_product_id' => '21',
            'external_parent_id' => '20',
            'stock_code' => 'SAN-CEVIZ',
            'raw_payload' => ['id' => 20, 'variant' => ['id' => 21]],
        ]);
        $listing = new ChannelListing([
            'listing_id' => '21',
            'currency' => 'TRY',
        ]);
        $listing->id = 1;
        $listing->setRelation('store', $store);
        $listing->setRelation('channelProduct', $product);

        return $listing;
    }
}
