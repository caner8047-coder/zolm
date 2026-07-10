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

    public function test_void_movement_action_via_ui(): void
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

        $service = app(\App\Services\Accounting\StockService::class);
        $movement = $service->recordMovement([
            'user_id'       => $user->id,
            'warehouse_id'  => $warehouse->id,
            'stock_code'    => 'PRD-100',
            'movement_type' => 'in_purchase',
            'direction'     => 'in',
            'quantity'      => 10,
        ]);

        $this->assertEquals(10, $service->getStockLevel($user->id, 'PRD-100'));

        Livewire::actingAs($user)
            ->test('accounting.stock')
            ->call('voidMovement', $movement->id)
            ->assertSet('messageType', 'success');

        $this->assertEquals(0, $service->getStockLevel($user->id, 'PRD-100'));
        $this->assertEquals('voided', $movement->fresh()->status);
    }

    public function test_critical_and_zero_stock_filters(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $warehouse = Warehouse::create([
            'user_id' => $user->id,
            'name' => 'Depo',
            'code' => 'depo-main',
            'is_active' => true,
        ]);

        // Critical product (threshold = 10, current = 5)
        $prodCrit = MpProduct::create([
            'user_id' => $user->id,
            'stock_code' => 'CRIT-1',
            'product_name' => 'Critical Product',
            'barcode' => 'BAR-1',
            'critical_stock_threshold' => 10,
        ]);

        StockBalance::create([
            'user_id' => $user->id,
            'warehouse_id' => $warehouse->id,
            'stock_code' => 'CRIT-1',
            'quantity' => 5,
        ]);

        // Out of stock product
        $prodZero = MpProduct::create([
            'user_id' => $user->id,
            'stock_code' => 'ZERO-1',
            'product_name' => 'Zero Product',
            'barcode' => 'BAR-2',
            'critical_stock_threshold' => -1,
        ]);


        StockBalance::create([
            'user_id' => $user->id,
            'warehouse_id' => $warehouse->id,
            'stock_code' => 'ZERO-1',
            'quantity' => 0,
        ]);

        // Healthy product
        $prodHealthy = MpProduct::create([
            'user_id' => $user->id,
            'stock_code' => 'HEALTHY-1',
            'product_name' => 'Healthy Product',
            'barcode' => 'BAR-3',
            'critical_stock_threshold' => 5,
        ]);

        StockBalance::create([
            'user_id' => $user->id,
            'warehouse_id' => $warehouse->id,
            'stock_code' => 'HEALTHY-1',
            'quantity' => 50,
        ]);

        $component = Livewire::actingAs($user)
            ->test('accounting.stock')
            ->set('filterStatus', 'critical');

        $this->assertCount(1, $component->instance()->stockBalances);
        $this->assertEquals('CRIT-1', $component->instance()->stockBalances->first()->stock_code);

        $component->set('filterStatus', 'out_of_stock');
        $this->assertCount(1, $component->instance()->stockBalances);
        $this->assertEquals('ZERO-1', $component->instance()->stockBalances->first()->stock_code);
    }

    public function test_search_filter_does_not_leak_tenant_records(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user1 = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $user2 = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $warehouse2 = Warehouse::create([
            'user_id' => $user2->id,
            'name' => 'LEAK_SEARCH_TERM',
            'code' => 'leak-u2',
            'is_active' => true,
        ]);

        $component = Livewire::actingAs($user1)
            ->test('accounting.stock')
            ->set('search', 'LEAK_SEARCH_TERM');

        $this->assertCount(0, $component->instance()->stockBalances);
    }

    public function test_dropdowns_exclude_other_users_records(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user1 = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $user2 = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $warehouse1 = Warehouse::create([
            'user_id' => $user1->id,
            'name' => 'User1 Depo',
            'code' => 'u1-depo',
            'is_active' => true,
        ]);

        $warehouse2 = Warehouse::create([
            'user_id' => $user2->id,
            'name' => 'User2 Depo',
            'code' => 'u2-depo',
            'is_active' => true,
        ]);

        $product1 = MpProduct::create([
            'user_id' => $user1->id,
            'stock_code' => 'PRD-1',
            'product_name' => 'User1 Product',
            'barcode' => 'BAR-1',
        ]);

        $product2 = MpProduct::create([
            'user_id' => $user2->id,
            'stock_code' => 'PRD-2',
            'product_name' => 'User2 Product',
            'barcode' => 'BAR-2',
        ]);

        $component = Livewire::actingAs($user1)->test('accounting.stock');

        // Check warehouses dropdown
        $this->assertTrue($component->instance()->warehouses->contains('id', $warehouse1->id));
        $this->assertFalse($component->instance()->warehouses->contains('id', $warehouse2->id));

        // Check products dropdown
        $this->assertTrue($component->instance()->products->contains('stock_code', $product1->stock_code));
        $this->assertFalse($component->instance()->products->contains('stock_code', $product2->stock_code));
    }

    public function test_legal_entity_dropdown_renders_without_hata(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        // Create active legal entity
        $legalEntity = \App\Models\LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Active Legal Entity',
            'tax_number' => '1234567890',
            'is_active' => true,
        ]);

        $component = Livewire::actingAs($user)
            ->test('accounting.stock')
            ->set('showMovementForm', true)
            ->assertSee('Active Legal Entity')
            ->set('showMovementForm', false)
            ->set('showWarehouseForm', true)
            ->assertSee('Active Legal Entity');

        // Check computed legalEntities contains our entity
        $this->assertTrue($component->instance()->legalEntities->contains('id', $legalEntity->id));
    }
}
