<?php

namespace Tests\Feature;

use App\Models\Party;
use App\Models\PartyIdentity;
use App\Models\PartyLedgerEntry;
use App\Models\PartyRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PartiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_is_blocked_when_accounting_enabled_is_false(): void
    {
        config()->set('marketplace.features.accounting_enabled', false);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $this->actingAs($user)
            ->get(route('accounting.parties'))
            ->assertStatus(404);
    }

    public function test_page_renders_when_accounting_enabled_is_true(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $this->actingAs($user)
            ->get(route('accounting.parties'))
            ->assertStatus(200)
            ->assertSeeLivewire('accounting.parties')
            ->assertSee('Cari Kartları');
    }

    public function test_creating_party_card_writes_roles_and_manual_identities(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        Livewire::actingAs($user)
            ->test('accounting.parties')
            ->set('displayName', 'Acme Tekstil A.Ş.')
            ->set('partyType', 'organization')
            ->set('primaryEmail', 'finans@acme.test')
            ->set('primaryPhone', '+90 532 111 22 33')
            ->set('taxNumber', '1234567890')
            ->set('taxOffice', 'Kadıköy')
            ->set('city', 'İstanbul')
            ->set('district', 'Kadıköy')
            ->set('roles', ['customer', 'supplier'])
            ->call('saveParty')
            ->assertSet('messageType', 'success')
            ->assertSet('showForm', false);

        $party = Party::where('user_id', $user->id)->where('display_name', 'Acme Tekstil A.Ş.')->firstOrFail();

        $this->assertDatabaseHas('party_roles', [
            'user_id' => $user->id,
            'party_id' => $party->id,
            'role' => 'customer',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('party_roles', [
            'user_id' => $user->id,
            'party_id' => $party->id,
            'role' => 'supplier',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('party_identities', [
            'user_id' => $user->id,
            'party_id' => $party->id,
            'source_type' => 'manual',
            'identity_kind' => 'phone',
            'identity_value' => '905321112233',
        ]);
    }

    public function test_party_card_can_be_updated_and_passivated(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create([
            'user_id' => $user->id,
            'display_name' => 'Eski Cari',
            'party_type' => 'unknown',
        ]);

        PartyRole::create([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'role' => 'customer',
            'status' => 'active',
            'assigned_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test('accounting.parties')
            ->call('editParty', $party->id)
            ->set('displayName', 'Yeni Cari')
            ->set('partyType', 'person')
            ->set('roles', ['supplier'])
            ->call('saveParty')
            ->assertSet('messageType', 'success');

        $this->assertDatabaseHas('parties', [
            'id' => $party->id,
            'display_name' => 'Yeni Cari',
            'party_type' => 'person',
        ]);

        $this->assertDatabaseHas('party_roles', [
            'party_id' => $party->id,
            'role' => 'customer',
            'status' => 'passive',
        ]);

        $this->assertDatabaseHas('party_roles', [
            'party_id' => $party->id,
            'role' => 'supplier',
            'status' => 'active',
        ]);

        Livewire::actingAs($user)
            ->test('accounting.parties')
            ->call('markPassive', $party->id)
            ->assertSet('messageType', 'success');

        $this->assertSame('passive', $party->fresh()->status);
    }

    public function test_party_list_is_user_isolated_and_shows_balance(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user1 = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $user2 = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $party1 = Party::factory()->create(['user_id' => $user1->id, 'display_name' => 'User1 Cari']);
        Party::factory()->create(['user_id' => $user2->id, 'display_name' => 'User2 Cari']);

        PartyLedgerEntry::create([
            'user_id' => $user1->id,
            'party_id' => $party1->id,
            'document_type' => 'manual_receivable',
            'document_date' => now()->toDateString(),
            'debit_amount' => 100,
            'credit_amount' => 25,
            'debit_base_amount' => 100,
            'credit_base_amount' => 25,
            'status' => 'posted',
        ]);

        Livewire::actingAs($user1)
            ->test('accounting.parties')
            ->assertSee('User1 Cari')
            ->assertDontSee('User2 Cari')
            ->assertSee('₺75,00');
    }
}
