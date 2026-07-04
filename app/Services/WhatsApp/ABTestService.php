<?php

namespace App\Services\WhatsApp;

use App\Models\WaAbTest;
use App\Models\WaAbTestResult;
use App\Models\WaContact;
use App\Models\WaSegment;

class ABTestService
{
    /**
     * A/B test oluştur
     */
    public function createTest(array $data): WaAbTest
    {
        return WaAbTest::create([
            'store_id' => $data['store_id'],
            'segment_id' => $data['segment_id'] ?? null,
            'name' => $data['name'],
            'variants_json' => $data['variants'],
            'traffic_split' => $data['traffic_split'] ?? 50,
            'primary_metric' => $data['primary_metric'] ?? 'conversion_rate',
            'created_by' => $data['created_by'] ?? null,
        ]);
    }

    /**
     * Testi başlat
     */
    public function startTest(WaAbTest $test): void
    {
        if ($test->status !== 'draft') {
            throw new \RuntimeException('Sadece draft testler başlatılabilir.');
        }

        // Varyans sonuçlarını başlat
        $variants = $test->variants_json ?? [];
        foreach ($variants as $variant) {
            WaAbTestResult::create([
                'ab_test_id' => $test->id,
                'variant_name' => $variant['name'],
            ]);
        }

        $test->update([
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    /**
     * İletişim için varyans seç (random split)
     */
    public function selectVariant(WaAbTest $test, WaContact $contact): ?array
    {
        if ($test->status !== 'running') {
            return null;
        }

        $variants = $test->variants_json ?? [];
        if (empty($variants)) {
            return null;
        }

        // Deterministik split: contact_id hash'inden
        $hash = crc32($contact->id . $test->id);
        $index = $hash % count($variants);

        return $variants[$index] ?? null;
    }

    /**
     * Sonuç güncelle
     */
    public function recordResult(WaAbTest $test, string $variantName, string $metric, float $value): void
    {
        $result = WaAbTestResult::where('ab_test_id', $test->id)
            ->where('variant_name', $variantName)
            ->first();

        if (!$result) {
            return;
        }

        $result->increment('sample_size');

        if ($metric === 'conversion') {
            $result->increment('conversions');
        } elseif ($metric === 'click') {
            $result->increment('clicks');
        }

        $result->update([
            'conversion_rate' => $result->sample_size > 0
                ? round($result->conversions / $result->sample_size * 100, 4)
                : 0,
        ]);
    }

    /**
     * Testi tamamla ve kazananı belirle
     */
    public function completeTest(WaAbTest $test): void
    {
        $test->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // En yüksek dönüşüm oranına sahip varyansı kazanan olarak işaretle
        $winner = $test->results()
            ->orderByDesc('conversion_rate')
            ->first();

        if ($winner && $winner->conversion_rate > 0) {
            $winner->update(['is_winner' => true]);
        }
    }

    /**
     * Sonuç raporu
     */
    public function getReport(WaAbTest $test): array
    {
        $results = $test->results()->get();

        return [
            'test' => [
                'name' => $test->name,
                'status' => $test->status,
                'started_at' => $test->started_at?->toDateTimeString(),
                'completed_at' => $test->completed_at?->toDateTimeString(),
            ],
            'variants' => $results->map(fn ($r) => [
                'name' => $r->variant_name,
                'sample_size' => $r->sample_size,
                'conversions' => $r->conversions,
                'conversion_rate' => $r->conversion_rate,
                'clicks' => $r->clicks,
                'is_winner' => $r->is_winner,
            ])->toArray(),
        ];
    }
}
