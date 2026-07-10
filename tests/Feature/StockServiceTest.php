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
            'stock_quantity' => 0,
        ]);

        $w = $this->service->createWarehouse($this->user->id, 'Depo', 'depo', true);

        // Add 10 items
        $this->service->recordMovement([
            'user_id'       => $this->user->id,
            'warehouse_id'  => $w->id,
            'stock_code'    => 'SYNC-CODE',
            'movement_type' => 'in_purchase',
            'direction'     => 'in',
            'quantity'      => 10,
        ]);

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

    public function test_warehouse_duplicate_code_is_rejected(): void
    {
        $this->service->createWarehouse($this->user->id, 'Merkez', 'depo-merkez');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/zaten kullanımda/i');

        $this->service->createWarehouse($this->user->id, 'Şube', 'DEPO-MERKEZ');
    }

    public function test_resolve_warehouse_id_checks(): void
    {
        $otherUser = User::factory()->create();
        $otherWh = $this->service->createWarehouse($otherUser->id, 'Other Wh', 'other-wh');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/kullanıcıya ait değil/i');

        $this->service->resolveWarehouseId($this->user->id, $otherWh->id);
    }

    public function test_resolve_passive_warehouse_is_rejected(): void
    {
        $wh = $this->service->createWarehouse($this->user->id, 'Passive Wh', 'passive-wh');
        $wh->update(['is_active' => false]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/pasif durumda/i');

        $this->service->resolveWarehouseId($this->user->id, $wh->id);
    }

    public function test_negative_quantity_is_rejected(): void
    {
        $wh = $this->service->createWarehouse($this->user->id, 'Depo', 'depo');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/sıfırdan büyük/i');

        $this->service->recordMovement([
            'user_id'       => $this->user->id,
            'warehouse_id'  => $wh->id,
            'stock_code'    => 'ABC',
            'movement_type' => 'in_purchase',
            'direction'     => 'in',
            'quantity'      => -5,
        ]);
    }

    public function test_invalid_movement_type_direction_mismatch_is_rejected(): void
    {
        $wh = $this->service->createWarehouse($this->user->id, 'Depo', 'depo');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Giriş yönlü hareket tipi/i');

        $this->service->recordMovement([
            'user_id'       => $this->user->id,
            'warehouse_id'  => $wh->id,
            'stock_code'    => 'ABC',
            'movement_type' => 'out_sale', // mismatch with direction=in
            'direction'     => 'in',
            'quantity'      => 5,
        ]);
    }

    public function test_insufficient_stock_outflow_is_rejected(): void
    {
        $wh = $this->service->createWarehouse($this->user->id, 'Depo', 'depo');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Yetersiz stok bakiyesi/i');

        $this->service->recordMovement([
            'user_id'       => $this->user->id,
            'warehouse_id'  => $wh->id,
            'stock_code'    => 'OUT-TEST',
            'movement_type' => 'out_sale',
            'direction'     => 'out',
            'quantity'      => 5,
        ]);
    }

    public function test_source_key_idempotency_returns_same_movement(): void
    {
        $wh = $this->service->createWarehouse($this->user->id, 'Depo', 'depo');

        $m1 = $this->service->recordMovement([
            'user_id'       => $this->user->id,
            'warehouse_id'  => $wh->id,
            'stock_code'    => 'IDEM-TEST',
            'movement_type' => 'in_purchase',
            'direction'     => 'in',
            'quantity'      => 10,
            'source_key'    => 'unique-src-123',
        ]);

        $m2 = $this->service->recordMovement([
            'user_id'       => $this->user->id,
            'warehouse_id'  => $wh->id,
            'stock_code'    => 'IDEM-TEST',
            'movement_type' => 'in_purchase',
            'direction'     => 'in',
            'quantity'      => 10,
            'source_key'    => 'unique-src-123', // duplicate
        ]);

        $this->assertEquals($m1->id, $m2->id);
    }

    public function test_void_in_movement_reduces_balance(): void
    {
        $wh = $this->service->createWarehouse($this->user->id, 'Depo', 'depo');

        $m = $this->service->recordMovement([
            'user_id'       => $this->user->id,
            'warehouse_id'  => $wh->id,
            'stock_code'    => 'VOID-IN-TEST',
            'movement_type' => 'in_purchase',
            'direction'     => 'in',
            'quantity'      => 10,
        ]);

        $this->assertEquals(10, $this->service->getStockLevel($this->user->id, 'VOID-IN-TEST', $wh->id));

        $this->service->voidMovement($m, 'Error entry', $this->user->id);

        $this->assertEquals(0, $this->service->getStockLevel($this->user->id, 'VOID-IN-TEST', $wh->id));
        $this->assertEquals('voided', $m->fresh()->status);
    }

    public function test_void_in_movement_negating_balance_is_rejected(): void
    {
        $wh = $this->service->createWarehouse($this->user->id, 'Depo', 'depo');

        $m = $this->service->recordMovement([
            'user_id'       => $this->user->id,
            'warehouse_id'  => $wh->id,
            'stock_code'    => 'VOID-NEG-TEST',
            'movement_type' => 'in_purchase',
            'direction'     => 'in',
            'quantity'      => 10,
        ]);

        // Consume 5 items (5 remain)
        $this->service->recordMovement([
            'user_id'       => $this->user->id,
            'warehouse_id'  => $wh->id,
            'stock_code'    => 'VOID-NEG-TEST',
            'movement_type' => 'out_sale',
            'direction'     => 'out',
            'quantity'      => 5,
        ]);

        // Trying to void the original +10 in movement when only 5 remain would cause negative stock (-5)
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/negatife düşecektir/i');

        $this->service->voidMovement($m, 'Voiding input', $this->user->id);
    }

    public function test_void_out_movement_restores_balance(): void
    {
        $wh = $this->service->createWarehouse($this->user->id, 'Depo', 'depo');

        // Add 10
        $this->service->recordMovement([
            'user_id'       => $this->user->id,
            'warehouse_id'  => $wh->id,
            'stock_code'    => 'VOID-OUT-TEST',
            'movement_type' => 'in_purchase',
            'direction'     => 'in',
            'quantity'      => 10,
        ]);

        // Subtract 4
        $m = $this->service->recordMovement([
            'user_id'       => $this->user->id,
            'warehouse_id'  => $wh->id,
            'stock_code'    => 'VOID-OUT-TEST',
            'movement_type' => 'out_sale',
            'direction'     => 'out',
            'quantity'      => 4,
        ]);

        $this->assertEquals(6, $this->service->getStockLevel($this->user->id, 'VOID-OUT-TEST', $wh->id));

        $this->service->voidMovement($m, 'Voiding output', $this->user->id);

        $this->assertEquals(10, $this->service->getStockLevel($this->user->id, 'VOID-OUT-TEST', $wh->id));
        $this->assertEquals('voided', $m->fresh()->status);
    }

    public function test_void_movement_other_user_is_rejected(): void
    {
        $wh = $this->service->createWarehouse($this->user->id, 'Depo', 'depo');
        $m = $this->service->recordMovement([
            'user_id'       => $this->user->id,
            'warehouse_id'  => $wh->id,
            'stock_code'    => 'TEST',
            'movement_type' => 'in_purchase',
            'direction'     => 'in',
            'quantity'      => 10,
        ]);

        $otherUser = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/yetkiniz yok/i');

        $this->service->voidMovement($m, 'Void', $otherUser->id);
    }

    public function test_void_movement_no_context_is_rejected(): void
    {
        $wh = $this->service->createWarehouse($this->user->id, 'Depo', 'depo');
        $m = $this->service->recordMovement([
            'user_id'       => $this->user->id,
            'warehouse_id'  => $wh->id,
            'stock_code' => 'TEST',
            'movement_type' => 'in_purchase',
            'direction'     => 'in',
            'quantity'      => 10,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/kullanıcı bilgisi bulunamadı/i');

        auth()->logout();
        $this->service->voidMovement($m, 'Void', null);
    }

    public function test_void_movement_twice_is_rejected(): void
    {
        $wh = $this->service->createWarehouse($this->user->id, 'Depo', 'depo');
        $m = $this->service->recordMovement([
            'user_id'       => $this->user->id,
            'warehouse_id'  => $wh->id,
            'stock_code'    => 'TEST',
            'movement_type' => 'in_purchase',
            'direction'     => 'in',
            'quantity'      => 10,
        ]);

        $this->service->voidMovement($m, 'Void first', $this->user->id);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/zaten iptal edilmiş/i');

        $this->service->voidMovement($m, 'Void second', $this->user->id);
    }

    public function test_migration_columns_and_unique_indexes(): void
    {
        $this->assertTrue(\Schema::hasColumn('stock_movements', 'legal_entity_id'));
        $this->assertTrue(\Schema::hasColumn('stock_movements', 'source_key'));
        $this->assertTrue(\Schema::hasColumn('stock_movements', 'reference_number'));
        $this->assertTrue(\Schema::hasColumn('stock_movements', 'status'));
        $this->assertTrue(\Schema::hasColumn('stock_movements', 'posted_at'));
        $this->assertTrue(\Schema::hasColumn('stock_movements', 'voided_at'));
        $this->assertTrue(\Schema::hasColumn('stock_movements', 'void_reason'));
        $this->assertTrue(\Schema::hasColumn('stock_movements', 'meta_json'));

        $this->assertTrue(\Schema::hasColumn('warehouses', 'legal_entity_id'));
        $this->assertTrue(\Schema::hasColumn('warehouses', 'meta_json'));

        // Test database unique constraint
        $wh = $this->service->createWarehouse($this->user->id, 'Depo', 'depo');

        // Create first movement with source_key
        $this->service->recordMovement([
            'user_id'       => $this->user->id,
            'warehouse_id'  => $wh->id,
            'stock_code'    => 'MIG-TEST',
            'movement_type' => 'in_purchase',
            'direction'     => 'in',
            'quantity'      => 10,
            'source_key'    => 'mig-src-key-1',
        ]);

        // Trying to bypass the service level check and insert duplicate unique constraint at DB level should fail
        $this->expectException(\Illuminate\Database\QueryException::class);

        \DB::table('stock_movements')->insert([
            'user_id'       => $this->user->id,
            'warehouse_id'  => $wh->id,
            'stock_code'    => 'MIG-TEST',
            'movement_type' => 'in_purchase',
            'direction'     => 'in',
            'quantity'      => 5,
            'source_key'    => 'mig-src-key-1', // same source key
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function test_voided_source_key_idempotency_returns_same_movement(): void
    {
        $wh = $this->service->createWarehouse($this->user->id, 'Depo', 'depo');

        // 1. Create movement with source key
        $m1 = $this->service->recordMovement([
            'user_id'       => $this->user->id,
            'warehouse_id'  => $wh->id,
            'stock_code'    => 'IDEM-VOID-TEST',
            'movement_type' => 'in_purchase',
            'direction'     => 'in',
            'quantity'      => 10,
            'source_key'    => 'unique-idem-void-123',
        ]);

        $this->assertEquals(10, $this->service->getStockLevel($this->user->id, 'IDEM-VOID-TEST', $wh->id));

        // 2. Void the movement
        $this->service->voidMovement($m1, 'Voiding it', $this->user->id);
        $this->assertEquals(0, $this->service->getStockLevel($this->user->id, 'IDEM-VOID-TEST', $wh->id));

        // 3. Try to record the exact same source key again
        // It must NOT throw DB unique key exception and it should return the exact same movement record
        $m2 = $this->service->recordMovement([
            'user_id'       => $this->user->id,
            'warehouse_id'  => $wh->id,
            'stock_code'    => 'IDEM-VOID-TEST',
            'movement_type' => 'in_purchase',
            'direction'     => 'in',
            'quantity'      => 10,
            'source_key'    => 'unique-idem-void-123',
        ]);

        $this->assertEquals($m1->id, $m2->id);
        $this->assertEquals('voided', $m2->fresh()->status);
        $this->assertEquals(0, $this->service->getStockLevel($this->user->id, 'IDEM-VOID-TEST', $wh->id));
    }
}
