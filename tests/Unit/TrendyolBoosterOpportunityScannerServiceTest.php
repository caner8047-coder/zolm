<?php

namespace Tests\Unit;

use App\Services\Marketplace\TrendyolBoosterOpportunityScannerService;
use Tests\TestCase;

class TrendyolBoosterOpportunityScannerServiceTest extends TestCase
{
    public function test_it_ranks_visible_products_and_caps_incomplete_evidence(): void
    {
        $scan = (new TrendyolBoosterOpportunityScannerService())->scan([
            $this->item('1', 'Dengeli Aday', 'ZOLM', 80, 4.8, 60),
            $this->item('2', 'Pahalı Rakip', 'Rakip', 180, 4.2, 500),
            $this->item('3', 'Eksik Kart', '', 0, 0, 0),
        ]);

        $this->assertSame(3, $scan['scanned_count']);
        $this->assertSame('1', $scan['results'][0]['trendyol_product_id']);
        $this->assertGreaterThan($scan['results'][1]['opportunity_score'], $scan['results'][0]['opportunity_score']);
        $incomplete = collect($scan['results'])->firstWhere('trendyol_product_id', '3');
        $this->assertLessThanOrEqual(64, $incomplete['opportunity_score']);
        $this->assertContains('Eksik kart verisi nedeniyle skor sınırlandı', $incomplete['reasons']);
        $this->assertStringContainsString('Kesin satış', $scan['method_note']);
    }

    /** @return array<string, mixed> */
    private function item(string $id, string $title, string $brand, float $price, float $rating, int $reviews): array
    {
        return [
            'trendyol_product_id' => $id,
            'source_url' => "https://www.trendyol.com/zolm/urun-p-{$id}",
            'title' => $title,
            'brand' => $brand,
            'sale_price' => $price,
            'rating' => $rating,
            'review_count' => $reviews,
        ];
    }
}
