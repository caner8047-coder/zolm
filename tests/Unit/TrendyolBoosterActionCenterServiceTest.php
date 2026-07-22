<?php

namespace Tests\Unit;

use App\Services\Marketplace\TrendyolBoosterActionCenterService;
use PHPUnit\Framework\TestCase;

class TrendyolBoosterActionCenterServiceTest extends TestCase
{
    public function test_it_merges_operational_and_product_actions_by_priority(): void
    {
        $service = new TrendyolBoosterActionCenterService();
        $operational = ['issues' => [[
            'key' => 'scheduler_stale',
            'severity' => 'critical',
            'label' => 'Scheduler gecikti',
            'detail' => 'Son tarama gecikti.',
            'action' => 'Worker kontrol edilmeli.',
            'metric' => '90 dk',
        ]]];
        $priority = ['actions' => [[
            'key' => 'loss',
            'product_id' => 42,
            'severity' => 'critical',
            'tone' => 'rose',
            'priority' => 550,
            'label' => 'Zarar riskini incele',
            'title' => 'Örnek ürün',
            'reason' => 'Zarar sinyali.',
            'metric' => '-25 TL',
        ]]];

        $initial = $service->dashboard(999999, $operational, $priority);
        $this->assertSame(2, $initial['open_count']);
        $this->assertSame('operational:scheduler_stale', $initial['items'][0]['fingerprint']);
        $this->assertSame('product:42:loss', $initial['items'][1]['fingerprint']);
        $this->assertSame(2, $initial['critical_count']);
    }
}
