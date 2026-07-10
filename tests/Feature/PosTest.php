<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\MpProduct;
use App\Models\Party;
use App\Models\PosTerminal;
use App\Models\PosShift;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PosTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_is_blocked_when_accounting_enabled_is_false(): void
    {
        config()->set('marketplace.features.accounting_enabled', false);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $this->actingAs($user)
            ->get(route('accounting.pos'))
            ->assertStatus(404);
    }

    public function test_page_renders_when_accounting_enabled_is_true(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $this->actingAs($user)
            ->get(route('accounting.pos'))
            ->assertStatus(200)
            ->assertSeeLivewire('accounting.pos');
    }

    public function test_opening_and_closing_shift(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $terminal = PosTerminal::create(['user_id' => $user->id, 'name' => 'Kasa 1', 'is_active' => true]);

        Livewire::actingAs($user)
            ->test('accounting.pos')
            ->call('selectTerminal', $terminal->id)
            ->set('shiftOpeningBalance', 150.00)
            ->call('openShift')
            ->assertSet('messageType', 'success');

        $shift = PosShift::where('user_id', $user->id)->first();
        $this->assertEquals('open', $shift->status);
        $this->assertEquals(150.00, $shift->opening_balance);

        Livewire::actingAs($user)
            ->test('accounting.pos')
            ->call('selectTerminal', $terminal->id)
            ->set('shiftClosingBalance', 300.00)
            ->call('closeShift')
            ->assertSet('messageType', 'success');

        $this->assertEquals('closed', $shift->fresh()->status);
        $this->assertEquals(300.00, $shift->fresh()->closing_balance);
    }

    public function test_checkout_pos_sale_updates_stock_and_kasa_balance(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $terminal = PosTerminal::create(['user_id' => $user->id, 'name' => 'Kasa 1', 'is_active' => true]);

        // Seed default charts of accounts
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
            'stock_code' => 'PRD-POS',
            'product_name' => 'Cola Can',
            'barcode' => 'BAR-POS',
            'sale_price' => 15.00,
        ]);

        // Place 20 in stock
        StockBalance::create([
            'user_id' => $user->id,
            'warehouse_id' => $warehouse->id,
            'stock_code' => 'PRD-POS',
            'quantity' => 20,
        ]);

        Livewire::actingAs($user)
            ->test('accounting.pos')
            ->call('selectTerminal', $terminal->id)
            ->set('shiftOpeningBalance', 150.00)
            ->call('openShift')
            ->call('addToCart', 'PRD-POS')
            ->set('paymentMethod', 'cash')
            ->call('checkout')
            ->assertSet('messageType', 'success');

        // Stock decreased by 1 (20 - 1 = 19)
        $this->assertEquals(19, StockBalance::where('user_id', $user->id)->first()->quantity);

        // Bank account (102 code) increased by sales amount (15 * 1.20 VAT = 18.00)
        $bank = Account::where('user_id', $user->id)->where('is_bank_account', true)->first();
        $this->assertEquals(18.00, $bank->balance());
    }

    public function test_checkout_with_insufficient_stock_fails(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $terminal = PosTerminal::create(['user_id' => $user->id, 'name' => 'Kasa 1', 'is_active' => true]);

        // Seed default charts of accounts
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
            'stock_code' => 'PRD-POS',
            'product_name' => 'Cola Can',
            'barcode' => 'BAR-POS',
            'sale_price' => 15.00,
        ]);

        // Place only 1 in stock
        StockBalance::create([
            'user_id' => $user->id,
            'warehouse_id' => $warehouse->id,
            'stock_code' => 'PRD-POS',
            'quantity' => 1,
        ]);

        Livewire::actingAs($user)
            ->test('accounting.pos')
            ->call('selectTerminal', $terminal->id)
            ->set('shiftOpeningBalance', 150.00)
            ->call('openShift')
            ->call('addToCart', 'PRD-POS')
            ->call('updateQuantity', 0, 5) // Try to checkout 5
            ->call('checkout')
            ->assertSet('messageType', 'error');

        // Stock quantity should remain 1
        $this->assertEquals(1, StockBalance::where('user_id', $user->id)->first()->quantity);
    }

    public function test_tenant_isolation_on_pos(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user1 = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $user2 = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        // Terminal belonging to User 2
        $terminal2 = PosTerminal::create(['user_id' => $user2->id, 'name' => 'User2 Kasa', 'is_active' => true]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        // User 1 attempting to select User 2's terminal should be blocked
        Livewire::actingAs($user1)
            ->test('accounting.pos')
            ->call('selectTerminal', $terminal2->id);
    }
}
