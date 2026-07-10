<?php

namespace Tests\Feature;

use App\Models\MpProduct;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private StockService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['is_active' => true]);
        $this->service = app(StockService::class);
    }

    public function test_create_warehouse_clears_other_defaults(): void
    {
        $w1 = $this->service->createWarehouse($this->user->id, 'Depo 1', 'depo-1', true);
        $w2 = $this->service->createWarehouse($this->user->id, 'Depo 2', 'depo-2', true);

        $w1->refresh();
        $w2->refresh();

        $this->assertFalse($w1->is_default);
        $this->assertTrue($w2->is_default);
    }

    public function test_in_movement_increases_stock_balance(): void
    {
        $w = $this->service->createWarehouse($this->user->id, 'Depo', 'depo', true);

        $this->service->recordMovement([
            'user_id'       => $this->user->id,
            'warehouse_id'  => $w->id,
            'stock_code'    => 'URUN-XYZ',
            'movement_type' => 'in_purchase',
            'direction'     => 'in',
            'quantity'      => 50,
        ]);

        $this->assertEquals(50, $this->service->getStockLevel($this->user->id, 'URUN-XYZ', $w->id));
    }

    public function test_out_movement_decreases_stock_balance(): void
    {
        $w = $this->service->createWarehouse($this->user->id, 'Depo', 'depo', true);

        // First deposit 100
        $this->service->recordMovement([
            'user_id'       => $this->user->id,
            'warehouse_id'  => $w->id,
            'stock_code'    => 'URUN-XYZ',
            'movement_type' => 'in_adjustment',
            'direction'     => 'in',
            'quantity'      => 100,
        ]);

        // Then subtract 30
        $this->service->recordMovement([
            'user_id'       => $this->user->id,
            'warehouse_id'  => $w->id,
            'stock_code'    => 'URUN-XYZ',
            'movement_type' => 'out_sale',
            'direction'     => 'out',
            'quantity'      => 30,
        ]);

        $this->assertEquals(70, $this->service->getStockLevel($this->user->id, 'URUN-XYZ', $w->id));
    }

    public function test_movement_resolves_default_warehouse_automatically(): void
    {
        // No warehouse_id specified; should create/resolve default
        $this->service->recordMovement([
            'user_id'       => $this->user->id,
            'stock_code'    => 'AUTO-DEP',
            'movement_type' => 'in_adjustment',
            'direction'     => 'in',
            'quantity'      => 20,
        ]);

        $defaultWarehouse = Warehouse::where('user_id', $this->user->id)->where('is_default', true)->first();
        $this->assertNotNull($defaultWarehouse);

        $this->assertEquals(20, $this->service->getStockLevel($this->user->id, 'AUTO-DEP', $defaultWarehouse->id));
    }

    public function test_movement_syncs_with_mp_products_table(): void
    {
        // Seed an mp_product
        $mpProduct = MpProduct::create([
            'user_id' => $this->user->id,
            'barcode' => '8690001',
            'stock_code' => 'SYNC-CODE',
            'product_name' => 'Sync Test Product',
            'stock_quantity' => 10,
        ]);

        $w = $this->service->createWarehouse($this->user->id, 'Depo', 'depo', true);

        // Add 15 items
        $this->service->recordMovement([
            'user_id'       => $this->user->id,
            'warehouse_id'  => $w->id,
            'stock_code'    => 'SYNC-CODE',
            'movement_type' => 'in_purchase',
            'direction'     => 'in',
            'quantity'      => 15,
        ]);

        $mpProduct->refresh();
        $this->assertEquals(25, $mpProduct->stock_quantity);
    }

    public function test_critical_stock_detection_rules(): void
    {
        // Seed an mp_product with threshold 15
        MpProduct::create([
            'user_id' => $this->user->id,
            'barcode' => '8690002',
            'stock_code' => 'CRIT-1',
            'product_name' => 'Crit Product',
            'stock_quantity' => 0,
            'critical_stock_threshold' => 15,
        ]);

        $w = $this->service->createWarehouse($this->user->id, 'Depo', 'depo', true);

        // 1. Level is 20: not critical (threshold is 15)
        $this->service->recordMovement([
            'user_id'       => $this->user->id,
            'warehouse_id'  => $w->id,
            'stock_code'    => 'CRIT-1',
            'movement_type' => 'in_adjustment',
            'direction'     => 'in',
            'quantity'      => 20,
        ]);
        $this->assertFalse($this->service->isCriticalStock($this->user->id, 'CRIT-1', $w->id));

        // 2. Reduce stock by 6 (now 14): critical!
        $this->service->recordMovement([
            'user_id'       => $this->user->id,
            'warehouse_id'  => $w->id,
            'stock_code'    => 'CRIT-1',
            'movement_type' => 'out_sale',
            'direction'     => 'out',
            'quantity'      => 6,
        ]);
        $this->assertTrue($this->service->isCriticalStock($this->user->id, 'CRIT-1', $w->id));
    }
}
