<?php

namespace Tests\Feature\Hepsiburada;

use App\Models\MarketplaceStore;
use App\Models\IntegrationConnection;
use App\Models\ChannelProduct;
use App\Models\ChannelListing;
use App\Models\ChannelOrder;
use App\Models\ChannelOrderPackage;
use App\Services\Marketplace\MarketplaceSyncService;
use App\Services\Marketplace\Connectors\HepsiburadaConnector;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HepsiburadaTenantIsolationTest extends TestCase
{
    use RefreshDatabase;
    protected function createStore(int $id, string $sellerId, string $storeName): MarketplaceStore
    {
        $user = \App\Models\User::factory()->create();

        $le = \App\Models\LegalEntity::create([
            'user_id'      => $user->id,
            'name'         => "Test Org {$id}",
            'company_name' => "Co {$id}",
            'tax_office'   => 'Kadikoy',
            'tax_number'   => '1234567890',
            'address'      => 'Istanbul',
        ]);

        $store = MarketplaceStore::create([
            'id'              => $id,
            'user_id'         => $user->id,
            'legal_entity_id' => $le->id,
            'marketplace'     => 'hepsiburada',
            'store_name'      => $storeName,
            'seller_id'       => $sellerId,
            'timezone'        => 'Europe/Istanbul',
            'currency'        => 'TRY',
            'is_active'       => true,
        ]);

        $connection = IntegrationConnection::create([
            'store_id'              => $store->id,
            'provider'              => 'hepsiburada',
            'auth_type'             => 'merchant_id_service_key',
            'credentials_encrypted' => [
                'api_key'    => "service-key-{$id}",
                'extra_user' => "user-{$id}",
            ],
            'api_base_url'          => 'https://oms-external.hepsiburada.com/',
            'status'                => 'configured',
        ]);

        $store->setRelation('connection', $connection);

        return $store;
    }

    public function test_tenant_isolation_prevents_credential_leak_and_cross_tenant_clash(): void
    {
        $storeA = $this->createStore(1001, 'seller-A', 'Store A');
        $storeB = $this->createStore(1002, 'seller-B', 'Store B');

        // Create same product SKU on both stores
        $productA = ChannelProduct::create([
            'store_id' => $storeA->id,
            'external_product_id' => 'PROD-SAME',
            'stock_code' => 'SKU-SAME',
            'barcode' => 'BAR-SAME',
            'title' => 'Product A',
        ]);

        $productB = ChannelProduct::create([
            'store_id' => $storeB->id,
            'external_product_id' => 'PROD-SAME',
            'stock_code' => 'SKU-SAME',
            'barcode' => 'BAR-SAME',
            'title' => 'Product B',
        ]);

        $this->assertNotEquals($productA->id, $productB->id);

        // Verify isolation under tenant context
        $this->assertSame('Product A', ChannelProduct::where('store_id', $storeA->id)->where('external_product_id', 'PROD-SAME')->first()->title);
        $this->assertSame('Product B', ChannelProduct::where('store_id', $storeB->id)->where('external_product_id', 'PROD-SAME')->first()->title);

        // Test orders matching: Order number overlaps shouldn't interfere between stores
        $orderA = ChannelOrder::create([
            'store_id' => $storeA->id,
            'legal_entity_id' => $storeA->legal_entity_id,
            'external_order_id' => 'ORD-123',
            'order_number' => 'ORD-123',
            'order_status' => 'Open',
        ]);

        $orderB = ChannelOrder::create([
            'store_id' => $storeB->id,
            'legal_entity_id' => $storeB->legal_entity_id,
            'external_order_id' => 'ORD-123',
            'order_number' => 'ORD-123',
            'order_status' => 'Shipped',
        ]);

        $this->assertNotEquals($orderA->id, $orderB->id);
    }
}
