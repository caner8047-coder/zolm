<?php

namespace App\Modules\Hr\Document\Jobs;

use App\Models\ActivityLog;
use App\Modules\Hr\Core\Jobs\HrJob;
use App\Modules\Hr\Document\Models\HrDocumentRequest;
use Carbon\Carbon;

class SendPendingDocumentRequestRemindersJob extends HrJob
{
    public function execute(): void
    {
        $today = Carbon::today()->toDateString();
        $batchReference = "doc_request_reminders:{$this->tenantId}:{$today}";

        // Idempotency: aynı gün bu tenant için yalnızca bir toplu hatırlatma turu.
        if (ActivityLog::where('action', 'document_request_reminders')
            ->where('description', $batchReference)
            ->exists()
        ) {
            return;
        }

        $requests = HrDocumentRequest::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $this->tenantId)
            ->whereIn('status', ['pending', 'overdue'])
            ->with('employee', 'documentType')
            ->get();

        foreach ($requests as $request) {
            ActivityLog::create([
                'user_id' => null,
                'legal_entity_id' => $request->legal_entity_id,
                'action' => 'document_request_reminder',
                'entity_type' => HrDocumentRequest::class,
                'entity_id' => $request->id,
                'description' => "doc_request_reminder:{$request->id}:{$today}",
                'metadata' => [
                    'module' => 'hr',
                    'employee_id' => $request->employee_id,
                    'document_type_id' => $request->document_type_id,
                    'status' => $request->status,
                    'due_date' => $request->due_date?->toDateString(),
                ],
                'ip_address' => null,
                'user_agent' => null,
            ]);
        }

        // Günlük toplu çalıştırma damgası (idempotency için).
        ActivityLog::create([
            'user_id' => null,
            'legal_entity_id' => $this->tenantId,
            'action' => 'document_request_reminders',
            'entity_type' => null,
            'entity_id' => null,
            'description' => $batchReference,
            'metadata' => ['module' => 'hr', 'count' => $requests->count()],
            'ip_address' => null,
            'user_agent' => null,
        ]);
    }
}
