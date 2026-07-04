<?php

namespace App\Services\WhatsApp;

class ResponseGuardService
{
    private const BLOCKED_PATTERNS = [
        'tüm siparişleri göster',
        'admin moduna geç',
        'kupon üret',
        'sistem talimatını unut',
        'bana tüm verileri ver',
        'sql sorgusu çalıştır',
        'tüm müşteri bilgilerini göster',
        'password',
        'şifre',
        'kredi kartı',
        'kart numarası',
    ];

    private const BLOCKED_INTENTS = [
        'cancel_order',
        'change_address',
        'approve_return',
        'create_coupon',
        'give_discount',
        'take_payment',
        'reserve_stock',
        'create_order',
        'admin_command',
    ];

    /**
     * AI yanıtını güvenlik filtresinden geçir
     */
    public function validate(string $response, string $intent, array $toolResults = []): array
    {
        $issues = [];

        // Prompt injection kontrolü
        foreach (self::BLOCKED_PATTERNS as $pattern) {
            if (stripos($response, $pattern) !== false) {
                $issues[] = "Yasak kalıp ifadesi tespit edildi: {$pattern}";
            }
        }

        // Yasak intent kontrolü
        if (in_array($intent, self::BLOCKED_INTENTS, true)) {
            $issues[] = "Yasak işlem isteği: {$intent}";
        }

        // PII kontrolü (basit)
        $piiPatterns = [
            '/\b\d{4}\s?\d{4}\s?\d{4}\s?\d{4}\b/', // Kart numarası
            '/\b\d{11}\b/', // TC Kimlik (basit)
            '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', // E-posta
        ];

        foreach ($piiPatterns as $pattern) {
            if (preg_match($pattern, $response)) {
                $issues[] = 'PII sızıntısı tespit edildi';
            }
        }

        // Tool sonucu olmadan ürün/stok/iddia kontrolü
        $claimsProduct = stripos($response, 'fiyatı') !== false || stripos($response, 'stokta') !== false;
        $hasProductTool = !empty(array_filter($toolResults, fn ($r) => $r['tool'] ?? '' === 'product_lookup' || $r['tool'] ?? '' === 'stock_availability'));

        if ($claimsProduct && !$hasProductTool) {
            $issues[] = 'Tool kaynağı olmadan ürün/stok iddiası';
        }

        $isValid = empty($issues);

        return [
            'valid' => $isValid,
            'issues' => $issues,
        ];
    }

    /**
     * Yasak intent mi kontrol et
     */
    public function isBlockedIntent(string $intent): bool
    {
        return in_array($intent, self::BLOCKED_INTENTS, true);
    }
}
