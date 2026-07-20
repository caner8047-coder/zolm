<?php

namespace Tests\Feature\Livewire\Marketplace;

use App\Models\MarketplaceStore;
use App\Models\MpPriceAction;
use App\Models\MpPriceShadowRecord;
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

    public function test_readiness_fails_if_insufficient_shadow_duration(): void
    {
        // Earliest shadow record is only 5 hours ago
        MpPriceShadowRecord::create([
            'store_id' => $this->store->id,
            'barcode' => 'BARCODE1',
            'current_price' => 100.0,
            'risk_level' => 'low',
            'is_actionable' => true,
            'recommendation_type' => 'LOWER_TO_WIN',
            'simulated_at' => now()->subHours(5),
        ]);

        $service = app(MarketplaceCanaryReadinessService::class);
        $res = $service->checkReadiness($this->store);

        $this->assertFalse($res['ready']);
        $this->assertEquals('insufficient_shadow_evidence', $res['decision']);
        $this->assertContains('Shadow Mode Süresi Yetersiz (5 saat < 24 saat)', $res['failed_criteria']);
    }

    public function test_readiness_fails_if_api_success_rate_below_99(): void
    {
        // 48 hours of shadow mode
        MpPriceShadowRecord::create([
            'store_id' => $this->store->id,
            'barcode' => 'BARCODE1',
            'current_price' => 100.0,
            'risk_level' => 'low',
            'is_actionable' => true,
            'recommendation_type' => 'LOWER_TO_WIN',
            'simulated_at' => now()->subHours(48),
        ]);

        // 1 failed and 2 success actions (66.6% success rate)
        MpPriceAction::create([
            'store_id' => $this->store->id,
            'barcode' => 'BARCODE1',
            'status' => 'failed',
            'failure_code' => 'API_ERROR',
            'old_price' => 100.0,
            'requested_price' => 98.0,
            'action_type' => 'price_change',
            'trigger_type' => 'automatic',
        ]);
        
        MpPriceAction::create([
            'store_id' => $this->store->id,
            'barcode' => 'BARCODE1',
            'status' => 'success',
            'old_price' => 100.0,
            'requested_price' => 98.0,
            'action_type' => 'price_change',
            'trigger_type' => 'automatic',
        ]);

        $service = app(MarketplaceCanaryReadinessService::class);
        $res = $service->checkReadiness($this->store);

        $this->assertFalse($res['ready']);
        $this->assertEquals('blocked_api_health', $res['decision']);
    }

    public function test_readiness_fails_if_min_price_violation(): void
    {
        // 48 hours of shadow mode
        MpPriceShadowRecord::create([
            'store_id' => $this->store->id,
            'barcode' => 'BARCODE1',
            'current_price' => 100.0,
            'risk_level' => 'low',
            'is_actionable' => true,
            'recommendation_type' => 'LOWER_TO_WIN',
            'simulated_at' => now()->subHours(48),
        ]);

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

        $service = app(MarketplaceCanaryReadinessService::class);
        $res = $service->checkReadiness($this->store);

        $this->assertFalse($res['ready']);
        $this->assertEquals('blocked_margin_safety', $res['decision']);
    }

    public function test_readiness_succeeds_if_all_criteria_passed(): void
    {
        // 48 hours of shadow mode
        MpPriceShadowRecord::create([
            'store_id' => $this->store->id,
            'barcode' => 'BARCODE1',
            'current_price' => 100.0,
            'risk_level' => 'low',
            'is_actionable' => true,
            'recommendation_type' => 'LOWER_TO_WIN',
            'simulated_at' => now()->subHours(48),
        ]);

        $service = app(MarketplaceCanaryReadinessService::class);
        $res = $service->checkReadiness($this->store);

        $this->assertTrue($res['ready']);
        $this->assertEquals('ready_for_single_product_canary', $res['decision']);
    }
}
