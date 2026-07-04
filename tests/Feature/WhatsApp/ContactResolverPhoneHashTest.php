<?php

namespace Tests\Feature\WhatsApp;

use App\Models\WaContact;
use App\Services\WhatsApp\ContactResolver;

class ContactResolverPhoneHashTest extends WhatsAppTestCase
{
    public function test_same_store_and_phone_does_not_create_duplicate(): void
    {
        $store = $this->createStore();
        $resolver = new ContactResolver();

        $contact1 = $resolver->resolveOrCreate($store->id, '+905321112233', 'wc-001', 'Test');
        $contact2 = $resolver->resolveOrCreate($store->id, '+905321112233', 'wc-001', 'Test');

        $this->assertNotNull($contact1);
        $this->assertNotNull($contact2);
        $this->assertEquals($contact1->id, $contact2->id);
        $this->assertEquals(1, WaContact::where('store_id', $store->id)->count());
    }

    public function test_different_store_allows_same_phone(): void
    {
        $store1 = $this->createStore('woocommerce');
        $store2 = $this->createStore('woocommerce');
        $resolver = new ContactResolver();

        $contact1 = $resolver->resolveOrCreate($store1->id, '+905321112233');
        $contact2 = $resolver->resolveOrCreate($store2->id, '+905321112233');

        $this->assertNotEquals($contact1->id, $contact2->id);
    }

    public function test_invalid_phone_returns_null(): void
    {
        $store = $this->createStore();
        $resolver = new ContactResolver();

        $contact = $resolver->resolveOrCreate($store->id, 'invalid-phone');
        $this->assertNull($contact);
    }

    public function test_hmac_hash_uses_app_key_as_pepper(): void
    {
        $hash1 = WaContact::hashPhone('+905321112233');
        $hash2 = hash_hmac('sha256', '+905321112233', config('app.key', ''));

        $this->assertEquals($hash2, $hash1);
    }
}
