<?php

namespace Tests\Feature\Livewire\Marketplace;

use App\Models\MarketplaceStore;
use App\Models\MpPriceAction;
use App\Models\MpPriceShadowRecord;
use App\Models\MpPriceShadowEvaluation;
use App\Models\MpPricePilotProduct;
use App\Models\MpPriceRecommendation;
use App\Models\MpPriceCanaryCertification;
use App\Models\IntegrationPushRun;
use App\Models\IntegrationConnection;
use App\Models\ChannelListing;
use App\Models\ChannelProduct;
use App\Models\User;
use App\Services\Marketplace\MarketplacePricePilotService;
use App\Services\Marketplace\MarketplaceCanaryReadinessService;
use App\Jobs\PushMarketplacePriceActionJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
            DB::table('integration_push_runs')->insert([
                'store_id' => $this->store->id,
                'channel_listing_id' => null,
                'push_type' => 'price',
                'status' => 'completed',
                'target_price' => 95.0,
                'created_at' => now()->subHours(2),
                'updated_at' => now()->subHours(2),
            ]);
        }

        // 20 queue jobs
        for ($i = 0; $i < 20; $i++) {
            DB::table('mp_price_actions')->insert([
                'store_id' => $this->store->id,
                'barcode' => 'BARCODE1',
                'status' => 'success',
                'old_price' => 100.0,
                'requested_price' => 95.0,
                'action_type' => 'price_change',
                'trigger_type' => 'manual',
                'created_at' => now()->subHours(2),
                'updated_at' => now()->subHours(2),
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

        $sellerId = $this->store->seller_id;
        $mockPayload = [
            'content' => [
                [
                    'id' => 'EXT-1234',
                    'barcode' => 'BARCODE1',
                    'title' => 'Test Product',
                    'price' => [
                        'salePrice' => 100.0,
                        'listPrice' => 120.0,
                    ],
                    'stock' => [
                        'quantity' => 10,
                    ],
                    'approved' => true,
                    'onSale' => true,
                ]
            ],
            'totalPages' => 1,
            'totalElements' => 1,
        ];

        Http::fake([
            "https://apigw.trendyol.com/integration/product/sellers/{$sellerId}/products/approved*" => Http::response($mockPayload, 200),
            "https://apigw.trendyol.com/integration/product/sellers/{$sellerId}/products*" => Http::response($mockPayload, 200),
            "*" => Http::response([], 200),
        ]);

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
        $this->assertStringContainsString('Readiness Kontrolü', $output);
        $this->assertStringContainsString('Connector Write Guard', $output);
        $this->assertStringContainsString('Sertifikasyon Sonucu', $output);
        $this->assertStringContainsString('Correlation ID', $output);

        // Assert certification was persisted
        $cert = MpPriceCanaryCertification::where('store_id', $this->store->id)->first();
        $this->assertNotNull($cert);
        $this->assertEquals(0, $cert->real_price_push_count);
        $this->assertFalse($cert->listing_price_changed);
        $this->assertContains($cert->certification_result, [
            'certified_zero_write', 'blocked_insufficient_evidence', 'blocked_readiness', 'blocked_approval',
        ]);
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
