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
use App\Services\Marketplace\MarketplaceHealthRetryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MarketplaceHealthRetryBatchTest extends TestCase
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

    public function test_it_retries_failed_operations_in_batches(): void
    {
        Queue::fake();

        [$user, $store, $order, $package] = $this->createMarketplaceGraph();

        $syncRun = IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'orders',
            'trigger_type' => 'manual',
            'status' => 'failed',
            'notes_json' => ['last_error' => 'Sync hata'],
        ]);

        $pushRun = IntegrationPushRun::query()->create([
            'store_id' => $store->id,
            'triggered_by' => $user->id,
            'push_type' => 'price',
            'status' => 'failed',
            'target_price' => 1234.56,
            'currency' => 'TRY',
            'error_message' => 'Push hata',
        ]);

        $actionRun = IntegrationOrderActionRun::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'channel_order_package_id' => $package->id,
            'triggered_by' => $user->id,
            'action_type' => 'package_common_label_get',
            'status' => 'failed',
            'request_context_json' => ['format' => 'PDF'],
            'error_message' => 'Aksiyon hata',
        ]);

        $webhookEvent = IntegrationWebhookEvent::query()->create([
            'store_id' => $store->id,
            'provider' => 'trendyol',
            'event_type' => 'order_created',
            'external_event_id' => 'WH-' . random_int(1000, 9999),
            'signature_valid' => true,
            'payload_json' => [
                'orderNumber' => $order->order_number,
                'shipmentPackageId' => $package->external_package_id,
            ],
            'received_at' => now(),
            'status' => 'failed',
            'error_message' => 'Webhook hata',
        ]);

        $service = app(MarketplaceHealthRetryService::class);

        $retriedSyncs = $service->retrySyncBatch([$syncRun]);
        $retriedPushes = $service->retryPushBatch([$pushRun], $user->id);
        $retriedActions = $service->retryOrderActionBatch([$actionRun], $user->id);
        $replayedWebhooks = $service->replayWebhookBatch([$webhookEvent]);

        $this->assertCount(1, $retriedSyncs);
        $this->assertCount(1, $retriedPushes);
        $this->assertCount(1, $retriedActions);
        $this->assertCount(1, $replayedWebhooks);

        $this->assertDatabaseHas('integration_sync_runs', [
            'id' => $retriedSyncs->first()->id,
            'store_id' => $store->id,
            'trigger_type' => 'retry',
            'status' => 'queued',
        ]);

        $this->assertDatabaseHas('integration_push_runs', [
            'id' => $retriedPushes->first()->id,
            'store_id' => $store->id,
            'push_type' => 'price',
            'status' => 'queued',
        ]);

        $this->assertDatabaseHas('integration_order_action_runs', [
            'id' => $retriedActions->first()->id,
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'channel_order_package_id' => $package->id,
            'status' => 'queued',
        ]);

        $this->assertDatabaseHas('integration_webhook_events', [
            'id' => $webhookEvent->id,
            'status' => 'replayed',
        ]);

        Queue::assertPushed(SyncMarketplaceDataJob::class, 2);
        Queue::assertPushed(PushMarketplaceListingUpdateJob::class, 1);
        Queue::assertPushed(RunMarketplaceOrderActionJob::class, 1);
    }

    public function test_it_replays_trendyol_webhook_with_nested_new_shipment_package_id(): void
    {
        Queue::fake();

        [, $store, $order, $package] = $this->createMarketplaceGraph();

        $webhookEvent = IntegrationWebhookEvent::query()->create([
            'store_id' => $store->id,
            'provider' => 'trendyol',
            'event_type' => 'order_updated',
            'external_event_id' => 'WH-NESTED-' . random_int(1000, 9999),
            'signature_valid' => true,
            'payload_json' => [
                'orderNumber' => $order->order_number,
                'shipmentPackage' => [
                    'shipmentPackageId' => $package->external_package_id,
                ],
            ],
            'received_at' => now(),
            'status' => 'failed',
            'error_message' => 'Webhook hata',
        ]);

        $syncRun = app(MarketplaceHealthRetryService::class)->replayWebhook($webhookEvent);

        $this->assertSame(
            [$package->external_package_id],
            data_get($syncRun->notes_json, 'options.shipment_package_ids')
        );

        Queue::assertPushed(SyncMarketplaceDataJob::class, 1);
    }

    /**
     * @return array{0: User, 1: MarketplaceStore, 2: ChannelOrder, 3: ChannelOrderPackage}
     */
    protected function createMarketplaceGraph(): array
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Retry Ltd.',
            'tax_number' => '6' . $suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'ZEM RETRY',
            'store_code' => 'RET-' . $suffix,
            'seller_id' => 'RET-' . $suffix,
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
            'external_order_id' => 'RETRY-ORDER-' . $suffix,
            'order_number' => 'RETRY-ORDER-' . $suffix,
            'order_status' => 'Created',
            'customer_name' => 'Retry Test',
            'ordered_at' => now(),
        ]);

        $package = ChannelOrderPackage::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'external_package_id' => 'PKG-' . $suffix,
            'package_number' => 'PKG-' . $suffix,
            'package_status' => 'Created',
        ]);

        return [$user, $store, $order, $package];
    }
}
