<?php

namespace App\Modules\Hr\Payroll\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Overtime\Enums\OvertimeRequestStatus;
use App\Modules\Hr\Overtime\Models\HrOvertimeRequest;
use App\Modules\Hr\Payroll\Models\HrPayrollPeriod;
use App\Modules\Hr\Payroll\Models\HrPayrollRecord;
use App\Modules\Hr\Payroll\Models\HrPayrollRule;
use App\Modules\Hr\Payroll\Services\PayrollRuleConfiguration;
use App\Modules\Hr\Timesheet\Enums\TimesheetDayType;
use App\Modules\Hr\Timesheet\Enums\TimesheetPeriodStatus;
use App\Modules\Hr\Timesheet\Models\HrTimesheetPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PreparePayrollPeriodAction
{
    public function __construct(
        private HrAuditService $audit,
        private PayrollRuleConfiguration $configuration,
    ) {}

    public function execute(HrTimesheetPeriod $timesheetPeriod): HrPayrollPeriod
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.payroll.calculate'), 403);
        $tenantId = app(TenantContext::class)->getId();
        abort_unless($timesheetPeriod->legal_entity_id === $tenantId, 404);
        abort_unless(
            $timesheetPeriod->status === TimesheetPeriodStatus::Closed,
            422,
            'Yalnız kapanmış puantaj bordro hazırlığına aktarılabilir.',
        );

        $rows = $timesheetPeriod->timesheets()
            ->with(['employee', 'latestCorrection'])
            ->get()
            ->groupBy('employee_id');
        $approvedOvertime = HrOvertimeRequest::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->where('status', OvertimeRequestStatus::Approved->value)
            ->whereBetween('work_date', [$timesheetPeriod->starts_on, $timesheetPeriod->ends_on])
            ->get()
            ->groupBy('employee_id');
        $rules = $this->ruleSnapshots($tenantId, $timesheetPeriod);
        $snapshots = [];

        foreach ($rows as $employeeId => $employeeRows) {
            $approvedMinutes = $this->classifyApprovedOvertime(
                $employeeRows,
                $approvedOvertime->get($employeeId, collect()),
            );

            $snapshots[] = [
                'timesheet_period_id' => $timesheetPeriod->id,
                'employee_id' => (int) $employeeId,
                'scheduled_minutes' => $employeeRows->sum('scheduled_minutes'),
                'worked_minutes' => $employeeRows->sum(fn ($row) => (int) $row->effective('worked_minutes')),
                'requested_leave_minutes' => $employeeRows->sum('requested_leave_minutes'),
                'leave_minutes' => $employeeRows->sum(fn ($row) => (int) $row->effective('leave_minutes')),
                'overtime_minutes' => $employeeRows->sum(fn ($row) => (int) $row->effective('overtime_minutes')),
                'holiday_work_minutes' => $employeeRows->sum(fn ($row) => (int) $row->effective('holiday_work_minutes')),
                'weekly_rest_work_minutes' => $employeeRows->sum(fn ($row) => (int) $row->effective('weekly_rest_work_minutes')),
                'approved_overtime_minutes' => array_sum($approvedMinutes),
                'approved_regular_overtime_minutes' => $approvedMinutes['regular'],
                'approved_holiday_work_minutes' => $approvedMinutes['official_holiday'],
                'approved_weekly_rest_work_minutes' => $approvedMinutes['weekly_rest'],
                'missing_minutes' => $employeeRows->sum(fn ($row) => (int) $row->effective('missing_minutes')),
                'timesheet_revisions' => $employeeRows->mapWithKeys(
                    fn ($row) => [$row->id => $row->latestCorrection?->revision_number ?? 0],
                )->all(),
                'rule_versions' => $rules,
                'classification_version' => 2,
            ];
        }

        $sourceHash = $this->configuration->hash($snapshots);
        $period = DB::transaction(function () use ($tenantId, $timesheetPeriod, $snapshots, $sourceHash) {
            $period = HrPayrollPeriod::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $tenantId)
                ->where('timesheet_period_id', $timesheetPeriod->id)
                ->lockForUpdate()
                ->first();

            abort_if($period && $period->status === 'approved', 422, 'Onaylanmış bordro hazırlık dönemi değiştirilemez.');
            if ($period && $period->source_hash === $sourceHash) {
                return $period;
            }

            $period ??= HrPayrollPeriod::create([
                'legal_entity_id' => $tenantId,
                'timesheet_period_id' => $timesheetPeriod->id,
                'name' => $timesheetPeriod->name,
                'status' => 'draft',
            ]);
            $period->records()->delete();

            foreach ($snapshots as $snapshot) {
                HrPayrollRecord::create([
                    'legal_entity_id' => $tenantId,
                    'payroll_period_id' => $period->id,
                    'employee_id' => $snapshot['employee_id'],
                    'scheduled_minutes' => $snapshot['scheduled_minutes'],
                    'worked_minutes' => $snapshot['worked_minutes'],
                    'leave_minutes' => $snapshot['leave_minutes'],
                    'overtime_minutes' => $snapshot['overtime_minutes'],
                    'holiday_work_minutes' => $snapshot['holiday_work_minutes'],
                    'weekly_rest_work_minutes' => $snapshot['weekly_rest_work_minutes'],
                    'approved_overtime_minutes' => $snapshot['approved_overtime_minutes'],
                    'approved_regular_overtime_minutes' => $snapshot['approved_regular_overtime_minutes'],
                    'approved_holiday_work_minutes' => $snapshot['approved_holiday_work_minutes'],
                    'approved_weekly_rest_work_minutes' => $snapshot['approved_weekly_rest_work_minutes'],
                    'missing_minutes' => $snapshot['missing_minutes'],
                    'source_snapshot' => $snapshot,
                    'source_hash' => $this->configuration->hash($snapshot),
                ]);
            }

            $period->update([
                'status' => 'prepared',
                'source_hash' => $sourceHash,
                'source_status' => 'fresh',
                'source_stale_findings' => [],
                'source_checked_at' => now(),
                'prepared_at' => now(),
                'prepared_by' => auth()->id(),
                'calculated_at' => null,
                'calculated_by' => null,
                'calculation_hash' => null,
                'preflight_status' => 'pending',
                'preflight_findings' => null,
                'variance_status' => 'pending',
                'variance_findings' => null,
                'variance_checked_at' => null,
                'variance_reviewed_by' => null,
                'variance_reviewed_at' => null,
                'variance_review_note' => null,
                'output_preflight_status' => 'pending',
                'output_preflight_findings' => null,
                'output_preflight_hash' => null,
                'output_preflight_at' => null,
                'output_preflight_by' => null,
            ]);

            return $period;
        });

        $this->audit->log('payroll_period_prepared', $period, null, [
            'source_hash' => $sourceHash,
            'record_count' => count($snapshots),
            'classification_version' => 2,
        ]);

        return $period->fresh('records');
    }

    private function classifyApprovedOvertime(Collection $timesheets, Collection $requests): array
    {
        $classified = ['regular' => 0, 'official_holiday' => 0, 'weekly_rest' => 0];
        $timesheetsByDate = $timesheets->keyBy(fn ($row) => $row->work_date->toDateString());

        foreach ($requests->groupBy(fn ($request) => $request->work_date->toDateString()) as $date => $dateRequests) {
            $approved = (int) $dateRequests->sum('approved_minutes');
            $timesheet = $timesheetsByDate->get($date);
            $dayType = $timesheet?->day_type instanceof TimesheetDayType
                ? $timesheet->day_type
                : TimesheetDayType::tryFrom((string) ($timesheet?->day_type ?? ''));

            if ($dayType === TimesheetDayType::OfficialHoliday) {
                $classified['official_holiday'] += min($approved, (int) $timesheet->effective('holiday_work_minutes'));
            } elseif ($dayType === TimesheetDayType::WeeklyRest) {
                $classified['weekly_rest'] += min($approved, (int) $timesheet->effective('weekly_rest_work_minutes'));
            } else {
                $classified['regular'] += $approved;
            }
        }

        return $classified;
    }

    private function ruleSnapshots(int $tenantId, HrTimesheetPeriod $timesheetPeriod): array
    {
        return HrPayrollRule::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->whereIn('status', ['approved', 'superseded'])
            ->whereDate('effective_from', '<=', $timesheetPeriod->ends_on)
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $timesheetPeriod->starts_on))
            ->orderBy('code')
            ->orderByDesc('effective_from')
            ->orderByDesc('version')
            ->get()
            ->unique('code')
            ->map(fn ($rule) => [
                'id' => $rule->id,
                'code' => $rule->code,
                'version' => $rule->version,
                'configuration_hash' => $rule->configuration_hash
                    ?? hash('sha256', json_encode($rule->configuration, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)),
            ])
            ->values()
            ->all();
    }
}
