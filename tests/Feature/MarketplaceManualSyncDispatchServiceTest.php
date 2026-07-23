<?php

namespace Tests\Feature;

use App\Jobs\SyncMarketplaceDataJob;
use App\Models\IntegrationSyncProfile;
use App\Models\IntegrationSyncRun;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\User;
use App\Services\Marketplace\MarketplaceManualSyncDispatchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class MarketplaceManualSyncDispatchServiceTest extends TestCase
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

    public function test_it_dispatches_manual_sync_when_no_active_or_recent_run_exists(): void
    {
        Queue::fake();

        $store = $this->createStore();

        $result = app(MarketplaceManualSyncDispatchService::class)->dispatch($store, 'orders', [
            'source' => 'test',
        ]);

        $this->assertTrue($result['created']);
        $this->assertFalse($result['debounced']);
        $this->assertDatabaseHas('integration_sync_runs', [
            'store_id' => $store->id,
            'sync_type' => 'orders',
            'trigger_type' => 'manual',
            'status' => 'queued',
        ]);

        Queue::assertPushed(SyncMarketplaceDataJob::class, function (SyncMarketplaceDataJob $job): bool {
            return $job->queue === config('marketplace.queues.sync', 'default');
        });
    }

    public function test_it_debounces_when_active_run_exists_for_same_store_and_sync_type(): void
    {
        Queue::fake();

        $store = $this->createStore();

        $existing = IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'orders',
            'trigger_type' => 'manual',
            'status' => 'processing',
            'notes_json' => ['options' => []],
            'created_at' => now()->subSeconds(20),
            'updated_at' => now()->subSeconds(20),
        ]);

        $result = app(MarketplaceManualSyncDispatchService::class)->dispatch($store, 'orders', [
            'source' => 'test',
        ]);

        $this->assertFalse($result['created']);
        $this->assertTrue($result['debounced']);
        $this->assertSame('active', $result['reason']);
        $this->assertTrue($existing->is($result['run']));
        $this->assertSame(1, IntegrationSyncRun::query()->where('store_id', $store->id)->count());

        Queue::assertNothingPushed();
    }

    public function test_it_allows_a_local_manual_retry_to_bypass_a_stale_queued_run(): void
    {
        Queue::fake();

        $store = $this->createStore();

        $staleRun = IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'claims',
            'trigger_type' => 'manual',
            'status' => 'queued',
            'notes_json' => ['options' => []],
        ]);
        $staleRun->forceFill([
            'created_at' => now()->subMinutes(2),
            'updated_at' => now()->subMinutes(2),
        ])->saveQuietly();

        $result = app(MarketplaceManualSyncDispatchService::class)->dispatch($store, 'claims', [
            'source' => 'test',
            'ignore_queued_active' => true,
        ]);

        $this->assertTrue($result['created']);
        $this->assertFalse($result['debounced']);
        $this->assertSame(2, IntegrationSyncRun::query()->where('store_id', $store->id)->count());
        Queue::assertPushed(SyncMarketplaceDataJob::class);
    }

    public function test_it_debounces_when_recent_manual_run_exists_inside_window(): void
    {
        Queue::fake();

        $store = $this->createStore();

        $existing = IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'finance',
            'trigger_type' => 'manual',
            'status' => 'completed',
            'notes_json' => ['options' => []],
            'created_at' => now()->subSeconds(10),
            'updated_at' => now()->subSeconds(10),
        ]);

        $result = app(MarketplaceManualSyncDispatchService::class)->dispatch($store, 'finance', [
            'source' => 'test',
        ]);

        $this->assertFalse($result['created']);
        $this->assertTrue($result['debounced']);
        $this->assertSame('recent', $result['reason']);
        $this->assertTrue($existing->is($result['run']));
        $this->assertSame(1, IntegrationSyncRun::query()->where('store_id', $store->id)->count());

        Queue::assertNothingPushed();
    }

    public function test_it_rejects_unsupported_finance_sync_for_woocommerce(): void
    {
        Queue::fake();

        $store = $this->createStore('woocommerce');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Bu kanal için finans sync desteklenmiyor.');

        app(MarketplaceManualSyncDispatchService::class)->dispatch($store, 'finance', [
            'source' => 'test',
        ]);
    }

    public function test_it_dispatches_claim_sync_for_supported_marketplace(): void
    {
        Queue::fake();

        $store = $this->createStore('trendyol');

        $result = app(MarketplaceManualSyncDispatchService::class)->dispatch($store, 'claims', [
            'source' => 'test',
        ]);

        $this->assertTrue($result['created']);
        $this->assertDatabaseHas('integration_sync_runs', [
            'store_id' => $store->id,
            'sync_type' => 'claims',
            'trigger_type' => 'manual',
            'status' => 'queued',
        ]);

        Queue::assertPushed(SyncMarketplaceDataJob::class);
    }

    protected function createStore(string $marketplace = 'trendyol', array $profileOverrides = []): MarketplaceStore
    {
        $suffix = (string) random_int(100000, 999999);
        $user = User::factory()->create([
            'email' => 'sync-dispatch-'.$suffix.'@example.test',
        ]);

        $entity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Sync Dispatch Ltd.',
            'tax_number' => '2'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => $marketplace,
            'store_name' => 'SYNC DISPATCH',
            'store_code' => 'SYNC-'.$suffix,
            'seller_id' => 'SYNC-'.$suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        IntegrationSyncProfile::query()->create([
            'store_id' => $store->id,
            ...array_replace(IntegrationSyncProfile::defaultsForMarketplace($marketplace), $profileOverrides),
        ]);

        return $store->fresh(['syncProfile']);
    }
}
