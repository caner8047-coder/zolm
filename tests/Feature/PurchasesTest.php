<?php

namespace Tests\Feature;

use App\Models\MpProduct;
use App\Models\Party;
use App\Models\PurchaseOrder;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Payable;
use App\Models\PartyLedgerEntry;
use App\Models\LegalEntity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PurchasesTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_is_blocked_when_accounting_enabled_is_false(): void
    {
        config()->set('marketplace.features.accounting_enabled', false);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $this->actingAs($user)
            ->get(route('accounting.purchases'))
            ->assertStatus(404);
    }

    public function test_page_renders_when_accounting_enabled_is_true(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $this->actingAs($user)
            ->get(route('accounting.purchases'))
            ->assertStatus(200)
            ->assertSeeLivewire('accounting.purchases');
    }

    public function test_creating_purchase_order_draft_with_discount(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user->id]);
        $party->roles()->create(['user_id' => $user->id, 'role' => 'supplier']);

        $product = MpProduct::create([
            'user_id' => $user->id,
            'stock_code' => 'PRD-Y',
            'product_name' => 'Fabric Bolt',
            'barcode' => 'BAR-Y',
        ]);

        Livewire::actingAs($user)
            ->test('accounting.purchases')
            ->set('partyId', $party->id)
            ->set('documentNumber', 'ALI-001')
            ->set('items', [
                [
                    'stock_code' => 'PRD-Y',
                    'quantity' => 5,
                    'unit_price' => 50.00,
                    'vat_rate' => 20.00,
                    'discount_rate' => 10.00 // 10% discount
                ],
            ])
            ->call('createPurchaseOrder')
            ->assertSet('messageType', 'success');

        // Calculations:
        // Base = 5 * 50 = 250
        // Discount = 250 * 0.10 = 25
        // Before VAT = 225
        // VAT = 225 * 0.20 = 45
        // Total = 225 + 45 = 270
        $this->assertDatabaseHas('purchase_orders', [
            'user_id' => $user->id,
            'party_id' => $party->id,
            'document_number' => 'ALI-001',
            'status' => 'draft',
            'total_amount' => 270.00,
            'discount_amount' => 25.00,
        ]);

        $this->assertDatabaseHas('purchase_order_items', [
            'stock_code' => 'PRD-Y',
            'quantity' => 5,
            'unit_price' => 50.00,
            'vat_rate' => 20.00,
            'discount_rate' => 10.00,
            'discount_amount' => 25.00,
            'total_amount' => 270.00,
        ]);
    }

    public function test_approving_purchase_order_processes_stock_and_payables(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user->id]);
        $party->roles()->create(['user_id' => $user->id, 'role' => 'supplier']);

        (new \Database\Seeders\ChartOfAccountsSeeder())->runForUser($user->id);

        $warehouse = Warehouse::create([
            'user_id' => $user->id,
            'name' => 'Main',
            'code' => 'depo-main',
            'is_default' => true,
            'is_active' => true,
        ]);

        $product = MpProduct::create([
            'user_id' => $user->id,
            'stock_code' => 'PRD-Y',
            'product_name' => 'Fabric Bolt',
            'barcode' => 'BAR-Y',
        ]);

        // Create draft purchase order
        $tradeService = app(\App\Services\Accounting\TradeService::class);
        $order = $tradeService->createPurchaseOrder([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'document_number' => 'ALI-001',
            'order_date' => now()->toDateString(),
        ], [
            [
                'stock_code' => 'PRD-Y',
                'quantity' => 10,
                'unit_price' => 50.00,
                'vat_rate' => 20.00,
                'discount_rate' => 10.00
            ],
        ]);

        // Base = 500. Discount = 50. VAT = 90. Total = 540.
        Livewire::actingAs($user)
            ->test('accounting.purchases')
            ->call('approveOrder', $order->id)
            ->assertSet('messageType', 'success');

        $this->assertEquals('approved', $order->fresh()->status);

        // Check Payable
        $this->assertDatabaseHas('payables', [
            'user_id' => $user->id,
            'party_id' => $party->id,
            'amount' => 540.00,
            'status' => 'open',
        ]);

        // Check PartyLedgerEntry
        $this->assertDatabaseHas('party_ledger_entries', [
            'user_id' => $user->id,
            'party_id' => $party->id,
            'debit_amount' => 0.00,
            'credit_amount' => 540.00,
            'source_type' => 'purchase_order',
            'source_key' => 'purchase_order_post_' . $order->id,
        ]);

        // Check stock (increased by 10)
        $this->assertEquals(10, StockBalance::where('user_id', $user->id)->first()->quantity);
    }

    public function test_approving_purchase_order_is_idempotent(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user->id]);
        $party->roles()->create(['user_id' => $user->id, 'role' => 'supplier']);

        (new \Database\Seeders\ChartOfAccountsSeeder())->runForUser($user->id);

        $warehouse = Warehouse::create([
            'user_id' => $user->id,
            'name' => 'Main',
            'code' => 'depo-main',
            'is_default' => true,
            'is_active' => true,
        ]);

        $product = MpProduct::create([
            'user_id' => $user->id,
            'stock_code' => 'PRD-Y',
            'product_name' => 'Fabric Bolt',
            'barcode' => 'BAR-Y',
        ]);

        $tradeService = app(\App\Services\Accounting\TradeService::class);
        $order = $tradeService->createPurchaseOrder([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'document_number' => 'ALI-001',
            'order_date' => now()->toDateString(),
        ], [
            ['stock_code' => 'PRD-Y', 'quantity' => 10, 'unit_price' => 50.00, 'vat_rate' => 20.00],
        ]);

        $tradeService->approvePurchaseOrder($order);
        $count = PartyLedgerEntry::where('user_id', $user->id)->count();

        // Try second time
        $this->expectException(\InvalidArgumentException::class);
        $tradeService->approvePurchaseOrder($order);

        $this->assertEquals($count, PartyLedgerEntry::where('user_id', $user->id)->count());
    }

    public function test_cancelling_purchase_order_reverts_stock_and_balances(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user->id]);
        $party->roles()->create(['user_id' => $user->id, 'role' => 'supplier']);

        (new \Database\Seeders\ChartOfAccountsSeeder())->runForUser($user->id);

        $warehouse = Warehouse::create([
            'user_id' => $user->id,
            'name' => 'Main',
            'code' => 'depo-main',
            'is_default' => true,
            'is_active' => true,
        ]);

        $product = MpProduct::create([
            'user_id' => $user->id,
            'stock_code' => 'PRD-Y',
            'product_name' => 'Fabric Bolt',
            'barcode' => 'BAR-Y',
        ]);

        $tradeService = app(\App\Services\Accounting\TradeService::class);
        $order = $tradeService->createPurchaseOrder([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'document_number' => 'ALI-001',
            'order_date' => now()->toDateString(),
        ], [
            ['stock_code' => 'PRD-Y', 'quantity' => 10, 'unit_price' => 50.00, 'vat_rate' => 20.00],
        ]);

        $tradeService->approvePurchaseOrder($order);

        // Cancel the order
        Livewire::actingAs($user)
            ->test('accounting.purchases')
            ->call('cancelOrder', $order->id)
            ->assertSet('messageType', 'success');

        $this->assertEquals('cancelled', $order->fresh()->status);

        // Stock must be restored back to 0
        $this->assertEquals(0, StockBalance::where('user_id', $user->id)->first()->quantity);

        // Payable status must be voided
        $this->assertEquals('voided', Payable::find($order->payable_id)->status);

        // PartyLedgerEntry status must be voided
        $ledgerEntry = PartyLedgerEntry::where('user_id', $user->id)
            ->where('source_type', 'purchase_order')
            ->where('source_key', 'purchase_order_post_' . $order->id)
            ->first();
        $this->assertTrue($ledgerEntry->isVoid());
    }

    public function test_tenant_isolation_on_purchases(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user1 = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $user2 = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $party2 = Party::factory()->create(['user_id' => $user2->id]);
        $party2->roles()->create(['user_id' => $user2->id, 'role' => 'supplier']);

        Livewire::actingAs($user1)
            ->test('accounting.purchases')
            ->set('partyId', $party2->id)
            ->set('documentNumber', 'ALI-BAD')
            ->set('items', [
                ['stock_code' => 'PRD-ANY', 'quantity' => 1, 'unit_price' => 10.00, 'vat_rate' => 20.00],
            ])
            ->call('createPurchaseOrder')
            ->assertSet('messageType', 'error');
    }

    /** @test */
    public function test_purchases_warehouse_tenant_isolation(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user1 = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $user2 = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $party = Party::factory()->create(['user_id' => $user1->id]);
        $party->roles()->create(['user_id' => $user1->id, 'role' => 'supplier']);

        $warehouseOfUser2 = Warehouse::create([
            'user_id' => $user2->id,
            'name' => 'Secret Wh',
            'code' => 'sec-wh',
            'is_default' => true,
            'is_active' => true,
        ]);

        $product = MpProduct::create([
            'user_id' => $user1->id,
            'stock_code' => 'PRD-Y',
            'product_name' => 'Fabric Bolt',
            'barcode' => 'BAR-Y',
        ]);

        Livewire::actingAs($user1)
            ->test('accounting.purchases')
            ->set('partyId', $party->id)
            ->set('documentNumber', 'ALI-002')
            ->set('warehouseId', $warehouseOfUser2->id) // should be rejected as not owned by user1
            ->set('items', [
                ['stock_code' => 'PRD-Y', 'quantity' => 2, 'unit_price' => 50.00, 'vat_rate' => 20.00],
            ])
            ->call('createPurchaseOrder')
            ->assertSet('messageType', 'error')
            ->assertSet('message', 'Seçilen depo bu kullanıcıya ait değil veya aktif değil.');
    }

    /** @test */
    public function test_purchases_filtering_by_status_party_and_date(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party1 = Party::factory()->create(['user_id' => $user->id, 'display_name' => 'Supplier A']);
        $party2 = Party::factory()->create(['user_id' => $user->id, 'display_name' => 'Supplier B']);

        $party1->roles()->create(['user_id' => $user->id, 'role' => 'supplier']);
        $party2->roles()->create(['user_id' => $user->id, 'role' => 'supplier']);

        // Seed orders
        PurchaseOrder::create([
            'user_id' => $user->id,
            'party_id' => $party1->id,
            'document_number' => 'PO-A-DRAFT',
            'order_date' => '2026-07-01',
            'status' => 'draft',
            'total_amount' => 100.00,
        ]);

        PurchaseOrder::create([
            'user_id' => $user->id,
            'party_id' => $party2->id,
            'document_number' => 'PO-B-APPROVED',
            'order_date' => '2026-07-10',
            'status' => 'approved',
            'total_amount' => 200.00,
        ]);

        $lw = Livewire::actingAs($user)->test('accounting.purchases');

        // Test status filtering
        $lw->set('filterStatus', 'approved');
        $this->assertCount(1, $lw->orders);
        $this->assertEquals('PO-B-APPROVED', $lw->orders[0]->document_number);

        // Test party filtering
        $lw->set('filterStatus', '')
           ->set('filterPartyId', $party1->id);
        $this->assertCount(1, $lw->orders);
        $this->assertEquals('PO-A-DRAFT', $lw->orders[0]->document_number);

        // Test date range filtering
        $lw->set('filterPartyId', null)
           ->set('filterDateFrom', '2026-07-05')
           ->set('filterDateTo', '2026-07-15');
        $this->assertCount(1, $lw->orders);
        $this->assertEquals('PO-B-APPROVED', $lw->orders[0]->document_number);
    }

    /** @test */
    public function test_purchases_table_sorting_ignores_invalid_columns(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $lw = Livewire::actingAs($user)->test('accounting.purchases');

        // Initial default sort
        $this->assertEquals('id', $lw->sortColumn);
        $this->assertEquals('desc', $lw->sortDirection);

        // Try invalid sort column (should be ignored)
        $lw->call('sortTable', 'invalid_col');
        $this->assertEquals('id', $lw->sortColumn);
        $this->assertEquals('desc', $lw->sortDirection);

        // Try valid column
        $lw->call('sortTable', 'document_number');
        $this->assertEquals('document_number', $lw->sortColumn);
        $this->assertEquals('asc', $lw->sortDirection);
    }

    /** @test */
    public function test_purchases_column_visibility_toggle(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $lw = Livewire::actingAs($user)->test('accounting.purchases');

        $this->assertContains('document_number', $lw->visibleColumns);

        // Toggle it off
        $lw->call('toggleColumn', 'document_number');
        $this->assertNotContains('document_number', $lw->visibleColumns);

        // Toggle it back on
        $lw->call('toggleColumn', 'document_number');
        $this->assertContains('document_number', $lw->visibleColumns);
    }

    /** @test */
    public function test_purchases_filtering_by_legal_entity_and_warehouse(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $party = Party::factory()->create(['user_id' => $user->id]);
        $party->roles()->create(['user_id' => $user->id, 'role' => 'supplier']);

        $le1 = LegalEntity::create(['user_id' => $user->id, 'name' => 'LE-1', 'is_active' => true, 'tax_number' => '1234567890']);
        $le2 = LegalEntity::create(['user_id' => $user->id, 'name' => 'LE-2', 'is_active' => true, 'tax_number' => '0987654321']);

        $wh1 = Warehouse::create(['user_id' => $user->id, 'name' => 'WH-1', 'code' => 'wh-1', 'is_active' => true]);
        $wh2 = Warehouse::create(['user_id' => $user->id, 'name' => 'WH-2', 'code' => 'wh-2', 'is_active' => true]);

        // Create orders with different entities & warehouses
        PurchaseOrder::create([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'document_number' => 'PO-1',
            'legal_entity_id' => $le1->id,
            'warehouse_id' => $wh1->id,
            'order_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        PurchaseOrder::create([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'document_number' => 'PO-2',
            'legal_entity_id' => $le2->id,
            'warehouse_id' => $wh2->id,
            'order_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        $lw = Livewire::actingAs($user)->test('accounting.purchases');

        // Filter by legal entity
        $lw->set('filterLegalEntityId', $le1->id);
        $this->assertCount(1, $lw->orders);
        $this->assertEquals('PO-1', $lw->orders[0]->document_number);

        // Filter by warehouse
        $lw->set('filterLegalEntityId', null)
           ->set('filterWarehouseId', $wh2->id);
        $this->assertCount(1, $lw->orders);
        $this->assertEquals('PO-2', $lw->orders[0]->document_number);
    }

    /** @test */
    public function test_purchases_search_does_not_leak_other_user_records(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user1 = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $user2 = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $party1 = Party::factory()->create(['user_id' => $user1->id, 'display_name' => 'Supplier-U1']);
        $party2 = Party::factory()->create(['user_id' => $user2->id, 'display_name' => 'Supplier-U2']);

        $party1->roles()->create(['user_id' => $user1->id, 'role' => 'supplier']);
        $party2->roles()->create(['user_id' => $user2->id, 'role' => 'supplier']);

        // U1 order
        PurchaseOrder::create([
            'user_id' => $user1->id,
            'party_id' => $party1->id,
            'document_number' => 'PO-U1',
            'order_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        // U2 order matching the search query
        PurchaseOrder::create([
            'user_id' => $user2->id,
            'party_id' => $party2->id,
            'document_number' => 'PO-U2',
            'order_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        // Act as user1, search for U2 display name or document number.
        // Should not yield any results or leak U2 order.
        $lw = Livewire::actingAs($user1)->test('accounting.purchases')
            ->set('search', 'PO-U2');

        $this->assertCount(0, $lw->orders);
    }

    /** @test */
    public function test_ui_cancellation_reason_saved_on_cancel(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user->id]);
        $party->roles()->create(['user_id' => $user->id, 'role' => 'supplier']);

        (new \Database\Seeders\ChartOfAccountsSeeder())->runForUser($user->id);

        $warehouse = Warehouse::create([
            'user_id' => $user->id,
            'name' => 'Main',
            'code' => 'depo-main',
            'is_default' => true,
            'is_active' => true,
        ]);

        $product = MpProduct::create([
            'user_id' => $user->id,
            'stock_code' => 'PRD-Y',
            'product_name' => 'Fabric Bolt',
            'barcode' => 'BAR-Y',
        ]);

        $tradeService = app(\App\Services\Accounting\TradeService::class);
        $order = $tradeService->createPurchaseOrder([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'document_number' => 'ALI-005',
            'order_date' => now()->toDateString(),
        ], [
            ['stock_code' => 'PRD-Y', 'quantity' => 5, 'unit_price' => 20.00, 'vat_rate' => 20.00],
        ]);

        $tradeService->approvePurchaseOrder($order);

        // Interact through Livewire cancellation flow
        Livewire::actingAs($user)
            ->test('accounting.purchases')
            ->call('confirmCancel', $order->id)
            ->assertSet('showCancelModal', true)
            ->assertSet('cancellingOrderId', $order->id)
            ->set('cancelReason', 'Wrong warehouse selection')
            ->call('cancelOrder')
            ->assertSet('messageType', 'success');

        $order->refresh();
        $this->assertEquals('cancelled', $order->status);
        $this->assertEquals('Wrong warehouse selection', $order->cancel_reason);
        $this->assertNotNull($order->cancelled_at);
    }

    /** @test */
    public function test_inactive_legal_entity_is_not_visible_in_dropdown(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $activeLe = LegalEntity::create(['user_id' => $user->id, 'name' => 'Active Entity', 'is_active' => true, 'tax_number' => '11111']);
        $inactiveLe = LegalEntity::create(['user_id' => $user->id, 'name' => 'Inactive Entity', 'is_active' => false, 'tax_number' => '22222']);

        $lw = Livewire::actingAs($user)->test('accounting.purchases');

        $this->assertCount(1, $lw->legalEntities);
        $this->assertEquals('Active Entity', $lw->legalEntities[0]->name);
    }

    /** @test */
    public function test_inactive_legal_entity_creation_fails(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $party = Party::factory()->create(['user_id' => $user->id]);
        $party->roles()->create(['user_id' => $user->id, 'role' => 'supplier']);

        $inactiveLe = LegalEntity::create(['user_id' => $user->id, 'name' => 'Inactive Entity', 'is_active' => false, 'tax_number' => '22222']);

        $product = MpProduct::create([
            'user_id' => $user->id,
            'stock_code' => 'PRD-X',
            'product_name' => 'Paper Reel',
            'barcode' => 'BAR-PRD-X',
        ]);

        $lw = Livewire::actingAs($user)->test('accounting.purchases')
            ->set('partyId', $party->id)
            ->set('documentNumber', 'ALI-INACTIVELE')
            ->set('orderDate', now()->toDateString())
            ->set('legalEntityId', $inactiveLe->id)
            ->set('items', [
                ['stock_code' => 'PRD-X', 'quantity' => 1, 'unit_price' => 10.00, 'vat_rate' => 20.00]
            ])
            ->call('createPurchaseOrder')
            ->assertSet('messageType', 'error');

        $this->assertStringContainsString('aktif değil', $lw->message);
    }
}
