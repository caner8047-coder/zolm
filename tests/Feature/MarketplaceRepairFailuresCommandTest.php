<?php

namespace Tests\Feature;

use App\Jobs\PushMarketplaceListingUpdateJob;
use App\Jobs\RunMarketplaceOrderActionJob;
use App\Jobs\SyncMarketplaceDataJob;
use App\Models\ChannelOrder;
use App\Models\ChannelOrderPackage;
use App\Models\IntegrationConnection;
use App\Models\IntegrationOrderActionRun;
use App\Models\IntegrationPushRun;
use App\Models\IntegrationSyncProfile;
use App\Models\IntegrationSyncRun;
use App\Models\IntegrationWebhookEvent;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MarketplaceRepairFailuresCommandTest extends TestCase
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

    public function test_it_lists_failed_records_in_dry_run_mode_without_queueing_anything(): void
    {
        Queue::fake();

        [$store] = $this->createMarketplaceGraph();

        IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'orders',
            'trigger_type' => 'manual',
            'status' => 'failed',
        ]);

        $this->artisan('marketplace:repair-failures', [
            '--store' => $store->id,
            '--type' => 'syncs',
            '--dry-run' => true,
        ])->assertExitCode(0);

        $this->assertSame(1, IntegrationSyncRun::query()->where('store_id', $store->id)->count());
        Queue::assertNothingPushed();
    }

    public function test_it_requeues_failed_records_for_all_types(): void
    {
        Queue::fake();

        [$store, $order, $package] = $this->createMarketplaceGraph();

        IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'orders',
            'trigger_type' => 'manual',
            'status' => 'failed',
            'notes_json' => ['last_error' => 'Sync hata'],
        ]);

        IntegrationPushRun::query()->create([
            'store_id' => $store->id,
            'push_type' => 'price',
            'status' => 'failed',
            'target_price' => 999.99,
            'currency' => 'TRY',
            'error_message' => 'Push hata',
        ]);

        IntegrationOrderActionRun::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'channel_order_package_id' => $package->id,
            'action_type' => 'package_common_label_get',
            'status' => 'failed',
            'request_context_json' => ['format' => 'PDF'],
            'error_message' => 'Aksiyon hata',
        ]);

        IntegrationWebhookEvent::query()->create([
            'store_id' => $store->id,
            'provider' => 'trendyol',
            'event_type' => 'order_created',
            'external_event_id' => 'WH-CMD-' . random_int(1000, 9999),
            'signature_valid' => true,
            'payload_json' => [
                'orderNumber' => $order->order_number,
                'shipmentPackageId' => $package->external_package_id,
            ],
            'received_at' => now(),
            'status' => 'failed',
            'error_message' => 'Webhook hata',
        ]);

        $this->artisan('marketplace:repair-failures', [
            '--store' => $store->id,
            '--type' => 'all',
            '--limit' => 10,
        ])->assertExitCode(0);

        $this->assertGreaterThan(1, IntegrationSyncRun::query()->where('store_id', $store->id)->count());
        $this->assertSame(2, IntegrationPushRun::query()->where('store_id', $store->id)->count());
        $this->assertSame(2, IntegrationOrderActionRun::query()->where('store_id', $store->id)->count());
        $this->assertDatabaseHas('integration_webhook_events', [
            'store_id' => $store->id,
            'status' => 'replayed',
        ]);

        Queue::assertPushed(SyncMarketplaceDataJob::class, 2);
        Queue::assertPushed(PushMarketplaceListingUpdateJob::class, 1);
        Queue::assertPushed(RunMarketplaceOrderActionJob::class, 1);
    }

    /**
     * @return array{0: MarketplaceStore, 1: ChannelOrder, 2: ChannelOrderPackage}
     */
    protected function createMarketplaceGraph(): array
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Repair Ltd.',
            'tax_number' => '4' . $suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'ZEM REPAIR',
            'store_code' => 'RPR-' . $suffix,
            'seller_id' => 'RPR-' . $suffix,
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
                'seller_id' => $suffix,
                'api_key' => 'test-key',
                'api_secret' => 'test-secret',
            ],
            'webhook_secret' => 'secret',
            'api_base_url' => 'https://apigw.trendyol.com',
            'status' => 'configured',
        ]);

        IntegrationSyncProfile::query()->create(array_merge(
            ['store_id' => $store->id],
            IntegrationSyncProfile::defaults(),
        ));

        $order = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $legalEntity->id,
            'external_order_id' => 'CMD-ORDER-' . $suffix,
            'order_number' => 'CMD-ORDER-' . $suffix,
            'order_status' => 'Created',
            'customer_name' => 'Repair Test',
            'ordered_at' => now(),
        ]);

        $package = ChannelOrderPackage::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'external_package_id' => 'PKG-' . $suffix,
            'package_number' => 'PKG-' . $suffix,
            'package_status' => 'Created',
        ]);

        return [$store, $order, $package];
    }
}
