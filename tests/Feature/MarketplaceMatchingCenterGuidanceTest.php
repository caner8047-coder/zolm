<?php

namespace Tests\Feature;

use App\Jobs\SyncMarketplaceDataJob;
use App\Livewire\MarketplaceMatchingCenter;
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

class MarketplaceMatchingCenterGuidanceTest extends TestCase
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

    public function test_it_shows_compact_guidance_band_in_matching_view(): void
    {
        $user = User::factory()->create();
        ['store' => $store] = $this->createStoreGraph($user, 'MATCH-GUIDE');

        IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'orders',
            'trigger_type' => 'smoke_test',
            'status' => 'completed',
            'items_received' => 3,
            'notes_json' => [
                'smoke_test' => true,
                'diagnostics' => [
                    'missing_stock_code_count' => 6,
                    'missing_barcode_count' => 2,
                    'warnings' => ['Eşleşme alanları eksik'],
                ],
            ],
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceMatchingCenter::class)
            ->assertSee('Bugün önce bunlara bak')
            ->assertSee('Ürün eşleşme alanları eksik')
            ->assertSee('Eşleştirme Merkezi');
    }

    public function test_it_can_focus_matching_list_from_top_guidance(): void
    {
        $user = User::factory()->create();
        ['store' => $store] = $this->createStoreGraph($user, 'MATCH-FOCUS');

        IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'orders',
            'trigger_type' => 'smoke_test',
            'status' => 'completed',
            'items_received' => 2,
            'notes_json' => [
                'smoke_test' => true,
                'diagnostics' => [
                    'missing_stock_code_count' => 3,
                    'missing_barcode_count' => 1,
                ],
            ],
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceMatchingCenter::class)
            ->call('focusTopGuidance')
            ->assertSet('marketplaceFilter', 'trendyol')
            ->assertSet('storeFilter', (string) $store->id)
            ->assertSet('statusFilter', 'pending');
    }

    public function test_it_can_queue_products_sync_from_top_guidance(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        ['store' => $store] = $this->createStoreGraph($user, 'MATCH-SYNC');

        IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'orders',
            'trigger_type' => 'smoke_test',
            'status' => 'completed',
            'items_received' => 2,
            'notes_json' => [
                'smoke_test' => true,
                'diagnostics' => [
                    'missing_stock_code_count' => 4,
                ],
            ],
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceMatchingCenter::class)
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
        ['store' => $store] = $this->createStoreGraph($user, 'MATCH-LEGACY');

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

        $orderNumber = 'MATCH-LEGACY-' . random_int(100000, 999999);

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

        Livewire::test(MarketplaceMatchingCenter::class)
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
            'name' => 'Zem Matching Guidance Ltd.',
            'tax_number' => '6' . $suffix,
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
