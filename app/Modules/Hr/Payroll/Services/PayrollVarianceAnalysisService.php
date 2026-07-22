<?php

namespace App\Modules\Hr\Payroll\Services;

use App\Modules\Hr\Payroll\Models\HrPayrollPeriod;

class PayrollVarianceAnalysisService
{
    private const WARNING_PERCENT = 10.0;
    private const CRITICAL_PERCENT = 25.0;

    public function analyze(HrPayrollPeriod $period): HrPayrollPeriod
    {
        $period->loadMissing(['records.employee', 'timesheetPeriod']);
        $previous = HrPayrollPeriod::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $period->legal_entity_id)
            ->whereKeyNot($period->id)
            ->whereIn('status', ['calculated', 'approved'])
            ->whereHas('timesheetPeriod', fn ($query) => $query->whereDate('ends_on', '<', $period->timesheetPeriod->starts_on))
            ->with(['records.employee', 'timesheetPeriod'])
            ->orderByDesc(
                \App\Modules\Hr\Timesheet\Models\HrTimesheetPeriod::select('ends_on')
                    ->whereColumn('hr_timesheet_periods.id', 'hr_payroll_periods.timesheet_period_id')
                    ->limit(1),
            )
            ->first();

        if (! $previous) {
            $period->update([
                'variance_status' => 'not_applicable',
                'variance_findings' => [],
                'variance_checked_at' => now(),
                'variance_reviewed_by' => null,
                'variance_reviewed_at' => null,
                'variance_review_note' => null,
            ]);

            return $period->fresh();
        }

        $findings = [];
        $previousRecords = $previous->records->keyBy('employee_id');
        $currentRecords = $period->records->keyBy('employee_id');
        $fields = [
            'gross_pay_cents' => 'Brüt ücret',
            'net_pay_cents' => 'Net ücret',
            'employee_deductions_cents' => 'Çalışan kesintileri',
            'employer_total_cost_cents' => 'İşveren toplam maliyeti',
        ];

        foreach ($currentRecords as $employeeId => $record) {
            $previousRecord = $previousRecords->get($employeeId);
            if (! $previousRecord) {
                $findings[] = $this->finding('new_payroll_employee', 'info', $employeeId, 'Çalışan önceki bordro döneminde bulunmuyor.');
                continue;
            }
            foreach ($fields as $field => $label) {
                $currentValue = (int) data_get($record->calculation_trace, $field, 0);
                $previousValue = (int) data_get($previousRecord->calculation_trace, $field, 0);
                $percentage = $this->percentageChange($previousValue, $currentValue);
                if ($percentage < self::WARNING_PERCENT) {
                    continue;
                }
                $severity = $percentage >= self::CRITICAL_PERCENT ? 'critical' : 'warning';
                $findings[] = $this->finding(
                    'payroll_value_variance',
                    $severity,
                    $employeeId,
                    "{$label} önceki döneme göre önemli ölçüde değişti.",
                    ['field' => $field, 'change_percent' => round($percentage, 2), 'direction' => $currentValue >= $previousValue ? 'increase' : 'decrease'],
                );
            }
            $overtimeDelta = abs($record->approved_overtime_minutes - $previousRecord->approved_overtime_minutes);
            if ($overtimeDelta >= 240) {
                $findings[] = $this->finding('overtime_variance', 'warning', $employeeId, 'Onaylı fazla mesai önceki döneme göre en az dört saat değişti.', ['delta_minutes' => $overtimeDelta]);
            }
        }

        foreach ($previousRecords->keys()->diff($currentRecords->keys()) as $employeeId) {
            $findings[] = $this->finding('employee_removed_from_payroll', 'warning', (int) $employeeId, 'Önceki dönemde bulunan çalışan bu bordro döneminde yok.');
        }

        $status = collect($findings)->contains(fn ($finding) => $finding['severity'] === 'critical')
            ? 'critical'
            : (collect($findings)->contains(fn ($finding) => $finding['severity'] === 'warning') ? 'warning' : 'passed');
        $period->update([
            'variance_status' => $status,
            'variance_findings' => $findings,
            'variance_checked_at' => now(),
            'variance_reviewed_by' => null,
            'variance_reviewed_at' => null,
            'variance_review_note' => null,
        ]);

        return $period->fresh();
    }

    private function percentageChange(int $previous, int $current): float
    {
        if ($previous === 0) {
            return $current === 0 ? 0.0 : 100.0;
        }

        return abs($current - $previous) / abs($previous) * 100;
    }

    private function finding(string $code, string $severity, int $employeeId, string $message, array $details = []): array
    {
        return [
            'code' => $code,
            'severity' => $severity,
            'employee_id' => $employeeId,
            'message' => $message,
            'details' => $details,
        ];
    }
}
