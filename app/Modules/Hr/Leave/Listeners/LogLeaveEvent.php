<?php

namespace App\Modules\Hr\Leave\Listeners;

use App\Modules\Hr\Core\Services\HrAuditService;

class LogLeaveEvent
{
    public function __construct(private HrAuditService $audit) {}

    public function handle(object $event): void
    {
        $action = match (class_basename($event)) {
            'LeaveRequested' => 'leave_requested_event',
            'LeaveApproved' => 'leave_approved_event',
            'LeaveRejected' => 'leave_rejected_event',
            'LeaveCancelled' => 'leave_cancelled_event',
            default => 'leave_event',
        };
        $this->audit->logEvent($action, null, ['legal_entity_id' => $event->legalEntityId, 'leave_request_id' => $event->leaveRequestId, 'employee_id' => $event->employeeId, 'actor_user_id' => $event->actorUserId]);
    }
}
