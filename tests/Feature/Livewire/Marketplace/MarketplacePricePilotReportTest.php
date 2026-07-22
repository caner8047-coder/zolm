<?php

namespace Tests\Feature\Livewire\Marketplace;

use App\Models\MarketplaceStore;
use App\Models\MpPriceShadowRecord;
use App\Models\MpPriceShadowEvaluation;
use App\Models\MpPriceAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplacePricePilotReportTest extends TestCase
{
    use RefreshDatabase;

    protected MarketplaceStore $store1;
    protected MarketplaceStore $store2;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'operator']);
        $this->store1 = MarketplaceStore::factory()->create([
            'user_id' => $this->user->id,
            'marketplace' => 'trendyol',
            'seller_id' => '123456',
        ]);

        $this->store2 = MarketplaceStore::factory()->create([
            'user_id' => $this->user->id,
            'marketplace' => 'trendyol',
            'seller_id' => '789012',
        ]);
    }

    public function test_report_command_outputs_table_by_default(): void
    {
        MpPriceShadowRecord::create([
            'store_id' => $this->store1->id,
            'barcode' => 'BARCODE1',
            'current_price' => 100.00,
            'recommended_price' => 95.00,
            'buybox_price' => 95.00,
            'recommendation_type' => 'MATCH_BUYBOX',
            'risk_level' => 'low',
            'is_actionable' => true,
            'simulated_at' => now(),
        ]);

        MpPriceShadowRecord::create([
            'store_id' => $this->store2->id, // Store 2 record (should be ignored for Store 1 report)
            'barcode' => 'BARCODE2',
            'current_price' => 200.00,
            'recommended_price' => 190.00,
            'buybox_price' => 190.00,
            'recommendation_type' => 'MATCH_BUYBOX',
            'risk_level' => 'low',
            'is_actionable' => true,
            'simulated_at' => now(),
        ]);

        $this->artisan('marketplace:price-pilot', [
            'action' => 'report',
            'store_id' => $this->store1->id,
            '--format' => 'table',
            '--include-products' => true,
        ])
        ->expectsOutput('=== ZOLM Pilot & Shadow Mode KPI Raporu ===')
        ->expectsOutput('Toplam Gölge Öneri: 1') // Only store 1 record counted
        ->assertExitCode(0);
    }

    public function test_report_command_outputs_json(): void
    {
        MpPriceShadowRecord::create([
            'store_id' => $this->store1->id,
            'barcode' => 'BARCODE1',
            'current_price' => 100.00,
            'recommended_price' => 95.00,
            'buybox_price' => 95.00,
            'recommendation_type' => 'MATCH_BUYBOX',
            'risk_level' => 'low',
            'is_actionable' => true,
            'simulated_at' => now(),
        ]);

        $this->artisan('marketplace:price-pilot', [
            'action' => 'report',
            'store_id' => $this->store1->id,
            '--format' => 'json',
            '--include-products' => true,
        ])
        ->assertExitCode(0);
    }

    public function test_report_command_outputs_excel(): void
    {
        MpPriceShadowRecord::create([
            'store_id' => $this->store1->id,
            'barcode' => 'BARCODE1',
            'current_price' => 100.00,
            'recommended_price' => 95.00,
            'buybox_price' => 95.00,
            'recommendation_type' => 'MATCH_BUYBOX',
            'risk_level' => 'low',
            'is_actionable' => true,
            'simulated_at' => now(),
        ]);

        $this->artisan('marketplace:price-pilot', [
            'action' => 'report',
            'store_id' => $this->store1->id,
            '--format' => 'excel',
        ])
        ->assertExitCode(0);
    }

    public function test_emergency_stop_notification_delivery(): void
    {
        config(['marketplace.features.notifications_enabled' => true]);

        $service = app(\App\Services\Marketplace\MarketplacePriceEmergencyStopService::class);
        $service->activateEmergencyStop($this->store1->id, 'Tatbikat Gerekçesi');

        $this->assertDatabaseHas('app_notifications', [
            'store_id' => $this->store1->id,
            'type' => 'risk_critical',
            'severity' => 'danger',
        ]);
    }

    public function test_shadow_accuracy_notification_delivery(): void
    {
        config(['marketplace.features.notifications_enabled' => true]);

        $record = MpPriceShadowRecord::create([
            'store_id' => $this->store1->id,
            'barcode' => 'SHADOWACC1',
            'current_price' => 100.00,
            'recommended_price' => 95.00,
            'buybox_price' => 95.00,
            'recommendation_type' => 'MATCH_BUYBOX',
            'risk_level' => 'low',
            'is_actionable' => true,
            'simulated_at' => now(),
        ]);

        // Create updated buybox listing where recommended price (95) > actual buybox (80) -> loss/fail
        \App\Models\MpBuyboxListing::factory()->create([
            'store_id' => $this->store1->id,
            'barcode' => 'SHADOWACC1',
            'buybox_price' => 80.00,
            'seller_rank' => 3,
        ]);

        $shadowService = app(\App\Services\Marketplace\MarketplacePriceShadowService::class);
        $shadowService->evaluateShadowRecords($this->store1);

        $this->assertDatabaseHas('app_notifications', [
            'store_id' => $this->store1->id,
            'type' => 'risk_warning',
            'severity' => 'warning',
        ]);
    }
}
