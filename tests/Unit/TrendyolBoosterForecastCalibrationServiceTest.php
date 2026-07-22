<?php

namespace Tests\Unit;

use App\Services\Marketplace\TrendyolBoosterForecastCalibrationService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class TrendyolBoosterForecastCalibrationServiceTest extends TestCase
{
    public function test_it_calibrates_predictions_and_reports_bias_by_category(): void
    {
        $summary = (new TrendyolBoosterForecastCalibrationService())->summarize(new Collection([
            ['product_id' => 1, 'title' => 'A', 'category' => 'Ev', 'predicted' => 10, 'actual' => 8, 'checked_at' => '2026-07-22T10:00:00+03:00'],
            ['product_id' => 2, 'title' => 'B', 'category' => 'Ev', 'predicted' => 5, 'actual' => 5, 'checked_at' => '2026-07-22T11:00:00+03:00'],
            ['product_id' => 3, 'title' => 'C', 'category' => 'Moda', 'predicted' => 4, 'actual' => 5, 'checked_at' => '2026-07-22T12:00:00+03:00'],
        ]));

        $this->assertSame('calibrated', $summary['status']);
        $this->assertSame(3, $summary['sample_count']);
        $this->assertSame(15.0, $summary['mape']);
        $this->assertSame(100.0, $summary['within_25_percent']);
        $this->assertSame('Yüksek tahmin eğilimi', $summary['bias_label']);
        $this->assertCount(2, $summary['categories']);
        $this->assertStringContainsString('Stok ikmali', $summary['evidence_note']);
    }

    public function test_it_stays_in_warming_up_state_without_three_samples(): void
    {
        $summary = (new TrendyolBoosterForecastCalibrationService())->summarize(new Collection([
            ['category' => 'Ev', 'predicted' => 2, 'actual' => 0, 'checked_at' => '2026-07-22T10:00:00+03:00'],
        ]));

        $this->assertSame('warming_up', $summary['status']);
        $this->assertNull($summary['mape']);
        $this->assertNull($summary['within_25_percent']);
    }
}
