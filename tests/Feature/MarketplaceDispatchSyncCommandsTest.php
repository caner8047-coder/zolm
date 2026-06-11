<?php

namespace Tests\Feature;

use App\Jobs\SyncMarketplaceDataJob;
use App\Models\IntegrationConnection;
use App\Models\IntegrationSyncProfile;
use App\Models\IntegrationSyncRun;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\User;
use App\Services\Marketplace\MarketplaceSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MarketplaceDispatchSyncCommandsTest extends TestCase
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
        config()->set('database.connections.mysql.database', 'zolm');
        config()->set('database.connections.mysql.username', 'sail');
        config()->set('database.connections.mysql.password', 'password');
        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::setDefaultConnection('mysql');
    }

    protected function tearDown(): void
    {
        if ($this->createdStoreIds !== []) {
            IntegrationSyncRun::query()->whereIn('store_id', $this->createdStoreIds)->delete();
            IntegrationSyncProfile::query()->whereIn('store_id', $this->createdStoreIds)->delete();
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

    public function test_due_dispatch_skips_store_that_fails_readiness(): void
    {
        Queue::fake();

        [, $failingStore] = $this->makeStores();
        $baselineCount = IntegrationSyncRun::query()->where('store_id', $failingStore->id)->count();

        $this->artisan('marketplace:dispatch-due-syncs', [
            '--type' => 'orders',
            '--store' => $failingStore->id,
        ])->assertExitCode(0);

        $this->assertSame($baselineCount, IntegrationSyncRun::query()->where('store_id', $failingStore->id)->count());
        Queue::assertNothingPushed();
    }

    public function test_due_dispatch_runs_ready_orders_inline(): void
    {
        Queue::fake();
        $this->mockInlineOrderSync();

        [$readyStore] = $this->makeStores();
        $baselineCount = IntegrationSyncRun::query()->where('store_id', $readyStore->id)->count();

        $this->artisan('marketplace:dispatch-due-syncs', [
            '--type' => 'orders',
            '--store' => $readyStore->id,
        ])->assertExitCode(0);

        $this->assertSame($baselineCount + 1, IntegrationSyncRun::query()->where('store_id', $readyStore->id)->count());
        $this->assertDatabaseHas('integration_sync_runs', [
            'store_id' => $readyStore->id,
            'sync_type' => 'orders',
            'trigger_type' => 'schedule',
            'status' => 'completed',
        ]);
        Queue::assertNothingPushed();
    }

    public function test_due_dispatch_queues_ready_store_questions_sync(): void
    {
        Queue::fake();

        [$readyStore] = $this->makeStores();
        $baselineCount = IntegrationSyncRun::query()->where('store_id', $readyStore->id)->count();

        $this->artisan('marketplace:dispatch-due-syncs', [
            '--type' => 'questions',
            '--store' => $readyStore->id,
        ])->assertExitCode(0);

        $this->assertSame($baselineCount + 1, IntegrationSyncRun::query()->where('store_id', $readyStore->id)->count());
        $this->assertDatabaseHas('integration_sync_runs', [
            'store_id' => $readyStore->id,
            'sync_type' => 'questions',
            'trigger_type' => 'schedule',
            'status' => 'queued',
        ]);
        Queue::assertPushed(SyncMarketplaceDataJob::class, 1);
    }

    public function test_due_dispatch_uses_global_15_minute_order_interval_for_existing_profiles(): void
    {
        Queue::fake();
        $this->mockInlineOrderSync();

        [$readyStore] = $this->makeStores();
        $readyStore->syncProfile()->update([
            'orders_poll_minutes' => 30,
        ]);

        IntegrationSyncRun::query()->create([
            'store_id' => $readyStore->id,
            'sync_type' => 'orders',
            'trigger_type' => 'schedule',
            'status' => 'completed',
            'started_at' => now()->subMinutes(17),
            'finished_at' => now()->subMinutes(16),
            'notes_json' => ['options' => []],
            'created_at' => now()->subMinutes(17),
            'updated_at' => now()->subMinutes(16),
        ]);

        $baselineCount = IntegrationSyncRun::query()->where('store_id', $readyStore->id)->count();

        $this->artisan('marketplace:dispatch-due-syncs', [
            '--type' => 'orders',
            '--store' => $readyStore->id,
        ])->assertExitCode(0);

        $this->assertSame($baselineCount + 1, IntegrationSyncRun::query()->where('store_id', $readyStore->id)->count());
        $this->assertDatabaseHas('integration_sync_runs', [
            'store_id' => $readyStore->id,
            'sync_type' => 'orders',
            'trigger_type' => 'schedule',
            'status' => 'completed',
        ]);
        Queue::assertNothingPushed();
    }

    public function test_manual_dispatch_rejects_store_when_last_live_verification_failed(): void
    {
        Queue::fake();

        [, $failingStore] = $this->makeStores();

        $this->artisan('marketplace:dispatch-sync', [
            'store' => $failingStore->id,
            'syncType' => 'orders',
        ])->assertExitCode(1);

        $this->assertSame(0, IntegrationSyncRun::query()->where('store_id', $failingStore->id)->count());
        Queue::assertNothingPushed();
    }

    /**
     * @return array{0: MarketplaceStore, 1: MarketplaceStore}
     */
    protected function makeStores(): array
    {
        $user = User::factory()->create();
        $this->createdUserIds[] = $user->id;
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Dispatch Ltd.',
            'tax_number' => '8'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
        $this->createdEntityIds[] = $legalEntity->id;

        $readyStore = $this->createStore($user->id, $legalEntity->id, 'READY-'.$suffix, false);
        $failingStore = $this->createStore($user->id, $legalEntity->id, 'FAIL-'.$suffix, true);

        return [$readyStore, $failingStore];
    }

    protected function createStore(int $userId, int $legalEntityId, string $sellerId, bool $withLiveError): MarketplaceStore
    {
        $store = MarketplaceStore::query()->create([
            'user_id' => $userId,
            'legal_entity_id' => $legalEntityId,
            'marketplace' => 'trendyol',
            'store_name' => 'Dispatch '.$sellerId,
            'store_code' => $sellerId,
            'seller_id' => $sellerId,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
        $this->createdStoreIds[] = $store->id;

        IntegrationConnection::query()->create([
            'store_id' => $store->id,
            'provider' => 'trendyol',
            'auth_type' => 'api_key_secret',
            'credentials_encrypted' => [
                'api_key' => 'key-'.$sellerId,
                'api_secret' => 'secret-'.$sellerId,
            ],
            'api_base_url' => 'https://apigw.trendyol.com/',
            'status' => 'configured',
            'last_verified_at' => $withLiveError ? now() : null,
            'last_error' => $withLiveError ? '401 Unauthorized' : null,
        ]);

        IntegrationSyncProfile::query()->create([
            'store_id' => $store->id,
            'orders_poll_minutes' => 15,
            'finance_poll_minutes' => 60,
            'products_poll_minutes' => 360,
            'questions_poll_minutes' => 15,
            'backfill_mode' => 'windowed',
            'orders_enabled' => true,
            'finance_enabled' => false,
            'products_enabled' => false,
            'questions_enabled' => true,
            'webhook_enabled' => false,
            'price_push_enabled' => false,
            'stock_push_enabled' => false,
            'auto_match_enabled' => false,
            'barcode_fallback_enabled' => false,
            'strict_unique_match_enabled' => false,
            'nightly_repair_sync_enabled' => false,
            'max_parallel_jobs' => 1,
            'request_jitter_seconds' => 0,
            'extra_settings' => [],
        ]);

        return $store->fresh(['connection', 'syncProfile']);
    }

    protected function mockInlineOrderSync(): void
    {
        $this->mock(MarketplaceSyncService::class, function ($mock): void {
            $mock->shouldReceive('run')->once()->andReturnUsing(function (int $runId): void {
                IntegrationSyncRun::query()
                    ->findOrFail($runId)
                    ->forceFill([
                        'status' => 'completed',
                        'started_at' => now(),
                        'finished_at' => now(),
                        'items_received' => 1,
                        'items_created' => 1,
                        'notes_json' => ['options' => []],
                    ])
                    ->save();
            });
        });
    }
}
