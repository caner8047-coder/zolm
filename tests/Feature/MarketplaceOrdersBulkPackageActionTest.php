<?php

namespace Tests\Feature;

use App\Jobs\RunMarketplaceOrderActionJob;
use App\Livewire\MarketplaceOrders;
use App\Models\ChannelOrder;
use App\Models\ChannelOrderPackage;
use App\Models\IntegrationConnection;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class MarketplaceOrdersBulkPackageActionTest extends TestCase
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

    public function test_it_queues_bulk_package_action_from_orders_component(): void
    {
        Queue::fake();

        [$user, $store, $order, $package] = $this->createMarketplaceOrderGraph();

        $this->actingAs($user);

        Livewire::test(MarketplaceOrders::class)
            ->set('selectedPackageIds', [(string) $package->id])
            ->set('bulkPackageActionType', 'package_common_label_get')
            ->call('runBulkPackageAction')
            ->assertSet('selectedPackageIds', [])
            ->assertSet('bulkPackageActionType', '')
            ->assertSet('actionMessageTone', 'success');

        $this->assertDatabaseHas('integration_order_action_runs', [
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'channel_order_package_id' => $package->id,
            'action_type' => 'package_common_label_get',
            'status' => 'queued',
            'triggered_by' => $user->id,
        ]);

        Queue::assertPushed(RunMarketplaceOrderActionJob::class);
    }

    /**
     * @return array{0: User, 1: MarketplaceStore, 2: ChannelOrder, 3: ChannelOrderPackage}
     */
    protected function createMarketplaceOrderGraph(): array
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Test Ltd.',
            'tax_number' => '2' . $suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'ZEM BULK',
            'store_code' => 'ZEM-BULK-' . $suffix,
            'seller_id' => 'B' . $suffix,
            'status' => 'active',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        IntegrationConnection::query()->create([
            'store_id' => $store->id,
            'provider' => 'trendyol',
            'auth_type' => 'api_key_secret',
            'credentials_encrypted' => [
                'seller_id' => 'B' . $suffix,
                'api_key' => 'test-key',
                'api_secret' => 'test-secret',
            ],
            'webhook_secret' => 'secret',
            'api_base_url' => 'https://apigw.trendyol.com',
            'status' => 'configured',
        ]);

        $order = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $legalEntity->id,
            'external_order_id' => 'TY-BULK-ORDER-' . $suffix,
            'order_number' => 'TY-BULK-ORDER-' . $suffix,
            'order_status' => 'Created',
            'customer_name' => 'Toplu Test',
            'ordered_at' => now(),
        ]);

        $package = ChannelOrderPackage::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'external_package_id' => 'PKG-BULK-' . $suffix,
            'package_number' => 'PKG-BULK-' . $suffix,
            'package_status' => 'Created',
            'cargo_tracking_number' => 'TRK-BULK-' . $suffix,
        ]);

        return [$user, $store, $order, $package];
    }
}
