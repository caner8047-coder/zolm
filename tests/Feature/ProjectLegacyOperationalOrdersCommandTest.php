<?php

namespace Tests\Feature;

use App\Models\IntegrationSyncProfile;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\MpOperationalOrder;
use App\Models\MpOperationalOrderItem;
use App\Models\MpProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProjectLegacyOperationalOrdersCommandTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.host', 'mysql');
        config()->set('database.connections.mysql.port', '3306');
        config()->set('database.connections.mysql.database', 'zolm');
        config()->set('database.connections.mysql.username', 'sail');
        config()->set('database.connections.mysql.password', 'password');
        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::setDefaultConnection('mysql');
    }

    public function test_it_projects_unassigned_legacy_orders_with_command(): void
    {
        [$store, $order] = $this->createGraph();

        $this->artisan('marketplace:project-legacy-orders', [
            'store' => $store->id,
            '--only-unprojected' => true,
            '--include-unassigned' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('channel_orders', [
            'store_id' => $store->id,
            'order_number' => $order->order_number,
        ]);

        $this->assertDatabaseHas('mp_operational_orders', [
            'id' => $order->id,
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'source_marketplace' => $store->marketplace,
        ]);
    }

    /**
     * @return array{0: MarketplaceStore, 1: MpOperationalOrder}
     */
    protected function createGraph(): array
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $entity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Legacy Command Ltd.',
            'tax_number' => '5' . $suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => 'woocommerce',
            'store_name' => 'LEGACY COMMAND',
            'store_code' => 'LEGACY-CMD-' . $suffix,
            'seller_id' => 'LEGACY-CMD-' . $suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        IntegrationSyncProfile::query()->create(array_merge(
            ['store_id' => $store->id],
            IntegrationSyncProfile::defaultsForMarketplace('woocommerce'),
        ));

        $product = MpProduct::query()->create([
            'user_id' => $user->id,
            'barcode' => 'LEGACY-CMD-BARCODE-' . $suffix,
            'stock_code' => 'LEGACY-CMD-STOCK-' . $suffix,
            'product_name' => 'Legacy Command Ürün',
            'cogs' => 400,
            'packaging_cost' => 15,
            'cargo_cost' => 30,
            'vat_rate' => 20,
            'status' => 'active',
        ]);

        $order = MpOperationalOrder::query()->create([
            'order_number' => 'CMD-ORD-' . $suffix,
            'package_number' => 'CMD-PKG-' . $suffix,
            'order_date' => now()->subHours(2),
            'customer_name' => 'Komut Müşteri',
            'customer_city' => 'Ankara',
            'customer_district' => 'Çankaya',
            'status' => 'Onaylandı',
            'total_gross_amount' => 999.90,
            'total_discount' => 0,
        ]);

        MpOperationalOrderItem::query()->create([
            'operational_order_id' => $order->id,
            'order_number' => $order->order_number,
            'barcode' => $product->barcode,
            'stock_code' => $product->stock_code,
            'product_name' => $product->product_name,
            'quantity' => 1,
            'unit_price' => 999.90,
            'sale_price' => 999.90,
            'discount_amount' => 0,
            'billable_amount' => 999.90,
            'commission_rate' => 0,
            'synced_vat_rate' => 20,
        ]);

        return [$store, $order];
    }
}
