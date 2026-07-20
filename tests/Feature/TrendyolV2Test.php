<?php

namespace Tests\Feature;

use App\Models\MarketplaceStore;
use App\Models\IntegrationSyncRun;
use App\Models\MpBuyboxListing;
use App\Models\MpBuyboxHistory;
use App\Models\MpBrand;
use App\Models\MpCategory;
use App\Models\MpClaimReason;
use App\Models\CargoInvoiceLine;
use App\Models\IntegrationPushRun;
use App\Services\Marketplace\Connectors\TrendyolConnector;
use App\Services\Marketplace\MarketplaceBuyboxSyncService;
use App\Services\Marketplace\MarketplaceReferenceSyncService;
use App\Services\Marketplace\MarketplaceCargoInvoiceSyncService;
use App\Jobs\SyncMarketplaceBuyboxJob;
use App\Jobs\TrackMarketplaceBatchRequestsJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TrendyolV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_v2_approved_endpoint_for_products()
    {
        $store = MarketplaceStore::factory()->create(['marketplace' => 'trendyol']);
        \App\Models\IntegrationConnection::factory()->create([
            'store_id' => $store->id,
            'provider' => 'trendyol',
            'credentials_encrypted' => ['api_key' => 'test-key', 'api_secret' => 'test-secret', 'seller_id' => '123'],
            'status' => 'active',
        ]);
        $connector = app(TrendyolConnector::class);

        Http::fake([
            '*products/approved*' => Http::response(['content' => []], 200),
        ]);

        $connector->pullProducts($store, ['start_date' => now()->subDay(), 'end_date' => now()]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'products/approved');
        });

        Http::assertNotSent(function ($request) {
            return preg_match('/\/products(\?|$)/', $request->url());
        });
    }

    public function test_stream_first_and_continue_cursor()
    {
        $store = MarketplaceStore::factory()->create(['marketplace' => 'trendyol']);
        \App\Models\IntegrationConnection::factory()->create([
            'store_id' => $store->id,
            'provider' => 'trendyol',
            'credentials_encrypted' => ['api_key' => 'test-key', 'api_secret' => 'test-secret', 'seller_id' => '123'],
            'status' => 'active',
        ]);
        $connector = app(TrendyolConnector::class);

        Http::fake([
            '*orders/stream*' => Http::sequence()
                ->push(['content' => [['id' => 1]], 'hasMore' => true, 'nextCursor' => 'cursor_1'], 200)
                ->push(['content' => [['id' => 2]], 'hasMore' => false, 'nextCursor' => 'cursor_2'], 200),
        ]);

        $result1 = $connector->pullOrders($store, ['start_date' => now()->subDay(), 'end_date' => now(), 'cursor' => null]);
        $this->assertTrue($result1['meta']['has_more']);
        $this->assertEquals('cursor_1', $result1['meta']['cursor_after']);

        $result2 = $connector->pullOrders($store, ['start_date' => now()->subDay(), 'end_date' => now(), 'cursor' => 'cursor_1']);
        $this->assertFalse($result2['meta']['has_more']);
        $this->assertNull($result2['meta']['cursor_after']);
    }

    public function test_buybox_upsert_and_history()
    {
        $store = MarketplaceStore::factory()->create(['marketplace' => 'trendyol']);
        \App\Models\IntegrationConnection::factory()->create([
            'store_id' => $store->id,
            'provider' => 'trendyol',
            'credentials_encrypted' => ['api_key' => 'test-key', 'api_secret' => 'test-secret', 'seller_id' => '123'],
            'status' => 'active',
        ]);
        \App\Models\MpProduct::factory()->create(['user_id' => $store->user_id, 'barcode' => 'TEST-123']);
        
        $service = app(MarketplaceBuyboxSyncService::class);

        Http::fake([
            '*buybox-information*' => Http::sequence()
                ->push(['buyboxInfo' => [['buyboxPrice' => 100, 'sellerPrice' => 100, 'sellerRank' => 1, 'barcode' => 'TEST-123']]], 200)  // Call 1
                ->push(['buyboxInfo' => [['buyboxPrice' => 100, 'sellerPrice' => 100, 'sellerRank' => 1, 'barcode' => 'TEST-123']]], 200)  // Call 2 (No change)
                ->push(['buyboxInfo' => [['buyboxPrice' => 95, 'sellerPrice' => 100, 'sellerRank' => 2, 'barcode' => 'TEST-123']]], 200)  // Call 3 (Change)
        ]);

        $service->sync($store);
        $this->assertEquals(1, MpBuyboxHistory::count());
        $this->assertEquals(100, MpBuyboxListing::first()->buybox_price);

        $service->sync($store);
        $this->assertEquals(1, MpBuyboxHistory::count()); // History should not increase

        $service->sync($store);
        $this->assertEquals(2, MpBuyboxHistory::count()); // History increased
        $this->assertEquals(95, MpBuyboxListing::first()->buybox_price);
    }

    public function test_brands_sync_pagination_and_duplicate_prevention()
    {
        $store = MarketplaceStore::factory()->create(['marketplace' => 'trendyol']);
        \App\Models\IntegrationConnection::factory()->create([
            'store_id' => $store->id,
            'provider' => 'trendyol',
            'credentials_encrypted' => ['api_key' => 'test-key', 'api_secret' => 'test-secret', 'seller_id' => '123'],
            'status' => 'active',
        ]);
        $service = app(MarketplaceReferenceSyncService::class);

        Http::fake([
            '*integration/product/brands*' => Http::sequence()
                ->push(['brands' => [['id' => 1, 'name' => 'Brand A']]], 200)
                ->push(['brands' => [['id' => 1, 'name' => 'Brand A Updated']]], 200)
                ->push(['brands' => []], 200),
        ]);

        $service->syncBrands($store);
        $this->assertEquals(1, MpBrand::count());
        $this->assertEquals('Brand A', MpBrand::first()->name);

        $service->syncBrands($store);
        $this->assertEquals(1, MpBrand::count()); // No duplicate
        $this->assertEquals('Brand A Updated', MpBrand::first()->name);
    }

    public function test_category_recursive_and_leaf()
    {
        $store = MarketplaceStore::factory()->create(['marketplace' => 'trendyol']);
        \App\Models\IntegrationConnection::factory()->create([
            'store_id' => $store->id,
            'provider' => 'trendyol',
            'credentials_encrypted' => ['api_key' => 'test-key', 'api_secret' => 'test-secret', 'seller_id' => '123'],
            'status' => 'active',
        ]);
        $service = app(MarketplaceReferenceSyncService::class);

        Http::fake([
            '*product-categories*' => Http::response([
                'categories' => [
                    [
                        'id' => 1,
                        'name' => 'Root',
                        'subCategories' => [
                            ['id' => 2, 'name' => 'Leaf 1', 'subCategories' => []],
                        ]
                    ]
                ]
            ], 200),
        ]);

        \App\Models\MpCategory::truncate();

        $service->syncCategories($store);

        $this->assertEquals(2, MpCategory::count());
        $root = MpCategory::where('platform_category_id', 1)->first();
        $leaf = MpCategory::where('platform_category_id', 2)->first();

        $this->assertFalse($root->is_leaf);
        $this->assertTrue($leaf->is_leaf);
        $this->assertEquals(1, $leaf->parent_id);
    }

    public function test_claim_reasons_sync()
    {
        $store = MarketplaceStore::factory()->create(['marketplace' => 'trendyol']);
        \App\Models\IntegrationConnection::factory()->create([
            'store_id' => $store->id,
            'provider' => 'trendyol',
            'credentials_encrypted' => ['api_key' => 'test-key', 'api_secret' => 'test-secret', 'seller_id' => '123'],
            'status' => 'active',
        ]);
        $service = app(MarketplaceReferenceSyncService::class);

        Http::fake([
            '*claim-issue-reasons*' => Http::response([
                ['id' => 1, 'name' => 'Defective'],
            ], 200),
        ]);

        $service->syncClaimReasons($store);
        $this->assertEquals(1, MpClaimReason::count());
        $this->assertEquals('Defective', MpClaimReason::first()->name);
    }

    public function test_cargo_invoice_duplicate_prevention()
    {
        $store = MarketplaceStore::factory()->create(['marketplace' => 'trendyol']);
        \App\Models\IntegrationConnection::factory()->create([
            'store_id' => $store->id,
            'provider' => 'trendyol',
            'credentials_encrypted' => ['api_key' => 'test-key', 'api_secret' => 'test-secret', 'seller_id' => '123'],
            'status' => 'active',
        ]);
        $service = app(MarketplaceCargoInvoiceSyncService::class);

        Http::fake([
            '*other-financials*' => Http::response([
                'content' => [
                    ['id' => 101, 'transactionType' => 'DeductionInvoices', 'receiptId' => 999, 'invoiceSerialNumber' => 'INV-123', 'transactionDate' => 1620000000000],
                ],
                'hasNext' => false,
            ], 200),
            '*cargo-invoice*' => Http::response([
                'content' => [
                    ['packageId' => 'PKG-123', 'cargoTrackingNumber' => 'TR123', 'barcode' => 'TEST-123', 'taxAmount' => 10, 'amountWithoutTax' => 50, 'totalAmount' => 60, 'invoiceDateTime' => 1620000000000]
                ]
            ], 200),
        ]);

        $result = $service->sync($store);
        $initialCount = CargoInvoiceLine::where('store_id', $store->id)->count();
        $this->assertGreaterThanOrEqual(1, $initialCount);

        $service->sync($store);
        $this->assertEquals($initialCount, CargoInvoiceLine::where('store_id', $store->id)->count()); // Duplicate prevented
    }

    public function test_batch_tracking_statuses()
    {
        \Illuminate\Support\Facades\Config::set('marketplace.trendyol.batch_tracking_enabled', true);
        $store = MarketplaceStore::factory()->create(['marketplace' => 'trendyol']);
        \App\Models\IntegrationConnection::factory()->create([
            'store_id' => $store->id,
            'provider' => 'trendyol',
            'credentials_encrypted' => ['api_key' => 'test-key', 'api_secret' => 'test-secret', 'seller_id' => '123'],
            'status' => 'active',
        ]);
        $run = IntegrationPushRun::create(['store_id' => $store->id, 'external_batch_id' => 'batch-1', 'status' => 'processing', 'push_type' => 'price']);

        Http::fake([
            '*batch-requests*' => Http::sequence()
                ->push(['status' => 'PROCESSING'], 200)
                ->push(['status' => 'COMPLETED'], 200),
        ]);

        $job = new TrackMarketplaceBatchRequestsJob();
        $job->handle(app(\App\Services\Marketplace\MarketplaceConnectorManager::class));
        
        $this->assertEquals('processing', $run->fresh()->status);
        $this->assertEquals(1, $run->fresh()->attempt_count);

        $job->handle(app(\App\Services\Marketplace\MarketplaceConnectorManager::class));
        
        $this->assertEquals('success', $run->fresh()->status);
        $this->assertNotNull($run->fresh()->finished_at);
    }

    public function test_feature_flag_disabled_does_not_run_jobs()
    {
        Config::set('marketplace.trendyol.buybox_sync_enabled', false);
        $store = MarketplaceStore::factory()->create(['marketplace' => 'trendyol']);
        \App\Models\IntegrationConnection::factory()->create([
            'store_id' => $store->id,
            'provider' => 'trendyol',
            'credentials_encrypted' => ['api_key' => 'test-key', 'api_secret' => 'test-secret', 'seller_id' => '123'],
            'status' => 'active',
        ]);
        
        Http::fake();
        $job = new SyncMarketplaceBuyboxJob($store);
        $job->handle(app(MarketplaceBuyboxSyncService::class));

        Http::assertNothingSent();
    }
}
