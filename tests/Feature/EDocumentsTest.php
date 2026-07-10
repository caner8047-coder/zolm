<?php

namespace Tests\Feature;

use App\Models\EDocument;
use App\Models\MpProduct;
use App\Models\Party;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_is_blocked_when_accounting_enabled_is_false(): void
    {
        config()->set('marketplace.features.accounting_enabled', false);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $this->actingAs($user)
            ->get(route('accounting.e-documents'))
            ->assertStatus(404);
    }

    public function test_page_renders_when_accounting_enabled_is_true(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $this->actingAs($user)
            ->get(route('accounting.e-documents'))
            ->assertStatus(200)
            ->assertSeeLivewire('accounting.e-documents');
    }

    public function test_creating_e_document_draft(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user->id]);
        $party->roles()->create(['user_id' => $user->id, 'role' => 'customer']);

        $tradeService = app(\App\Services\Accounting\TradeService::class);
        $order = $tradeService->createSalesOrder([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'document_number' => 'SO-001',
            'order_date' => now()->toDateString(),
        ], [
            ['stock_code' => 'P-1', 'quantity' => 1, 'unit_price' => 100],
        ]);

        // Must be approved to create e-document
        $order->update(['status' => 'approved']);

        Livewire::actingAs($user)
            ->test('accounting.e-documents')
            ->set('selectedSalesOrderId', $order->id)
            ->set('documentType', 'e_invoice')
            ->call('createEDocument')
            ->assertSet('messageType', 'success');

        $this->assertDatabaseHas('e_documents', [
            'user_id' => $user->id,
            'sales_order_id' => $order->id,
            'document_type' => 'e_invoice',
            'status' => 'draft',
        ]);
    }

    public function test_sending_to_gib(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user->id]);
        $party->roles()->create(['user_id' => $user->id, 'role' => 'customer']);

        $tradeService = app(\App\Services\Accounting\TradeService::class);
        $order = $tradeService->createSalesOrder([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'document_number' => 'SO-001',
            'order_date' => now()->toDateString(),
        ], [
            ['stock_code' => 'P-1', 'quantity' => 1, 'unit_price' => 100],
        ]);
        $order->update(['status' => 'approved']);

        $service = app(\App\Services\Accounting\EDocumentService::class);
        $doc = $service->createDraft($order, 'e_invoice');

        Livewire::actingAs($user)
            ->test('accounting.e-documents')
            ->call('sendToGib', $doc->id)
            ->assertSet('messageType', 'success');

        $this->assertEquals('accepted', $doc->fresh()->status);
        $this->assertNotEmpty($doc->fresh()->invoice_number);
    }

    public function test_cancelling_e_document(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user->id]);
        $party->roles()->create(['user_id' => $user->id, 'role' => 'customer']);

        $tradeService = app(\App\Services\Accounting\TradeService::class);
        $order = $tradeService->createSalesOrder([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'document_number' => 'SO-001',
            'order_date' => now()->toDateString(),
        ], [
            ['stock_code' => 'P-1', 'quantity' => 1, 'unit_price' => 100],
        ]);
        $order->update(['status' => 'approved']);

        $service = app(\App\Services\Accounting\EDocumentService::class);
        $doc = $service->createDraft($order, 'e_invoice');
        $service->sendToProvider($doc);

        Livewire::actingAs($user)
            ->test('accounting.e-documents')
            ->call('openCancelModal', $doc->id)
            ->set('cancelReason', 'Müşteri vazgeçti')
            ->call('cancelDocument')
            ->assertSet('messageType', 'success');

        $this->assertEquals('cancelled', $doc->fresh()->status);
        $this->assertEquals('Müşteri vazgeçti', $doc->fresh()->response_message);
    }

    public function test_tenant_isolation_on_e_documents(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user1 = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $user2 = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $party2 = Party::factory()->create(['user_id' => $user2->id]);
        $party2->roles()->create(['user_id' => $user2->id, 'role' => 'customer']);

        $tradeService = app(\App\Services\Accounting\TradeService::class);
        $order2 = $tradeService->createSalesOrder([
            'user_id' => $user2->id,
            'party_id' => $party2->id,
            'document_number' => 'SO-002',
            'order_date' => now()->toDateString(),
        ], [
            ['stock_code' => 'P-1', 'quantity' => 1, 'unit_price' => 100],
        ]);
        $order2->update(['status' => 'approved']);

        $service = app(\App\Services\Accounting\EDocumentService::class);
        $doc2 = $service->createDraft($order2, 'e_invoice');

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        // User 1 attempting to send User 2's document should fail
        Livewire::actingAs($user1)
            ->test('accounting.e-documents')
            ->call('sendToGib', $doc2->id);
    }
}
