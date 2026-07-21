<?php

namespace Tests\Feature\Hr;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HrPhase3BMigrationsRollbackTest extends TestCase
{
    use RefreshHrDatabase;

    public function test_expense_tables_have_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('hr_expense_categories', ['legal_entity_id', 'code', 'requires_receipt', 'default_vat_rate', 'approval_limit']));
        $this->assertTrue(Schema::hasColumns('hr_expenses', ['employee_id', 'expense_category_id', 'receipt_file_id', 'net_amount', 'vat_amount', 'gross_amount', 'source_key', 'payload_hash', 'paid_at']));
        $this->assertTrue(Schema::hasColumns('hr_expense_status_history', ['expense_id', 'from_status', 'to_status', 'acted_by']));
    }

    public function test_phase3b_rollback_preserves_payroll_tables(): void
    {
        Artisan::call('migrate:rollback', ['--step' => 1]);Artisan::call('migrate:rollback', ['--step' => 1]);Artisan::call('migrate:rollback', ['--step' => 4]);Artisan::call('migrate:rollback', ['--step' => 1]);
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
        $this->assertFalse(Schema::hasTable('hr_expenses'));
        $this->assertFalse(Schema::hasTable('hr_expense_categories'));
        $this->assertTrue(Schema::hasTable('hr_payroll_periods'));
        $this->assertTrue(Schema::hasTable('hr_payroll_records'));
    }
}
