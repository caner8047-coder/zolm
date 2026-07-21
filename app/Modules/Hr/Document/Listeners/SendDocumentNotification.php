<?php

namespace App\Modules\Hr\Document\Listeners;

use App\Models\ActivityLog;
use App\Modules\Hr\Document\Events\EmployeeDocumentRequested;
use App\Modules\Hr\Document\Events\EmployeeDocumentRequestFulfilled;
use App\Modules\Hr\Document\Models\HrDocumentRequest;
use App\Modules\Hr\Document\Models\HrEmployeeDocument;

class SendDocumentNotification
{
    public function handle(object $event): void
    {
        $legalEntityId = $event->legalEntityId ?? null;
        if (!$legalEntityId) {
            return;
        }

        $reference = $this->reference($event);
        $employeeId = $event->employeeId ?? null;
        $entityId = $event->employeeDocumentId ?? ($event->documentRequestId ?? null);
        $actorUserId = property_exists($event, 'actorUserId') ? $event->actorUserId : null;

        // Idempotency: aynı olay + aynı varlık için yalnızca bir bildirim kaydı.
        $alreadyDispatched = ActivityLog::where('action', 'document_notification_dispatched')
            ->where('description', $reference)
            ->exists();

        if ($alreadyDispatched) {
            return;
        }

        // Bildirim niyeti audit'e yazılır. Gerçek dış kanal gönderimi Faz 1C'de HR
        // notification altyapısı ile yapılacak; burada kalıcı, dedup kayıt tutulur.
        ActivityLog::create([
            'user_id' => $actorUserId ?? auth()->id(),
            'legal_entity_id' => $legalEntityId,
            'action' => 'document_notification_dispatched',
            'entity_type' => $entityId ? $this->entityType($event) : null,
            'entity_id' => $entityId,
            'description' => $reference,
            'metadata' => [
                'module' => 'hr',
                'event' => get_class($event),
                'employee_id' => $employeeId,
                'channel' => 'internal_log',
            ],
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }

    private function reference(object $event): string
    {
        $type = class_basename($event);
        $id = $event->employeeDocumentId ?? ($event->documentRequestId ?? null);

        return "doc_notif:{$type}:{$id}";
    }

    private function entityType(object $event): string
    {
        return match (true) {
            $event instanceof EmployeeDocumentRequested,
            $event instanceof EmployeeDocumentRequestFulfilled => HrDocumentRequest::class,
            default => HrEmployeeDocument::class,
        };
    }
}
