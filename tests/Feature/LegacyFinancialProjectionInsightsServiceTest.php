<?php

namespace Tests\Feature;

use App\Models\ChannelOrder;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\MpOrder;
use App\Models\MpPeriod;
use App\Models\OrderFinancialEvent;
use App\Models\OrderProfitSnapshot;
use App\Models\User;
use App\Services\Marketplace\LegacyFinancialProjectionInsightsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LegacyFinancialProjectionInsightsServiceTest extends TestCase
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

    public function test_it_summarizes_pending_projected_and_confirmed_legacy_financial_projection_metrics(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $entity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Legacy Insights Ltd.',
            'tax_number' => '4'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'LEGACY INSIGHTS',
            'store_code' => 'LEGACY-INS-'.$suffix,
            'seller_id' => 'LEGACY-INS-'.$suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $period = MpPeriod::query()->create([
            'user_id' => $user->id,
            'seller_id' => $store->seller_id,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'marketplace' => 'trendyol',
            'status' => 'completed',
        ]);

        $pendingOrderNumber = 'LEGACY-PENDING-'.$suffix;
        $projectedOrderNumber = 'LEGACY-PROJECTED-'.$suffix;

        $pendingOrder = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $entity->id,
            'external_order_id' => $pendingOrderNumber,
            'order_number' => $pendingOrderNumber,
            'order_status' => 'Teslim Edildi',
            'ordered_at' => now()->subDay(),
        ]);

        $projectedOrder = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $entity->id,
            'external_order_id' => $projectedOrderNumber,
            'order_number' => $projectedOrderNumber,
            'order_status' => 'Teslim Edildi',
            'ordered_at' => now()->subDay(),
        ]);

        MpOrder::query()->create([
            'period_id' => $period->id,
            'order_number' => $pendingOrderNumber,
            'status' => 'Teslim Edildi',
            'order_date' => now()->subDay(),
            'payment_date' => now()->subHours(6)->toDateString(),
            'gross_amount' => 1000,
            'net_hakedis' => 800,
            'projected_at' => null,
        ]);

        $projectedAt = now()->subMinutes(10);

        MpOrder::query()->create([
            'period_id' => $period->id,
            'store_id' => $store->id,
            'legal_entity_id' => $entity->id,
            'source_marketplace' => 'trendyol',
            'order_number' => $projectedOrderNumber,
            'status' => 'Teslim Edildi',
            'order_date' => now()->subDay(),
            'payment_date' => now()->subHours(6)->toDateString(),
            'gross_amount' => 900,
            'net_hakedis' => 720,
            'projected_at' => $projectedAt,
        ]);

        OrderFinancialEvent::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $entity->id,
            'channel_order_id' => $projectedOrder->id,
            'event_source' => 'legacy_mp_order',
            'event_type' => 'seller_revenue',
            'external_event_id' => sha1('legacy-insight'),
            'reference_number' => $projectedOrderNumber,
            'event_date' => now()->subHours(6),
            'settlement_date' => now()->subHours(6),
            'amount' => 900,
            'currency' => 'TRY',
            'direction' => 'credit',
            'status' => 'posted',
        ]);

        OrderProfitSnapshot::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $projectedOrder->id,
            'channel_order_item_id' => null,
            'profit_state' => 'confirmed',
            'gross_revenue' => 900,
            'net_receivable' => 720,
            'commission_total' => 0,
            'cargo_total' => 0,
            'service_fee_total' => 0,
            'withholding_total' => 0,
            'packaging_cost' => 0,
            'own_cargo_cost' => 0,
            'cogs_cost' => 0,
            'return_effect' => 0,
            'vat_effect' => 0,
            'estimated_profit' => 0,
            'confirmed_profit' => 200,
            'margin_percent' => 22.22,
            'calculated_at' => now(),
            'version' => 1,
        ]);

        $summary = app(LegacyFinancialProjectionInsightsService::class)->summaryForUser($user->id, $store->id);

        $this->assertSame(1, $summary['pending_rows']);
        $this->assertSame(1, $summary['projected_rows']);
        $this->assertSame(1, $summary['legacy_event_orders']);
        $this->assertSame(1, $summary['confirmed_orders']);
        $this->assertNotNull($summary['last_projected_at']);
    }
}
