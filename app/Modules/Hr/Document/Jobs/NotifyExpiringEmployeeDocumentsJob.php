<?php

namespace App\Modules\Hr\Document\Jobs;

use App\Models\ActivityLog;
use App\Modules\Hr\Core\Jobs\HrJob;
use App\Modules\Hr\Document\Enums\DocumentStatus;
use App\Modules\Hr\Document\Models\HrEmployeeDocument;
use Carbon\Carbon;

class NotifyExpiringEmployeeDocumentsJob extends HrJob
{
    private array $reminderDays = [30, 15, 7, 1];

    public function execute(): void
    {
        $today = Carbon::today();

        foreach ($this->reminderDays as $days) {
            $targetDate = $today->copy()->addDays($days)->toDateString();

            $docs = HrEmployeeDocument::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $this->tenantId)
                ->where('status', DocumentStatus::Active)
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', $targetDate)
                ->with('employee', 'documentType')
                ->get();

            foreach ($docs as $doc) {
                $this->dispatchReminder($doc, $days);
            }
        }
    }

    private function dispatchReminder(HrEmployeeDocument $doc, int $days): void
    {
        $reference = "doc_expiry_reminder:{$doc->id}:{$days}";

        // Idempotency: aynı belge + aynı hatırlatma günü için yalnızca bir kayıt.
        if (ActivityLog::where('action', 'document_expiry_reminder')
            ->where('description', $reference)
            ->exists()
        ) {
            return;
        }

        ActivityLog::create([
            'user_id' => null,
            'legal_entity_id' => $doc->legal_entity_id,
            'action' => 'document_expiry_reminder',
            'entity_type' => HrEmployeeDocument::class,
            'entity_id' => $doc->id,
            'description' => $reference,
            'metadata' => [
                'module' => 'hr',
                'employee_id' => $doc->employee_id,
                'document_type_id' => $doc->document_type_id,
                'days_before_expiry' => $days,
                'expiry_date' => $doc->expiry_date?->toDateString(),
            ],
            'ip_address' => null,
            'user_agent' => null,
        ]);
    }
}
