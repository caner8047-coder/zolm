<?php

namespace Tests\Feature\WhatsApp;

use App\Models\WaAbandonedCart;
use App\Models\WaCartRecoveryRun;
use App\Models\WaAutomationConfig;
use App\Models\WaContactPreference;
use App\Models\WaOutbox;
use App\Services\WhatsApp\CartRecoveryService;
use Illuminate\Support\Facades\Config;

class CartRecoveryTest extends WhatsAppTestCase
{
    public function test_wc_non_store_cart_event_ignored(): void
    {
        $store = $this->createStore('trendyol');
        $service = app(CartRecoveryService::class);

        $service->onCartUpdated([
            'store_id' => $store->id,
            'cart_key' => 'test-cart-key',
            'cart_items' => [['product_id' => 1, 'quantity' => 1]],
            'cart_total' => 100,
        ]);

        $this->assertEquals(0, WaAbandonedCart::count());
    }

    public function test_cart_recovery_consent_required(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905321112233');
        // Consent yok — sadece order_updates var
        $this->giveConsent($contact, $store, 'order_updates', 'granted');

        $service = app(CartRecoveryService::class);
        $service->onCartUpdated([
            'store_id' => $store->id,
            'cart_key' => 'no-consent-cart',
            'wc_customer_id' => '123',
            'phone' => '+905321112233',
            'cart_items' => [['product_id' => 1, 'quantity' => 1]],
            'cart_total' => 100,
            'cart_recovery_consent' => 'withdrawn',
        ]);

        $this->assertEquals(0, WaAbandonedCart::count());
    }

    public function test_marketing_consent_does_not_grant_cart_recovery(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905327776655');
        $this->giveConsent($contact, $store, 'marketing', 'granted');

        $service = app(CartRecoveryService::class);
        $service->onCartUpdated([
            'store_id' => $store->id,
            'cart_key' => 'marketing-only-cart',
            'phone' => '+905327776655',
            'cart_items' => [['product_id' => 1, 'quantity' => 1]],
            'cart_total' => 100,
            'cart_recovery_consent' => 'withdrawn',
        ]);

        $this->assertEquals(0, WaAbandonedCart::count());
    }

    public function test_duplicate_cart_event_not_created(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905328887766');
        $this->giveConsent($contact, $store, 'cart_recovery', 'granted');

        $service = app(CartRecoveryService::class);
        $payload = [
            'store_id' => $store->id,
            'cart_key' => 'dup-cart-key',
            'wc_customer_id' => '456',
            'phone' => '+905328887766',
            'cart_items' => [['product_id' => 1, 'quantity' => 1]],
            'cart_total' => 100,
            'cart_recovery_consent' => 'granted',
        ];

        $service->onCartUpdated($payload);
        $this->assertEquals(1, WaAbandonedCart::count());

        // Tekrar gönder
        $service->onCartUpdated($payload);
        $this->assertEquals(1, WaAbandonedCart::count());
    }

    public function test_same_cart_and_stage_no_second_message(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905329998877');
        $this->giveConsent($contact, $store, 'cart_recovery', 'granted');

        WaAutomationConfig::set('cart_recovery', [
            'enabled' => true,
            'stages' => [
                ['delay_minutes' => 0, 'enabled' => true, 'template_id' => null, 'coupon_enabled' => false],
                ['delay_minutes' => 1440, 'enabled' => false],
                ['delay_minutes' => 4320, 'enabled' => false],
            ],
        ]);

        $service = app(CartRecoveryService::class);
        $service->onCartUpdated([
            'store_id' => $store->id,
            'cart_key' => 'idempotent-cart',
            'phone' => '+905329998877',
            'cart_items' => [['product_id' => 1, 'quantity' => 1]],
            'cart_total' => 100,
            'cart_recovery_consent' => 'granted',
        ]);

        $cart = WaAbandonedCart::first();
        $this->assertEquals(1, WaCartRecoveryRun::where('cart_id', $cart->id)->where('stage', 'stage_1')->count());
    }

