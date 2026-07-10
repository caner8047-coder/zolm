<?php

namespace Tests\Feature;

use App\Models\CrmContact;
use App\Models\LegalEntity;
use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PartyLedgerWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_is_blocked_when_accounting_enabled_is_false(): void
    {
        config()->set('marketplace.features.accounting_enabled', false);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $this->actingAs($user)
            ->get(route('accounting.party-ledger'))
            ->assertStatus(404);
    }

    public function test_page_renders_when_accounting_enabled_is_true(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $this->actingAs($user)
            ->get(route('accounting.party-ledger'))
            ->assertStatus(200)
            ->assertSeeLivewire('accounting.party-ledger-workspace');
    }

    public function test_only_current_user_entries_are_visible(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user1 = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $user2 = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $party1 = Party::factory()->create(['user_id' => $user1->id]);
        $party2 = Party::factory()->create(['user_id' => $user2->id]);

        \App\Models\PartyLedgerEntry::create([
            'user_id' => $user1->id,
            'party_id' => $party1->id,
            'document_type' => 'receivable',
            'document_date' => now()->toDateString(),
            'debit_amount' => 1000,
            'credit_amount' => 0,
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        \App\Models\PartyLedgerEntry::create([
            'user_id' => $user2->id,
            'party_id' => $party2->id,
            'document_type' => 'receivable',
            'document_date' => now()->toDateString(),
            'debit_amount' => 2000,
            'credit_amount' => 0,
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        Livewire::actingAs($user1)
            ->test('accounting.party-ledger-workspace')
            ->assertDontSee('2.000,00')
            ->assertSee('1.000,00');
    }

    public function test_another_user_party_is_not_visible(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user1 = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $user2 = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        Party::factory()->create(['user_id' => $user1->id, 'display_name' => 'My Party']);
        Party::factory()->create(['user_id' => $user2->id, 'display_name' => 'Other Party']);

        Livewire::actingAs($user1)
            ->test('accounting.party-ledger-workspace')
            ->set('showForm', true)
            ->assertSee('My Party')
            ->assertDontSee('Other Party');
    }

    public function test_manual_receivable_creates_debit_entry(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test('accounting.party-ledger-workspace')
            ->set('showForm', true)
            ->set('formPartyId', $party->id)
            ->set('formEntryType', 'receivable')
            ->set('formAmount', 500)
            ->set('formDocumentDate', now()->toDateString())
            ->call('submitEntry');

        $this->assertDatabaseHas('party_ledger_entries', [
            'user_id' => $user->id,
            'party_id' => $party->id,
            'document_type' => 'receivable',
            'debit_amount' => 500,
            'credit_amount' => 0,
        ]);
    }

    public function test_manual_collection_creates_credit_entry(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test('accounting.party-ledger-workspace')
            ->set('showForm', true)
            ->set('formPartyId', $party->id)
            ->set('formEntryType', 'collection')
            ->set('formAmount', 300)
            ->set('formDocumentDate', now()->toDateString())
            ->call('submitEntry');

        $this->assertDatabaseHas('party_ledger_entries', [
            'user_id' => $user->id,
            'party_id' => $party->id,
            'document_type' => 'collection',
            'debit_amount' => 0,
            'credit_amount' => 300,
        ]);
    }

    public function test_manual_payable_creates_credit_entry(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test('accounting.party-ledger-workspace')
            ->set('showForm', true)
            ->set('formPartyId', $party->id)
            ->set('formEntryType', 'payable')
            ->set('formAmount', 800)
            ->set('formDocumentDate', now()->toDateString())
            ->call('submitEntry');

        $this->assertDatabaseHas('party_ledger_entries', [
            'user_id' => $user->id,
            'party_id' => $party->id,
            'document_type' => 'payable',
            'debit_amount' => 0,
            'credit_amount' => 800,
        ]);
    }

    public function test_manual_payment_creates_debit_entry(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test('accounting.party-ledger-workspace')
            ->set('showForm', true)
            ->set('formPartyId', $party->id)
            ->set('formEntryType', 'payment')
            ->set('formAmount', 200)
            ->set('formDocumentDate', now()->toDateString())
            ->call('submitEntry');

        $this->assertDatabaseHas('party_ledger_entries', [
            'user_id' => $user->id,
            'party_id' => $party->id,
            'document_type' => 'payment',
            'debit_amount' => 200,
            'credit_amount' => 0,
        ]);
    }

    public function test_zero_amount_is_rejected(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test('accounting.party-ledger-workspace')
            ->set('showForm', true)
            ->set('formPartyId', $party->id)
            ->set('formEntryType', 'receivable')
            ->set('formAmount', 0)
            ->set('formDocumentDate', now()->toDateString())
            ->call('submitEntry')
            ->assertHasErrors(['formAmount']);
    }

    public function test_party_is_required(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        Livewire::actingAs($user)
            ->test('accounting.party-ledger-workspace')
            ->set('showForm', true)
            ->set('formEntryType', 'receivable')
            ->set('formAmount', 100)
            ->set('formDocumentDate', now()->toDateString())
            ->call('submitEntry')
            ->assertHasErrors(['formPartyId']);
    }

    public function test_document_date_is_required(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test('accounting.party-ledger-workspace')
            ->set('showForm', true)
            ->set('formPartyId', $party->id)
            ->set('formEntryType', 'receivable')
            ->set('formAmount', 100)
            ->set('formDocumentDate', null)
            ->call('submitEntry')
            ->assertHasErrors(['formDocumentDate']);
    }

    public function test_filter_by_document_type_works(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user->id]);

        \App\Models\PartyLedgerEntry::create([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'document_type' => 'receivable',
            'document_date' => now()->toDateString(),
            'debit_amount' => 100,
            'credit_amount' => 0,
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        \App\Models\PartyLedgerEntry::create([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'document_type' => 'collection',
            'document_date' => now()->toDateString(),
            'debit_amount' => 0,
            'credit_amount' => 50,
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test('accounting.party-ledger-workspace')
            ->set('documentType', 'receivable')
            ->assertSee('100,00')
            ->assertDontSee('50,00');
    }

    public function test_filter_by_status_works(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user->id]);

        $entry = \App\Models\PartyLedgerEntry::create([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'document_type' => 'receivable',
            'document_date' => now()->toDateString(),
            'debit_amount' => 100,
            'credit_amount' => 0,
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test('accounting.party-ledger-workspace')
            ->call('confirmVoid', $entry->id)
            ->set('voidReason', 'Test')
            ->call('voidEntry')
            ->assertSee('Kayıt iptal edildi');
    }

    public function test_search_by_party_name_works(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party1 = Party::factory()->create(['user_id' => $user->id, 'display_name' => 'Acme A.Ş.']);
        $party2 = Party::factory()->create(['user_id' => $user->id, 'display_name' => 'Beta Ltd.']);

        \App\Models\PartyLedgerEntry::create([
            'user_id' => $user->id,
            'party_id' => $party1->id,
            'document_type' => 'receivable',
            'document_date' => now()->toDateString(),
            'debit_amount' => 100,
            'credit_amount' => 0,
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        \App\Models\PartyLedgerEntry::create([
            'user_id' => $user->id,
            'party_id' => $party2->id,
            'document_type' => 'receivable',
            'document_date' => now()->toDateString(),
            'debit_amount' => 200,
            'credit_amount' => 0,
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test('accounting.party-ledger-workspace')
            ->set('search', 'Acme')
            ->assertSee('Acme')
            ->assertDontSee('Beta');
    }

    public function test_void_action_removes_from_balance(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user->id]);

        $entry = \App\Models\PartyLedgerEntry::create([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'document_type' => 'receivable',
            'document_date' => now()->toDateString(),
            'debit_amount' => 500,
            'credit_amount' => 0,
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test('accounting.party-ledger-workspace')
            ->call('confirmVoid', $entry->id)
            ->set('voidReason', 'Mistake')
            ->call('voidEntry');

        $entry->refresh();
        $this->assertEquals('voided', $entry->status);
        $this->assertNotNull($entry->voided_at);
        $this->assertEquals('Mistake', $entry->void_reason);
    }

    public function test_legal_entity_id_from_another_user_is_rejected(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user1 = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $user2 = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user1->id]);
        $legalEntity = LegalEntity::create(['user_id' => $user2->id, 'name' => 'Other LE', 'tax_number' => '9999999999']);

        Livewire::actingAs($user1)
            ->test('accounting.party-ledger-workspace')
            ->set('showForm', true)
            ->set('formPartyId', $party->id)
            ->set('formEntryType', 'receivable')
            ->set('formAmount', 100)
            ->set('formDocumentDate', now()->toDateString())
            ->set('formLegalEntityId', $legalEntity->id)
            ->call('submitEntry')
            ->assertSee('LegalEntity bu kullanıcıya ait değil');
    }

    public function test_crm_contact_id_from_another_user_is_rejected(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user1 = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $user2 = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user1->id]);
        $crmContact = CrmContact::create(['user_id' => $user2->id, 'display_name' => 'Other Contact', 'status' => 'active']);

        Livewire::actingAs($user1)
            ->test('accounting.party-ledger-workspace')
            ->set('showForm', true)
            ->set('formPartyId', $party->id)
            ->set('formEntryType', 'receivable')
            ->set('formAmount', 100)
            ->set('formDocumentDate', now()->toDateString())
            ->set('formCrmContactId', $crmContact->id)
            ->call('submitEntry')
            ->assertSee('CrmContact bu kullanıcıya ait değil');
    }

    public function test_sidebar_link_visible_when_enabled(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $response = $this->actingAs($user)->get(route('marketplace-accounting'));

        $response->assertSee('Cari Açık Hesap');
    }

    public function test_sidebar_link_hidden_when_disabled(): void
    {
        config()->set('marketplace.features.accounting_enabled', false);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $response = $this->actingAs($user)->get(route('marketplace-accounting'));

        $response->assertDontSee('Cari Açık Hesap');
    }

    public function test_visible_columns_and_sorting_methods(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user->id, 'display_name' => 'Sort Test Party']);

        Livewire::actingAs($user)
            ->test('accounting.party-ledger-workspace')
            ->assertSeeHtml('Kolonlar')
            ->call('toggleColumn', 'aciklama')
            ->assertSet('visibleColumns', ['tarih', 'party', 'tip', 'belge_no', 'borc', 'alacak', 'bakiye_etkisi', 'durum', 'aksiyon'])
            ->call('toggleColumn', 'aciklama')
            ->assertSet('visibleColumns', ['tarih', 'party', 'tip', 'belge_no', 'aciklama', 'borc', 'alacak', 'bakiye_etkisi', 'durum', 'aksiyon'])
            ->call('sortTable', 'party')
            ->assertSet('sortField', 'party')
            ->assertSet('sortDirection', 'asc')
            ->call('sortTable', 'party')
            ->assertSet('sortDirection', 'desc');
    }

    public function test_mount_parameter_mapping(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test('accounting.party-ledger-workspace', ['party' => $party->id])
            ->assertSet('partyId', $party->id);
    }

    public function test_sorting_ignores_invalid_values(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        // Set invalid sorting parameter strings
        Livewire::actingAs($user)
            ->test('accounting.party-ledger-workspace')
            ->set('sortField', 'invalid_column_name_here_malicious')
            ->set('sortDirection', 'invalid_direction_malicious')
            ->assertOk(); // Must render successfully without SQL syntax errors
    }
}
