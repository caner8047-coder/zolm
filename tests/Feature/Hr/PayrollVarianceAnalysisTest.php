<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Payroll\Actions\ApprovePayrollPeriodAction;
use App\Modules\Hr\Payroll\Actions\ReviewPayrollVarianceAction;
use App\Modules\Hr\Payroll\Models\HrPayrollPeriod;
use App\Modules\Hr\Payroll\Models\HrPayrollRecord;
use App\Modules\Hr\Payroll\Services\PayrollVarianceAnalysisService;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Timesheet\Models\HrTimesheetPeriod;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PayrollVarianceAnalysisTest extends TestCase
{
    use RefreshHrDatabase;

    public function test_critical_monthly_variance_requires_independent_review_before_approval(): void
    {
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $roleId = DB::table('roles')->where('slug', 'hr_admin')->value('id');
        $calculator = User::factory()->create(['role_id' => $roleId]);
        $reviewer = User::factory()->create(['role_id' => $roleId]);
        $this->actingAs($calculator);
        $tenant = LegalEntity::create([
            'user_id' => $calculator->id,
            'name' => 'Dönem Fark Testi',
            'tax_number' => '4848484848',
            'is_active' => true,
        ]);
        app(TenantContext::class)->set($tenant);
        $employee = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenant->id,
            'employee_number' => 'VAR01',
            'national_id_encrypted' => 'enc',
            'national_id_hash' => 'variance-analysis-test',
            'national_id_last_four' => '0001',
            'first_name' => 'Fark',
            'last_name' => 'Kontrolü',
            'status' => 'active',
        ]);
        $previousTimesheet = $this->timesheetPeriod($tenant->id, $calculator->id, 'Ocak 2026', '2026-01-01', '2026-01-31');
        $currentTimesheet = $this->timesheetPeriod($tenant->id, $calculator->id, 'Şubat 2026', '2026-02-01', '2026-02-28');
        $previous = HrPayrollPeriod::create([
            'legal_entity_id' => $tenant->id,
            'timesheet_period_id' => $previousTimesheet->id,
            'name' => 'Ocak 2026',
            'status' => 'approved',
            'source_hash' => str_repeat('1', 64),
            'preflight_status' => 'passed',
            'calculated_by' => $calculator->id,
            'approved_by' => $reviewer->id,
            'approved_at' => now(),
        ]);
        $current = HrPayrollPeriod::create([
            'legal_entity_id' => $tenant->id,
            'timesheet_period_id' => $currentTimesheet->id,
            'name' => 'Şubat 2026',
            'status' => 'calculated',
            'source_hash' => str_repeat('2', 64),
            'source_status' => 'fresh',
            'preflight_status' => 'passed',
            'calculated_by' => $calculator->id,
            'calculated_at' => now(),
        ]);
        $this->record($previous, $employee, 100000, 'approved');
        $this->record($current, $employee, 140000, 'calculated');

        $analyzed = app(PayrollVarianceAnalysisService::class)->analyze($current);
        $this->assertSame('critical', $analyzed->variance_status);
        $this->assertSame(40, $analyzed->variance_findings[0]['details']['change_percent']);

        $this->actingAs($reviewer);
        try {
            app(ApprovePayrollPeriodAction::class)->execute($analyzed);
            $this->fail('Kritik fark incelemesi olmadan bordro onaylanamamalı.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $reviewed = app(ReviewPayrollVarianceAction::class)->execute($analyzed->fresh(), 'Ücret artışı insan tarafından doğrulandı.');
        $approved = app(ApprovePayrollPeriodAction::class)->execute($reviewed);
        $this->assertSame('approved', $approved->status);
        $this->assertSame($reviewer->id, $approved->variance_reviewed_by);
    }

    private function timesheetPeriod(int $tenantId, int $userId, string $name, string $start, string $end): HrTimesheetPeriod
    {
        return HrTimesheetPeriod::create([
            'legal_entity_id' => $tenantId,
            'name' => $name,
            'starts_on' => $start,
            'ends_on' => $end,
            'status' => 'closed',
            'closed_at' => now(),
            'closed_by' => $userId,
        ]);
    }

    private function record(HrPayrollPeriod $period, HrEmployee $employee, int $netCents, string $status): void
    {
        HrPayrollRecord::create([
            'legal_entity_id' => $period->legal_entity_id,
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'scheduled_minutes' => 6000,
            'worked_minutes' => 6000,
            'source_snapshot' => ['employee_id' => $employee->id],
            'source_hash' => hash('sha256', $period->id.'-'.$employee->id),
            'calculation_trace' => [
                'gross_pay_cents' => $netCents,
                'net_pay_cents' => $netCents,
                'employee_deductions_cents' => 0,
                'employer_total_cost_cents' => $netCents,
            ],
            'calculation_hash' => hash('sha256', 'calc-'.$period->id),
            'status' => $status,
        ]);
    }
}
