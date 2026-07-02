<?php

namespace App\Services\Marketplace;

use App\Models\TrendyolBoosterActivityLog;

class TrendyolBoosterActivityLogger
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function log(
        int $userId,
        string $type,
        string $title,
        ?string $subject = null,
        ?string $summary = null,
        ?string $resultLabel = null,
        mixed $resultValue = null,
        array $payload = [],
        ?int $trackedProductId = null,
    ): TrendyolBoosterActivityLog {
        return TrendyolBoosterActivityLog::query()->create([
            'user_id' => $userId,
            'trendyol_booster_product_id' => $trackedProductId,
            'activity_type' => $type,
            'title' => $title,
            'subject' => $subject,
            'summary' => $summary,
            'result_label' => $resultLabel,
            'result_value' => is_numeric($resultValue) ? (float) $resultValue : null,
            'payload' => $payload,
            'recorded_at' => now(),
        ]);
    }
}
