<?php

namespace Tests\Feature\WhatsApp;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

abstract class WhatsAppTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Config cache'i temizle ki test config'leri uygulansın
        \Illuminate\Support\Facades\Artisan::call('config:clear');

        Config::set('whatsapp.features.whatsapp_enabled', true);
        Config::set('whatsapp.features.test_mode', true);
        Config::set('whatsapp.features.test_phone_numbers', ['+905321112233']);
        Config::set('whatsapp.webhook.app_secret', 'test-app-secret-key');
        Config::set('whatsapp.webhook.verify_token', 'test-verify-token');
        Config::set('whatsapp.meta.graph_version', 'v25.0');
        Config::set('whatsapp.sending.quiet_hours_start', '22:00');
        Config::set('whatsapp.sending.quiet_hours_end', '08:00');
    }

    protected function createStore(string $marketplace = 'woocommerce'): \App\Models\MarketplaceStore
    {
        $user = \App\Models\User::create([
            'name' => 'Test User',
            'email' => 'test-' . Str::random(8) . '@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $legalEntity = \App\Models\LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Test Hukuki Varlık',
            'tax_number' => '1234567890',
            'is_active' => true,
        ]);

        return \App\Models\MarketplaceStore::create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => $marketplace,
            'store_name' => 'Test Mağaza',
            'store_code' => 'test-store-' . Str::random(4),
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    protected function createAccount(\App\Models\MarketplaceStore $store): \App\Models\WaAccount
    {
        return \App\Models\WaAccount::create([
            'store_id' => $store->id,
            'waba_id' => 'test-waba-id',
            'phone_number_id' => 'test-phone-number-id',
            'display_phone_number' => '+905321112233',
            'access_token_encrypted' => 'test-access-token',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    protected function createContact(
        \App\Models\MarketplaceStore $store,
        string $phone = '+905321112233',
        ?string $wcCustomerId = null,
    ): \App\Models\WaContact {
        return \App\Models\WaContact::create([
            'store_id' => $store->id,
            'wc_customer_id' => $wcCustomerId,
            'phone_e164_encrypted' => $phone,
            'phone_hash' => \App\Models\WaContact::hashPhone($phone),
            'first_name' => 'Test',
            'last_name' => 'Müşteri',
            'status' => 'active',
        ]);
    }

    protected function giveConsent(
        \App\Models\WaContact $contact,
        \App\Models\MarketplaceStore $store,
        string $purpose = 'order_updates',
        string $status = 'granted',
    ): void {
        \App\Models\WaContactPreference::create([
            'contact_id' => $contact->id,
            'store_id' => $store->id,
            'purpose' => $purpose,
            'status' => $status,
        ]);

        \App\Models\WaConsentEvent::create([
            'contact_id' => $contact->id,
            'store_id' => $store->id,
            'purpose' => $purpose,
            'action' => $status,
            'source' => 'checkout',
            'consent_timestamp' => now(),
        ]);
    }
}
