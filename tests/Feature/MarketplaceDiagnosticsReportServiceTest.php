<?php

namespace Tests\Feature;

use App\Models\IntegrationSyncRun;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\User;
use App\Services\Marketplace\MarketplaceDiagnosticsReportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketplaceDiagnosticsReportServiceTest extends TestCase
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

    public function test_it_aggregates_sync_diagnostics_by_store_and_sync_type(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Diagnostics Ltd.',
            'tax_number' => '3'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'ZEM DIAG',
            'store_code' => 'DIAG-'.$suffix,
            'seller_id' => 'DIAG-'.$suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'orders',
            'trigger_type' => 'smoke_test',
            'status' => 'completed',
            'notes_json' => [
                'diagnostics' => [
                    'package_count' => 2,
                    'order_count' => 2,
                    'item_count' => 4,
                    'missing_order_number_count' => 1,
                    'missing_stock_code_count' => 2,
                    'missing_barcode_count' => 1,
                    'warnings' => ['Bazı sipariş satırlarında hem stok kodu hem barkod eksik.'],
                ],
                'smoke_test' => true,
            ],
        ]);

        IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'orders',
            'trigger_type' => 'manual',
            'status' => 'completed',
            'notes_json' => [
                'diagnostics' => [
                    'package_count' => 1,
                    'order_count' => 1,
                    'item_count' => 2,
                    'missing_package_id_count' => 1,
                    'missing_item_line_id_count' => 1,
                    'missing_stock_code_count' => 1,
                    'warnings' => ['Bazı sipariş satırlarında hem stok kodu hem barkod eksik.'],
                ],
            ],
        ]);

        $summary = app(MarketplaceDiagnosticsReportService::class)->summaryForUser($user->id, [
            'hours' => 168,
            'limit' => 50,
        ]);

        $this->assertSame(2, $summary['totals']['runs']);
        $this->assertSame(1, $summary['totals']['groups']);
        $this->assertSame(1, $summary['totals']['smoke_runs']);
        $this->assertSame(2, $summary['totals']['warning_runs']);
        $this->assertSame(2, $summary['totals']['total_warnings']);

        $row = $summary['rows'][0];
        $this->assertSame('trendyol', $row['marketplace']);
        $this->assertSame('orders', $row['sync_type']);
        $this->assertSame(2, $row['total_runs']);
        $this->assertSame(1, $row['smoke_runs']);
        $this->assertSame(2, $row['warning_runs']);
        $this->assertSame(3, $row['missing_stock_code_count']);
        $this->assertSame(1, $row['missing_order_number_count']);
        $this->assertSame(1, $row['missing_package_id_count']);
        $this->assertSame(1, $row['missing_item_line_id_count']);
        $this->assertNotEmpty($row['top_warning']);
    }
}
