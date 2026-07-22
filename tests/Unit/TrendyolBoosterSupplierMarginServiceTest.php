<?php

namespace Tests\Unit;

use App\Services\Marketplace\TrendyolBoosterSupplierMarginService;
use PHPUnit\Framework\TestCase;

class TrendyolBoosterSupplierMarginServiceTest extends TestCase
{
    public function test_it_ranks_verified_supplier_offers_by_margin_and_exposes_break_even_limit(): void
    {
        $result = (new TrendyolBoosterSupplierMarginService())->scenarios([
            ['id' => 1, 'platform_label' => 'Kanal A', 'seller_name' => 'Ucuz', 'sale_price' => 500, 'match_score' => 95],
            ['id' => 2, 'platform_label' => 'Kanal B', 'seller_name' => 'Pahalı', 'sale_price' => 850, 'match_score' => 92],
            ['id' => 3, 'platform_label' => 'Kanal C', 'seller_name' => 'Belirsiz', 'sale_price' => 300, 'match_score' => 60],
        ], 1000, 20, 50, 10, 15);

        $this->assertSame(590.0, $result['max_purchase_cost']);
        $this->assertSame('go', $result['rows'][0]['decision']);
        $this->assertSame(240.0, $result['rows'][0]['net_profit']);
        $this->assertSame('verify', $result['rows'][2]['decision']);
        $this->assertSame(1, $result['go_count']);
    }
}
