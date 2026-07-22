<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Overtime\Models\HrOvertimeRequest;
use App\Modules\Hr\Overtime\Models\HrOvertimeType;
use App\Modules\Hr\Payroll\Actions\PreparePayrollPeriodAction;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Timesheet\Models\HrTimesheet;
use App\Modules\Hr\Timesheet\Models\HrTimesheetPeriod;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PayrollSpecialWorkClassificationTest extends TestCase
{
    use RefreshHrDatabase;

    public function test_approved_overtime_is_classified_by_timesheet_day_type(): void
    {
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $roleId = DB::table('roles')->where('slug', 'hr_admin')->value('id');
        $user = User::factory()->create(['role_id' => $roleId]);
        $this->actingAs($user);
        $tenant = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Özel Çalışma Testi',
            'tax_number' => '5656565656',
            'is_active' => true,
        ]);
        app(TenantContext::class)->set($tenant);
        $employee = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenant->id,
            'employee_number' => 'SPECIAL01',
            'national_id_encrypted' => 'enc',
            'national_id_hash' => 'special-work-test',
            'national_id_last_four' => '0001',
            'first_name' => 'Özel',
            'last_name' => 'Çalışma',
            'status' => 'active',
        ]);
        $period = HrTimesheetPeriod::create([
            'legal_entity_id' => $tenant->id,
            'name' => 'Temmuz 2026',
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-07-03',
            'status' => 'closed',
            'closed_at' => now(),
            'closed_by' => $user->id,
        ]);

        $this->timesheet($period, $employee, '2026-07-01', 'workday', 480, 540, 60, 0, 0);
        $this->timesheet($period, $employee, '2026-07-02', 'official_holiday', 0, 480, 0, 480, 0);
        $this->timesheet($period, $employee, '2026-07-03', 'weekly_rest', 0, 480, 0, 0, 480);

        $type = HrOvertimeType::create([
            'legal_entity_id' => $tenant->id,
            'code' => 'APPROVED',
            'name' => 'Onaylı Çalışma',
            'multiplier' => 1.5,
            'requires_approval' => true,
            'is_active' => true,
        ]);
        foreach ([
            ['2026-07-01', 60],
            ['2026-07-02', 480],
            ['2026-07-03', 480],
        ] as [$date, $minutes]) {
            HrOvertimeRequest::create([
                'legal_entity_id' => $tenant->id,
                'employee_id' => $employee->id,
                'overtime_type_id' => $type->id,
                'work_date' => $date,
                'starts_at' => '09:00',
                'ends_at' => '18:00',
                'requested_minutes' => $minutes,
                'approved_minutes' => $minutes,
                'status' => 'approved',
                'reason' => 'Sınıflandırma testi',
                'requested_by' => $user->id,
                'decided_by' => $user->id,
                'decided_at' => now(),
            ]);
        }

        $record = app(PreparePayrollPeriodAction::class)->execute($period)->records->firstOrFail();

        $this->assertSame(60, $record->approved_regular_overtime_minutes);
        $this->assertSame(480, $record->approved_holiday_work_minutes);
        $this->assertSame(480, $record->approved_weekly_rest_work_minutes);
        $this->assertSame(1020, $record->approved_overtime_minutes);
        $this->assertSame(2, $record->source_snapshot['classification_version']);
    }

    private function timesheet(
        HrTimesheetPeriod $period,
        HrEmployee $employee,
        string $date,
        string $dayType,
        int $scheduled,
        int $worked,
        int $overtime,
        int $holidayWork,
        int $weeklyRestWork,
    ): void {
        HrTimesheet::create([
            'legal_entity_id' => $period->legal_entity_id,
            'timesheet_period_id' => $period->id,
            'employee_id' => $employee->id,
            'work_date' => $date,
            'day_type' => $dayType,
            'scheduled_minutes' => $scheduled,
            'worked_minutes' => $worked,
            'overtime_minutes' => $overtime,
            'holiday_work_minutes' => $holidayWork,
            'weekly_rest_work_minutes' => $weeklyRestWork,
            'status' => 'closed',
            'source_revision' => 1,
            'calculation_version' => 2,
        ]);
    }
}
