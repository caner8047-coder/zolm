<?php

namespace Tests\Feature;

use App\Jobs\PushMarketplaceListingUpdateJob;
use App\Models\ChannelListing;
use App\Models\ChannelProduct;
use App\Models\IntegrationPushRun;
use App\Models\IntegrationSyncProfile;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\MpProduct;
use App\Models\User;
use App\Services\Marketplace\MarketplaceListingPushService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MarketplaceListingPushServiceTest extends TestCase
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

    public function test_it_queues_new_price_push_when_no_conflict_exists(): void
    {
        Queue::fake();

        [$user, , , $listing] = $this->createListingGraph();

        $result = app(MarketplaceListingPushService::class)->queuePricePush($listing, 1549.90, [
            'list_price' => 1699.90,
            'quantity' => 9,
        ], $user->id);

        $this->assertTrue($result['created']);
        $this->assertFalse($result['coalesced']);
        $this->assertFalse($result['busy']);
        $this->assertSame('price', $result['push_run']->push_type);

        $this->assertDatabaseHas('integration_push_runs', [
            'id' => $result['push_run']->id,
            'channel_listing_id' => $listing->id,
            'push_type' => 'price',
            'status' => 'queued',
        ]);

        Queue::assertPushed(PushMarketplaceListingUpdateJob::class, 1);
    }

    public function test_it_coalesces_into_existing_queued_push_run(): void
    {
        Queue::fake();

        [$user, , , $listing] = $this->createListingGraph();

        $existing = IntegrationPushRun::query()->create([
            'store_id' => $listing->store_id,
            'channel_listing_id' => $listing->id,
            'mp_product_id' => $listing->mp_product_id,
            'triggered_by' => $user->id,
            'push_type' => 'price',
            'status' => 'queued',
            'target_price' => 1499.90,
            'target_quantity' => 5,
            'currency' => 'TRY',
            'request_context_json' => [
                'list_price' => 1599.90,
            ],
            'attempt_count' => 0,
        ]);

        $result = app(MarketplaceListingPushService::class)->queuePricePush($listing, 1649.90, [
            'list_price' => 1799.90,
            'quantity' => 11,
        ], $user->id);

        $existing->refresh();

        $this->assertFalse($result['created']);
        $this->assertTrue($result['coalesced']);
        $this->assertSame($existing->id, $result['push_run']->id);
        $this->assertSame('1649.90', $existing->target_price);
        $this->assertSame(11, $existing->target_quantity);
        $this->assertSame($user->id, $existing->triggered_by);
        $this->assertSame(1, (int) data_get($existing->request_context_json, '_merged_push_count'));

        Queue::assertNothingPushed();
    }

    public function test_it_does_not_open_new_push_when_processing_run_exists(): void
    {
        Queue::fake();

        [$user, , , $listing] = $this->createListingGraph();

        $processing = IntegrationPushRun::query()->create([
            'store_id' => $listing->store_id,
            'channel_listing_id' => $listing->id,
            'mp_product_id' => $listing->mp_product_id,
            'triggered_by' => $user->id,
            'push_type' => 'stock',
            'status' => 'processing',
            'target_price' => 1499.90,
            'target_quantity' => 5,
            'currency' => 'TRY',
            'request_context_json' => [
                'sale_price' => 1499.90,
            ],
            'attempt_count' => 1,
            'started_at' => now(),
        ]);

        $result = app(MarketplaceListingPushService::class)->queueStockPush($listing, 12, [
            'sale_price' => 1599.90,
            'list_price' => 1699.90,
        ], $user->id);

        $this->assertFalse($result['created']);
        $this->assertFalse($result['coalesced']);
        $this->assertTrue($result['busy']);
        $this->assertSame($processing->id, $result['push_run']->id);
        $this->assertSame(1, IntegrationPushRun::query()->where('channel_listing_id', $listing->id)->count());

        Queue::assertNothingPushed();
    }

    public function test_it_debounces_recent_completed_identical_push(): void
    {
        Queue::fake();

        [$user, , , $listing] = $this->createListingGraph();

        $completed = IntegrationPushRun::query()->create([
            'store_id' => $listing->store_id,
            'channel_listing_id' => $listing->id,
            'mp_product_id' => $listing->mp_product_id,
            'triggered_by' => $user->id,
            'push_type' => 'stock',
            'status' => 'completed',
            'target_price' => 1499.90,
            'target_quantity' => 8,
            'currency' => 'TRY',
            'request_context_json' => [
                'sale_price' => 1499.90,
            ],
            'attempt_count' => 1,
            'started_at' => now()->subSeconds(5),
            'finished_at' => now()->subSeconds(3),
            'created_at' => now()->subSeconds(5),
            'updated_at' => now()->subSeconds(3),
        ]);

        $result = app(MarketplaceListingPushService::class)->queueStockPush($listing, 8, [
            'sale_price' => 1499.90,
        ], $user->id);

        $this->assertFalse($result['created']);
        $this->assertFalse($result['coalesced']);
        $this->assertFalse($result['busy']);
        $this->assertTrue($result['recent']);
        $this->assertSame($completed->id, $result['push_run']->id);
        $this->assertSame(1, IntegrationPushRun::query()->where('channel_listing_id', $listing->id)->count());

        Queue::assertNothingPushed();
    }

    /**
     * @return array{0: User, 1: MarketplaceStore, 2: MpProduct, 3: ChannelListing}
     */
    protected function createListingGraph(): array
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $entity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Push Ltd.',
            'tax_number' => '7' . $suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'ZEM PUSH',
            'store_code' => 'PUSH-' . $suffix,
            'seller_id' => 'SELLER-' . $suffix,
            'status' => 'active',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        IntegrationSyncProfile::query()->create(array_merge(
            ['store_id' => $store->id],
            IntegrationSyncProfile::defaultsForMarketplace('trendyol'),
            [
                'price_push_enabled' => true,
                'stock_push_enabled' => true,
                'request_jitter_seconds' => 15,
            ],
        ));

        $product = MpProduct::query()->create([
            'user_id' => $user->id,
            'stock_code' => 'PUSH-STK-' . $suffix,
            'barcode' => '869' . $suffix,
            'product_name' => 'ZEM Push Test',
            'brand' => 'ZEM',
            'category_name' => 'Mobilya',
            'sale_price' => 1499.90,
            'market_price' => 1699.90,
            'stock_quantity' => 8,
            'status' => 'active',
        ]);

        $channelProduct = ChannelProduct::query()->create([
            'store_id' => $store->id,
            'external_product_id' => 'CP-' . $suffix,
            'stock_code' => 'PUSH-STK-' . $suffix,
            'barcode' => '869' . $suffix,
            'title' => 'ZEM Push Test',
            'brand' => 'ZEM',
            'category_name' => 'Mobilya',
        ]);

        $listing = ChannelListing::query()->create([
            'store_id' => $store->id,
            'channel_product_id' => $channelProduct->id,
            'mp_product_id' => $product->id,
            'listing_id' => 'LIST-' . $suffix,
            'listing_status' => 'active',
            'sale_price' => 1499.90,
            'list_price' => 1699.90,
            'stock_quantity' => 8,
            'currency' => 'TRY',
        ]);

        return [$user, $store, $product, $listing];
    }
}
