<?php

namespace Tests\Feature\CustomerCare;

use App\Livewire\CustomerCare\Integrations;
use App\Models\IntegrationConnection;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\User;
use App\Services\Support\Integration\GenericCrmConnectorInterface;
use App\Services\Support\Integration\GenericErpConnectorInterface;
use App\Services\Support\Integration\CustomerCareIntegrationHubService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerCareExternalConnectorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private MarketplaceStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('customer-care.enabled', true);
        Config::set('customer-care.integration_hub_enabled', true);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $legal = LegalEntity::create([
            'user_id' => $this->admin->id,
            'name' => 'Connector Legal',
            'tax_number' => '1010101010',
        ]);
        $this->store = MarketplaceStore::create([
            'user_id' => $this->admin->id,
            'legal_entity_id' => $legal->id,
            'marketplace' => 'trendyol',
            'store_name' => 'Connector Store',
            'seller_id' => 'connector-seller',
            'status' => 'active',
            'is_active' => true,
        ]);
        $this->actingAs($this->admin);
    }

    public function test_connection_secret_is_encrypted_and_health_success_activates_connection(): void
    {
        Http::fake(['https://crm.example.test/health' => Http::response(['ok' => true])]);

        Livewire::test(Integrations::class)
            ->set('selectedStoreId', $this->store->id)
            ->set('externalProvider', 'crm')
            ->set('externalBaseUrl', 'https://crm.example.test')
            ->set('externalAuthType', 'bearer')
            ->set('externalAccessToken', 'top-secret-crm-token')
            ->set('externalHealthPath', '/health')
            ->set('externalResourcePath', '/v1/contacts')
            ->call('saveExternalConnection')
            ->call('testExternalConnection');

        $connection = IntegrationConnection::where('store_id', $this->store->id)->where('provider', 'crm')->firstOrFail();
        $this->assertSame('active', $connection->status);
        $this->assertSame('top-secret-crm-token', $connection->credentials_encrypted['access_token']);
        $raw = DB::table('integration_connections')->where('id', $connection->id)->value('credentials_encrypted');
        $this->assertStringNotContainsString('top-secret-crm-token', (string) $raw);
        $this->assertNotNull($connection->last_verified_at);
    }

    public function test_private_network_base_url_is_rejected_fail_closed(): void
    {
        Http::fake();
        IntegrationConnection::create([
            'store_id' => $this->store->id,
            'provider' => 'crm',
            'auth_type' => 'bearer',
            'credentials_encrypted' => ['access_token' => 'secret-token'],
            'api_base_url' => 'https://127.0.0.1',
            'status' => 'pending_verification',
        ]);

        Livewire::test(Integrations::class)
            ->set('selectedStoreId', $this->store->id)
            ->set('externalProvider', 'crm')
            ->call('testExternalConnection')
            ->assertSet('errorMessage', fn (string $message) => str_contains($message, 'Özel/rezerve IP'));
        Http::assertNothingSent();
        $this->assertSame('error', IntegrationConnection::where('store_id', $this->store->id)->where('provider', 'crm')->value('status'));
    }

    public function test_noncanonical_numeric_and_credentialed_base_urls_are_rejected(): void
    {
        Http::fake();
        foreach (['https://2130706433', 'https://user:pass@example.com', 'https://crm.example.test/base'] as $index => $url) {
            $connection = IntegrationConnection::create([
                'store_id' => $this->store->id,
                'provider' => 'crm-' . $index,
                'auth_type' => 'bearer',
                'credentials_encrypted' => ['access_token' => 'secret-token'],
                'api_base_url' => $url,
                'status' => 'pending_verification',
            ]);

            $result = app(\App\Services\Support\Integration\CustomerCareHttpConnector::class)
                ->healthCheck($connection);
            $this->assertFalse($result['success']);
        }

        Http::assertNothingSent();
    }

    public function test_crm_and_erp_connectors_send_versioned_idempotent_contracts(): void
    {
        Http::fake([
            'https://crm.example.test/v1/contacts' => Http::response(['id' => 'crm-77'], 201),
            'https://erp.example.test/v1/orders' => Http::response(['external_id' => 'erp-88'], 200),
        ]);
        IntegrationConnection::create([
            'store_id' => $this->store->id,
            'provider' => 'crm',
            'auth_type' => 'bearer',
            'credentials_encrypted' => ['access_token' => 'crm-token', 'contacts_path' => '/v1/contacts'],
            'api_base_url' => 'https://crm.example.test',
            'status' => 'active',
        ]);
        IntegrationConnection::create([
            'store_id' => $this->store->id,
            'provider' => 'erp',
            'auth_type' => 'api_key',
            'credentials_encrypted' => ['api_key' => 'erp-token', 'orders_path' => '/v1/orders'],
            'api_base_url' => 'https://erp.example.test',
            'status' => 'active',
        ]);

        $crm = app(GenericCrmConnectorInterface::class)->syncContact([
            'store_id' => $this->store->id,
            'external_id' => 'customer-77',
            'email_hash' => hash('sha256', 'person@example.test'),
        ]);
        $erp = app(GenericErpConnectorInterface::class)->pushOrder($this->store->id, [
            'order_number' => 'ORDER-88',
            'status' => 'shipped',
        ]);

        $this->assertTrue($crm['success']);
        $this->assertTrue($crm['queued']);
        $this->assertTrue($erp['success']);
        $this->assertTrue($erp['queued']);
        Http::assertNothingSent();
        app(CustomerCareIntegrationHubService::class)->processPending();
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->hasHeader('X-Zolm-Idempotency-Key')
            && $request['schema_version'] === '1.0');
    }
}
