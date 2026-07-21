<?php

namespace Tests\Feature\Hr;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HrPhase7APayrollCalculationRollbackTest extends TestCase
{
    use RefreshHrDatabase;

    public function test_payroll_calculation_schema_exists(): void
    {
        $this->assertTrue(Schema::hasColumns('hr_payroll_rules', ['status', 'configuration_hash', 'approved_by', 'approved_at']));
        $this->assertTrue(Schema::hasColumns('hr_payroll_periods', ['calculated_at', 'calculation_hash', 'preflight_status', 'preflight_findings']));
        $this->assertTrue(Schema::hasColumns('hr_payroll_records', ['salary_record_id', 'calculation_trace', 'gross_pay_encrypted', 'net_pay_encrypted']));
        $this->assertTrue(Schema::hasTable('hr_payroll_tax_openings'));
        $this->assertTrue(Schema::hasTable('hr_payroll_tax_ledgers'));
    }

    public function test_rollback_preserves_phase6_tables(): void
    {
        Artisan::call('migrate:rollback', ['--step' => 1]);Artisan::call('migrate:rollback', ['--step' => 1]);Artisan::call('migrate:rollback', ['--step' => 1]);Artisan::call('migrate:rollback', ['--step' => 4]);
        $this->assertFalse(Schema::hasTable('hr_payroll_tax_ledgers'));
        $this->assertFalse(Schema::hasColumn('hr_payroll_records', 'net_pay_encrypted'));
        $this->assertTrue(Schema::hasTable('hr_analytics_snapshots'));
        $this->assertTrue(Schema::hasTable('hr_salary_records'));
    }
}
