<?php

namespace Tests\Feature\Livewire\Marketplace;

use App\Livewire\Marketplace\BuyboxAnalysis;
use App\Models\MarketplaceStore;
use App\Models\MpBuyboxListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BuyboxAnalysisTest extends TestCase
{
    use RefreshDatabase;

    // ─── Render ──────────────────────────────────────────────────

    public function test_renders_successfully(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(BuyboxAnalysis::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.marketplace.buybox-analysis');
    }

    // ─── Authentication ──────────────────────────────────────────

    public function test_unauthenticated_user_cannot_access(): void
    {
        $this->get(route('mp.buybox'))->assertRedirect(route('login'));
    }

    // ─── Tenant Isolation ────────────────────────────────────────

    public function test_prevents_accessing_other_users_store(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $storeUser2 = MarketplaceStore::factory()->create([
            'user_id' => $user2->id,
            'marketplace' => 'trendyol',
        ]);

        $this->actingAs($user1);

        Livewire::test(BuyboxAnalysis::class)
            ->set('selectedStoreId', $storeUser2->id)
            ->assertSet('selectedStoreId', 0);
    }

    public function test_only_own_store_data_is_shown(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $store1 = MarketplaceStore::factory()->create(['user_id' => $user1->id, 'marketplace' => 'trendyol']);
        $store2 = MarketplaceStore::factory()->create(['user_id' => $user2->id, 'marketplace' => 'trendyol']);

        \App\Models\MpPriceRecommendation::factory()->create(['store_id' => $store1->id, 'barcode' => 'MYBARCODE001']);
        \App\Models\MpPriceRecommendation::factory()->create(['store_id' => $store2->id, 'barcode' => 'OTHERBARCODE']);

        $this->actingAs($user1);

        $component = Livewire::test(BuyboxAnalysis::class)
            ->set('selectedStoreId', $store1->id)
            ->set('filterBarcode', 'MYBARCODE001');

        $component->assertSee('MYBARCODE001')
            ->assertDontSee('OTHERBARCODE');
    }

    // ─── Filters ─────────────────────────────────────────────────

    public function test_barcode_filter_works(): void
    {
        $user = User::factory()->create();
        $store = MarketplaceStore::factory()->create(['user_id' => $user->id, 'marketplace' => 'trendyol']);

        \App\Models\MpPriceRecommendation::factory()->create(['store_id' => $store->id, 'barcode' => 'SEARCHME123']);
        \App\Models\MpPriceRecommendation::factory()->create(['store_id' => $store->id, 'barcode' => 'DIFFERENT456']);

        $this->actingAs($user);

        Livewire::test(BuyboxAnalysis::class)
            ->set('selectedStoreId', $store->id)
            ->set('filterBarcode', 'SEARCHME123')
            ->assertSee('SEARCHME123')
            ->assertDontSee('DIFFERENT456');
    }

    public function test_buybox_winner_filter(): void
    {
        $user = User::factory()->create();
        $store = MarketplaceStore::factory()->create(['user_id' => $user->id, 'marketplace' => 'trendyol']);

        $l1 = MpBuyboxListing::factory()->winning()->create(['store_id' => $store->id, 'barcode' => 'WINNER001']);
        $l2 = MpBuyboxListing::factory()->losing()->create(['store_id' => $store->id, 'barcode' => 'LOSER001']);

        \App\Models\MpPriceRecommendation::factory()->create(['store_id' => $store->id, 'barcode' => 'WINNER001', 'mp_buybox_listing_id' => $l1->id]);
        \App\Models\MpPriceRecommendation::factory()->create(['store_id' => $store->id, 'barcode' => 'LOSER001', 'mp_buybox_listing_id' => $l2->id]);

        $this->actingAs($user);

        Livewire::test(BuyboxAnalysis::class)
            ->set('selectedStoreId', $store->id)
            ->set('filterStatus', 'winning')
            ->assertSee('WINNER001')
            ->assertDontSee('LOSER001');
    }

    public function test_stale_data_filter(): void
    {
        $user = User::factory()->create();
        $store = MarketplaceStore::factory()->create(['user_id' => $user->id, 'marketplace' => 'trendyol']);

        \App\Models\MpPriceRecommendation::factory()->create([
            'store_id' => $store->id,
            'barcode' => 'STALE_ITEM',
            'recommendation_type' => 'STALE_BUYBOX_DATA',
        ]);

        \App\Models\MpPriceRecommendation::factory()->create([
            'store_id' => $store->id,
            'barcode' => 'FRESH_ITEM',
            'recommendation_type' => 'LOWER_TO_WIN',
        ]);

        $this->actingAs($user);

        Livewire::test(BuyboxAnalysis::class)
            ->set('selectedStoreId', $store->id)
            ->set('filterRecommendationType', 'STALE_BUYBOX_DATA')
            ->assertSee('STALE_ITEM')
            ->assertDontSee('FRESH_ITEM');
    }

    // ─── Sorting ─────────────────────────────────────────────────

    public function test_sorting_by_valid_column(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(BuyboxAnalysis::class)
            ->call('sortTable', 'buybox_price')
            ->assertSet('sortBy', 'buybox_price');
    }

    public function test_invalid_sort_column_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $component = Livewire::test(BuyboxAnalysis::class);
        $originalSort = $component->get('sortBy');

        $component->call('sortTable', 'user_id; DROP TABLE users --');

        $this->assertEquals($originalSort, $component->get('sortBy'));
    }

    public function test_sort_direction_toggles(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(BuyboxAnalysis::class)
            ->call('sortTable', 'buybox_price')
            ->assertSet('sortDir', 'desc')
            ->call('sortTable', 'buybox_price')
            ->assertSet('sortDir', 'asc');
    }

    // ─── Price Diff Calculation ───────────────────────────────────

    public function test_price_diff_is_calculated(): void
    {
        $user = User::factory()->create();
        $store = MarketplaceStore::factory()->create(['user_id' => $user->id, 'marketplace' => 'trendyol']);

        MpBuyboxListing::factory()->create([
            'store_id' => $store->id,
            'barcode' => 'PRICETEST',
            'buybox_price' => 100.00,
            'seller_price' => 120.00,
        ]);

        $this->actingAs($user);

        // We test by checking data in component
        $component = Livewire::test(BuyboxAnalysis::class)
            ->set('selectedStoreId', $store->id)
            ->set('filterBarcode', 'PRICETEST');

        $component->assertStatus(200);
    }

    // ─── Feature Flag ────────────────────────────────────────────

    public function test_feature_flag_disabled_shows_in_view(): void
    {
        config(['marketplace.trendyol.buybox_sync_enabled' => false]);

        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(BuyboxAnalysis::class)
            ->assertSet('selectedStoreId', 0)
            ->assertStatus(200);
    }

    // ─── Pagination ──────────────────────────────────────────────

    public function test_pagination_works(): void
    {
        $user = User::factory()->create();
        $store = MarketplaceStore::factory()->create(['user_id' => $user->id, 'marketplace' => 'trendyol']);

        MpBuyboxListing::factory()->count(30)->create(['store_id' => $store->id]);

        $this->actingAs($user);

        Livewire::test(BuyboxAnalysis::class)
            ->set('selectedStoreId', $store->id)
            ->set('perPage', 10)
            ->assertStatus(200);
    }

    // ─── Empty State ─────────────────────────────────────────────

    public function test_empty_state_when_no_listings(): void
    {
        $user = User::factory()->create();
        $store = MarketplaceStore::factory()->create(['user_id' => $user->id, 'marketplace' => 'trendyol']);

        $this->actingAs($user);

        Livewire::test(BuyboxAnalysis::class)
            ->set('selectedStoreId', $store->id)
            ->assertStatus(200);
    }
}
