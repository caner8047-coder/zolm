<?php

namespace Tests\Feature\Hr;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HrPhase2CMigrationsRollbackTest extends TestCase
{
    use RefreshHrDatabase;

    public function test_timesheet_and_overtime_tables_have_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('hr_timesheet_periods', ['legal_entity_id', 'starts_on', 'ends_on', 'status', 'closed_at']));
        $this->assertTrue(Schema::hasColumns('hr_timesheets', ['timesheet_period_id', 'employee_id', 'work_date', 'scheduled_minutes', 'worked_minutes', 'overtime_minutes', 'missing_minutes', 'source_revision']));
        $this->assertTrue(Schema::hasColumns('hr_timesheet_corrections', ['timesheet_id', 'revision_number', 'old_values', 'new_values', 'reason']));
        $this->assertTrue(Schema::hasColumns('hr_overtime_types', ['code', 'multiplier', 'annual_limit_minutes', 'requires_approval']));
        $this->assertTrue(Schema::hasColumns('hr_overtime_requests', ['employee_id', 'overtime_type_id', 'requested_minutes', 'approved_minutes', 'status']));
    }

    public function test_phase2c_rollback_preserves_attendance_tables(): void
    {
        Artisan::call('migrate:rollback', ['--step' => 4]);
        Artisan::call('migrate:rollback', ['--step' => 4]);
        Artisan::call('migrate:rollback', ['--step' => 5]);
        Artisan::call('migrate:rollback', ['--step' => 3]);
        Artisan::call('migrate:rollback', ['--step' => 5]);
        Artisan::call('migrate:rollback', ['--step' => 6]);
        Artisan::call('migrate:rollback', ['--step' => 2]);
        Artisan::call('migrate:rollback', ['--step' => 4]);
        Artisan::call('migrate:rollback', ['--step' => 2]);
        Artisan::call('migrate:rollback', ['--step' => 3]);
        Artisan::call('migrate:rollback', ['--step' => 3]);
        Artisan::call('migrate:rollback', ['--step' => 5]);
        $this->assertFalse(Schema::hasTable('hr_timesheets'));
        $this->assertFalse(Schema::hasTable('hr_overtime_requests'));
        $this->assertTrue(Schema::hasTable('hr_attendance_events'));
        $this->assertTrue(Schema::hasTable('hr_attendance_anomalies'));
    }
}
