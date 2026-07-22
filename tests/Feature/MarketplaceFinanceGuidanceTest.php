<?php

namespace Tests\Feature;

use App\Jobs\SyncMarketplaceDataJob;
use App\Livewire\MarketplaceFinance;
use App\Models\ChannelOrder;
use App\Models\IntegrationConnection;
use App\Models\IntegrationSyncProfile;
use App\Models\IntegrationSyncRun;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\MpOrder;
use App\Models\MpPeriod;
use App\Models\OrderFinancialEvent;
use App\Models\OrderProfitSnapshot;
use App\Models\User;
use App\Services\MpSettingsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class MarketplaceFinanceGuidanceTest extends TestCase
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

    public function test_it_shows_compact_guidance_band_in_finance_view(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $entity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Finance Guidance Ltd.',
            'tax_number' => '2'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'ZEM FIN GUIDE',
            'store_code' => 'FIN-GUIDE-'.$suffix,
            'seller_id' => 'FIN-GUIDE-'.$suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'finance',
            'trigger_type' => 'smoke_test',
            'status' => 'completed',
            'items_received' => 4,
            'notes_json' => [
                'smoke_test' => true,
                'diagnostics' => [
                    'missing_amount_count' => 8,
                    'missing_settlement_date_count' => 3,
                    'warnings' => ['Tutar alanı gelmedi'],
                ],
            ],
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceFinance::class)
            ->assertSee('Bugün önce bunlara bak')
            ->assertSee('Finans alanları eksik')
            ->assertSee('Finans');
    }

    public function test_it_can_focus_finance_list_from_top_guidance(): void
    {
        [$user, $store] = $this->createStoreGraph('4');

        IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'finance',
            'trigger_type' => 'smoke_test',
            'status' => 'completed',
            'items_received' => 4,
            'notes_json' => [
                'smoke_test' => true,
                'diagnostics' => [
                    'missing_amount_count' => 8,
                    'missing_settlement_date_count' => 3,
                ],
            ],
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceFinance::class)
            ->call('focusTopGuidance')
            ->assertSet('marketplaceFilter', 'trendyol')
            ->assertSet('storeFilter', (string) $store->id)
            ->assertSet('financialStateFilter', 'waiting');
    }

    public function test_it_can_queue_finance_sync_from_top_guidance(): void
    {
        Queue::fake();

        [$user, $store] = $this->createStoreGraph('7');

        IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'finance',
            'trigger_type' => 'smoke_test',
            'status' => 'completed',
            'items_received' => 2,
            'notes_json' => [
                'smoke_test' => true,
                'diagnostics' => [
                    'missing_amount_count' => 2,
                ],
            ],
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceFinance::class)
            ->call('syncTopGuidance')
            ->assertSet('actionMessageTone', 'success');

        $this->assertDatabaseHas('integration_sync_runs', [
            'store_id' => $store->id,
            'sync_type' => 'finance',
            'trigger_type' => 'manual',
            'status' => 'queued',
        ]);

        Queue::assertPushed(SyncMarketplaceDataJob::class);
    }

    public function test_it_redirects_to_orders_route_for_legacy_financial_projection_guidance(): void
    {
        [$user, $store] = $this->createStoreGraph('8');

        IntegrationSyncProfile::query()->create(array_merge(
            ['store_id' => $store->id],
            IntegrationSyncProfile::defaultsForMarketplace('trendyol'),
        ));

        $period = MpPeriod::query()->create([
            'user_id' => $user->id,
            'seller_id' => $store->seller_id,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'marketplace' => 'trendyol',
            'status' => 'completed',
        ]);

        $orderNumber = 'FIN-LEGACY-'.random_int(100000, 999999);

        ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'external_order_id' => $orderNumber,
            'order_number' => $orderNumber,
            'order_status' => 'Teslim Edildi',
            'ordered_at' => now()->subDay(),
        ]);

        MpOrder::query()->create([
            'period_id' => $period->id,
            'order_number' => $orderNumber,
            'status' => 'Teslim Edildi',
            'order_date' => now()->subDay(),
            'payment_date' => now()->subHours(6)->toDateString(),
            'gross_amount' => 1000,
            'net_hakedis' => 800,
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceFinance::class)
            ->assertSee('Legacy finans satirlari V2 ledger\'a tasinmamis')
            ->call('syncTopGuidance')
            ->assertRedirect(route('mp.orders', ['storeFilter' => $store->id]));
    }

    public function test_it_shows_legacy_projection_effect_summary_card_in_finance_view(): void
    {
        [$user, $store] = $this->createStoreGraph('9');

        $period = MpPeriod::query()->create([
            'user_id' => $user->id,
            'seller_id' => $store->seller_id,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'marketplace' => 'trendyol',
            'status' => 'completed',
        ]);

        $orderNumber = 'FIN-CARD-'.random_int(100000, 999999);

        $order = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'external_order_id' => $orderNumber,
            'order_number' => $orderNumber,
            'order_status' => 'Teslim Edildi',
            'ordered_at' => now()->subDay(),
        ]);

        MpOrder::query()->create([
            'period_id' => $period->id,
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'source_marketplace' => 'trendyol',
            'order_number' => $orderNumber,
            'status' => 'Teslim Edildi',
            'order_date' => now()->subDay(),
            'payment_date' => now()->subHours(6)->toDateString(),
            'gross_amount' => 1000,
            'net_hakedis' => 800,
            'projected_at' => now()->subMinutes(10),
        ]);

        OrderFinancialEvent::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'channel_order_id' => $order->id,
            'event_source' => 'legacy_mp_order',
            'event_type' => 'seller_revenue',
            'external_event_id' => sha1('fin-card'),
            'reference_number' => $orderNumber,
            'event_date' => now()->subHours(6),
            'settlement_date' => now()->subHours(6),
            'amount' => 1000,
            'currency' => 'TRY',
            'direction' => 'credit',
            'status' => 'posted',
        ]);

        OrderProfitSnapshot::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'channel_order_item_id' => null,
            'profit_state' => 'confirmed',
            'gross_revenue' => 1000,
            'net_receivable' => 800,
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
            'confirmed_profit' => 250,
            'margin_percent' => 25,
            'calculated_at' => now(),
            'version' => 1,
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceFinance::class)
            ->assertSee('Eski veri aktarım etkisi')
            ->assertSee('Bekleyen eski satır')
            ->assertSee('Projeksiyonu tamamlanan')
            ->assertSee('Kesine dönen sipariş');
    }

    public function test_it_shows_legacy_projection_focus_card_in_finance_guidance_band(): void
    {
        [$user, $store] = $this->createStoreGraph('10');

        IntegrationSyncProfile::query()->create(array_merge(
            ['store_id' => $store->id],
            IntegrationSyncProfile::defaultsForMarketplace('trendyol'),
        ));

        $period = MpPeriod::query()->create([
            'user_id' => $user->id,
            'seller_id' => $store->seller_id,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'marketplace' => 'trendyol',
            'status' => 'completed',
        ]);

        $pendingOrderNumber = 'FIN-PEND-'.random_int(100000, 999999);
        $confirmedOrderNumber = 'FIN-CONF-'.random_int(100000, 999999);

        ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'external_order_id' => $pendingOrderNumber,
            'order_number' => $pendingOrderNumber,
            'order_status' => 'Teslim Edildi',
            'ordered_at' => now()->subDays(2),
        ]);

        $confirmedOrder = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'external_order_id' => $confirmedOrderNumber,
            'order_number' => $confirmedOrderNumber,
            'order_status' => 'Teslim Edildi',
            'ordered_at' => now()->subDay(),
        ]);

        MpOrder::query()->insert([
            [
                'period_id' => $period->id,
                'store_id' => $store->id,
                'legal_entity_id' => $store->legal_entity_id,
                'source_marketplace' => 'trendyol',
                'order_number' => $pendingOrderNumber,
                'status' => 'Teslim Edildi',
                'order_date' => now()->subDays(2),
                'payment_date' => now()->subDay()->toDateString(),
                'gross_amount' => 850,
                'net_hakedis' => 640,
                'projected_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'period_id' => $period->id,
                'store_id' => $store->id,
                'legal_entity_id' => $store->legal_entity_id,
                'source_marketplace' => 'trendyol',
                'order_number' => $confirmedOrderNumber,
                'status' => 'Teslim Edildi',
                'order_date' => now()->subDay(),
                'payment_date' => now()->subHours(6)->toDateString(),
                'gross_amount' => 1000,
                'net_hakedis' => 800,
                'projected_at' => now()->subMinutes(10),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        OrderFinancialEvent::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'channel_order_id' => $confirmedOrder->id,
            'event_source' => 'legacy_mp_order',
            'event_type' => 'seller_revenue',
            'external_event_id' => sha1('fin-guidance-confirmed'),
            'reference_number' => $confirmedOrderNumber,
            'event_date' => now()->subHours(6),
            'settlement_date' => now()->subHours(6),
            'amount' => 1000,
            'currency' => 'TRY',
            'direction' => 'credit',
            'status' => 'posted',
        ]);

        OrderProfitSnapshot::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $confirmedOrder->id,
            'channel_order_item_id' => null,
            'profit_state' => 'confirmed',
            'gross_revenue' => 1000,
            'net_receivable' => 800,
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
            'confirmed_profit' => 250,
            'margin_percent' => 25,
            'calculated_at' => now(),
            'version' => 1,
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceFinance::class)
            ->assertSee('Legacy muhasebe backlogu bu mağazada öne çıkıyor')
            ->assertSee('Backlogu odakla')
            ->assertSee('Kesin etkiyi göster')
            ->assertSee('Bekleyen 1')
            ->assertSee('Kesine dönen 1');
    }

    public function test_it_can_focus_legacy_projection_backlog_from_finance_focus_card(): void
    {
        [$user, $store] = $this->createStoreGraph('11');

        $period = MpPeriod::query()->create([
            'user_id' => $user->id,
            'seller_id' => $store->seller_id,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'marketplace' => 'trendyol',
            'status' => 'completed',
        ]);

        MpOrder::query()->create([
            'period_id' => $period->id,
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'source_marketplace' => 'trendyol',
            'order_number' => $orderNumber = 'FIN-FOCUS-'.random_int(100000, 999999),
            'status' => 'Teslim Edildi',
            'order_date' => now()->subDay(),
            'payment_date' => now()->subHours(6)->toDateString(),
            'gross_amount' => 950,
            'net_hakedis' => 760,
        ]);

        ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'external_order_id' => $orderNumber,
            'order_number' => $orderNumber,
            'order_status' => 'Teslim Edildi',
            'ordered_at' => now()->subDay(),
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceFinance::class)
            ->call('focusLegacyProjectionCard')
            ->assertSet('marketplaceFilter', 'trendyol')
            ->assertSet('storeFilter', (string) $store->id)
            ->assertSet('legacyProjectionFilter', 'backlog')
            ->assertSet('financialStateFilter', '')
            ->assertSet('deltaStateFilter', '')
            ->assertSet('actionMessageTone', 'success');
    }

    public function test_it_can_focus_legacy_confirmed_projection_impact_from_finance_focus_card(): void
    {
        [$user, $store] = $this->createStoreGraph('12');

        $period = MpPeriod::query()->create([
            'user_id' => $user->id,
            'seller_id' => $store->seller_id,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'marketplace' => 'trendyol',
            'status' => 'completed',
        ]);

        $orderNumber = 'FIN-CONF-FOCUS-'.random_int(100000, 999999);

        $order = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'external_order_id' => $orderNumber,
            'order_number' => $orderNumber,
            'order_status' => 'Teslim Edildi',
            'ordered_at' => now()->subDay(),
        ]);

        MpOrder::query()->create([
            'period_id' => $period->id,
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'source_marketplace' => 'trendyol',
            'order_number' => $orderNumber,
            'status' => 'Teslim Edildi',
            'order_date' => now()->subDay(),
            'payment_date' => now()->subHours(6)->toDateString(),
            'gross_amount' => 1000,
            'net_hakedis' => 800,
            'projected_at' => now()->subMinutes(10),
        ]);

        OrderFinancialEvent::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'channel_order_id' => $order->id,
            'event_source' => 'legacy_mp_order',
            'event_type' => 'seller_revenue',
            'external_event_id' => sha1('fin-confirmed-focus'),
            'reference_number' => $orderNumber,
            'event_date' => now()->subHours(6),
            'settlement_date' => now()->subHours(6),
            'amount' => 1000,
            'currency' => 'TRY',
            'direction' => 'credit',
            'status' => 'posted',
        ]);

        OrderProfitSnapshot::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'channel_order_item_id' => null,
            'profit_state' => 'confirmed',
            'gross_revenue' => 1000,
            'net_receivable' => 800,
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
            'confirmed_profit' => 250,
            'margin_percent' => 25,
            'calculated_at' => now(),
            'version' => 1,
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceFinance::class)
            ->call('focusLegacyConfirmedProjectionCard')
            ->assertSet('marketplaceFilter', 'trendyol')
            ->assertSet('storeFilter', (string) $store->id)
            ->assertSet('legacyProjectionFilter', 'confirmed')
            ->assertSet('financialStateFilter', 'ready')
            ->assertSet('actionMessageTone', 'success');
    }

    /**
     * @return array{0: User, 1: MarketplaceStore}
     */
    protected function createStoreGraph(string $prefix): array
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $entity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Finance Guidance Ltd.',
            'tax_number' => $prefix.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'ZEM FIN GUIDE',
            'store_code' => 'FIN-GUIDE-'.$suffix,
            'seller_id' => 'FIN-GUIDE-'.$suffix,
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

        return [$user, $store];
    }

    public function test_finance_applies_default_date_range_from_settings(): void
    {
        $user = User::factory()->create();

        (new MpSettingsService($user->id))->set('ui.finance_default_date_range_days', 7);

        $this->actingAs($user);

        Carbon::setTestNow('2026-07-01 12:00:00');

        try {
            Livewire::test(MarketplaceFinance::class)
                ->assertSet('dateFrom', '2026-06-24')
                ->assertSet('dateTo', '2026-07-01');
        } finally {
            Carbon::setTestNow(null);
        }
    }

    public function test_finance_keeps_empty_dates_when_default_is_zero(): void
    {
        $user = User::factory()->create();

        (new MpSettingsService($user->id))->set('ui.finance_default_date_range_days', 0);

        $this->actingAs($user);

        Livewire::test(MarketplaceFinance::class)
            ->assertSet('dateFrom', '')
            ->assertSet('dateTo', '');
    }

    public function test_finance_preserves_query_string_dates_over_default(): void
    {
        $user = User::factory()->create();

        (new MpSettingsService($user->id))->set('ui.finance_default_date_range_days', 7);

        $this->actingAs($user);

        Livewire::withQueryParams(['dateFrom' => '2026-01-01', 'dateTo' => '2026-01-31'])
            ->test(MarketplaceFinance::class)
            ->assertSet('dateFrom', '2026-01-01')
            ->assertSet('dateTo', '2026-01-31');
    }

    public function test_finance_reset_filters_applies_saved_default(): void
    {
        $user = User::factory()->create();

        (new MpSettingsService($user->id))->set('ui.finance_default_date_range_days', 60);

        $this->actingAs($user);

        Carbon::setTestNow('2026-07-01 12:00:00');

        try {
            Livewire::test(MarketplaceFinance::class)
                ->set('dateFrom', '2026-01-01')
                ->set('dateTo', '2026-06-01')
                ->call('resetFilters')
                ->assertSet('dateFrom', '2026-05-02')
                ->assertSet('dateTo', '2026-07-01');
        } finally {
            Carbon::setTestNow(null);
        }
    }
}
