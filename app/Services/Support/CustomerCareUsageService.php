<?php

namespace App\Services\Support;

use App\Models\SupportUsage;
use App\Models\SupportUsageEvent;
use App\Models\SupportChannel;
use Illuminate\Support\Facades\Config;

class CustomerCareUsageService
{
    protected const ALLOWED_METRICS = [
        'ai_drafts',
        'auto_replies',
        'agent_replies',
        'knowledge_suggestions',
        'connected_channels',
    ];

    /**
     * Metriğin izin verilen listede olup olmadığını doğrular.
     */
    protected function validateMetric(string $metric): void
    {
        if (!in_array($metric, self::ALLOWED_METRICS, true)) {
            throw new \InvalidArgumentException("Desteklenmeyen veya geçersiz metrik: '{$metric}'");
        }
    }

    /**
     * Kullanım miktarını artırır ve denetim logunu (event) yazar.
     */
    public function incrementUsage(int $storeId, string $metric, int $amount = 1): void
    {
        $this->validateMetric($metric);

        $monthKey = $this->resolvePeriodKey($metric);

        $usage = SupportUsage::firstOrCreate(
            [
                'store_id' => $storeId,
                'metric' => $metric,
                'month' => $monthKey,
            ],
            ['count' => 0]
        );

        $usage->increment('count', $amount);

        // Append-only event ledger kaydı
        SupportUsageEvent::create([
            'store_id' => $storeId,
            'metric' => $metric,
            'details_json' => [
                'amount' => $amount,
                'period_key' => $monthKey,
                'current_total' => (int)$usage->fresh()->count,
            ],
        ]);
    }

    /**
     * Limit ve kota durumunu kontrol eder.
     */
    public function checkLimit(int $storeId, string $metric): array
    {
        $this->validateMetric($metric);

        $limit = $this->resolveLimitValue($storeId, $metric);
        $current = $this->resolveCurrentValue($storeId, $metric);

        $allowed = $current < $limit;
        $reason = null;

        if (!$allowed) {
            $reason = "Kota Sınırı Aşıldı: '{$metric}' için belirlenen limit (" . ($limit === PHP_INT_MAX ? 'Sınırsız' : $limit) . ") dolmuştur (Mevcut: {$current}).";
        }

        return [
            'allowed' => $allowed,
            'reason' => $reason,
            'current' => $current,
            'limit' => $limit,
        ];
    }

    /**
     * İlgili metriğin dönem anahtarını (Aylık/Günlük) çözer.
     */
    protected function resolvePeriodKey(string $metric): string
    {
        if ($metric === 'knowledge_suggestions') {
            return now()->format('Y-m-d'); // Günlük limit
        }
        return now()->format('Y-m'); // Aylık limit
    }

    /**
     * Konfigürasyondaki limit değerini döner.
     */
    protected function resolveLimitValue(int $storeId, string $metric): int
    {
        return match ($metric) {
            'ai_drafts' => (int) Config::get('customer-care.plans.monthly_ai_drafts', 500),
            'auto_replies' => (int) Config::get('customer-care.plans.monthly_auto_replies', 200),
            'connected_channels' => (int) Config::get('customer-care.plans.connected_channels', 5),
            'knowledge_suggestions' => (int) Config::get('customer-care.plans.knowledge_suggestions_per_day', 20),
            'agent_replies' => PHP_INT_MAX, // Explicit unlimited limit
        };
    }

    /**
     * İlgili metriğin mevcut kullanım değerini döner.
     */
    protected function resolveCurrentValue(int $storeId, string $metric): int
    {
        if ($metric === 'connected_channels') {
            // Veritabanındaki aktif kanal sayısını ölçer
            return SupportChannel::where('store_id', $storeId)
                ->where('is_enabled', true)
                ->count();
        }

        $monthKey = $this->resolvePeriodKey($metric);

        return (int) SupportUsage::where('store_id', $storeId)
            ->where('metric', $metric)
            ->where('month', $monthKey)
            ->value('count');
    }
}
