<?php

namespace Tests\Feature;

use App\Models\CrmContact;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\Party;
use App\Models\PartyIdentity;
use App\Models\PartyRole;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PartyCoreMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_parties_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('parties'));

        $columns = [
            'id', 'user_id', 'legal_entity_id', 'display_name', 'normalized_name',
            'party_type', 'primary_email', 'primary_phone', 'normalized_phone',
            'tax_number', 'tax_office', 'city', 'district', 'status',
            'is_blacklisted', 'meta_json', 'created_at', 'updated_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(Schema::hasColumn('parties', $column), "Missing column: {$column}");
        }
    }

    public function test_party_roles_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('party_roles'));

        foreach (['id', 'user_id', 'party_id', 'role', 'role_code', 'is_primary', 'status', 'assigned_at', 'meta_json', 'created_at', 'updated_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('party_roles', $column), "Missing column: {$column}");
        }
    }

    public function test_party_identities_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('party_identities'));

        foreach (['id', 'user_id', 'party_id', 'store_id', 'source_type', 'identity_kind', 'identity_value', 'external_id', 'confidence', 'raw_payload', 'created_at', 'updated_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('party_identities', $column), "Missing column: {$column}");
        }
    }

    public function test_crm_contacts_has_nullable_party_id_column(): void
    {
        $this->assertTrue(Schema::hasColumn('crm_contacts', 'party_id'));

        $user = User::factory()->create();
        $contact = CrmContact::create([
            'user_id' => $user->id,
            'display_name' => 'No Party Contact',
            'status' => 'active',
        ]);

        $this->assertNull($contact->fresh()->party_id);
    }

    public function test_party_roles_unique_prevents_duplicate_role_per_party(): void
    {
        $user = User::factory()->create();
        $party = Party::create([
            'user_id' => $user->id,
            'display_name' => 'Test Party',
            'party_type' => 'unknown',
        ]);

        PartyRole::create([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'role' => 'customer',
        ]);

        try {
            PartyRole::create([
                'user_id' => $user->id,
                'party_id' => $party->id,
                'role' => 'customer',
            ]);
            $this->fail('Expected unique violation for duplicate party role');
        } catch (QueryException $e) {
            $this->assertStringContainsStringIgnoringCase('unique', $e->getMessage());
        }

        $this->assertSame(1, PartyRole::where('party_id', $party->id)->where('role', 'customer')->count());
    }

    public function test_party_roles_allows_multiple_roles_for_same_party(): void
    {
        $user = User::factory()->create();
        $party = Party::create([
            'user_id' => $user->id,
            'display_name' => 'Multi Role Party',
            'party_type' => 'organization',
        ]);

        PartyRole::create(['user_id' => $user->id, 'party_id' => $party->id, 'role' => 'customer']);
        PartyRole::create(['user_id' => $user->id, 'party_id' => $party->id, 'role' => 'supplier']);

        $this->assertSame(2, PartyRole::where('party_id', $party->id)->count());
    }

    public function test_party_identities_unique_prevents_duplicate_identity(): void
    {
        // store_id DOLU iken DB seviyesinde unique koruması çalışır (pazaryeri kaynağı).
        $user = User::factory()->create();
        $legalEntity = LegalEntity::create(['user_id' => $user->id, 'name' => 'LE', 'tax_number' => '1111111111']);
        $store = MarketplaceStore::create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'TY Store',
        ]);
        $party = Party::create([
            'user_id' => $user->id,
            'display_name' => 'Identity Party',
            'party_type' => 'unknown',
        ]);

        PartyIdentity::create([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'store_id' => $store->id,
            'source_type' => 'marketplace',
            'identity_kind' => 'external_customer_id',
            'identity_value' => 'ty-12345',
        ]);

        try {
            PartyIdentity::create([
                'user_id' => $user->id,
                'party_id' => $party->id,
                'store_id' => $store->id,
                'source_type' => 'marketplace',
                'identity_kind' => 'external_customer_id',
                'identity_value' => 'ty-12345',
            ]);
            $this->fail('Expected unique violation for duplicate party identity');
        } catch (QueryException $e) {
            $this->assertStringContainsStringIgnoringCase('unique', $e->getMessage());
        }

        $this->assertSame(1, PartyIdentity::where('identity_value', 'ty-12345')->count());
    }

    public function test_party_identities_user_isolation_allows_same_value_for_different_users(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $partyA = Party::create(['user_id' => $userA->id, 'display_name' => 'A', 'party_type' => 'unknown']);
        $partyB = Party::create(['user_id' => $userB->id, 'display_name' => 'B', 'party_type' => 'unknown']);

        PartyIdentity::create(['user_id' => $userA->id, 'party_id' => $partyA->id, 'source_type' => 'manual', 'identity_kind' => 'email', 'identity_value' => 'shared@test.com']);
        PartyIdentity::create(['user_id' => $userB->id, 'party_id' => $partyB->id, 'source_type' => 'manual', 'identity_kind' => 'email', 'identity_value' => 'shared@test.com']);

        $this->assertSame(2, PartyIdentity::where('identity_value', 'shared@test.com')->count());
    }
}
