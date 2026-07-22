<?php

namespace Tests\Feature;

use App\Livewire\MarketplaceIntegrations;
use App\Livewire\MarketplaceMatchingCenter;
use App\Livewire\MarketplaceOverview;
use App\Livewire\MpProductsManager;
use App\Models\ChannelOrder;
use App\Models\IntegrationConnection;
use App\Models\IntegrationSyncRun;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\MpOrder;
use App\Models\MpPeriod;
use App\Models\OrderFinancialEvent;
use App\Models\OrderProfitSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class MarketplaceOperationsExportTest extends TestCase
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

    public function test_integrations_exports_streamed_csv_responses(): void
    {
        [$user, $store] = $this->makeStoreGraph();

        IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'orders',
            'trigger_type' => 'smoke_test',
            'status' => 'completed',
            'items_received' => 2,
            'notes_json' => [
                'smoke_test' => true,
                'diagnostics' => [
                    'package_count' => 2,
                    'warnings' => ['Eksik barkod'],
                ],
            ],
        ]);

        $this->actingAs($user);

        $component = app(MarketplaceIntegrations::class);
        $component->selectedStoreId = $store->id;

        $this->assertInstanceOf(StreamedResponse::class, $component->exportReadinessCsv());
        $this->assertInstanceOf(StreamedResponse::class, $component->exportSelectedStoreSmokeCsv());
    }

    public function test_overview_exports_streamed_csv_response(): void
    {
        [$user, $store] = $this->makeStoreGraph();

        IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'orders',
            'trigger_type' => 'smoke_test',
            'status' => 'completed',
            'items_received' => 1,
            'notes_json' => [
                'smoke_test' => true,
                'diagnostics' => [
                    'package_count' => 1,
                    'warnings' => [],
                ],
            ],
        ]);

        $this->actingAs($user);

        $component = app(MarketplaceOverview::class);

        $period = MpPeriod::query()->create([
            'user_id' => $user->id,
            'seller_id' => $store->seller_id,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'marketplace' => 'trendyol',
            'status' => 'completed',
        ]);

        $legacyOrderNumber = 'LEGACY-EXPORT-'.random_int(100000, 999999);

        $channelOrder = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'external_order_id' => $legacyOrderNumber,
            'order_number' => $legacyOrderNumber,
            'order_status' => 'Teslim Edildi',
            'ordered_at' => now()->subDay(),
        ]);

        MpOrder::query()->create([
            'period_id' => $period->id,
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'source_marketplace' => 'trendyol',
            'order_number' => $legacyOrderNumber,
            'status' => 'Teslim Edildi',
            'order_date' => now()->subDay(),
            'payment_date' => now()->subHours(4)->toDateString(),
            'gross_amount' => 1000,
            'net_hakedis' => 800,
            'projected_at' => now()->subMinutes(10),
        ]);

        OrderFinancialEvent::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'channel_order_id' => $channelOrder->id,
            'event_source' => 'legacy_mp_order',
            'event_type' => 'seller_revenue',
            'external_event_id' => sha1('legacy-export-'.$legacyOrderNumber),
            'reference_number' => $legacyOrderNumber,
            'event_date' => now()->subHours(4),
            'settlement_date' => now()->subHours(4),
            'amount' => 1000,
            'currency' => 'TRY',
            'direction' => 'credit',
            'status' => 'posted',
        ]);

        OrderProfitSnapshot::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $channelOrder->id,
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
            'confirmed_profit' => 220,
            'margin_percent' => 22,
            'calculated_at' => now(),
            'version' => 1,
        ]);

        $this->assertInstanceOf(StreamedResponse::class, $component->exportHealthReportCsv());
        $this->assertInstanceOf(StreamedResponse::class, $component->exportFailureReportCsv());
        $this->assertInstanceOf(StreamedResponse::class, $component->exportDiagnosticsReportCsv());
        $this->assertInstanceOf(StreamedResponse::class, $component->exportDiagnosticsGuidanceCsv());
        $this->assertInstanceOf(StreamedResponse::class, $component->exportLegacyProjectionCsv());
    }

    public function test_products_and_matching_exports_streamed_csv_response(): void
    {
        [$user, $store] = $this->makeStoreGraph();

        IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'products',
            'trigger_type' => 'smoke_test',
            'status' => 'completed',
            'items_received' => 2,
            'notes_json' => [
                'smoke_test' => true,
                'diagnostics' => [
                    'missing_listing_id_count' => 2,
                    'missing_sale_price_count' => 1,
                    'warnings' => ['Listing alanlari eksik'],
                ],
            ],
        ]);

        IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'orders',
            'trigger_type' => 'smoke_test',
            'status' => 'completed',
            'items_received' => 2,
            'notes_json' => [
                'smoke_test' => true,
                'diagnostics' => [
                    'missing_stock_code_count' => 2,
                    'missing_barcode_count' => 1,
                    'warnings' => ['Eşleşme alanlari eksik'],
                ],
            ],
        ]);

        $this->actingAs($user);

        $productsComponent = app(MpProductsManager::class);
        $matchingComponent = app(MarketplaceMatchingCenter::class);

        $this->assertInstanceOf(StreamedResponse::class, $productsComponent->exportDiagnosticsGuidanceCsv());
        $this->assertInstanceOf(StreamedResponse::class, $matchingComponent->exportDiagnosticsGuidanceCsv());
    }

    /**
     * @return array{0: User, 1: MarketplaceStore}
     */
    protected function makeStoreGraph(): array
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Export Ltd.',
            'tax_number' => '9'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'ZEM EXPORT',
            'store_code' => 'EXP-'.$suffix,
            'seller_id' => 'EXP-'.$suffix,
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
                'store_front_code' => '12345',
            ],
            'api_base_url' => 'https://apigw.trendyol.com/',
            'status' => 'configured',
        ]);

        return [$user, $store];
    }
}
