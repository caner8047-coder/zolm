<?php

namespace App\Services\Marketplace;

use App\Services\MpSettingsService;

class MarketplaceDeliveryTermClassifier
{
    public function __construct(
        protected MpSettingsService $settings,
    ) {}

    /**
     * @return array{key: string, label: string, tone: string, days: int}
     */
    public function classify(int $days): array
    {
        $days = max(0, $days);
        $thresholds = $this->settings->getDeliveryTermThresholds();

        return match (true) {
            $days <= $thresholds['fast_max_days'] => [
                'key' => 'fast',
                'label' => 'Hızlı teslimat',
                'tone' => 'emerald',
                'days' => $days,
            ],
            $days <= $thresholds['standard_max_days'] => [
                'key' => 'standard',
                'label' => 'Standart',
                'tone' => 'amber',
                'days' => $days,
            ],
            $days <= $thresholds['slow_max_days'] => [
                'key' => 'slow',
                'label' => 'Yavaş gönderim',
                'tone' => 'orange',
                'days' => $days,
            ],
            default => [
                'key' => 'very_slow',
                'label' => 'Çok yavaş',
                'tone' => 'red',
                'days' => $days,
            ],
        };
    }
}
