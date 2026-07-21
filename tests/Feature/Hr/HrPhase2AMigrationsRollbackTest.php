<?php

namespace Tests\Feature\Hr;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HrPhase2AMigrationsRollbackTest extends TestCase
{
    use RefreshHrDatabase;

    public function test_shift_tables_have_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('hr_shift_templates', ['legal_entity_id', 'code', 'starts_at', 'ends_at', 'break_minutes', 'is_active']));
        $this->assertTrue(Schema::hasColumns('hr_shift_assignments', ['legal_entity_id', 'employee_id', 'shift_template_id', 'shift_date', 'status', 'cancelled_at', 'cancelled_by', 'cancellation_reason']));
        $this->assertTrue(Schema::hasColumns('hr_shift_availabilities', ['legal_entity_id', 'employee_id', 'availability_date', 'status']));
        $this->assertTrue(Schema::hasColumns('hr_shift_change_requests', ['legal_entity_id', 'employee_id', 'shift_assignment_id', 'desired_shift_template_id', 'desired_shift_date', 'status']));
    }

    public function test_phase2a_rollback_preserves_leave_tables(): void
    {
        Artisan::call('migrate:rollback', ['--step' => 3]);
        Artisan::call('migrate:rollback', ['--step' => 5]);
        $this->assertFalse(Schema::hasTable('hr_shift_assignments'));
        $this->assertFalse(Schema::hasTable('hr_shift_templates'));
        $this->assertFalse(Schema::hasTable('hr_shift_availabilities'));
        $this->assertFalse(Schema::hasTable('hr_shift_change_requests'));
        $this->assertTrue(Schema::hasTable('hr_leave_requests'));
        $this->assertTrue(Schema::hasTable('hr_leave_balances'));
    }
}
