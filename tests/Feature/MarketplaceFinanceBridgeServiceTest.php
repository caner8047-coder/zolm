<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\OrderFinancialEvent;
use App\Models\Party;
use App\Models\User;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use App\Services\Accounting\MarketplaceFinanceBridgeService;
use App\Services\Accounting\StockService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceFinanceBridgeServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private MarketplaceStore $store;
    private LegalEntity $legalEntity;
    private MarketplaceFinanceBridgeService $bridge;
    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['is_active' => true]);

        $seeder = new ChartOfAccountsSeeder();
        $seeder->runForUser($this->user->id);

        $this->legalEntity = LegalEntity::create([
            'user_id' => $this->user->id,
            'name' => 'ZOLM LTD',
            'tax_number' => '1234567890',
        ]);

        $this->store = MarketplaceStore::create([
            'user_id' => $this->user->id,
            'legal_entity_id' => $this->legalEntity->id,
            'store_name' => 'Trendyol Store',
            'store_code' => 'trendyol-store-01',
            'marketplace' => 'trendyol',
            'status' => 'active',
        ]);

        $this->warehouse = app(StockService::class)->createWarehouse($this->user->id, 'Merkez Depo', 'depo-merkez', true);

        // Enable feature flags
        config()->set('marketplace.features.party_core_enabled', true);
        config()->set('marketplace.features.accounting_enabled', true);

        $this->bridge = app(MarketplaceFinanceBridgeService::class);
    }

    public function test_bridge_order_resolves_party_creates_sales_order_and_reduces_inventory(): void
    {
        // 1. Setup stock level: 30 items
        app(StockService::class)->recordMovement([
            'user_id'       => $this->user->id,
            'warehouse_id'  => $this->warehouse->id,
            'stock_code'    => 'M-PROD-01',
            'movement_type' => 'in_adjustment',
            'direction'     => 'in',
            'quantity'      => 30,
        ]);

        // 2. Create Channel Order
        $order = ChannelOrder::create([
            'store_id' => $this->store->id,
            'legal_entity_id' => $this->legalEntity->id,
            'external_order_id' => 'ty-999',
            'order_number' => 'TY999888',
            'order_status' => 'approved',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@doe.com',
            'customer_phone' => '05554443322',
            'currency' => 'TRY',
            'ordered_at' => now(),
        ]);

        ChannelOrderItem::create([
            'store_id' => $this->store->id,
            'channel_order_id' => $order->id,
            'external_line_id' => 'ty-item-1',
            'stock_code' => 'M-PROD-01',
            'quantity' => 2,
            'gross_amount' => 100.00,
            'vat_rate' => 20.00,
        ]);

        // 3. Bridge the Order
        $salesOrder = $this->bridge->bridgeOrder($order, true);

        $this->assertNotNull($salesOrder);
        $this->assertEquals('approved', $salesOrder->status);
        $this->assertEquals(240.00, (float) $salesOrder->total_amount); // 200 * 1.2 = 240

        // Verify Customer Party has been resolved/created
        $party = Party::where('user_id', $this->user->id)->where('display_name', 'John Doe')->first();
        $this->assertNotNull($party);
        $this->assertEquals($party->id, $salesOrder->party_id);

        // Verify stock reduced: 30 - 2 = 28
        $this->assertEquals(28, app(StockService::class)->getStockLevel($this->user->id, 'M-PROD-01', $this->warehouse->id));

        // Verify General Ledger Invoice Entry
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $salesOrder->receivable->journal_entry_id,
            'debit_amount' => 240.00,
        ]);
    }

    public function test_bridge_financial_event_posts_commission_expenses(): void
    {
        $event = OrderFinancialEvent::create([
            'store_id' => $this->store->id,
            'legal_entity_id' => $this->legalEntity->id,
            'event_source' => 'trendyol',
            'event_type' => 'commission',
            'amount' => 45.00,
            'event_date' => now(),
            'currency' => 'TRY',
            'direction' => 'out',
            'status' => 'settled',
        ]);

        $journal = $this->bridge->bridgeFinancialEvent($event);

        $this->assertNotNull($journal);
        $this->assertEquals('posted', $journal->status);

        // Debit 760 (Commission/Pazarlama Gideri) 45.00, Credit 120 (Alıcılar) 45.00
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $journal->id,
            'account_id' => Account::where('user_id', $this->user->id)->where('code', '760')->first()->id,
            'debit_amount' => 45.00,
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $journal->id,
            'account_id' => Account::where('user_id', $this->user->id)->where('is_ar_account', true)->first()->id,
            'credit_amount' => 45.00,
        ]);
    }

    public function test_bridge_financial_event_posts_settlement_payouts(): void
    {
        $event = OrderFinancialEvent::create([
            'store_id' => $this->store->id,
            'legal_entity_id' => $this->legalEntity->id,
            'event_source' => 'trendyol',
            'event_type' => 'payout',
            'amount' => 950.00,
            'event_date' => now(),
            'currency' => 'TRY',
            'direction' => 'in',
            'status' => 'settled',
        ]);

        $journal = $this->bridge->bridgeFinancialEvent($event);

        $this->assertNotNull($journal);
        $this->assertEquals('posted', $journal->status);

        // Debit 102 (Bankalar) 950.00, Credit 120 (Alıcılar) 950.00
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $journal->id,
            'account_id' => Account::where('user_id', $this->user->id)->where('is_bank_account', true)->first()->id,
            'debit_amount' => 950.00,
        ]);
    }

    public function test_bridge_financial_event_isolates_by_user_id(): void
    {
        $otherUser = User::factory()->create();

        $otherLegalEntity = LegalEntity::create([
            'user_id' => $otherUser->id,
            'name' => 'Other LTD',
            'tax_number' => '1111111111',
        ]);

        $otherStore = MarketplaceStore::create([
            'user_id' => $otherUser->id,
            'legal_entity_id' => $otherLegalEntity->id,
            'store_name' => 'Other Store',
            'store_code' => 'other-store-code',
            'marketplace' => 'trendyol',
            'status' => 'active',
        ]);

        $otherOrder = ChannelOrder::create([
            'store_id' => $otherStore->id,
            'legal_entity_id' => $otherLegalEntity->id,
            'external_order_id' => 'ty-999',
            'order_number' => 'TY999888', // Same order number
            'order_status' => 'approved',
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@doe.com',
            'customer_phone' => '05554443311',
            'currency' => 'TRY',
            'ordered_at' => now(),
        ]);

        // Seed other user's TDHP accounts
        (new ChartOfAccountsSeeder())->runForUser($otherUser->id);

        $otherParty = Party::create([
            'user_id' => $otherUser->id,
            'display_name' => 'Jane Doe',
            'party_type' => 'person',
            'status' => 'active',
        ]);

        // Create other user's sales order
        $otherSalesOrder = SalesOrder::create([
            'user_id'         => $otherUser->id,
            'party_id'         => $otherParty->id,
            'document_number' => 'TY999888',
            'order_date'      => now()->toDateString(),
            'total_amount'    => 100.00,
            'status'          => 'approved',
        ]);

        // Create our user's order with same order number but different user_id
        $ourOrder = ChannelOrder::create([
            'store_id' => $this->store->id,
            'legal_entity_id' => $this->legalEntity->id,
            'external_order_id' => 'ty-999',
            'order_number' => 'TY999888', // Same order number
            'order_status' => 'approved',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@doe.com',
            'customer_phone' => '05554443322',
            'currency' => 'TRY',
            'ordered_at' => now(),
        ]);

        $ourParty = Party::create([
            'user_id' => $this->user->id,
            'display_name' => 'John Doe',
            'party_type' => 'person',
            'status' => 'active',
        ]);

        $ourSalesOrder = SalesOrder::create([
            'user_id'         => $this->user->id,
            'party_id'         => $ourParty->id,
            'document_number' => 'TY999888',
            'order_date'      => now()->toDateString(),
            'total_amount'    => 100.00,
            'status'          => 'approved',
        ]);

        $event = OrderFinancialEvent::create([
            'store_id' => $this->store->id,
            'legal_entity_id' => $this->legalEntity->id,
            'channel_order_id' => $ourOrder->id,
            'event_source' => 'trendyol',
            'event_type' => 'payout',
            'amount' => 100.00,
            'event_date' => now(),
            'currency' => 'TRY',
            'direction' => 'in',
            'status' => 'settled',
        ]);

        $journal = $this->bridge->bridgeFinancialEvent($event);

        $this->assertNotNull($journal);
        // Verify it mapped to our user's party (John Doe), not the other user's (Jane Doe)
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $journal->id,
            'party_id' => $ourParty->id,
            'user_id' => $this->user->id,
        ]);
        $this->assertDatabaseMissing('journal_lines', [
            'journal_entry_id' => $journal->id,
            'party_id' => $otherParty->id,
        ]);
    }
}