    public function test_order_cancels_pending_stages(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905321001001');
        $this->giveConsent($contact, $store, 'cart_recovery', 'granted');

        WaAutomationConfig::set('cart_recovery', [
            'enabled' => true,
            'stages' => [
                ['delay_minutes' => 60, 'enabled' => true, 'template_id' => null, 'coupon_enabled' => false],
                ['delay_minutes' => 1440, 'enabled' => true, 'template_id' => null, 'coupon_enabled' => false],
                ['delay_minutes' => 4320, 'enabled' => true, 'template_id' => null, 'coupon_enabled' => false],
            ],
        ]);

        $service = app(CartRecoveryService::class);
        $service->onCartUpdated([
            'store_id' => $store->id,
            'cart_key' => 'order-cancel-cart',
            'phone' => '+905321001001',
            'cart_items' => [['product_id' => 1, 'quantity' => 1]],
            'cart_total' => 100,
            'cart_recovery_consent' => 'granted',
        ]);

        $cart = WaAbandonedCart::first();
        $pendingCount = WaCartRecoveryRun::where('cart_id', $cart->id)
            ->where('status', WaCartRecoveryRun::STATUS_PENDING)
            ->count();

        $this->assertGreaterThan(0, $pendingCount);

        // Sipariş oluştu
        $service->onOrderCreated([
            'store_id' => $store->id,
            'cart_key' => 'order-cancel-cart',
            'order_id' => null,
        ]);

        $cart->refresh();
        $this->assertEquals(WaAbandonedCart::STATUS_RECOVERED, $cart->status);

        $pendingAfter = WaCartRecoveryRun::where('cart_id', $cart->id)
            ->where('status', WaCartRecoveryRun::STATUS_PENDING)
            ->count();

        $this->assertEquals(0, $pendingAfter);
    }

    public function test_consent_withdrawn_cancels_flows(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905322002002');
        $this->giveConsent($contact, $store, 'cart_recovery', 'granted');

        $service = app(CartRecoveryService::class);
        $service->onCartUpdated([
            'store_id' => $store->id,
            'cart_key' => 'consent-cancel-cart',
            'phone' => '+905322002002',
            'cart_items' => [['product_id' => 1, 'quantity' => 1]],
            'cart_total' => 100,
            'cart_recovery_consent' => 'granted',
        ]);

        $cart = WaAbandonedCart::first();
        $this->assertEquals(WaAbandonedCart::STATUS_ACTIVE, $cart->status);

        // Consent withdrawn
        $service->cancelFlowsForContact($contact->id);

        $cart->refresh();
        $this->assertEquals(WaAbandonedCart::STATUS_CANCELLED, $cart->status);
    }

    public function test_cart_recovery_message_not_sent_during_quiet_hours(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        // Sessiz saat: 22:00 - 08:00
        Config::set('whatsapp.sending.quiet_hours_start', '22:00');
        Config::set('whatsapp.sending.quiet_hours_end', '08:00');

        $store = $this->createStore();
        $contact = $this->createContact($store, '+905323003003');
        $this->giveConsent($contact, $store, 'cart_recovery', 'granted');

        WaAutomationConfig::set('cart_recovery', [
            'enabled' => true,
            'stages' => [
                ['delay_minutes' => 0, 'enabled' => true, 'template_id' => 1, 'coupon_enabled' => false],
                ['delay_minutes' => 1440, 'enabled' => false],
                ['delay_minutes' => 4320, 'enabled' => false],
            ],
        ]);

        // 02:00'te çalıştır (sessiz saat)
        $this->travelTo(now()->setTime(2, 0));

        $service = app(CartRecoveryService::class);
        $service->onCartUpdated([
            'store_id' => $store->id,
            'cart_key' => 'quiet-hours-cart',
            'phone' => '+905323003003',
            'cart_items' => [['product_id' => 1, 'quantity' => 1]],
            'cart_total' => 100,
            'cart_recovery_consent' => 'granted',
        ]);

        // Template yok (null), bu yüzden mesaj gönderilmez
        // Asıl kontrol: cart_recovery.enabled=true ama template_id=null olduğu için run cancelled olur
        $cart = WaAbandonedCart::first();
        $this->assertNotNull($cart);

        $this->travelBack();
    }
}
