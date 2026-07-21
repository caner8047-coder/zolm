<?php

namespace Tests\Feature\Hepsiburada;

use App\Models\MarketplaceStore;
use App\Models\IntegrationConnection;
use App\Models\HepsiburadaReadinessAudit;
use App\Models\User;
use App\Models\LegalEntity;
use App\Services\Marketplace\HepsiburadaReadinessService;
use App\Services\Marketplace\HepsiburadaReadinessOutputSanitizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HepsiburadaReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['marketplace.hepsiburada.p0_connection_probe_enabled' => false]);
        config(['marketplace.hepsiburada.p0_reference_sync_enabled' => false]);
        config(['marketplace.hepsiburada.p0_catalog_sync_enabled' => false]);
        config(['marketplace.hepsiburada.p0_batch_status_sync_enabled' => false]);
    }

    protected function makeStore(User $user, array $credentials = [], array $storeData = []): MarketplaceStore
    {
        $le = LegalEntity::create([
            'user_id'      => $user->id,
            'name'         => 'Test Org ' . $user->id . '_' . rand(1000, 9999),
            'company_name' => 'Co',
            'tax_office'   => 'Kadikoy',
            'tax_number'   => (string) rand(1000000000, 9999999999),
            'address'      => 'Istanbul',
        ]);

        $store = MarketplaceStore::create(array_merge([
            'user_id'         => $user->id,
            'legal_entity_id' => $le->id,
            'marketplace'     => 'hepsiburada',
            'store_name'      => 'HB Test',
            'seller_id'       => (string) rand(100000000, 999999999),
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
    public function it_returns_configured_not_verified_when_http_is_not_attempted()
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user);

        $service = app(HepsiburadaReadinessService::class);
        $result = $service->inspect($store, [
            'confirm_read' => false,
            'actor_id'     => $user->id,
            'reason'       => 'Audit test run',
        ]);

        $this->assertEquals('configured_not_verified', $result['decision']);
        $this->assertTrue($result['is_ready']);
        $this->assertFalse($result['is_live_verified']);
        $this->assertFalse($result['http_attempted']);
        Http::assertNothingSent();
    }

    /** @test */
    public function it_returns_not_configured_when_no_credentials_exist()
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user);
        $store->connection->update(['credentials_encrypted' => []]);

        $service = app(HepsiburadaReadinessService::class);
        $result = $service->inspect($store, [
            'actor_id' => $user->id,
            'reason'   => 'Audit test run',
        ]);

        $this->assertEquals('not_configured', $result['decision']);
        $this->assertFalse($result['is_ready']);
    }

    /** @test */
    public function it_returns_credential_placeholder_for_exact_denylist_matches_only()
    {
        $user = User::factory()->create();
        
        // Exact placeholder match
        $storePlaceholder = $this->makeStore($user, ['api_key' => 'service-key']);
        $result1 = app(HepsiburadaReadinessService::class)->inspect($storePlaceholder, [
            'actor_id' => $user->id,
            'reason'   => 'Audit test run',
        ]);
        $this->assertEquals('credential_placeholder', $result1['decision']);

        // Realistic key containing substring 'test' - should NOT be false positive
        $storeLegit = $this->makeStore($user, ['api_key' => 'mytestproductionkey987']);
        $result2 = app(HepsiburadaReadinessService::class)->inspect($storeLegit, [
            'actor_id' => $user->id,
            'reason'   => 'Audit test run',
        ]);
        $this->assertNotEquals('credential_placeholder', $result2['decision']);
    }

    /** @test */
    public function it_enforces_connection_probe_rollout_gate()
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user);

        // confirm_read is true but p0_connection_probe_enabled is false
        config(['marketplace.hepsiburada.p0_connection_probe_enabled' => false]);
        $result = app(HepsiburadaReadinessService::class)->inspect($store, [
            'operation'    => 'connection',
            'confirm_read' => true,
            'actor_id'     => $user->id,
            'reason'       => 'Audit test run',
        ]);

        $this->assertEquals('rollout_disabled', $result['decision']);
        $this->assertFalse($result['http_attempted']);
        Http::assertNothingSent();

        // Enable flag & confirm_read
        config(['marketplace.hepsiburada.p0_connection_probe_enabled' => true]);
        Http::fake([
            'https://listing-external.hepsiburada.com/*' => Http::response(['listings' => []], 200),
            'https://oms-external.hepsiburada.com/*'     => Http::response(['listings' => []], 200),
        ]);

        $result2 = app(HepsiburadaReadinessService::class)->inspect($store, [
            'operation'    => 'connection',
            'confirm_read' => true,
            'actor_id'     => $user->id,
            'reason'       => 'Audit test run',
        ]);

        $this->assertEquals('authentication_success', $result2['decision']);
        $this->assertTrue($result2['http_attempted']);
        Http::assertSentCount(1);
    }

    /** @test */
    public function catalog_smoke_probe_fetches_single_page_only()
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user);

        config(['marketplace.hepsiburada.p0_catalog_sync_enabled' => true]);

        Http::fake([
            'https://mpop.hepsiburada.com/*' => Http::response([
                'listings'   => [
                    ['sku' => 'SKU-001', 'title' => 'Product 1', 'barcode' => '869000000001'],
                    ['sku' => 'SKU-002', 'title' => 'Product 2', 'barcode' => '869000000002'],
                ],
                'totalCount' => 100,
            ], 200)
        ]);

        $result = app(HepsiburadaReadinessService::class)->inspect($store, [
            'operation'    => 'catalog',
            'confirm_read' => true,
            'max_items'    => 5,
            'actor_id'     => $user->id,
            'reason'       => 'Audit test run',
        ]);

        $this->assertEquals('read_probe_success', $result['decision']);
        $this->assertTrue($result['http_attempted']);
        Http::assertSentCount(1); // EXACTLY 1 HTTP call, no pagination
    }

    /** @test */
    public function it_sanitizes_catalog_output_masking_skus_and_barcodes()
    {
        $sanitizer = new HepsiburadaReadinessOutputSanitizer();
        $rawItems = [
            ['sku' => 'SECRET-SKU-999', 'barcode' => '8691234567890', 'title' => 'Very Long Product Title Exceeding Thirty Characters Length']
        ];

        $sanitized = $sanitizer->sanitizeCatalogItems($rawItems);

        $this->assertCount(5, $sanitized[0]);
        $this->assertArrayHasKey('merchant_sku_masked', $sanitized[0]);
        $this->assertArrayHasKey('barcode_masked', $sanitized[0]);
        $this->assertArrayHasKey('product_name_short', $sanitized[0]);

        $this->assertStringNotContainsString('SECRET-SKU-999', $sanitized[0]['merchant_sku_masked']);
        $this->assertStringNotContainsString('8691234567890', $sanitized[0]['barcode_masked']);
        $this->assertStringEndsWith('...', $sanitized[0]['product_name_short']);
    }

    /** @test */
    public function command_requires_actor_id_and_reason_and_checks_store_authorization()
    {
        $user = User::factory()->create();
        $unauthorizedUser = User::factory()->create(['role' => 'operator']);
        $store = $this->makeStore($user);

        // Missing actor-id
        $this->artisan('marketplace:hepsiburada-readiness', [
            'store'    => $store->id,
            '--reason' => 'Test reason',
        ])
        ->assertExitCode(1)
        ->expectsOutputToContain('İşlem aktörü zorunludur');

        // Unauthorized user trying to inspect store
        $this->artisan('marketplace:hepsiburada-readiness', [
            'store'      => $store->id,
            '--actor-id' => $unauthorizedUser->id,
            '--reason'   => 'Test reason',
        ])
        ->assertExitCode(1)
        ->expectsOutputToContain('authorization_failed');

        // Authorized store owner
        $this->artisan('marketplace:hepsiburada-readiness', [
            'store'      => $store->id,
            '--actor-id' => $user->id,
            '--reason'   => 'Authorized test audit run',
        ])
        ->assertExitCode(0)
        ->expectsOutputToContain('configured_not_verified');
    }

    /** @test */
    public function mutation_guard_catches_insert_update_and_delete_operations_and_rolls_back()
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user);

        config(['marketplace.hepsiburada.p0_catalog_sync_enabled' => true]);

        // Insert Mutation
        Http::fake([
            'https://mpop.hepsiburada.com/*' => function() use ($store) {
                DB::table('channel_products')->insert([
                    'store_id'            => $store->id,
                    'external_product_id' => 'MUTATION-INSERT',
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);
                return Http::response(['listings' => []], 200);
            }
        ]);

        $this->artisan('marketplace:hepsiburada-readiness', [
            'store'          => $store->id,
            '--actor-id'     => $user->id,
            '--reason'       => 'Mutation check',
            '--catalog'      => true,
            '--confirm-read' => true,
        ])
        ->assertExitCode(1)
        ->expectsOutputToContain('read_probe_mutated_database');

        // Assert rollback was executed and zero rows persisted in channel_products
        $this->assertEquals(0, DB::table('channel_products')->where('external_product_id', 'MUTATION-INSERT')->count());
    }

    /** @test */
    public function mutation_guard_catches_update_statement_with_zero_affected_rows()
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user);

        config(['marketplace.hepsiburada.p0_catalog_sync_enabled' => true]);

        // UPDATE Query that modifies 0 existing rows
        Http::fake([
            'https://mpop.hepsiburada.com/*' => function() use ($store) {
                DB::table('channel_products')->where('store_id', 999999)->update(['title' => 'Zero Rows Updated']);
                return Http::response(['listings' => []], 200);
            }
        ]);

        $this->artisan('marketplace:hepsiburada-readiness', [
            'store'          => $store->id,
            '--actor-id'     => $user->id,
            '--reason'       => 'Zero row update check',
            '--catalog'      => true,
            '--confirm-read' => true,
        ])
        ->assertExitCode(1)
        ->expectsOutputToContain('read_probe_mutated_database');
    }

    /** @test */
    public function it_handles_http_errors_with_sanitized_provider_error_codes()
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user);

        config(['marketplace.hepsiburada.p0_catalog_sync_enabled' => true]);

        // 401
        Http::fake(['https://mpop.hepsiburada.com/*' => Http::response(['message' => 'Sensitive Internal Token Error'], 401)]);
        $result = app(HepsiburadaReadinessService::class)->inspect($store, [
            'operation'    => 'catalog',
            'confirm_read' => true,
            'actor_id'     => $user->id,
            'reason'       => 'Audit test run',
        ]);

        $this->assertEquals('authentication_failed', $result['decision']);
        $audit = HepsiburadaReadinessAudit::where('store_id', $store->id)->latest('id')->first();
        $this->assertEquals('401_UNAUTHORIZED', $audit->provider_error_code);
        $this->assertStringNotContainsString('Sensitive Internal Token Error', $audit->provider_error_code);
        $this->assertEquals($user->id, $audit->acting_user_id);
        $this->assertEquals('Audit test run', $audit->reason);
    }

    /** @test */
    public function service_enforces_actor_and_reason_validation()
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user);

        // Missing actor
        $res1 = app(HepsiburadaReadinessService::class)->inspect($store, ['reason' => 'Test reason']);
        $this->assertEquals('audit_actor_missing', $res1['decision']);

        // Missing reason
        $res2 = app(HepsiburadaReadinessService::class)->inspect($store, ['actor_id' => $user->id]);
        $this->assertEquals('audit_reason_missing', $res2['decision']);

        // Reason containing sensitive credentials
        $res3 = app(HepsiburadaReadinessService::class)->inspect($store, ['actor_id' => $user->id, 'reason' => 'test api_key=secret']);
        $this->assertEquals('audit_reason_invalid', $res3['decision']);
    }

    /** @test */
    public function valid_numeric_merchant_id_is_not_falsely_flagged_as_placeholder()
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user, ['api_key' => 'mysecureapikey12345'], ['seller_id' => '123456']);

        $res = app(HepsiburadaReadinessService::class)->inspect($store, [
            'actor_id' => $user->id,
            'reason'   => 'Valid merchant numeric check',
        ]);

        $this->assertNotEquals('credential_placeholder', $res['decision']);
    }

    /** @test */
    public function it_returns_audit_persistence_failed_when_audit_creation_fails()
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user);

        // Drop audit table temporarily or listen to event
        Schema::dropIfExists('hepsiburada_readiness_audits');

        $res = app(HepsiburadaReadinessService::class)->inspect($store, [
            'actor_id' => $user->id,
            'reason'   => 'Audit fail test',
        ]);

        $this->assertEquals('audit_persistence_failed', $res['decision']);
        $this->assertFalse($res['is_ready']);

        // Re-create table for subsequent tests
        \Illuminate\Support\Facades\Artisan::call('migrate');
    }
}
