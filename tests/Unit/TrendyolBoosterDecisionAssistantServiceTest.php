<?php

namespace Tests\Unit;

use App\Models\TrendyolBoosterProduct;
use App\Services\AIService;
use App\Services\Marketplace\TrendyolBoosterDecisionAssistantService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class TrendyolBoosterDecisionAssistantServiceTest extends TestCase
{
    public function test_it_never_exposes_profit_for_a_product_without_cost_evidence(): void
    {
        $service = new TrendyolBoosterDecisionAssistantService($this->createMock(AIService::class));
        $withoutCost = new TrendyolBoosterProduct(['title' => 'Eksik Maliyet', 'sale_price' => 1000, 'cogs' => 0, 'net_profit' => 800]);
        $withCost = new TrendyolBoosterProduct(['title' => 'Hazır Ürün', 'sale_price' => 1000, 'cogs' => 500, 'net_profit' => 120, 'net_margin' => 12]);

        $evidence = $service->buildEvidence(new Collection([$withoutCost, $withCost]));
        $answer = $service->fallbackAnswer('Hangisi?', $evidence);

        $this->assertNull($evidence[0]['net_profit']);
        $this->assertStringNotContainsString('800', $answer['answer']);
        $this->assertStringContainsString('[K2]', $answer['answer']);
    }
}
