<?php

namespace Tests\Unit;

use App\Models\TrendyolBoosterProduct;
use App\Models\TrendyolBoosterSnapshot;
use App\Services\Marketplace\TrendyolBoosterAnalysisService;
use App\Services\Marketplace\TrendyolBoosterIntelligenceService;
use App\Services\Marketplace\TrendyolBoosterProductAnalysisService;
use Mockery;
use Tests\TestCase;

class TrendyolBoosterProductDecisionSummaryTest extends TestCase
{
    public function test_quick_decision_does_not_publish_profit_without_cost_evidence(): void
    {
        $result = $this->service()->present(
            $this->product(['cogs' => 0, 'commission_rate' => 0, 'net_profit' => 599.90, 'profit_margin_percent' => 100]),
            $this->snapshot(),
        );

        $this->assertSame('needs_cost', $result['decision']['status']);
        $this->assertSame('Maliyet gerekli', $result['decision']['label']);
        $this->assertFalse($result['decision']['finance_ready']);
        $this->assertNull($result['decision']['net_profit']);
        $this->assertNull($result['decision']['profit_margin_percent']);
        $this->assertSame('İlgi sinyallerinden tahmin edildi', $result['evidence']['sales_label']);
    }

    public function test_quick_decision_exposes_finance_when_cost_and_commission_are_ready(): void
    {
        $result = $this->service()->present(
            $this->product([
                'cogs' => 300,
                'commission_rate' => 18,
                'decision_status' => 'go',
                'net_profit' => 145.75,
                'profit_margin_percent' => 24.3,
            ]),
            $this->snapshot(),
        );

        $this->assertSame('go', $result['decision']['status']);
        $this->assertSame('Satışa uygun', $result['decision']['label']);
        $this->assertTrue($result['decision']['finance_ready']);
        $this->assertSame(145.75, $result['decision']['net_profit']);
        $this->assertSame(24.3, $result['decision']['profit_margin_percent']);
    }

    private function service(): TrendyolBoosterProductAnalysisService
    {
        return new TrendyolBoosterProductAnalysisService(
            Mockery::mock(TrendyolBoosterAnalysisService::class),
            Mockery::mock(TrendyolBoosterIntelligenceService::class),
        );
    }

    /** @param array<string, mixed> $overrides */
    private function product(array $overrides): TrendyolBoosterProduct
    {
        $product = new TrendyolBoosterProduct(array_replace([
            'title' => 'ZOLM Test Ürünü',
            'source_url' => 'https://www.trendyol.com/zolm/test-p-123456',
            'trendyol_product_id' => '123456',
            'sale_price' => 599.90,
            'cogs' => 0,
            'commission_rate' => 0,
            'decision_status' => 'risk',
            'opportunity_score' => 35,
            'decision_reasons' => ['Maliyet eksik.'],
        ], $overrides));
        $product->id = 42;

        return $product;
    }

    private function snapshot(): TrendyolBoosterSnapshot
    {
        return new TrendyolBoosterSnapshot([
            'sale_price' => 599.90,
            'estimated_daily_sales' => 3.4,
            'confidence_score' => 64,
            'data_quality_score' => 72,
            'analysis_source' => 'browser_companion',
            'metrics_json' => ['sales_estimate' => ['status' => 'proxy']],
            'data_sources' => ['browser_companion'],
            'checked_at' => now(),
        ]);
    }
}
