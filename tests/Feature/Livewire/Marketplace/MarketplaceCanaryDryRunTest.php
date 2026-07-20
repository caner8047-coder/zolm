<?php

namespace Tests\Feature\Livewire\Marketplace;

use App\Models\MarketplaceStore;
use App\Models\MpPriceAction;
use App\Models\MpPriceShadowRecord;
use App\Models\MpPriceShadowEvaluation;
use App\Models\MpPricePilotProduct;
use App\Models\MpPriceRecommendation;
use App\Models\IntegrationPushRun;
use App\Models\ChannelListing;
use App\Models\ChannelProduct;
use App\Models\User;
use App\Services\Marketplace\MarketplacePricePilotService;
use App\Services\Marketplace\MarketplaceCanaryReadinessService;
use App\Jobs\PushMarketplacePriceActionJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MarketplaceCanaryDryRunTest extends TestCase
{
    use RefreshDatabase;

    protected MarketplaceStore $store;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create(['role' => 'admin']);
        
        $this->store = MarketplaceStore::factory()->create([
            'user_id' => $this->adminUser->id,
            'marketplace' => 'trendyol',
            'seller_id' => '9456',
            'store_name' => 'Trendyol Test Store',
        ]);

        // Mock pilot service risk level
        $mock = \Mockery::mock(MarketplacePricePilotService::class)->makePartial();
        $mock->shouldReceive('getRiskLevel')->andReturn('low');
        $this->app->instance(MarketplacePricePilotService::class, $mock);
    }

    private function createBaselineEvidence(): void
    {
        // 20 shadow records
        for ($i = 0; $i < 25; $i++) {
            $rec = MpPriceShadowRecord::create([
                'store_id' => $this->store->id,
                'barcode' => 'BARCODE1',
                'current_price' => 100.0,
                'risk_level' => 'low',
                'is_actionable' => true,
                'recommendation_type' => 'LOWER_TO_WIN',
                'simulated_at' => now()->subHours(48)->addMinutes($i),
            ]);

            MpPriceShadowEvaluation::create([
                'shadow_record_id' => $rec->id,
                'store_id' => $this->store->id,
                'barcode' => 'BARCODE1',
                'evaluated_at' => now()->subHours(24),
                'actual_buybox_price_after' => 95.0,
                'actual_seller_rank_after' => 1,
                'would_win_buybox' => true,
                'would_preserve_margin' => true,
                'was_unnecessary_drop' => false,
            ]);
        }

        // 20 API requests
        for ($i = 0; $i < 20; $i++) {
            IntegrationPushRun::create([
                'store_id' => $this->store->id,
                'channel_listing_id' => null,
                'push_type' => 'price',
                'status' => 'completed',
                'target_price' => 95.0,
            ]);
        }

        // 20 queue jobs
        for ($i = 0; $i < 20; $i++) {
            MpPriceAction::create([
                'store_id' => $this->store->id,
                'barcode' => 'BARCODE1',
                'status' => 'success',
                'old_price' => 100.0,
                'requested_price' => 95.0,
                'action_type' => 'price_change',
                'trigger_type' => 'manual',
            ]);
        }

        MpPricePilotProduct::create([
            'store_id' => $this->store->id,
            'barcode' => 'BARCODE1',
            'mode' => 'shadow',
            'inclusion_reason' => 'test',
        ]);

        MpPriceRecommendation::create([
            'store_id' => $this->store->id,
            'barcode' => 'BARCODE1',
            'current_price' => 100.0,
            'recommended_price' => 95.0,
            'minimum_safe_price' => 90.0,
            'risk_level' => 'low',
            'status' => 'new',
            'recommendation_type' => 'LOWER_TO_WIN',
        ]);

        $prod = ChannelProduct::create([
            'store_id' => $this->store->id,
            'barcode' => 'BARCODE1',
            'title' => 'Test Product',
            'external_product_id' => 'EXT-1234',
        ]);

        ChannelListing::create([
            'store_id' => $this->store->id,
            'channel_product_id' => $prod->id,
            'listing_id' => 'LST-1234',
            'price' => 100.0,
            'stock_quantity' => 10,
        ]);
    }

    public function test_dry_run_command_prints_full_report(): void
    {
        $this->createBaselineEvidence();

        $code = Artisan::call('marketplace:price-pilot', [
            'action' => 'canary-dry-run',
            'store_id' => $this->store->id,
            '--product' => 'BARCODE1',
            '--approved-by' => $this->adminUser->id,
            '--confirm' => true,
        ]);

        $output = Artisan::output();

        $this->assertEquals(0, $code);
        $this->assertStringContainsString('ZOLM Canary Dry-Run Sertifikasyon Raporu', $output);
        $this->assertStringContainsString('Readiness Durumu', $output);
        $this->assertStringContainsString('Connector Write Guard', $output);
    }

    public function test_dry_run_job_execution_intercepted(): void
    {
        $this->createBaselineEvidence();

        $action = MpPriceAction::create([
            'store_id' => $this->store->id,
            'barcode' => 'BARCODE1',
            'status' => 'pending',
            'old_price' => 100.0,
            'requested_price' => 95.0,
            'action_type' => 'price_change',
            'trigger_type' => 'automatic',
        ]);

        config([
            'marketplace.trendyol.dry_run_enabled' => true,
        ]);

        $mockReval = \Mockery::mock(\App\Services\Marketplace\MarketplacePriceActionRevalidatorService::class);
        $mockReval->shouldReceive('revalidateAtExecution')->andReturn(true);
        $this->app->instance(\App\Services\Marketplace\MarketplacePriceActionRevalidatorService::class, $mockReval);

        // Dispatch & handle job
        $job = new PushMarketplacePriceActionJob($action->id);
        $job->handle(app(\App\Services\Marketplace\MarketplaceConnectorManager::class));

        // Refresh action and assert status
        $action->refresh();
        $this->assertEquals('dry_run_completed', $action->status);
    }
}
