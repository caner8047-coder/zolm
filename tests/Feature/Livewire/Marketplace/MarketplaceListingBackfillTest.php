<?php

namespace Tests\Feature\Livewire\Marketplace;

use App\Models\MarketplaceStore;
use App\Models\MpProduct;
use App\Models\User;
use App\Models\ChannelProduct;
use App\Models\ChannelListing;
use App\Models\Role;
use App\Models\IntegrationConnection;
use App\Models\IntegrationSyncProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketplaceListingBackfillTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $tenantUser;
    protected MarketplaceStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $operatorRole = Role::create(['name' => 'Operator', 'slug' => 'operator']);

        $this->adminUser = User::factory()->create([
            'email' => 'admin@zolm.test',
            'role_id' => $adminRole->id,
        ]);

        $this->tenantUser = User::factory()->create([
            'email' => 'tenant@zolm.test',
            'role_id' => $operatorRole->id,
        ]);

        $this->store = MarketplaceStore::factory()->create([
            'user_id' => $this->tenantUser->id,
            'store_name' => 'Test Store',
            'marketplace' => 'trendyol',
            'seller_id' => '9456',
            'status' => 'active',
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

        IntegrationSyncProfile::query()->create(array_merge(
            IntegrationSyncProfile::defaultsForMarketplace('trendyol'),
            [
                'store_id' => $this->store->id,
                'auto_match_enabled' => true,
                'barcode_fallback_enabled' => true,
            ]
        ));

        // Create baseline products for User 1 (Admin)
        $barcodes = ['BARCODE1', 'ZEM-DRYRUN-CERT-001', 'CANARYBARCODE'];
        foreach ($barcodes as $bc) {
            MpProduct::create([
                'user_id' => $this->adminUser->id,
                'barcode' => $bc,
                'stock_code' => "{$bc}-STK",
                'product_name' => "Admin Test Product {$bc}",
                'sale_price' => 100.00,
                'stock_quantity' => 10,
                'status' => 'active',
                'product_type' => 'single',
            ]);
        }

        // Setup HTTP fakes for Trendyol approved products endpoint
        $mockContent = [];
        foreach ($barcodes as $idx => $bc) {
            $mockContent[] = [
                'id' => "EXT-{$idx}",
                'contentId' => "CONTENT-{$bc}",
                'productMainId' => "MAIN-{$bc}",
                'title' => "Admin Test Product {$bc}",
                'brand' => ['name' => 'Zem'],
                'category' => ['name' => 'Home'],
                'vatRate' => 20,
                'barcode' => $bc,
                'stockCode' => "{$bc}-STK",
                'price' => [
                    'salePrice' => 100.0,
                    'listPrice' => 110.0,
                ],
                'stock' => [
                    'quantity' => 10,
                ],
                'approved' => true,
                'onSale' => true,
            ];
        }

        $mockPayload = [
            'content' => $mockContent,
            'totalPages' => 1,
            'totalElements' => count($mockContent),
        ];

        Http::fake([
            "https://apigw.trendyol.com/integration/product/sellers/9456/products/approved*" => Http::response($mockPayload, 200),
            "https://apigw.trendyol.com/integration/product/sellers/9456/products*" => Http::response($mockPayload, 200),
            "*" => Http::response([], 200),
        ]);
    }

    public function test_backfill_command_requires_dry_run_or_confirm(): void
    {
        $code = Artisan::call('marketplace:listings:backfill', [
            'store_id' => $this->store->id,
        ]);

        $this->assertEquals(1, $code);
        $this->assertStringContainsString("Lütfen '--dry-run' veya '--confirm' parametrelerinden birini belirtin", Artisan::output());
    }

    public function test_backfill_command_dry_run_does_not_write_to_db(): void
    {
        $code = Artisan::call('marketplace:listings:backfill', [
            'store_id' => $this->store->id,
            '--dry-run' => true,
        ]);

        $this->assertEquals(0, $code);
        
        // Assert no cloned products or listings were created
        $this->assertEquals(0, MpProduct::where('user_id', $this->store->user_id)->count());
        $this->assertEquals(0, ChannelListing::where('store_id', $this->store->id)->count());
    }

    public function test_backfill_command_confirm_clones_catalog_and_syncs_listings(): void
    {
        $code = Artisan::call('marketplace:listings:backfill', [
            'store_id' => $this->store->id,
            '--confirm' => true,
        ]);

        $this->assertEquals(0, $code);

        // Assert products were cloned under tenant user_id
        $tenantProductCount = MpProduct::where('user_id', $this->store->user_id)->count();
        $this->assertGreaterThan(0, $tenantProductCount);

        // Verify test target barcodes also exist
        $this->assertTrue(MpProduct::where('user_id', $this->store->user_id)->where('barcode', 'ZEM-DRYRUN-CERT-001')->exists());

        // Assert channel listings were created and correctly mapped to MpProducts
        $listings = ChannelListing::where('store_id', $this->store->id)->get();
        $this->assertGreaterThan(0, $listings->count());

        foreach ($listings as $listing) {
            $this->assertNotNull($listing->mp_product_id);
            $mpProduct = MpProduct::find($listing->mp_product_id);
            $this->assertEquals($listing->channelProduct->barcode, $mpProduct->barcode);
        }
    }
}
