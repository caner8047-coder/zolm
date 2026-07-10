<?php

namespace Tests\Feature;

use App\Models\EDocument;
use App\Models\MpProduct;
use App\Models\Party;
use App\Models\PartyIdentity;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\StockService;
use App\Services\Accounting\TradeService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Party $party;
    private SalesOrder $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $this->party = Party::factory()->create(['user_id' => $this->user->id, 'display_name' => 'Müşteri']);
        $this->party->roles()->create(['user_id' => $this->user->id, 'role' => 'customer']);

        PartyIdentity::create([
            'user_id'        => $this->user->id,
            'party_id'       => $this->party->id,
            'source_type'    => 'manual',
            'identity_kind'  => 'vkn',
            'identity_value' => '9998887776',
        ]);

        $seeder = new ChartOfAccountsSeeder();
        $seeder->runForUser($this->user->id);

        $warehouse = app(StockService::class)->createWarehouse($this->user->id, 'Merkez Depo', 'depo-merkez', true);

        // MpProduct oluştur
        MpProduct::create([
            'user_id'    => $this->user->id,
            'stock_code' => 'P-1',
            'name'       => 'Test Ürün P-1',
            'barcode'    => 'BAR-P-1',
        ]);

        // Seed stock
        app(StockService::class)->recordMovement([
            'user_id'       => $this->user->id,
            'warehouse_id'  => $warehouse->id,
            'stock_code'    => 'P-1',
            'movement_type' => 'in_adjustment',
            'direction'     => 'in',
            'quantity'      => 10,
        ]);

        $trade = app(TradeService::class);
        $this->order = $trade->createSalesOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'document_number' => 'SO-001',
            'order_date'      => now()->toDateString(),
        ], [
            ['stock_code' => 'P-1', 'quantity' => 1, 'unit_price' => 100],
        ]);
        $trade->approveSalesOrder($this->order);
    }

    public function test_route_is_blocked_when_accounting_enabled_is_false(): void
    {
        config()->set('marketplace.features.accounting_enabled', false);

        $this->actingAs($this->user)
            ->get(route('accounting.e-documents'))
            ->assertStatus(404);
    }

    public function test_page_renders_when_accounting_enabled_is_true(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $this->actingAs($this->user)
            ->get(route('accounting.e-documents'))
            ->assertStatus(200)
            ->assertSeeLivewire('accounting.e-documents');
    }

    public function test_available_sales_orders_sadece_userin_siparislerini_listeler(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        // Diğer kullanıcı siparişi
        $otherUser = User::factory()->create(['is_active' => true]);
        $otherParty = Party::factory()->create(['user_id' => $otherUser->id]);
        $otherParty->roles()->create(['user_id' => $otherUser->id, 'role' => 'customer']);

        MpProduct::create([
            'user_id'    => $otherUser->id,
            'stock_code' => 'P-1',
            'name'       => 'Test Ürün P-1',
            'barcode'    => 'BAR-P-1-OTHER',
        ]);

        $trade = app(TradeService::class);
        $otherOrder = $trade->createSalesOrder([
            'user_id'         => $otherUser->id,
            'party_id'        => $otherParty->id,
            'document_number' => 'SO-OTHER',
            'order_date'      => now()->toDateString(),
        ], [
            ['stock_code' => 'P-1', 'quantity' => 1, 'unit_price' => 100],
        ]);
        $otherOrder->update([
            'status'          => 'approved',
            'subtotal_amount' => 100.00,
            'discount_amount' => 0.00,
            'vat_amount'      => 20.00,
            'total_amount'    => 120.00,
        ]);

        Livewire::actingAs($this->user)
            ->test('accounting.e-documents')
            ->set('showCreateForm', true)
            ->assertSee($this->order->document_number)
            ->assertDontSee('SO-OTHER');
    }

    public function test_creating_e_document_draft_ui(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        Livewire::actingAs($this->user)
            ->test('accounting.e-documents')
            ->set('selectedSalesOrderId', $this->order->id)
            ->set('documentType', 'e_invoice')
            ->set('buyerTaxNumber', '9998887776')
            ->call('createEDocument')
            ->assertSet('messageType', 'success');

        $this->assertDatabaseHas('e_documents', [
            'user_id'        => $this->user->id,
            'sales_order_id' => $this->order->id,
            'document_type'  => 'e_invoice',
            'status'         => 'draft',
        ]);
    }

    public function test_sending_to_gib_ui(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $doc = app(\App\Services\Accounting\EDocumentService::class)->createDraft($this->order, 'e_invoice', [], $this->user->id);

        Livewire::actingAs($this->user)
            ->test('accounting.e-documents')
            ->call('sendToGib', $doc->id)
            ->assertSet('messageType', 'success');

        $this->assertEquals('accepted', $doc->fresh()->status);
        $this->assertNotEmpty($doc->fresh()->invoice_number);
    }

    public function test_cancelling_e_document_ui(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $doc = app(\App\Services\Accounting\EDocumentService::class)->createDraft($this->order, 'e_invoice', [], $this->user->id);
        app(\App\Services\Accounting\EDocumentService::class)->sendToProvider($doc, $this->user->id);

        Livewire::actingAs($this->user)
            ->test('accounting.e-documents')
            ->call('openCancelModal', $doc->id)
            ->set('cancelReason', 'Müşteri iptal istedi')
            ->call('cancelDocument')
            ->assertSet('messageType', 'success');

        $this->assertEquals('cancelled', $doc->fresh()->status);
        $this->assertEquals('Müşteri iptal istedi', $doc->fresh()->cancel_reason);
    }

    public function test_events_modal_only_opens_allowed_document(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $otherUser = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $otherParty = Party::factory()->create(['user_id' => $otherUser->id]);
        $otherParty->roles()->create(['user_id' => $otherUser->id, 'role' => 'customer']);

        MpProduct::create([
            'user_id'    => $otherUser->id,
            'stock_code' => 'P-1',
            'name'       => 'Test Ürün P-1',
            'barcode'    => 'BAR-P-1-OTHER-EVENTS',
        ]);

        $trade = app(TradeService::class);
        $otherOrder = $trade->createSalesOrder([
            'user_id'         => $otherUser->id,
            'party_id'        => $otherParty->id,
            'document_number' => 'SO-999',
            'order_date'      => now()->toDateString(),
        ], [
            ['stock_code' => 'P-1', 'quantity' => 1, 'unit_price' => 100],
        ]);
        $otherOrder->update([
            'status'          => 'approved',
            'subtotal_amount' => 100.00,
            'discount_amount' => 0.00,
            'vat_amount'      => 20.00,
            'total_amount'    => 120.00,
        ]);

        $otherDoc = app(\App\Services\Accounting\EDocumentService::class)->createDraft($otherOrder, 'e_archive', [], $otherUser->id);

        // User attempting to open other user's event log should throw exception
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Livewire::actingAs($this->user)
            ->test('accounting.e-documents')
            ->call('openEventsModal', $otherDoc->id);
    }

    public function test_tenant_isolation_on_search_ui(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $otherUser = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $otherParty = Party::factory()->create(['user_id' => $otherUser->id, 'display_name' => 'OtherBuyerName']);
        $otherParty->roles()->create(['user_id' => $otherUser->id, 'role' => 'customer']);

        MpProduct::create([
            'user_id'    => $otherUser->id,
            'stock_code' => 'P-1',
            'name'       => 'Test Ürün P-1',
            'barcode'    => 'BAR-P-1-OTHER-SEARCH',
        ]);

        $trade = app(TradeService::class);
        $otherOrder = $trade->createSalesOrder([
            'user_id'         => $otherUser->id,
            'party_id'        => $otherParty->id,
            'document_number' => 'SO-OTHER-SEARCH',
            'order_date'      => now()->toDateString(),
        ], [
            ['stock_code' => 'P-1', 'quantity' => 1, 'unit_price' => 100],
        ]);
        $otherOrder->update([
            'status'          => 'approved',
            'subtotal_amount' => 100.00,
            'discount_amount' => 0.00,
            'vat_amount'      => 20.00,
            'total_amount'    => 120.00,
        ]);

        $otherDoc = app(\App\Services\Accounting\EDocumentService::class)->createDraft($otherOrder, 'e_archive', [], $otherUser->id);

        Livewire::actingAs($this->user)
            ->test('accounting.e-documents')
            ->set('search', 'OtherBuyerName')
            ->assertDontSee('OtherBuyerName');
    }

    public function test_sorting_and_toggling_columns(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        Livewire::actingAs($this->user)
            ->test('accounting.e-documents')
            ->call('toggleColumn', 'issue_date')
            ->assertSet('visibleColumns', ['id', 'invoice_number', 'document_type', 'buyer', 'total_amount', 'status', 'action'])
            ->call('sortTable', 'total_amount')
            ->assertSet('sortColumn', 'total_amount')
            ->assertSet('sortDirection', 'asc')
            ->call('sortTable', 'total_amount')
            ->assertSet('sortDirection', 'desc');
    }
}
