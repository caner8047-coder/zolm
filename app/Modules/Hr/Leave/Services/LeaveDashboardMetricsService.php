<?php

namespace App\Modules\Hr\Leave\Services;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Leave\Enums\LeaveRequestStatus;
use App\Modules\Hr\Leave\Models\HrLeaveBalance;
use App\Modules\Hr\Leave\Models\HrLeaveRequest;

class LeaveDashboardMetricsService
{
    public function getMetrics(): array
    {
        $tenantId = app(TenantContext::class)->getId();
        $requests = HrLeaveRequest::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId);

        return [
            'pending_approval' => (clone $requests)->whereIn('status', [LeaveRequestStatus::PendingManager->value, LeaveRequestStatus::PendingHr->value])->count(),
            'upcoming_approved' => (clone $requests)->where('status', LeaveRequestStatus::Approved->value)->whereDate('end_date', '>=', today()->toDateString())->count(),
            'today_approved' => (clone $requests)->where('status', LeaveRequestStatus::Approved->value)->whereDate('start_date', '<=', today()->toDateString())->whereDate('end_date', '>=', today()->toDateString())->count(),
            'negative_balances' => HrLeaveBalance::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('remaining_amount', '<', 0)->count(),
        ];
    }
}
