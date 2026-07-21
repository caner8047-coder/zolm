<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Compensation\Actions\ManageSalaryRecordAction;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Overtime\Models\HrOvertimeRequest;
use App\Modules\Hr\Payroll\Actions\ApprovePayrollPeriodAction;
use App\Modules\Hr\Payroll\Actions\ApprovePayrollRuleAction;
use App\Modules\Hr\Payroll\Actions\CalculatePayrollPeriodAction;
use App\Modules\Hr\Payroll\Actions\PreparePayrollPeriodAction;
use App\Modules\Hr\Payroll\Models\HrPayrollRule;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Timesheet\Models\HrTimesheet;
use App\Modules\Hr\Timesheet\Models\HrTimesheetPeriod;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PayrollCalculationWorkflowTest extends TestCase
{
    use RefreshHrDatabase;

    public function test_versioned_rules_produce_encrypted_explainable_payroll_and_require_second_approver(): void
    {
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $role = DB::table('roles')->where('slug', 'hr_admin')->value('id');
        $maker = User::factory()->create(['role_id' => $role]);
        $ruleApprover = User::factory()->create(['role_id' => $role]);
        $payrollApprover = User::factory()->create(['role_id' => $role]);
        $this->actingAs($maker);
        $tenant = LegalEntity::create(['user_id' => $maker->id, 'name' => 'Bordro', 'tax_number' => '2323232323', 'is_active' => true]);
        app(TenantContext::class)->set($tenant);
        $employee = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenant->id, 'employee_number' => 'CALC1', 'national_id_encrypted' => 'enc',
            'national_id_hash' => 'payroll-calc-1', 'national_id_last_four' => '0001', 'first_name' => 'Hesap', 'last_name' => 'Test', 'status' => 'active',
        ]);
        $date = now()->setDate(2026, 1, 31)->startOfDay();
        $timesheetPeriod = HrTimesheetPeriod::create([
            'legal_entity_id' => $tenant->id, 'name' => 'Ocak 2026', 'starts_on' => '2026-01-01',
            'ends_on' => '2026-01-31', 'status' => 'closed', 'closed_at' => now(), 'closed_by' => $maker->id,
        ]);
        HrTimesheet::create([
            'legal_entity_id' => $tenant->id, 'timesheet_period_id' => $timesheetPeriod->id, 'employee_id' => $employee->id,
            'work_date' => $date, 'scheduled_minutes' => 6000, 'worked_minutes' => 6000, 'overtime_minutes' => 600,
            'status' => 'closed', 'source_revision' => 1,
        ]);
        $overtimeTypeId = DB::table('hr_overtime_types')->insertGetId([
            'legal_entity_id' => $tenant->id, 'code' => 'STANDARD', 'name' => 'Standart', 'multiplier' => 1.5,
            'requires_approval' => true, 'is_active' => true, 'created_by' => $maker->id, 'updated_by' => $maker->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        HrOvertimeRequest::create([
            'legal_entity_id' => $tenant->id, 'employee_id' => $employee->id, 'overtime_type_id' => $overtimeTypeId,
            'work_date' => $date, 'starts_at' => '18:00', 'ends_at' => '04:00', 'requested_minutes' => 600,
            'approved_minutes' => 600, 'status' => 'approved', 'reason' => 'Test', 'requested_by' => $maker->id,
            'decided_by' => $ruleApprover->id, 'decided_at' => now(),
        ]);

        $salary = app(ManageSalaryRecordAction::class)->propose($employee, 10000, '2026-01-01', 'İlk ücret');
        $rules = [
            'standard_monthly_minutes' => 6000,
            'overtime_multiplier_basis_points' => 15000,
            'employee_social_security_basis_points' => 1000,
            'employee_unemployment_basis_points' => 100,
            'employer_social_security_basis_points' => 500,
            'employer_unemployment_basis_points' => 100,
            'stamp_tax_basis_points' => 100,
            'income_tax_exemption_cents' => 22500,
            'stamp_tax_exemption_cents' => 2500,
            'income_tax_brackets' => [['upper_limit_cents' => null, 'rate_basis_points' => 2000]],
        ];
        $rule = HrPayrollRule::create([
            'legal_entity_id' => $tenant->id, 'code' => 'STATUTORY_PAYROLL', 'name' => 'Test mevzuat paketi',
            'version' => 1, 'configuration' => $rules, 'configuration_hash' => app(\App\Modules\Hr\Payroll\Services\PayrollRuleConfiguration::class)->hash($rules),
            'effective_from' => '2026-01-01', 'is_active' => false, 'status' => 'pending_approval', 'created_by' => $maker->id,
        ]);

        try {
            app(ApprovePayrollRuleAction::class)->execute($rule);
            $this->fail('Kuralı hazırlayan kişi onaylayamamalı.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->actingAs($ruleApprover);
        app(ManageSalaryRecordAction::class)->approve($salary);
        app(ApprovePayrollRuleAction::class)->execute($rule->fresh());
        $prepared = app(PreparePayrollPeriodAction::class)->execute($timesheetPeriod);
        $calculated = app(CalculatePayrollPeriodAction::class)->execute($prepared);
        $record = $calculated->records->first();

        $this->assertSame('calculated', $calculated->status);
        $this->assertSame('passed', $calculated->preflight_status);
        $this->assertSame(11500.0, $record->grossPay());
        $this->assertSame(8323.0, $record->netPay());
        $this->assertSame(690.0, $record->employerContributions());
        $this->assertSame(1023500, $record->calculation_trace['closing_tax_base_cents']);
        $this->assertDatabaseHas('hr_payroll_tax_ledgers', ['payroll_period_id' => $calculated->id, 'employee_id' => $employee->id, 'tax_year' => 2026]);
        $raw = DB::table('hr_payroll_records')->where('id', $record->id)->first();
        $this->assertStringNotContainsString('8323', $raw->net_pay_encrypted);
        $this->assertStringNotContainsString('1023500', $raw->calculation_trace);

        try {
            app(ApprovePayrollPeriodAction::class)->execute($calculated);
            $this->fail('Bordroyu hesaplayan kişi onaylayamamalı.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->actingAs($payrollApprover);
        $approved = app(ApprovePayrollPeriodAction::class)->execute($calculated->fresh());
        $this->assertSame('approved', $approved->status);
    }
}
