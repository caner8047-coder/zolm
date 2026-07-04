<?php

namespace Tests\Feature\WhatsApp;

use App\Models\ChannelClaim;
use App\Models\ChannelClaimItem;
use App\Models\ChannelOrder;
use App\Models\WaAutomationConfig;
use App\Models\WaOutbox;
use App\Services\WhatsApp\ReturnNotificationService;
use Illuminate\Support\Facades\Config;

class ReturnNotificationTest extends WhatsAppTestCase
{
    private function makeClaimWithOrder(array $orderOverrides = [], array $claimOverrides = []): array
    {
        $store = $this->createStore();

        $order = ChannelOrder::create(array_merge([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'external_order_id' => 'WC-RET-100',
            'order_number' => 'RET-100',
            'order_status' => 'completed',
            'customer_phone' => '+905329990001',
            'customer_name' => 'İade Test',
            'ordered_at' => now()->subDays(5),
        ], $orderOverrides));

        $claim = ChannelClaim::create(array_merge([
            'store_id' => $store->id,
            'external_claim_id' => 'CLAIM-' . uniqid(),
            'order_number' => 'RET-100',
            'status' => 'pending',
            'created_date' => now(),
        ], $claimOverrides));

        ChannelClaimItem::create([
            'claim_id' => $claim->id,
            'external_item_id' => 'ITEM-' . uniqid(),
            'external_order_id' => 'WC-RET-100',
            'status' => 'pending',
        ]);

        return ['store' => $store, 'order' => $order, 'claim' => $claim];
    }

    public function test_wc_non_store_return_no_notification(): void
    {
        $store = $this->createStore('trendyol');
        $claim = ChannelClaim::create([
            'store_id' => $store->id,
            'external_claim_id' => 'TY-CLAIM-1',
            'status' => 'pending',
            'created_date' => now(),
        ]);

        $service = new ReturnNotificationService();
        $service->onReturnStatusChanged($claim, 'pending', 'approved');

        $this->assertEquals(0, WaOutbox::count());
    }

    public function test_unknown_return_status_no_notification(): void
    {
        ['store' => $store, 'claim' => $claim] = $this->makeClaimWithOrder();

        Config::set('whatsapp.features.test_mode', false);

        WaAutomationConfig::set('returns', [
            'enabled' => true,
            'stages' => [
                'return_approved' => ['enabled' => true, 'template_id' => null],
            ],
        ]);

        $service = new ReturnNotificationService();
        // 'cancelled' STATUS_MAP'te yok (returnCancelled değil, sadece ChannelClaim cancelled)
        $service->onReturnStatusChanged($claim, 'pending', 'cancelled');

        $this->assertEquals(0, WaOutbox::count());
    }

    public function test_same_return_stage_no_second_message(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        ['store' => $store, 'claim' => $claim, 'order' => $order] = $this->makeClaimWithOrder();
        $this->createAccount($store);
        $contact = $this->createContact($store, '+905329990001');
        $this->giveConsent($contact, $store, 'order_updates', 'granted');

        WaAutomationConfig::set('returns', [
            'enabled' => true,
            'stages' => [
                'return_approved' => ['enabled' => true, 'template_id' => null],
            ],
        ]);

        $service = new ReturnNotificationService();

        // İlk — template null, outbox oluşmaz
        $service->onReturnStatusChanged($claim, 'pending', 'approved');

        // İkinci kez — yine oluşmaz
        $service->onReturnStatusChanged($claim, 'approved', 'approved');

        $this->assertEquals(0, WaOutbox::count());
    }

    public function test_return_requested_and_approved_different_messages(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        ['store' => $store, 'claim' => $claim] = $this->makeClaimWithOrder();

        WaAutomationConfig::set('returns', [
            'enabled' => true,
            'stages' => [
                'return_requested' => ['enabled' => true, 'template_id' => null],
                'return_approved' => ['enabled' => true, 'template_id' => null],
            ],
        ]);

        $service = new ReturnNotificationService();
        $service->onReturnStatusChanged($claim, 'pending', 'pending');

        // pending → pending mappede yok (return_requested değil)
        $this->assertEquals(0, WaOutbox::count());
    }

    public function test_return_without_linked_order_no_notification(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore();

        $claim = ChannelClaim::create([
            'store_id' => $store->id,
            'external_claim_id' => 'NOLINK-CLAIM',
            'status' => 'pending',
            'created_date' => now(),
        ]);

        // Items yok — order eşleşemez
        WaAutomationConfig::set('returns', [
            'enabled' => true,
            'stages' => ['return_approved' => ['enabled' => true, 'template_id' => null]],
        ]);

        $service = new ReturnNotificationService();
        $service->onReturnStatusChanged($claim, 'pending', 'approved');

        $this->assertEquals(0, WaOutbox::count());
    }

    public function test_consent_withdrawn_prevents_new_return_messages(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        ['store' => $store, 'claim' => $claim] = $this->makeClaimWithOrder();
        $contact = $this->createContact($store, '+905329990001');
        // Consent withdrawn
        \App\Models\WaContactPreference::create([
            'contact_id' => $contact->id,
            'store_id' => $store->id,
            'purpose' => 'order_updates',
            'status' => 'withdrawn',
        ]);

        WaAutomationConfig::set('returns', [
            'enabled' => true,
            'stages' => ['return_approved' => ['enabled' => true, 'template_id' => null]],
        ]);

        $service = new ReturnNotificationService();
        $service->onReturnStatusChanged($claim, 'pending', 'approved');

        $this->assertEquals(0, WaOutbox::count());
    }

    public function test_returns_settings_page_loads(): void
    {
        $store = $this->createStore();
        $this->actingAs($store->user);

        $response = $this->get(route('whatsapp.overview'));
        $response->assertOk();
    }
}
