<?php

namespace Tests\Feature\Hr;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HrPhase7BPayrollExportRollbackTest extends TestCase
{
    use RefreshHrDatabase;

    public function test_payroll_output_preflight_schema_exists(): void
    {
        $this->assertTrue(Schema::hasColumns('hr_payroll_periods', ['output_preflight_status', 'output_preflight_hash', 'output_preflight_at', 'output_preflight_by']));
        $this->assertTrue(Schema::hasColumns('hr_payroll_exports', ['classification', 'preflight_hash', 'content_hash', 'generated_by']));
    }

    public function test_rollback_preserves_phase7a_calculation_tables(): void
    {
        Artisan::call('migrate:rollback', ['--step' => 1]);Artisan::call('migrate:rollback', ['--step' => 1]);Artisan::call('migrate:rollback', ['--step' => 1]);Artisan::call('migrate:rollback', ['--step' => 1]);
        $this->assertFalse(Schema::hasTable('hr_payroll_exports'));
        $this->assertFalse(Schema::hasColumn('hr_payroll_periods', 'output_preflight_hash'));
        $this->assertTrue(Schema::hasTable('hr_payroll_tax_ledgers'));
        $this->assertTrue(Schema::hasColumn('hr_payroll_records', 'net_pay_encrypted'));
    }
}
