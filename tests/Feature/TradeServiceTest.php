<?php

namespace Tests\Feature;

use App\Models\MpProduct;
use App\Models\Party;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\TradeService;
use App\Services\Accounting\StockService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TradeServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Party $party;
    private TradeService $service;
    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['is_active' => true]);
        $this->party = Party::factory()->create(['user_id' => $this->user->id]);

        $seeder = new ChartOfAccountsSeeder();
        $seeder->runForUser($this->user->id);

        $this->service = app(TradeService::class);

        // Seed default warehouse
        $this->warehouse = app(StockService::class)->createWarehouse($this->user->id, 'Merkez Depo', 'depo-merkez', true);
    }

    public function test_create_sales_order_calculates_total_with_vat(): void
    {
        $order = $this->service->createSalesOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'document_number' => 'SO-001',
            'order_date'      => now()->toDateString(),
        ], [
            // quantity * price = 200. KDV 20% -> 240
            ['stock_code' => 'P-1', 'quantity' => 2, 'unit_price' => 100, 'vat_rate' => 20.00],
            // quantity * price = 300. KDV 10% -> 330
            ['stock_code' => 'P-2', 'quantity' => 3, 'unit_price' => 100, 'vat_rate' => 10.00],
        ]);

        $this->assertEquals('draft', $order->status);
        $this->assertEquals(570.00, (float) $order->total_amount); // 240 + 330 = 570
        $this->assertCount(2, $order->items);
    }

    public function test_approve_sales_order_processes_receivable_and_reduces_inventory(): void
    {
        // 1. Initial inventory: 50 items
        app(StockService::class)->recordMovement([
            'user_id'       => $this->user->id,
            'warehouse_id'  => $this->warehouse->id,
            'stock_code'    => 'STOCK-A',
            'movement_type' => 'in_adjustment',
            'direction'     => 'in',
            'quantity'      => 50,
        ]);

        $order = $this->service->createSalesOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'document_number' => 'SO-100',
            'order_date'      => now()->toDateString(),
        ], [
            ['stock_code' => 'STOCK-A', 'quantity' => 10, 'unit_price' => 20.00, 'vat_rate' => 0.00],
        ]);

        // 2. Approve Order
        $this->service->approveSalesOrder($order);

        $order->refresh();
        $this->assertEquals('approved', $order->status);
        $this->assertNotNull($order->receivable_id);

        // Verify Outstanding Receivable (Invoice of 200.00)
        $this->assertDatabaseHas('receivables', [
            'id' => $order->receivable_id,
            'amount' => 200.00,
            'status' => 'open',
        ]);

        // Verify stock is reduced: 50 - 10 = 40
        $this->assertEquals(40, app(StockService::class)->getStockLevel($this->user->id, 'STOCK-A', $this->warehouse->id));

        // Verify General Ledger is processed: debit 120, credit 600
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $order->receivable->journal_entry_id,
            'debit_amount' => 200.00,
        ]);
    }

    public function test_create_purchase_order_calculates_correctly(): void
    {
        $order = $this->service->createPurchaseOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'document_number' => 'PO-001',
            'order_date'      => now()->toDateString(),
        ], [
            ['stock_code' => 'RAW-1', 'quantity' => 10, 'unit_price' => 5.00, 'vat_rate' => 20.00],
        ]);

        $this->assertEquals('draft', $order->status);
        $this->assertEquals(60.00, (float) $order->total_amount); // 50 * 1.2 = 60
    }

    public function test_approve_purchase_order_processes_payable_and_increases_inventory(): void
    {
        $order = $this->service->createPurchaseOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'document_number' => 'PO-100',
            'order_date'      => now()->toDateString(),
        ], [
            ['stock_code' => 'RAW-2', 'quantity' => 20, 'unit_price' => 15.00, 'vat_rate' => 20.00], // 300 * 1.2 = 360
        ]);

        $this->service->approvePurchaseOrder($order);

        $order->refresh();
        $this->assertEquals('approved', $order->status);
        $this->assertNotNull($order->payable_id);

        // Verify Outstanding Payable (Bill of 360.00)
        $this->assertDatabaseHas('payables', [
            'id' => $order->payable_id,
            'amount' => 360.00,
        ]);

        // Verify stock is increased: 20
        $this->assertEquals(20, app(StockService::class)->getStockLevel($this->user->id, 'RAW-2', $this->warehouse->id));

        // Verify General Ledger: debit 770, credit 320
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $order->payable->journal_entry_id,
            'credit_amount' => 360.00,
        ]);
    }

    public function test_approving_non_draft_order_is_rejected(): void
    {
        // Seed stock for ITEM-A
        app(StockService::class)->recordMovement([
            'user_id'       => $this->user->id,
            'warehouse_id'  => $this->warehouse->id,
            'stock_code'    => 'ITEM-A',
            'movement_type' => 'in_adjustment',
            'direction'     => 'in',
            'quantity'      => 10,
        ]);

        $order = $this->service->createSalesOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'document_number' => 'SO-ERR',
            'order_date'      => now()->toDateString(),
        ], [
            ['stock_code' => 'ITEM-A', 'quantity' => 1, 'unit_price' => 10.00],
        ]);

        $this->service->approveSalesOrder($order);
        $order->refresh();

        $this->expectException(\InvalidArgumentException::class);
        $this->service->approveSalesOrder($order); // Should fail as it is already approved
    }

    public function test_trade_service_rejects_other_user_party(): void
    {
        $otherUser = User::factory()->create();
        $otherParty = Party::factory()->create(['user_id' => $otherUser->id]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/bu kullanıcıya ait değil/i');

        $this->service->createSalesOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $otherParty->id,
            'document_number' => 'SO-SEC-1',
            'order_date'      => now()->toDateString(),
        ], [
            ['stock_code' => 'ITEM-A', 'quantity' => 1, 'unit_price' => 10.00],
        ]);
    }

    public function test_trade_service_rejects_other_user_legal_entity(): void
    {
        $otherUser = User::factory()->create();
        $otherEntity = \App\Models\LegalEntity::create([
            'user_id' => $otherUser->id,
            'name' => 'Fake Legal Entity',
            'tax_number' => '999999',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/bu kullanıcıya ait değil/i');

        $this->service->createSalesOrder([
            'user_id'         => $this->user->id,
            'party_id'         => $this->party->id,
            'legal_entity_id'  => $otherEntity->id,
            'document_number' => 'SO-SEC-2',
            'order_date'      => now()->toDateString(),
        ], [
            ['stock_code' => 'ITEM-A', 'quantity' => 1, 'unit_price' => 10.00],
        ]);
    }

    public function test_approve_sales_order_fails_when_default_warehouse_is_empty_but_other_has_stock(): void
    {
        // 1. Create a non-default warehouse
        $otherWarehouse = app(StockService::class)->createWarehouse($this->user->id, 'Secondary Warehouse', 'depo-sec', false);

        // 2. Put 10 items in the secondary warehouse
        app(StockService::class)->recordMovement([
            'user_id'       => $this->user->id,
            'warehouse_id'  => $otherWarehouse->id,
            'stock_code'    => 'STOCK-MULTI',
            'movement_type' => 'in_adjustment',
            'direction'     => 'in',
            'quantity'      => 10,
        ]);

        // Verify stock levels: default has 0, secondary has 10
        $this->assertEquals(0, app(StockService::class)->getStockLevel($this->user->id, 'STOCK-MULTI', $this->warehouse->id));
        $this->assertEquals(10, app(StockService::class)->getStockLevel($this->user->id, 'STOCK-MULTI', $otherWarehouse->id));

        // 3. Create a sales order requiring 5 items
        $order = $this->service->createSalesOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'document_number' => 'SO-MULTI',
            'order_date'      => now()->toDateString(),
        ], [
            ['stock_code' => 'STOCK-MULTI', 'quantity' => 5, 'unit_price' => 20.00, 'vat_rate' => 0.00],
        ]);

        // 4. Try to approve the order, it should fail since it checks the default warehouse which has 0 stock
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/yetersiz stok/i');

        try {
            $this->service->approveSalesOrder($order);
        } finally {
            // Verify absolutely NO receivable / journal / party ledger / stock movement is created
            $order->refresh();
            $this->assertEquals('draft', $order->status);
            $this->assertNull($order->receivable_id);
            $this->assertDatabaseMissing('receivables', ['document_number' => 'SO-MULTI']);
            $this->assertDatabaseMissing('party_ledger_entries', ['source_key' => 'sales_order_post_' . $order->id]);
            $this->assertDatabaseMissing('stock_movements', [
                'user_id'    => $this->user->id,
                'stock_code' => 'STOCK-MULTI',
                'direction'  => 'out',
            ]);
        }
    }
}
