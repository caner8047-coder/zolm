<?php

namespace Tests\Feature\Livewire\Marketplace;

use App\Livewire\MarketplaceIntegrations;
use App\Models\ActivityLog;
use App\Models\IntegrationConnection;
use App\Models\MarketplaceStore;
use App\Models\User;
use App\Services\Marketplace\MarketplaceStoreAccessResolver;
use App\Services\Marketplace\MarketplaceTenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class MarketplaceIntegrationsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $ownerUser;

    protected User $operatorUser;

    protected User $unauthorizedUser;

    protected MarketplaceStore $ownerStore;

    protected MarketplaceStore $otherStore;

    protected function setUp(): void
    {
        parent::setUp();

        // Target Owner User is explicitly User-15
        $this->ownerUser = User::factory()->create([
            'id' => 15,
            'role' => 'operator',
            'is_active' => true,
        ]);

        $this->operatorUser = User::factory()->create([
            'id' => 99,
            'role' => 'operator',
            'is_active' => true,
        ]);

        $this->unauthorizedUser = User::factory()->create([
            'id' => 88,
            'role' => 'operator',
            'is_active' => true,
        ]);

        // Target Owner Store is explicitly Store-14
        $this->ownerStore = MarketplaceStore::factory()->create([
            'id' => 14,
            'user_id' => 15,
            'store_name' => 'Owner Store 14',
            'marketplace' => 'trendyol',
            'seller_id' => '102493',
            'status' => 'active',
        ]);

        $this->otherStore = MarketplaceStore::factory()->create([
            'id' => 20,
            'user_id' => 88,
            'store_name' => 'Other Store 20',
            'marketplace' => 'trendyol',
            'seller_id' => '1002',
            'status' => 'active',
        ]);

        IntegrationConnection::create([
            'store_id' => 14,
            'provider' => 'trendyol',
            'auth_type' => 'api_key',
            'credentials_encrypted' => [
                'api_key' => 'owner-key',
                'api_secret' => 'owner-secret',
            ],
            'status' => 'connected',
        ]);

        IntegrationConnection::create([
            'store_id' => 20,
            'provider' => 'trendyol',
            'auth_type' => 'api_key',
            'credentials_encrypted' => [
                'api_key' => 'other-key',
                'api_secret' => 'other-secret',
            ],
            'status' => 'connected',
        ]);
    }

    /**
     * Route & Middleware tests
     */
    public function test_route_owner_can_open_screen(): void
    {
        $this->actingAs($this->ownerUser)
            ->get('/marketplace-integrations')
            ->assertStatus(200);
    }

    public function test_route_guest_cannot_open_screen(): void
    {
        $this->get('/marketplace-integrations')
            ->assertRedirect(route('login'));
    }

    public function test_route_operator_can_open_screen(): void
    {
        $this->actingAs($this->operatorUser)
            ->get('/marketplace-integrations')
            ->assertStatus(200);
    }

    public function test_new_store_form_exposes_ready_ikas_with_client_credentials_guide(): void
    {
        $component = Livewire::actingAs($this->ownerUser)
            ->test(MarketplaceIntegrations::class)
            ->call('startNewStore')
            ->set('storeForm.marketplace', 'ikas')
            ->assertSet('connectionForm.authType', 'client_credentials')
            ->assertSet('connectionForm.apiBaseUrl', 'https://api.myikas.com/api/v2/admin/graphql');

        $this->assertSame('ikas', data_get($component->get('providerOptions'), 'ikas'));
        $this->assertSame('ready', data_get($component->get('selectedProviderMeta'), 'status'));
        $this->assertSame('ikas mağaza / merchant kimliği', data_get($component->get('selectedConnectionGuide'), 'seller_id_label'));
        $this->assertStringContainsString('Client credentials', data_get($component->get('selectedConnectionGuide'), 'seller_id_help'));
    }

    public function test_new_store_form_exposes_ready_ideasoft_oauth_guide(): void
    {
        $component = Livewire::actingAs($this->ownerUser)
            ->test(MarketplaceIntegrations::class)
            ->call('startNewStore')
            ->set('storeForm.marketplace', 'ideasoft')
            ->assertSet('connectionForm.authType', 'authorization_code')
            ->assertSet('connectionForm.apiBaseUrl', '');

        $this->assertSame('IdeaSoft', data_get($component->get('providerOptions'), 'ideasoft'));
        $this->assertSame('ready', data_get($component->get('selectedProviderMeta'), 'status'));
        $this->assertSame('Client ID', data_get($component->get('selectedConnectionGuide'), 'api_key_label'));
        $this->assertStringContainsString('OAuth', data_get($component->get('selectedConnectionGuide'), 'hints.1'));
    }

    public function test_new_store_form_exposes_ready_ticimax_membership_code_guide(): void
    {
        $component = Livewire::actingAs($this->ownerUser)
            ->test(MarketplaceIntegrations::class)
            ->call('startNewStore')
            ->set('storeForm.marketplace', 'ticimax')
            ->assertSet('connectionForm.authType', 'membership_code')
            ->assertSet('connectionForm.apiBaseUrl', '');

        $this->assertSame('Ticimax', data_get($component->get('providerOptions'), 'ticimax'));
        $this->assertSame('ready', data_get($component->get('selectedProviderMeta'), 'status'));
        $this->assertSame('Üye Kodu / Web Servis Şifresi', data_get($component->get('selectedConnectionGuide'), 'api_secret_label'));
        $this->assertStringContainsString('SiparisServis.svc', data_get($component->get('selectedConnectionGuide'), 'hints.2'));
    }

    public function test_new_store_form_exposes_ready_tsoft_service_user_guide(): void
    {
        $component = Livewire::actingAs($this->ownerUser)
            ->test(MarketplaceIntegrations::class)
            ->call('startNewStore')
            ->set('storeForm.marketplace', 'tsoft')
            ->assertSet('connectionForm.authType', 'username_password')
            ->assertSet('connectionForm.apiBaseUrl', '');

        $this->assertSame('T-Soft', data_get($component->get('providerOptions'), 'tsoft'));
        $this->assertSame('ready', data_get($component->get('selectedProviderMeta'), 'status'));
        $this->assertSame('Web Servis kullanıcı adı', data_get($component->get('selectedConnectionGuide'), 'api_key_label'));
        $this->assertStringContainsString('/rest1/auth/login', data_get($component->get('selectedConnectionGuide'), 'hints.4'));
    }

    public function test_new_store_form_exposes_ready_magento_access_token_guide(): void
    {
        $component = Livewire::actingAs($this->ownerUser)
            ->test(MarketplaceIntegrations::class)
            ->call('startNewStore')
            ->set('storeForm.marketplace', 'magento')
            ->assertSet('connectionForm.authType', 'access_token')
            ->assertSet('connectionForm.apiBaseUrl', '');

        $this->assertSame('Adobe Commerce / Magento', data_get($component->get('providerOptions'), 'magento'));
        $this->assertSame('ready', data_get($component->get('selectedProviderMeta'), 'status'));
        $this->assertSame('Integration Access Token', data_get($component->get('selectedConnectionGuide'), 'api_secret_label'));
        $this->assertStringContainsString('PaaS/on-prem', data_get($component->get('selectedConnectionGuide'), 'hints.0'));
        $this->assertStringContainsString('inventory/source-items', data_get($component->get('selectedConnectionGuide'), 'hints.6'));
    }

    public function test_route_access_does_not_leak_cross_tenant_data(): void
    {
        MarketplaceTenantContext::clearContext();

        $this->actingAs($this->operatorUser);

        Livewire::test(MarketplaceIntegrations::class)
            ->assertViewHas('stores', function ($stores) {
                return $stores->isEmpty();
            });
    }

    /**
     * Store resolver tests
     */
    public function test_resolver_owner_can_resolve_own_store(): void
    {
        $resolver = new MarketplaceStoreAccessResolver;
        $resolved = $resolver->resolveForView($this->ownerUser, 14);
        $this->assertEquals(14, $resolved->id);
    }

    public function test_resolver_owner_cannot_resolve_other_store(): void
    {
        $resolver = new MarketplaceStoreAccessResolver;
        $this->expectException(AuthorizationException::class);
        $resolver->resolveForView($this->ownerUser, 20);
    }

    public function test_resolver_operator_without_tenant_context_cannot_resolve_other_store(): void
    {
        MarketplaceTenantContext::clearContext();
        $resolver = new MarketplaceStoreAccessResolver;
        $this->expectException(AuthorizationException::class);
        $resolver->resolveForView($this->operatorUser, 14);
    }

    public function test_resolver_operator_with_valid_tenant_context_can_resolve(): void
    {
        MarketplaceTenantContext::setContext(14, 15, 'credential maintenance');
        $resolver = new MarketplaceStoreAccessResolver;
        $resolved = $resolver->resolveForView($this->operatorUser, 14);
        $this->assertEquals(14, $resolved->id);
    }

    public function test_resolver_manipulated_selected_store_id_is_rejected(): void
    {
        MarketplaceTenantContext::setContext(14, 15, 'other reason');
        $resolver = new MarketplaceStoreAccessResolver;
        $this->expectException(AuthorizationException::class);
        $resolver->resolveForCredentialManagement($this->operatorUser, 14);
    }

    /**
     * Tenant Context Expiration & Reason tests
     */
    public function test_tenant_context_expiration(): void
    {
        MarketplaceTenantContext::setContext(14, 15, 'credential maintenance', 1);
        sleep(2);

        $this->assertTrue(MarketplaceTenantContext::isExpired());
        $this->assertFalse(MarketplaceTenantContext::hasActiveContext());
    }

    public function test_resolver_fails_when_tenant_context_expired(): void
    {
        MarketplaceTenantContext::setContext(14, 15, 'credential maintenance', 1);
        sleep(2);

        $resolver = new MarketplaceStoreAccessResolver;
        $this->expectException(AuthorizationException::class);
        $resolver->resolveForCredentialManagement($this->operatorUser, 14);
    }

    public function test_tenant_context_can_be_cleared_explicitly(): void
    {
        MarketplaceTenantContext::setContext(14, 15, 'credential maintenance');
        $this->assertTrue(MarketplaceTenantContext::hasActiveContext());

        MarketplaceTenantContext::clearContext();
        $this->assertFalse(MarketplaceTenantContext::hasActiveContext());
    }

    public function test_tenant_context_requires_mandatory_reason(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MarketplaceTenantContext::setContext(14, 15, '   ');
    }

    /**
     * Credential save and protection tests
     */
    public function test_credential_save_owner_updates_own_credential(): void
    {
        $resolver = new MarketplaceStoreAccessResolver;
        $store = $resolver->resolveForCredentialManagement($this->ownerUser, 14);

        $this->actingAs($this->ownerUser);
        $this->travel(1)->seconds();

        Livewire::test(MarketplaceIntegrations::class, ['store' => 14])
            ->set('connectionForm.authType', 'api_key')
            ->set('connectionForm.apiKey', 'new-owner-key')
            ->set('connectionForm.apiSecret', 'new-owner-secret')
            ->call('saveConnection')
            ->assertSet('saveResult', 'credential_saved');

        $this->ownerStore->refresh();
        $this->assertEquals('new-owner-key', $this->ownerStore->connection->credentials_encrypted['api_key']);
        $this->assertEquals('new-owner-secret', $this->ownerStore->connection->credentials_encrypted['api_secret']);
    }

    public function test_credential_save_operator_updates_with_valid_tenant_context(): void
    {
        MarketplaceTenantContext::setContext(14, 15, 'credential maintenance');
        $resolver = new MarketplaceStoreAccessResolver;
        $store = $resolver->resolveForCredentialManagement($this->operatorUser, 14);

        $this->actingAs($this->operatorUser);
        $this->travel(1)->seconds();

        Livewire::test(MarketplaceIntegrations::class, ['store' => 14])
            ->set('connectionForm.authType', 'api_key')
            ->set('connectionForm.apiKey', 'operator-provided-key')
            ->set('connectionForm.apiSecret', 'operator-provided-secret')
            ->call('saveConnection')
            ->assertSet('saveResult', 'credential_saved');

        $this->ownerStore->refresh();
        $this->assertEquals('operator-provided-key', $this->ownerStore->connection->credentials_encrypted['api_key']);

        // Assert audit log exists and does NOT contain secrets
        $audit = ActivityLog::where('action', 'update_connection_credentials')->first();
        $this->assertNotNull($audit);
        $this->assertStringContainsString('Reason: credential maintenance', $audit->description);
        $this->assertStringNotContainsString('operator-provided-secret', json_encode($audit->metadata));
    }

    public function test_credential_save_operator_cannot_update_without_tenant_context(): void
    {
        MarketplaceTenantContext::clearContext();
        $this->actingAs($this->operatorUser);

        Livewire::test(MarketplaceIntegrations::class, ['store' => 14])
            ->set('connectionForm.authType', 'api_key')
            ->set('connectionForm.apiKey', 'unauthorized-key')
            ->set('connectionForm.apiSecret', 'unauthorized-secret')
            ->call('saveConnection')
            ->assertSet('saveResult', 'authorization_denied');
    }

    public function test_credential_save_secret_preserved_when_empty_or_asterisks(): void
    {
        $this->actingAs($this->ownerUser);

        // Preserve Secret when empty
        $this->travel(1)->seconds();
        Livewire::test(MarketplaceIntegrations::class, ['store' => 14])
            ->set('connectionForm.authType', 'api_key')
            ->set('connectionForm.apiKey', 'new-owner-key-2')
            ->set('connectionForm.apiSecret', '')
            ->call('saveConnection')
            ->assertSet('saveResult', 'credential_saved');

        $this->ownerStore->refresh();
        $this->assertEquals('owner-secret', $this->ownerStore->connection->credentials_encrypted['api_secret']);

        // Preserve Secret when asterisks
        $this->travel(1)->seconds();
        Livewire::test(MarketplaceIntegrations::class, ['store' => 14])
            ->set('connectionForm.authType', 'api_key')
            ->set('connectionForm.apiKey', 'new-owner-key-3')
            ->set('connectionForm.apiSecret', '********')
            ->call('saveConnection')
            ->assertSet('saveResult', 'credential_saved');

        $this->ownerStore->refresh();
        $this->assertEquals('owner-secret', $this->ownerStore->connection->credentials_encrypted['api_secret']);
    }

    public function test_credential_save_api_key_preserved_when_empty_or_asterisks(): void
    {
        $this->actingAs($this->ownerUser);

        // Preserve API Key when empty
        $this->travel(1)->seconds();
        Livewire::test(MarketplaceIntegrations::class, ['store' => 14])
            ->set('connectionForm.authType', 'api_key')
            ->set('connectionForm.apiKey', '')
            ->set('connectionForm.apiSecret', 'new-secret-val')
            ->call('saveConnection')
            ->assertSet('saveResult', 'credential_saved');

        $this->ownerStore->refresh();
        $this->assertEquals('owner-key', $this->ownerStore->connection->credentials_encrypted['api_key']);
        $this->assertEquals('new-secret-val', $this->ownerStore->connection->credentials_encrypted['api_secret']);

        // Preserve API Key when asterisks
        $this->travel(1)->seconds();
        Livewire::test(MarketplaceIntegrations::class, ['store' => 14])
            ->set('connectionForm.authType', 'api_key')
            ->set('connectionForm.apiKey', '********')
            ->set('connectionForm.apiSecret', 'another-secret-val')
            ->call('saveConnection')
            ->assertSet('saveResult', 'credential_saved');

        $this->ownerStore->refresh();
        $this->assertEquals('owner-key', $this->ownerStore->connection->credentials_encrypted['api_key']);
    }

    public function test_livewire_select_store_idor(): void
    {
        $this->actingAs($this->ownerUser);

        Livewire::test(MarketplaceIntegrations::class, ['store' => 14])
            ->call('selectStore', 20)
            ->assertSet('saveResult', 'authorization_denied');
    }

    public function test_livewire_save_connection_idor(): void
    {
        $this->actingAs($this->ownerUser);

        $component = Livewire::test(MarketplaceIntegrations::class, ['store' => 14]);

        $component->set('selectedStoreId', 20)
            ->call('saveConnection')
            ->assertSet('saveResult', 'authorization_denied');
    }

    public function test_livewire_validation_fails_for_placeholders_in_production(): void
    {
        $this->actingAs($this->ownerUser);

        // Fake production environment
        $this->app->detectEnvironment(fn () => 'production');

        Livewire::test(MarketplaceIntegrations::class, ['store' => 14])
            ->set('connectionForm.authType', 'api_key')
            ->set('connectionForm.apiKey', 'test-key')
            ->set('connectionForm.apiSecret', 'valid-secret')
            ->call('saveConnection')
            ->assertHasErrors('connectionForm.apiKey')
            ->assertSet('saveResult', 'validation_failed');
    }

    public function test_runtime_parity_headers_present_for_authenticated_users(): void
    {
        $response = $this->actingAs($this->ownerUser)
            ->get('/marketplace-integrations');

        $response->assertHeader('X-Zolm-Release', 'a7d8bd7');
        $response->assertHeader('X-Zolm-Runtime-ID');
    }

    public function test_runtime_parity_headers_absent_for_guests(): void
    {
        $response = $this->get('/marketplace-integrations');
        $this->assertFalse($response->headers->has('X-Zolm-Release'));
        $this->assertFalse($response->headers->has('X-Zolm-Runtime-ID'));
    }

    public function test_credential_save_trace_generates_correlation_id_and_audits(): void
    {
        $this->actingAs($this->ownerUser);

        $component = Livewire::test(MarketplaceIntegrations::class, ['store' => 14])
            ->set('connectionForm.authType', 'api_key')
            ->set('connectionForm.apiKey', 'owner-key') // same as before, no change
            ->set('connectionForm.apiSecret', 'owner-secret')
            ->call('saveConnection')
            ->assertSet('saveResult', 'credential_unchanged');

        $correlationId = $component->get('saveCorrelationId');
        $this->assertNotNull($correlationId);

        $audit = ActivityLog::where('action', 'save_connection_audit')
            ->where('metadata->correlation_id', $correlationId)
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals('credential_unchanged', $audit->metadata['save_result']);
        $this->assertEquals('a7d8bd7', $audit->metadata['release_sha']);
        $this->assertNotNull($audit->metadata['runtime_id']);

        // Assert secrets are not exposed in metadata
        $metaJson = json_encode($audit->metadata);
        $this->assertStringNotContainsString('owner-key', $metaJson);
        $this->assertStringNotContainsString('owner-secret', $metaJson);
    }

    public function test_validation_failure_is_audited(): void
    {
        $this->actingAs($this->ownerUser);

        try {
            Livewire::test(MarketplaceIntegrations::class, ['store' => 14])
                ->set('connectionForm.authType', '') // blank, validation fails
                ->call('saveConnection');
        } catch (ValidationException $e) {
            // expected
        }

        $audit = ActivityLog::where('action', 'save_connection_audit')
            ->where('metadata->save_result', 'validation_failed')
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals('validation_failed', $audit->metadata['save_result']);
        $this->assertFalse($audit->metadata['validation_passed']);
        $this->assertEquals('Illuminate\Validation\ValidationException', $audit->metadata['exception_class']);
    }
}
