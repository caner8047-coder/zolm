<?php

namespace Tests\Feature;

use App\Models\CrmContact;
use App\Models\CrmContactIdentity;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\Party;
use App\Models\PartyIdentity;
use App\Models\PartyRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartyBackfillFromCrmCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_does_not_write_any_data(): void
    {
        $user = User::factory()->create();
        $contact = $this->createContact($user, ['display_name' => 'Dry Run Contact']);

        $this->artisan('party:backfill-from-crm', [
            '--user-id' => $user->id,
            '--dry-run' => true,
        ])->assertExitCode(0);

        $this->assertNull($contact->fresh()->party_id);
        $this->assertSame(0, Party::where('user_id', $user->id)->count());
        $this->assertSame(0, PartyRole::where('user_id', $user->id)->count());
        $this->assertSame(0, PartyIdentity::where('user_id', $user->id)->count());
    }

    public function test_flag_disabled_blocks_real_run_without_force(): void
    {
        // party_core_enabled default false
        $user = User::factory()->create();
        $this->createContact($user, ['display_name' => 'Blocked Contact']);

        $this->artisan('party:backfill-from-crm', [
            '--user-id' => $user->id,
        ])->assertExitCode(1);

        $this->assertSame(0, Party::where('user_id', $user->id)->count());
    }

    public function test_force_allows_real_run_when_flag_disabled(): void
    {
        $user = User::factory()->create();
        $contact = $this->createContact($user, [
            'display_name' => 'Force Contact',
            'primary_email' => 'force@test.com',
        ]);

        $this->artisan('party:backfill-from-crm', [
            '--user-id' => $user->id,
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertNotNull($contact->fresh()->party_id);
        $this->assertSame(1, Party::where('user_id', $user->id)->count());
    }

    public function test_flag_enabled_allows_real_run(): void
    {
        config()->set('marketplace.features.party_core_enabled', true);

        $user = User::factory()->create();
        $contact = $this->createContact($user, [
            'display_name' => 'Enabled Contact',
            'primary_email' => 'enabled@test.com',
        ]);

        $this->artisan('party:backfill-from-crm', [
            '--user-id' => $user->id,
        ])->assertExitCode(0);

        $this->assertNotNull($contact->fresh()->party_id);
    }

    public function test_party_id_empty_contact_gets_linked_to_party(): void
    {
        config()->set('marketplace.features.party_core_enabled', true);

        $user = User::factory()->create();
        $contact = $this->createContact($user, [
            'display_name' => 'Link Me Contact',
            'primary_email' => 'link@test.com',
        ]);

        $this->artisan('party:backfill-from-crm', [
            '--user-id' => $user->id,
            '--force' => true,
        ])->assertExitCode(0);

        $contact->refresh();
        $this->assertNotNull($contact->party_id);
        $party = Party::find($contact->party_id);
        $this->assertSame('Link Me Contact', $party->display_name);
        $this->assertSame('link@test.com', $party->primary_email);
    }

    public function test_existing_party_id_contact_is_preserved(): void
    {
        config()->set('marketplace.features.party_core_enabled', true);

        $user = User::factory()->create();
        $existingParty = Party::create([
            'user_id' => $user->id,
            'display_name' => 'Existing Party',
            'party_type' => 'person',
        ]);
        $contact = $this->createContact($user, [
            'display_name' => 'Already Linked',
            'party_id' => $existingParty->id,
        ]);

        $this->artisan('party:backfill-from-crm', [
            '--user-id' => $user->id,
            '--force' => true,
        ])->assertExitCode(0);

        // party_id dolu olduğu için atlanmalı; yeni party oluşmamalı
        $this->assertSame($existingParty->id, $contact->fresh()->party_id);
        $this->assertSame(1, Party::where('user_id', $user->id)->count());
    }
    public function test_customer_role_is_not_duplicated(): void
    {
        config()->set('marketplace.features.party_core_enabled', true);

        $user = User::factory()->create();
        $contact = $this->createContact($user, [
            'display_name' => 'Role Dup Contact',
            'primary_email' => 'roledup@test.com',
        ]);

        // İlk run
        $this->artisan('party:backfill-from-crm', ['--user-id' => $user->id])->assertExitCode(0);
        $partyId = $contact->fresh()->party_id;

        // Aynı party'yi tekrar işleyecek şekilde party_id'yi temizle ve tekrar run et.
        // firstOrCreate aynı party+customer kombinasyonunu bulmalı, duplicate rol oluşturmamalı.
        CrmContact::where('id', $contact->id)->update(['party_id' => null]);
        $this->artisan('party:backfill-from-crm', ['--user-id' => $user->id])->assertExitCode(0);

        $this->assertSame(
            1,
            PartyRole::where('user_id', $user->id)->where('party_id', $partyId)->where('role', 'customer')->count(),
            'Customer rolü duplicate oluştu; firstOrCreate idempotent çalışmalı.',
        );
        $this->assertSame(1, Party::where('user_id', $user->id)->count());
    }

    public function test_party_identity_is_not_duplicated_on_second_run(): void
    {
        config()->set('marketplace.features.party_core_enabled', true);

        $user = User::factory()->create();
        $legalEntity = LegalEntity::create(['user_id' => $user->id, 'name' => 'LE', 'tax_number' => '1111111111']);
        $store = MarketplaceStore::create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'TY Store',
        ]);
        $contact = $this->createContact($user, [
            'display_name' => 'Identity Dup Contact',
            'primary_email' => 'identitydup@test.com',
        ]);
        CrmContactIdentity::create([
            'user_id' => $user->id,
            'contact_id' => $contact->id,
            'store_id' => $store->id,
            'source_type' => 'marketplace',
            'external_customer_id' => 'ty-id-123',
            'confidence' => 0.9,
        ]);

        $this->artisan('party:backfill-from-crm', ['--user-id' => $user->id])->assertExitCode(0);
        $partyId = $contact->fresh()->party_id;

        // identity'yi silip tekrar run edip tekrar eklenmesini simüle et yerine:
        // direkt aynı identity tekrar firstOrCreate ile yazılmaya çalışırsa duplicate olmaz.
        $countAfterFirst = PartyIdentity::where('party_id', $partyId)->count();

        // contact'ı tekrar aday yapmak için party_id'yi temizle ve tekrar run et
        CrmContact::where('id', $contact->id)->update(['party_id' => null]);
        $this->artisan('party:backfill-from-crm', ['--user-id' => $user->id])->assertExitCode(0);

        $countAfterSecond = PartyIdentity::where('party_id', $partyId)->count();
        $this->assertSame($countAfterFirst, $countAfterSecond, 'Party identity duplicate oluştu.');
    }

    public function test_user_id_filter_preserves_tenant_isolation(): void
    {
        config()->set('marketplace.features.party_core_enabled', true);

        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $contactA = $this->createContact($userA, ['display_name' => 'User A Contact', 'primary_email' => 'a@test.com']);
        $contactB = $this->createContact($userB, ['display_name' => 'User B Contact', 'primary_email' => 'b@test.com']);

        $this->artisan('party:backfill-from-crm', ['--user-id' => $userA->id])->assertExitCode(0);

        $this->assertNotNull($contactA->fresh()->party_id);
        // userB contact'ı dokunulmamalı
        $this->assertNull($contactB->fresh()->party_id);
        $this->assertSame(0, Party::where('user_id', $userB->id)->count());
    }

    public function test_second_run_is_idempotent(): void
    {
        config()->set('marketplace.features.party_core_enabled', true);

        $user = User::factory()->create();
        $contact = $this->createContact($user, [
            'display_name' => 'Idempotent Contact',
            'primary_email' => 'idem@test.com',
        ]);

        // İlk run
        $this->artisan('party:backfill-from-crm', ['--user-id' => $user->id])->assertExitCode(0);
        $partyCountAfterFirst = Party::where('user_id', $user->id)->count();
        $roleCountAfterFirst = PartyRole::where('user_id', $user->id)->count();
        $identityCountAfterFirst = PartyIdentity::where('user_id', $user->id)->count();

        // İkinci run — party_id dolu olduğu için aday 0 olmalı, hiçbir şey değişmemeli
        $this->artisan('party:backfill-from-crm', ['--user-id' => $user->id])->assertExitCode(0);

        $this->assertSame($partyCountAfterFirst, Party::where('user_id', $user->id)->count());
        $this->assertSame($roleCountAfterFirst, PartyRole::where('user_id', $user->id)->count());
        $this->assertSame($identityCountAfterFirst, PartyIdentity::where('user_id', $user->id)->count());
    }

    public function test_chunk_size_one_processes_multiple_contacts(): void
    {
        config()->set('marketplace.features.party_core_enabled', true);

        $user = User::factory()->create();
        $this->createContact($user, ['display_name' => 'Chunk A', 'primary_email' => 'a@chunk.test']);
        $this->createContact($user, ['display_name' => 'Chunk B', 'primary_email' => 'b@chunk.test']);
        $this->createContact($user, ['display_name' => 'Chunk C', 'primary_email' => 'c@chunk.test']);

        $this->artisan('party:backfill-from-crm', [
            '--user-id' => $user->id,
            '--chunk' => 1,
        ])->assertExitCode(0);

        $this->assertSame(3, Party::where('user_id', $user->id)->count());
        $this->assertSame(0, CrmContact::where('user_id', $user->id)->whereNull('party_id')->count());
    }

    public function test_force_restores_config_to_original_value_after_run(): void
    {
        // party_core_enabled default false
        $user = User::factory()->create();
        $this->createContact($user, ['display_name' => 'Restore Contact', 'primary_email' => 'restore@test.com']);

        $this->assertFalse((bool) config('marketplace.features.party_core_enabled'));

        $this->artisan('party:backfill-from-crm', [
            '--user-id' => $user->id,
            '--force' => true,
        ])->assertExitCode(0);

        // --force sonrası config eski değerine (false) dönmüş olmalı.
        $this->assertFalse((bool) config('marketplace.features.party_core_enabled'));
    }

    public function test_existing_party_identity_match_links_to_existing_party(): void
    {
        config()->set('marketplace.features.party_core_enabled', true);

        $user = User::factory()->create();
        $legalEntity = LegalEntity::create(['user_id' => $user->id, 'name' => 'LE', 'tax_number' => '1111111111']);
        $store = MarketplaceStore::create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'TY Store',
        ]);

        // Önceden bir party + external_customer_id identity oluştur.
        $existingParty = Party::create([
            'user_id' => $user->id,
            'display_name' => 'Pre-existing Party',
            'party_type' => 'organization',
        ]);
        PartyIdentity::create([
            'user_id' => $user->id,
            'party_id' => $existingParty->id,
            'store_id' => $store->id,
            'source_type' => 'marketplace',
            'identity_kind' => 'external_customer_id',
            'identity_value' => 'ty-match-999',
        ]);

        // Aynı external_customer_id + store_id içeren bir contact oluştur.
        $contact = $this->createContact($user, [
            'display_name' => 'Match Contact',
            'primary_email' => 'match@test.com',
        ]);
        CrmContactIdentity::create([
            'user_id' => $user->id,
            'contact_id' => $contact->id,
            'store_id' => $store->id,
            'source_type' => 'marketplace',
            'external_customer_id' => 'ty-match-999',
            'confidence' => 0.95,
        ]);

        $this->artisan('party:backfill-from-crm', ['--user-id' => $user->id])->assertExitCode(0);

        // Yeni party OLUŞMAMALI; mevcut party'ye bağlanmalı.
        $this->assertSame($existingParty->id, $contact->fresh()->party_id);
        $this->assertSame(1, Party::where('user_id', $user->id)->count());
    }

    public function test_identity_with_multiple_fields_transfers_all(): void
    {
        config()->set('marketplace.features.party_core_enabled', true);

        $user = User::factory()->create();
        $legalEntity = LegalEntity::create(['user_id' => $user->id, 'name' => 'LE2', 'tax_number' => '2222222222']);
        $store = MarketplaceStore::create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'TY Store 2',
        ]);

        $contact = $this->createContact($user, [
            'display_name' => 'Multi Identity Contact',
            'primary_email' => 'multi@test.com',
        ]);
        // Tek bir crm_contact_identity satırında external_customer_id + normalized_phone + email dolu.
        CrmContactIdentity::create([
            'user_id' => $user->id,
            'contact_id' => $contact->id,
            'store_id' => $store->id,
            'source_type' => 'marketplace',
            'external_customer_id' => 'ty-multi-1',
            'normalized_phone' => '5321112233',
            'email' => 'multi-identity@test.com',
            'confidence' => 0.8,
        ]);

        $this->artisan('party:backfill-from-crm', ['--user-id' => $user->id])->assertExitCode(0);

        $partyId = $contact->fresh()->party_id;
        $this->assertNotNull($partyId);

        // Üç identity de aktarılmış olmalı: external_customer_id, phone, email.
        $this->assertSame(
            1,
            PartyIdentity::where('party_id', $partyId)->where('identity_kind', 'external_customer_id')->where('identity_value', 'ty-multi-1')->count(),
        );
        $this->assertSame(
            1,
            PartyIdentity::where('party_id', $partyId)->where('identity_kind', 'phone')->where('identity_value', '5321112233')->count(),
        );
        $this->assertSame(
            1,
            PartyIdentity::where('party_id', $partyId)->where('identity_kind', 'email')->where('identity_value', 'multi-identity@test.com')->count(),
        );
    }

    protected function createContact(User $user, array $overrides = []): CrmContact
    {
        return CrmContact::create(array_merge([
            'user_id' => $user->id,
            'display_name' => 'Test Contact',
            'status' => 'active',
        ], $overrides));
    }
}

