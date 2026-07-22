<?php

namespace Tests\Feature\Livewire\Marketplace;

use App\Models\MarketplaceStore;
use App\Models\MpPriceAction;
use App\Models\MpPriceShadowRecord;
use App\Models\MpPriceShadowEvaluation;
use App\Models\MpPricePilotProduct;
use App\Models\IntegrationPushRun;
use App\Models\User;
use App\Services\Marketplace\MarketplaceCanaryReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceCanaryReadinessTest extends TestCase
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
            'seller_id' => '9456',
        ]);
    }

    private function createValidBaselineEvidence(): void
    {
        // Shadow mode duration: 48h
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

            // 20 evaluations
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

        // 1 pilot product
        MpPricePilotProduct::create([
            'store_id' => $this->store->id,
            'barcode' => 'BARCODE1',
            'mode' => 'shadow',
            'inclusion_reason' => 'test',
        ]);
    }

    public function test_readiness_fails_if_insufficient_shadow_duration(): void
    {
        // Baseline except simulated_at is only 5h ago
        $this->createValidBaselineEvidence();
        
        // Update earliest record to make duration short
        MpPriceShadowRecord::where('store_id', $this->store->id)->update(['simulated_at' => now()->subHours(5)]);

        $service = app(MarketplaceCanaryReadinessService::class);
        $res = $service->checkReadiness($this->store);

        $this->assertFalse($res['ready']);
        $this->assertEquals('insufficient_shadow_evidence', $res['decision']);
    }

    public function test_readiness_fails_if_api_success_rate_below_99(): void
    {
        $this->createValidBaselineEvidence();

        // Add 1 failed IntegrationPushRun
        IntegrationPushRun::create([
            'store_id' => $this->store->id,
            'channel_listing_id' => null,
            'push_type' => 'price',
            'status' => 'failed',
            'target_price' => 95.0,
        ]);

        $service = app(MarketplaceCanaryReadinessService::class);
        $res = $service->checkReadiness($this->store);

        $this->assertFalse($res['ready']);
        $this->assertEquals('blocked_api_health', $res['decision']);
    }

    public function test_readiness_fails_if_min_price_violation(): void
    {
        $this->createValidBaselineEvidence();

        MpPriceAction::create([
            'store_id' => $this->store->id,
            'barcode' => 'BARCODE1',
            'status' => 'blocked_margin',
            'failure_code' => 'BLOCKED_MARGIN',
            'old_price' => 100.0,
            'requested_price' => 80.0,
            'action_type' => 'price_change',
            'trigger_type' => 'automatic',
        ]);

        config(['marketplace.trendyol.canary_enabled' => true]);

        $service = app(MarketplaceCanaryReadinessService::class);
        $res = $service->checkReadiness($this->store);

        $this->assertFalse($res['ready']);
        $this->assertEquals('blocked_margin_safety', $res['decision']);
    }

    public function test_readiness_succeeds_if_all_criteria_passed(): void
    {
        $this->createValidBaselineEvidence();

        $service = app(MarketplaceCanaryReadinessService::class);
        $res = $service->checkReadiness($this->store);

        $this->assertTrue($res['ready']);
        $this->assertEquals('ready_for_single_product_canary', $res['decision']);
    }
}
