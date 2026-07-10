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
}
