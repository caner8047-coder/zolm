<?php

namespace Tests\Feature;

use App\Models\MpProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductsTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_is_blocked_when_accounting_enabled_is_false(): void
    {
        config()->set('marketplace.features.accounting_enabled', false);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $this->actingAs($user)
            ->get(route('accounting.products'))
            ->assertStatus(404);
    }

    public function test_page_renders_when_accounting_enabled_is_true(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $this->actingAs($user)
            ->get(route('accounting.products'))
            ->assertStatus(200)
            ->assertSeeLivewire('accounting.products')
            ->assertSee('Ürün Kartları');
    }

    public function test_creating_product_card(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        Livewire::actingAs($user)
            ->test('accounting.products')
            ->set('barcode', '8690000000001')
            ->set('stockCode', 'SKU-001')
            ->set('productName', 'Pamuklu Tişört')
            ->set('brand', 'ZOLM')
            ->set('categoryName', 'Tekstil')
            ->set('unitName', 'adet')
            ->set('vatRate', 20)
            ->set('cogs', 80)
            ->set('salePrice', 150)
            ->set('stockQuantity', 12)
            ->set('criticalStockThreshold', 5)
            ->call('saveProduct')
            ->assertSet('messageType', 'success')
            ->assertSet('showForm', false);

        $this->assertDatabaseHas('mp_products', [
            'user_id' => $user->id,
            'barcode' => '8690000000001',
            'stock_code' => 'SKU-001',
            'product_name' => 'Pamuklu Tişört',
            'unit_name' => 'adet',
            'sale_price' => 150,
            'stock_quantity' => 12,
        ]);
    }

    public function test_product_card_can_be_updated_and_passivated(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $product = MpProduct::create([
            'user_id' => $user->id,
            'barcode' => 'BAR-OLD',
            'stock_code' => 'SKU-OLD',
            'product_name' => 'Eski Ürün',
            'unit_name' => 'adet',
            'sale_price' => 100,
            'stock_quantity' => 3,
            'status' => 'active',
        ]);

        Livewire::actingAs($user)
            ->test('accounting.products')
            ->call('editProduct', $product->id)
            ->set('productName', 'Yeni Ürün')
            ->set('salePrice', 125)
            ->set('stockQuantity', 9)
            ->call('saveProduct')
            ->assertSet('messageType', 'success');

        $this->assertDatabaseHas('mp_products', [
            'id' => $product->id,
            'product_name' => 'Yeni Ürün',
            'sale_price' => 125,
            'stock_quantity' => 9,
        ]);

        Livewire::actingAs($user)
            ->test('accounting.products')
            ->call('markPassive', $product->id)
            ->assertSet('messageType', 'success');

        $this->assertSame('suspended', $product->fresh()->status);
    }

    public function test_duplicate_barcode_is_rejected_per_user(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        MpProduct::create([
            'user_id' => $user->id,
            'barcode' => 'DUP-1',
            'stock_code' => 'SKU-A',
            'product_name' => 'Ürün A',
        ]);

        Livewire::actingAs($user)
            ->test('accounting.products')
            ->set('barcode', 'DUP-1')
            ->set('stockCode', 'SKU-B')
            ->set('productName', 'Ürün B')
            ->call('saveProduct')
            ->assertHasErrors('barcode');
    }

    public function test_critical_filter_and_tenant_isolation(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user1 = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $user2 = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        MpProduct::create([
            'user_id' => $user1->id,
            'barcode' => 'BAR-CRIT',
            'stock_code' => 'SKU-CRIT',
            'product_name' => 'Kritik Ürün',
            'stock_quantity' => 2,
            'critical_stock_threshold' => 5,
        ]);

        MpProduct::create([
            'user_id' => $user1->id,
            'barcode' => 'BAR-OK',
            'stock_code' => 'SKU-OK',
            'product_name' => 'Rahat Ürün',
            'stock_quantity' => 20,
            'critical_stock_threshold' => 5,
        ]);

        MpProduct::create([
            'user_id' => $user2->id,
            'barcode' => 'BAR-OTHER',
            'stock_code' => 'SKU-OTHER',
            'product_name' => 'Başka Kullanıcı Ürünü',
            'stock_quantity' => 1,
            'critical_stock_threshold' => 5,
        ]);

        Livewire::actingAs($user1)
            ->test('accounting.products')
            ->set('filterCritical', 'critical')
            ->assertSee('Kritik Ürün')
            ->assertDontSee('Rahat Ürün')
            ->assertDontSee('Başka Kullanıcı Ürünü');
    }
}
