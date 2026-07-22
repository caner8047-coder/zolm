<?php

namespace Tests\Unit\Hr;

use App\Modules\Hr\Lifecycle\Services\SeveranceCalculatorService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SeveranceCalculatorServiceTest extends TestCase
{
    public function test_severance_and_notice_calculation_for_three_years_tenure(): void
    {
        $service = new SeveranceCalculatorService();

        $startDate = Carbon::parse('2023-01-01');
        $endDate = Carbon::parse('2026-01-01');
        $grossSalary = 40000.00;
        $benefits = 5000.00; // Giydirilmiş = 45.000 TL

        $result = $service->calculate($startDate, $endDate, $grossSalary, $benefits, 50000.00);

        $this->assertEquals(3, $result['tenure']['years']);
        $this->assertEquals(45000.00, $result['base']['adjusted_gross']);
        $this->assertGreaterThanOrEqual(135000.00, $result['severance']['gross_amount']);
        $this->assertEquals(0.00, $result['severance']['income_tax']); // Gelir vergisinden muaf
        $this->assertGreaterThan(0, $result['severance']['stamp_tax']);

        // 3 Yıl kıdem için ihbar süresi 8 hafta (56 gün)
        $this->assertEquals(8, $result['notice']['notice_weeks']);
        $this->assertEquals(56, $result['notice']['notice_days']);
        $this->assertGreaterThan(0, $result['notice']['income_tax']);
        $this->assertGreaterThan(0, $result['summary']['total_net_payable']);
    }

    public function test_severance_ceiling_cap_applied(): void
    {
        $service = new SeveranceCalculatorService();

        $startDate = Carbon::parse('2025-01-01');
        $endDate = Carbon::parse('2026-01-01');
        $grossSalary = 120000.00; // Tavan olan 46.244,38 TL'yi aşıyor

        $result = $service->calculate($startDate, $endDate, $grossSalary, 0.0, 46244.38);

        $this->assertTrue($result['base']['ceiling_applied']);
        $this->assertEquals(46244.38, $result['base']['effective_severance_base']);
        $this->assertGreaterThanOrEqual(46244.38, $result['severance']['gross_amount']);
    }
}
