<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Analytics\Models\HrAnalyticsSnapshot;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HrMockDataCommandTest extends TestCase
{
    use RefreshHrDatabase;

    public function test_mock_scenario_is_complete_and_idempotent(): void
    {
        Storage::fake('private');
        $admin = User::factory()->create(['email' => 'admin@zolm.test', 'role' => 'admin']);
        $tenant = LegalEntity::create([
            'user_id' => $admin->id,
            'name' => 'ZOLM Mock Test',
            'tax_number' => '9999999999',
            'is_active' => true,
        ]);
        HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenant->id,
            'employee_number' => 'EMP00001',
            'national_id_encrypted' => '39999999999',
            'national_id_hash' => hash('sha256', '39999999999'.config('app.key')),
            'national_id_last_four' => '9999',
            'first_name' => 'Mevcut',
            'last_name' => 'Çalışan',
            'status' => 'active',
        ]);

        $this->artisan('hr:seed-mock-data', ['--legal-entity-id' => $tenant->id])->assertExitCode(0);
        $firstCounts = $this->scenarioCounts($tenant->id);
        $this->artisan('hr:seed-mock-data', ['--legal-entity-id' => $tenant->id])->assertExitCode(0);

        $this->assertSame($firstCounts, $this->scenarioCounts($tenant->id));
        $this->assertSame(15, $firstCounts['employees']);
        $this->assertSame(15, $firstCounts['linked_users']);
        $this->assertSame(15, $firstCounts['complete_employment']);
        $this->assertSame(15, $firstCounts['salary_coverage']);
        $this->assertSame($firstCounts['check_ins'], $firstCounts['check_outs']);
        $this->assertGreaterThan(0, $firstCounts['check_ins']);
        $this->assertSame(15, $firstCounts['payroll_records']);
        $this->assertSame(15, $firstCounts['tax_ledgers']);
        $this->assertSame(15, $firstCounts['benefit_adjustments']);
        $this->assertGreaterThanOrEqual(5, $firstCounts['document_types']);
        $this->assertGreaterThan(45, $firstCounts['documents']);
        $this->assertGreaterThan(0, $firstCounts['document_requests']);
        $this->assertSame(3, $firstCounts['leave_approval_steps']);

        $this->assertDatabaseHas('hr_timesheet_periods', [
            'legal_entity_id' => $tenant->id,
            'status' => 'closed',
        ]);
        $this->assertDatabaseHas('hr_payroll_rules', [
            'legal_entity_id' => $tenant->id,
            'code' => 'STATUTORY_PAYROLL',
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('hr_payroll_periods', [
            'legal_entity_id' => $tenant->id,
            'status' => 'calculated',
            'preflight_status' => 'passed',
        ]);
        $this->assertDatabaseHas('hr_integration_outbox', [
            'legal_entity_id' => $tenant->id,
            'target' => 'finance',
            'event_type' => 'expense_approved',
        ]);

        $snapshot = HrAnalyticsSnapshot::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenant->id)
            ->latest('id')
            ->firstOrFail();
        $this->assertSame(15, $snapshot->metrics['headcount']);
        $this->assertSame(15, $snapshot->metrics['salary_coverage']);
        $this->assertArrayHasKey('latest_payroll_employer_cost', $snapshot->metrics);
        $this->assertArrayHasKey('payroll', $snapshot->sources);
    }

    private function scenarioCounts(int $tenantId): array
    {
        return [
            'employees' => DB::table('hr_employees')->where('legal_entity_id', $tenantId)->where('status', 'active')->count(),
            'linked_users' => DB::table('hr_employees')->where('legal_entity_id', $tenantId)->where('status', 'active')->whereNotNull('user_id')->count(),
            'complete_employment' => DB::table('hr_employment_records')->where('legal_entity_id', $tenantId)->where('status', 'active')->whereNotNull('branch_id')->whereNotNull('sgk_workplace_id')->whereNotNull('cost_center_id')->count(),
            'salary_coverage' => DB::table('hr_salary_records')->where('legal_entity_id', $tenantId)->where('status', 'approved')->distinct()->count('employee_id'),
            'check_ins' => DB::table('hr_attendance_events')->where('legal_entity_id', $tenantId)->where('event_type', 'check_in')->count(),
            'check_outs' => DB::table('hr_attendance_events')->where('legal_entity_id', $tenantId)->where('event_type', 'check_out')->count(),
            'payroll_records' => DB::table('hr_payroll_records')->where('legal_entity_id', $tenantId)->count(),
            'tax_ledgers' => DB::table('hr_payroll_tax_ledgers')->where('legal_entity_id', $tenantId)->count(),
            'benefit_adjustments' => DB::table('hr_payroll_adjustments')->where('legal_entity_id', $tenantId)->where('type', 'employer_benefit')->count(),
            'document_types' => DB::table('hr_document_types')->where('legal_entity_id', $tenantId)->count(),
            'documents' => DB::table('hr_employee_documents')->where('legal_entity_id', $tenantId)->whereNull('deleted_at')->count(),
            'document_requests' => DB::table('hr_document_requests')->where('legal_entity_id', $tenantId)->where('status', 'pending')->count(),
            'leave_approval_steps' => DB::table('hr_leave_approval_steps')->where('legal_entity_id', $tenantId)->count(),
            'support_messages' => DB::table('hr_support_messages')->where('legal_entity_id', $tenantId)->count(),
            'recognitions' => DB::table('hr_recognitions')->where('legal_entity_id', $tenantId)->count(),
            'analytics' => DB::table('hr_analytics_snapshots')->where('legal_entity_id', $tenantId)->count(),
        ];
    }
}
