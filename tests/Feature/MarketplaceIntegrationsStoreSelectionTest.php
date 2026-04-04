<?php

namespace Tests\Feature;

use App\Livewire\MarketplaceIntegrations;
use App\Models\IntegrationConnection;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class MarketplaceIntegrationsStoreSelectionTest extends TestCase
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

    public function test_it_prefers_store_query_parameter_on_mount(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Integrations Ltd.',
            'tax_number' => '5' . $suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $firstStore = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'ZEM FIRST',
            'store_code' => 'FIRST-' . $suffix,
            'seller_id' => 'SELLER-FIRST-' . $suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $secondStore = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'hepsiburada',
            'store_name' => 'ZEM SECOND',
            'store_code' => 'SECOND-' . $suffix,
            'seller_id' => 'SELLER-SECOND-' . $suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        IntegrationConnection::query()->create([
            'store_id' => $firstStore->id,
            'provider' => 'trendyol',
            'auth_type' => 'api_key_secret',
            'credentials_encrypted' => [
                'api_key' => 'key-first',
                'api_secret' => 'secret-first',
            ],
            'api_base_url' => 'https://apigw.trendyol.com/',
            'status' => 'configured',
        ]);

        IntegrationConnection::query()->create([
            'store_id' => $secondStore->id,
            'provider' => 'hepsiburada',
            'auth_type' => 'merchant_id_service_key',
            'credentials_encrypted' => [
                'api_key' => 'service-key',
                'extra_user' => 'zem_dev',
            ],
            'api_base_url' => 'https://oms-external.hepsiburada.com/',
            'status' => 'configured',
        ]);

        $this->actingAs($user);

        Livewire::withQueryParams(['store' => $secondStore->id])
            ->test(MarketplaceIntegrations::class)
            ->assertSet('selectedStoreId', $secondStore->id)
            ->assertSee('ZEM SECOND');
    }
}
