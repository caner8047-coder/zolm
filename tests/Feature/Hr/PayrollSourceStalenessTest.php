<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Payroll\Actions\CalculatePayrollPeriodAction;
use App\Modules\Hr\Payroll\Actions\PreparePayrollPeriodAction;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Timesheet\Actions\CreateTimesheetCorrectionAction;
use App\Modules\Hr\Timesheet\Models\HrTimesheet;
use App\Modules\Hr\Timesheet\Models\HrTimesheetPeriod;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PayrollSourceStalenessTest extends TestCase
{
    use RefreshHrDatabase;

    public function test_timesheet_correction_marks_prepared_payroll_stale_and_blocks_calculation(): void
    {
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $roleId = DB::table('roles')->where('slug', 'hr_admin')->value('id');
        $user = User::factory()->create(['role_id' => $roleId]);
        $this->actingAs($user);
        $tenant = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Kaynak Güncellik Testi',
            'tax_number' => '4747474747',
            'is_active' => true,
        ]);
        app(TenantContext::class)->set($tenant);
        $employee = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenant->id,
            'employee_number' => 'STALE01',
            'national_id_encrypted' => 'enc',
            'national_id_hash' => 'payroll-stale-test',
            'national_id_last_four' => '0001',
            'first_name' => 'Kaynak',
            'last_name' => 'Kontrolü',
            'status' => 'active',
        ]);
        $timesheetPeriod = HrTimesheetPeriod::create([
            'legal_entity_id' => $tenant->id,
            'name' => 'Kaynak Test Dönemi',
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-07-01',
            'status' => 'closed',
            'closed_at' => now(),
            'closed_by' => $user->id,
        ]);
        $timesheet = HrTimesheet::create([
            'legal_entity_id' => $tenant->id,
            'timesheet_period_id' => $timesheetPeriod->id,
            'employee_id' => $employee->id,
            'work_date' => '2026-07-01',
            'day_type' => 'workday',
            'scheduled_minutes' => 480,
            'worked_minutes' => 480,
            'status' => 'closed',
            'source_revision' => 1,
            'calculation_version' => 2,
        ]);
        $payrollPeriod = app(PreparePayrollPeriodAction::class)->execute($timesheetPeriod);
        $this->assertSame('fresh', $payrollPeriod->source_status);

        app(CreateTimesheetCorrectionAction::class)->execute($timesheet, [
            'worked_minutes' => 450,
            'missing_minutes' => 30,
        ], 'Eksik çıkış kaydı düzeltmesi');

        $stale = $payrollPeriod->fresh();
        $this->assertSame('stale', $stale->source_status);
        $this->assertSame('timesheet_corrected', $stale->source_stale_findings[0]['code']);

        try {
            app(CalculatePayrollPeriodAction::class)->execute($stale);
            $this->fail('Güncelliğini yitirmiş bordro kaynağı hesaplanamamalı.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
            $this->assertStringContainsString('kaynağı değişti', $exception->getMessage());
        }
    }
}
