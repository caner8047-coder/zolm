<?php

namespace App\Modules\Hr\Document\Jobs;

use App\Modules\Hr\Core\Jobs\HrJob;
use App\Modules\Hr\Document\Models\HrDocumentRequest;

class MarkOverdueDocumentRequestsJob extends HrJob
{
    public function execute(): void
    {
        HrDocumentRequest::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $this->tenantId)
            ->where('status', 'pending')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->update(['status' => 'overdue']);
    }
}
