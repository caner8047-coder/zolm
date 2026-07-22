<?php

namespace App\Modules\Hr\Leave\Services;

use App\Modules\Hr\Leave\Models\HrLeaveBalance;
use App\Modules\Hr\Leave\Models\HrLeaveType;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Support\Carbon;

class HrAnnualLeaveAccrualService
{
    /**
     * 4857 SK m.53 uyarınca kıdeme ve yaşa göre yıllık izin hak ediş gün sayısını hesaplar
     */
    public function calculateEntitlementDays(Carbon|string $startDate, Carbon|string $dateOfBirth): int
    {
        $start = Carbon::parse($startDate);
        $dob = Carbon::parse($dateOfBirth);

        $tenureYears = (int) $start->diffInYears(now());
        $age = (int) $dob->diffInYears(now());

        if ($tenureYears < 1) {
            return 0; // 1 yıldan az kıdemi olanlar hak kazanamaz
        }

        $days = match (true) {
            $tenureYears < 5 => 14,
            $tenureYears < 15 => 20,
            default => 26,
        };

        // 18 yaş ve altı veya 50 yaş ve üzeri çalışanlar için yasal taban 20 gündür
        if (($age <= 18 || $age >= 50) && $days < 20) {
            $days = 20;
        }

        return $days;
    }

    /**
     * Tüm aktif çalışanların yıllık izin hak ediş bakiyelerini günceller
     */
    public function accrueAllEmployees(int $tenantId): array
    {
        $currentYear = (int) date('Y');
        try {
            $annualType = HrLeaveType::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $tenantId)
                ->where('code', 'ANNUAL')
                ->first();

            if (! $annualType) {
                return ['processed' => 0, 'updated' => 0];
            }

            $employees = HrEmployee::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $tenantId)
                ->where('status', 'active')
                ->with('activeEmployment')
                ->get();
        } catch (\Throwable $e) {
            return ['processed' => 0, 'updated' => 0];
        }

        $updatedCount = 0;
        foreach ($employees as $emp) {
            $employment = $emp->activeEmployment;
            if (! $employment || ! $employment->start_date) continue;

            $entitledDays = $this->calculateEntitlementDays($employment->start_date, $emp->date_of_birth ?? '1990-01-01');
            if ($entitledDays <= 0) continue;

            $balance = HrLeaveBalance::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $tenantId)
                ->where('employee_id', $emp->id)
                ->where('leave_type_id', $annualType->id)
                ->where('period_year', $currentYear)
                ->first();

            if ($balance) {
                $balance->entitled_amount = (float) $entitledDays;
                $balance->remaining_amount = max(0, ($entitledDays + $balance->carried_amount + $balance->adjustment_amount) - $balance->used_amount);
                $balance->save();
            } else {
                HrLeaveBalance::withoutGlobalScope('tenant')->create([
                    'legal_entity_id' => $tenantId,
                    'employee_id' => $emp->id,
                    'leave_type_id' => $annualType->id,
                    'period_year' => $currentYear,
                    'entitled_amount' => (float) $entitledDays,
                    'carried_amount' => 0.0,
                    'used_amount' => 0.0,
                    'adjustment_amount' => 0.0,
                    'remaining_amount' => (float) $entitledDays,
                ]);
            }
            $updatedCount++;
        }

        return ['processed' => $employees->count(), 'updated' => $updatedCount];
    }
}
