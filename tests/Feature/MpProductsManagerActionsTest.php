<?php

namespace Tests\Feature;

use App\Livewire\MpProductsManager;
use App\Models\MpProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
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
        config()->set('database.connections.mysql.database', 'zolm');
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
        $this->assertSame($product->product_name . ' (Kopya)', $duplicate->product_name);
        $this->assertStringStartsWith($product->barcode . '-KOPYA', (string) $duplicate->barcode);
        $this->assertStringStartsWith($product->stock_code . '-KOPYA', (string) $duplicate->stock_code);
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
            ->assertSee('Beklet');
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
}
