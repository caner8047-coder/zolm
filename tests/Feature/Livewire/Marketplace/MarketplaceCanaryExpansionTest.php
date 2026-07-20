<?php

namespace Tests\Feature\Livewire\Marketplace;

use App\Models\MarketplaceStore;
use App\Models\MpPriceAction;
use App\Models\MpPriceShadowRecord;
use App\Models\MpPriceShadowEvaluation;
use App\Models\MpPricePilotProduct;
use App\Models\MpPriceCanaryApproval;
use App\Models\MpPriceCanaryStageResult;
use App\Models\IntegrationPushRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class MarketplaceCanaryExpansionTest extends TestCase
{
    use RefreshDatabase;

    protected MarketplaceStore $store;
    protected User $adminUser;
    protected User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create(['role' => 'admin']);
        $this->unauthorizedUser = User::factory()->create(['role' => 'manager']);
        
        $this->store = MarketplaceStore::factory()->create([
            'user_id' => $this->adminUser->id,
            'marketplace' => 'trendyol',
            'seller_id' => '9456',
        ]);

        // Mock pilot service to return low risk for test barcodes
        $mock = \Mockery::mock(\App\Services\Marketplace\MarketplacePricePilotService::class)->makePartial();
        $mock->shouldReceive('getRiskLevel')->andReturn('low');
        $this->app->instance(\App\Services\Marketplace\MarketplacePricePilotService::class, $mock);
    }

    private function createValidBaselineEvidence(): void
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

        // pilot products
        MpPricePilotProduct::create([
            'store_id' => $this->store->id,
            'barcode' => 'BARCODE1',
            'mode' => 'shadow',
            'inclusion_reason' => 'test',
        ]);
        
        MpPricePilotProduct::create([
            'store_id' => $this->store->id,
            'barcode' => 'BARCODE2',
            'mode' => 'shadow',
            'inclusion_reason' => 'test',
        ]);
    }

    public function test_expansion_fails_without_confirm(): void
    {
        $this->createValidBaselineEvidence();

        $code = Artisan::call('marketplace:price-pilot', [
            'action' => 'expand-canary',
            'store_id' => $this->store->id,
            '--products' => 'BARCODE1,BARCODE2',
            '--approved-by' => $this->adminUser->id,
        ]);

        $this->assertEquals(1, $code);
    }

    public function test_expansion_fails_if_unauthorized_user(): void
    {
        $this->createValidBaselineEvidence();

        $code = Artisan::call('marketplace:price-pilot', [
            'action' => 'expand-canary',
            'store_id' => $this->store->id,
            '--products' => 'BARCODE1,BARCODE2',
            '--approved-by' => $this->unauthorizedUser->id,
            '--confirm' => true,
        ]);

        $this->assertEquals(1, $code);
    }

    public function test_expansion_fails_if_no_single_product_stage_certificate(): void
    {
        $this->createValidBaselineEvidence();

        $code = Artisan::call('marketplace:price-pilot', [
            'action' => 'expand-canary',
            'store_id' => $this->store->id,
            '--products' => 'BARCODE1,BARCODE2',
            '--approved-by' => $this->adminUser->id,
            '--confirm' => true,
        ]);

        $this->assertEquals(1, $code);
    }

    public function test_expansion_succeeds_when_all_conditions_passed(): void
    {
        $this->createValidBaselineEvidence();

        // Create success stage certificate
        MpPriceCanaryStageResult::create([
            'store_id' => $this->store->id,
            'barcode' => 'BARCODE1',
            'stage' => 'single_product',
            'status' => 'approved_for_expansion',
        ]);

        // Create initial approval
        MpPriceCanaryApproval::create([
            'store_id' => $this->store->id,
            'approved_by' => $this->adminUser->id,
            'approval_scope' => 'single_product',
            'approved_product_ids' => ['BARCODE1'],
            'expires_at' => now()->addHours(24),
            'status' => 'approved',
        ]);

        $code = Artisan::call('marketplace:price-pilot', [
            'action' => 'expand-canary',
            'store_id' => $this->store->id,
            '--products' => 'BARCODE1,BARCODE2',
            '--approved-by' => $this->adminUser->id,
            '--confirm' => true,
        ]);

        $this->assertEquals(0, $code);

        // Verify new approval created
        $newApproval = MpPriceCanaryApproval::where('store_id', $this->store->id)
            ->where('status', 'approved')
            ->where('approval_scope', 'three_products')
            ->first();

        $this->assertNotNull($newApproval);
        $this->assertContains('BARCODE1', $newApproval->approved_product_ids);
        $this->assertContains('BARCODE2', $newApproval->approved_product_ids);
    }
}
