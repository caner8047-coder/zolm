<?php

namespace Tests\Feature;

use App\Models\IntegrationConnection;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeactivatePlaceholderMarketplaceStoresCommandTest extends TestCase
{
    /** @var array<int, int> */
    protected array $createdStoreIds = [];

    /** @var array<int, int> */
    protected array $createdEntityIds = [];

    /** @var array<int, int> */
    protected array $createdUserIds = [];

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

    protected function tearDown(): void
    {
        if ($this->createdStoreIds !== []) {
            IntegrationConnection::query()->whereIn('store_id', $this->createdStoreIds)->delete();
            MarketplaceStore::query()->whereKey($this->createdStoreIds)->delete();
        }

        if ($this->createdEntityIds !== []) {
            LegalEntity::query()->whereKey($this->createdEntityIds)->delete();
        }

        if ($this->createdUserIds !== []) {
            User::query()->whereKey($this->createdUserIds)->delete();
        }

        parent::tearDown();
    }

    public function test_it_deactivates_only_placeholder_marketplace_stores(): void
    {
        $suffix = (string) random_int(100000, 999999);

        $placeholder = $this->createStore(
            marketplace: 'woocommerce',
            storeName: 'Placeholder Woo',
            sellerId: 'WOO-PLACEHOLDER-'.$suffix,
            connection: [
                'provider' => 'woocommerce',
                'auth_type' => 'api_key_secret',
                'api_base_url' => 'https://example.com/wp-json/wc/v3/',
                'credentials_encrypted' => [
                    'api_key' => 'ck_real',
                    'api_secret' => 'cs_real',
                ],
                'status' => 'configured',
            ],
        );

        $real = $this->createStore(
            marketplace: 'n11',
            storeName: 'Gerçek N11',
            sellerId: 'N11-REAL-'.$suffix,
            connection: [
                'provider' => 'n11',
                'auth_type' => 'api_key_secret',
                'api_base_url' => 'https://api.n11.com',
                'credentials_encrypted' => [
                    'api_key' => 'real-key',
                    'api_secret' => 'real-secret',
                ],
                'status' => 'configured',
            ],
        );

        $this->artisan('marketplace:deactivate-placeholder-stores', [
            '--all' => true,
        ])->assertExitCode(0);

        $this->assertFalse($placeholder->fresh()->is_active);
        $this->assertTrue($real->fresh()->is_active);
    }

    public function test_it_does_not_write_in_dry_run_mode(): void
    {
        $suffix = (string) random_int(100000, 999999);

        $placeholder = $this->createStore(
            marketplace: 'trendyol',
            storeName: 'Placeholder Trendyol',
            sellerId: 'SELLER-SECOND-'.$suffix,
            connection: [
                'provider' => 'trendyol',
                'auth_type' => 'api_key_secret',
                'api_base_url' => 'https://apigw.trendyol.com/',
                'credentials_encrypted' => [
                    'api_key' => 'test-key',
                    'api_secret' => 'test-secret',
                ],
                'status' => 'configured',
            ],
        );

        $this->artisan('marketplace:deactivate-placeholder-stores', [
            '--all' => true,
            '--dry-run' => true,
        ])->assertExitCode(0);

        $this->assertTrue($placeholder->fresh()->is_active);
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    protected function createStore(
        string $marketplace,
        string $storeName,
        string $sellerId,
        array $connection,
    ): MarketplaceStore {
        $user = User::factory()->create();
        $this->createdUserIds[] = $user->id;
        $suffix = (string) random_int(100000, 999999);

        $entity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => $storeName.' Ltd.',
            'tax_number' => '9'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
        $this->createdEntityIds[] = $entity->id;

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => $marketplace,
            'store_name' => $storeName,
            'store_code' => $storeName.'-'.$suffix,
            'seller_id' => $sellerId,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
        $this->createdStoreIds[] = $store->id;

        IntegrationConnection::query()->create(array_merge($connection, [
            'store_id' => $store->id,
        ]));

        return $store;
    }
}
