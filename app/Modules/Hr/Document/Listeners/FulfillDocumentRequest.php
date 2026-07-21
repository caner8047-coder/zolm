<?php

namespace App\Modules\Hr\Document\Listeners;

use App\Modules\Hr\Document\Events\EmployeeDocumentUploaded;
use App\Modules\Hr\Document\Models\HrDocumentRequest;

class FulfillDocumentRequest
{
    public function handle(EmployeeDocumentUploaded $event): void
    {
        // Yanlış belge türünün talebi kapatmasını engellemek için document_type_id filtresi.
        $pendingRequest = HrDocumentRequest::where('employee_id', $event->employeeId)
            ->where('status', 'pending')
            ->where('legal_entity_id', $event->legalEntityId)
            ->when($event->documentTypeId, fn($q) => $q->where('document_type_id', $event->documentTypeId))
            ->first();

        if (!$pendingRequest) {
            return;
        }

        $pendingRequest->update([
            'status' => 'fulfilled',
            'fulfilled_document_id' => $event->employeeDocumentId,
            'completed_at' => now(),
        ]);

        event(new \App\Modules\Hr\Document\Events\EmployeeDocumentRequestFulfilled(
            legalEntityId: $event->legalEntityId,
            documentRequestId: $pendingRequest->id,
            employeeId: $event->employeeId,
            employeeDocumentId: $event->employeeDocumentId,
        ));
    }
}
