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
}
