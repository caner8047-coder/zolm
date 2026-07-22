<?php

namespace Tests\Unit;

use App\Models\TrendyolBoosterProduct;
use App\Services\Marketplace\TrendyolBoosterReportService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class TrendyolBoosterReportServiceTest extends TestCase
{
    public function test_it_builds_explicit_turkish_report_columns_without_losing_product_ids(): void
    {
        $product = new TrendyolBoosterProduct([
            'title' => 'Test Ürünü',
            'trendyol_product_id' => '001234567890',
            'sale_price' => 999.9,
            'cogs' => 500,
            'net_profit' => 120.4,
            'net_margin' => 12.04,
            'decision_status' => 'watch',
        ]);
        $product->setRelation('latestSnapshot', null);

        $rows = (new TrendyolBoosterReportService())->buildRows(new Collection([$product]));

        $this->assertSame('001234567890', $rows->first()['Trendyol Ürün ID']);
        $this->assertSame(999.9, $rows->first()['Satış Fiyatı TL']);
        $this->assertArrayHasKey('Kaynak URL', $rows->first());
    }
}
