<?php

namespace Tests\Feature;

use App\Models\Party;
use App\Models\PartyIdentity;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\EDocument;
use App\Models\EDocumentLine;
use App\Models\EDocumentEvent;
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
    private Party $party;
    private SalesOrder $order;
    private EDocumentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['is_active' => true]);
        $this->party = Party::factory()->create(['user_id' => $this->user->id, 'display_name' => 'Test Alıcı Müşteri']);
        $this->party->roles()->create(['user_id' => $this->user->id, 'role' => 'customer']);

        // VKN/TCKN ekleyelim
        PartyIdentity::create([
            'user_id'        => $this->user->id,
            'party_id'       => $this->party->id,
            'source_type'    => 'manual',
            'identity_kind'  => 'vkn',
            'identity_value' => '1234567890',
        ]);

        $seeder = new ChartOfAccountsSeeder();
        $seeder->runForUser($this->user->id);

        $warehouse = app(StockService::class)->createWarehouse($this->user->id, 'Merkez Depo', 'depo-merkez', true);

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
            'document_number' => 'SO-101',
            'order_date'      => now()->toDateString(),
        ], [
            ['stock_code' => 'P-1', 'quantity' => 2, 'unit_price' => 100.00, 'vat_rate' => 20.00, 'discount_rate' => 10.00],
        ]);

        $trade->approveSalesOrder($this->order);

        $this->service = app(EDocumentService::class);
    }

    public function test_create_e_document_draft_from_approved_sales_order(): void
    {
        $doc = $this->service->createDraft($this->order, 'e_invoice', [], $this->user->id);

        $this->assertDatabaseHas('e_documents', [
            'id'             => $doc->id,
            'sales_order_id' => $this->order->id,
            'document_type'  => 'e_invoice',
            'status'         => 'draft',
            'buyer_name'     => 'Test Alıcı Müşteri',
            'buyer_tax_number'=> '1234567890',
        ]);

        // Lines control
        $this->assertDatabaseHas('e_document_lines', [
            'e_document_id' => $doc->id,
            'stock_code'    => 'P-1',
            'quantity'      => 2,
            'unit_price'    => 100.00,
        ]);

        // Event log control
        $this->assertDatabaseHas('e_document_events', [
            'e_document_id' => $doc->id,
            'status_from'   => 'none',
            'status_to'     => 'draft',
            'event_type'    => 'created',
        ]);
    }

    public function test_approved_olmayan_siparis_reddedilir(): void
    {
        $this->order->update(['status' => 'draft']);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->createDraft($this->order, 'e_invoice', [], $this->user->id);
    }

    public function test_baska_user_siparis_ile_draft_olusturma_reddedilir(): void
    {
        $otherUser = User::factory()->create(['is_active' => true]);
        $this->expectException(\InvalidArgumentException::class);
        $this->service->createDraft($this->order, 'e_invoice', [], $otherUser->id);
    }

    public function test_e_invoice_tax_number_olmadan_reddedilir(): void
    {
        // Identity silelim
        PartyIdentity::where('party_id', $this->party->id)->delete();

        $this->expectException(\InvalidArgumentException::class);
        $this->service->createDraft($this->order, 'e_invoice', [], $this->user->id);
    }

    public function test_e_archive_buyer_name_ile_draft_olusturur(): void
    {
        PartyIdentity::where('party_id', $this->party->id)->delete();

        $doc = $this->service->createDraft($this->order, 'e_archive', [], $this->user->id);
        $this->assertEquals('draft', $doc->status);
        $this->assertEquals('Test Alıcı Müşteri', $doc->buyer_name);
    }

    public function test_subtotal_vat_discount_total_siparisle_uyumlu_yazilir(): void
    {
        $doc = $this->service->createDraft($this->order, 'e_invoice', [], $this->user->id);

        $this->assertEquals(200.00, $doc->subtotal_amount);
        $this->assertEquals(20.00, $doc->discount_amount);
        $this->assertEquals(36.00, $doc->vat_amount);
        $this->assertEquals(216.00, $doc->total_amount);
    }

    public function test_ayni_sales_order_icin_ikinci_aktif_belge_reddedilir(): void
    {
        $this->service->createDraft($this->order, 'e_invoice', [], $this->user->id);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->createDraft($this->order, 'e_invoice', [], $this->user->id);
    }

    public function test_source_key_ayni_payload_ile_mevcut_belgeyi_döndürür(): void
    {
        $doc1 = $this->service->createDraft($this->order, 'e_invoice', ['source_key' => 'key123'], $this->user->id);
        $doc2 = $this->service->createDraft($this->order, 'e_invoice', ['source_key' => 'key123'], $this->user->id);

        $this->assertEquals($doc1->id, $doc2->id);
    }

    public function test_source_key_farkli_payload_ile_exception_verir(): void
    {
        $this->service->createDraft($this->order, 'e_invoice', ['source_key' => 'key123'], $this->user->id);

        $otherOrder = app(TradeService::class)->createSalesOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'document_number' => 'SO-102',
            'order_date'      => now()->toDateString(),
        ], [
            ['stock_code' => 'P-1', 'quantity' => 5, 'unit_price' => 200.00],
        ]);
        app(TradeService::class)->approveSalesOrder($otherOrder);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->createDraft($otherOrder, 'e_invoice', ['source_key' => 'key123'], $this->user->id);
    }

    public function test_send_to_provider_draft_belgeyi_accepted_yapar_ve_sirali_numara_uretir(): void
    {
        $doc1 = $this->service->createDraft($this->order, 'e_invoice', [], $this->user->id);
        $sent1 = $this->service->sendToProvider($doc1, $this->user->id);

        $this->assertEquals('accepted', $sent1->status);
        $this->assertNotNull($sent1->invoice_number);

        $year = now()->year;
        $this->assertEquals("GIB{$year}000000001", $sent1->invoice_number);

        // İkinci sipariş
        $otherOrder = app(TradeService::class)->createSalesOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'document_number' => 'SO-103',
            'order_date'      => now()->toDateString(),
        ], [
            ['stock_code' => 'P-1', 'quantity' => 1, 'unit_price' => 50.00],
        ]);
        app(TradeService::class)->approveSalesOrder($otherOrder);

        $doc2 = $this->service->createDraft($otherOrder, 'e_invoice', [], $this->user->id);
        $sent2 = $this->service->sendToProvider($doc2, $this->user->id);
        $this->assertEquals("GIB{$year}000000002", $sent2->invoice_number);
    }

    public function test_send_to_provider_accepted_belgeye_tekrar_cagrilinca_idempotent(): void
    {
        $doc = $this->service->createDraft($this->order, 'e_invoice', [], $this->user->id);
        $sent1 = $this->service->sendToProvider($doc, $this->user->id);
        $sent2 = $this->service->sendToProvider($sent1, $this->user->id);

        $this->assertEquals($sent1->invoice_number, $sent2->invoice_number);
    }

    public function test_cancel_document_accepted_belgeyi_cancelled_yapar(): void
    {
        $doc = $this->service->createDraft($this->order, 'e_invoice', [], $this->user->id);
        $sent = $this->service->sendToProvider($doc, $this->user->id);

        $cancelled = $this->service->cancelDocument($sent, 'Yanlış kesim', $this->user->id);

        $this->assertEquals('cancelled', $cancelled->status);
        $this->assertEquals('Yanlış kesim', $cancelled->cancel_reason);

        $this->assertDatabaseHas('e_document_events', [
            'e_document_id' => $doc->id,
            'status_from'   => 'accepted',
            'status_to'     => 'cancelled',
            'event_type'    => 'status_changed',
        ]);
    }

    public function test_cancel_document_bos_reason_ile_reddedilir(): void
    {
        $doc = $this->service->createDraft($this->order, 'e_invoice', [], $this->user->id);
        $sent = $this->service->sendToProvider($doc, $this->user->id);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->cancelDocument($sent, '', $this->user->id);
    }

    public function test_cancel_document_draft_belgeyi_reddeder(): void
    {
        $doc = $this->service->createDraft($this->order, 'e_invoice', [], $this->user->id);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->cancelDocument($doc, 'Gerekçe', $this->user->id);
    }

    public function test_cancel_document_baska_user_belgesini_reddeder(): void
    {
        $doc = $this->service->createDraft($this->order, 'e_invoice', [], $this->user->id);
        $sent = $this->service->sendToProvider($doc, $this->user->id);

        $otherUser = User::factory()->create(['is_active' => true]);
        $this->expectException(\InvalidArgumentException::class);
        $this->service->cancelDocument($sent, 'Gerekçe', $otherUser->id);
    }

    public function test_cancel_document_cancelled_belgeyi_tekrar_reddeder(): void
    {
        $doc = $this->service->createDraft($this->order, 'e_invoice', [], $this->user->id);
        $sent = $this->service->sendToProvider($doc, $this->user->id);
        $cancelled = $this->service->cancelDocument($sent, 'İptal Gerekçesi', $this->user->id);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Belge zaten iptal edilmiş.');
        $this->service->cancelDocument($cancelled, 'Tekrar İptal', $this->user->id);
    }

    public function test_same_source_key_different_buyer_tax_number_throws(): void
    {
        $this->service->createDraft($this->order, 'e_invoice', ['source_key' => 'key123'], $this->user->id);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Çakışan source_key ile farklı detaylara sahip bir e-Belge zaten mevcut.');
        $this->service->createDraft($this->order, 'e_invoice', ['source_key' => 'key123', 'buyer_tax_number' => '9999999999'], $this->user->id);
    }

    public function test_same_source_key_different_document_type_throws(): void
    {
        $this->service->createDraft($this->order, 'e_invoice', ['source_key' => 'key123'], $this->user->id);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Çakışan source_key ile farklı detaylara sahip bir e-Belge zaten mevcut.');
        $this->service->createDraft($this->order, 'e_archive', ['source_key' => 'key123'], $this->user->id);
    }

    public function test_same_source_key_different_line_vat_discount_snapshot_throws(): void
    {
        // 1. draft
        $this->service->createDraft($this->order, 'e_invoice', ['source_key' => 'key123'], $this->user->id);

        // İkinci siparişi farklı kalem fiyatı/oranıyla oluşturalım
        $otherOrder = app(TradeService::class)->createSalesOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'document_number' => 'SO-105',
            'order_date'      => now()->toDateString(),
        ], [
            ['stock_code' => 'P-1', 'quantity' => 2, 'unit_price' => 120.00, 'vat_rate' => 18.00, 'discount_rate' => 5.00],
        ]);
        app(TradeService::class)->approveSalesOrder($otherOrder);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Çakışan source_key ile farklı detaylara sahip bir e-Belge zaten mevcut.');
        $this->service->createDraft($otherOrder, 'e_invoice', ['source_key' => 'key123'], $this->user->id);
    }

    public function test_sequence_concurrency_production_consecutive_invoice_numbers(): void
    {
        $doc1 = $this->service->createDraft($this->order, 'e_invoice', [], $this->user->id);
        $sent1 = $this->service->sendToProvider($doc1, $this->user->id);

        $otherOrder1 = app(TradeService::class)->createSalesOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'document_number' => 'SO-201',
            'order_date'      => now()->toDateString(),
        ], [
            ['stock_code' => 'P-1', 'quantity' => 1, 'unit_price' => 50.00],
        ]);
        app(TradeService::class)->approveSalesOrder($otherOrder1);
        $doc2 = $this->service->createDraft($otherOrder1, 'e_invoice', [], $this->user->id);
        $sent2 = $this->service->sendToProvider($doc2, $this->user->id);

        $otherOrder2 = app(TradeService::class)->createSalesOrder([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'document_number' => 'SO-202',
            'order_date'      => now()->toDateString(),
        ], [
            ['stock_code' => 'P-1', 'quantity' => 1, 'unit_price' => 75.00],
        ]);
        app(TradeService::class)->approveSalesOrder($otherOrder2);
        $doc3 = $this->service->createDraft($otherOrder2, 'e_invoice', [], $this->user->id);
        $sent3 = $this->service->sendToProvider($doc3, $this->user->id);

        $year = now()->year;
        $this->assertEquals("GIB{$year}000000001", $sent1->invoice_number);
        $this->assertEquals("GIB{$year}000000002", $sent2->invoice_number);
        $this->assertEquals("GIB{$year}000000003", $sent3->invoice_number);
    }
}
