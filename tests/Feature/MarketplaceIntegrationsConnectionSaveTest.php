<?php

namespace Tests\Feature;

use App\Livewire\MarketplaceIntegrations;
use App\Models\IntegrationConnection;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class MarketplaceIntegrationsConnectionSaveTest extends TestCase
{
    /** @var array<int, int> */
    protected array $createdStoreIds = [];

    /** @var array<int, int> */
    protected array $createdEntityIds = [];

    /** @var array<int, int> */
    protected array $createdUserIds = [];

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

    protected function tearDown(): void
    {
        if ($this->createdStoreIds !== []) {
            IntegrationConnection::query()->whereIn('store_id', $this->createdStoreIds)->delete();
            MarketplaceStore::query()->whereKey($this->createdStoreIds)->delete();
        }

        if ($this->createdEntityIds !== []) {
            LegalEntity::query()->whereKey($this->createdEntityIds)->delete();
        }

        if ($this->createdUserIds !== []) {
            User::query()->whereKey($this->createdUserIds)->delete();
        }

        parent::tearDown();
    }

    public function test_it_uses_store_url_as_api_base_url_for_woocommerce_when_api_url_is_blank(): void
    {
        $user = User::factory()->create();
        $this->createdUserIds[] = $user->id;
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Woo Ltd.',
            'tax_number' => '9'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
        $this->createdEntityIds[] = $legalEntity->id;

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'woocommerce',
            'store_name' => 'ZEM WOO',
            'store_code' => 'WOO-'.$suffix,
            'seller_id' => 'woo-'.$suffix,
            'status' => 'draft',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
        $this->createdStoreIds[] = $store->id;

        $this->actingAs($user);

        Livewire::withQueryParams(['store' => $store->id])
            ->test(MarketplaceIntegrations::class)
            ->set('connectionForm.authType', 'api_key_secret')
            ->set('connectionForm.apiBaseUrl', '')
            ->set('connectionForm.webhookSecret', '')
            ->set('connectionForm.apiKey', 'ck_test')
            ->set('connectionForm.apiSecret', 'cs_test')
            ->set('connectionForm.storeFrontCode', '')
            ->set('connectionForm.extraUser', '')
            ->set('connectionForm.extraPassword', '')
            ->set('connectionForm.storeUrl', 'https://shop.example.com')
            ->call('saveConnection')
            ->assertHasNoErrors();

        $store->refresh();
        $connection = $store->connection()->firstOrFail();

        $this->assertSame('https://shop.example.com', $connection->api_base_url);
        $this->assertSame('https://shop.example.com', data_get($connection->credentials_encrypted, 'store_url'));
        $this->assertSame('configured', $connection->status);
    }
}
