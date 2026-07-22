<?php

namespace Tests\Unit\Hr;

use App\Modules\Hr\Leave\Services\HrAnnualLeaveAccrualService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HrAnnualLeaveAccrualServiceTest extends TestCase
{
    public function test_annual_leave_entitlement_calculation_under_labor_law_m53(): void
    {
        $service = new HrAnnualLeaveAccrualService();

        // 1-5 yıl kıdem -> 14 gün
        $days3Years = $service->calculateEntitlementDays(
            Carbon::now()->subYears(3),
            '1990-01-01'
        );
        $this->assertEquals(14, $days3Years);

        // 5-15 yıl kıdem -> 20 gün
        $days7Years = $service->calculateEntitlementDays(
            Carbon::now()->subYears(7),
            '1990-01-01'
        );
        $this->assertEquals(20, $days7Years);

        // 15+ yıl kıdem -> 26 gün
        $days16Years = $service->calculateEntitlementDays(
            Carbon::now()->subYears(16),
            '1990-01-01'
        );
        $this->assertEquals(26, $days16Years);

        // Yaş 50 ve üzeri özel yaş şartı: 2 yıl kıdem normalde 14 gün fakat 50 yaş için taban 20 gün!
        $daysSeniorAge = $service->calculateEntitlementDays(
            Carbon::now()->subYears(2),
            Carbon::now()->subYears(52)
        );
        $this->assertEquals(20, $daysSeniorAge);
    }
}
