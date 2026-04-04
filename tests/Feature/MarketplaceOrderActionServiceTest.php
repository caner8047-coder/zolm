<?php

namespace Tests\Feature;

use App\Jobs\RunMarketplaceOrderActionJob;
use App\Models\ChannelOrder;
use App\Models\ChannelOrderPackage;
use App\Models\IntegrationConnection;
use App\Models\IntegrationOrderActionRun;
use App\Models\IntegrationSyncProfile;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\User;
use App\Services\Marketplace\MarketplaceHealthRetryService;
use App\Services\Marketplace\MarketplaceOrderActionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MarketplaceOrderActionServiceTest extends TestCase
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

    public function test_it_queues_a_package_level_order_action(): void
    {
        Queue::fake();

        [$user, $store, $order, $package] = $this->createMarketplaceOrderGraph();

        $actionRun = app(MarketplaceOrderActionService::class)->queue(
            $order,
            'package_common_label_get',
            ['format' => 'ZPL'],
            $user->id,
            $package,
        );

        $this->assertDatabaseHas('integration_order_action_runs', [
            'id' => $actionRun->id,
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'channel_order_package_id' => $package->id,
            'triggered_by' => $user->id,
            'action_type' => 'package_common_label_get',
            'status' => 'queued',
        ]);

        Queue::assertPushed(RunMarketplaceOrderActionJob::class, function (RunMarketplaceOrderActionJob $job) use ($actionRun) {
            return $job->actionRunId === $actionRun->id;
        });
    }

    public function test_it_coalesces_into_existing_queued_order_action(): void
    {
        Queue::fake();

        [$user, $store, $order, $package] = $this->createMarketplaceOrderGraph();

        $existingRun = IntegrationOrderActionRun::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'channel_order_package_id' => $package->id,
            'triggered_by' => $user->id,
            'action_type' => 'package_invoice_link',
            'status' => 'queued',
            'attempt_count' => 0,
            'request_context_json' => [
                'invoice_link' => 'https://example.com/old.pdf',
            ],
        ]);

        $result = app(MarketplaceOrderActionService::class)->dispatch(
            $order,
            'package_invoice_link',
            ['invoice_link' => 'https://example.com/new.pdf'],
            $user->id,
            $package,
        );

        $existingRun->refresh();

        $this->assertFalse($result['created']);
        $this->assertTrue($result['coalesced']);
        $this->assertSame($existingRun->id, $result['action_run']->id);
        $this->assertSame('https://example.com/new.pdf', data_get($existingRun->request_context_json, 'invoice_link'));
        $this->assertSame(1, (int) data_get($existingRun->request_context_json, '_merged_action_count'));

        Queue::assertNothingPushed();
    }

    public function test_it_does_not_open_new_action_when_processing_run_exists(): void
    {
        Queue::fake();

        [$user, $store, $order, $package] = $this->createMarketplaceOrderGraph();

        $processingRun = IntegrationOrderActionRun::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'channel_order_package_id' => $package->id,
            'triggered_by' => $user->id,
            'action_type' => 'package_common_label_get',
            'status' => 'processing',
            'attempt_count' => 1,
            'request_context_json' => ['format' => 'ZPL'],
            'started_at' => now(),
        ]);

        $result = app(MarketplaceOrderActionService::class)->dispatch(
            $order,
            'package_common_label_get',
            ['format' => 'PDF'],
            $user->id,
            $package,
        );

        $this->assertFalse($result['created']);
        $this->assertFalse($result['coalesced']);
        $this->assertTrue($result['busy']);
        $this->assertSame($processingRun->id, $result['action_run']->id);
        $this->assertSame(1, IntegrationOrderActionRun::query()->where('store_id', $store->id)->count());

        Queue::assertNothingPushed();
    }

    public function test_retry_service_clones_order_action_with_retry_metadata(): void
    {
        Queue::fake();

        [$user, $store, $order, $package] = $this->createMarketplaceOrderGraph();

        $originalRun = IntegrationOrderActionRun::create([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'channel_order_package_id' => $package->id,
            'triggered_by' => $user->id,
            'action_type' => 'package_invoice_link',
            'status' => 'failed',
            'attempt_count' => 2,
            'request_context_json' => [
                'invoice_link' => 'https://example.com/invoice.pdf',
                'invoice_number' => 'FTR-001',
            ],
            'error_message' => 'Örnek hata',
        ]);

        $retryRun = app(MarketplaceHealthRetryService::class)->retryOrderAction($originalRun, $user->id);

        $this->assertNotSame($originalRun->id, $retryRun->id);
        $this->assertSame('queued', $retryRun->status);
        $this->assertSame($originalRun->action_type, $retryRun->action_type);
        $this->assertSame($originalRun->channel_order_id, $retryRun->channel_order_id);
        $this->assertSame($originalRun->channel_order_package_id, $retryRun->channel_order_package_id);
        $this->assertSame($user->id, $retryRun->triggered_by);
        $this->assertSame((string) $originalRun->id, (string) data_get($retryRun->request_context_json, 'retry_of'));
        $this->assertSame('https://example.com/invoice.pdf', data_get($retryRun->request_context_json, 'invoice_link'));
        $this->assertNotEmpty(data_get($retryRun->request_context_json, 'retried_at'));

        Queue::assertPushed(RunMarketplaceOrderActionJob::class, function (RunMarketplaceOrderActionJob $job) use ($retryRun) {
            return $job->actionRunId === $retryRun->id;
        });
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
            'tax_number' => '1' . $suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'ZEM HOME',
            'store_code' => 'ZEM-' . $suffix,
            'seller_id' => $suffix,
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
            'external_order_id' => 'TY-ORDER-' . $suffix,
            'order_number' => 'TY-ORDER-' . $suffix,
            'order_status' => 'Created',
            'customer_name' => 'Test Müşteri',
            'ordered_at' => now(),
        ]);

        $package = ChannelOrderPackage::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'external_package_id' => 'PKG-' . $suffix,
            'package_number' => 'PKG-' . $suffix,
            'package_status' => 'Created',
            'cargo_tracking_number' => 'TRK-' . $suffix,
        ]);

        return [$user, $store, $order, $package];
    }
}
