<?php

namespace Tests\Feature\Hr;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HrPhase2BMigrationsRollbackTest extends TestCase
{
    use RefreshHrDatabase;

    public function test_attendance_tables_have_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('hr_attendance_devices', ['legal_entity_id', 'code', 'type', 'secret_hash', 'last_seen_at']));
        $this->assertTrue(Schema::hasColumns('hr_attendance_events', ['legal_entity_id', 'employee_id', 'event_type', 'occurred_at', 'source', 'source_key', 'payload_hash', 'is_manual']));
        $this->assertTrue(Schema::hasColumns('hr_attendance_anomalies', ['legal_entity_id', 'employee_id', 'work_date', 'type', 'severity', 'status', 'resolved_at']));
    }

    public function test_phase2b_rollback_preserves_shift_tables(): void
    {
        Artisan::call('migrate:rollback', ['--step' => 3]);
        Artisan::call('migrate:rollback', ['--step' => 5]);
        Artisan::call('migrate:rollback', ['--step' => 6]);
        Artisan::call('migrate:rollback', ['--step' => 2]);
        Artisan::call('migrate:rollback', ['--step' => 4]);
        Artisan::call('migrate:rollback', ['--step' => 2]);
        Artisan::call('migrate:rollback', ['--step' => 3]);
        Artisan::call('migrate:rollback', ['--step' => 3]);
        Artisan::call('migrate:rollback', ['--step' => 5]);
        Artisan::call('migrate:rollback', ['--step' => 3]);
        $this->assertFalse(Schema::hasTable('hr_attendance_devices'));
        $this->assertFalse(Schema::hasTable('hr_attendance_events'));
        $this->assertFalse(Schema::hasTable('hr_attendance_anomalies'));
        $this->assertTrue(Schema::hasTable('hr_shift_templates'));
        $this->assertTrue(Schema::hasTable('hr_shift_assignments'));
    }
}
