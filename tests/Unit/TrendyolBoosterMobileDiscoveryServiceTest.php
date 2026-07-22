<?php

namespace Tests\Unit;

use App\Services\Marketplace\TrendyolBoosterMobileDiscoveryService;
use App\Services\Marketplace\TrendyolSearchResultReader;
use PHPUnit\Framework\TestCase;

class TrendyolBoosterMobileDiscoveryServiceTest extends TestCase
{
    public function test_it_accepts_only_a_verified_trendyol_product_url_from_search_results(): void
    {
        $service = new TrendyolBoosterMobileDiscoveryService($this->createMock(TrendyolSearchResultReader::class));
        $result = $service->fromSearchPayload('8691234567890', ['top_products' => [
            ['source_url' => 'https://example.com/fake-p-1', 'trendyol_product_id' => '1'],
            ['source_url' => 'https://www.trendyol.com/marka/urun-p-987654', 'trendyol_product_id' => '987654'],
        ]]);

        $this->assertTrue($result['ok']);
        $this->assertSame('987654', $result['product_id']);
        $this->assertStringContainsString('-p-987654', $result['source_url']);
    }
}
