<?php

namespace App\Modules\Hr\Leave\Services;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Leave\Enums\LeavePolicyScope;
use App\Modules\Hr\Leave\Models\HrLeavePolicy;
use App\Modules\Hr\Leave\Models\HrLeaveType;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Carbon\CarbonInterface;

class LeavePolicyResolver
{
    public function resolve(HrEmployee $employee, HrLeaveType $leaveType, ?CarbonInterface $date = null): ?HrLeavePolicy
    {
        $tenantId = app(TenantContext::class)->getId();
        abort_unless($employee->legal_entity_id === $tenantId && $leaveType->legal_entity_id === $tenantId, 422, 'Çalışan veya izin türü başka bir tüzel kişiliğe ait.');

        $employment = $employee->activeEmployment;
        if (!$employment) {
            return null;
        }

        $date ??= now();
        $policies = HrLeavePolicy::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->where('leave_type_id', $leaveType->id)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $date->toDateString())
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $date->toDateString());
            })
            ->get();

        return $policies
            ->filter(function (HrLeavePolicy $policy) use ($employment) {
                return match ($policy->scope) {
                    LeavePolicyScope::Company => true,
                    LeavePolicyScope::Branch => $policy->branch_id === $employment->branch_id,
                    LeavePolicyScope::Department => $policy->department_id === $employment->department_id,
                    LeavePolicyScope::Position => $policy->position_id === $employment->position_id,
                    LeavePolicyScope::EmploymentType => $policy->employment_type === $employment->employment_type?->value,
                };
            })
            ->sortByDesc(fn (HrLeavePolicy $policy) => $this->priority($policy->scope))
            ->first();
    }

    private function priority(LeavePolicyScope $scope): int
    {
        return match ($scope) {
            LeavePolicyScope::Company => 1,
            LeavePolicyScope::Branch => 2,
            LeavePolicyScope::Department => 3,
            LeavePolicyScope::EmploymentType => 4,
            LeavePolicyScope::Position => 5,
        };
    }
}
