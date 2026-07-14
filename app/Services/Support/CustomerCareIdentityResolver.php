<?php

namespace App\Services\Support;

use App\Models\SupportConversation;
use App\Models\WaConversation;

/**
 * Dalga T — Kanal kimliklerini güvenli şekilde çözer.
 *
 * Tasarım kararları:
 * - Farklı kanallardaki kimlikler ASLA otomatik merge edilmez.
 * - Merge/association yalnız explicit deterministic key ile yapılabilir.
 * - Telefon/e-posta ham veri ile değil hash/normalized token ile eşleştirilir.
 * - Her resolver işlemi store-scoped olarak çalışır.
 */
class CustomerCareIdentityResolver
{
    /**
     * Konuşma için store-scoped müşteri kimliğini çözer.
     *
     * @return array{
     *   channel: string,
     *   external_customer_id: string|null,
     *   identity_key: string|null,
     *   identity_type: string,
     *   store_id: int,
     *   can_merge: bool
     * }
     */
    public function resolveForConversation(SupportConversation $conversation): array
    {
        $storeId = (int)$conversation->store_id;

        $base = [
            'channel' => $conversation->source_type,
            'store_id' => $storeId,
            'can_merge' => false, // Otomatik merge her zaman kapalı
        ];

        return match ($conversation->source_type) {
            'whatsapp' => $this->resolveWhatsApp($conversation, $base),
            'trendyol', 'n11', 'hepsiburada' => $this->resolveMarketplace($conversation, $base),
            default => $this->resolveUnknown($conversation, $base),
        };
    }

    /**
     * İki kanal kimliğini güvenli eşleştirme.
     * YALNIZCA deterministic external_customer_id eşleşmesi kabul edilir.
     * Otomatik telefon/e-posta eşleşmesi YASAK.
     */
    public function canAssociate(array $identity1, array $identity2): bool
    {
        // Farklı store'lar — kesinlikle merge edilemez
        if ($identity1['store_id'] !== $identity2['store_id']) {
            return false;
        }

        // Deterministic key eşleşmesi zorunlu
        if (
            !empty($identity1['identity_key']) &&
            !empty($identity2['identity_key']) &&
            $identity1['identity_key'] === $identity2['identity_key'] &&
            $identity1['identity_type'] === $identity2['identity_type']
        ) {
            return true;
        }

        // WhatsApp contact ↔ marketplace customer: OTOMATİK MERGE YASAK
        if ($identity1['channel'] !== $identity2['channel']) {
            return false;
        }

        return false;
    }

    private function resolveWhatsApp(SupportConversation $conversation, array $base): array
    {
        $ref = $conversation->source_reference_json ?? [];
        $waConvId = $ref['wa_conversation_id'] ?? null;

        if (!$waConvId) {
            return array_merge($base, [
                'external_customer_id' => null,
                'identity_key' => null,
                'identity_type' => 'unknown',
            ]);
        }

        $waConv = WaConversation::whereKey($waConvId)
            ->where('store_id', $conversation->store_id) // Store-bound!
            ->with('contact')
            ->first();

        if (!$waConv || !$waConv->contact) {
            return array_merge($base, [
                'external_customer_id' => null,
                'identity_key' => null,
                'identity_type' => 'unknown',
            ]);
        }

        $contact = $waConv->contact;

        // contact store_id uyumunu doğrula
        if ((int)$contact->store_id !== (int)$conversation->store_id) {
            return array_merge($base, [
                'external_customer_id' => null,
                'identity_key' => null,
                'identity_type' => 'cross_store_rejected',
            ]);
        }

        return array_merge($base, [
            'external_customer_id' => 'wa_contact_' . $contact->id,
            // Telefon hash kullan, ham veri değil
            'identity_key' => $contact->phone_hash,
            'identity_type' => 'phone_hash',
        ]);
    }

    private function resolveMarketplace(SupportConversation $conversation, array $base): array
    {
        $externalCustomerId = $conversation->external_customer_id;

        if (!$externalCustomerId) {
            return array_merge($base, [
                'external_customer_id' => null,
                'identity_key' => null,
                'identity_type' => 'unknown',
            ]);
        }

        return array_merge($base, [
            'external_customer_id' => $externalCustomerId,
            'identity_key' => $externalCustomerId, // Marketplace ID deterministik
            'identity_type' => 'marketplace_customer_id',
        ]);
    }

    private function resolveUnknown(SupportConversation $conversation, array $base): array
    {
        return array_merge($base, [
            'external_customer_id' => null,
            'identity_key' => null,
            'identity_type' => 'unknown',
        ]);
    }
}
