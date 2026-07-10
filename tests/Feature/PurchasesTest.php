<?php

namespace Tests\Feature;

use App\Models\MpProduct;
use App\Models\Party;
use App\Models\PurchaseOrder;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Warehouse;
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

    public function test_creating_purchase_order_draft(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user->id]);
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
                ['stock_code' => 'PRD-Y', 'quantity' => 5, 'unit_price' => 50.00, 'vat_rate' => 20.00],
            ])
            ->call('createPurchaseOrder')
            ->assertSet('messageType', 'success');

        $this->assertDatabaseHas('purchase_orders', [
            'user_id' => $user->id,
            'party_id' => $party->id,
            'document_number' => 'ALI-001',
            'status' => 'draft',
            'total_amount' => 300.00, // 5 * 50 * 1.20 = 300
        ]);
    }

    public function test_approving_purchase_order_processes_stock_and_payables(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user->id]);
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
            ['stock_code' => 'PRD-Y', 'quantity' => 10, 'unit_price' => 50.00, 'vat_rate' => 20.00],
        ]);

        Livewire::actingAs($user)
            ->test('accounting.purchases')
            ->call('approveOrder', $order->id)
            ->assertSet('messageType', 'success');

        $this->assertEquals('approved', $order->fresh()->status);
        $this->assertDatabaseHas('payables', [
            'user_id' => $user->id,
            'party_id' => $party->id,
            'amount' => 600.00,
        ]);

        // Stock increased by 10
        $this->assertEquals(10, StockBalance::where('user_id', $user->id)->first()->quantity);
    }

    public function test_tenant_isolation_on_purchases(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user1 = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $user2 = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $party2 = Party::factory()->create(['user_id' => $user2->id]);

        // Attempting to create a purchase order draft bound to User 2's party while logged in as User 1 should be blocked
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
