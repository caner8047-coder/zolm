<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\LegalEntity;
use App\Models\Party;
use App\Models\Warehouse;
use App\Models\MpProduct;
use App\Models\Product;
use App\Models\Account;
use App\Models\Receivable;
use App\Models\Payable;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Collection;
use App\Models\Payment;
use App\Models\ReceivableAllocation;
use App\Models\PayableAllocation;
use App\Models\StockBalance;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\TestCase;

class AccountingAccessAndDemoSeedTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────────────────
    // 1. Feature Flag & Yetkilendirme Testleri
    // ─────────────────────────────────────────────────────────────────────────

    public function test_accounting_routes_return_404_when_accounting_disabled(): void
    {
        config()->set('marketplace.features.accounting_enabled', false);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        // 1. Dashboard
        $response = $this->actingAs($user)->get(route('accounting.dashboard'));
        $response->assertStatus(404);

        // 2. Sales
        $response2 = $this->actingAs($user)->get(route('accounting.sales'));
        $response2->assertStatus(404);
    }

    public function test_sidebar_menu_hidden_when_accounting_disabled(): void
    {
        config()->set('marketplace.features.accounting_enabled', false);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $response = $this->actingAs($user)->get(route('ai-chat'));
        $response->assertDontSee('Muhasebe (ERP)');
    }

    public function test_sidebar_menu_visible_when_accounting_enabled(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $response = $this->actingAs($user)->get(route('ai-chat'));
        $response->assertSee('Muhasebe (ERP)');
    }

    public function test_admin_can_access_accounting_dashboard(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $response = $this->actingAs($user)->get(route('accounting.dashboard'));
        $response->assertStatus(200);
    }

    public function test_non_admin_cannot_access_accounting_dashboard(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        // admin / operator olmayan crm sorumlu rolü (check constraint'e uyan, ama operatör yetkisi olmayan)
        $role = \App\Models\Role::create(['name' => 'CRM Sorumlusu', 'slug' => 'crm_sorumlusu']);
        $user = User::factory()->create([
            'is_active' => true,
            'role_id' => $role->id,
            'role' => 'operator',
        ]);
        
        unset($user->role);
        $user->setRelation('role', $role);

        $response = $this->actingAs($user)->get(route('accounting.dashboard'));
        $response->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. Demo Seed Command Testleri
    // ─────────────────────────────────────────────────────────────────────────

    public function test_seed_demo_command_requires_user_id(): void
    {
        $this->artisan('accounting:seed-demo')
            ->expectsOutput('Hata: --user parametresi zorunludur (örn: --user=1).')
            ->assertExitCode(1);
    }

    public function test_seed_demo_command_creates_all_expected_records(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        Artisan::call('accounting:seed-demo', ['--user' => $user->id]);

        // Yasal Birlik
        $this->assertDatabaseHas('legal_entities', [
            'user_id' => $user->id,
            'tax_number' => '1234567890',
        ]);

        // Hesap Planı
        $this->assertDatabaseHas('accounts', [
            'user_id' => $user->id,
            'code' => '120',
            'meta_json->demo' => true,
        ]);
        $this->assertDatabaseHas('accounts', [
            'user_id' => $user->id,
            'code' => '320',
            'meta_json->demo' => true,
        ]);

        // Cariler
        $this->assertDatabaseHas('parties', [
            'user_id' => $user->id,
            'display_name' => 'ZOLM Demo Perakende Müşteri A.Ş.',
            'meta_json->demo' => true,
        ]);
        $this->assertDatabaseHas('parties', [
            'user_id' => $user->id,
            'display_name' => 'ZOLM Demo Ana Sağlayıcı A.Ş.',
            'meta_json->demo' => true,
        ]);

        // Kasa & Banka
        $this->assertDatabaseHas('cash_accounts', ['user_id' => $user->id, 'name' => 'ZOLM Demo Merkez Kasa (TL)']);
        $this->assertDatabaseHas('bank_accounts', ['user_id' => $user->id, 'bank_name' => 'ZOLM Demo Ziraat Bankası (Vadesiz)']);

        // Depo & Ürünler
        $this->assertDatabaseHas('warehouses', ['user_id' => $user->id, 'code' => 'demo-depo-merkez']);
        $this->assertDatabaseHas('mp_products', ['user_id' => $user->id, 'stock_code' => 'demo_prod_1']);

        // Siparişler
        $this->assertDatabaseHas('sales_orders', ['user_id' => $user->id, 'document_number' => 'DEMO-SO-001', 'status' => 'approved']);
        $this->assertDatabaseHas('purchase_orders', ['user_id' => $user->id, 'document_number' => 'DEMO-PO-001', 'status' => 'approved']);

        // Finansal Hareketler
        $this->assertDatabaseHas('receivables', ['user_id' => $user->id, 'document_number' => 'DEMO-SO-001', 'status' => 'partially_paid']);
        $this->assertDatabaseHas('payables', ['user_id' => $user->id, 'document_number' => 'DEMO-PO-001', 'status' => 'partially_paid']);

        $this->assertDatabaseHas('collections', ['user_id' => $user->id, 'source_key' => 'demo_collection_1']);
        $this->assertDatabaseHas('payments', ['user_id' => $user->id, 'source_key' => 'demo_payment_1']);
        $this->assertDatabaseHas('money_transfers', ['user_id' => $user->id, 'source_key' => 'demo_transfer_1']);
    }

    public function test_seed_demo_command_is_idempotent(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        // İlk Seeding
        Artisan::call('accounting:seed-demo', ['--user' => $user->id]);

        $initialParties = Party::where('user_id', $user->id)->count();
        $initialProducts = MpProduct::where('user_id', $user->id)->count();
        $initialJournalEntries = JournalEntry::where('user_id', $user->id)->count();

        // İkinci Seeding
        Artisan::call('accounting:seed-demo', ['--user' => $user->id]);

        $this->assertEquals($initialParties, Party::where('user_id', $user->id)->count());
        $this->assertEquals($initialProducts, MpProduct::where('user_id', $user->id)->count());
        $this->assertEquals($initialJournalEntries, JournalEntry::where('user_id', $user->id)->count());
    }

    public function test_seed_demo_command_reset_clears_only_demo_data(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        // Gerçek kullanıcı verileri oluşturalım
        $realParty = Party::create([
            'user_id' => $user->id,
            'display_name' => 'Gerçek Müşteri A.Ş.',
            'party_type' => 'customer',
        ]);

        // Seeding
        Artisan::call('accounting:seed-demo', ['--user' => $user->id]);

        $this->assertDatabaseHas('parties', [
            'display_name' => 'ZOLM Demo Perakende Müşteri A.Ş.',
            'meta_json->demo' => true,
        ]);

        // Reset ve Yeniden Seeding
        Artisan::call('accounting:seed-demo', ['--user' => $user->id, '--reset' => true]);

        // Demo veriler yine var olmalı (silinip yeniden yüklendi)
        $this->assertDatabaseHas('parties', [
            'display_name' => 'ZOLM Demo Perakende Müşteri A.Ş.',
            'meta_json->demo' => true,
        ]);

        // Gerçek kullanıcı verisine kesinlikle dokunulmamış olmalı
        $this->assertDatabaseHas('parties', ['id' => $realParty->id, 'display_name' => 'Gerçek Müşteri A.Ş.']);
    }

    public function test_demo_isolation_does_not_affect_other_users(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user1 = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $user2 = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        // User1 için seeder çalıştır
        Artisan::call('accounting:seed-demo', ['--user' => $user1->id]);

        // User2 için veritabanında hiçbir demo verisi olmamalı
        $this->assertEquals(0, Party::where('user_id', $user2->id)->count());
        $this->assertEquals(0, MpProduct::where('user_id', $user2->id)->count());
        $this->assertEquals(0, JournalEntry::where('user_id', $user2->id)->count());
    }

    public function test_kpis_and_reports_show_values_after_seed(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        Artisan::call('accounting:seed-demo', ['--user' => $user->id]);

        // Dashboard KPI verisi
        $response = Livewire::actingAs($user)
            ->test(\App\Livewire\Accounting\AccountingDashboard::class)
            ->assertStatus(200);

        // Raporlama Servisi Executive Summary test
        $summary = app(\App\Services\Accounting\ReportService::class)->executiveSummary($user->id);
        $this->assertNotNull($summary);
        
        // Alacak, borç ve kasa banka durumları sıfırdan farklı olmalı
        $this->assertGreaterThan(0, (float) $summary['total_open_receivables']);
        $this->assertGreaterThan(0, (float) $summary['total_open_payables']);
    }

    public function test_reset_does_not_delete_real_receivable_allocations(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        // Gerçek Receivable, Collection ve Allocation oluşturalım
        $realParty = Party::create([
            'user_id' => $user->id,
            'display_name' => 'Gerçek Müşteri A.Ş.',
            'party_type' => 'customer',
        ]);
        $realReceivable = Receivable::create([
            'user_id' => $user->id,
            'party_id' => $realParty->id,
            'document_number' => 'REAL-SO-INV-1',
            'document_date' => now()->toDateString(),
            'amount' => 1000.0,
            'paid_amount' => 400.0,
            'status' => 'partially_paid',
        ]);
        $realCashAccount = Account::create([
            'user_id' => $user->id,
            'code' => '100.99',
            'name' => 'Gerçek Kasa',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_cash_account' => true,
            'is_active' => true,
        ]);
        $realCollection = Collection::create([
            'user_id' => $user->id,
            'party_id' => $realParty->id,
            'account_id' => $realCashAccount->id,
            'collection_date' => now()->toDateString(),
            'amount' => 400.0,
            'source_key' => 'real_coll_key_1',
            'status' => 'posted',
        ]);
        $realAllocation = ReceivableAllocation::create([
            'user_id' => $user->id,
            'receivable_id' => $realReceivable->id,
            'collection_id' => $realCollection->id,
            'amount' => 400.0,
        ]);

        // Demo seed çalıştır
        Artisan::call('accounting:seed-demo', ['--user' => $user->id]);

        // Reset çalıştır
        Artisan::call('accounting:seed-demo', ['--user' => $user->id, '--reset' => true]);

        // Gerçek allocation hâlâ duruyor olmalı
        $this->assertDatabaseHas('receivable_allocations', [
            'id' => $realAllocation->id,
            'amount' => 400.0,
        ]);
    }

    public function test_reset_does_not_delete_real_payable_allocations(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        // Gerçek Payable, Payment ve Allocation oluşturalım
        $realParty = Party::create([
            'user_id' => $user->id,
            'display_name' => 'Gerçek Tedarikçi A.Ş.',
            'party_type' => 'supplier',
        ]);
        $realPayable = Payable::create([
            'user_id' => $user->id,
            'party_id' => $realParty->id,
            'document_number' => 'REAL-PO-INV-1',
            'document_date' => now()->toDateString(),
            'amount' => 2000.0,
            'paid_amount' => 600.0,
            'status' => 'partially_paid',
        ]);
        $realBankAccount = Account::create([
            'user_id' => $user->id,
            'code' => '102.99',
            'name' => 'Gerçek Banka',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_bank_account' => true,
            'is_active' => true,
        ]);
        $realPayment = Payment::create([
            'user_id' => $user->id,
            'party_id' => $realParty->id,
            'account_id' => $realBankAccount->id,
            'payment_date' => now()->toDateString(),
            'amount' => 600.0,
            'source_key' => 'real_pay_key_1',
            'status' => 'posted',
        ]);
        $realAllocation = PayableAllocation::create([
            'user_id' => $user->id,
            'payable_id' => $realPayable->id,
            'payment_id' => $realPayment->id,
            'amount' => 600.0,
        ]);

        // Demo seed çalıştır
        Artisan::call('accounting:seed-demo', ['--user' => $user->id]);

        // Reset çalıştır
        Artisan::call('accounting:seed-demo', ['--user' => $user->id, '--reset' => true]);

        // Gerçek allocation hâlâ duruyor olmalı
        $this->assertDatabaseHas('payable_allocations', [
            'id' => $realAllocation->id,
            'amount' => 600.0,
        ]);
    }

    public function test_reset_does_not_delete_real_journal_lines(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        // Gerçek journal entry ve line'lar oluşturalım
        $realCashAccount = Account::create([
            'user_id' => $user->id,
            'code' => '100.99',
            'name' => 'Gerçek Kasa',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_cash_account' => true,
            'is_active' => true,
        ]);
        $realJournalEntry = JournalEntry::create([
            'user_id' => $user->id,
            'entry_date' => now()->toDateString(),
            'entry_type' => 'manual',
            'source_key' => 'real_journal_entry_key_1',
            'status' => 'posted',
        ]);
        $realJournalLine1 = JournalLine::create([
            'user_id' => $user->id,
            'journal_entry_id' => $realJournalEntry->id,
            'account_id' => $realCashAccount->id,
            'debit_amount' => 1500.0,
            'credit_amount' => 0.0,
            'debit_base_amount' => 1500.0,
            'credit_base_amount' => 0.0,
        ]);

        // Demo seed çalıştır
        Artisan::call('accounting:seed-demo', ['--user' => $user->id]);

        // Reset çalıştır
        Artisan::call('accounting:seed-demo', ['--user' => $user->id, '--reset' => true]);

        // Gerçek journal entry/line durmalı
        $this->assertDatabaseHas('journal_entries', [
            'id' => $realJournalEntry->id,
            'source_key' => 'real_journal_entry_key_1',
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'id' => $realJournalLine1->id,
            'debit_amount' => 1500.0,
        ]);
    }

    public function test_reset_does_not_delete_global_products(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        // Global master product oluşturalım
        $globalProduct = Product::create([
            'stok_kodu' => 'demo_prod_external',
            'urun_adi' => 'Gerçek Global Ürün',
            'parca' => 1,
            'desi' => 5.0,
            'tutar' => 200.0,
            'is_active' => true,
        ]);

        // Demo seed çalıştır
        Artisan::call('accounting:seed-demo', ['--user' => $user->id]);

        // Reset çalıştır
        Artisan::call('accounting:seed-demo', ['--user' => $user->id, '--reset' => true]);

        // Global product silinmemiş olmalı
        $this->assertDatabaseHas('products', [
            'stok_kodu' => 'demo_prod_external',
        ]);
    }

    public function test_demo_records_have_demo_markers(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        // Demo seed çalıştır
        Artisan::call('accounting:seed-demo', ['--user' => $user->id]);

        // 1. Party marker'ı doğrula
        $party = Party::where('user_id', $user->id)->where('display_name', 'ZOLM Demo Perakende Müşteri A.Ş.')->first();
        $this->assertNotNull($party);
        $this->assertEquals('accounting_p14', $party->meta_json['demo_seed'] ?? null);
        $this->assertTrue($party->meta_json['demo'] ?? false);

        // 2. Account marker'ı doğrula
        $account = Account::where('user_id', $user->id)->where('code', '120')->first();
        $this->assertNotNull($account);
        $this->assertEquals('accounting_p14', $account->meta_json['demo_seed'] ?? null);
        $this->assertTrue($account->meta_json['demo'] ?? false);

        // 3. Warehouse marker'ı doğrula
        $warehouse = Warehouse::where('user_id', $user->id)->where('code', 'demo-depo-merkez')->first();
        $this->assertNotNull($warehouse);
        $this->assertEquals('accounting_p14', $warehouse->meta_json['demo_seed'] ?? null);
        $this->assertTrue($warehouse->meta_json['demo'] ?? false);

        // 4. PartyIdentity source_type = 'demo' doğrula
        $identity = \App\Models\PartyIdentity::where('user_id', $user->id)->where('identity_value', 'perakende@demo.zolm.com')->first();
        $this->assertNotNull($identity);
        $this->assertEquals('demo', $identity->source_type);
    }

    public function test_reset_does_not_delete_real_journal_lines_on_demo_legal_entity(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        // Demo seed çalıştırıp legal entity'yi bulalım
        Artisan::call('accounting:seed-demo', ['--user' => $user->id]);
        $demoLe = LegalEntity::where('user_id', $user->id)->where('tax_number', '1234567890')->first();
        $this->assertNotNull($demoLe);

        // Gerçek Kasa GL Hesabı
        $realCashAccount = Account::create([
            'user_id' => $user->id,
            'code' => '100.99',
            'name' => 'Gerçek Kasa',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_cash_account' => true,
            'is_active' => true,
        ]);

        // Demo legal entity'ye bağlı olan, ama source_key 'demo_%' olmayan GERÇEK yevmiye fişi
        $realJournalEntry = JournalEntry::create([
            'user_id' => $user->id,
            'legal_entity_id' => $demoLe->id,
            'entry_date' => now()->toDateString(),
            'entry_type' => 'manual',
            'source_key' => 'real_journal_on_demo_le',
            'status' => 'posted',
        ]);

        $realJournalLine = JournalLine::create([
            'user_id' => $user->id,
            'journal_entry_id' => $realJournalEntry->id,
            'account_id' => $realCashAccount->id,
            'debit_amount' => 5000.0,
            'credit_amount' => 0.0,
            'debit_base_amount' => 5000.0,
            'credit_base_amount' => 0.0,
        ]);

        // Reset çalıştır
        Artisan::call('accounting:seed-demo', ['--user' => $user->id, '--reset' => true]);

        // Gerçek yevmiye fişi ve satırları durmalı (silinmemeli!)
        $this->assertDatabaseHas('journal_entries', [
            'id' => $realJournalEntry->id,
            'source_key' => 'real_journal_on_demo_le',
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'id' => $realJournalLine->id,
            'debit_amount' => 5000.0,
        ]);
    }
}
