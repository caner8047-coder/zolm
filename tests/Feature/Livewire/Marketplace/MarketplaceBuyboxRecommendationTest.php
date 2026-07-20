<?php

namespace Tests\Feature\Livewire\Marketplace;

use App\Models\MarketplaceStore;
use App\Models\MpBuyboxListing;
use App\Models\MpPriceRecommendation;
use App\Models\MpProduct;
use App\Models\User;
use App\Services\Marketplace\MarketplaceBuyboxRecommendationService;
use App\Services\Marketplace\MarketplacePricePolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceBuyboxRecommendationTest extends TestCase
{
    use RefreshDatabase;

    public function test_recommendation_generated_with_minimum_safe_price(): void
    {
        $user = User::factory()->create(['role' => 'operator']);
        $store = MarketplaceStore::factory()->create(['user_id' => $user->id, 'marketplace' => 'trendyol']);

        // Create product with COGS = 100
        MpProduct::factory()->create([
            'user_id' => $user->id,
            'barcode' => 'TESTPRODUCT01',
            'cogs' => 100.00,
            'commission_rate' => 15.0,
        ]);

        $listing = MpBuyboxListing::factory()->losing()->create([
            'store_id' => $store->id,
            'barcode' => 'TESTPRODUCT01',
            'seller_price' => 300.00,
            'buybox_price' => 250.00,
            'retrieved_at' => now(),
        ]);

        $service = app(MarketplaceBuyboxRecommendationService::class);
        $rec = $service->generateForListing($listing);

        $this->assertNotNull($rec);
        $this->assertEquals('TESTPRODUCT01', $rec->barcode);
        $this->assertGreaterThan(100.00, (float) $rec->minimum_safe_price);
        $this->assertGreaterThanOrEqual((float) $rec->minimum_safe_price, (float) $rec->recommended_price);
        $this->assertEquals('LOWER_TO_WIN', $rec->recommendation_type);
    }

    public function test_missing_cost_blocks_recommendation_action(): void
    {
        $user = User::factory()->create(['role' => 'operator']);
        $store = MarketplaceStore::factory()->create(['user_id' => $user->id, 'marketplace' => 'trendyol']);

        // Listing without product cost (cogs = 0)
        $listing = MpBuyboxListing::factory()->create([
            'store_id' => $store->id,
            'barcode' => 'NOCOST01',
            'seller_price' => 200.00,
            'buybox_price' => 150.00,
            'retrieved_at' => now(),
        ]);

        $service = app(MarketplaceBuyboxRecommendationService::class);
        $rec = $service->generateForListing($listing);

        $this->assertEquals('MISSING_COST', $rec->recommendation_type);
        $this->assertEquals('blocked', $rec->risk_level);
        $this->assertFalse($rec->isActionable());
    }

    public function test_buybox_below_minimum_safe_price_yields_protect_margin(): void
    {
        $user = User::factory()->create(['role' => 'operator']);
        $store = MarketplaceStore::factory()->create(['user_id' => $user->id, 'marketplace' => 'trendyol']);

        MpProduct::factory()->create([
            'user_id' => $user->id,
            'barcode' => 'HIGHCOST01',
            'cogs' => 200.00,
            'commission_rate' => 20.0,
        ]);

        // Buybox price is 150 (below minimum safe price for 200 TL COGS)
        $listing = MpBuyboxListing::factory()->losing()->create([
            'store_id' => $store->id,
            'barcode' => 'HIGHCOST01',
            'seller_price' => 300.00,
            'buybox_price' => 150.00,
            'retrieved_at' => now(),
        ]);

        $service = app(MarketplaceBuyboxRecommendationService::class);
        $rec = $service->generateForListing($listing);

        $this->assertEquals('PROTECT_MARGIN', $rec->recommendation_type);
        $this->assertGreaterThan(150.00, (float) $rec->minimum_safe_price);
    }
}
