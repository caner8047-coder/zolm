<?php

namespace Tests\Unit;

use App\Services\Marketplace\TrendyolBoosterStorePortfolioService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class TrendyolBoosterStorePortfolioServiceTest extends TestCase
{
    public function test_it_builds_store_and_category_portfolio_without_counting_removed_products(): void
    {
        $result = (new TrendyolBoosterStorePortfolioService())->analyze(new Collection([
            $this->item('Ev Mobilyası', 100, true, ['Kupon'], 3.2, 120, 8, true),
            $this->item('Ev Mobilyası', 300, false, [], 1.1, 80, 2, false),
            $this->item('Dekorasyon', 500, false, ['Kargo Bedava'], 0.7, 60, 1, false),
            $this->item('Eski Kategori', 900, false, [], 0, 10, 0, false, true),
        ]));

        $this->assertSame(3, $result['active_product_count']);
        $this->assertSame(1, $result['removed_product_count']);
        $this->assertSame(300.0, $result['median_price']);
        $this->assertSame('Ev Mobilyası', $result['dominant_category']);
        $this->assertSame(66.7, $result['dominant_category_share_percent']);
        $this->assertSame('Yüksek yoğunlaşma', $result['concentration_label']);
        $this->assertSame(5.0, $result['estimated_daily_sales']);
        $this->assertCount(2, $result['categories']);
        $this->assertStringContainsString('tahmindir', $result['evidence_note']);
    }

    /** @return array<string, mixed> */
    private function item(
        string $category,
        float $price,
        bool $new,
        array $campaigns,
        float $sales,
        int $reviews,
        int $reviewDelta,
        bool $firstSeller,
        bool $removed = false,
    ): array {
        return [
            'category_name' => $category,
            'sale_price' => $price,
            'is_new' => $new,
            'is_removed' => $removed,
            'campaign_badges' => $campaigns,
            'review_count' => $reviews,
            'review_delta' => $reviewDelta,
            'is_first_seller' => $firstSeller,
            'store_sales_signal' => ['estimated_daily_sales' => $sales],
        ];
    }
}
