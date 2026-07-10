<?php

namespace Tests\Feature;

use App\Models\MpProduct;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StockTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_is_blocked_when_accounting_enabled_is_false(): void
    {
        config()->set('marketplace.features.accounting_enabled', false);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $this->actingAs($user)
            ->get(route('accounting.stock'))
            ->assertStatus(404);
    }

    public function test_page_renders_when_accounting_enabled_is_true(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $this->actingAs($user)
            ->get(route('accounting.stock'))
            ->assertStatus(200)
            ->assertSeeLivewire('accounting.stock');
    }

    public function test_creating_warehouse(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        Livewire::actingAs($user)
            ->test('accounting.stock')
            ->set('warehouseName', 'Anadolu Yakası Depo')
            ->set('warehouseCode', 'depo-anadolu')
            ->set('warehouseIsDefault', true)
            ->call('createWarehouse')
            ->assertSet('messageType', 'success');

        $this->assertDatabaseHas('warehouses', [
            'user_id' => $user->id,
            'name' => 'Anadolu Yakası Depo',
            'code' => 'depo-anadolu',
            'is_default' => true,
        ]);
    }

    public function test_recording_stock_movement_inflow(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $warehouse = Warehouse::create([
            'user_id' => $user->id,
            'name' => 'Depo',
            'code' => 'depo-main',
            'is_active' => true,
        ]);

        $product = MpProduct::create([
            'user_id' => $user->id,
            'stock_code' => 'PRD-100',
            'product_name' => 'T-Shirt',
            'barcode' => 'BAR-100',
        ]);

        Livewire::actingAs($user)
            ->test('accounting.stock')
            ->set('movDirection', 'in')
            ->set('movType', 'in_purchase')
            ->set('movWarehouseId', $warehouse->id)
            ->set('movStockCode', 'PRD-100')
            ->set('movQuantity', 50)
            ->set('movUnitCost', 12.50)
            ->call('recordStockMovement')
            ->assertSet('messageType', 'success');

        $this->assertDatabaseHas('stock_movements', [
            'user_id' => $user->id,
            'warehouse_id' => $warehouse->id,
            'stock_code' => 'PRD-100',
            'direction' => 'in',
            'quantity' => 50,
            'unit_cost' => 12.50,
        ]);

        $this->assertDatabaseHas('stock_balances', [
            'user_id' => $user->id,
            'warehouse_id' => $warehouse->id,
            'stock_code' => 'PRD-100',
            'quantity' => 50,
        ]);
    }

    public function test_recording_stock_movement_outflow_exceeding_fails(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $warehouse = Warehouse::create([
            'user_id' => $user->id,
            'name' => 'Depo',
            'code' => 'depo-main',
            'is_active' => true,
        ]);

        $product = MpProduct::create([
            'user_id' => $user->id,
            'stock_code' => 'PRD-100',
            'product_name' => 'T-Shirt',
            'barcode' => 'BAR-100',
        ]);

        // Put 10 in stock
        StockBalance::create([
            'user_id' => $user->id,
            'warehouse_id' => $warehouse->id,
            'stock_code' => 'PRD-100',
            'quantity' => 10,
        ]);

        // Try to draw 15 out
        Livewire::actingAs($user)
            ->test('accounting.stock')
            ->set('movDirection', 'out')
            ->set('movType', 'out_sale')
            ->set('movWarehouseId', $warehouse->id)
            ->set('movStockCode', 'PRD-100')
            ->set('movQuantity', 15)
            ->call('recordStockMovement')
            ->assertSet('messageType', 'error');

        $this->assertEquals(10, StockBalance::where('user_id', $user->id)->first()->quantity);
    }

    public function test_tenant_isolation_on_stock(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user1 = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $user2 = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $warehouse2 = Warehouse::create([
            'user_id' => $user2->id,
            'name' => 'User2 Depo',
            'code' => 'depo-u2',
            'is_active' => true,
        ]);

        $product1 = MpProduct::create([
            'user_id' => $user1->id,
            'stock_code' => 'PRD-100',
            'product_name' => 'User1 T-Shirt',
            'barcode' => 'BAR-100',
        ]);

        // Attempting to record movement using User 2's warehouse while logged in as User 1 should be blocked
        Livewire::actingAs($user1)
            ->test('accounting.stock')
            ->set('movDirection', 'in')
            ->set('movWarehouseId', $warehouse2->id)
            ->set('movStockCode', 'PRD-100')
            ->set('movQuantity', 10)
            ->call('recordStockMovement')
            ->assertSet('messageType', 'error');
    }
}
