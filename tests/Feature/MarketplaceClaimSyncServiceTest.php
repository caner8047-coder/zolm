<?php

namespace Tests\Feature;

use App\Models\ChannelClaim;
use App\Models\ChannelOrder;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\User;
use App\Services\Marketplace\MarketplaceClaimSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceClaimSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_syncs_marketplace_claims_and_items(): void
    {
        [$store] = $this->makeStore();

        ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'external_order_id' => 'TY-1001',
            'order_number' => 'TY-1001',
            'order_status' => 'delivered',
        ]);

        $result = app(MarketplaceClaimSyncService::class)->sync($store, [[
            'claimId' => 'CLM-1',
            'orderNumber' => 'TY-1001',
            'cargoTrackingNumber' => 'TRK-1',
            'claimStatus' => 'Delivered',
            'claimReason' => 'Ürün hasarlı',
            'customerName' => 'Ayşe Demir',
            'claimDate' => '2026-04-20T10:00:00+03:00',
            'items' => [[
                'claimLineItemId' => 55,
                'orderLineId' => 11,
                'productName' => 'Deneme Ürün',
                'barcode' => '869000000001',
                'stockCode' => 'SKU-1',
                'quantity' => 2,
                'price' => 120.50,
            ]],
        ]]);

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['skipped']);
        $this->assertNotEmpty($result['impacted_order_ids']);

        $claim = ChannelClaim::query()->with('items')->where('external_claim_id', 'CLM-1')->firstOrFail();

        $this->assertSame('delivered', $claim->status);
        $this->assertSame('TY-1001', $claim->order_number);
        $this->assertSame('Ürün hasarlı', $claim->reason);
        $this->assertCount(1, $claim->items);
        $this->assertSame('55', $claim->items->first()->external_item_id);
        $this->assertSame('SKU-1', $claim->items->first()->stock_code);

        app(MarketplaceClaimSyncService::class)->sync($store, [[
            'claimId' => 'CLM-1',
            'orderNumber' => 'TY-1001',
            'claimStatus' => 'Approved',
            'items' => [[
                'claimLineItemId' => 55,
                'quantity' => 1,
            ]],
        ]]);

        $claim->refresh();

        $this->assertSame('approved', $claim->status);
        $this->assertSame(1, ChannelClaim::query()->where('store_id', $store->id)->count());
    }

    /**
     * @return array{0: MarketplaceStore, 1: User}
     */
    protected function makeStore(string $marketplace = 'trendyol'): array
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $entity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'ZOLM Claim Ltd.',
            'tax_number' => (string) random_int(1000000000, 9999999999),
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => $marketplace,
            'store_name' => 'Claim Store',
            'store_code' => 'CLAIM',
            'seller_id' => '123456',
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        return [$store, $user];
    }
}
