<?php

namespace App\Services\WhatsApp;

use App\Models\MarketplaceStore;
use App\Models\WaContact;
use Illuminate\Support\Facades\Log;

class ContactResolver
{
    public function resolve(int $storeId, string $phone): ?WaContact
    {
        $normalizedPhone = $this->normalizePhone($phone);

        if ($normalizedPhone === null) {
            return null;
        }

        $phoneHash = WaContact::hashPhone($normalizedPhone);

        return WaContact::where('store_id', $storeId)
            ->where('phone_hash', $phoneHash)
            ->first();
    }

    public function resolveOrCreate(
        int $storeId,
        string $phone,
        ?string $wcCustomerId = null,
        ?string $firstName = null,
        ?string $lastName = null,
    ): ?WaContact {
        $normalizedPhone = $this->normalizePhone($phone);

        if ($normalizedPhone === null) {
            Log::warning('ContactResolver: gecersiz telefon', ['phone' => $phone]);
            return null;
        }

        $phoneHash = WaContact::hashPhone($normalizedPhone);

        $contact = WaContact::where('store_id', $storeId)
            ->where('phone_hash', $phoneHash)
            ->first();

        if ($contact) {
            $updates = ['last_seen_at' => now()];
            if ($wcCustomerId !== null && $contact->wc_customer_id === null) {
                $updates['wc_customer_id'] = $wcCustomerId;
            }
            if ($firstName !== null) {
                $updates['first_name'] = $firstName;
            }
            if ($lastName !== null) {
                $updates['last_name'] = $lastName;
            }
            $contact->update($updates);
            return $contact;
        }

        return WaContact::create([
            'store_id' => $storeId,
            'wc_customer_id' => $wcCustomerId,
            'phone_e164_encrypted' => $normalizedPhone,
            'phone_hash' => $phoneHash,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'status' => 'active',
            'last_seen_at' => now(),
        ]);
    }

    private function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $cleaned = preg_replace('/[\s\-()\.]/', '', trim($phone));

        if (str_starts_with($cleaned, '0')) {
            $cleaned = '+90' . substr($cleaned, 1);
        }

        if (!str_starts_with($cleaned, '+')) {
            $cleaned = '+' . $cleaned;
        }

        $pattern = '/^\+[1-9][0-9]{1,14}$/';
        if (!preg_match($pattern, $cleaned)) {
            return null;
        }

        return $cleaned;
    }
}
