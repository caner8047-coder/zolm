<?php

namespace Tests\Feature\Hr;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HrPhase1CMigrationsRollbackTest extends TestCase
{
    use RefreshHrDatabase;

    public function test_faz1c_tables_exist_with_expected_core_columns(): void
    {
        foreach (['hr_leave_types', 'hr_leave_policies', 'hr_leave_balances', 'hr_leave_transactions', 'hr_leave_requests', 'hr_leave_approval_steps'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
            $this->assertTrue(Schema::hasColumns($table, ['id', 'legal_entity_id', 'created_at', 'updated_at']));
        }
        $this->assertTrue(Schema::hasColumns('hr_leave_requests', ['employee_id', 'leave_type_id', 'status', 'requested_amount']));
    }

    public function test_faz1c_rollback_preserves_previous_hr_tables(): void
    {
        Artisan::call('migrate:rollback', ['--step' => 6]);
        Artisan::call('migrate:rollback', ['--step' => 2]);
        Artisan::call('migrate:rollback', ['--step' => 4]);
        Artisan::call('migrate:rollback', ['--step' => 2]);
        Artisan::call('migrate:rollback', ['--step' => 3]);
        Artisan::call('migrate:rollback', ['--step' => 3]);
        Artisan::call('migrate:rollback', ['--step' => 5]);
        Artisan::call('migrate:rollback', ['--step' => 3]);
        Artisan::call('migrate:rollback', ['--step' => 5]);
        $this->assertTrue(Schema::hasTable('hr_leave_types'));

        Artisan::call('migrate:rollback', ['--step' => 6]);

        $this->assertFalse(Schema::hasTable('hr_leave_types'));
        $this->assertFalse(Schema::hasTable('hr_leave_requests'));
        $this->assertFalse(Schema::hasTable('hr_leave_approval_steps'));
        $this->assertTrue(Schema::hasTable('hr_employees'));
        $this->assertTrue(Schema::hasTable('hr_employee_documents'));
        $this->assertTrue(Schema::hasTable('hr_document_requests'));
    }
}
