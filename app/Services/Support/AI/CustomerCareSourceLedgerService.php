<?php

namespace App\Services\Support\AI;

use Carbon\Carbon;

class CustomerCareSourceLedgerService
{
    private const MAX_AGE_MINUTES = [
        'order' => 60,
        'shipment' => 60,
        'product_catalog' => 1440,
        'product_catalog_fallback' => 1440,
        'campaign' => 1440,
        'knowledge_base' => 43200,
    ];

    public function validate(array $sources): array
    {
        if ($sources === []) {
            return ['valid' => false, 'reason' => 'Kaynak defteri boş.'];
        }
        foreach ($sources as $source) {
            if (!is_array($source) || empty($source['type']) || empty($source['name'])) {
                return ['valid' => false, 'reason' => 'Kaynak kaydı eksik alan içeriyor.'];
            }
            if (($source['is_stale'] ?? false) === true) {
                return ['valid' => false, 'reason' => "Kaynak güncelliğini kaybetmiş: {$source['name']}"];
            }
            $maxAge = self::MAX_AGE_MINUTES[$source['type']] ?? null;
            if ($maxAge !== null) {
                if (empty($source['freshness_at'])) {
                    return ['valid' => false, 'reason' => "Kaynak güncellik zamanı eksik: {$source['name']}"];
                }
                try {
                    if (Carbon::parse($source['freshness_at'])->lt(now()->subMinutes($maxAge))) {
                        return ['valid' => false, 'reason' => "Kaynak süresi dolmuş: {$source['name']}"];
                    }
                } catch (\Throwable) {
                    return ['valid' => false, 'reason' => "Kaynak zamanı geçersiz: {$source['name']}"];
                }
            }
        }

        return ['valid' => true, 'reason' => null];
    }

    public function containsRequiredClaimSource(string $message, array $sources): bool
    {
        $normalized = mb_strtolower($message);
        $requiredTypes = match (true) {
            preg_match('/sipariş|siparis|kargo|takip|teslim/u', $normalized) === 1 => ['order', 'shipment'],
            preg_match('/fiyat|stok|ürün|urun|beden|kampanya/u', $normalized) === 1 => ['product_catalog', 'product_catalog_fallback', 'campaign'],
            default => [],
        };
        if ($requiredTypes === []) {
            return true;
        }
        return collect($sources)->contains(fn ($source) => in_array($source['type'] ?? null, $requiredTypes, true)
            && !empty($source['record_id']));
    }

    public function customerSummary(array $sources): array
    {
        return collect($sources)->map(fn ($source) => [
            'type' => $source['type'] ?? 'unknown',
            'name' => $source['name'] ?? 'Kaynak',
            'updated_at' => $source['freshness_at'] ?? null,
        ])->unique(fn ($source) => $source['type'] . '|' . $source['name'])->values()->all();
    }
}
