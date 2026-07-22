<?php

namespace App\Modules\Hr\Payroll\Services;

use App\Modules\Hr\Payroll\Models\HrPayrollEmployeeProfile;
use App\Modules\Hr\Payroll\Models\HrPayrollPeriod;
use App\Modules\Hr\Payroll\Models\HrPayrollRule;
use Carbon\CarbonInterface;

class PayrollSourceStalenessService
{
    public function markForTimesheetPeriod(int $tenantId, int $timesheetPeriodId, string $code, string $message, ?int $employeeId = null): void
    {
        $this->mark(
            HrPayrollPeriod::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $tenantId)
                ->where('timesheet_period_id', $timesheetPeriodId),
            $code,
            $message,
            $employeeId,
        );
    }

    public function markForWorkDate(int $tenantId, CarbonInterface|string $workDate, string $code, string $message, ?int $employeeId = null): void
    {
        $date = $workDate instanceof CarbonInterface ? $workDate->toDateString() : $workDate;
        $this->mark(
            HrPayrollPeriod::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $tenantId)
                ->whereHas('timesheetPeriod', fn ($query) => $query
                    ->whereDate('starts_on', '<=', $date)
                    ->whereDate('ends_on', '>=', $date)),
            $code,
            $message,
            $employeeId,
        );
    }

    public function markForRule(HrPayrollRule $rule): void
    {
        $this->mark(
            HrPayrollPeriod::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $rule->legal_entity_id)
                ->whereHas('timesheetPeriod', fn ($query) => $query
                    ->whereDate('ends_on', '>=', $rule->effective_from)
                    ->when($rule->effective_until, fn ($inner) => $inner->whereDate('starts_on', '<=', $rule->effective_until))),
            'payroll_rule_changed',
            'Dönemi etkileyen bordro kural sürümü değişti.',
        );
    }

    public function markForProfile(HrPayrollEmployeeProfile $profile): void
    {
        $this->mark(
            HrPayrollPeriod::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $profile->legal_entity_id)
                ->whereHas('records', fn ($query) => $query->where('employee_id', $profile->employee_id))
                ->whereHas('timesheetPeriod', fn ($query) => $query
                    ->whereDate('ends_on', '>=', $profile->effective_from)
                    ->when($profile->effective_until, fn ($inner) => $inner->whereDate('starts_on', '<=', $profile->effective_until))),
            'payroll_profile_changed',
            'Çalışanın bordro profili değişti.',
            $profile->employee_id,
        );
    }

    private function mark($query, string $code, string $message, ?int $employeeId = null): void
    {
        foreach ($query->get() as $period) {
            $finding = array_filter([
                'code' => $code,
                'message' => $message,
                'employee_id' => $employeeId,
                'detected_at' => now()->toIso8601String(),
            ], fn ($value) => $value !== null);
            $findings = collect($period->source_stale_findings ?? [])
                ->reject(fn ($item) => ($item['code'] ?? null) === $code && ($item['employee_id'] ?? null) === $employeeId)
                ->push($finding)
                ->values()
                ->all();
            $period->update([
                'source_status' => 'stale',
                'source_stale_findings' => $findings,
                'source_checked_at' => now(),
            ]);
        }
    }
}
