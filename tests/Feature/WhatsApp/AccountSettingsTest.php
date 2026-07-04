<?php

namespace Tests\Feature\WhatsApp;

use App\Models\WaAccount;
use Illuminate\Support\Facades\Config;

class AccountSettingsTest extends WhatsAppTestCase
{
    public function test_display_phone_number_not_filled_from_auth_user(): void
    {
        $store = $this->createStore();
        WaAccount::create([
            'store_id' => $store->id,
            'waba_id' => 'waba-empty',
            'phone_number_id' => 'phone-empty',
            'display_phone_number' => '',
            'access_token_encrypted' => 'tok',
            'status' => 'active',
            'is_active' => true,
        ]);
        $this->actingAs($store->user);

        $component = \Livewire\Livewire::test(\App\Livewire\WhatsApp\WhatsAppAccountSettings::class);
        $component->assertSet('displayPhoneNumber', '');
    }

    public function test_token_input_empty_on_first_load(): void
    {
        $store = $this->createStore();
        $this->createAccount($store);
        $this->actingAs($store->user);

        $component = \Livewire\Livewire::test(\App\Livewire\WhatsApp\WhatsAppAccountSettings::class);
        $component->assertSet('newAccessToken', '');
    }

    public function test_empty_token_does_not_corrupt_existing_token(): void
    {
        $store = $this->createStore();
        $account = $this->createAccount($store);
        $this->actingAs($store->user);

        $originalToken = $account->access_token_encrypted;

        $component = \Livewire\Livewire::test(\App\Livewire\WhatsApp\WhatsAppAccountSettings::class);
        $component->call('saveAccount', [
            'wabaId' => 'new-waba',
            'phoneNumberId' => 'new-phone',
            'storeId' => $store->id,
        ]);

        $account->refresh();
        $this->assertEquals($originalToken, $account->access_token_encrypted);
    }

    public function test_cannot_select_non_woocommerce_store(): void
    {
        $store = $this->createStore('trendyol');
        $this->actingAs($store->user);

        $component = \Livewire\Livewire::test(\App\Livewire\WhatsApp\WhatsAppAccountSettings::class);
        $stores = $component->get('availableStores');
        $this->assertTrue($stores->isEmpty());
    }

    public function test_cannot_save_without_store(): void
    {
        $store = $this->createStore();
        $this->actingAs($store->user);

        $component = \Livewire\Livewire::test(\App\Livewire\WhatsApp\WhatsAppAccountSettings::class);
        $component->call('saveAccount', [
            'wabaId' => 'test',
            'phoneNumberId' => 'test',
            'storeId' => 0,
        ]);

        $component->assertHasErrors(['storeId']);
    }

    public function test_same_store_cannot_have_two_active_accounts(): void
    {
        $store = $this->createStore();
        $existingAccount = $this->createAccount($store);

        // Component'te accountId'i sıfırla — yeni hesap oluşturur gibi davran
        $this->actingAs($store->user);

        $component = \Livewire\Livewire::test(\App\Livewire\WhatsApp\WhatsAppAccountSettings::class);
        // mount accountId'i mevcut hesaptan set eder, manuel olarak sıfırla
        $component->set('accountId', 0);

        $component->call('saveAccount', [
            'wabaId' => 'another-waba',
            'phoneNumberId' => 'another-phone',
            'storeId' => $store->id,
        ]);

        $component->assertHasErrors(['storeId']);
    }

    public function test_token_never_hydrated_to_component(): void
    {
        $store = $this->createStore();
        $account = $this->createAccount($store);
        $this->actingAs($store->user);

        // Hesap mevcut, mount edildiğinde token boş olmalı
        $component = \Livewire\Livewire::test(\App\Livewire\WhatsApp\WhatsAppAccountSettings::class);
        $component->assertSet('newAccessToken', '');

        // DB'de token var ama component'te görünmüyor
        $this->assertNotEmpty($account->fresh()->access_token_encrypted);
        $component->assertSet('newAccessToken', '');
    }

    public function test_token_not_included_in_audit_log(): void
    {
        $store = $this->createStore();
        $this->actingAs($store->user);

        $component = \Livewire\Livewire::test(\App\Livewire\WhatsApp\WhatsAppAccountSettings::class);
        $component->set('newAccessToken', 'super-secret-token')
            ->call('saveAccount', [
                'wabaId' => 'waba-audit',
                'phoneNumberId' => 'phone-audit',
                'storeId' => $store->id,
            ]);

        $log = \App\Models\WaAuditLog::latest()->first();
        $this->assertStringNotContainsString('super-secret-token', json_encode($log->details ?? []));
    }
}
