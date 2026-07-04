<?php

namespace Tests\Feature\WhatsApp;

use App\Models\WaContactPreference;
use App\Models\WaConsentEvent;

class ConsentWithdrawalPreservesHistoryTest extends WhatsAppTestCase
{
    public function test_withdrawal_preserves_history_and_updates_preference(): void
    {
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905327776655');

        // İlk consent
        $this->giveConsent($contact, $store, 'order_updates', 'granted');

        $this->assertEquals(1, WaConsentEvent::where('contact_id', $contact->id)->count());
        $this->assertEquals('granted', WaContactPreference::where('contact_id', $contact->id)->where('purpose', 'order_updates')->first()->status);

        // Withdrawal
        WaContactPreference::where('contact_id', $contact->id)
            ->where('purpose', 'order_updates')
            ->update(['status' => 'withdrawn']);

        WaConsentEvent::create([
            'contact_id' => $contact->id,
            'store_id' => $store->id,
            'purpose' => 'order_updates',
            'action' => 'withdrawn',
            'source' => 'account_settings',
            'consent_timestamp' => now(),
        ]);

        // Preference güncellendi
        $this->assertEquals('withdrawn', WaContactPreference::where('contact_id', $contact->id)->where('purpose', 'order_updates')->first()->status);

        // History korundu (2 kayıt: granted + withdrawn)
        $this->assertEquals(2, WaConsentEvent::where('contact_id', $contact->id)->count());
    }
}
