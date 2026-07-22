<?php

namespace Tests\Feature;

use App\Models\IntegrationSyncRun;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketplaceDiagnosticsGuidanceCommandTest extends TestCase
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

    public function test_it_prints_guidance_summary(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Guidance Cmd Ltd.',
            'tax_number' => '1'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'shopify',
            'store_name' => 'ZEM GUIDE CMD',
            'store_code' => 'GUIDE-CMD-'.$suffix,
            'seller_id' => 'GUIDE-CMD-'.$suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'products',
            'trigger_type' => 'smoke_test',
            'status' => 'completed',
            'notes_json' => [
                'smoke_test' => true,
                'diagnostics' => [
                    'product_count' => 5,
                    'missing_listing_id_count' => 2,
                    'missing_sale_price_count' => 3,
                    'missing_stock_quantity_count' => 1,
                    'warnings' => ['Ürün cevabı boş geldi.'],
                ],
            ],
        ]);

        $this->artisan('marketplace:diagnostics-guidance', [
            'user' => $user->id,
            '--store' => $store->id,
            '--type' => 'products',
            '--smoke-only' => true,
        ])
            ->expectsOutputToContain('Pazaryeri diagnostik karar desteği')
            ->assertExitCode(0);
    }
}
