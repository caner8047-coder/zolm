<?php

namespace Tests\Feature;

use App\Models\MpProduct;
use App\Models\Party;
use App\Models\SalesOrder;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Warehouse;
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

    public function test_creating_sales_order_draft(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user->id]);
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
                ['stock_code' => 'PRD-X', 'quantity' => 2, 'unit_price' => 100.00, 'vat_rate' => 20.00],
            ])
            ->call('createSalesOrder')
            ->assertSet('messageType', 'success');

        $this->assertDatabaseHas('sales_orders', [
            'user_id' => $user->id,
            'party_id' => $party->id,
            'document_number' => 'FAT-001',
            'status' => 'draft',
            'total_amount' => 240.00, // 2 * 100 * 1.20 = 240
        ]);
    }

    public function test_approving_sales_order_processes_stock_and_receivables(): void
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
            'stock_code' => 'PRD-X',
            'product_name' => 'Fancy Shirt',
            'barcode' => 'BAR-X',
        ]);

        // Place 10 in stock
        StockBalance::create([
            'user_id' => $user->id,
            'warehouse_id' => $warehouse->id,
            'stock_code' => 'PRD-X',
            'quantity' => 10,
        ]);

        // Create draft sales order
        $tradeService = app(\App\Services\Accounting\TradeService::class);
        $order = $tradeService->createSalesOrder([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'document_number' => 'FAT-001',
            'order_date' => now()->toDateString(),
        ], [
            ['stock_code' => 'PRD-X', 'quantity' => 3, 'unit_price' => 100.00, 'vat_rate' => 20.00],
        ]);

        Livewire::actingAs($user)
            ->test('accounting.sales')
            ->call('approveOrder', $order->id)
            ->assertSet('messageType', 'success');

        $this->assertEquals('approved', $order->fresh()->status);
        $this->assertDatabaseHas('receivables', [
            'user_id' => $user->id,
            'party_id' => $party->id,
            'amount' => 360.00,
        ]);

        // Stock decreased by 3 (10 - 3 = 7)
        $this->assertEquals(7, StockBalance::where('user_id', $user->id)->first()->quantity);
    }

    public function test_approving_with_insufficient_stock_fails(): void
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
            'stock_code' => 'PRD-X',
            'product_name' => 'Fancy Shirt',
            'barcode' => 'BAR-X',
        ]);

        // Place only 2 in stock
        StockBalance::create([
            'user_id' => $user->id,
            'warehouse_id' => $warehouse->id,
            'stock_code' => 'PRD-X',
            'quantity' => 2,
        ]);

        // Create draft sales order requiring 5
        $tradeService = app(\App\Services\Accounting\TradeService::class);
        $order = $tradeService->createSalesOrder([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'document_number' => 'FAT-001',
            'order_date' => now()->toDateString(),
        ], [
            ['stock_code' => 'PRD-X', 'quantity' => 5, 'unit_price' => 100.00, 'vat_rate' => 20.00],
        ]);

        Livewire::actingAs($user)
            ->test('accounting.sales')
            ->call('approveOrder', $order->id)
            ->assertSet('messageType', 'error');

        $this->assertEquals('draft', $order->fresh()->status);
    }

    public function test_tenant_isolation_on_sales(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user1 = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $user2 = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $party2 = Party::factory()->create(['user_id' => $user2->id]);

        // Attempting to create a sales order draft bound to User 2's party while logged in as User 1 should be blocked
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
}
