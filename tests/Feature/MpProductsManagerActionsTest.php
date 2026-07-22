<?php

namespace Tests\Feature;

use App\Jobs\SyncMarketplaceDataJob;
use App\Livewire\MpProductsManager;
use App\Models\ChannelListing;
use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
use App\Models\ChannelProduct;
use App\Models\IntegrationConnection;
use App\Models\IntegrationSyncRun;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\MpProduct;
use App\Models\ProductMatchIssue;
use App\Models\Recipe;
use App\Models\User;
use App\Services\MpProductImportService;
use App\Services\MpSettingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class MpProductsManagerActionsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.host', 'mysql');
        config()->set('database.connections.mysql.port', '3306');
        config()->set('database.connections.mysql.database', $this->mysqlTestDatabaseName());
        config()->set('database.connections.mysql.username', 'sail');
        config()->set('database.connections.mysql.password', 'password');
        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::setDefaultConnection('mysql');
    }

    public function test_edit_product_opens_modal_with_product_data(): void
    {
        $user = User::factory()->create();
        $product = MpProduct::query()->create($this->productPayload($user->id));

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->call('editProduct', $product->id)
            ->assertSet('showEditModal', true)
            ->assertSet('editingId', $product->id)
            ->assertSet('f_barcode', $product->barcode)
            ->assertSet('f_stock_code', $product->stock_code);
    }

    public function test_duplicate_product_creates_copy(): void
    {
        $user = User::factory()->create();
        $product = MpProduct::query()->create($this->productPayload($user->id));
        $initialCount = MpProduct::query()->where('user_id', $user->id)->count();

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->call('duplicateProduct', $product->id);

        $this->assertSame($initialCount + 1, MpProduct::query()->where('user_id', $user->id)->count());
        $duplicate = MpProduct::query()
            ->where('user_id', $user->id)
            ->where('id', '!=', $product->id)
            ->first();

        $this->assertNotNull($duplicate);
        $this->assertSame($product->product_name.' (Kopya)', $duplicate->product_name);
        $this->assertStringStartsWith($product->barcode.'-KOPYA', (string) $duplicate->barcode);
        $this->assertStringStartsWith($product->stock_code.'-KOPYA', (string) $duplicate->stock_code);
    }

    public function test_delete_product_removes_record(): void
    {
        $user = User::factory()->create();
        $product = MpProduct::query()->create($this->productPayload($user->id));

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->call('deleteProduct', $product->id);

        $this->assertDatabaseMissing('mp_products', [
            'id' => $product->id,
        ]);
    }

    public function test_query_tab_param_opens_edit_modal_on_requested_tab(): void
    {
        $user = User::factory()->create();
        $product = MpProduct::query()->create($this->productPayload($user->id));

        $this->actingAs($user);

        Livewire::withQueryParams([
            'edit' => $product->id,
            'tab' => 'pricing',
        ])->test(MpProductsManager::class)
            ->assertSet('showEditModal', true)
            ->assertSet('editingId', $product->id)
            ->assertSet('tab', 'pricing')
            ->assertSet('editTab', 'pricing');
    }

    public function test_setting_tab_property_updates_modal_tab(): void
    {
        $user = User::factory()->create();
        $product = MpProduct::query()->create($this->productPayload($user->id));

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->call('editProduct', $product->id)
            ->set('tab', 'logistics')
            ->assertSet('tab', 'logistics')
            ->assertSet('editTab', 'logistics');
    }

    public function test_open_edit_product_tab_opens_requested_section(): void
    {
        $user = User::factory()->create();
        $product = MpProduct::query()->create($this->productPayload($user->id));

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->call('openEditProductTab', $product->id, 'images')
            ->assertSet('showEditModal', true)
            ->assertSet('editingId', $product->id)
            ->assertSet('tab', 'images')
            ->assertSet('editTab', 'images');
    }

    public function test_create_modal_tab_buttons_use_livewire_and_switch_tabs(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->call('openCreateModal')
            ->assertSet('showEditModal', true)
            ->assertSeeHtml('wire:click="setEditTab(\'pricing\')"')
            ->assertSeeHtml('wire:click="setEditTab(\'logistics\')"')
            ->assertSeeHtml('wire:click="setEditTab(\'images\')"')
            ->call('setEditTab', 'pricing')
            ->assertSet('tab', 'pricing')
            ->assertSet('editTab', 'pricing');
    }

    public function test_edit_product_loads_existing_image_data_into_modal(): void
    {
        $user = User::factory()->create();
        $product = MpProduct::query()->create(array_merge($this->productPayload($user->id), [
            'image_url' => 'https://example.com/cover.jpg',
            'image_urls' => [
                'https://example.com/cover.jpg',
                'https://example.com/detail-1.jpg',
            ],
        ]));

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->call('editProduct', $product->id)
            ->assertSee('Görseller')
            ->assertSet('f_image_url', 'https://example.com/cover.jpg')
            ->assertSet('f_image_urls.0', 'https://example.com/cover.jpg')
            ->assertSet('f_image_urls.1', 'https://example.com/detail-1.jpg');
    }

    public function test_save_product_updates_image_fields(): void
    {
        $user = User::factory()->create();
        $product = MpProduct::query()->create($this->productPayload($user->id));

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->call('editProduct', $product->id)
            ->set('f_image_url', 'https://example.com/cover.jpg')
            ->set('f_image_urls', [
                'https://example.com/cover.jpg',
                'https://example.com/detail-1.jpg',
                'https://example.com/detail-2.jpg',
            ])
            ->call('saveProduct');

        $product->refresh();

        $this->assertSame('https://example.com/cover.jpg', $product->image_url);
        $this->assertSame([
            'https://example.com/cover.jpg',
            'https://example.com/detail-1.jpg',
            'https://example.com/detail-2.jpg',
        ], $product->image_urls);
    }

    public function test_select_all_state_tracks_selected_products_on_current_page(): void
    {
        $user = User::factory()->create();
        $firstProduct = MpProduct::query()->create(array_merge($this->productPayload($user->id), [
            'barcode' => '8691234567891',
            'stock_code' => 'TEST-STK-002',
            'product_name' => 'Alpha Ürün',
        ]));
        $secondProduct = MpProduct::query()->create(array_merge($this->productPayload($user->id), [
            'barcode' => '8691234567892',
            'stock_code' => 'TEST-STK-003',
            'product_name' => 'Beta Ürün',
        ]));

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->set('selectedProducts', [(string) $firstProduct->id])
            ->assertSet('selectAll', false)
            ->set('selectedProducts', [(string) $firstProduct->id, (string) $secondProduct->id])
            ->assertSet('selectAll', true)
            ->set('selectedProducts', [(string) $firstProduct->id])
            ->assertSet('selectAll', false);
    }

    public function test_status_filter_is_reflected_in_active_filter_summary_and_reset_action(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::withQueryParams([
            'filterStatus' => 'active',
        ])->test(MpProductsManager::class)
            ->assertSee('Durum: Satışta')
            ->assertSeeHtml('wire:click="resetFilters"')
            ->assertSee('Sıfırla');
    }

    public function test_empty_state_offers_filter_reset_when_no_results_match(): void
    {
        $user = User::factory()->create();
        MpProduct::query()->create($this->productPayload($user->id));

        $this->actingAs($user);

        Livewire::withQueryParams([
            'search' => 'bulunamayan-urun',
        ])->test(MpProductsManager::class)
            ->assertSee('Bu filtrelerle eşleşen ürün bulunamadı.')
            ->assertSee('Filtreleri temizle');
    }

    public function test_cost_update_modal_is_available_from_products_workspace(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->assertSee('Maliyet Güncelle')
            ->call('openCostUpdateModal')
            ->assertSet('showCostUpdateModal', true)
            ->assertSee('Excel ile Maliyet Güncelle');
    }

    public function test_cost_update_import_updates_cogs_and_packaging_by_stock_code(): void
    {
        $user = User::factory()->create();
        $product = MpProduct::query()->create($this->productPayload($user->id));
        $packagingOnlyProduct = MpProduct::query()->create(array_merge($this->productPayload($user->id), [
            'barcode' => '8691234567802',
            'stock_code' => 'PKG-STK',
            'product_name' => 'Ambalaj Ürün',
            'cogs' => 75,
            'packaging_cost' => 0,
            'cargo_cost' => 9,
        ]));
        $englishFormatProduct = MpProduct::query()->create(array_merge($this->productPayload($user->id), [
            'barcode' => '8691234567803',
            'stock_code' => 'EN-STK',
            'product_name' => 'İngilizce Format Ürün',
            'cogs' => 80,
            'packaging_cost' => 0,
            'cargo_cost' => 11,
        ]));
        $zeroProduct = MpProduct::query()->create(array_merge($this->productPayload($user->id), [
            'barcode' => '8691234567801',
            'stock_code' => 'ZERO-STK',
            'product_name' => 'Zero Ürün',
            'cogs' => 55,
            'packaging_cost' => 12,
            'cargo_cost' => 18,
        ]));

        $this->actingAs($user);

        $file = $this->makeExcelUpload([
            [null, null, null, null],
            ['Kartlardan Dökümler', null, null, null],
            ['Stok Kodu', 'Açıklama', 'MF Fiyatı', 'Ambalaj Gideri'],
            ['TEST-STK-001', 'Test Ürün', '345,67', '22,50'],
            ['PKG-STK', 'Ambalaj Ürün', null, '13,25'],
            ['EN-STK', 'İngilizce Format Ürün', '2,580.4500', '1,234.5600'],
            ['ZERO-STK', 'Zero Ürün', '0', null],
            ['MISSING-STK', 'Eksik Ürün', '111,11', '5'],
        ], 'maliyet-guncelleme.xlsx');

        $result = app(MpProductImportService::class)->importCostUpdates($file);

        $product->refresh();
        $packagingOnlyProduct->refresh();
        $englishFormatProduct->refresh();
        $zeroProduct->refresh();

        $this->assertTrue($result['success']);
        $this->assertSame(3, $result['updated']);
        $this->assertSame(1, $result['not_found']);
        $this->assertSame(1, $result['zero_cost']);
        $this->assertEqualsWithDelta(345.67, (float) $product->cogs, 0.001);
        $this->assertEqualsWithDelta(22.50, (float) $product->packaging_cost, 0.001);
        $this->assertEqualsWithDelta(15.0, (float) $product->cargo_cost, 0.001);
        $this->assertEqualsWithDelta(75.0, (float) $packagingOnlyProduct->cogs, 0.001);
        $this->assertEqualsWithDelta(13.25, (float) $packagingOnlyProduct->packaging_cost, 0.001);
        $this->assertEqualsWithDelta(2580.45, (float) $englishFormatProduct->cogs, 0.001);
        $this->assertEqualsWithDelta(1234.56, (float) $englishFormatProduct->packaging_cost, 0.001);
        $this->assertEqualsWithDelta(11.0, (float) $englishFormatProduct->cargo_cost, 0.001);
        $this->assertEqualsWithDelta(55.0, (float) $zeroProduct->cogs, 0.001);
        $this->assertEqualsWithDelta(12.0, (float) $zeroProduct->packaging_cost, 0.001);
    }

    public function test_product_list_marks_stock_code_matches_with_recipe_verified_badge(): void
    {
        $user = User::factory()->create();
        $product = MpProduct::query()->create($this->productPayload($user->id));

        Recipe::query()->create([
            'user_id' => $user->id,
            'mp_product_id' => null,
            'stock_code' => $product->stock_code,
            'name' => 'Test Ürün Reçetesi',
            'version' => 'v1',
            'status' => 'active',
        ]);

        $this->actingAs($user);

        $component = Livewire::test(MpProductsManager::class)
            ->assertSee('Reçeteye bağlı ürün')
            ->assertSee($product->stock_code.' stok kodu aktif reçeteye bağlı');

        $queryMethod = new \ReflectionMethod(MpProductsManager::class, 'buildProductsQuery');
        $queryMethod->setAccessible(true);

        $matchedProduct = $queryMethod->invoke($component->instance())
            ->whereKey($product->id)
            ->first();

        $this->assertSame(1, (int) $matchedProduct->active_recipe_count_metric);
        $this->assertNotEmpty($matchedProduct->active_recipe_id_metric);
    }

    public function test_recipe_link_filter_limits_products_by_active_recipe_match(): void
    {
        $user = User::factory()->create();
        $linkedProduct = MpProduct::query()->create($this->productPayload($user->id));
        $unlinkedProduct = MpProduct::query()->create(array_merge($this->productPayload($user->id), [
            'barcode' => '8691234567898',
            'stock_code' => 'NO-REC-STK',
            'product_name' => 'Reçetesiz Ürün',
        ]));

        Recipe::query()->create([
            'user_id' => $user->id,
            'stock_code' => $linkedProduct->stock_code,
            'name' => 'Aktif Reçete',
            'version' => 'v1',
            'status' => 'active',
        ]);

        $this->actingAs($user);

        $component = Livewire::test(MpProductsManager::class)
            ->set('recipeLinkFilter', 'linked')
            ->assertSee('Reçete: Bağlı');

        $queryMethod = new \ReflectionMethod(MpProductsManager::class, 'buildProductsQuery');
        $queryMethod->setAccessible(true);

        $linkedNames = $queryMethod->invoke($component->instance())->pluck('product_name');
        $this->assertTrue($linkedNames->contains($linkedProduct->product_name));
        $this->assertFalse($linkedNames->contains($unlinkedProduct->product_name));

        $component->set('recipeLinkFilter', 'unlinked');

        $unlinkedNames = $queryMethod->invoke($component->instance())->pluck('product_name');
        $this->assertFalse($unlinkedNames->contains($linkedProduct->product_name));
        $this->assertTrue($unlinkedNames->contains($unlinkedProduct->product_name));
    }

    public function test_product_detail_panel_keeps_master_actions_accessible(): void
    {
        $user = User::factory()->create();
        MpProduct::query()->create($this->productPayload($user->id));

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->assertSee('Ürünü düzenle')
            ->assertSee('Çoğalt')
            ->assertSee('Sil');
    }

    public function test_desktop_table_uses_compact_action_menu_toggle(): void
    {
        $user = User::factory()->create();
        MpProduct::query()->create($this->productPayload($user->id));

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->assertSee('İşlem menüsünü aç')
            ->assertSee('Genel düzenle')
            ->assertSee('COGS, fiyat ve komisyon')
            ->assertSee('Görseller')
            ->assertSee('Güncel durum al')
            ->assertSee('Hızlı eşleştir')
            ->assertSee('Beklet');
    }

    public function test_refresh_current_status_queues_product_sync_for_linked_store(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $legalEntity = $this->legalEntityFor($user, 'current-status-row');
        $product = MpProduct::query()->create($this->productPayload($user->id));
        $store = $this->connectedStoreFor($user, $legalEntity, 'trendyol', 'ROW');

        ChannelListing::query()->create([
            'store_id' => $store->id,
            'mp_product_id' => $product->id,
            'listing_id' => 'TY-CURRENT-1',
            'listing_status' => 'active',
            'sale_price' => 190,
            'currency' => 'TRY',
            'stock_quantity' => 25,
        ]);

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->call('refreshCurrentStatus', $product->id);

        $run = IntegrationSyncRun::query()
            ->where('store_id', $store->id)
            ->where('sync_type', 'products')
            ->latest('id')
            ->first();

        $this->assertNotNull($run);
        $this->assertSame('queued', $run->status);
        $this->assertSame('manual', $run->trigger_type);
        $this->assertSame('current_status_refresh', $run->notes_json['source']);
        $this->assertSame('products', $run->notes_json['origin_screen']);
        $this->assertSame([$product->id], $run->notes_json['mp_product_ids']);
        $this->assertSame('TEST-STK-001', $run->notes_json['options']['stock_code']);
        $this->assertSame('8691234567890', $run->notes_json['options']['barcode']);
        $this->assertTrue((bool) $run->notes_json['options']['current_status_refresh']);

        Queue::assertPushed(SyncMarketplaceDataJob::class, fn (SyncMarketplaceDataJob $job) => $job->syncRunId === $run->id);
    }

    public function test_bulk_refresh_current_status_groups_selected_products_by_store(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $legalEntity = $this->legalEntityFor($user, 'current-status-bulk');
        $firstProduct = MpProduct::query()->create($this->productPayload($user->id));
        $secondProduct = MpProduct::query()->create(array_merge($this->productPayload($user->id), [
            'barcode' => '8691234567891',
            'stock_code' => 'TEST-STK-002',
        ]));
        $trendyolStore = $this->connectedStoreFor($user, $legalEntity, 'trendyol', 'BULK-TY');
        $n11Store = $this->connectedStoreFor($user, $legalEntity, 'n11', 'BULK-N11');

        ChannelListing::query()->create([
            'store_id' => $trendyolStore->id,
            'mp_product_id' => $firstProduct->id,
            'listing_id' => 'TY-CURRENT-BULK-1',
            'listing_status' => 'active',
            'currency' => 'TRY',
            'stock_quantity' => 4,
        ]);
        ChannelListing::query()->create([
            'store_id' => $trendyolStore->id,
            'mp_product_id' => $secondProduct->id,
            'listing_id' => 'TY-CURRENT-BULK-2',
            'listing_status' => 'active',
            'currency' => 'TRY',
            'stock_quantity' => 5,
        ]);
        ChannelListing::query()->create([
            'store_id' => $n11Store->id,
            'mp_product_id' => $secondProduct->id,
            'listing_id' => 'N11-CURRENT-BULK-1',
            'listing_status' => 'active',
            'currency' => 'TRY',
            'stock_quantity' => 6,
        ]);

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->set('selectedProducts', [(string) $firstProduct->id, (string) $secondProduct->id])
            ->call('bulkRefreshCurrentStatus')
            ->assertSet('selectedProducts', [])
            ->assertSet('selectAll', false);

        $runs = IntegrationSyncRun::query()
            ->whereIn('store_id', [$trendyolStore->id, $n11Store->id])
            ->where('sync_type', 'products')
            ->get()
            ->keyBy('store_id');

        $this->assertCount(2, $runs);
        $this->assertEqualsCanonicalizing(
            [$firstProduct->id, $secondProduct->id],
            $runs[$trendyolStore->id]->notes_json['mp_product_ids']
        );
        $this->assertEqualsCanonicalizing(
            [$secondProduct->id],
            $runs[$n11Store->id]->notes_json['mp_product_ids']
        );
        $this->assertSame('current_status_refresh', $runs[$trendyolStore->id]->notes_json['source']);
        $this->assertTrue((bool) $runs[$n11Store->id]->notes_json['options']['current_status_refresh']);

        Queue::assertPushed(SyncMarketplaceDataJob::class, 2);
    }

    public function test_update_product_status_changes_single_product_state(): void
    {
        $user = User::factory()->create();
        $product = MpProduct::query()->create($this->productPayload($user->id));

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->call('updateProductStatus', $product->id, 'suspended');

        $product->refresh();

        $this->assertSame('suspended', $product->status);
    }

    public function test_new_products_table_keeps_legacy_row_data_visible(): void
    {
        $user = User::factory()->create();

        MpProduct::query()->create(array_merge($this->productPayload($user->id), [
            'barcode' => '1907311035',
            'stock_code' => '1BRJZEM00177',
            'product_name' => 'Alaves Hazeran Jüt Bohem İkili Berjer Ceviz Ahşap, Sütlü Kahve',
            'cogs' => 1440,
            'packaging_cost' => 0,
            'cargo_cost' => 888.15,
            'sale_price' => 8499.90,
            'market_price' => 8499.90,
            'commission_rate' => 21,
            'stock_quantity' => 100,
            'desi' => 75,
            'pieces' => 3,
        ]));

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->assertSee('1907311035')
            ->assertSee('1BRJZEM00177')
            ->assertSee('3P 75D')
            ->assertSee('%304,6')
            ->assertSee('+₺4.386,77');
    }

    public function test_woocommerce_profit_scenario_uses_manual_commission_setting(): void
    {
        $user = User::factory()->create();
        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Test Ltd',
            'tax_number' => '1234567890',
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
        $product = MpProduct::query()->create(array_merge($this->productPayload($user->id), [
            'cogs' => 100,
            'packaging_cost' => 10,
            'cargo_cost' => 15,
            'sale_price' => 200,
            'commission_rate' => 18.9,
        ]));
        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'woocommerce',
            'store_name' => 'Zem Home',
            'status' => 'active',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        ChannelListing::query()->create([
            'store_id' => $store->id,
            'mp_product_id' => $product->id,
            'listing_id' => 'WOO-1',
            'listing_status' => 'active',
            'sale_price' => 200,
            'commission_rate' => 0,
            'commission_source' => 'marketplace_default',
            'currency' => 'TRY',
            'stock_quantity' => 10,
        ]);

        (new MpSettingsService($user->id))->setMany([
            'marketplace_products.profit.default_marketplace' => 'woocommerce',
            'marketplace_products.profit.woocommerce_commission_rate' => 3,
        ]);

        $this->actingAs($user);

        $scenario = (new MpProductsManager)->selectedProductCommissionScenario(
            $product->fresh()->load('channelListings.store')
        );

        $this->assertSame(3.0, (float) $scenario['commission_rate']);
        $this->assertSame(6.0, (float) $scenario['commission_amount']);
        $this->assertSame(194.0, (float) $scenario['receivable']);
        $this->assertSame(69.0, (float) $scenario['profit']);
        $this->assertEqualsWithDelta(1.6273, (float) $scenario['profit_margin'], 0.0001);
    }

    public function test_default_marketplace_missing_uses_average_with_clear_label(): void
    {
        $user = User::factory()->create();
        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Test Ltd',
            'tax_number' => '1234567891',
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
        $product = MpProduct::query()->create(array_merge($this->productPayload($user->id), [
            'sale_price' => 500,
            'commission_rate' => 20,
        ]));
        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'pazarama',
            'store_name' => 'Zem Pazarama',
            'status' => 'active',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        ChannelListing::query()->create([
            'store_id' => $store->id,
            'mp_product_id' => $product->id,
            'listing_id' => 'PZ-1',
            'listing_status' => 'active',
            'sale_price' => 500,
            'commission_rate' => 10,
            'commission_source' => 'catalog',
            'currency' => 'TRY',
            'stock_quantity' => 10,
        ]);

        (new MpSettingsService($user->id))->set('marketplace_products.profit.default_marketplace', 'trendyol');

        $this->actingAs($user);

        $scenario = (new MpProductsManager)->selectedProductCommissionScenario(
            $product->fresh()->load('channelListings.store')
        );

        $this->assertSame('fallback:average', $scenario['key']);
        $this->assertSame('Trendyol kaydı yok · mağaza ortalaması', $scenario['selection_label']);
    }

    public function test_koctas_profit_scenario_uses_configured_agreed_commission_rate(): void
    {
        $user = User::factory()->create();
        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Test Ltd',
            'tax_number' => '1234567893',
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
        $product = MpProduct::query()->create(array_merge($this->productPayload($user->id), [
            'sale_price' => 1000,
            'commission_rate' => 20,
        ]));
        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'koctas',
            'store_name' => 'Zem Koçtaş',
            'status' => 'active',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        ChannelListing::query()->create([
            'store_id' => $store->id,
            'mp_product_id' => $product->id,
            'listing_id' => 'KCT-1',
            'listing_status' => 'active',
            'sale_price' => 1000,
            'commission_rate' => 21,
            'commission_source' => 'catalog',
            'currency' => 'TRY',
            'stock_quantity' => 10,
        ]);

        (new MpSettingsService($user->id))->set('marketplace_products.profit.default_marketplace', 'koctas');

        $this->actingAs($user);

        $scenario = (new MpProductsManager)->selectedProductCommissionScenario(
            $product->fresh()->load('channelListings.store')
        );

        $this->assertSame('provider:koctas', $scenario['selection_key']);
        $this->assertSame(15.0, (float) $scenario['commission_rate']);
        $this->assertSame('Koçtaş anlaşmalı oran', $scenario['commission_source']);
        $this->assertSame(150.0, (float) $scenario['commission_amount']);
    }

    public function test_manual_product_commission_override_drives_profit_scenario(): void
    {
        $user = User::factory()->create();
        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Test Ltd',
            'tax_number' => '1234567892',
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
        $product = MpProduct::query()->create(array_merge($this->productPayload($user->id), [
            'cogs' => 100,
            'packaging_cost' => 10,
            'cargo_cost' => 20,
            'sale_price' => 500,
            'commission_rate' => 25,
            'profit_commission_override_enabled' => true,
        ]));
        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'pazarama',
            'store_name' => 'Zem Pazarama',
            'status' => 'active',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        ChannelListing::query()->create([
            'store_id' => $store->id,
            'mp_product_id' => $product->id,
            'listing_id' => 'PZ-2',
            'listing_status' => 'active',
            'sale_price' => 500,
            'commission_rate' => 10,
            'commission_source' => 'catalog',
            'currency' => 'TRY',
            'stock_quantity' => 10,
        ]);

        (new MpSettingsService($user->id))->set('marketplace_products.profit.default_marketplace', 'pazarama');

        $this->actingAs($user);

        $scenario = (new MpProductsManager)->selectedProductCommissionScenario(
            $product->fresh()->load('channelListings.store')
        );

        $this->assertSame('manual:'.$product->id, $scenario['key']);
        $this->assertSame('Manuel ürün komisyonu', $scenario['selection_label']);
        $this->assertSame(25.0, (float) $scenario['commission_rate']);
        $this->assertSame(125.0, (float) $scenario['commission_amount']);
        $this->assertSame(245.0, (float) $scenario['profit']);
        $this->assertEqualsWithDelta(3.2273, (float) $scenario['profit_margin'], 0.0001);
    }

    public function test_product_form_persists_manual_commission_override_flag(): void
    {
        $user = User::factory()->create();
        $product = MpProduct::query()->create($this->productPayload($user->id));

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->call('editProduct', $product->id)
            ->assertSet('f_profit_commission_override_enabled', false)
            ->set('f_profit_commission_override_enabled', true)
            ->call('saveProduct');

        $this->assertTrue((bool) $product->fresh()->profit_commission_override_enabled);
    }

    public function test_product_form_persists_marketplace_control_fields(): void
    {
        $user = User::factory()->create();
        $product = MpProduct::query()->create($this->productPayload($user->id));

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->call('editProduct', $product->id)
            ->set('f_extra_cost_fixed', 18.75)
            ->set('f_extra_cost_percentage', 3.5)
            ->set('f_vat_rate', 10)
            ->set('f_cost_vat_rate', 20)
            ->set('f_return_rate', 12.25)
            ->set('f_fast_delivery_type', 'Hızlı teslimat')
            ->call('saveProduct');

        $product->refresh();

        $this->assertEqualsWithDelta(18.75, (float) $product->extra_cost_fixed, 0.001);
        $this->assertEqualsWithDelta(3.5, (float) $product->extra_cost_percentage, 0.001);
        $this->assertEqualsWithDelta(20.0, (float) $product->cost_vat_rate, 0.001);
        $this->assertEqualsWithDelta(12.25, (float) $product->return_rate, 0.001);
        $this->assertSame('manual_form', $product->return_rate_source);
        $this->assertSame('Hızlı teslimat', $product->fast_delivery_type);
        $this->assertNotNull($product->return_rate_calculated_at);
    }

    public function test_inline_update_changes_control_fields_and_return_source(): void
    {
        $user = User::factory()->create();
        $product = MpProduct::query()->create($this->productPayload($user->id));

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->call('updateInlineField', $product->id, 'extra_cost_fixed', '22.40')
            ->call('updateInlineField', $product->id, 'extra_cost_percentage', '4.5')
            ->call('updateInlineField', $product->id, 'pieces', '4')
            ->call('updateInlineField', $product->id, 'return_rate', '16.2')
            ->call('updateInlineField', $product->id, 'fast_delivery_type', 'Aynı gün');

        $product->refresh();

        $this->assertEqualsWithDelta(22.4, (float) $product->extra_cost_fixed, 0.001);
        $this->assertEqualsWithDelta(4.5, (float) $product->extra_cost_percentage, 0.001);
        $this->assertSame(4, (int) $product->pieces);
        $this->assertEqualsWithDelta(16.2, (float) $product->return_rate, 0.001);
        $this->assertSame('manual_inline', $product->return_rate_source);
        $this->assertSame('Aynı gün', $product->fast_delivery_type);
    }

    public function test_bulk_action_toggles_manual_commission_override(): void
    {
        $user = User::factory()->create();
        $firstProduct = MpProduct::query()->create($this->productPayload($user->id));
        $secondProduct = MpProduct::query()->create(array_merge($this->productPayload($user->id), [
            'barcode' => '8691234567891',
            'stock_code' => 'TEST-STK-002',
        ]));

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->set('selectedProducts', [(string) $firstProduct->id, (string) $secondProduct->id])
            ->call('bulkSetProfitCommissionOverride', true)
            ->assertSet('selectedProducts', [])
            ->assertSet('selectAll', false);

        $this->assertTrue((bool) $firstProduct->fresh()->profit_commission_override_enabled);
        $this->assertTrue((bool) $secondProduct->fresh()->profit_commission_override_enabled);

        Livewire::test(MpProductsManager::class)
            ->set('selectedProducts', [(string) $firstProduct->id, (string) $secondProduct->id])
            ->call('bulkSetProfitCommissionOverride', false)
            ->assertSet('selectedProducts', [])
            ->assertSet('selectAll', false);

        $this->assertFalse((bool) $firstProduct->fresh()->profit_commission_override_enabled);
        $this->assertFalse((bool) $secondProduct->fresh()->profit_commission_override_enabled);
    }

    public function test_bulk_action_sets_packaging_cost_for_selected_products(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $firstProduct = MpProduct::query()->create($this->productPayload($user->id));
        $secondProduct = MpProduct::query()->create(array_merge($this->productPayload($user->id), [
            'barcode' => '8691234567891',
            'stock_code' => 'TEST-STK-002',
            'packaging_cost' => 4.25,
        ]));
        $otherProduct = MpProduct::query()->create(array_merge($this->productPayload($otherUser->id), [
            'barcode' => '8691234567892',
            'stock_code' => 'TEST-STK-003',
            'packaging_cost' => 3.50,
        ]));

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->set('selectedProducts', [(string) $firstProduct->id, (string) $secondProduct->id, (string) $otherProduct->id])
            ->set('bulkPackagingCost', 12.345)
            ->call('bulkSetPackagingCost')
            ->assertSet('bulkPackagingCost', null)
            ->assertSet('selectedProducts', [])
            ->assertSet('selectAll', false);

        $this->assertEqualsWithDelta(12.35, (float) $firstProduct->fresh()->packaging_cost, 0.001);
        $this->assertEqualsWithDelta(12.35, (float) $secondProduct->fresh()->packaging_cost, 0.001);
        $this->assertEqualsWithDelta(3.50, (float) $otherProduct->fresh()->packaging_cost, 0.001);
    }

    public function test_bulk_action_sets_logistics_info_for_selected_products(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $firstProduct = MpProduct::query()->create($this->productPayload($user->id));
        $secondProduct = MpProduct::query()->create(array_merge($this->productPayload($user->id), [
            'barcode' => '8691234567891',
            'stock_code' => 'TEST-STK-002',
            'cargo_cost' => 22.10,
            'desi' => 4.5,
            'pieces' => 2,
        ]));
        $otherProduct = MpProduct::query()->create(array_merge($this->productPayload($otherUser->id), [
            'barcode' => '8691234567892',
            'stock_code' => 'TEST-STK-003',
            'cargo_cost' => 31.75,
            'desi' => 6.25,
            'pieces' => 4,
        ]));

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->set('selectedProducts', [(string) $firstProduct->id, (string) $secondProduct->id, (string) $otherProduct->id])
            ->set('bulkCargoCost', 203.456)
            ->set('bulkDesi', 18.25)
            ->set('bulkPieces', 3)
            ->call('bulkSetLogisticsInfo')
            ->assertSet('bulkCargoCost', null)
            ->assertSet('bulkDesi', null)
            ->assertSet('bulkPieces', null)
            ->assertSet('selectedProducts', [])
            ->assertSet('selectAll', false);

        $this->assertEqualsWithDelta(203.46, (float) $firstProduct->fresh()->cargo_cost, 0.001);
        $this->assertEqualsWithDelta(18.25, (float) $firstProduct->fresh()->desi, 0.001);
        $this->assertSame(3, (int) $firstProduct->fresh()->pieces);
        $this->assertEqualsWithDelta(203.46, (float) $secondProduct->fresh()->cargo_cost, 0.001);
        $this->assertEqualsWithDelta(18.25, (float) $secondProduct->fresh()->desi, 0.001);
        $this->assertSame(3, (int) $secondProduct->fresh()->pieces);
        $this->assertEqualsWithDelta(31.75, (float) $otherProduct->fresh()->cargo_cost, 0.001);
        $this->assertEqualsWithDelta(6.25, (float) $otherProduct->fresh()->desi, 0.001);
        $this->assertSame(4, (int) $otherProduct->fresh()->pieces);
    }

    public function test_bulk_action_sets_and_clears_critical_stock_threshold(): void
    {
        $user = User::factory()->create();
        $firstProduct = MpProduct::query()->create($this->productPayload($user->id));
        $secondProduct = MpProduct::query()->create(array_merge($this->productPayload($user->id), [
            'barcode' => '8691234567891',
            'stock_code' => 'TEST-STK-002',
        ]));

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->set('selectedProducts', [(string) $firstProduct->id, (string) $secondProduct->id])
            ->set('bulkCriticalStockThreshold', 7)
            ->call('bulkSetCriticalStockThreshold')
            ->assertSet('bulkCriticalStockThreshold', null)
            ->assertSet('selectedProducts', [])
            ->assertSet('selectAll', false);

        $this->assertSame(7, (int) $firstProduct->fresh()->critical_stock_threshold);
        $this->assertSame(7, (int) $secondProduct->fresh()->critical_stock_threshold);

        Livewire::test(MpProductsManager::class)
            ->set('selectedProducts', [(string) $firstProduct->id, (string) $secondProduct->id])
            ->call('bulkClearCriticalStockThreshold')
            ->assertSet('bulkCriticalStockThreshold', null)
            ->assertSet('selectedProducts', [])
            ->assertSet('selectAll', false);

        $this->assertNull($firstProduct->fresh()->critical_stock_threshold);
        $this->assertNull($secondProduct->fresh()->critical_stock_threshold);
    }

    public function test_bulk_action_updates_stock_for_all_or_selected_marketplace(): void
    {
        $user = User::factory()->create();
        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Test Ltd',
            'tax_number' => '1234567898',
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
        $firstProduct = MpProduct::query()->create($this->productPayload($user->id));
        $secondProduct = MpProduct::query()->create(array_merge($this->productPayload($user->id), [
            'barcode' => '8691234567891',
            'stock_code' => 'TEST-STK-002',
            'stock_quantity' => 31,
        ]));
        $trendyolStore = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'Zem Trendyol',
            'seller_id' => 'TY-SELLER-'.$user->id,
            'status' => 'active',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
        $hepsiburadaStore = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'hepsiburada',
            'store_name' => 'Zem Hepsiburada',
            'seller_id' => 'HB-SELLER-'.$user->id,
            'status' => 'active',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $firstTrendyolListing = ChannelListing::query()->create([
            'store_id' => $trendyolStore->id,
            'mp_product_id' => $firstProduct->id,
            'listing_id' => 'TY-1',
            'listing_status' => 'active',
            'currency' => 'TRY',
            'stock_quantity' => 4,
        ]);
        $secondTrendyolListing = ChannelListing::query()->create([
            'store_id' => $trendyolStore->id,
            'mp_product_id' => $secondProduct->id,
            'listing_id' => 'TY-2',
            'listing_status' => 'active',
            'currency' => 'TRY',
            'stock_quantity' => 5,
        ]);
        $firstHepsiburadaListing = ChannelListing::query()->create([
            'store_id' => $hepsiburadaStore->id,
            'mp_product_id' => $firstProduct->id,
            'listing_id' => 'HB-1',
            'listing_status' => 'active',
            'currency' => 'TRY',
            'stock_quantity' => 8,
        ]);

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->set('selectedProducts', [(string) $firstProduct->id, (string) $secondProduct->id])
            ->set('bulkStockTarget', 'marketplace:trendyol')
            ->set('bulkStockQuantity', 9)
            ->call('bulkSetStockQuantity')
            ->assertSet('bulkStockQuantity', null)
            ->assertSet('bulkStockTarget', 'all')
            ->assertSet('selectedProducts', [])
            ->assertSet('selectAll', false);

        $this->assertSame(25, (int) $firstProduct->fresh()->stock_quantity);
        $this->assertSame(31, (int) $secondProduct->fresh()->stock_quantity);
        $this->assertSame(9, (int) $firstTrendyolListing->fresh()->stock_quantity);
        $this->assertSame(9, (int) $secondTrendyolListing->fresh()->stock_quantity);
        $this->assertSame(8, (int) $firstHepsiburadaListing->fresh()->stock_quantity);

        Livewire::test(MpProductsManager::class)
            ->set('selectedProducts', [(string) $firstProduct->id, (string) $secondProduct->id])
            ->set('bulkStockTarget', 'all')
            ->set('bulkStockQuantity', 12)
            ->call('bulkSetStockQuantity')
            ->assertSet('bulkStockQuantity', null)
            ->assertSet('bulkStockTarget', 'all')
            ->assertSet('selectedProducts', [])
            ->assertSet('selectAll', false);

        $this->assertSame(12, (int) $firstProduct->fresh()->stock_quantity);
        $this->assertSame(12, (int) $secondProduct->fresh()->stock_quantity);
        $this->assertSame(12, (int) $firstTrendyolListing->fresh()->stock_quantity);
        $this->assertSame(12, (int) $secondTrendyolListing->fresh()->stock_quantity);
        $this->assertSame(12, (int) $firstHepsiburadaListing->fresh()->stock_quantity);
    }

    public function test_bulk_action_adjusts_sale_prices_for_all_or_selected_marketplace(): void
    {
        $user = User::factory()->create();
        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Test Ltd',
            'tax_number' => '1234567897',
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
        $firstProduct = MpProduct::query()->create(array_merge($this->productPayload($user->id), [
            'sale_price' => 190,
        ]));
        $secondProduct = MpProduct::query()->create(array_merge($this->productPayload($user->id), [
            'barcode' => '8691234567891',
            'stock_code' => 'TEST-STK-002',
            'sale_price' => 200,
        ]));
        $trendyolStore = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'Zem Trendyol',
            'seller_id' => 'TY-SELLER-PRICE-'.$user->id,
            'status' => 'active',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
        $hepsiburadaStore = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'hepsiburada',
            'store_name' => 'Zem Hepsiburada',
            'seller_id' => 'HB-SELLER-PRICE-'.$user->id,
            'status' => 'active',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $firstTrendyolListing = ChannelListing::query()->create([
            'store_id' => $trendyolStore->id,
            'mp_product_id' => $firstProduct->id,
            'listing_id' => 'TY-PRICE-1',
            'listing_status' => 'active',
            'sale_price' => 100,
            'currency' => 'TRY',
            'stock_quantity' => 4,
        ]);
        $secondTrendyolListing = ChannelListing::query()->create([
            'store_id' => $trendyolStore->id,
            'mp_product_id' => $secondProduct->id,
            'listing_id' => 'TY-PRICE-2',
            'listing_status' => 'active',
            'sale_price' => 200,
            'currency' => 'TRY',
            'stock_quantity' => 5,
        ]);
        $firstHepsiburadaListing = ChannelListing::query()->create([
            'store_id' => $hepsiburadaStore->id,
            'mp_product_id' => $firstProduct->id,
            'listing_id' => 'HB-PRICE-1',
            'listing_status' => 'active',
            'sale_price' => 50,
            'currency' => 'TRY',
            'stock_quantity' => 8,
        ]);

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->set('selectedProducts', [(string) $firstProduct->id, (string) $secondProduct->id])
            ->set('bulkPriceTarget', 'marketplace:trendyol')
            ->set('bulkPriceDirection', 'decrease')
            ->set('bulkPricePercent', 10)
            ->call('bulkAdjustSalePrices')
            ->assertSet('bulkPricePercent', null)
            ->assertSet('bulkPriceDirection', 'increase')
            ->assertSet('bulkPriceTarget', 'all')
            ->assertSet('selectedProducts', [])
            ->assertSet('selectAll', false);

        $this->assertEqualsWithDelta(190.0, (float) $firstProduct->fresh()->sale_price, 0.001);
        $this->assertEqualsWithDelta(200.0, (float) $secondProduct->fresh()->sale_price, 0.001);
        $this->assertEqualsWithDelta(90.0, (float) $firstTrendyolListing->fresh()->sale_price, 0.001);
        $this->assertEqualsWithDelta(180.0, (float) $secondTrendyolListing->fresh()->sale_price, 0.001);
        $this->assertEqualsWithDelta(50.0, (float) $firstHepsiburadaListing->fresh()->sale_price, 0.001);

        Livewire::test(MpProductsManager::class)
            ->set('selectedProducts', [(string) $firstProduct->id, (string) $secondProduct->id])
            ->set('bulkPriceTarget', 'all')
            ->set('bulkPriceDirection', 'increase')
            ->set('bulkPricePercent', 20)
            ->call('bulkAdjustSalePrices')
            ->assertSet('bulkPricePercent', null)
            ->assertSet('bulkPriceDirection', 'increase')
            ->assertSet('bulkPriceTarget', 'all')
            ->assertSet('selectedProducts', [])
            ->assertSet('selectAll', false);

        $this->assertEqualsWithDelta(228.0, (float) $firstProduct->fresh()->sale_price, 0.001);
        $this->assertEqualsWithDelta(240.0, (float) $secondProduct->fresh()->sale_price, 0.001);
        $this->assertEqualsWithDelta(108.0, (float) $firstTrendyolListing->fresh()->sale_price, 0.001);
        $this->assertEqualsWithDelta(216.0, (float) $secondTrendyolListing->fresh()->sale_price, 0.001);
        $this->assertEqualsWithDelta(60.0, (float) $firstHepsiburadaListing->fresh()->sale_price, 0.001);
    }

    public function test_bulk_action_sets_target_profit_margin_for_all_or_selected_marketplace(): void
    {
        $user = User::factory()->create();
        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Test Ltd',
            'tax_number' => '1234567896',
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
        $firstProduct = MpProduct::query()->create(array_merge($this->productPayload($user->id), [
            'sale_price' => 190,
            'commission_rate' => 21,
        ]));
        $secondProduct = MpProduct::query()->create(array_merge($this->productPayload($user->id), [
            'barcode' => '8691234567891',
            'stock_code' => 'TEST-STK-002',
            'sale_price' => 200,
            'commission_rate' => 21,
        ]));
        $trendyolStore = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'Zem Trendyol',
            'seller_id' => 'TY-SELLER-PROFIT-'.$user->id,
            'status' => 'active',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
        $hepsiburadaStore = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'hepsiburada',
            'store_name' => 'Zem Hepsiburada',
            'seller_id' => 'HB-SELLER-PROFIT-'.$user->id,
            'status' => 'active',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $firstTrendyolListing = ChannelListing::query()->create([
            'store_id' => $trendyolStore->id,
            'mp_product_id' => $firstProduct->id,
            'listing_id' => 'TY-PROFIT-1',
            'listing_status' => 'active',
            'sale_price' => 100,
            'commission_rate' => 10,
            'currency' => 'TRY',
            'stock_quantity' => 4,
        ]);
        $secondTrendyolListing = ChannelListing::query()->create([
            'store_id' => $trendyolStore->id,
            'mp_product_id' => $secondProduct->id,
            'listing_id' => 'TY-PROFIT-2',
            'listing_status' => 'active',
            'sale_price' => 200,
            'commission_rate' => 20,
            'currency' => 'TRY',
            'stock_quantity' => 5,
        ]);
        $firstHepsiburadaListing = ChannelListing::query()->create([
            'store_id' => $hepsiburadaStore->id,
            'mp_product_id' => $firstProduct->id,
            'listing_id' => 'HB-PROFIT-1',
            'listing_status' => 'active',
            'sale_price' => 50,
            'commission_rate' => 15,
            'currency' => 'TRY',
            'stock_quantity' => 8,
        ]);

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->set('selectedProducts', [(string) $firstProduct->id, (string) $secondProduct->id])
            ->set('bulkProfitTarget', 'marketplace:trendyol')
            ->set('bulkProfitTargetMargin', 40)
            ->call('bulkSetTargetProfitMargin')
            ->assertSet('bulkProfitTargetMargin', null)
            ->assertSet('bulkProfitTarget', 'all')
            ->assertSet('selectedProducts', [])
            ->assertSet('selectAll', false);

        $this->assertEqualsWithDelta(190.0, (float) $firstProduct->fresh()->sale_price, 0.001);
        $this->assertEqualsWithDelta(200.0, (float) $secondProduct->fresh()->sale_price, 0.001);
        $this->assertEqualsWithDelta(187.78, (float) $firstTrendyolListing->fresh()->sale_price, 0.001);
        $this->assertEqualsWithDelta(211.25, (float) $secondTrendyolListing->fresh()->sale_price, 0.001);
        $this->assertEqualsWithDelta(50.0, (float) $firstHepsiburadaListing->fresh()->sale_price, 0.001);

        Livewire::test(MpProductsManager::class)
            ->set('selectedProducts', [(string) $firstProduct->id, (string) $secondProduct->id])
            ->set('bulkProfitTarget', 'all')
            ->set('bulkProfitTargetMargin', 30)
            ->call('bulkSetTargetProfitMargin')
            ->assertSet('bulkProfitTargetMargin', null)
            ->assertSet('bulkProfitTarget', 'all')
            ->assertSet('selectedProducts', [])
            ->assertSet('selectAll', false);

        $this->assertEqualsWithDelta(200.0, (float) $firstProduct->fresh()->sale_price, 0.001);
        $this->assertEqualsWithDelta(200.0, (float) $secondProduct->fresh()->sale_price, 0.001);
        $this->assertEqualsWithDelta(175.56, (float) $firstTrendyolListing->fresh()->sale_price, 0.001);
        $this->assertEqualsWithDelta(197.50, (float) $secondTrendyolListing->fresh()->sale_price, 0.001);
        $this->assertEqualsWithDelta(185.88, (float) $firstHepsiburadaListing->fresh()->sale_price, 0.001);
    }

    public function test_profit_margin_filter_shows_products_below_selected_threshold(): void
    {
        $user = User::factory()->create();

        MpProduct::query()->create(array_merge($this->productPayload($user->id), [
            'barcode' => '8691234567892',
            'stock_code' => 'LOW-PROFIT',
            'product_name' => 'Düşük Kâr Ürünü',
            'sale_price' => 200,
            'commission_rate' => 10,
        ]));
        MpProduct::query()->create(array_merge($this->productPayload($user->id), [
            'barcode' => '8691234567893',
            'stock_code' => 'HIGH-PROFIT',
            'product_name' => 'Yüksek Kâr Ürünü',
            'sale_price' => 500,
            'commission_rate' => 10,
        ]));

        $this->actingAs($user);

        $component = Livewire::test(MpProductsManager::class)
            ->set('filterProfitComparison', 'below')
            ->set('filterProfitMargin', 60);

        $queryMethod = new \ReflectionMethod(MpProductsManager::class, 'buildProductsQuery');
        $queryMethod->setAccessible(true);

        $belowNames = $queryMethod->invoke($component->instance())->pluck('product_name');
        $this->assertTrue($belowNames->contains('Düşük Kâr Ürünü'));
        $this->assertFalse($belowNames->contains('Yüksek Kâr Ürünü'));

        $component->set('filterProfitComparison', 'above');

        $aboveNames = $queryMethod->invoke($component->instance())->pluck('product_name');
        $this->assertFalse($aboveNames->contains('Düşük Kâr Ürünü'));
        $this->assertTrue($aboveNames->contains('Yüksek Kâr Ürünü'));
    }

    public function test_refresh_return_rates_calculates_from_selected_multi_marketplace_orders(): void
    {
        $user = User::factory()->create();
        $legalEntity = $this->legalEntityFor($user, 'returns');
        $product = MpProduct::query()->create($this->productPayload($user->id));
        $trendyolStore = $this->connectedStoreFor($user, $legalEntity, 'trendyol', 'RET-TY');
        $wooStore = $this->connectedStoreFor($user, $legalEntity, 'woocommerce', 'RET-WOO');

        $deliveredOrder = ChannelOrder::query()->create([
            'store_id' => $trendyolStore->id,
            'legal_entity_id' => $legalEntity->id,
            'external_order_id' => 'RET-TY-1',
            'order_number' => 'RET-TY-1',
            'order_status' => 'Delivered',
            'ordered_at' => now(),
        ]);
        $returnedOrder = ChannelOrder::query()->create([
            'store_id' => $wooStore->id,
            'legal_entity_id' => $legalEntity->id,
            'external_order_id' => 'RET-WOO-1',
            'order_number' => 'RET-WOO-1',
            'order_status' => 'Iade',
            'returned_at' => now(),
            'ordered_at' => now(),
        ]);

        ChannelOrderItem::query()->create([
            'store_id' => $trendyolStore->id,
            'channel_order_id' => $deliveredOrder->id,
            'mp_product_id' => $product->id,
            'external_line_id' => 'RET-TY-LINE-1',
            'stock_code' => $product->stock_code,
            'barcode' => $product->barcode,
            'product_name' => $product->product_name,
            'quantity' => 3,
            'line_status' => 'Delivered',
            'is_matched' => true,
        ]);
        ChannelOrderItem::query()->create([
            'store_id' => $wooStore->id,
            'channel_order_id' => $returnedOrder->id,
            'mp_product_id' => $product->id,
            'external_line_id' => 'RET-WOO-LINE-1',
            'stock_code' => $product->stock_code,
            'barcode' => $product->barcode,
            'product_name' => $product->product_name,
            'quantity' => 1,
            'line_status' => 'returned',
            'is_matched' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->set('selectedProducts', [(string) $product->id])
            ->call('refreshReturnRates')
            ->assertSet('selectedProducts', [])
            ->assertSet('selectAll', false);

        $product->refresh();

        $this->assertEqualsWithDelta(25.0, (float) $product->return_rate, 0.001);
        $this->assertSame('orders', $product->return_rate_source);
        $this->assertNotNull($product->return_rate_calculated_at);
    }

    public function test_quick_match_modal_resolves_pending_issue_for_selected_product(): void
    {
        $user = User::factory()->create();
        $legalEntity = $this->legalEntityFor($user, 'quick-match');
        $product = MpProduct::query()->create($this->productPayload($user->id));
        $store = $this->connectedStoreFor($user, $legalEntity, 'hepsiburada', 'QM-HB');
        $channelProduct = ChannelProduct::query()->create([
            'store_id' => $store->id,
            'external_product_id' => 'HB-QM-1',
            'stock_code' => $product->stock_code,
            'barcode' => $product->barcode,
            'title' => 'HB '.$product->product_name,
            'brand' => 'Zem',
            'category_name' => 'Mobilya',
            'last_synced_at' => now(),
        ]);
        $listing = ChannelListing::query()->create([
            'store_id' => $store->id,
            'channel_product_id' => $channelProduct->id,
            'listing_id' => 'HB-QM-LISTING-1',
            'listing_status' => 'active',
            'sale_price' => 190,
            'currency' => 'TRY',
            'stock_quantity' => 4,
        ]);
        $order = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $legalEntity->id,
            'external_order_id' => 'HB-QM-ORDER-1',
            'order_number' => 'HB-QM-ORDER-1',
            'order_status' => 'Created',
            'ordered_at' => now(),
        ]);
        $item = ChannelOrderItem::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'channel_listing_id' => $listing->id,
            'external_line_id' => 'HB-QM-LINE-1',
            'stock_code' => $product->stock_code,
            'barcode' => $product->barcode,
            'product_name' => $product->product_name,
            'quantity' => 1,
            'unit_price' => 190,
            'gross_amount' => 190,
            'is_matched' => false,
        ]);
        $issue = ProductMatchIssue::query()->create([
            'store_id' => $store->id,
            'channel_listing_id' => $listing->id,
            'match_status' => 'pending',
            'match_reason' => 'candidate_found',
            'candidate_ids_json' => [$product->id],
        ]);

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->call('openQuickMatchModal', $product->id)
            ->assertSet('showQuickMatchModal', true)
            ->assertSee('Hızlı Eşleştirme')
            ->assertSee('HB '.$product->product_name)
            ->call('quickMatchIssue', $issue->id)
            ->assertSet('showQuickMatchModal', false);

        $this->assertSame('resolved', $issue->fresh()->match_status);
        $this->assertSame($product->id, (int) $listing->fresh()->mp_product_id);
        $this->assertSame($product->id, (int) $item->fresh()->mp_product_id);
        $this->assertTrue((bool) $item->fresh()->is_matched);
    }

    protected function legalEntityFor(User $user, string $suffix): LegalEntity
    {
        return LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Test '.$suffix,
            'tax_number' => (string) (9000000000 + (int) $user->id),
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
    }

    protected function connectedStoreFor(User $user, LegalEntity $legalEntity, string $marketplace, string $suffix): MarketplaceStore
    {
        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => $marketplace,
            'store_name' => 'Zem '.strtoupper($marketplace),
            'seller_id' => $suffix.'-'.$user->id.'-'.substr(md5((string) microtime(true)), 0, 8),
            'status' => 'active',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        IntegrationConnection::query()->create([
            'store_id' => $store->id,
            'provider' => $marketplace,
            'auth_type' => 'api_key_secret',
            'credentials_encrypted' => ['api_key' => 'test', 'api_secret' => 'test'],
            'status' => 'configured',
        ]);

        return $store->fresh('connection');
    }

    protected function productPayload(int $userId): array
    {
        return [
            'user_id' => $userId,
            'barcode' => '8691234567890',
            'stock_code' => 'TEST-STK-001',
            'product_name' => 'Test Ürün',
            'cogs' => 100,
            'packaging_cost' => 10,
            'cargo_cost' => 15,
            'vat_rate' => 10,
            'market_price' => 200,
            'sale_price' => 190,
            'commission_rate' => 21,
            'stock_quantity' => 25,
            'desi' => 2.5,
            'pieces' => 1,
            'status' => 'active',
        ];
    }

    public function test_new_product_form_uses_settings_vat_rate_when_configured(): void
    {
        $user = User::factory()->create();

        (new MpSettingsService($user->id))->set('tax.default_product_vat_rate', 0.20);

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->call('openCreateModal')
            ->assertSet('f_vat_rate', 20)
            ->assertSet('showEditModal', true);
    }

    public function test_new_product_form_defaults_to_10_when_no_setting(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->call('openCreateModal')
            ->assertSet('f_vat_rate', 10);
    }

    public function test_edit_product_preserves_own_vat_rate(): void
    {
        $user = User::factory()->create();

        (new MpSettingsService($user->id))->set('tax.default_product_vat_rate', 0.20);

        $product = MpProduct::query()->create(
            array_merge($this->productPayload($user->id), ['vat_rate' => 1])
        );

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->call('editProduct', $product->id)
            ->assertSet('f_vat_rate', 1);
    }

    public function test_products_manager_loads_per_page_from_settings(): void
    {
        $user = User::factory()->create();

        (new MpSettingsService($user->id))->set('ui.products_per_page', 50);

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->assertSet('perPage', 50);
    }

    public function test_products_manager_defaults_to_25_when_no_setting(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->assertSet('perPage', 25);
    }

    public function test_products_manager_saves_per_page_on_change(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->set('perPage', 100);

        $settings = new MpSettingsService($user->id);
        $this->assertSame(100, $settings->getProductsPerPage());
    }

    public function test_products_manager_normalizes_invalid_per_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->set('perPage', 77)
            ->assertSet('perPage', 25);
    }

    /**
     * @param  array<int, array<int, scalar|null>>  $rows
     */
    protected function makeExcelUpload(array $rows, string $filename): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($rows, null, 'A1', true);

        $path = storage_path('framework/testing/'.uniqid('mp-products-', true).'-'.$filename);
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return UploadedFile::fake()
            ->createWithContent($filename, (string) file_get_contents($path))
            ->mimeType('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
