<?php

namespace Tests\Feature;

use App\Jobs\SyncMarketplaceDataJob;
use App\Livewire\MpProductsManager;
use App\Models\ChannelOrder;
use App\Models\IntegrationConnection;
use App\Models\IntegrationSyncRun;
use App\Models\IntegrationSyncProfile;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\MpOrder;
use App\Models\MpPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class MpProductsManagerGuidanceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.host', 'mysql');
        config()->set('database.connections.mysql.port', '3306');
        config()->set('database.connections.mysql.database', 'zolm');
        config()->set('database.connections.mysql.username', 'sail');
        config()->set('database.connections.mysql.password', 'password');
        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::setDefaultConnection('mysql');
    }

    public function test_it_shows_compact_guidance_band_in_products_view(): void
    {
        $user = User::factory()->create();
        ['store' => $store] = $this->createStoreGraph($user, 'PROD-GUIDE');

        IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'products',
            'trigger_type' => 'smoke_test',
            'status' => 'completed',
            'items_received' => 4,
            'notes_json' => [
                'smoke_test' => true,
                'diagnostics' => [
                    'missing_listing_id_count' => 3,
                    'missing_sale_price_count' => 4,
                    'missing_stock_quantity_count' => 2,
                    'warnings' => ['Listing alanlari eksik'],
                ],
            ],
        ]);

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->assertSee('Detaylar')
            ->assertSee('Listing tamlık alanları eksik')
            ->assertSee('Ürünler');
    }

    public function test_it_can_focus_products_list_from_top_guidance(): void
    {
        $user = User::factory()->create();
        ['store' => $store] = $this->createStoreGraph($user, 'PROD-FOCUS');

        IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'products',
            'trigger_type' => 'smoke_test',
            'status' => 'completed',
            'items_received' => 2,
            'notes_json' => [
                'smoke_test' => true,
                'diagnostics' => [
                    'missing_listing_id_count' => 2,
                    'missing_sale_price_count' => 1,
                ],
            ],
        ]);

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->call('focusTopGuidance')
            ->assertSet('marketplaceFilter', 'trendyol')
            ->assertSet('listingCoverageFilter', 'listed');
    }

    public function test_it_can_queue_products_sync_from_top_guidance(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        ['store' => $store] = $this->createStoreGraph($user, 'PROD-SYNC');

        IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'products',
            'trigger_type' => 'smoke_test',
            'status' => 'completed',
            'items_received' => 2,
            'notes_json' => [
                'smoke_test' => true,
                'diagnostics' => [
                    'missing_listing_id_count' => 2,
                ],
            ],
        ]);

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->call('syncTopGuidance');

        $this->assertDatabaseHas('integration_sync_runs', [
            'store_id' => $store->id,
            'sync_type' => 'products',
            'trigger_type' => 'manual',
            'status' => 'queued',
        ]);

        Queue::assertPushed(SyncMarketplaceDataJob::class);
    }

    public function test_it_redirects_to_orders_route_for_legacy_financial_projection_guidance(): void
    {
        $user = User::factory()->create();
        ['store' => $store] = $this->createStoreGraph($user, 'PROD-LEGACY');

        IntegrationSyncProfile::query()->create(array_merge(
            ['store_id' => $store->id],
            IntegrationSyncProfile::defaultsForMarketplace('trendyol'),
        ));

        $period = MpPeriod::query()->create([
            'user_id' => $user->id,
            'seller_id' => $store->seller_id,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'marketplace' => 'trendyol',
            'status' => 'completed',
        ]);

        $orderNumber = 'PROD-LEGACY-' . random_int(100000, 999999);

        ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'external_order_id' => $orderNumber,
            'order_number' => $orderNumber,
            'order_status' => 'Teslim Edildi',
            'ordered_at' => now()->subDay(),
        ]);

        MpOrder::query()->create([
            'period_id' => $period->id,
            'order_number' => $orderNumber,
            'status' => 'Teslim Edildi',
            'order_date' => now()->subDay(),
            'payment_date' => now()->subHours(6)->toDateString(),
            'gross_amount' => 1000,
            'net_hakedis' => 800,
        ]);

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->assertSee('Legacy finans satirlari V2 ledger\'a tasinmamis')
            ->call('syncTopGuidance')
            ->assertRedirect(route('mp.orders', ['storeFilter' => $store->id]));
    }

    /**
     * @return array{store: MarketplaceStore, connection: IntegrationConnection}
     */
    protected function createStoreGraph(User $user, string $prefix): array
    {
        $suffix = (string) random_int(100000, 999999);

        $entity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Products Guidance Ltd.',
            'tax_number' => '5' . $suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'ZEM ' . $prefix,
            'store_code' => $prefix . '-' . $suffix,
            'seller_id' => $prefix . '-' . $suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $connection = IntegrationConnection::query()->create([
            'store_id' => $store->id,
            'provider' => 'trendyol',
            'auth_type' => 'api_key_secret',
            'credentials_encrypted' => [
                'api_key' => 'key',
                'api_secret' => 'secret',
            ],
            'api_base_url' => 'https://apigw.trendyol.com/',
            'status' => 'configured',
        ]);

        return compact('store', 'connection');
    }
}
