<?php

namespace Tests\Feature;

use App\Models\PosShift;
use App\Models\PosTerminal;
use App\Models\Party;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\PosService;
use App\Services\Accounting\StockService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private PosTerminal $terminal;
    private PosService $service;
    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['is_active' => true]);

        $seeder = new ChartOfAccountsSeeder();
        $seeder->runForUser($this->user->id);

        $this->warehouse = app(StockService::class)->createWarehouse($this->user->id, 'Merkez Depo', 'depo-merkez', true);

        $this->terminal = PosTerminal::create([
            'user_id' => $this->user->id,
            'name' => 'Kasa 1',
            'is_active' => true,
        ]);

        $this->service = app(PosService::class);
    }

    public function test_open_and_close_shift(): void
    {
        $shift = $this->service->openShift($this->terminal, 150.00);

        $this->assertEquals('open', $shift->status);
        $this->assertEquals(150.00, (float) $shift->opening_balance);

        // Cannot open duplicate open shift
        $this->expectException(\InvalidArgumentException::class);
        $this->service->openShift($this->terminal, 200.00);

        $closed = $this->service->closeShift($shift, 500.00);
        $this->assertEquals('closed', $closed->status);
        $this->assertEquals(500.00, (float) $closed->closing_balance);
    }

    public function test_pos_sale_triggers_complete_order_stock_and_payment_flow(): void
    {
        // 1. Initial stock: 10 items
        app(StockService::class)->recordMovement([
            'user_id'       => $this->user->id,
            'warehouse_id'  => $this->warehouse->id,
            'stock_code'    => 'POS-BARCODE-01',
            'movement_type' => 'in_adjustment',
            'direction'     => 'in',
            'quantity'      => 10,
        ]);

        $shift = $this->service->openShift($this->terminal, 100.00);

        // 2. Perform POS Checkout (Cart: 1 item of qty 2, price 50 TRY each)
        $posSale = $this->service->recordPosSale($shift, [
            'payment_method' => 'credit_card',
        ], [
            ['stock_code' => 'POS-BARCODE-01', 'quantity' => 2, 'unit_price' => 50.00, 'vat_rate' => 0.00], // Total = 100.00
        ]);

        $this->assertNotNull($posSale);
        $this->assertEquals(100.00, (float) $posSale->amount);

        // Verify Perakende Müşteri Party was resolved
        $party = Party::where('user_id', $this->user->id)->where('display_name', 'Perakende Müşteri')->first();
        $this->assertNotNull($party);

        // Verify stock reduced: 10 - 2 = 8
        $this->assertEquals(8, app(StockService::class)->getStockLevel($this->user->id, 'POS-BARCODE-01', $this->warehouse->id));

        // Verify underlying order is approved
        $order = $posSale->salesOrder;
        $this->assertEquals('approved', $order->status);

        // Verify Receivable is created and fully PAID (status = 'paid', paid_amount = 100.00)
        $receivable = $order->receivable;
        $this->assertEquals('paid', $receivable->status);
        $this->assertEquals(100.00, (float) $receivable->paid_amount);

        // Verify Collection exists for 100.00
        $this->assertDatabaseHas('collections', [
            'user_id' => $this->user->id,
            'amount' => 100.00,
            'payment_method' => 'credit_card',
        ]);
    }

    public function test_checkout_rejects_other_user_party(): void
    {
        $otherUser = User::factory()->create(['is_active' => true]);
        $otherParty = Party::factory()->create([
            'user_id' => $otherUser->id,
            'display_name' => 'Other User Customer',
        ]);

        $shift = $this->service->openShift($this->terminal, 100.00);

        // Expect Exception because party belongs to other user
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        try {
            $this->service->recordPosSale($shift, [
                'payment_method' => 'credit_card',
                'party_id' => $otherParty->id,
            ], [
                ['stock_code' => 'POS-BARCODE-01', 'quantity' => 2, 'unit_price' => 50.00, 'vat_rate' => 0.00],
            ]);
        } finally {
            // Verify no role was added to the other party
            $this->assertFalse($otherParty->roles()->where('role', 'customer')->exists());

            // Verify no sales order, stock movement, collection or journal entry was created
            $this->assertEquals(0, \App\Models\SalesOrder::count());
            $this->assertEquals(0, \App\Models\StockMovement::count());
            $this->assertEquals(0, \App\Models\Collection::count());
            $this->assertEquals(0, \App\Models\JournalEntry::count());
        }
    }
}
