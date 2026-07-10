<?php

namespace Tests\Feature;

use App\Models\Party;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\EDocumentService;
use App\Services\Accounting\TradeService;
use App\Services\Accounting\StockService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EDocumentServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private SalesOrder $order;
    private EDocumentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['is_active' => true]);
        $party = Party::factory()->create(['user_id' => $this->user->id]);

        $seeder = new ChartOfAccountsSeeder();
        $seeder->runForUser($this->user->id);

        $warehouse = app(StockService::class)->createWarehouse($this->user->id, 'Merkez Depo', 'depo-merkez', true);

        // Seed stock for testing
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
            'party_id'        => $party->id,
            'document_number' => 'SO-101',
            'order_date'      => now()->toDateString(),
        ], [
            ['stock_code' => 'P-1', 'quantity' => 1, 'unit_price' => 100.00],
        ]);

        $trade->approveSalesOrder($this->order);

        $this->service = app(EDocumentService::class);
    }

    public function test_create_e_document_draft_from_approved_sales_order(): void
    {
        $doc = $this->service->createDraft($this->order, 'e_invoice');

        $this->assertDatabaseHas('e_documents', [
            'id' => $doc->id,
            'sales_order_id' => $this->order->id,
            'document_type' => 'e_invoice',
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('e_document_events', [
            'e_document_id' => $doc->id,
            'status_to' => 'draft',
        ]);
    }

    public function test_send_e_document_to_provider(): void
    {
        $doc = $this->service->createDraft($this->order, 'e_invoice');
        $sent = $this->service->sendToProvider($doc);

        $this->assertEquals('accepted', $sent->status);
        $this->assertNotNull($sent->invoice_number);
        $this->assertStringStartsWith('GIB', $sent->invoice_number);

        $this->assertDatabaseHas('e_document_events', [
            'e_document_id' => $doc->id,
            'status_from' => 'draft',
            'status_to' => 'accepted',
        ]);
    }

    public function test_cancel_e_document(): void
    {
        $doc = $this->service->createDraft($this->order, 'e_archive');
        $sent = $this->service->sendToProvider($doc);

        $cancelled = $this->service->cancelDocument($sent, 'Müşteri vazgeçti');

        $this->assertEquals('cancelled', $cancelled->status);
        $this->assertEquals('Müşteri vazgeçti', $cancelled->response_message);

        $this->assertDatabaseHas('e_document_events', [
            'e_document_id' => $doc->id,
            'status_from' => 'accepted',
            'status_to' => 'cancelled',
        ]);
    }
}
