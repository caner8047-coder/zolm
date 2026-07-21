<?php

namespace Tests\Feature\Hr;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HrPhase7CPayrollAdjustmentsRollbackTest extends TestCase
{
    use RefreshHrDatabase;

    public function test_payroll_adjustment_schema_exists(): void
    {
        $this->assertTrue(Schema::hasColumns('hr_payroll_adjustments', ['payroll_period_id', 'employee_id', 'type', 'amount_encrypted', 'status', 'approved_by']));
    }

    public function test_rollback_preserves_phase7b_exports(): void
    {
        Artisan::call('migrate:rollback', ['--step' => 1]);
        $this->assertFalse(Schema::hasTable('hr_payroll_adjustments'));
        $this->assertTrue(Schema::hasTable('hr_payroll_exports'));
        $this->assertTrue(Schema::hasColumn('hr_payroll_periods', 'output_preflight_hash'));
    }
}
