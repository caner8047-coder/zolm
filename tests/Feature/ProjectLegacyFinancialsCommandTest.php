<?php

namespace Tests\Feature;

use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
use App\Models\IntegrationSyncProfile;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\MpOrder;
use App\Models\MpPeriod;
use App\Models\MpSettlement;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProjectLegacyFinancialsCommandTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.host', 'mysql');
        config()->set('database.connections.mysql.port', '3306');
        config()->set('database.connections.mysql.database', $this->mysqlTestDatabaseName());
        config()->set('database.connections.mysql.username', 'sail');
        config()->set('database.connections.mysql.password', 'password');
        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::setDefaultConnection('mysql');
    }

    public function test_dry_run_does_not_persist_legacy_financial_projection(): void
    {
        [$store, $legacyOrder] = $this->createGraph('woocommerce');

        $this->artisan('marketplace:project-legacy-financials', [
            'store' => $store->id,
            '--only-unprojected' => true,
            '--dry-run' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseMissing('order_financial_events', [
            'store_id' => $store->id,
            'event_source' => 'legacy_mp_order',
        ]);

        $this->assertDatabaseHas('mp_orders', [
            'id' => $legacyOrder->id,
            'projected_at' => null,
        ]);
    }

    public function test_command_projects_legacy_financial_rows_into_v2_ledger(): void
    {
        [$store, $legacyOrder] = $this->createGraph('shopify');

        $this->artisan('marketplace:project-legacy-financials', [
            'store' => $store->id,
            '--only-unprojected' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('order_financial_events', [
            'store_id' => $store->id,
            'event_source' => 'legacy_mp_order',
            'event_type' => 'seller_revenue',
        ]);

        $this->assertDatabaseHas('mp_orders', [
            'id' => $legacyOrder->id,
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'source_marketplace' => $store->marketplace,
        ]);
    }

    /**
     * @return array{0: MarketplaceStore, 1: MpOrder}
     */
    protected function createGraph(string $marketplace): array
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $entity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Legacy Finans Komut Ltd.',
            'tax_number' => '7'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => $marketplace,
            'store_name' => strtoupper($marketplace).' LEGACY FIN',
            'store_code' => strtoupper($marketplace).'-LEG-'.$suffix,
            'seller_id' => strtoupper($marketplace).'-LEG-'.$suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        IntegrationSyncProfile::query()->create(array_merge(
            ['store_id' => $store->id],
            IntegrationSyncProfile::defaultsForMarketplace($marketplace),
        ));

        $period = MpPeriod::query()->create([
            'user_id' => $user->id,
            'seller_id' => $store->seller_id,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'marketplace' => $marketplace,
            'status' => 'completed',
        ]);

        $orderNumber = 'LEGACY-CMD-FIN-'.$suffix;

        $channelOrder = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $entity->id,
            'external_order_id' => $orderNumber,
            'order_number' => $orderNumber,
            'order_status' => 'approved',
            'customer_name' => 'Command Finans',
            'ordered_at' => now()->subDay(),
        ]);

        ChannelOrderItem::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $channelOrder->id,
            'external_line_id' => 'LINE-'.$suffix,
            'stock_code' => 'CMD-FIN-STOCK-'.$suffix,
            'barcode' => 'CMD-FIN-BARCODE-'.$suffix,
            'product_name' => 'Command Finans Urun',
            'quantity' => 1,
            'unit_price' => 1500,
            'gross_amount' => 1500,
            'billable_amount' => 1500,
            'commission_rate' => 10,
        ]);

        $legacyOrder = MpOrder::query()->create([
            'period_id' => $period->id,
            'order_number' => $orderNumber,
            'barcode' => 'CMD-FIN-BARCODE-'.$suffix,
            'stock_code' => 'CMD-FIN-STOCK-'.$suffix,
            'product_name' => 'Command Finans Urun',
            'quantity' => 1,
            'order_date' => now()->subDay(),
            'delivery_date' => now()->subHours(8),
            'payment_date' => now()->subHours(4)->toDateString(),
            'status' => 'Teslim Edildi',
            'gross_amount' => 1500,
            'commission_amount' => 150,
            'cargo_amount' => 35,
            'service_fee' => 10,
            'withholding_tax' => 15,
            'net_hakedis' => 1290,
        ]);

        MpSettlement::query()->create([
            'user_id' => $user->id,
            'period_id' => $period->id,
            'order_id' => $legacyOrder->id,
            'transaction_type' => 'Satis',
            'order_number' => $orderNumber,
            'document_number' => 'CMD-DOC-'.$suffix,
            'transaction_date' => now()->subDay()->toDateString(),
            'settlement_date' => now()->subHours(4)->toDateString(),
            'due_date' => now()->subHours(2)->toDateString(),
            'seller_hakedis' => 1290,
            'total_amount' => 1500,
        ]);

        return [$store, $legacyOrder];
    }
}
