<?php

namespace App\Services\Support\Compliance;

use App\Models\SupportConsentRecord;
use Illuminate\Support\Facades\Log;

class CustomerCareConsentService
{
    /**
     * Müşterinin belirli bir kanal ve gönderim amacı için onay durumunu sorgular.
     */
    public function hasConsent(int $storeId, ?string $customerId, string $channelKey, string $consentType): bool
    {
        $enabled = config('customer-care.compliance_enabled', false);
        if (!$enabled) {
            return true;
        }

        if (empty($customerId)) {
            return false;
        }

        // Operasyonel müşteri hizmetleri yanıtları her zaman izinlidir
        if ($consentType === 'operational') {
            return true;
        }

        // Pazarlama veya proaktif mesajlar için izin kontrolü yap
        $consent = SupportConsentRecord::where('store_id', $storeId)
            ->where('customer_hash', hash('sha256', $customerId))
            ->where('channel_key', $channelKey)
            ->where('consent_type', 'marketing')
            ->first();

        if (!$consent || $consent->status !== 'granted') {
            Log::info("Consent Blocked: Pazarlama mesajı izni bulunamadı.", [
                'store_id' => $storeId,
                'customer_id_hash' => hash('sha256', $customerId),
                'channel_key' => $channelKey
            ]);
            return false;
        }

        return true;
    }
}
