<?php

namespace Tests\Feature\Hepsiburada;

use App\Models\MpCategory;
use App\Models\MpCategoryAttribute;
use App\Models\MpCategoryAttributeValue;
use App\Models\MarketplaceStore;
use App\Models\IntegrationConnection;
use App\Services\Marketplace\MarketplaceReferenceSyncService;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HepsiburadaReferenceSyncTest extends TestCase
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

        $store = \App\Models\MarketplaceStore::create([
            'user_id'         => $user->id,
            'legal_entity_id' => $le->id,
            'marketplace'     => 'hepsiburada',
            'store_name'      => 'HB Test',
            'seller_id'       => '123456',
            'timezone'        => 'Europe/Istanbul',
            'currency'        => 'TRY',
            'is_active'       => true,
        ]);

        $connection = \App\Models\IntegrationConnection::create([
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

    public function test_it_syncs_categories(): void
    {
        Http::fake([
            'https://mpop.hepsiburada.com/product/api/categories/get-all-categories' => Http::response([
                [
                    'id' => '10001',
                    'name' => 'Mutfak',
                    'subCategories' => [
                        [
                            'id' => '10002',
                            'name' => 'Tencere Setleri',
                            'subCategories' => [],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $store = $this->makeStore();
        $syncService = app(MarketplaceReferenceSyncService::class);

        // Run sync
        $result = $syncService->syncCategories($store);

        $this->assertSame('completed', $result['status']);
        $this->assertSame(2, $result['items_processed']);

        $this->assertDatabaseHas('mp_categories', [
            'marketplace' => 'hepsiburada',
            'platform_category_id' => '10001',
            'name' => 'Mutfak',
            'is_leaf' => false,
        ]);

        $this->assertDatabaseHas('mp_categories', [
            'marketplace' => 'hepsiburada',
            'platform_category_id' => '10002',
            'name' => 'Tencere Setleri',
            'is_leaf' => true,
        ]);

        // Verify request contract (URL, method, basic auth and user-agent presence)
        Http::assertSent(function ($request) {
            return $request->method() === 'GET'
                && str_contains($request->url(), 'product/api/categories/get-all-categories')
                && $request->hasHeader('Authorization')
                && $request->hasHeader('User-Agent');
        });
    }

    public function test_it_syncs_category_attributes(): void
    {
        // Setup leaf category in DB
        $category = MpCategory::create([
            'marketplace' => 'hepsiburada',
            'platform_category_id' => '10002',
            'name' => 'Tencere Setleri',
            'level' => 1,
            'is_leaf' => true,
            'is_active' => true,
        ]);

        Http::fake([
            'https://mpop.hepsiburada.com/product/api/categories/10002/attributes' => Http::response([
                [
                    'id' => 'attr-500',
                    'name' => 'Renk',
                    'mandatory' => true,
                    'varianter' => true,
                    'multipleSelect' => false,
                    'allowedDataType' => 'string',
                    'attributeValues' => [
                        ['id' => 'val-red', 'name' => 'Kırmızı'],
                        ['id' => 'val-blue', 'name' => 'Mavi'],
                    ],
                ],
            ], 200),
        ]);

        $store = $this->makeStore();
        $syncService = app(MarketplaceReferenceSyncService::class);

        // Run sync
        $result = $syncService->syncCategoryAttributes($store);

        $this->assertSame('completed', $result['status']);
        $this->assertSame(1, $result['items_processed']);

        $this->assertDatabaseHas('mp_category_attributes', [
            'marketplace' => 'hepsiburada',
            'platform_category_id' => '10002',
            'platform_attribute_id' => 'attr-500',
            'name' => 'Renk',
            'is_required' => true,
            'is_variant' => true,
        ]);

        $attribute = MpCategoryAttribute::where('platform_attribute_id', 'attr-500')->first();
        $this->assertNotNull($attribute);

        $this->assertDatabaseHas('mp_category_attribute_values', [
            'mp_category_attribute_id' => $attribute->id,
            'platform_value_id' => 'val-red',
            'name' => 'Kırmızı',
        ]);

        $this->assertDatabaseHas('mp_category_attribute_values', [
            'mp_category_attribute_id' => $attribute->id,
            'platform_value_id' => 'val-blue',
            'name' => 'Mavi',
        ]);

        // Verify request contract (URL, method, basic auth and user-agent presence)
        Http::assertSent(function ($request) {
            return $request->method() === 'GET'
                && str_contains($request->url(), 'product/api/categories/10002/attributes')
                && $request->hasHeader('Authorization')
                && $request->hasHeader('User-Agent');
        });
    }
}
