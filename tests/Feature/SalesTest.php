<?php

namespace Tests\Feature;

use App\Models\MpProduct;
use App\Models\Party;
use App\Models\SalesOrder;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Receivable;
use App\Models\PartyLedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SalesTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_is_blocked_when_accounting_enabled_is_false(): void
    {
        config()->set('marketplace.features.accounting_enabled', false);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $this->actingAs($user)
            ->get(route('accounting.sales'))
            ->assertStatus(404);
    }

    public function test_page_renders_when_accounting_enabled_is_true(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $this->actingAs($user)
            ->get(route('accounting.sales'))
            ->assertStatus(200)
            ->assertSeeLivewire('accounting.sales');
    }

    public function test_creating_sales_order_draft_with_discount(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user->id]);
        $party->roles()->create(['user_id' => $user->id, 'role' => 'customer']);

        $product = MpProduct::create([
            'user_id' => $user->id,
            'stock_code' => 'PRD-X',
            'product_name' => 'Fancy Shirt',
            'barcode' => 'BAR-X',
        ]);

        Livewire::actingAs($user)
            ->test('accounting.sales')
            ->set('partyId', $party->id)
            ->set('documentNumber', 'FAT-001')
            ->set('items', [
                [
                    'stock_code' => 'PRD-X',
                    'quantity' => 2,
                    'unit_price' => 100.00,
                    'vat_rate' => 20.00,
                    'discount_rate' => 10.00 // 10% discount
                ],
            ])
            ->call('createSalesOrder')
            ->assertSet('messageType', 'success');

        // Calculations:
        // Base = 2 * 100 = 200
        // Discount = 200 * 0.10 = 20
        // Before VAT = 180
        // VAT = 180 * 0.20 = 36
        // Total = 180 + 36 = 216
        $this->assertDatabaseHas('sales_orders', [
            'user_id' => $user->id,
            'party_id' => $party->id,
            'document_number' => 'FAT-001',
            'status' => 'draft',
            'total_amount' => 216.00,
            'discount_amount' => 20.00,
        ]);

        $this->assertDatabaseHas('sales_order_items', [
            'stock_code' => 'PRD-X',
            'quantity' => 2,
            'unit_price' => 100.00,
            'vat_rate' => 20.00,
            'discount_rate' => 10.00,
            'discount_amount' => 20.00,
            'total_amount' => 216.00,
        ]);
    }

    public function test_approving_sales_order_processes_stock_and_receivables(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user->id]);
        $party->roles()->create(['user_id' => $user->id, 'role' => 'customer']);

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
            'stock_code' => 'PRD-X',
            'product_name' => 'Fancy Shirt',
            'barcode' => 'BAR-X',
        ]);

        StockBalance::create([
            'user_id' => $user->id,
            'warehouse_id' => $warehouse->id,
            'stock_code' => 'PRD-X',
            'quantity' => 10,
        ]);

        $tradeService = app(\App\Services\Accounting\TradeService::class);
        $order = $tradeService->createSalesOrder([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'document_number' => 'FAT-001',
            'order_date' => now()->toDateString(),
        ], [
            [
                'stock_code' => 'PRD-X',
                'quantity' => 3,
                'unit_price' => 100.00,
                'vat_rate' => 20.00,
                'discount_rate' => 10.00
            ],
        ]);

        // Base = 300. Discount = 30. VAT = 54. Total = 324.
        Livewire::actingAs($user)
            ->test('accounting.sales')
            ->call('approveOrder', $order->id)
            ->assertSet('messageType', 'success');

        $this->assertEquals('approved', $order->fresh()->status);

        // Check Receivable
        $this->assertDatabaseHas('receivables', [
            'user_id' => $user->id,
            'party_id' => $party->id,
            'amount' => 324.00,
            'status' => 'open',
        ]);

        // Check PartyLedgerEntry
        $this->assertDatabaseHas('party_ledger_entries', [
            'user_id' => $user->id,
            'party_id' => $party->id,
            'debit_amount' => 324.00,
            'credit_amount' => 0.00,
            'source_type' => 'sales_order',
            'source_key' => 'sales_order_post_' . $order->id,
        ]);

        // Check stock
        $this->assertEquals(7, StockBalance::where('user_id', $user->id)->first()->quantity);
    }

    public function test_approving_sales_order_is_idempotent(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user->id]);
        $party->roles()->create(['user_id' => $user->id, 'role' => 'customer']);

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
            'stock_code' => 'PRD-X',
            'product_name' => 'Fancy Shirt',
            'barcode' => 'BAR-X',
        ]);

        StockBalance::create([
            'user_id' => $user->id,
            'warehouse_id' => $warehouse->id,
            'stock_code' => 'PRD-X',
            'quantity' => 10,
        ]);

        $tradeService = app(\App\Services\Accounting\TradeService::class);
        $order = $tradeService->createSalesOrder([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'document_number' => 'FAT-001',
            'order_date' => now()->toDateString(),
        ], [
            ['stock_code' => 'PRD-X', 'quantity' => 2, 'unit_price' => 100.00, 'vat_rate' => 20.00],
        ]);

        $tradeService->approveSalesOrder($order);
        $count = PartyLedgerEntry::where('user_id', $user->id)->count();

        // Try second time (it should raise exception or keep same count because order status is approved, and service uses source_key idempotency)
        $this->expectException(\InvalidArgumentException::class);
        $tradeService->approveSalesOrder($order);

        $this->assertEquals($count, PartyLedgerEntry::where('user_id', $user->id)->count());
    }

    public function test_cancelling_sales_order_reverts_stock_and_balances(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user->id]);
        $party->roles()->create(['user_id' => $user->id, 'role' => 'customer']);

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
            'stock_code' => 'PRD-X',
            'product_name' => 'Fancy Shirt',
            'barcode' => 'BAR-X',
        ]);

        StockBalance::create([
            'user_id' => $user->id,
            'warehouse_id' => $warehouse->id,
            'stock_code' => 'PRD-X',
            'quantity' => 10,
        ]);

        $tradeService = app(\App\Services\Accounting\TradeService::class);
        $order = $tradeService->createSalesOrder([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'document_number' => 'FAT-001',
            'order_date' => now()->toDateString(),
        ], [
            ['stock_code' => 'PRD-X', 'quantity' => 3, 'unit_price' => 100.00, 'vat_rate' => 20.00],
        ]);

        $tradeService->approveSalesOrder($order);

        // Cancel the order
        Livewire::actingAs($user)
            ->test('accounting.sales')
            ->call('confirmCancel', $order->id)
            ->set('cancelReason', 'Müşteri talebi')
            ->call('cancelOrder')
            ->assertSet('messageType', 'success');

        $this->assertEquals('cancelled', $order->fresh()->status);

        // Stock must be restored back to 10
        $this->assertEquals(10, StockBalance::where('user_id', $user->id)->first()->quantity);

        // Receivable status must be voided
        $this->assertEquals('voided', Receivable::find($order->receivable_id)->status);

        // PartyLedgerEntry status must be voided
        $ledgerEntry = PartyLedgerEntry::where('user_id', $user->id)
            ->where('source_type', 'sales_order')
            ->where('source_key', 'sales_order_post_' . $order->id)
            ->first();
        $this->assertTrue($ledgerEntry->isVoid());
    }

    public function test_tenant_isolation_on_sales(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user1 = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $user2 = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $party2 = Party::factory()->create(['user_id' => $user2->id]);
        $party2->roles()->create(['user_id' => $user2->id, 'role' => 'customer']);

        Livewire::actingAs($user1)
            ->test('accounting.sales')
            ->set('partyId', $party2->id)
            ->set('documentNumber', 'FAT-BAD')
            ->set('items', [
                ['stock_code' => 'PRD-ANY', 'quantity' => 1, 'unit_price' => 10.00, 'vat_rate' => 20.00],
            ])
            ->call('createSalesOrder')
            ->assertSet('messageType', 'error');
    }

    public function test_cannot_create_sales_order_if_party_not_customer(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user->id]);
        $party->roles()->create(['user_id' => $user->id, 'role' => 'supplier']);

        Livewire::actingAs($user)
            ->test('accounting.sales')
            ->set('partyId', $party->id)
            ->set('documentNumber', 'FAT-NO-CUST')
            ->set('items', [
                ['stock_code' => 'PRD-ANY', 'quantity' => 1, 'unit_price' => 10.00, 'vat_rate' => 20.00],
            ])
            ->call('createSalesOrder')
            ->assertSet('messageType', 'error');
    }

    public function test_cannot_cancel_sales_order_if_already_paid(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user->id]);
        $party->roles()->create(['user_id' => $user->id, 'role' => 'customer']);

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
            'stock_code' => 'PRD-X',
            'product_name' => 'Fancy Shirt',
            'barcode' => 'BAR-X',
        ]);

        StockBalance::create([
            'user_id' => $user->id,
            'warehouse_id' => $warehouse->id,
            'stock_code' => 'PRD-X',
            'quantity' => 10,
        ]);

        $tradeService = app(\App\Services\Accounting\TradeService::class);
        $order = $tradeService->createSalesOrder([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'document_number' => 'FAT-001',
            'order_date' => now()->toDateString(),
        ], [
            ['stock_code' => 'PRD-X', 'quantity' => 3, 'unit_price' => 100.00, 'vat_rate' => 20.00],
        ]);

        $tradeService->approveSalesOrder($order);

        // Mark receivable as paid/partially paid
        $receivable = Receivable::find($order->receivable_id);
        $receivable->update([
            'paid_amount' => 100.00,
            'status' => 'partially_paid'
        ]);

        // Attempt cancel
        Livewire::actingAs($user)
            ->test('accounting.sales')
            ->call('confirmCancel', $order->id)
            ->call('cancelOrder')
            ->assertSet('messageType', 'error');

        $this->assertEquals('approved', $order->fresh()->status);
    }

    public function test_sorting_and_visibility(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $component = Livewire::actingAs($user)
            ->test('accounting.sales');

        $component->assertSet('sortColumn', 'id')
            ->assertSet('sortDirection', 'desc')
            ->call('sortTable', 'total_amount')
            ->assertSet('sortColumn', 'total_amount')
            ->assertSet('sortDirection', 'asc')
            ->call('sortTable', 'total_amount')
            ->assertSet('sortDirection', 'desc');

        // Column toggles using PHP assert
        $visible = $component->get('visibleColumns');
        $this->assertContains('document_number', $visible);

        $component->call('toggleColumn', 'document_number');
        $visible = $component->get('visibleColumns');
        $this->assertNotContains('document_number', $visible);

        $component->call('toggleColumn', 'document_number');
        $visible = $component->get('visibleColumns');
        $this->assertContains('document_number', $visible);
    }

    public function test_cannot_cancel_with_allocation(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user->id]);
        $party->roles()->create(['user_id' => $user->id, 'role' => 'customer']);

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
            'stock_code' => 'PRD-X',
            'product_name' => 'Fancy Shirt',
            'barcode' => 'BAR-X',
        ]);

        StockBalance::create([
            'user_id' => $user->id,
            'warehouse_id' => $warehouse->id,
            'stock_code' => 'PRD-X',
            'quantity' => 10,
        ]);

        $tradeService = app(\App\Services\Accounting\TradeService::class);
        $order = $tradeService->createSalesOrder([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'document_number' => 'FAT-ALL-1',
            'order_date' => now()->toDateString(),
        ], [
            ['stock_code' => 'PRD-X', 'quantity' => 3, 'unit_price' => 100.00, 'vat_rate' => 20.00],
        ]);

        $tradeService->approveSalesOrder($order);

        // Seed bank account and collection first using CashBankService to satisfy database schema requirements
        $bankAcc = app(\App\Services\Accounting\CashBankService::class)->createBankAccount($user->id, [
            'bank_name'      => 'Garanti',
            'account_number' => '123',
            'currency_code'  => 'TRY',
        ]);
        $collection = \App\Models\Collection::create([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'account_id' => $bankAcc->account->id,
            'amount' => 50.00,
            'collection_date' => now()->toDateString(),
            'payment_method' => 'bank',
            'status' => 'posted',
        ]);

        // Create a fake allocation
        \App\Models\ReceivableAllocation::create([
            'user_id' => $user->id,
            'receivable_id' => $order->receivable_id,
            'collection_id' => $collection->id,
            'amount' => 50.00,
        ]);

        // Attempt cancel
        Livewire::actingAs($user)
            ->test('accounting.sales')
            ->call('confirmCancel', $order->id)
            ->call('cancelOrder')
            ->assertSet('messageType', 'error');

        $this->assertEquals('approved', $order->fresh()->status);
    }

    public function test_ui_search_does_not_leak_other_user_orders(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user1 = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $user2 = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $party1 = Party::factory()->create(['user_id' => $user1->id]);
        $party1->roles()->create(['user_id' => $user1->id, 'role' => 'customer']);

        $party2 = Party::factory()->create(['user_id' => $user2->id]);
        $party2->roles()->create(['user_id' => $user2->id, 'role' => 'customer']);

        // Seed PRD-ANY for both users to allow order creation
        MpProduct::create([
            'user_id' => $user1->id,
            'stock_code' => 'PRD-ANY',
            'product_name' => 'Any Product U1',
            'barcode' => 'BAR-1',
        ]);
        MpProduct::create([
            'user_id' => $user2->id,
            'stock_code' => 'PRD-ANY',
            'product_name' => 'Any Product U2',
            'barcode' => 'BAR-2',
        ]);

        $tradeService = app(\App\Services\Accounting\TradeService::class);

        // Order for User 1
        $order1 = $tradeService->createSalesOrder([
            'user_id' => $user1->id,
            'party_id' => $party1->id,
            'document_number' => 'FAT-U1',
            'order_date' => now()->toDateString(),
        ], [
            ['stock_code' => 'PRD-ANY', 'quantity' => 1, 'unit_price' => 10.00, 'vat_rate' => 0.00],
        ]);

        // Order for User 2
        $order2 = $tradeService->createSalesOrder([
            'user_id' => $user2->id,
            'party_id' => $party2->id,
            'document_number' => 'FAT-U2',
            'order_date' => now()->toDateString(),
        ], [
            ['stock_code' => 'PRD-ANY', 'quantity' => 1, 'unit_price' => 10.00, 'vat_rate' => 0.00],
        ]);

        // Accessing UI as User 1
        $component = Livewire::actingAs($user1)
            ->test('accounting.sales')
            ->set('search', 'FAT');

        $orders = $component->instance()->orders;
        $ids = collect($orders->items())->pluck('id')->all();
        $this->assertContains($order1->id, $ids);
        $this->assertNotContains($order2->id, $ids);
    }

    public function test_sort_table_ignores_non_whitelisted_column(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        Livewire::actingAs($user)
            ->test('accounting.sales')
            ->assertSet('sortColumn', 'id')
            ->call('sortTable', 'non_existent_column_injection')
            ->assertSet('sortColumn', 'id'); // Should remain 'id'
    }
}
