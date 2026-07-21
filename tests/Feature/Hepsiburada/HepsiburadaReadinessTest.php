<?php
 
namespace Tests\Feature\Hepsiburada;
 
use App\Models\MarketplaceStore;
use App\Models\IntegrationConnection;
use App\Models\HepsiburadaReadinessAudit;
use App\Models\User;
use App\Models\LegalEntity;
use App\Services\Marketplace\HepsiburadaReadinessService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HepsiburadaReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['marketplace.hepsiburada.p0_reference_sync_enabled' => false]);
        config(['marketplace.hepsiburada.p0_catalog_sync_enabled' => false]);
        config(['marketplace.hepsiburada.p0_batch_status_sync_enabled' => false]);
    }

    protected function makeStore(User $user, array $credentials = [], array $storeData = []): MarketplaceStore
    {
        $le = LegalEntity::create([
            'user_id'      => $user->id,
            'name'         => 'Test Org ' . $user->id,
            'company_name' => 'Co',
            'tax_office'   => 'Kadikoy',
            'tax_number'   => '1234567890',
            'address'      => 'Istanbul',
        ]);

        $store = MarketplaceStore::create(array_merge([
            'user_id'         => $user->id,
            'legal_entity_id' => $le->id,
            'marketplace'     => 'hepsiburada',
            'store_name'      => 'HB Test',
            'seller_id'       => '987654321',
            'timezone'        => 'Europe/Istanbul',
            'currency'        => 'TRY',
            'is_active'       => true,
        ], $storeData));

        IntegrationConnection::create([
            'store_id'              => $store->id,
            'provider'              => 'hepsiburada',
            'auth_type'             => 'merchant_id_service_key',
            'credentials_encrypted' => array_merge([
                'api_key'    => 'hb_actual_secure_token_key',
                'extra_user' => 'ZOLM',
            ], $credentials),
            'api_base_url'          => 'https://oms-external.hepsiburada.com/',
            'status'                => 'configured',
        ]);

        return $store;
    }

    /** @test */
    public function it_returns_not_configured_when_no_credentials_exist()
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user, ['api_key' => '']);
        $store->connection->update(['credentials_encrypted' => []]);

        $service = app(HepsiburadaReadinessService::class);
        $result = $service->inspect($store);

        $this->assertEquals('not_configured', $result['decision']);
        $this->assertFalse($result['is_ready']);
    }

    /** @test */
    public function it_returns_credential_placeholder_when_placeholder_value_is_detected()
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user, ['api_key' => 'service-key-placeholder']);

        $service = app(HepsiburadaReadinessService::class);
        $result = $service->inspect($store);

        $this->assertEquals('credential_placeholder', $result['decision']);
        $this->assertFalse($result['is_ready']);
    }

    /** @test */
    public function it_returns_merchant_id_mismatch_when_seller_id_does_not_match_credentials()
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user, ['merchant_id' => 'mismatch-id-999']);

        $service = app(HepsiburadaReadinessService::class);
        $result = $service->inspect($store);

        $this->assertEquals('merchant_id_mismatch', $result['decision']);
        $this->assertFalse($result['is_ready']);
    }

    /** @test */
    public function it_returns_rollout_disabled_when_flag_is_off_and_probe_requested()
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user);

        $service = app(HepsiburadaReadinessService::class);
        
        // categories operation with gate off
        $result = $service->inspect($store, ['operation' => 'categories']);
        $this->assertEquals('rollout_disabled', $result['decision']);
    }

    /** @test */
    public function it_does_not_leak_secrets_in_readiness_audits()
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user);

        $service = app(HepsiburadaReadinessService::class);
        $service->inspect($store);

        $audit = HepsiburadaReadinessAudit::where('store_id', $store->id)->first();
        $this->assertNotNull($audit);
        
        // Assert that the database columns do not contain the secret string
        $serialized = json_encode($audit->toArray());
        $this->assertStringNotContainsString('hb_actual_secure_token_key', $serialized);
    }

    /** @test */
    public function command_runs_without_confirm_read_and_does_not_make_http_requests()
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user);

        $this->artisan('marketplace:hepsiburada-readiness', [
            'store' => $store->id
        ])
        ->assertExitCode(0)
        ->expectsOutputToContain('authentication_success');

        Http::assertNothingSent();
    }

    /** @test */
    public function command_makes_http_requests_only_when_confirm_read_is_passed()
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user);

        config(['marketplace.hepsiburada.p0_catalog_sync_enabled' => true]);

        Http::fake([
            'https://mpop.hepsiburada.com/*' => Http::response([
                'listings' => [
                    ['sku' => 'SKU-1', 'price' => 100],
                ]
            ], 200)
        ]);

        $this->artisan('marketplace:hepsiburada-readiness', [
            'store' => $store->id,
            '--catalog' => true,
            '--confirm-read' => true,
        ])
        ->assertExitCode(0)
        ->expectsOutputToContain('read_probe_success');

        Http::assertSentCount(1);
    }

    /** @test */
    public function it_handles_401_unauthorized_gracefully()
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user);

        config(['marketplace.hepsiburada.p0_catalog_sync_enabled' => true]);

        Http::fake(['https://mpop.hepsiburada.com/*' => Http::response(['message' => 'Invalid token'], 401)]);
        $result = app(HepsiburadaReadinessService::class)->inspect($store, ['operation' => 'catalog', 'confirm_read' => true]);
        $this->assertEquals('authentication_failed', $result['decision']);
    }

    /** @test */
    public function it_handles_403_forbidden_gracefully()
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user);

        config(['marketplace.hepsiburada.p0_catalog_sync_enabled' => true]);

        Http::fake(['https://mpop.hepsiburada.com/*' => Http::response(['message' => 'No permission'], 403)]);
        $result = app(HepsiburadaReadinessService::class)->inspect($store, ['operation' => 'catalog', 'confirm_read' => true]);
        $this->assertEquals('permission_blocked', $result['decision']);
    }

    /** @test */
    public function it_handles_429_rate_limit_gracefully()
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user);

        config(['marketplace.hepsiburada.p0_catalog_sync_enabled' => true]);

        Http::fake(['https://mpop.hepsiburada.com/*' => Http::response(['message' => 'Rate limit'], 429)]);
        $result = app(HepsiburadaReadinessService::class)->inspect($store, ['operation' => 'catalog', 'confirm_read' => true]);
        $this->assertEquals('rate_limited', $result['decision']);
    }

    /** @test */
    public function it_handles_500_server_error_gracefully()
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user);

        config(['marketplace.hepsiburada.p0_catalog_sync_enabled' => true]);

        Http::fake(['https://mpop.hepsiburada.com/*' => Http::response(['message' => 'Internal server error'], 500)]);
        $result = app(HepsiburadaReadinessService::class)->inspect($store, ['operation' => 'catalog', 'confirm_read' => true]);
        $this->assertEquals('provider_unavailable', $result['decision']);
    }

    /** @test */
    public function db_mutation_guard_detects_changes_and_flags_violation()
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user);

        config(['marketplace.hepsiburada.p0_catalog_sync_enabled' => true]);

        Http::fake([
            'https://mpop.hepsiburada.com/*' => function() use ($store) {
                DB::table('channel_products')->insert([
                    'store_id' => $store->id,
                    'external_product_id' => 'MUTATION-TEST',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                return Http::response(['listings' => []], 200);
            }
        ]);

        $this->artisan('marketplace:hepsiburada-readiness', [
            'store' => $store->id,
            '--catalog' => true,
            '--confirm-read' => true,
        ])
        ->assertExitCode(1)
        ->expectsOutputToContain('read_probe_mutated_database');
    }

    /** @test */
    public function livewire_metadata_computed_property_resolves_hepsiburada_correctly()
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user);

        $this->actingAs($user);

        $component = new \App\Livewire\MarketplaceIntegrations();
        $component->selectedStoreId = $store->id;
        $component->storeForm = ['marketplace' => 'hepsiburada'];
        
        // Manually set selectedStore relation or load it
        $component->mount();
        $component->selectStore($store->id);

        $metadata = $component->selectedStoreHepsiburadaReadinessMetadata;

        $this->assertNotEmpty($metadata);
        $this->assertTrue($metadata['has_credentials']);
        $this->assertTrue($metadata['has_merchant_id']);
        $this->assertFalse($metadata['reference_gate']);
    }
}
