<?php

namespace Tests\Feature\Hepsiburada;

use App\Models\MarketplaceStore;
use App\Models\IntegrationConnection;
use App\Models\ChannelProduct;
use App\Models\ChannelListing;
use App\Services\Marketplace\MarketplaceSyncService;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HepsiburadaCatalogProductsTest extends TestCase
{
    use RefreshDatabase;
    /**
     * @param  array<string, mixed>  $credentials
     */
    protected function makeStore(array $credentials = []): MarketplaceStore
    {
        $user = \App\Models\User::factory()->create();

        $le = \App\Models\LegalEntity::create([
            'user_id'      => $user->id,
            'name'         => 'Test Org',
            'company_name' => 'Co',
            'tax_office'   => 'Kadikoy',
            'tax_number'   => '1234567890',
            'address'      => 'Istanbul',
        ]);

        $store = MarketplaceStore::create([
            'user_id'         => $user->id,
            'legal_entity_id' => $le->id,
            'marketplace'     => 'hepsiburada',
            'store_name'      => 'HB Test',
            'seller_id'       => '123456',
            'timezone'        => 'Europe/Istanbul',
            'currency'        => 'TRY',
            'is_active'       => true,
        ]);

        $connection = IntegrationConnection::create([
            'store_id'              => $store->id,
            'provider'              => 'hepsiburada',
            'auth_type'             => 'merchant_id_service_key',
            'credentials_encrypted' => array_merge([
                'api_key'    => 'service-key',
                'extra_user' => 'zem_dev',
            ], $credentials),
            'api_base_url'          => 'https://oms-external.hepsiburada.com/',
            'status'                => 'configured',
        ]);

        $store->setRelation('connection', $connection);

        return $store;
    }

    public function test_it_syncs_catalog_products_successfully(): void
    {
        Http::fake([
            'https://mpop.hepsiburada.com/product/api/products/all-products-of-merchant/123456*' => Http::response([
                'items' => [
                    [
                        'hepsiburadaSku' => 'HB-CAT-1',
                        'merchantSku' => 'ZOLM-CAT-1',
                        'barcode' => '869000000999',
                        'productName' => 'Tasarım Masa',
                        'description' => 'Ahşap tasarım yemek masası',
                        'brand' => 'ZOLM brand',
                        'categoryName' => 'Masa',
                        'vatRate' => 10.00,
                        'images' => ['https://images.com/masa1.jpg'],
                        'attributes' => [
                            ['attributeId' => 'materyal', 'attributeValue' => 'Ahşap'],
                        ],
                        'productStatus' => 'Approved',
                        'trackingId' => 'track-112233',
                    ]
                ],
                'totalCount' => 1,
            ], 200),
        ]);

        $store = $this->makeStore();

        // Create IntegrationSyncRun to run the sync
        $run = \App\Models\IntegrationSyncRun::create([
            'store_id' => $store->id,
            'sync_type' => 'catalog_products',
            'trigger_type' => 'manual',
            'status' => 'queued',
            'notes_json' => [
                'options' => [
                    'start_date' => now()->subDay()->toIso8601String(),
                    'end_date' => now()->toIso8601String(),
                ]
            ],
        ]);

        app(MarketplaceSyncService::class)->run($run->id);

        $this->assertDatabaseHas('channel_products', [
            'store_id' => $store->id,
            'external_product_id' => 'HB-CAT-1',
            'stock_code' => 'ZOLM-CAT-1',
            'barcode' => '869000000999',
            'title' => 'Tasarım Masa',
            'brand' => 'ZOLM brand',
            'category_name' => 'Masa',
            'description' => 'Ahşap tasarım yemek masası',
            'approval_status' => 'Approved',
            'import_tracking_id' => 'track-112233',
            'is_catalog_product' => true,
        ]);

        $product = ChannelProduct::where('external_product_id', 'HB-CAT-1')->first();
        $this->assertNotNull($product);

        $this->assertCount(1, $product->images);
        $this->assertSame('https://images.com/masa1.jpg', $product->images[0]['url'] ?? null);

        $this->assertCount(1, $product->attributes);
        $this->assertSame('materyal', $product->attributes[0]['attributeId'] ?? null);

        // Verify request contract (URL, method, basic auth and user-agent presence)
        Http::assertSent(function ($request) {
            return $request->method() === 'GET'
                && str_contains($request->url(), 'product/api/products/all-products-of-merchant/123456')
                && $request->hasHeader('Authorization')
                && $request->hasHeader('User-Agent');
        });
    }
}
