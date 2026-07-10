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
        $this->party->roles()->create(['user_id' => $this->user->id, 'role' => 'customer']);
        $this->party->roles()->create(['user_id' => $this->user->id, 'role' => 'supplier']);

        $seeder = new ChartOfAccountsSeeder();
        $seeder->runForUser($this->user->id);

        $this->service = app(TradeService::class);

        // Seed default warehouse
        $this->warehouse = app(StockService::class)->createWarehouse($this->user->id, 'Merkez Depo', 'depo-merkez', true);

        // Seed MpProducts to satisfy stock_code verification
        $stockCodes = ['P-1', 'P-2', 'STOCK-A', 'RAW-1', 'RAW-2', 'ITEM-A', 'STOCK-MULTI', 'PRD-ANY'];
        foreach ($stockCodes as $code) {
            MpProduct::create([
                'user_id' => $this->user->id,
                'stock_code' => $code,
                'product_name' => 'Product ' . $code,
                'barcode' => 'BAR-' . $code,
            ]);
        }
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

    public function test_create_sales_order_rejects_non_customer_party(): void
    {
        $nonCustomerParty = Party::factory()->create(['user_id' => $this->user->id]);
        $nonCustomerParty->roles()->create(['user_id' => $this->user->id, 'role' => 'supplier']); // not customer

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/cari müşteri rolüne sahip değil/i');

        $this->service->createSalesOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $nonCustomerParty->id,
            'document_number' => 'SO-NOCUST',
            'order_date'      => now()->toDateString(),
        ], [
            ['stock_code' => 'P-1', 'quantity' => 1, 'unit_price' => 10],
        ]);
    }

    public function test_create_sales_order_rejects_other_user_warehouse(): void
    {
        $otherUser = User::factory()->create();
        $otherWarehouse = app(StockService::class)->createWarehouse($otherUser->id, 'Other WH', 'depo-other', true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/depo bu kullanıcıya ait değil/i');

        $this->service->createSalesOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'warehouse_id'    => $otherWarehouse->id,
            'document_number' => 'SO-BADWH',
            'order_date'      => now()->toDateString(),
        ], [
            ['stock_code' => 'P-1', 'quantity' => 1, 'unit_price' => 10],
        ]);
    }

    public function test_source_key_with_different_payload_throws(): void
    {
        // First creation
        $this->service->createSalesOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'document_number' => 'SO-PAY-1',
            'order_date'      => now()->toDateString(),
            'source_key'      => 'unique_src_key',
        ], [
            ['stock_code' => 'P-1', 'quantity' => 1, 'unit_price' => 10],
        ]);

        // Attempt second creation with different document_number but same source_key
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/farklı başlık detaylarına sahip/i');

        $this->service->createSalesOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'document_number' => 'SO-PAY-2', // different
            'order_date'      => now()->toDateString(),
            'source_key'      => 'unique_src_key',
        ], [
            ['stock_code' => 'P-1', 'quantity' => 1, 'unit_price' => 10],
        ]);
    }

    public function test_same_source_key_different_item_quantity_throws(): void
    {
        // First creation
        $this->service->createSalesOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'document_number' => 'SO-QTY-1',
            'order_date'      => now()->toDateString(),
            'source_key'      => 'unique_qty_key',
        ], [
            ['stock_code' => 'P-1', 'quantity' => 1, 'unit_price' => 10],
        ]);

        // Attempt second creation with different item quantity
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/farklı kalem detaylarına sahip/i');

        $this->service->createSalesOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'document_number' => 'SO-QTY-1',
            'order_date'      => now()->toDateString(),
            'source_key'      => 'unique_qty_key',
        ], [
            ['stock_code' => 'P-1', 'quantity' => 2, 'unit_price' => 10], // different quantity
        ]);
    }

    public function test_same_source_key_different_warehouse_throws(): void
    {
        // Create secondary warehouse
        $whSecondary = app(StockService::class)->createWarehouse($this->user->id, 'Second WH', 'depo-wh-2', false);

        // First creation
        $this->service->createSalesOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'warehouse_id'    => $this->warehouse->id,
            'document_number' => 'SO-WH-1',
            'order_date'      => now()->toDateString(),
            'source_key'      => 'unique_wh_key',
        ], [
            ['stock_code' => 'P-1', 'quantity' => 1, 'unit_price' => 10],
        ]);

        // Attempt second creation with different warehouse
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/farklı başlık detaylarına sahip/i');

        $this->service->createSalesOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'warehouse_id'    => $whSecondary->id, // different warehouse
            'document_number' => 'SO-WH-1',
            'order_date'      => now()->toDateString(),
            'source_key'      => 'unique_wh_key',
        ], [
            ['stock_code' => 'P-1', 'quantity' => 1, 'unit_price' => 10],
        ]);
    }

    public function test_same_source_key_different_legal_entity_throws(): void
    {
        $legalEntity = \App\Models\LegalEntity::create([
            'user_id' => $this->user->id,
            'name' => 'Firma Test 1',
            'tax_number' => '1234567890',
            'company_type' => 'limited',
            'is_active' => true,
        ]);

        // First creation with legal_entity_id set
        $this->service->createSalesOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'legal_entity_id' => $legalEntity->id,
            'document_number' => 'SO-LE-1',
            'order_date'      => now()->toDateString(),
            'source_key'      => 'unique_le_key',
        ], [
            ['stock_code' => 'P-1', 'quantity' => 1, 'unit_price' => 10],
        ]);

        // Attempt second creation with missing/null legal_entity_id
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/farklı başlık detaylarına sahip/i');

        $this->service->createSalesOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'legal_entity_id' => null, // missing/null
            'document_number' => 'SO-LE-1',
            'order_date'      => now()->toDateString(),
            'source_key'      => 'unique_le_key',
        ], [
            ['stock_code' => 'P-1', 'quantity' => 1, 'unit_price' => 10],
        ]);
    }

    public function test_same_source_key_different_default_warehouse_throws(): void
    {
        // First creation uses warehouse_id explicitly
        $this->service->createSalesOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'warehouse_id'    => $this->warehouse->id,
            'document_number' => 'SO-DEFWH-1',
            'order_date'      => now()->toDateString(),
            'source_key'      => 'unique_defwh_key',
        ], [
            ['stock_code' => 'P-1', 'quantity' => 1, 'unit_price' => 10],
        ]);

        // Change default warehouse to a secondary warehouse
        $whSecondary = app(StockService::class)->createWarehouse($this->user->id, 'Second WH', 'depo-wh-2', true); // set as default

        // Attempt second creation with warehouse_id omitted (should resolve to new default warehouse, which is different)
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/farklı başlık detaylarına sahip/i');

        $this->service->createSalesOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'warehouse_id'    => null, // omitted/null
            'document_number' => 'SO-DEFWH-1',
            'order_date'      => now()->toDateString(),
            'source_key'      => 'unique_defwh_key',
        ], [
            ['stock_code' => 'P-1', 'quantity' => 1, 'unit_price' => 10],
        ]);
    }

    public function test_approve_writes_stock_movement_source_key_and_warehouse(): void
    {
        app(StockService::class)->recordMovement([
            'user_id'       => $this->user->id,
            'warehouse_id'  => $this->warehouse->id,
            'stock_code'    => 'P-1',
            'movement_type' => 'in_adjustment',
            'direction'     => 'in',
            'quantity'      => 10,
        ]);

        $order = $this->service->createSalesOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'warehouse_id'    => $this->warehouse->id,
            'document_number' => 'SO-MVT-1',
            'order_date'      => now()->toDateString(),
        ], [
            ['stock_code' => 'P-1', 'quantity' => 2, 'unit_price' => 100],
        ]);

        $this->service->approveSalesOrder($order);

        $this->assertDatabaseHas('stock_movements', [
            'user_id'          => $this->user->id,
            'warehouse_id'     => $this->warehouse->id,
            'stock_code'       => 'P-1',
            'direction'        => 'out',
            'source_type'      => 'sales_order',
            'source_id'        => $order->id,
            'source_key'       => 'sales_order_stock_out_' . $order->id . '_' . $order->items->first()->id,
            'reference_number' => 'SO-MVT-1',
        ]);
    }

    public function test_cancel_returns_stock_to_original_warehouse(): void
    {
        // Create 2 warehouses
        $whSecondary = app(StockService::class)->createWarehouse($this->user->id, 'Second', 'depo-2', false);

        // Put stock in secondary warehouse
        app(StockService::class)->recordMovement([
            'user_id'       => $this->user->id,
            'warehouse_id'  => $whSecondary->id,
            'stock_code'    => 'P-1',
            'movement_type' => 'in_adjustment',
            'direction'     => 'in',
            'quantity'      => 10,
        ]);

        // Create Sales Order targeting secondary warehouse
        $order = $this->service->createSalesOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'warehouse_id'    => $whSecondary->id,
            'document_number' => 'SO-WH-2',
            'order_date'      => now()->toDateString(),
        ], [
            ['stock_code' => 'P-1', 'quantity' => 4, 'unit_price' => 50],
        ]);

        $this->service->approveSalesOrder($order);

        // Change default warehouse
        $whNewDefault = app(StockService::class)->createWarehouse($this->user->id, 'New Default', 'depo-3', true);

        // Cancel order
        $this->service->cancelSalesOrder($order, 'Test Cancel');

        // Stock in secondary warehouse should return to 10
        $this->assertEquals(10, app(StockService::class)->getStockLevel($this->user->id, 'P-1', $whSecondary->id));
        // Stock in new default warehouse should remain 0
        $this->assertEquals(0, app(StockService::class)->getStockLevel($this->user->id, 'P-1', $whNewDefault->id));
    }

    public function test_cancel_receivable_journal_party_ledger_all_voided(): void
    {
        app(StockService::class)->recordMovement([
            'user_id'       => $this->user->id,
            'warehouse_id'  => $this->warehouse->id,
            'stock_code'    => 'P-1',
            'movement_type' => 'in_adjustment',
            'direction'     => 'in',
            'quantity'      => 10,
        ]);

        $order = $this->service->createSalesOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'document_number' => 'SO-VOID-ALL',
            'order_date'      => now()->toDateString(),
        ], [
            ['stock_code' => 'P-1', 'quantity' => 2, 'unit_price' => 50, 'vat_rate' => 0.00],
        ]);

        $this->service->approveSalesOrder($order);

        $this->service->cancelSalesOrder($order, 'İptal Nedeni');

        $order->refresh();
        $this->assertEquals('cancelled', $order->status);
        $this->assertEquals('İptal Nedeni', $order->cancel_reason);

        // Receivable status must be voided
        $this->assertDatabaseHas('receivables', [
            'id' => $order->receivable_id,
            'status' => 'voided',
        ]);

        // JournalEntry must be voided
        $receivable = \App\Models\Receivable::find($order->receivable_id);
        $this->assertNotNull($receivable->journal_entry_id);
        $journalEntry = \App\Models\JournalEntry::find($receivable->journal_entry_id);
        $this->assertTrue($journalEntry->isVoid());

        // Party Ledger Entry must be voided
        $ledgerEntry = \App\Models\PartyLedgerEntry::where('user_id', $this->user->id)
            ->where('source_type', 'sales_order')
            ->where('source_key', 'sales_order_post_' . $order->id)
            ->first();
        $this->assertTrue($ledgerEntry->isVoid());
    }

    public function test_create_sales_order_rejects_empty_item_list(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/sipariş kalemi bulunamadı/i');

        $this->service->createSalesOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'document_number' => 'SO-EMPTY',
            'order_date'      => now()->toDateString(),
        ], []);
    }

    public function test_create_sales_order_rejects_duplicate_document_number(): void
    {
        $this->service->createSalesOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'document_number' => 'SO-DUP-1',
            'order_date'      => now()->toDateString(),
        ], [
            ['stock_code' => 'P-1', 'quantity' => 1, 'unit_price' => 10],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/zaten mevcut/i');

        $this->service->createSalesOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'document_number' => 'SO-DUP-1',
            'order_date'      => now()->toDateString(),
        ], [
            ['stock_code' => 'P-1', 'quantity' => 1, 'unit_price' => 10],
        ]);
    }

    public function test_create_sales_order_calculates_correctly_with_discounts(): void
    {
        $order = $this->service->createSalesOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'document_number' => 'SO-DISC-TEST',
            'order_date'      => now()->toDateString(),
            'discount_amount' => 15.00, // Header discount
        ], [
            // quantity * price = 200. line discount 10% -> 20. line total before vat = 180. VAT 20% -> 36. line total = 216
            ['stock_code' => 'P-1', 'quantity' => 2, 'unit_price' => 100, 'vat_rate' => 20.00, 'discount_rate' => 10.00],
        ]);

        // subtotal = 200
        // total discount = 20 (line) + 15 (header) = 35
        // vat = 36
        // total = 200 - 35 + 36 = 201
        $this->assertEquals(201.00, (float) $order->total_amount);
        $this->assertEquals(35.00, (float) $order->discount_amount);
    }

    public function test_approve_sales_order_rollback_on_failed_stock(): void
    {
        // No stock seeded for P-1
        $order = $this->service->createSalesOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'document_number' => 'SO-ROLLBACK',
            'order_date'      => now()->toDateString(),
        ], [
            ['stock_code' => 'P-1', 'quantity' => 5, 'unit_price' => 10],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/yetersiz stok/i');

        try {
            $this->service->approveSalesOrder($order);
        } finally {
            $order->refresh();
            $this->assertEquals('draft', $order->status);
            $this->assertNull($order->receivable_id);
            $this->assertDatabaseMissing('receivables', ['document_number' => 'SO-ROLLBACK']);
        }
    }

    // -------------------------------------------------------
    // P7 — Satın Alma Siparişi Hardening Testleri
    // -------------------------------------------------------

    /** @test */
    public function test_create_purchase_order_requires_supplier_role(): void
    {
        // Create a new party for this test that has NO supplier role
        $noSupplierParty = Party::factory()->create(['user_id' => $this->user->id]);
        $noSupplierParty->roles()->create(['user_id' => $this->user->id, 'role' => 'customer']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/tedarikçi rol/i');

        $this->service->createPurchaseOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $noSupplierParty->id,
            'document_number' => 'ALI-ROLE',
            'order_date'      => now()->toDateString(),
        ], [
            ['stock_code' => 'STOCK-A', 'quantity' => 2, 'unit_price' => 50.00],
        ]);
    }

    /** @test */
    public function test_create_purchase_order_succeeds_with_supplier_role(): void
    {
        $order = $this->service->createPurchaseOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'document_number' => 'ALI-SUPPLIER-OK',
            'order_date'      => now()->toDateString(),
        ], [
            ['stock_code' => 'STOCK-A', 'quantity' => 3, 'unit_price' => 100.00, 'vat_rate' => 20.00],
        ]);

        $this->assertEquals('draft', $order->status);
        $this->assertDatabaseHas('purchase_orders', [
            'document_number' => 'ALI-SUPPLIER-OK',
            'status'          => 'draft',
        ]);
        // 3 * 100 = 300, KDV = 60, Toplam = 360
        $this->assertEquals(360.00, (float) $order->total_amount);
    }

    /** @test */
    public function test_create_purchase_order_source_key_idempotent_same_payload_returns_existing(): void
    {
        $params = [
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'document_number' => 'ALI-IDEM-001',
            'order_date'      => now()->toDateString(),
            'source_key'      => 'ext_purchase_001',
        ];
        $itemParams = [
            ['stock_code' => 'STOCK-A', 'quantity' => 2, 'unit_price' => 50.00, 'vat_rate' => 20.00, 'discount_rate' => 0.00],
        ];

        $first  = $this->service->createPurchaseOrder($params, $itemParams);
        $second = $this->service->createPurchaseOrder($params, $itemParams);

        $this->assertEquals($first->id, $second->id);
        $this->assertDatabaseCount('purchase_orders', 1);
    }

    /** @test */
    public function test_create_purchase_order_source_key_different_payload_throws(): void
    {
        $this->service->createPurchaseOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'document_number' => 'ALI-IDEM-002',
            'order_date'      => now()->toDateString(),
            'source_key'      => 'ext_purchase_002',
        ], [
            ['stock_code' => 'STOCK-A', 'quantity' => 2, 'unit_price' => 50.00, 'vat_rate' => 20.00],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/source_key/i');

        // Same source_key, different quantity
        $this->service->createPurchaseOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'document_number' => 'ALI-IDEM-002',
            'order_date'      => now()->toDateString(),
            'source_key'      => 'ext_purchase_002',
        ], [
            ['stock_code' => 'STOCK-A', 'quantity' => 99, 'unit_price' => 50.00, 'vat_rate' => 20.00],
        ]);
    }

    /** @test */
    public function test_approve_purchase_order_uses_deterministic_source_key(): void
    {
        $order = $this->service->createPurchaseOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'document_number' => 'ALI-DETKEY',
            'order_date'      => now()->toDateString(),
        ], [
            ['stock_code' => 'STOCK-A', 'quantity' => 5, 'unit_price' => 40.00, 'vat_rate' => 20.00],
        ]);

        $this->service->approvePurchaseOrder($order);

        $item = $order->items->first();
        $expectedKey = 'purchase_order_stock_in_' . $order->id . '_' . $item->id;

        $this->assertDatabaseHas('stock_movements', [
            'user_id'          => $this->user->id,
            'source_key'       => $expectedKey,
            'direction'        => 'in',
            'warehouse_id'     => $order->warehouse_id,
            'reference_number' => $order->document_number,
            'legal_entity_id'  => $order->legal_entity_id,
            'source_type'      => 'purchase_order',
            'source_id'        => $order->id,
        ]);
    }

    /** @test */
    public function test_cancel_purchase_order_negative_stock_guard(): void
    {
        $order = $this->service->createPurchaseOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'document_number' => 'ALI-NEGGUARD',
            'order_date'      => now()->toDateString(),
        ], [
            ['stock_code' => 'STOCK-A', 'quantity' => 10, 'unit_price' => 20.00, 'vat_rate' => 20.00],
        ]);

        $this->service->approvePurchaseOrder($order);

        // Manually drain most of the stock via a separate out movement so cancel would go negative
        app(StockService::class)->recordMovement([
            'user_id'       => $this->user->id,
            'stock_code'    => 'STOCK-A',
            'movement_type' => 'out_sale',
            'direction'     => 'out',
            'quantity'      => 8, // leaves only 2 of the 10 purchased
            'unit_cost'     => 20.00,
            'source_type'   => 'sales_order',
            'source_id'     => 9999,
            'movement_date' => now()->toDateString(),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/yetersiz stok/i');

        $this->service->cancelPurchaseOrder($order->fresh(['items']));
    }

    /** @test */
    public function test_cancel_purchase_order_with_allocation_is_blocked(): void
    {
        $order = $this->service->createPurchaseOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'document_number' => 'ALI-ALLOC',
            'order_date'      => now()->toDateString(),
        ], [
            ['stock_code' => 'STOCK-A', 'quantity' => 5, 'unit_price' => 30.00, 'vat_rate' => 20.00],
        ]);

        $this->service->approvePurchaseOrder($order->fresh());
        $order->refresh();

        // Simulate allocation on payable
        $payable = \App\Models\Payable::find($order->payable_id);
        $payable->update(['status' => 'partially_paid']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/ödeme/i');

        $this->service->cancelPurchaseOrder($order->fresh(['items']));
    }

    /** @test */
    public function test_approve_purchase_order_sets_approved_at(): void
    {
        $order = $this->service->createPurchaseOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'document_number' => 'ALI-APPRTS',
            'order_date'      => now()->toDateString(),
        ], [
            ['stock_code' => 'STOCK-A', 'quantity' => 2, 'unit_price' => 100.00, 'vat_rate' => 20.00],
        ]);

        $this->service->approvePurchaseOrder($order);
        $order->refresh();

        $this->assertNotNull($order->approved_at);
        $this->assertEquals('approved', $order->status);
    }

    /** @test */
    public function test_cancel_purchase_order_sets_cancelled_at_and_reason(): void
    {
        $order = $this->service->createPurchaseOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'document_number' => 'ALI-CANCTS',
            'order_date'      => now()->toDateString(),
        ], [
            ['stock_code' => 'STOCK-A', 'quantity' => 3, 'unit_price' => 50.00, 'vat_rate' => 20.00],
        ]);

        $this->service->approvePurchaseOrder($order);
        $item = $order->items->first();

        $this->service->cancelPurchaseOrder($order->fresh(['items']), 'Test iptali');

        $order->refresh();
        $this->assertEquals('cancelled', $order->status);
        $this->assertNotNull($order->cancelled_at);
        $this->assertEquals('Test iptali', $order->cancel_reason);

        // Verify reverse stock movement was logged correctly
        $this->assertDatabaseHas('stock_movements', [
            'user_id' => $this->user->id,
            'source_type' => 'purchase_order',
            'source_key' => 'purchase_order_cancel_stock_out_' . $order->id . '_' . $item->id,
            'movement_type' => 'out_purchase_return',
            'direction' => 'out',
            'quantity' => 3,
        ]);
    }

    /** @test */
    public function test_purchase_order_discounts_and_vat_calculation(): void
    {
        // Setup:
        // Item: qty=5, price=100, discount_rate=10% -> base = 500, line_disc = 50, item_total = 450
        // Header discount: 30
        // Ara toplam = 500
        // İndirim toplamı = 50 + 30 = 80
        // KDV matrahı = 500 - 50 (line discount) = 450. KDV (20%) = 90
        // Genel Toplam = Matrah - Header_Disc + KDV = 450 - 30 + 90 = 510
        $order = $this->service->createPurchaseOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'document_number' => 'ALI-DISCVAT',
            'order_date'      => now()->toDateString(),
            'discount_amount' => 30.00,
        ], [
            ['stock_code' => 'STOCK-A', 'quantity' => 5, 'unit_price' => 100.00, 'vat_rate' => 20.00, 'discount_rate' => 10.00],
        ]);

        $this->assertEquals(510.00, (float) $order->total_amount);
        $this->assertEquals(80.00, (float) $order->discount_amount);
    }

    /** @test */
    public function test_create_purchase_order_different_discount_amount_with_same_source_key_throws(): void
    {
        $this->service->createPurchaseOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'document_number' => 'ALI-IDEM-DISC1',
            'order_date'      => now()->toDateString(),
            'source_key'      => 'idem_disc_key',
            'discount_amount' => 10.00,
        ], [
            ['stock_code' => 'STOCK-A', 'quantity' => 2, 'unit_price' => 50.00, 'vat_rate' => 20.00],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/source_key/i');

        // Same source_key, but different discount_amount
        $this->service->createPurchaseOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'document_number' => 'ALI-IDEM-DISC2',
            'order_date'      => now()->toDateString(),
            'source_key'      => 'idem_disc_key',
            'discount_amount' => 20.00, // conflict
        ], [
            ['stock_code' => 'STOCK-A', 'quantity' => 2, 'unit_price' => 50.00, 'vat_rate' => 20.00],
        ]);
    }

    /** @test */
    public function test_cancel_purchase_order_with_actual_allocation_is_blocked(): void
    {
        $order = $this->service->createPurchaseOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'document_number' => 'ALI-REALALLOC',
            'order_date'      => now()->toDateString(),
        ], [
            ['stock_code' => 'STOCK-A', 'quantity' => 5, 'unit_price' => 30.00, 'vat_rate' => 20.00],
        ]);

        $this->service->approvePurchaseOrder($order);
        $order->refresh();

        $payable = \App\Models\Payable::find($order->payable_id);

        // Seed a bank account to make a payment
        $bankAcc = app(\App\Services\Accounting\CashBankService::class)->createBankAccount($this->user->id, [
            'bank_name'      => 'Test Bankası',
            'account_number' => '1234567',
            'currency_code'  => 'TRY',
        ]);
        $bankAccount = $bankAcc->account;

        // Record a payment of 50.00
        $payment = app(\App\Services\Accounting\CollectionPaymentService::class)->recordPayment([
            'user_id'        => $this->user->id,
            'party_id'       => $this->party->id,
            'account_id'     => $bankAccount->id,
            'amount'         => 50.00,
            'payment_date'   => now()->toDateString(),
            'payment_method' => 'bank',
        ]);

        // Allocate 50.00 to the payable
        app(\App\Services\Accounting\CollectionPaymentService::class)->allocatePayment($payment, [
            ['payable_id' => $payable->id, 'amount' => 50.00]
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/ödeme/i');

        $this->service->cancelPurchaseOrder($order->fresh(['items']));
    }
}
