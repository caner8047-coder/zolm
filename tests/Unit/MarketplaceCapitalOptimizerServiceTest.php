<?php

namespace Tests\Unit;

use App\Services\Marketplace\MarketplaceCapitalOptimizerService;
use Tests\TestCase;

class MarketplaceCapitalOptimizerServiceTest extends TestCase
{
    public function test_it_separates_growth_stock_protection_reduction_and_data_gaps(): void
    {
        $rows = collect([
            (object) ['id' => 1, 'product_name' => 'Büyüme Ürünü', 'stock_quantity' => 45, 'sold_quantity' => 30, 'gross_revenue' => 9000, 'cogs' => 100, 'packaging_cost' => 10, 'cargo_cost' => 20, 'sale_price' => 300, 'commission_rate' => 10],
            (object) ['id' => 2, 'product_name' => 'Stok Riski', 'stock_quantity' => 5, 'sold_quantity' => 30, 'gross_revenue' => 9000, 'cogs' => 100, 'packaging_cost' => 10, 'cargo_cost' => 20, 'sale_price' => 300, 'commission_rate' => 10],
            (object) ['id' => 3, 'product_name' => 'Yavaş Stok', 'stock_quantity' => 200, 'sold_quantity' => 10, 'gross_revenue' => 1600, 'cogs' => 100, 'packaging_cost' => 10, 'cargo_cost' => 10, 'sale_price' => 160, 'commission_rate' => 15],
            (object) ['id' => 4, 'product_name' => 'Maliyetsiz', 'stock_quantity' => 10, 'sold_quantity' => 4, 'gross_revenue' => 800, 'cogs' => 0, 'packaging_cost' => 0, 'cargo_cost' => 0, 'sale_price' => 200, 'commission_rate' => 10],
        ]);

        $result = app(MarketplaceCapitalOptimizerService::class)->analyzeRows($rows, 30);
        $items = collect($result['items'])->keyBy('product_id');

        $this->assertSame('grow', $items[1]['decision']);
        $this->assertSame('protect', $items[2]['decision']);
        $this->assertSame('reduce', $items[3]['decision']);
        $this->assertSame('investigate', $items[4]['decision']);
        $this->assertGreaterThan(0, $items[3]['releasable_capital']);
        $this->assertSame(4, $result['summary']['product_count']);
        $this->assertStringContainsString('otomatik satın alma', $result['evidence_note']);
    }

    public function test_it_does_not_claim_releasable_capital_for_products_without_sales_evidence(): void
    {
        $result = app(MarketplaceCapitalOptimizerService::class)->analyzeRows(collect([
            (object) ['id' => 8, 'product_name' => 'Yeni Ürün', 'stock_quantity' => 100, 'sold_quantity' => 0, 'gross_revenue' => 0, 'cogs' => 50, 'packaging_cost' => 5, 'cargo_cost' => 5, 'sale_price' => 150, 'commission_rate' => 15],
        ]), 30);

        $this->assertSame('investigate', $result['items'][0]['decision']);
        $this->assertSame(0.0, $result['items'][0]['releasable_capital']);
        $this->assertSame(0.0, $result['summary']['releasable_capital']);
    }
}
