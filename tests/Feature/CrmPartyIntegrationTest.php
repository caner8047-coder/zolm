<?php

namespace Tests\Feature;

use App\Models\CrmContact;
use App\Models\Party;
use App\Models\PartyLedgerEntry;
use App\Models\PartyRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CrmPartyIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_party_badge_hidden_when_feature_flag_disabled(): void
    {
        config()->set('marketplace.features.party_core_enabled', false);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user->id]);
        $contact = CrmContact::create([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'display_name' => 'Test Contact',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get(route('crm.workspace'));

        $response->assertDontSee('Party Bağlı');
        $response->assertDontSee('Bağlanmamış');
    }

    public function test_party_badge_shown_when_party_attached(): void
    {
        config()->set('marketplace.features.party_core_enabled', true);
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user->id]);
        $contact = CrmContact::create([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'display_name' => 'Test Contact',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get(route('crm.workspace'));

        $response->assertSee('Party Bağlı');
    }

    public function test_party_badge_shows_unlinked_when_no_party(): void
    {
        config()->set('marketplace.features.party_core_enabled', true);
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $contact = CrmContact::create([
            'user_id' => $user->id,
            'display_name' => 'Unlinked Contact',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get(route('crm.workspace'));

        $response->assertSee('Bağlanmamış');
    }

    public function test_crm_360_shows_party_not_linked_when_no_party(): void
    {
        config()->set('marketplace.features.party_core_enabled', true);
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $contact = CrmContact::create([
            'user_id' => $user->id,
            'display_name' => 'No Party Contact',
            'status' => 'active',
        ]);

        Livewire::actingAs($user)
            ->test('crm-customer-ledger')
            ->set('selectedContactId', $contact->id)
            ->assertSee('Party bağlı değil');
    }

    public function test_crm_360_shows_balance_summary_when_party_attached(): void
    {
        config()->set('marketplace.features.party_core_enabled', true);
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user->id]);
        $contact = CrmContact::create([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'display_name' => 'Party Contact',
            'status' => 'active',
        ]);

        PartyLedgerEntry::create([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'document_type' => 'receivable',
            'document_date' => now()->toDateString(),
            'debit_amount' => 1000,
            'credit_amount' => 0,
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test('crm-customer-ledger')
            ->set('selectedContactId', $contact->id)
            ->assertSee('Cari Açık Hesap Özeti')
            ->assertSee('1.000,00');
    }

    public function test_balance_calculation_follows_debit_credit_rule(): void
    {
        config()->set('marketplace.features.party_core_enabled', true);
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user->id]);
        $contact = CrmContact::create([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'display_name' => 'Balance Contact',
            'status' => 'active',
        ]);

        PartyLedgerEntry::create([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'document_type' => 'receivable',
            'document_date' => now()->toDateString(),
            'debit_amount' => 1000,
            'credit_amount' => 0,
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        PartyLedgerEntry::create([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'document_type' => 'collection',
            'document_date' => now()->toDateString(),
            'debit_amount' => 0,
            'credit_amount' => 400,
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test('crm-customer-ledger')
            ->set('selectedContactId', $contact->id)
            ->assertSee('1.000,00')
            ->assertSee('400,00')
            ->assertSee('600,00');
    }

    public function test_party_roles_are_displayed(): void
    {
        config()->set('marketplace.features.party_core_enabled', true);
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user->id]);
        PartyRole::create(['user_id' => $user->id, 'party_id' => $party->id, 'role' => 'customer']);
        $contact = CrmContact::create([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'display_name' => 'Role Contact',
            'status' => 'active',
        ]);

        Livewire::actingAs($user)
            ->test('crm-customer-ledger')
            ->set('selectedContactId', $contact->id)
            ->assertSee('customer');
    }

    public function test_party_link_not_shown_when_feature_flag_disabled(): void
    {
        config()->set('marketplace.features.party_core_enabled', false);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user->id]);
        $contact = CrmContact::create([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'display_name' => 'Hidden Contact',
            'status' => 'active',
        ]);

        Livewire::actingAs($user)
            ->test('crm-customer-ledger')
            ->set('selectedContactId', $contact->id)
            ->assertDontSee('Cari Açık Hesap Özeti');
    }

    public function test_party_link_not_shown_when_accounting_disabled(): void
    {
        config()->set('marketplace.features.party_core_enabled', true);
        config()->set('marketplace.features.accounting_enabled', false);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user->id]);
        $contact = CrmContact::create([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'display_name' => 'Hidden Contact 2',
            'status' => 'active',
        ]);

        Livewire::actingAs($user)
            ->test('crm-customer-ledger')
            ->set('selectedContactId', $contact->id)
            ->assertDontSee('Cari Açık Hesap Özeti');
    }

    public function test_summary_service_strict_tenant_security(): void
    {
        config()->set('marketplace.features.crm_enabled', true);
        config()->set('marketplace.features.party_core_enabled', true);
        config()->set('marketplace.features.accounting_enabled', true);

        $user1 = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $user2 = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        // User1'e ait contact ve party
        $party1 = Party::create(['user_id' => $user1->id, 'display_name' => 'Party 1', 'status' => 'active', 'party_type' => 'customer']);
        $contact1 = CrmContact::create([
            'user_id' => $user1->id,
            'party_id' => $party1->id,
            'display_name' => 'Contact 1',
            'status' => 'active',
        ]);

        // User2'ye ait contact
        $contact2 = CrmContact::create([
            'user_id' => $user2->id,
            'display_name' => 'Contact 2',
            'status' => 'active',
        ]);

        $service = app(\App\Services\Crm\CrmAccountingSummaryService::class);

        // 1. User1, User2'nin contact özetini çekememeli (boş/disabled dönmeli)
        $summary = $service->summaryForContact($user1, $contact2);
        $this->assertFalse($summary['enabled']);

        // User2'ye ait party oluştur
        $party2 = Party::create(['user_id' => $user2->id, 'display_name' => 'Party 2', 'status' => 'active', 'party_type' => 'customer']);

        // 2. Contact'ın party_id'si başka kullanıcıya aitse, resolve edilmemeli (has_party false olmalı)
        $contact1->party_id = $party2->id; // Başkasının party'si
        $contact1->save();
        $summary2 = $service->summaryForContact($user1, $contact1);
        $this->assertTrue($summary2['enabled']);
        $this->assertFalse($summary2['has_party']);
    }

    public function test_summary_service_balance_calculation_and_directions(): void
    {
        config()->set('marketplace.features.crm_enabled', true);
        config()->set('marketplace.features.party_core_enabled', true);
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::create(['user_id' => $user->id, 'display_name' => 'Test Party', 'status' => 'active', 'party_type' => 'customer']);
        $contact = CrmContact::create([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'display_name' => 'Test Contact',
            'status' => 'active',
        ]);

        $service = app(\App\Services\Crm\CrmAccountingSummaryService::class);

        // 1. Dengede
        $summary = $service->summaryForContact($user, $contact);
        $this->assertSame('balanced', $summary['balance']['direction']);

        // 2. Alacaklı (Biz alacaklıyız)
        $entry1 = PartyLedgerEntry::create([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'document_type' => 'receivable',
            'document_date' => now()->toDateString(),
            'debit_amount' => 1500.0,
            'credit_amount' => 0.0,
            'status' => 'posted',
            'posted_at' => now(),
        ]);
        $summary2 = $service->summaryForContact($user, $contact);
        $this->assertSame('receivable', $summary2['balance']['direction']);
        $this->assertEquals(1500.0, $summary2['balance']['net_balance']);

        // 3. Borçlu (Biz borçluyuz)
        PartyLedgerEntry::create([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'document_type' => 'collection',
            'document_date' => now()->toDateString(),
            'debit_amount' => 0.0,
            'credit_amount' => 2000.0,
            'status' => 'posted',
            'posted_at' => now(),
        ]);
        $summary3 = $service->summaryForContact($user, $contact);
        $this->assertSame('payable', $summary3['balance']['direction']);
        $this->assertEquals(-500.0, $summary3['balance']['net_balance']);

        // 4. Voided entry bakiyeye dahil edilmemeli
        $entry1->update(['status' => 'voided', 'voided_at' => now()]);
        $summary4 = $service->summaryForContact($user, $contact);
        // debit=0, credit=2000 => balance=-2000
        $this->assertEquals(-2000.0, $summary4['balance']['net_balance']);
    }

    public function test_summary_service_open_totals_and_latest_entries_limit(): void
    {
        config()->set('marketplace.features.crm_enabled', true);
        config()->set('marketplace.features.party_core_enabled', true);
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::create(['user_id' => $user->id, 'display_name' => 'Test Party', 'status' => 'active', 'party_type' => 'customer']);
        $contact = CrmContact::create([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'display_name' => 'Test Contact',
            'status' => 'active',
        ]);

        // 10 adet hareket ekleyelim
        for ($i = 0; $i < 10; $i++) {
            PartyLedgerEntry::create([
                'user_id' => $user->id,
                'party_id' => $party->id,
                'document_type' => 'receivable',
                'document_date' => now()->subDays($i)->toDateString(),
                'debit_amount' => 100.0,
                'credit_amount' => 0.0,
                'status' => 'posted',
                'posted_at' => now(),
                'description' => "Entry $i",
            ]);
        }

        $service = app(\App\Services\Crm\CrmAccountingSummaryService::class);
        $summary = $service->summaryForContact($user, $contact);

        // En fazla 8 tane çekmeli (latestLedgerEntries limiti 8)
        $this->assertCount(8, $summary['latest_entries']);
        // Sıralama document_date desc olmalı
        $this->assertEquals(now()->toDateString(), $summary['latest_entries'][0]['document_date']);
    }

    public function test_workspace_renders_accounting_summary_when_enabled(): void
    {
        config()->set('marketplace.features.crm_enabled', true);
        config()->set('marketplace.features.party_core_enabled', true);
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::create(['user_id' => $user->id, 'display_name' => 'Test Party', 'status' => 'active', 'party_type' => 'customer']);
        $contact = CrmContact::create([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'display_name' => 'Test Contact',
            'status' => 'active',
        ]);

        PartyLedgerEntry::create([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'document_type' => 'receivable',
            'document_date' => now()->toDateString(),
            'debit_amount' => 500.0,
            'credit_amount' => 0.0,
            'status' => 'posted',
            'posted_at' => now(),
            'description' => 'Test Receivable Item',
        ]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\CrmWorkspace::class)
            ->set('selectedContactId', $contact->id)
            ->assertSee('Cari / Muhasebe Özeti')
            ->assertSee('Party Bağlı')
            ->assertSee('Biz alacaklıyız')
            ->assertSee('500,00')
            ->assertSee('Test Receivable Item')
            ->assertSee('Muhasebede Cari Aç');
    }

    public function test_workspace_hides_accounting_summary_when_flag_disabled(): void
    {
        config()->set('marketplace.features.crm_enabled', true);
        config()->set('marketplace.features.party_core_enabled', false); // Kapalı

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::create(['user_id' => $user->id, 'display_name' => 'Test Party', 'status' => 'active', 'party_type' => 'customer']);
        $contact = CrmContact::create([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'display_name' => 'Test Contact',
            'status' => 'active',
        ]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\CrmWorkspace::class)
            ->set('selectedContactId', $contact->id)
            ->assertDontSee('Cari / Muhasebe Özeti');
    }
}
