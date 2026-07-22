<?php

namespace Tests\Feature;

use App\Models\IntegrationConnection;
use App\Models\IntegrationSyncRun;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\User;
use App\Services\Marketplace\Contracts\MarketplaceConnector;
use App\Services\Marketplace\Contracts\PullsOrders;
use App\Services\Marketplace\Contracts\PullsProducts;
use App\Services\Marketplace\Contracts\TestsConnection;
use App\Services\Marketplace\MarketplaceConnectorManager;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

trait MarketplaceSmokeTestsConnectionFake
{
    public function testConnection(MarketplaceStore $store): array
    {
        return [
            'ok' => true,
            'message' => 'Bağlantı başarılı.',
            'meta' => ['provider' => $store->marketplace],
        ];
    }
}

class MarketplaceSmokeTestCommandTest extends TestCase
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

    public function test_it_can_persist_smoke_test_results_into_sync_runs(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Smoke Ltd.',
            'tax_number' => '6'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'ZEM SMOKE',
            'store_code' => 'SMOKE-'.$suffix,
            'seller_id' => 'SMOKE-'.$suffix,
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
                'api_key' => 'key',
                'api_secret' => 'secret',
                'store_front_code' => '12345',
            ],
            'api_base_url' => 'https://apigw.trendyol.com/',
            'status' => 'configured',
        ]);

        app()->bind(MarketplaceConnectorManager::class, fn () => new class extends MarketplaceConnectorManager
        {
            public function resolve(string $provider): MarketplaceConnector
            {
                return new class implements MarketplaceConnector, PullsOrders, TestsConnection
                {
                    use MarketplaceSmokeTestsConnectionFake;

                    public function providerKey(): string
                    {
                        return 'trendyol';
                    }

                    public function displayName(): string
                    {
                        return 'Trendyol Fake';
                    }

                    public function defaultApiBaseUrl(): ?string
                    {
                        return 'https://apigw.trendyol.com/';
                    }

                    public function capabilities(): array
                    {
                        return [
                            'orders' => true,
                            'products' => false,
                            'finance' => false,
                            'webhooks' => false,
                            'price_push' => false,
                            'stock_push' => false,
                        ];
                    }

                    public function pullOrders(MarketplaceStore $store, array $options = []): array
                    {
                        return [
                            'items' => [[
                                'external_order_id' => 'ORD-1',
                                'order_number' => 'ORD-1',
                                'external_package_id' => 'PKG-1',
                                'items' => [[
                                    'external_line_id' => 'LINE-1',
                                    'stock_code' => 'SKU-1',
                                    'barcode' => 'BAR-1',
                                    'quantity' => 1,
                                ]],
                            ]],
                            'meta' => [
                                'items_received' => 1,
                                'cursor_after' => $options['end_date'] ?? now()->toIso8601String(),
                            ],
                        ];
                    }
                };
            }
        });

        $this->artisan('marketplace:smoke-test', [
            'store' => $store->id,
            '--type' => 'orders',
            '--skip-connection' => true,
            '--persist' => true,
        ])->assertExitCode(0);

        $run = IntegrationSyncRun::query()
            ->where('store_id', $store->id)
            ->where('trigger_type', 'smoke_test')
            ->latest('id')
            ->first();

        $this->assertNotNull($run);
        $this->assertSame('orders', $run->sync_type);
        $this->assertSame('completed', $run->status);
        $this->assertSame(1, $run->items_received);
        $this->assertTrue((bool) data_get($run->notes_json, 'smoke_test'));
        $this->assertSame(1, (int) data_get($run->notes_json, 'diagnostics.package_count'));
    }

    public function test_it_skips_unsupported_types_when_all_is_requested(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Woo Smoke Ltd.',
            'tax_number' => '7'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'woocommerce',
            'store_name' => 'ZEM WOO SMOKE',
            'store_code' => 'WOO-SMOKE-'.$suffix,
            'seller_id' => 'WOO-'.$suffix,
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
            ],
            'api_base_url' => 'https://woo.example.com',
            'status' => 'configured',
        ]);

        app()->bind(MarketplaceConnectorManager::class, fn () => new class extends MarketplaceConnectorManager
        {
            public function resolve(string $provider): MarketplaceConnector
            {
                return new class implements MarketplaceConnector, PullsOrders, PullsProducts, TestsConnection
                {
                    use MarketplaceSmokeTestsConnectionFake;

                    public function providerKey(): string
                    {
                        return 'woocommerce';
                    }

                    public function displayName(): string
                    {
                        return 'WooCommerce Fake';
                    }

                    public function defaultApiBaseUrl(): ?string
                    {
                        return 'https://woo.example.com';
                    }

                    public function capabilities(): array
                    {
                        return [
                            'orders' => true,
                            'products' => true,
                            'finance' => false,
                            'webhooks' => true,
                            'price_push' => true,
                            'stock_push' => true,
                        ];
                    }

                    public function pullOrders(MarketplaceStore $store, array $options = []): array
                    {
                        return [
                            'items' => [[
                                'order' => [
                                    'external_order_id' => 'WOO-ORD-1',
                                    'order_number' => 'WOO-ORD-1',
                                ],
                                'package' => [
                                    'external_package_id' => 'WOO-PKG-1',
                                    'package_status' => 'processing',
                                ],
                                'items' => [[
                                    'external_line_id' => 'WOO-LINE-1',
                                    'stock_code' => 'WOO-SKU-1',
                                    'barcode' => 'WOO-BAR-1',
                                    'quantity' => 1,
                                ]],
                            ]],
                            'meta' => [
                                'items_received' => 1,
                                'cursor_after' => $options['end_date'] ?? now()->toIso8601String(),
                            ],
                        ];
                    }

                    public function pullProducts(MarketplaceStore $store, array $options = []): array
                    {
                        return [
                            'items' => [[
                                'product' => [
                                    'external_product_id' => 'WOO-PROD-1',
                                    'stock_code' => 'WOO-SKU-1',
                                    'barcode' => 'WOO-BAR-1',
                                ],
                                'listing' => [
                                    'listing_id' => 'WOO-LIST-1',
                                    'sale_price' => 499.90,
                                    'stock_quantity' => 8,
                                ],
                            ]],
                            'meta' => [
                                'items_received' => 1,
                                'cursor_after' => $options['end_date'] ?? now()->toIso8601String(),
                            ],
                        ];
                    }
                };
            }
        });

        $this->artisan('marketplace:smoke-test', [
            'store' => $store->id,
            '--type' => 'all',
            '--skip-connection' => true,
            '--persist' => true,
        ])->assertExitCode(0);

        $runs = IntegrationSyncRun::query()
            ->where('store_id', $store->id)
            ->where('trigger_type', 'smoke_test')
            ->orderBy('sync_type')
            ->get();

        $this->assertCount(2, $runs);
        $this->assertSame(['orders', 'products'], $runs->pluck('sync_type')->all());
    }
}
