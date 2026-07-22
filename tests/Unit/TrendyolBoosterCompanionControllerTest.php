<?php

namespace Tests\Unit;

use App\Http\Controllers\TrendyolBoosterCompanionController;
use App\Models\TrendyolBestsellerReport;
use App\Models\TrendyolBestsellerReportRun;
use App\Services\Marketplace\TrendyolBestsellerReportService;
use App\Services\Marketplace\TrendyolBoosterAnalysisService;
use App\Services\Marketplace\TrendyolBoosterProductAnalysisService;
use App\Services\Marketplace\TrendyolBoosterReviewService;
use App\Services\Marketplace\TrendyolBoosterStockService;
use App\Services\Marketplace\TrendyolBoosterStoreWatchService;
use App\Services\Marketplace\TrendyolBoosterSupplierResearchService;
use App\Services\Marketplace\TrendyolProductPageReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

class TrendyolBoosterCompanionControllerTest extends TestCase
{
    public function test_visible_listing_is_saved_as_a_bestseller_report_run(): void
    {
        Auth::shouldReceive('id')->once()->andReturn(42);

        $report = new TrendyolBestsellerReport(['query' => 'puf']);
        $report->id = 321;
        $run = new TrendyolBestsellerReportRun([
            'item_count' => 2,
            'priced_item_count' => 2,
        ]);
        $run->id = 654;

        $reportService = Mockery::mock(TrendyolBestsellerReportService::class);
        $reportService->shouldReceive('storeRun')
            ->once()
            ->withArgs(function (int $userId, array $context, array $items): bool {
                return $userId === 42
                    && $context['query'] === 'puf'
                    && $context['source'] === 'browser_companion'
                    && $items[0]['rank'] === 1
                    && $items[0]['price'] === 538.35
                    && $items[0]['rating_count'] === 124;
            })
            ->andReturn([
                'report' => $report,
                'run' => $run,
                'created' => true,
            ]);

        $controller = new TrendyolBoosterCompanionController(
            Mockery::mock(TrendyolBoosterAnalysisService::class),
            Mockery::mock(TrendyolBoosterProductAnalysisService::class),
            Mockery::mock(TrendyolBoosterStockService::class),
            Mockery::mock(TrendyolBoosterStoreWatchService::class),
            Mockery::mock(TrendyolBoosterSupplierResearchService::class),
            Mockery::mock(TrendyolBoosterReviewService::class),
            Mockery::mock(TrendyolProductPageReader::class),
            $reportService,
        );
        $request = Request::create('/marketplace-trendyol-booster/companion/bestseller-capture', 'POST', [
            'query' => 'puf',
            'matched_label' => 'Puf',
            'source_url' => 'https://www.trendyol.com/sr?q=puf',
            'items' => [
                [
                    'trendyol_product_id' => '700001',
                    'source_url' => 'https://www.trendyol.com/zolm/alpha-p-700001',
                    'title' => 'Alpha Puf',
                    'brand' => 'ZOLM',
                    'sale_price' => 538.35,
                    'rating' => 4.8,
                    'review_count' => 124,
                    'campaign_badges' => ['Kargo Bedava'],
                ],
                [
                    'trendyol_product_id' => '700002',
                    'source_url' => 'https://www.trendyol.com/zolm/beta-p-700002',
                    'title' => 'Beta Puf',
                    'brand' => 'ZOLM',
                    'sale_price' => 649.90,
                ],
            ],
        ]);
        $response = $controller->bestsellerCapture($request);
        $payload = $response->getData(true);

        $this->assertTrue($payload['ok']);
        $this->assertSame('bestseller_capture', $payload['mode']);
        $this->assertSame(321, $payload['report_id']);
        $this->assertSame(654, $payload['run_id']);
        $this->assertSame(2, $payload['item_count']);
        $this->assertStringContainsString('bestseller_mode=reports', $payload['dashboard_url']);
        $this->assertStringContainsString('bestseller_report=321', $payload['dashboard_url']);
    }
}
