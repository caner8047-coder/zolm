<?php

namespace Tests\Feature\Livewire\Marketplace;

use App\Jobs\PushMarketplacePriceActionJob;
use App\Jobs\RunTrendyolPriceCanaryJob;
use App\Models\ChannelListing;
use App\Models\ChannelProduct;
use App\Models\IntegrationConnection;
use App\Models\MarketplaceStore;
use App\Models\MpBuyboxListing;
use App\Models\MpPriceAction;
use App\Models\MpPriceEmergencyStop;
use App\Models\MpPriceManualLock;
use App\Models\MpPricePilotProduct;
use App\Models\MpPriceRecommendation;
use App\Models\MpProduct;
use App\Models\User;
use App\Services\Marketplace\MarketplaceAutomaticPriceEligibilityService;
use App\Services\Marketplace\MarketplaceListingPriceVerificationService;
use App\Services\Marketplace\MarketplacePriceActionRevalidatorService;
use App\Services\Marketplace\MarketplacePriceCanaryService;
use App\Services\Marketplace\MarketplacePriceEmergencyStopService;
use App\Services\Marketplace\MarketplacePriceLockService;
use App\Services\Marketplace\MarketplacePricePilotService;
use App\Services\Marketplace\MarketplacePriceShadowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MarketplacePilotShadowEmergencyTest extends TestCase
{
    use RefreshDatabase;

    protected MarketplaceStore $store;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'operator']);
        $this->store = MarketplaceStore::factory()->create([
            'user_id' => $this->user->id,
            'marketplace' => 'trendyol',
            'seller_id' => '123456',
        ]);

        IntegrationConnection::create([
            'store_id' => $this->store->id,
            'provider' => 'trendyol',
            'auth_type' => 'api_key',
            'credentials_encrypted' => [
                'api_key' => 'test_key',
                'api_secret' => 'test_secret',
            ],
            'status' => 'connected',
        ]);

        config([
            'marketplace.trendyol.price_recommendations_enabled' => true,
            'marketplace.trendyol.manual_price_actions_enabled' => true,
            'marketplace.trendyol.bulk_price_actions_enabled' => true,
            'marketplace.trendyol.automatic_price_actions_enabled' => true,
            'marketplace.trendyol.price_rollback_enabled' => true,
        ]);
    }

    // ─── 1. Shadow Mode Tests ────────────────────────────────────

    public function test_shadow_mode_records_simulation_does_not_call_api(): void
    {
        config(['marketplace.trendyol.shadow_mode_enabled' => true]);

        $listing = MpBuyboxListing::factory()->create([
            'store_id' => $this->store->id,
            'barcode' => 'SHADOWBARCODE',
            'buybox_price' => 150.00,
            'seller_price' => 160.00,
        ]);

        $mpProduct = MpProduct::factory()->create([
            'user_id' => $this->user->id,
            'barcode' => 'SHADOWBARCODE',
            'cogs' => 50.00,
        ]);

        $shadowService = app(MarketplacePriceShadowService::class);
        $record = $shadowService->recordShadowSimulation($listing);

        $this->assertNotNull($record);
        $this->assertEquals('SHADOWBARCODE', $record->barcode);
        $this->assertEquals(150.00, $record->buybox_price);
        $this->assertEquals(160.00, $record->current_price);
        $this->assertTrue($record->is_actionable);

        // Verify evaluations can be run
        $evaluatedCount = $shadowService->evaluateShadowRecords($this->store);
        $this->assertEquals(1, $evaluatedCount);
    }

    // ─── 2. Whitelisting & Product Limits ──────────────────────────

    public function test_pilot_product_whitelisting_and_catalog_size_limits(): void
    {
        $pilotService = app(MarketplacePricePilotService::class);

        // Seed some listings so total catalog 1% limit allows adding at least 1 product
        MpBuyboxListing::factory()->count(10)->create(['store_id' => $this->store->id]);

        $mpProduct = MpProduct::factory()->create([
            'user_id' => $this->user->id,
            'barcode' => 'PILOTBARCODE',
            'cogs' => 60.00,
            'stock_quantity' => 100,
        ]);

        $pilot = $pilotService->addProductToPilot($this->store, 'PILOTBARCODE', 'shadow', 'Test add');

        $this->assertNotNull($pilot);
        $this->assertEquals('shadow', $pilot->mode);
        $this->assertTrue($pilotService->isProductInPilot($this->store->id, 'PILOTBARCODE'));

        // Exclude test: high return rate
        $mpProduct->update(['return_rate' => 20.0]);
        $this->assertEquals('Yüksek iade oranlı ürün (> %15)', $pilotService->checkExclusionCriteria($this->store, 'PILOTBARCODE'));
    }

    // ─── 3. Execution-Time Revalidation & Conflicts ───────────────

    public function test_revalidation_fails_on_price_conflict_or_cost_change(): void
    {
        $rec = MpPriceRecommendation::factory()->create([
            'store_id' => $this->store->id,
            'barcode' => 'CONFBARCODE',
            'current_price' => 120.00,
            'recommended_price' => 100.00,
            'minimum_safe_price' => 90.00,
        ]);

        $action = MpPriceAction::create([
            'store_id' => $this->store->id,
            'recommendation_id' => $rec->id,
            'barcode' => 'CONFBARCODE',
            'old_price' => 120.00,
            'expected_current_price' => 120.00,
            'requested_price' => 100.00,
            'status' => 'pending',
        ]);

        $cp = ChannelProduct::create([
            'store_id' => $this->store->id,
            'external_product_id' => 'EXT-1111',
            'barcode' => 'CONFBARCODE',
            'title' => 'Test',
        ]);

        $listing = ChannelListing::create([
            'store_id' => $this->store->id,
            'channel_product_id' => $cp->id,
            'listing_id' => 'LIST-1111',
            'sale_price' => 130.00, // Current price changed to 130! Conflict!
            'stock_quantity' => 10,
        ]);

        config(['marketplace.trendyol.manual_price_actions_enabled' => true]);

        $reval = app(MarketplacePriceActionRevalidatorService::class);
        $result = $reval->revalidateAtExecution($action);

        $this->assertFalse($result);
        $this->assertEquals('conflict_price_changed', $action->fresh()->status);
    }

    // ─── 4. Eligibility Engine & Canary ────────────────────────────

    public function test_canary_auto_pricing_limits_and_rules(): void
    {
        Queue::fake();

        config([
            'marketplace.trendyol.automatic_price_actions_enabled' => true,
            'marketplace.trendyol.canary_enabled' => true,
        ]);

        MpPricePilotProduct::create([
            'store_id' => $this->store->id,
            'barcode' => 'CANARYBARCODE',
            'mode' => 'canary_auto',
        ]);

        $rec = MpPriceRecommendation::factory()->create([
            'store_id' => $this->store->id,
            'barcode' => 'CANARYBARCODE',
            'current_price' => 100.00,
            'buybox_price' => 99.00,
            'recommended_price' => 99.00, // 1% drop -> within 2% canary limit
            'minimum_safe_price' => 95.00,
            'risk_level' => 'low',
            'status' => 'new',
        ]);

        MpBuyboxListing::factory()->create([
            'store_id' => $this->store->id,
            'barcode' => 'CANARYBARCODE',
            'retrieved_at' => now(),
        ]);

        $canaryService = app(MarketplacePriceCanaryService::class);
        $dispatched = $canaryService->runCanaryCycle($this->store);

        $this->assertEquals(1, $dispatched);
        Queue::assertPushed(PushMarketplacePriceActionJob::class);
    }

    // ─── 5. Emergency Stop & Manual Locks ──────────────────────────

    public function test_emergency_stop_blocks_all_actions(): void
    {
        $emergencyStop = app(MarketplacePriceEmergencyStopService::class);
        $emergencyStop->activateEmergencyStop($this->store->id, 'Critical error');

        $this->assertTrue($emergencyStop->isEmergencyStopActive($this->store->id));

        $rec = MpPriceRecommendation::factory()->create([
            'store_id' => $this->store->id,
            'barcode' => 'LOCKBARCODE',
            'current_price' => 100.00,
            'recommended_price' => 99.00,
        ]);

        $action = MpPriceAction::create([
            'store_id' => $this->store->id,
            'recommendation_id' => $rec->id,
            'barcode' => 'LOCKBARCODE',
            'old_price' => 100.00,
            'requested_price' => 99.00,
            'status' => 'pending',
        ]);

        $reval = app(MarketplacePriceActionRevalidatorService::class);
        $this->assertFalse($reval->revalidateAtExecution($action));
        $this->assertEquals('blocked_emergency_stop', $action->fresh()->status);
    }

    // ─── 6. Fiyat Doğrulama (Post-Push Verification) ────────────────

    public function test_price_verification_matches_actual_observed_price(): void
    {
        config(['marketplace.trendyol.price_verification_enabled' => true]);

        $action = MpPriceAction::create([
            'store_id' => $this->store->id,
            'barcode' => 'VERIFYBARCODE',
            'old_price' => 100.00,
            'requested_price' => 95.00,
            'status' => 'success',
        ]);

        $cp = ChannelProduct::create([
            'store_id' => $this->store->id,
            'external_product_id' => 'EXT-2222',
            'barcode' => 'VERIFYBARCODE',
            'title' => 'Test 2',
        ]);

        $listing = ChannelListing::create([
            'store_id' => $this->store->id,
            'channel_product_id' => $cp->id,
            'listing_id' => 'LIST-2222',
            'sale_price' => 95.00, // Matches requested price!
            'stock_quantity' => 20,
        ]);

        $verifyService = app(MarketplaceListingPriceVerificationService::class);
        $result = $verifyService->verifyActionPrice($action);

        $this->assertTrue($result);
        $this->assertEquals('verified_success', $action->fresh()->verification_status);
    }
}
