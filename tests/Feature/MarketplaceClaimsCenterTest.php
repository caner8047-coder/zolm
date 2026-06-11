<?php

namespace Tests\Feature;

use App\Livewire\Returns\MarketplaceClaimsCenter;
use App\Models\ChannelClaim;
use App\Models\ChannelClaimItem;
use App\Models\IntegrationConnection;
use App\Models\IntegrationSyncRun;
use App\Models\IntegrationSyncProfile;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\User;
use App\Services\Marketplace\MarketplaceManualSyncDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\TestCase;

class MarketplaceClaimsCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_marketplace_claims_and_selects_a_claim(): void
    {
        [$store, $user] = $this->makeStore();

        $claim = ChannelClaim::query()->create([
            'store_id' => $store->id,
            'external_claim_id' => 'TY-CLAIM-1',
            'order_number' => 'TY-ORDER-1',
            'status' => 'delivered',
            'reason' => 'Ürün hasarlı',
            'customer_name' => 'Ayşe Demir',
            'created_date' => now(),
        ]);

        ChannelClaimItem::query()->create([
            'claim_id' => $claim->id,
            'external_item_id' => '77',
            'product_name' => 'Deneme Ürün',
            'barcode' => '869000000001',
            'stock_code' => 'SKU-1',
            'quantity' => 1,
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceClaimsCenter::class)
            ->assertSee('Gelen İade Talepleri')
            ->assertSee('TY-CLAIM-1')
            ->call('selectClaim', $claim->id)
            ->assertSet('selectedClaimId', $claim->id)
            ->call('markNeedsReview')
            ->assertSet('messageType', 'success');

        $this->assertSame('unresolved', $claim->refresh()->status);
    }

    public function test_sync_claims_shows_inline_completion_feedback(): void
    {
        [$store, $user] = $this->makeStore();

        $run = IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'claims',
            'trigger_type' => 'manual',
            'status' => 'completed',
            'items_received' => 0,
            'items_created' => 0,
            'items_updated' => 0,
            'items_skipped' => 0,
        ]);

        $this->mock(MarketplaceManualSyncDispatchService::class, function (MockInterface $mock) use ($store, $run): void {
            $mock->shouldReceive('dispatch')
                ->once()
                ->withArgs(fn (MarketplaceStore $actualStore, string $syncType) => $actualStore->is($store) && $syncType === 'claims')
                ->andReturn([
                    'created' => true,
                    'debounced' => false,
                    'reason' => null,
                    'run' => $run,
                    'debounce_seconds' => 30,
                    'executed_inline' => true,
                    'inline_error' => null,
                ]);
        });

        $this->actingAs($user);

        Livewire::test(MarketplaceClaimsCenter::class)
            ->set('storeFilter', (string) $store->id)
            ->call('syncClaims')
            ->assertSet('messageType', 'success')
            ->assertSee('1 mağazada sync tamamlandı (0 kayıt alındı).');
    }

    public function test_sync_claims_shows_inline_errors(): void
    {
        [$store, $user] = $this->makeStore();

        $run = IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'claims',
            'trigger_type' => 'manual',
            'status' => 'failed',
            'error_count' => 1,
            'notes_json' => [
                'last_error' => '401 Unauthorized',
            ],
        ]);

        $this->mock(MarketplaceManualSyncDispatchService::class, function (MockInterface $mock) use ($store, $run): void {
            $mock->shouldReceive('dispatch')
                ->once()
                ->withArgs(fn (MarketplaceStore $actualStore, string $syncType) => $actualStore->is($store) && $syncType === 'claims')
                ->andReturn([
                    'created' => true,
                    'debounced' => false,
                    'reason' => null,
                    'run' => $run,
                    'debounce_seconds' => 30,
                    'executed_inline' => true,
                    'inline_error' => null,
                ]);
        });

        $this->actingAs($user);

        Livewire::test(MarketplaceClaimsCenter::class)
            ->set('storeFilter', (string) $store->id)
            ->call('syncClaims')
            ->assertSet('messageType', 'error')
            ->assertSee('401 Unauthorized');
    }

    /**
     * @return array{0: MarketplaceStore, 1: User}
     */
    protected function makeStore(): array
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $entity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'ZOLM Claims UI Ltd.',
            'tax_number' => (string) random_int(1000000000, 9999999999),
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'Trendyol İade',
            'store_code' => 'TY-RETURN',
            'seller_id' => '123456',
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        IntegrationConnection::query()->create([
            'store_id' => $store->id,
            'provider' => 'trendyol',
            'auth_type' => 'api_key_secret',
            'credentials_encrypted' => [
                'api_key' => 'key',
                'api_secret' => 'secret',
            ],
            'api_base_url' => 'https://apigw.trendyol.com/',
            'status' => 'configured',
        ]);

        IntegrationSyncProfile::query()->create([
            'store_id' => $store->id,
            ...IntegrationSyncProfile::defaultsForMarketplace('trendyol'),
        ]);

        return [$store->fresh(['connection', 'syncProfile']), $user];
    }
}
