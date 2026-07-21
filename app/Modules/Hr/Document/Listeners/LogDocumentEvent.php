<?php

namespace App\Modules\Hr\Document\Listeners;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Document\Events\EmployeeDocumentUploaded;
use App\Modules\Hr\Document\Events\EmployeeDocumentVerified;
use App\Modules\Hr\Document\Events\EmployeeDocumentRejected;
use App\Modules\Hr\Document\Events\EmployeeDocumentExpired;

class LogDocumentEvent
{
    public function __construct(
        private HrAuditService $auditService
    ) {}

    public function handle(object $event): void
    {
        $action = match (get_class($event)) {
            EmployeeDocumentUploaded::class => 'document_uploaded',
            EmployeeDocumentVerified::class => 'document_verified',
            EmployeeDocumentRejected::class => 'document_rejected',
            EmployeeDocumentExpired::class => 'document_expired',
            default => 'document_event',
        };

        $metadata = ['employee_id' => $event->employeeId];
        if (property_exists($event, 'reason')) {
            $metadata['reason'] = $event->reason;
        }

        $this->auditService->logEvent($action, null, $metadata);
    }
}
