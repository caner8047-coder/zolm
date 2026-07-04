<?php

namespace App\Exceptions;

use RuntimeException;

class WhatsAppException extends RuntimeException
{
    public static function metaApiError(string $message): static
    {
        return new static("Meta API hatası: {$message}");
    }

    public static function webhookVerificationFailed(): static
    {
        return new static('Webhook imza doğrulaması başarısız');
    }

    public static function accountNotFound(?int $storeId = null): static
    {
        $context = $storeId ? " (store_id: {$storeId})" : '';
        return new static("WhatsApp hesabı bulunamadı{$context}");
    }
}
