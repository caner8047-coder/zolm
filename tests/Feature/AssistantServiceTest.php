<?php

namespace Tests\Feature;

use App\Models\AssistantQuery;
use App\Models\Party;
use App\Models\Warehouse;
use App\Models\LegalEntity;
use App\Models\User;
use App\Services\Accounting\AssistantService;
use App\Services\Accounting\OutstandingInvoiceService;
use App\Services\Accounting\StockService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AssistantServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Party $party;
    private AssistantService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user  = User::factory()->create(['is_active' => true]);
        $this->party = Party::factory()->create(['user_id' => $this->user->id]);
        $this->party->roles()->create(['user_id' => $this->user->id, 'role' => 'customer']);

        (new ChartOfAccountsSeeder())->runForUser($this->user->id);

        $this->service = app(AssistantService::class);
    }

    // ─── INTENT ROUTING ──────────────────────────────────────────────────

    public function test_cash_flow_intent_routes_to_cash_flow_forecast(): void
    {
        app(OutstandingInvoiceService::class)->recordCollection([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'amount'          => 5000.00,
            'collection_date' => now()->toDateString(),
        ]);

        $query = $this->service->askAssistant($this->user->id, 'Nakit akışım ve kasa durumum nedir?');

        $this->assertEquals('completed', $query->status);
        $this->assertEquals('cash_flow', $query->intent);
        $this->assertStringContainsString('Nakit görünümü', $query->response_text);
        $this->assertStringContainsString('5.000,00', $query->response_text);
        $this->assertNotEmpty($query->sources_json);
        $this->assertEquals('ReportService', $query->sources_json[0]['service']);
        $this->assertEquals('cashFlowForecast', $query->sources_json[0]['method']);
    }

    public function test_receivables_aging_intent(): void
    {
        app(OutstandingInvoiceService::class)->createReceivable([
            'user_id'       => $this->user->id,
            'party_id'      => $this->party->id,
            'amount'        => 750.00,
            'document_date' => now()->subDays(10)->toDateString(),
            'due_date'      => now()->subDays(5)->toDateString(),
        ]);

        $query = $this->service->askAssistant($this->user->id, 'Vadesi geçmiş alacaklarım nedir?');

        $this->assertEquals('receivables_aging', $query->intent);
        $this->assertStringContainsString('750,00', $query->response_text);
        $this->assertEquals('receivablesAging', $query->sources_json[0]['method']);
    }

    public function test_payables_aging_intent(): void
    {
        app(OutstandingInvoiceService::class)->createPayable([
            'user_id'       => $this->user->id,
            'party_id'      => $this->party->id,
            'amount'        => 1200.00,
            'document_date' => now()->toDateString(),
            'due_date'      => now()->addDays(15)->toDateString(),
        ]);

        $query = $this->service->askAssistant($this->user->id, 'Tedarikçi borçlarım nedir?');

        $this->assertEquals('payables_aging', $query->intent);
        $this->assertStringContainsString('1.200,00', $query->response_text);
        $this->assertEquals('payablesAging', $query->sources_json[0]['method']);
    }

    public function test_income_expense_intent(): void
    {
        $query = $this->service->askAssistant($this->user->id, 'Bu ay karlılık durumum nasıl?');

        $this->assertEquals('income_expense', $query->intent);
        $this->assertStringContainsString('Gelir-Gider özeti', $query->response_text);
    }

    public function test_stock_inventory_intent(): void
    {
        $stock = app(StockService::class);
        $wh    = $stock->createWarehouse($this->user->id, 'Ana Depo', 'ana-depo', true);

        \App\Models\MpProduct::create([
            'user_id'                  => $this->user->id,
            'stock_code'               => 'P-001',
            'product_name'             => 'Test Ürünü',
            'cogs'                     => 20.00,
            'barcode'                  => 'BAR001',
            'critical_stock_threshold' => 10, // Kritik eşik 10
        ]);

        // 5 adet giriyoruz, yani 10'un altında (kritik seviye)
        $stock->recordMovement([
            'user_id'       => $this->user->id,
            'warehouse_id'  => $wh->id,
            'stock_code'    => 'P-001',
            'movement_type' => 'in_purchase',
            'direction'     => 'in',
            'quantity'      => 5,
            'unit_cost'     => 20.00,
        ]);

        $query = $this->service->askAssistant($this->user->id, 'Stok değerim ne kadar?');

        $this->assertEquals('stock_inventory', $query->intent);
        $this->assertStringContainsString('Stok durumu', $query->response_text);
        // Kritik stok adet doğrulaması
        $this->assertStringContainsString('Kritik stok uyarısı olan 1 ürün mevcut', $query->response_text);

        // Önerilerin doğru üretildiğini doğrula
        $this->assertNotEmpty($query->suggestions_json);
        $this->assertEquals('1 ürün kritik stok seviyesinde', $query->suggestions_json[0]['title']);
    }

    public function test_party_balances_intent(): void
    {
        $query = $this->service->askAssistant($this->user->id, 'Cari bakiye durumum nedir?');

        $this->assertEquals('party_balances', $query->intent);
        $this->assertStringContainsString('Cari bakiye özeti', $query->response_text);
    }

    public function test_executive_summary_intent(): void
    {
        $query = $this->service->askAssistant($this->user->id, 'Genel finans durumumu özetle');

        $this->assertEquals('executive_summary', $query->intent);
        $this->assertStringContainsString('Genel finans özeti', $query->response_text);
    }

    // ─── FALLBACK ────────────────────────────────────────────────────────

    public function test_unknown_query_produces_fallback(): void
    {
        $query = $this->service->askAssistant($this->user->id, 'Yarın hava nasıl olacak?');

        $this->assertEquals('unknown', $query->intent);
        $this->assertStringContainsString('anlayamadım', $query->response_text);
        $this->assertEquals('completed', $query->status);
    }

    // ─── İŞLEM İSTEĞİ GUARD ────────────────────────────────────────────

    public function test_action_request_is_blocked_and_no_financial_change(): void
    {
        $beforeCount = AssistantQuery::where('user_id', $this->user->id)->count();

        $query = $this->service->askAssistant($this->user->id, 'Şu faturayı iptal et');

        $this->assertEquals('blocked', $query->status);
        $this->assertStringContainsString('işlem yapmaz', $query->response_text);
        // blocked olarak kayıt oluştu ama diğer muhasebe tablolarına dokunulmadı
        $this->assertDatabaseMissing('journal_entries', ['user_id' => $this->user->id]);
    }

    public function test_create_action_is_blocked(): void
    {
        $query = $this->service->askAssistant($this->user->id, 'Ödeme oluştur 500 TL');
        $this->assertEquals('blocked', $query->status);
    }

    // ─── VALIDATION ──────────────────────────────────────────────────────

    public function test_empty_query_throws_invalid_argument(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->askAssistant($this->user->id, '');
    }

    public function test_too_long_query_throws_invalid_argument(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->askAssistant($this->user->id, str_repeat('a', 1001));
    }

    // ─── QUERY LOG ───────────────────────────────────────────────────────

    public function test_query_log_is_created_with_all_fields(): void
    {
        $query = $this->service->askAssistant($this->user->id, 'Nakit durumum nedir?');

        $this->assertDatabaseHas('assistant_queries', [
            'id'     => $query->id,
            'status' => 'completed',
        ]);

        $this->assertNotNull($query->intent);
        $this->assertNotNull($query->sources_json);
        $this->assertNotNull($query->filters_json);
        $this->assertNotNull($query->answered_at);
    }

    // ─── TENANT İZOLASYONU ──────────────────────────────────────────────

    public function test_tenant_isolation_other_user_data_not_included(): void
    {
        $otherUser  = User::factory()->create(['is_active' => true]);
        $otherParty = Party::factory()->create(['user_id' => $otherUser->id]);
        $otherParty->roles()->create(['user_id' => $otherUser->id, 'role' => 'customer']);
        (new ChartOfAccountsSeeder())->runForUser($otherUser->id);

        // Diğer kullanıcının alacağı
        app(OutstandingInvoiceService::class)->createReceivable([
            'user_id'       => $otherUser->id,
            'party_id'      => $otherParty->id,
            'amount'        => 99999.00,
            'document_date' => now()->toDateString(),
        ]);

        // Asıl user sorguluyor
        $query = $this->service->askAssistant($this->user->id, 'Alacaklarım nedir?');

        // 99999 diğer kullanıcıya ait, cevaba sızmamalı
        $this->assertStringNotContainsString('99.999', $query->response_text);
    }

    public function test_context_with_other_user_party_id_is_rejected(): void
    {
        $otherUser  = User::factory()->create(['is_active' => true]);
        $otherParty = Party::factory()->create(['user_id' => $otherUser->id]);

        $this->expectException(InvalidArgumentException::class);
        $this->service->askAssistant($this->user->id, 'Alacaklarım nedir?', [
            'party_id' => $otherParty->id,
        ]);
    }

    public function test_context_with_other_user_legal_entity_is_rejected(): void
    {
        $otherUser = User::factory()->create(['is_active' => true]);
        $otherLE   = LegalEntity::create([
            'user_id'    => $otherUser->id,
            'name'       => 'Yabancı Şirket',
            'is_active'  => true,
            'tax_number' => '9999999999',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->service->askAssistant($this->user->id, 'Stok değerim ne?', [
            'legal_entity_id' => $otherLE->id,
        ]);
    }

    public function test_context_with_other_user_warehouse_is_rejected(): void
    {
        $otherUser = User::factory()->create(['is_active' => true]);
        $otherWh   = app(StockService::class)->createWarehouse($otherUser->id, 'Yabancı Depo', 'yab-depo', true);

        $this->expectException(InvalidArgumentException::class);
        $this->service->askAssistant($this->user->id, 'Stok değerim ne?', [
            'warehouse_id' => $otherWh->id,
        ]);
    }

    // ─── TARİH FİLTRELERİ ───────────────────────────────────────────────

    public function test_bu_ay_filter_extracted_correctly(): void
    {
        $service = $this->service;
        $filters = $service->extractFilters($this->user->id, 'bu ay nakit akışım');

        $this->assertEquals(now()->startOfMonth()->toDateString(), $filters['date_from']);
        $this->assertEquals(now()->toDateString(), $filters['date_to']);
    }

    public function test_gecen_ay_filter_extracted_correctly(): void
    {
        $filters = $this->service->extractFilters($this->user->id, 'geçen ay gelir gider');

        $this->assertEquals(now()->subMonth()->startOfMonth()->toDateString(), $filters['date_from']);
        $this->assertEquals(now()->subMonth()->endOfMonth()->toDateString(), $filters['date_to']);
    }

    public function test_son_30_gun_filter_extracted_correctly(): void
    {
        $filters = $this->service->extractFilters($this->user->id, 'son 30 gün alacak');

        $this->assertEquals(now()->subDays(30)->toDateString(), $filters['date_from']);
        $this->assertEquals(now()->toDateString(), $filters['date_to']);
    }

    public function test_son_7_gun_filter_extracted_correctly(): void
    {
        $filters = $this->service->extractFilters($this->user->id, 'son 7 gün nakit');

        $this->assertEquals(now()->subDays(7)->toDateString(), $filters['date_from']);
        $this->assertEquals(now()->toDateString(), $filters['date_to']);
    }

    // ─── DUPLICATE GUARD ─────────────────────────────────────────────────

    public function test_duplicate_query_within_guard_returns_existing(): void
    {
        (new ChartOfAccountsSeeder())->runForUser($this->user->id);

        $first  = $this->service->askAssistant($this->user->id, 'Nakit akışım nedir?');
        $second = $this->service->askAssistant($this->user->id, 'Nakit akışım nedir?');

        // İkincisi yeni kayıt oluşturmamalı — aynı ID döner
        $this->assertEquals($first->id, $second->id);
    }

    public function test_duplicate_guard_context_validation_is_not_bypassed(): void
    {
        (new ChartOfAccountsSeeder())->runForUser($this->user->id);

        // İlk sorgu geçerli
        $this->service->askAssistant($this->user->id, 'Nakit akışım nedir?');

        // İkinci sorguda başka bir kullanıcının party_id'si ile bypass denemesi yapılıyor.
        // Duplicate guard'dan önce validateContext tetiklendiği için hata fırlatılmalıdır.
        $otherUser  = User::factory()->create(['is_active' => true]);
        $otherParty = Party::factory()->create(['user_id' => $otherUser->id]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Belirtilen cari bu kullanıcıya ait değil.');

        $this->service->askAssistant($this->user->id, 'Nakit akışım nedir?', [
            'party_id' => $otherParty->id
        ]);
    }

    public function test_duplicate_guard_does_not_trigger_for_different_context_filters(): void
    {
        (new ChartOfAccountsSeeder())->runForUser($this->user->id);

        // İki farklı geçerli şirket oluşturuyoruz
        $leA = LegalEntity::create(['user_id' => $this->user->id, 'name' => 'Şirket A', 'is_active' => true, 'tax_number' => '1111111111']);
        $leB = LegalEntity::create(['user_id' => $this->user->id, 'name' => 'Şirket B', 'is_active' => true, 'tax_number' => '2222222222']);

        // Aynı sorgu, farklı geçerli legal_entity_id filtreleri ile
        $first  = $this->service->askAssistant($this->user->id, 'Nakit akışım nedir?', ['legal_entity_id' => $leA->id]);
        $second = $this->service->askAssistant($this->user->id, 'Nakit akışım nedir?', ['legal_entity_id' => $leB->id]);

        // Farklı filtreler olduğu için duplicate guard'a girmemeli ve yeni bir sorgu kaydetmeli
        $this->assertNotEquals($first->id, $second->id);
    }
}
