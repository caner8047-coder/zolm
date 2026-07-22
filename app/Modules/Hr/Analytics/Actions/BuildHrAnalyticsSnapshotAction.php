<?php

namespace App\Modules\Hr\Analytics\Actions;

use App\Modules\Hr\Analytics\Models\HrAnalyticsSnapshot;
use App\Modules\Hr\Attendance\Models\HrAttendanceAnomaly;
use App\Modules\Hr\Compensation\Models\HrEmployeeBenefit;
use App\Modules\Hr\Compensation\Models\HrSalaryRecord;
use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Leave\Enums\LeaveRequestStatus;
use App\Modules\Hr\Leave\Models\HrLeaveRequest;
use App\Modules\Hr\Payroll\Models\HrPayrollPeriod;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Personnel\Models\HrEmploymentRecord;
use App\Modules\Hr\Recruitment\Models\HrJobPosting;
use App\Modules\Hr\Training\Models\HrTrainingEnrollment;
use Carbon\Carbon;

class BuildHrAnalyticsSnapshotAction
{
    public function __construct(private HrAuditService $audit) {}

    public function execute(string $from, string $to): HrAnalyticsSnapshot
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.analytics.view'), 403);

        $tenantId = app(TenantContext::class)->getId();
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();
        abort_if($end->lt($start) || $start->diffInDays($end) > 730, 422, 'Analiz dönemi geçersiz.');

        $activeEmployeeIds = HrEmployee::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->where('status', 'active')
            ->pluck('id');
        $headcount = $activeEmployeeIds->count();

        $hires = HrEmploymentRecord::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->whereBetween('start_date', [$start, $end])
            ->count();
        $exits = HrEmploymentRecord::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->whereBetween('end_date', [$start, $end])
            ->count();
        $leave = (float) HrLeaveRequest::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->where('status', LeaveRequestStatus::Approved->value)
            ->whereBetween('start_date', [$start, $end])
            ->sum('requested_amount');
        $anomalies = HrAttendanceAnomaly::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->whereBetween('work_date', [$start, $end])
            ->count();
        $training = HrTrainingEnrollment::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$start, $end])
            ->count();
        $openRoles = (int) HrJobPosting::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->where('status', 'published')
            ->sum('headcount');

        $salaryCost = null;
        $benefitCost = null;
        $averageGrossSalary = null;
        $salaryCoverage = null;
        if (auth()->user()?->hasHrPermission('hr.salary.view')) {
            $salaryRecords = HrSalaryRecord::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $tenantId)
                ->whereIn('employee_id', $activeEmployeeIds)
                ->whereIn('status', ['approved', 'superseded'])
                ->whereDate('effective_from', '<=', $end)
                ->orderByDesc('effective_from')
                ->orderByDesc('version')
                ->get()
                ->unique('employee_id');
            $salaryCost = round($salaryRecords->sum(fn (HrSalaryRecord $record) => $record->grossSalary()), 2);
            $salaryCoverage = $salaryRecords->count();
            $averageGrossSalary = $salaryCoverage > 0 ? round($salaryCost / $salaryCoverage, 2) : 0.0;

            $benefitCost = round(HrEmployeeBenefit::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $tenantId)
                ->whereIn('employee_id', $activeEmployeeIds)
                ->where('status', 'active')
                ->whereDate('starts_on', '<=', $end)
                ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $start))
                ->with('benefit')
                ->get()
                ->sum(fn (HrEmployeeBenefit $row) => (float) ($row->benefit?->employer_cost_encrypted ?? 0)), 2);
        }

        $latestPayroll = HrPayrollPeriod::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->whereIn('status', ['calculated', 'approved'])
            ->where('preflight_status', 'passed')
            ->with('records')
            ->latest('calculated_at')
            ->first();
        $payrollEmployerCost = $latestPayroll?->records->sum(
            fn ($record) => (float) data_get($record->calculation_trace, 'employer_total_cost_cents', 0) / 100
        );

        $metrics = [
            'headcount' => $headcount,
            'hires' => $hires,
            'exits' => $exits,
            'turnover_rate' => $headcount > 0 ? round($exits / $headcount * 100, 1) : 0.0,
            'approved_leave_units' => $leave,
            'attendance_anomalies' => $anomalies,
            'training_completions' => $training,
            'open_headcount' => $openRoles,
            'monthly_gross_cost' => $salaryCost,
            'monthly_benefit_cost' => $benefitCost,
            'average_gross_salary' => $averageGrossSalary,
            'salary_coverage' => $salaryCoverage,
            'latest_payroll_employer_cost' => $payrollEmployerCost,
        ];
        $sources = [
            'headcount' => 'hr_employees.status',
            'hires_exits' => 'hr_employment_records.start_date/end_date',
            'leave' => 'hr_leave_requests.requested_amount (approved)',
            'attendance' => 'hr_attendance_anomalies.work_date',
            'training' => 'hr_training_enrollments.completed_at',
            'recruitment' => 'hr_job_postings.headcount (published)',
            'salary' => $salaryCost === null ? 'hidden: hr.salary.view required' : 'hr_salary_records latest effective record per employee',
            'benefits' => $benefitCost === null ? 'hidden: hr.salary.view required' : 'hr_employee_benefits + hr_benefits employer cost',
            'payroll' => 'latest preflight-passed hr_payroll_period + calculation_trace',
        ];
        $hash = hash('sha256', json_encode([$tenantId, $start->toDateString(), $end->toDateString(), $metrics, $sources], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        $snapshot = HrAnalyticsSnapshot::withoutGlobalScope('tenant')->firstOrCreate(
            ['legal_entity_id' => $tenantId, 'source_hash' => $hash],
            [
                'period_start' => $start,
                'period_end' => $end,
                'metrics' => $metrics,
                'sources' => $sources,
                'generated_by' => auth()->id(),
                'generated_at' => now(),
            ]
        );

        if ($snapshot->wasRecentlyCreated) {
            $this->audit->log('hr_analytics_snapshot_generated', $snapshot, null, ['source_hash' => $hash]);
        }

        return $snapshot;
    }
}
