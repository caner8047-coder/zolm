<?php

namespace Tests\Feature\Livewire\Marketplace;

use App\Models\ActivityLog;
use App\Models\IntegrationConnection;
use App\Models\MarketplaceStore;
use App\Models\User;
use App\Services\Marketplace\MarketplaceStoreAccessResolver;
use App\Services\Marketplace\MarketplaceTenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
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

        $this->ownerUser = User::factory()->create([
            'role' => 'operator',
            'is_active' => true,
        ]);

        $this->operatorUser = User::factory()->create([
            'role' => 'operator',
            'is_active' => true,
        ]);

        $this->unauthorizedUser = User::factory()->create([
            'role' => 'operator',
            'is_active' => true,
        ]);

        $this->ownerStore = MarketplaceStore::factory()->create([
            'user_id' => $this->ownerUser->id,
            'store_name' => 'Owner Store',
            'marketplace' => 'trendyol',
            'seller_id' => '1001',
            'status' => 'active',
        ]);

        $this->otherStore = MarketplaceStore::factory()->create([
            'user_id' => $this->unauthorizedUser->id,
            'store_name' => 'Other Store',
            'marketplace' => 'trendyol',
            'seller_id' => '1002',
            'status' => 'active',
        ]);

        IntegrationConnection::create([
            'store_id' => $this->ownerStore->id,
            'provider' => 'trendyol',
            'auth_type' => 'api_key',
            'credentials_encrypted' => [
                'api_key' => 'owner-key',
                'api_secret' => 'owner-secret',
            ],
            'status' => 'connected',
        ]);

        IntegrationConnection::create([
            'store_id' => $this->otherStore->id,
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
     * Route tests
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

    public function test_route_access_does_not_leak_cross_tenant_data(): void
    {
        MarketplaceTenantContext::clearContext();
        
        $this->actingAs($this->operatorUser);
        
        Livewire::test(\App\Livewire\MarketplaceIntegrations::class)
            ->assertViewHas('stores', function ($stores) {
                return $stores->isEmpty();
            });
    }

    /**
     * Store resolver tests
     */
    public function test_resolver_owner_can_resolve_own_store(): void
    {
        $resolver = new MarketplaceStoreAccessResolver();
        $resolved = $resolver->resolveForView($this->ownerUser, $this->ownerStore->id);
        $this->assertEquals($this->ownerStore->id, $resolved->id);
    }

    public function test_resolver_owner_cannot_resolve_other_store(): void
    {
        $resolver = new MarketplaceStoreAccessResolver();
        $this->expectException(AuthorizationException::class);
        $resolver->resolveForView($this->ownerUser, $this->otherStore->id);
    }

    public function test_resolver_operator_without_tenant_context_cannot_resolve_other_store(): void
    {
        MarketplaceTenantContext::clearContext();
        $resolver = new MarketplaceStoreAccessResolver();
        $this->expectException(AuthorizationException::class);
        $resolver->resolveForView($this->operatorUser, $this->ownerStore->id);
    }

    public function test_resolver_operator_with_valid_tenant_context_can_resolve(): void
    {
        MarketplaceTenantContext::setContext($this->ownerStore->id, $this->ownerUser->id, 'credential maintenance');
        $resolver = new MarketplaceStoreAccessResolver();
        $resolved = $resolver->resolveForView($this->operatorUser, $this->ownerStore->id);
        $this->assertEquals($this->ownerStore->id, $resolved->id);
    }

    public function test_resolver_manipulated_selected_store_id_is_rejected(): void
    {
        MarketplaceTenantContext::setContext($this->ownerStore->id, $this->ownerUser->id, 'other reason');
        $resolver = new MarketplaceStoreAccessResolver();
        $this->expectException(AuthorizationException::class);
        $resolver->resolveForCredentialManagement($this->operatorUser, $this->ownerStore->id);
    }

    /**
     * Credential save tests
     */
    public function test_credential_save_owner_updates_own_credential(): void
    {
        $resolver = new MarketplaceStoreAccessResolver();
        $store = $resolver->resolveForCredentialManagement($this->ownerUser, $this->ownerStore->id);

        $this->actingAs($this->ownerUser);

        Livewire::test(\App\Livewire\MarketplaceIntegrations::class, ['store' => $store->id])
            ->set('connectionForm.authType', 'api_key')
            ->set('connectionForm.apiKey', 'new-owner-key')
            ->set('connectionForm.apiSecret', 'new-owner-secret')
            ->call('saveConnection')
            ->assertSet('saveResult', 'credential_saved');

        $this->ownerStore->refresh();
        $this->assertEquals('new-owner-key', $this->ownerStore->connection->credentials_encrypted['api_key']);
    }

    public function test_credential_save_operator_updates_with_valid_tenant_context(): void
    {
        MarketplaceTenantContext::setContext($this->ownerStore->id, $this->ownerUser->id, 'credential maintenance');
        $resolver = new MarketplaceStoreAccessResolver();
        $store = $resolver->resolveForCredentialManagement($this->operatorUser, $this->ownerStore->id);

        $this->actingAs($this->operatorUser);

        Livewire::test(\App\Livewire\MarketplaceIntegrations::class, ['store' => $store->id])
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

        Livewire::test(\App\Livewire\MarketplaceIntegrations::class, ['store' => $this->ownerStore->id])
            ->set('connectionForm.authType', 'api_key')
            ->set('connectionForm.apiKey', 'unauthorized-key')
            ->set('connectionForm.apiSecret', 'unauthorized-secret')
            ->call('saveConnection')
            ->assertSet('saveResult', 'authorization_denied');
    }

    public function test_credential_save_secret_preserved_when_empty(): void
    {
        $this->actingAs($this->ownerUser);

        Livewire::test(\App\Livewire\MarketplaceIntegrations::class, ['store' => $this->ownerStore->id])
            ->set('connectionForm.authType', 'api_key')
            ->set('connectionForm.apiKey', 'new-owner-key')
            ->set('connectionForm.apiSecret', '')
            ->call('saveConnection')
            ->assertSet('saveResult', 'credential_saved');

        $this->ownerStore->refresh();
        $this->assertEquals('owner-secret', $this->ownerStore->connection->credentials_encrypted['api_secret']);
    }

    public function test_credential_save_secret_preserved_when_asterisks(): void
    {
        $this->actingAs($this->ownerUser);

        Livewire::test(\App\Livewire\MarketplaceIntegrations::class, ['store' => $this->ownerStore->id])
            ->set('connectionForm.authType', 'api_key')
            ->set('connectionForm.apiKey', 'new-owner-key')
            ->set('connectionForm.apiSecret', '********')
            ->call('saveConnection')
            ->assertSet('saveResult', 'credential_saved');

        $this->ownerStore->refresh();
        $this->assertEquals('owner-secret', $this->ownerStore->connection->credentials_encrypted['api_secret']);
    }

    public function test_livewire_select_store_idor(): void
    {
        $this->actingAs($this->ownerUser);

        Livewire::test(\App\Livewire\MarketplaceIntegrations::class, ['store' => $this->ownerStore->id])
            ->call('selectStore', $this->otherStore->id)
            ->assertSet('saveResult', 'authorization_denied');
    }

    public function test_livewire_save_connection_idor(): void
    {
        $this->actingAs($this->ownerUser);

        $component = Livewire::test(\App\Livewire\MarketplaceIntegrations::class, ['store' => $this->ownerStore->id]);
        
        // Manipulate selectedStoreId directly in component
        $component->set('selectedStoreId', $this->otherStore->id)
            ->call('saveConnection')
            ->assertSet('saveResult', 'authorization_denied');
    }

    public function test_livewire_validation_fails_for_placeholders_in_production(): void
    {
        $this->actingAs($this->ownerUser);

        // Fake production environment
        $this->app->detectEnvironment(fn() => 'production');

        Livewire::test(\App\Livewire\MarketplaceIntegrations::class, ['store' => $this->ownerStore->id])
            ->set('connectionForm.authType', 'api_key')
            ->set('connectionForm.apiKey', 'test-key')
            ->set('connectionForm.apiSecret', 'valid-secret')
            ->call('saveConnection')
            ->assertHasErrors('connectionForm.apiKey')
            ->assertSet('saveResult', 'validation_failed');
    }
}
