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
use App\Services\Marketplace\MarketplaceCanaryReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\CreatesCanaryEvidence;
use Tests\TestCase;

class MarketplaceCanaryExpansionTest extends TestCase
{
    use RefreshDatabase, CreatesCanaryEvidence;

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
        // Use shared trait for BARCODE1 baseline
        $this->createBaselineReadinessEvidence($this->store, 'BARCODE1');

        // Add BARCODE2 pilot product
        MpPricePilotProduct::firstOrCreate(
            ['store_id' => $this->store->id, 'barcode' => 'BARCODE2'],
            ['mode' => 'shadow', 'inclusion_reason' => 'test']
        );
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

        // Create initial approval WITH real readiness fingerprint
        $this->createFingerprintedApproval($this->store, $this->adminUser->id, ['BARCODE1']);

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
