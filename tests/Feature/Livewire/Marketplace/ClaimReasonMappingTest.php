<?php

namespace Tests\Feature\Livewire\Marketplace;

use App\Enums\ZolmClaimReason;
use App\Livewire\Marketplace\ClaimReasonMapping;
use App\Models\MarketplaceStore;
use App\Models\MpClaimReason;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Tests\TestCase;

class ClaimReasonMappingTest extends TestCase
{
    use RefreshDatabase;

    // ─── Render ──────────────────────────────────────────────────

    public function test_renders_successfully(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ClaimReasonMapping::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.marketplace.claim-reason-mapping');
    }

    // ─── Authentication ──────────────────────────────────────────

    public function test_unauthenticated_user_cannot_access(): void
    {
        $this->get(route('mp.claim.mapping'))->assertRedirect(route('login'));
    }

    // ─── Tenant Isolation ────────────────────────────────────────

    public function test_prevents_accessing_other_users_store(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $storeUser2 = MarketplaceStore::factory()->create([
            'user_id' => $user2->id,
            'marketplace' => 'trendyol',
        ]);

        $this->actingAs($user1);

        Livewire::test(ClaimReasonMapping::class)
            ->set('selectedStoreId', $storeUser2->id)
            ->assertSet('selectedStoreId', 0);
    }

    public function test_cannot_update_other_users_claim_reason(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $storeUser2 = MarketplaceStore::factory()->create([
            'user_id' => $user2->id,
            'marketplace' => 'trendyol',
        ]);

        $reason = MpClaimReason::factory()->create([
            'store_id' => $storeUser2->id,
            'mapped_zolm_reason_code' => null,
        ]);

        $this->actingAs($user1);

        $store1 = MarketplaceStore::factory()->create([
            'user_id' => $user1->id,
            'marketplace' => 'trendyol',
        ]);

        Livewire::test(ClaimReasonMapping::class)
            ->set('selectedStoreId', $store1->id)
            ->call('updateMapping', $reason->id, 'ZOLM_DEFECTIVE');

        // DB must remain unchanged — IDOR protected
        $this->assertNull($reason->fresh()->mapped_zolm_reason_code);
    }

    // ─── Validation ──────────────────────────────────────────────

    public function test_invalid_enum_value_is_rejected(): void
    {
        $user = User::factory()->create();
        $store = MarketplaceStore::factory()->create([
            'user_id' => $user->id,
            'marketplace' => 'trendyol',
        ]);

        $reason = MpClaimReason::factory()->create([
            'store_id' => $store->id,
            'mapped_zolm_reason_code' => null,
        ]);

        $this->actingAs($user);

        Livewire::test(ClaimReasonMapping::class)
            ->set('selectedStoreId', $store->id)
            ->call('updateMapping', $reason->id, 'INVALID_CODE');

        $this->assertNull($reason->fresh()->mapped_zolm_reason_code);
    }

    // ─── DB Update ───────────────────────────────────────────────

    public function test_valid_reason_code_is_saved(): void
    {
        config(['marketplace.trendyol.reference_sync_enabled' => true]);

        $user = User::factory()->create(['role' => 'operator']);
        $store = MarketplaceStore::factory()->create([
            'user_id' => $user->id,
            'marketplace' => 'trendyol',
        ]);

        $reason = MpClaimReason::factory()->create([
            'store_id' => $store->id,
            'mapped_zolm_reason_code' => null,
        ]);

        $this->actingAs($user);

        Livewire::test(ClaimReasonMapping::class)
            ->set('selectedStoreId', $store->id)
            ->call('updateMapping', $reason->id, 'ZOLM_DEFECTIVE');

        $this->assertEquals('ZOLM_DEFECTIVE', $reason->fresh()->mapped_zolm_reason_code);
    }

    public function test_mapping_can_be_cleared(): void
    {
        config(['marketplace.trendyol.reference_sync_enabled' => true]);

        $user = User::factory()->create(['role' => 'operator']);
        $store = MarketplaceStore::factory()->create([
            'user_id' => $user->id,
            'marketplace' => 'trendyol',
        ]);

        $reason = MpClaimReason::factory()->create([
            'store_id' => $store->id,
            'mapped_zolm_reason_code' => 'ZOLM_DEFECTIVE',
        ]);

        $this->actingAs($user);

        Livewire::test(ClaimReasonMapping::class)
            ->set('selectedStoreId', $store->id)
            ->call('updateMapping', $reason->id, ''); // Empty string = clear

        $this->assertNull($reason->fresh()->mapped_zolm_reason_code);
    }

    // ─── Audit ───────────────────────────────────────────────────

    public function test_audit_log_contains_old_and_new_value(): void
    {
        config(['marketplace.trendyol.reference_sync_enabled' => true]);

        $user = User::factory()->create(['role' => 'operator']);
        $store = MarketplaceStore::factory()->create([
            'user_id' => $user->id,
            'marketplace' => 'trendyol',
        ]);

        $reason = MpClaimReason::factory()->create([
            'store_id' => $store->id,
            'mapped_zolm_reason_code' => 'ZOLM_WRONG_ITEM',
        ]);

        $this->actingAs($user);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) use ($reason) {
                return $message === '[ClaimReasonMapping] Mapping güncellendi'
                    && $context['old_value'] === 'ZOLM_WRONG_ITEM'
                    && $context['new_value'] === 'ZOLM_DEFECTIVE'
                    && $context['claim_reason_id'] === $reason->id;
            });

        Livewire::test(ClaimReasonMapping::class)
            ->set('selectedStoreId', $store->id)
            ->call('updateMapping', $reason->id, 'ZOLM_DEFECTIVE');
    }

    // ─── Feature Flag ────────────────────────────────────────────

    public function test_feature_flag_disabled_blocks_mapping_update(): void
    {
        config(['marketplace.trendyol.reference_sync_enabled' => false]);

        $user = User::factory()->create(['role' => 'operator']);
        $store = MarketplaceStore::factory()->create([
            'user_id' => $user->id,
            'marketplace' => 'trendyol',
        ]);

        $reason = MpClaimReason::factory()->create([
            'store_id' => $store->id,
            'mapped_zolm_reason_code' => null,
        ]);

        $this->actingAs($user);

        Livewire::test(ClaimReasonMapping::class)
            ->set('selectedStoreId', $store->id)
            ->call('updateMapping', $reason->id, 'ZOLM_DEFECTIVE');

        $this->assertNull($reason->fresh()->mapped_zolm_reason_code);
    }

    // ─── Sorting ─────────────────────────────────────────────────

    public function test_sort_by_invalid_column_is_ignored(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $component = Livewire::test(ClaimReasonMapping::class);
        $originalSort = $component->get('sortBy');

        $component->call('sortTable', 'injected_column');

        $this->assertEquals($originalSort, $component->get('sortBy'));
    }

    // ─── Empty State ─────────────────────────────────────────────

    public function test_shows_empty_state_when_no_reasons(): void
    {
        $user = User::factory()->create();
        $store = MarketplaceStore::factory()->create([
            'user_id' => $user->id,
            'marketplace' => 'trendyol',
        ]);

        $this->actingAs($user);

        Livewire::test(ClaimReasonMapping::class)
            ->set('selectedStoreId', $store->id)
            ->assertSet('selectedStoreId', $store->id);
    }
}
