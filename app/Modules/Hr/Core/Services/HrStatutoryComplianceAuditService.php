<?php

namespace App\Modules\Hr\Core\Services;

use App\Modules\Hr\Overtime\Models\HrOvertimeRequest;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Safety\Models\HrSafetyIncident;
use App\Modules\Hr\Shift\Models\HrShiftAssignment;

class HrStatutoryComplianceAuditService
{
    public function runFullComplianceAudit(int $tenantId): array
    {
        return [
            'overtime_warnings' => $this->checkAnnualOvertimeLimit($tenantId),
            'night_shift_warnings' => $this->checkNightShiftDuration($tenantId),
            'safety_incident_warnings' => $this->checkWorkplaceAccidentSgkDeadline($tenantId),
            'quota_warnings' => $this->checkDisabledEmploymentQuota($tenantId),
        ];
    }

    /**
     * 4857 SK m.41: Yıllık 270 saat (16,200 dakika) fazla mesai sınırı kontrolü
     */
    public function checkAnnualOvertimeLimit(int $tenantId): array
    {
        $currentYear = (int) date('Y');
        $overtimes = HrOvertimeRequest::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->where('status', 'approved')
            ->whereYear('work_date', $currentYear)
            ->get()
            ->groupBy('employee_id');

        $warnings = [];
        foreach ($overtimes as $empId => $requests) {
            $totalMinutes = $requests->sum('approved_minutes');
            $hours = round($totalMinutes / 60, 1);

            if ($hours >= 240) { // 240 saat kritik eşik, 270 saat yasal limit
                $emp = HrEmployee::withoutGlobalScope('tenant')->find($empId);
                $isExceeded = $hours >= 270;

                $warnings[] = [
                    'employee_id' => $empId,
                    'employee_name' => $emp ? "{$emp->first_name} {$emp->last_name}" : "Çalışan #{$empId}",
                    'employee_number' => $emp?->employee_number ?? '',
                    'total_hours' => $hours,
                    'is_exceeded' => $isExceeded,
                    'severity' => $isExceeded ? 'critical' : 'warning',
                    'message' => $isExceeded
                        ? "4857 SK m.41 uyarınca yıllık 270 saatlik yasal fazla mesai sınırı aşıldı! (Toplam: {$hours} saat)"
                        : "Yıllık 270 saatlik yasal fazla mesai sınırına yaklaşıldı. (Toplam: {$hours} saat)",
                ];
            }
        }

        return $warnings;
    }

    /**
     * 4857 SK m.69: Gece vardiyası 7.5 saat (450 dakika) sınırı kontrolü
     */
    public function checkNightShiftDuration(int $tenantId): array
    {
        $assignments = HrShiftAssignment::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->where('shift_date', '>=', now()->subDays(7)->toDateString())
            ->with(['employee', 'template'])
            ->get();

        $warnings = [];
        foreach ($assignments as $assignment) {
            $template = $assignment->template;
            if (! $template) continue;

            $startsAt = strtotime($template->starts_at);
            $endsAt = strtotime($template->ends_at);
            if ($endsAt <= $startsAt) {
                $endsAt += 86400; // Geceye sarkan vardiya
            }

            $durationMinutes = max(0, ($endsAt - $startsAt) / 60 - ($template->break_minutes ?? 0));

            // Gece çalışma saatleri (20:00 - 06:00 arası)
            $isNightShift = (date('H', $startsAt) >= 20 || date('H', $startsAt) <= 5);

            if ($isNightShift && $durationMinutes > 450) { // 7.5 saat = 450 dk
                $emp = $assignment->employee;
                $hours = round($durationMinutes / 60, 1);

                $warnings[] = [
                    'assignment_id' => $assignment->id,
                    'employee_name' => $emp ? "{$emp->first_name} {$emp->last_name}" : "Çalışan",
                    'shift_date' => $assignment->shift_date->format('d.m.Y'),
                    'duration_hours' => $hours,
                    'severity' => 'warning',
                    'message' => "4857 SK m.69 uyarınca gece vardiyası 7.5 saati geçemez! ({$assignment->shift_date->format('d.m.Y')} vardiyası: {$hours} saat)",
                ];
            }
        }

        return $warnings;
    }

    /**
     * 6331 SK & SGK: İş Kazası SGK'ya 3 iş günü içinde bildirilmelidir
     */
    public function checkWorkplaceAccidentSgkDeadline(int $tenantId): array
    {
        $incidents = HrSafetyIncident::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->where('status', 'under_investigation')
            ->get();

        $warnings = [];
        foreach ($incidents as $inc) {
            $occurredAt = $inc->occurred_at;
            $daysPassed = $occurredAt ? (int) $occurredAt->diffInDays(now()) : 0;

            if ($daysPassed >= 2) {
                $warnings[] = [
                    'incident_id' => $inc->id,
                    'incident_number' => $inc->incident_number,
                    'occurred_at' => $occurredAt?->format('d.m.Y H:i'),
                    'days_passed' => $daysPassed,
                    'severity' => $daysPassed >= 3 ? 'critical' : 'warning',
                    'message' => $daysPassed >= 3
                        ? "6331 SK uyarınca İş Kazası SGK 3 iş günü bildirim süresi doldu! ({$inc->incident_number})"
                        : "İş kazası SGK bildirimi için son gün! ({$inc->incident_number})",
                ];
            }
        }

        return $warnings;
    }

    /**
     * 4857 SK m.30: 50 ve üzeri çalışanı olan işyerlerinde %3 engelli çalıştırma kotası kontrolü
     */
    public function checkDisabledEmploymentQuota(int $tenantId): array
    {
        $activeEmployeesCount = HrEmployee::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->where('status', 'active')
            ->count();

        if ($activeEmployeesCount < 50) {
            return [];
        }

        $requiredQuota = (int) ceil($activeEmployeesCount * 0.03);

        return [
            [
                'active_employees' => $activeEmployeesCount,
                'required_quota' => $requiredQuota,
                'severity' => 'info',
                'message' => "50 ve üzeri çalışan barajı nedeniyle 4857 SK m.30 uyarınca en az {$requiredQuota} engelli personel istihdam kotası bulunmaktadır.",
            ],
        ];
    }
}
