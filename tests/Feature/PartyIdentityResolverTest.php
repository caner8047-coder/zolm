<?php

namespace Tests\Feature;

use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\Party;
use App\Models\PartyIdentity;
use App\Models\User;
use App\Services\Crm\PartyIdentityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartyIdentityResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_feature_flag_defaults_to_false(): void
    {
        $resolver = app(PartyIdentityResolver::class);

        $this->assertFalse($resolver->isEnabled());
    }

    public function test_feature_flag_can_be_enabled(): void
    {
        config()->set('marketplace.features.party_core_enabled', true);

        $resolver = app(PartyIdentityResolver::class);

        $this->assertTrue($resolver->isEnabled());
    }

    public function test_create_party_defaults_to_unknown_type_and_active_status(): void
    {
        $user = User::factory()->create();
        $resolver = app(PartyIdentityResolver::class);

        $party = $resolver->createParty([
            'user_id' => $user->id,
            'display_name' => 'Acme Müşteri',
        ]);

        $fresh = $party->fresh();
        $this->assertSame('unknown', $fresh->party_type);
        $this->assertSame('active', $fresh->status);
    }

    public function test_find_identity_returns_null_when_none_exists(): void
    {
        $user = User::factory()->create();
        $resolver = app(PartyIdentityResolver::class);

        $this->assertNull($resolver->findIdentity($user->id, 'manual', null, 'email', 'missing@test.com'));
    }

    public function test_find_party_returns_null_when_none_exists(): void
    {
        $user = User::factory()->create();
        $resolver = app(PartyIdentityResolver::class);

        $this->assertNull($resolver->findParty($user->id, null, 'manual', null, null, null, null, null, null));
    }

    public function test_resolve_feature_flag_disabled_returns_null_and_creates_nothing(): void
    {
        // party_core_enabled default false (config). resolve null döner ve
        // hiçbir party/identity oluşturmaz.
        $user = User::factory()->create();
        $resolver = app(PartyIdentityResolver::class);

        $this->assertFalse($resolver->isEnabled());

        $result = $resolver->resolve([
            'user_id' => $user->id,
            'source_type' => 'manual',
            'name' => 'Flag Off Müşteri',
            'email' => 'off@test.com',
            'phone' => '0532 000 00 00',
        ]);

        $this->assertNull($result);
        $this->assertSame(0, Party::where('user_id', $user->id)->count());
        $this->assertSame(0, PartyIdentity::where('user_id', $user->id)->count());
    }

    public function test_resolve_creates_new_party_when_no_match(): void
    {
        config()->set('marketplace.features.party_core_enabled', true);

        $user = User::factory()->create();
        $resolver = app(PartyIdentityResolver::class);

        $party = $resolver->resolve([
            'user_id' => $user->id,
            'source_type' => 'manual',
            'name' => 'Yeni Müşteri',
            'email' => 'new@test.com',
            'phone' => '0532 111 22 33',
        ]);

        $this->assertNotNull($party->id);
        $this->assertSame('Yeni Müşteri', $party->display_name);
        $this->assertSame('new@test.com', $party->primary_email);
        // normalizePhone artık baştaki 0'ı kaldırır: 0532... → 532...
        $this->assertSame('5321112233', $party->normalized_phone);
        $this->assertSame('unknown', $party->party_type);

        $identity = PartyIdentity::query()->where('party_id', $party->id)->firstOrFail();
        // Telefon verildiğinde upsertIdentity sıralı dalar nedeniyle phone identity'sini oluşturur.
        $this->assertSame('phone', $identity->identity_kind);
        $this->assertSame('5321112233', $identity->identity_value);
    }

    public function test_resolve_finds_existing_party_by_email_identity(): void
    {
        config()->set('marketplace.features.party_core_enabled', true);

        $user = User::factory()->create();
        $resolver = app(PartyIdentityResolver::class);

        $existing = $resolver->resolve([
            'user_id' => $user->id,
            'source_type' => 'manual',
            'name' => 'Existing Müşteri',
            'email' => 'dup@test.com',
        ]);

        $resolved = $resolver->resolve([
            'user_id' => $user->id,
            'source_type' => 'marketplace',
            'name' => 'Existing Müşteri Farklı Ad',
            'email' => 'dup@test.com',
        ]);

        $this->assertSame($existing->id, $resolved->id);
        $this->assertSame(1, Party::where('user_id', $user->id)->count());
    }
    public function test_find_identity_with_null_store_id_does_not_match_store_bound_identity(): void
    {
        // store_id=null araması, store'a bağlı identity'yi yanlışlıkla BULMAMALI.
        $user = User::factory()->create();
        $legalEntity = LegalEntity::create(['user_id' => $user->id, 'name' => 'LE', 'tax_number' => '1111111111']);
        $store = MarketplaceStore::create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'TY Store',
        ]);
        $party = Party::create(['user_id' => $user->id, 'display_name' => 'Store Party', 'party_type' => 'unknown']);
        PartyIdentity::create([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'store_id' => $store->id,
            'source_type' => 'marketplace',
            'identity_kind' => 'external_customer_id',
            'identity_value' => 'ty-999',
        ]);

        $resolver = app(PartyIdentityResolver::class);

        // store_id=null ile aynı external_id aranırsa store'a bağlı kayıt dönmemeli.
        $this->assertNull($resolver->findIdentity($user->id, 'marketplace', null, 'external_customer_id', 'ty-999'));
    }

    public function test_find_identity_with_store_id_finds_correct_identity(): void
    {
        // store_id dolu iken doğru store'a bağlı identity bulunmalı.
        $user = User::factory()->create();
        $legalEntity = LegalEntity::create(['user_id' => $user->id, 'name' => 'LE', 'tax_number' => '2222222222']);
        $store = MarketplaceStore::create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'TY Store 2',
        ]);
        $party = Party::create(['user_id' => $user->id, 'display_name' => 'Store Party 2', 'party_type' => 'organization']);
        PartyIdentity::create([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'store_id' => $store->id,
            'source_type' => 'marketplace',
            'identity_kind' => 'external_customer_id',
            'identity_value' => 'ty-777',
        ]);

        $resolver = app(PartyIdentityResolver::class);

        $found = $resolver->findIdentity($user->id, 'marketplace', $store->id, 'external_customer_id', 'ty-777');

        $this->assertNotNull($found);
        $this->assertSame($store->id, $found->store_id);
        $this->assertTrue($found->party->is($party));
    }

    public function test_find_identity_null_store_id_matches_null_store_identity(): void
    {
        // store_id=null araması, store_id=null identity'yi doğru şekilde bulmalı.
        $user = User::factory()->create();
        $party = Party::create(['user_id' => $user->id, 'display_name' => 'Manual Party', 'party_type' => 'person']);
        PartyIdentity::create([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'store_id' => null,
            'source_type' => 'manual',
            'identity_kind' => 'email',
            'identity_value' => 'manual@test.com',
        ]);

        $resolver = app(PartyIdentityResolver::class);

        $found = $resolver->findIdentity($user->id, 'manual', null, 'email', 'manual@test.com');

        $this->assertNotNull($found);
        $this->assertNull($found->store_id);
        $this->assertTrue($found->party->is($party));
    }
    public function test_normalize_phone_collapses_formats(): void
    {
        $resolver = app(PartyIdentityResolver::class);
        $reflection = new \ReflectionMethod($resolver, 'normalizePhone');
        $reflection->setAccessible(true);

        // 0532..., 90532..., 532... formatları aynı değere (532...) indirgenmeli.
        $this->assertSame('5321112233', $reflection->invoke($resolver, '0532 111 22 33'));
        $this->assertSame('5321112233', $reflection->invoke($resolver, '905321112233'));
        $this->assertSame('5321112233', $reflection->invoke($resolver, '5321112233'));
        $this->assertNull($reflection->invoke($resolver, null));
        $this->assertNull($reflection->invoke($resolver, ''));
    }
}
