<?php

namespace Tests\Feature\Livewire\Marketplace;

use App\Jobs\PushMarketplacePriceActionJob;
use App\Jobs\RollbackMarketplacePriceActionJob;
use App\Models\ChannelListing;
use App\Models\ChannelProduct;
use App\Models\MarketplaceStore;
use App\Models\MpPriceAction;
use App\Models\MpPriceRecommendation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

use Tests\TestCase;

class MarketplacePriceActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_push_price_action_job_executes_when_feature_flag_enabled(): void
    {
        config(['marketplace.trendyol.manual_price_actions_enabled' => true]);

        $user = User::factory()->create(['role' => 'operator']);
        $store = MarketplaceStore::factory()->create(['user_id' => $user->id, 'marketplace' => 'trendyol', 'seller_id' => '12345']);

        \App\Models\IntegrationConnection::create([
            'store_id' => $store->id,
            'provider' => 'trendyol',
            'auth_type' => 'api_key',
            'credentials_encrypted' => [
                'api_key' => 'dummy_key',
                'api_secret' => 'dummy_secret',
            ],
            'status' => 'connected',
        ]);

        $cp = ChannelProduct::create([
            'store_id' => $store->id,
            'external_product_id' => 'EXT-12345',
            'barcode' => 'PUSHBARCODE01',
            'product_name' => 'Test Product',
            'sale_price' => 150.00,
        ]);

        $cl = ChannelListing::create([
            'store_id' => $store->id,
            'channel_product_id' => $cp->id,
            'listing_id' => 'LISTING-01',
            'sale_price' => 150.00,
        ]);

        \App\Models\MpProduct::factory()->create([
            'user_id' => $user->id,
            'barcode' => 'PUSHBARCODE01',
            'cogs' => 50.00,
            'stock_quantity' => 10,
        ]);

        $bl = \App\Models\MpBuyboxListing::factory()->create([
            'store_id' => $store->id,
            'barcode' => 'PUSHBARCODE01',
            'buybox_price' => 140.00,
            'seller_price' => 150.00,
            'retrieved_at' => now(),
        ]);

        $rec = MpPriceRecommendation::factory()->create([
            'store_id' => $store->id,
            'mp_buybox_listing_id' => $bl->id,
            'barcode' => 'PUSHBARCODE01',
            'current_price' => 150.00,
            'buybox_price' => 140.00,
            'recommended_price' => 140.00,
            'minimum_safe_price' => 100.00,
            'unit_cost' => 50.00,
            'risk_level' => 'low',
        ]);

        $action = MpPriceAction::create([
            'store_id' => $store->id,
            'recommendation_id' => $rec->id,
            'barcode' => 'PUSHBARCODE01',
            'old_price' => 150.00,
            'expected_current_price' => 150.00,
            'requested_price' => 140.00,
            'status' => 'pending',
            'approved_by' => $user->id,
        ]);

        Http::fake([
            '*/integration/inventory/sellers/*/products/price-and-inventory' => Http::response([
                'batchRequestId' => 'BATCH-TEST-999',
            ], 200),
        ]);

        $job = new PushMarketplacePriceActionJob($action->id);
        $job->handle(app(\App\Services\Marketplace\MarketplaceConnectorManager::class));

        $action->refresh();
        $this->assertEquals('processing', $action->status);
        $this->assertEquals('BATCH-TEST-999', $action->batch_request_id);
    }

    public function test_push_price_action_job_blocked_when_feature_flag_disabled(): void
    {
        config(['marketplace.trendyol.manual_price_actions_enabled' => false]);

        $user = User::factory()->create(['role' => 'operator']);
        $store = MarketplaceStore::factory()->create(['user_id' => $user->id, 'marketplace' => 'trendyol']);

        $action = MpPriceAction::create([
            'store_id' => $store->id,
            'barcode' => 'DISABLED01',
            'old_price' => 150.00,
            'requested_price' => 140.00,
            'status' => 'pending',
            'approved_by' => $user->id,
        ]);

        $job = new PushMarketplacePriceActionJob($action->id);
        $job->handle(app(\App\Services\Marketplace\MarketplaceConnectorManager::class));

        $action->refresh();
        $this->assertEquals('blocked_feature_disabled', $action->status);
        $this->assertEquals('BLOCKED_FEATURE_DISABLED', $action->failure_code);
    }

    public function test_rollback_action_creates_new_rollback_action(): void
    {
        config([
            'marketplace.trendyol.manual_price_actions_enabled' => true,
            'marketplace.trendyol.price_rollback_enabled' => true,
        ]);

        $user = User::factory()->create(['role' => 'operator']);
        $store = MarketplaceStore::factory()->create(['user_id' => $user->id, 'marketplace' => 'trendyol']);

        $action = MpPriceAction::create([
            'store_id' => $store->id,
            'barcode' => 'ROLLBACK01',
            'old_price' => 180.00,
            'requested_price' => 160.00,
            'confirmed_price' => 160.00,
            'status' => 'success',
            'approved_by' => $user->id,
        ]);

        Http::fake([
            '*/integration/inventory/sellers/*/products/price-and-inventory' => Http::response([
                'batchRequestId' => 'BATCH-ROLLBACK-123',
            ], 200),
        ]);

        $job = new RollbackMarketplacePriceActionJob($action->id, $user->id);
        $job->handle();

        $action->refresh();
        $this->assertNotNull($action->rolled_back_at);

        $rollbackAction = MpPriceAction::where('store_id', $store->id)
            ->where('action_type', 'rollback')
            ->first();

        $this->assertNotNull($rollbackAction);
        $this->assertEquals(180.00, (float) $rollbackAction->requested_price);
    }
}
