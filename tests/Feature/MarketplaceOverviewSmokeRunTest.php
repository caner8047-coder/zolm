<?php

namespace Tests\Feature;

use App\Livewire\MarketplaceOverview;
use App\Models\IntegrationSyncRun;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketplaceOverviewSmokeRunTest extends TestCase
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

    public function test_it_separates_smoke_runs_from_regular_sync_history(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Smoke Overview Ltd.',
            'tax_number' => '8'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'ZEM SMOKE OVERVIEW',
            'store_code' => 'SMK-OVR-'.$suffix,
            'seller_id' => 'SMK-OVR-'.$suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $manualRun = IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'orders',
            'trigger_type' => 'manual',
            'status' => 'completed',
            'items_received' => 5,
            'notes_json' => [],
        ]);

        $smokeRun = IntegrationSyncRun::query()->create([
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

        $component = app(MarketplaceOverview::class);

        $this->assertCount(1, $component->recentSyncRuns());
        $this->assertSame($manualRun->id, $component->recentSyncRuns()->first()->id);
        $this->assertCount(1, $component->recentSmokeRuns());
        $this->assertSame($smokeRun->id, $component->recentSmokeRuns()->first()->id);
        $this->assertTrue($component->recentSmokeRuns()->first()->isSmokeTest());
    }
}
