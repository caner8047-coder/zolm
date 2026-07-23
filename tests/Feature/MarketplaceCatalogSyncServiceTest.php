<?php

namespace Tests\Feature;

use App\Models\LegalEntity;
use App\Models\IntegrationSyncProfile;
use App\Models\MarketplaceStore;
use App\Models\MpProduct;
use App\Models\User;
use App\Services\Marketplace\MarketplaceCatalogSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceCatalogSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_listing_delivery_terms_from_catalog_payload(): void
    {
        $user = User::factory()->create();
        $entity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Test Ltd',
            'tax_number' => '1234567890',
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'Zem Trendyol',
            'seller_id' => 'TY-DELIVERY-TERMS',
            'status' => 'active',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
        IntegrationSyncProfile::query()->create(array_merge(
            IntegrationSyncProfile::defaults(),
            ['store_id' => $store->id]
        ));

        $product = MpProduct::query()->create([
            'user_id' => $user->id,
            'barcode' => '8690000000999',
            'stock_code' => 'TY-TERM-1',
            'product_name' => 'Termin Test Ürünü',
            'sale_price' => 1000,
            'cogs' => 500,
            'packaging_cost' => 10,
            'vat_rate' => 20,
            'stock_quantity' => 10,
        ]);

        app(MarketplaceCatalogSyncService::class)->sync($store, [[
            'product' => [
                'external_product_id' => 'TY-EXT-1',
                'stock_code' => 'TY-TERM-1',
                'barcode' => '8690000000999',
                'title' => 'Termin Test Ürünü',
                'images' => [
                    ['url' => 'https://cdn.example.test/termin-cover.jpg'],
                    ['imageUrl' => 'https://cdn.example.test/termin-detail.jpg'],
                ],
            ],
            'listing' => [
                'listing_id' => 'TY-LST-1',
                'listing_status' => 'active',
                'sale_price' => 1200,
                'stock_quantity' => 8,
                'shipping_days' => 3,
                'shipping_type' => 'Standart',
                'fast_delivery_type' => 'Hızlı teslimat',
            ],
        ]]);

        $this->assertDatabaseHas('channel_listings', [
            'store_id' => $store->id,
            'mp_product_id' => $product->id,
            'listing_id' => 'TY-LST-1',
            'shipping_days' => 3,
            'shipping_type' => 'Standart',
            'fast_delivery_type' => 'Hızlı teslimat',
        ]);

        $this->assertSame('https://cdn.example.test/termin-cover.jpg', $product->fresh()->image_url);
        $this->assertSame([
            'https://cdn.example.test/termin-cover.jpg',
            'https://cdn.example.test/termin-detail.jpg',
        ], $product->fresh()->image_urls);
    }

}
