<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\LegalEntity;
use App\Models\MpProduct;
use App\Models\Party;
use App\Models\PosTerminal;
use App\Models\PosShift;
use App\Models\PosSale;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PosTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private PosTerminal $terminal;
    private Warehouse $warehouse;
    private Account $cashAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        // Seed default charts of accounts
        (new \Database\Seeders\ChartOfAccountsSeeder())->runForUser($this->user->id);

        $this->warehouse = Warehouse::create([
            'user_id' => $this->user->id,
            'name' => 'Main',
            'code' => 'depo-main',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->cashAccount = Account::create([
            'user_id' => $this->user->id,
            'code'    => '100.POS.01',
            'name'    => 'Merkez Kasa',
            'type'    => 'cash',
            'is_active' => true,
            'normal_balance' => 'debit',
            'is_cash_account' => true,
        ]);

        $this->terminal = PosTerminal::create([
            'user_id'      => $this->user->id,
            'name'         => 'Kasa 1',
            'is_active'    => true,
            'warehouse_id' => $this->warehouse->id,
            'account_id'   => $this->cashAccount->id,
        ]);
    }

    /** @test */
    public function test_route_is_blocked_when_accounting_enabled_is_false(): void
    {
        config()->set('marketplace.features.accounting_enabled', false);

        $this->actingAs($this->user)
            ->get(route('accounting.pos'))
            ->assertStatus(404);
    }

    /** @test */
    public function test_page_renders_when_accounting_enabled_is_true(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $this->actingAs($this->user)
            ->get(route('accounting.pos'))
            ->assertStatus(200)
            ->assertSeeLivewire('accounting.pos');
    }

    /** @test */
    public function test_product_search_works_and_excludes_other_users_products(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $otherUser = User::factory()->create(['is_active' => true]);

        // User1 product
        $p1 = MpProduct::create([
            'user_id' => $this->user->id,
            'stock_code' => 'P1-MINE',
            'product_name' => 'My Cola',
            'barcode' => 'BAR-11',
        ]);

        // User2 product
        $p2 = MpProduct::create([
            'user_id' => $otherUser->id,
            'stock_code' => 'P2-OTHER',
            'product_name' => 'Other User Cola',
            'barcode' => 'BAR-22',
        ]);

        $lw = Livewire::actingAs($this->user)
            ->test('accounting.pos')
            ->set('cartSearch', 'Cola');

        // Only p1 should be visible
        $this->assertCount(1, $lw->products);
        $this->assertEquals('P1-MINE', $lw->products[0]->stock_code);
    }

    /** @test */
    public function test_cart_totals_calculated_correctly(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $p = MpProduct::create([
            'user_id' => $this->user->id,
            'stock_code' => 'P-CALC',
            'product_name' => 'Pizza Slice',
            'barcode' => 'BAR-PIZZA',
            'sale_price' => 50.00,
        ]);

        $lw = Livewire::actingAs($this->user)
            ->test('accounting.pos')
            ->call('addToCart', 'P-CALC')
            ->call('updateQuantity', 0, 2)
            ->call('updateDiscountRate', 0, 10.00); // 10% discount on 100 TRY subtotal = 90 TRY matrah. VAT(20%) = 18.00 TRY. Total = 108.00 TRY.

        $this->assertEquals(100.00, $lw->subtotal);
        $this->assertEquals(10.00, $lw->discountTotal);
        $this->assertEquals(18.00, $lw->vatTotal);
        $this->assertEquals(108.00, $lw->total);
    }

    /** @test */
    public function test_checkout_clears_cart_and_shows_in_recent_sales(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $p = MpProduct::create([
            'user_id' => $this->user->id,
            'stock_code' => 'P-CHECKOUT',
            'product_name' => 'Coffee Cup',
            'barcode' => 'BAR-COFFEE',
            'sale_price' => 10.00,
        ]);

        StockBalance::create([
            'user_id' => $this->user->id,
            'warehouse_id' => $this->warehouse->id,
            'stock_code' => 'P-CHECKOUT',
            'quantity' => 10,
        ]);

        Livewire::actingAs($this->user)
            ->test('accounting.pos')
            ->call('selectTerminal', $this->terminal->id)
            ->set('shiftOpeningBalance', 50.00)
            ->call('openShift')
            ->call('addToCart', 'P-CHECKOUT')
            ->call('checkout')
            ->assertSet('messageType', 'success')
            ->assertSet('cart', []); // Cart cleared

        $this->assertCount(1, PosSale::all());
    }

    /** @test */
    public function test_column_toggle_and_table_sort_functions(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $lw = Livewire::actingAs($this->user)
            ->test('accounting.pos')
            ->call('selectTerminal', $this->terminal->id);

        // Toggle columns
        $this->assertContains('amount', $lw->visibleColumns);
        $lw->call('toggleColumn', 'amount');
        $this->assertNotContains('amount', $lw->visibleColumns);

        // Sort table whitelist
        $this->assertEquals('id', $lw->sortColumn);
        $lw->call('sortTable', 'invalid_col');
        $this->assertEquals('id', $lw->sortColumn);

        $lw->call('sortTable', 'amount');
        $this->assertEquals('amount', $lw->sortColumn);
        $this->assertEquals('asc', $lw->sortDirection);
    }

    /** @test */
    public function test_mobile_critical_actions_render_correctly(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        // Renders blade view without crashing
        $this->actingAs($this->user)
            ->get(route('accounting.pos'))
            ->assertSee('Perakende Checkout')
            ->assertSee('Kasa Vardiyası Kontrolü');
    }
}
