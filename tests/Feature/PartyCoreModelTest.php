<?php

namespace Tests\Feature;

use App\Models\CrmContact;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\Party;
use App\Models\PartyIdentity;
use App\Models\PartyRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartyCoreModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_party_type_defaults_to_unknown(): void
    {
        $user = User::factory()->create();
        $party = Party::create([
            'user_id' => $user->id,
            'display_name' => 'Defaults Party',
        ]);

        $fresh = $party->fresh();
        $this->assertSame('unknown', $fresh->party_type);
        $this->assertSame('active', $fresh->status);
        $this->assertFalse((bool) $fresh->is_blacklisted);
    }

    public function test_party_belongs_to_user_and_optional_legal_entity(): void
    {
        $user = User::factory()->create();
        $legalEntity = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'My Company A.S.',
            'tax_number' => '1234567890',
        ]);

        $party = Party::create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'display_name' => 'Customer With Entity',
            'party_type' => 'organization',
        ]);

        $this->assertTrue($party->user->is($user));
        $this->assertTrue($party->legalEntity->is($legalEntity));
    }

    public function test_party_legal_entity_is_optional(): void
    {
        $user = User::factory()->create();
        $party = Party::create([
            'user_id' => $user->id,
            'display_name' => 'No Entity Party',
            'party_type' => 'person',
        ]);

        $this->assertNull($party->legal_entity_id);
        $this->assertNull($party->legalEntity);
    }

    public function test_party_can_have_roles_and_identities(): void
    {
        $user = User::factory()->create();
        $party = Party::create([
            'user_id' => $user->id,
            'display_name' => 'Related Party',
            'party_type' => 'organization',
        ]);

        PartyRole::create(['user_id' => $user->id, 'party_id' => $party->id, 'role' => 'customer', 'is_primary' => true]);
        PartyRole::create(['user_id' => $user->id, 'party_id' => $party->id, 'role' => 'supplier']);
        PartyIdentity::create(['user_id' => $user->id, 'party_id' => $party->id, 'source_type' => 'manual', 'identity_kind' => 'email', 'identity_value' => 'rel@test.com']);

        $this->assertCount(2, $party->roles);
        $this->assertCount(1, $party->identities);
        $this->assertCount(1, $party->customers);
        $this->assertCount(1, $party->suppliers);
    }
    public function test_party_role_belongs_to_party(): void
    {
        $user = User::factory()->create();
        $party = Party::create(['user_id' => $user->id, 'display_name' => 'Role Parent', 'party_type' => 'unknown']);
        $role = PartyRole::create(['user_id' => $user->id, 'party_id' => $party->id, 'role' => 'customer']);

        $this->assertTrue($role->party->is($party));
        $this->assertTrue($role->user->is($user));
    }

    public function test_party_identity_belongs_to_party_and_optional_store(): void
    {
        $user = User::factory()->create();
        $legalEntity = LegalEntity::create(['user_id' => $user->id, 'name' => 'LE', 'tax_number' => '1111111111']);
        $store = MarketplaceStore::create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'TY Store',
        ]);
        $party = Party::create(['user_id' => $user->id, 'display_name' => 'Store Party', 'party_type' => 'organization']);

        $identity = PartyIdentity::create([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'store_id' => $store->id,
            'source_type' => 'marketplace',
            'identity_kind' => 'external_customer_id',
            'identity_value' => 'ty-12345',
        ]);

        $this->assertTrue($identity->party->is($party));
        $this->assertTrue($identity->store->is($store));
    }

    public function test_party_identity_store_is_optional(): void
    {
        $user = User::factory()->create();
        $party = Party::create(['user_id' => $user->id, 'display_name' => 'No Store', 'party_type' => 'unknown']);
        $identity = PartyIdentity::create([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'source_type' => 'manual',
            'identity_kind' => 'phone',
            'identity_value' => '905320000000',
        ]);

        $this->assertNull($identity->store_id);
        $this->assertNull($identity->store);
    }


    public function test_crm_contact_belongs_to_party(): void
    {
        $user = User::factory()->create();
        $party = Party::create(['user_id' => $user->id, 'display_name' => 'Contact Party', 'party_type' => 'person']);
        $contact = CrmContact::create([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'display_name' => 'Contact With Party',
            'status' => 'active',
        ]);

        $this->assertTrue($contact->party->is($party));
        $this->assertCount(1, $party->crmContacts);
    }

    public function test_crm_contact_without_party_works(): void
    {
        $user = User::factory()->create();
        $contact = CrmContact::create([
            'user_id' => $user->id,
            'display_name' => 'Legacy Contact',
            'status' => 'active',
        ]);

        $this->assertNull($contact->party_id);
        $this->assertNull($contact->party);
    }

    public function test_party_cascade_deletes_roles_and_identities(): void
    {
        $user = User::factory()->create();
        $party = Party::create(['user_id' => $user->id, 'display_name' => 'Cascade Party', 'party_type' => 'unknown']);
        PartyRole::create(['user_id' => $user->id, 'party_id' => $party->id, 'role' => 'customer']);
        PartyIdentity::create(['user_id' => $user->id, 'party_id' => $party->id, 'source_type' => 'manual', 'identity_kind' => 'email', 'identity_value' => 'casc@test.com']);

        $party->delete();

        $this->assertSame(0, PartyRole::where('party_id', $party->id)->count());
        $this->assertSame(0, PartyIdentity::where('party_id', $party->id)->count());
    }

    public function test_party_user_isolation(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Party::create(['user_id' => $userA->id, 'display_name' => 'A Party', 'party_type' => 'unknown']);
        Party::create(['user_id' => $userB->id, 'display_name' => 'B Party', 'party_type' => 'unknown']);

        $this->assertSame(1, Party::where('user_id', $userA->id)->count());
        $this->assertSame(1, Party::where('user_id', $userB->id)->count());
    }


}
