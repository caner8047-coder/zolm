<?php

namespace App\Modules\Hr\Document\Jobs;

use App\Modules\Hr\Core\Jobs\HrJob;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Document\Enums\DocumentStatus;
use App\Modules\Hr\Document\Events\EmployeeDocumentExpired;
use App\Modules\Hr\Document\Models\HrEmployeeDocument;
use Illuminate\Support\Facades\DB;

class MarkExpiredEmployeeDocumentsJob extends HrJob
{
    public function execute(): void
    {
        $expiredDocs = HrEmployeeDocument::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $this->tenantId)
            ->where('status', DocumentStatus::Active)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now())
            ->get();

        foreach ($expiredDocs as $doc) {
            DB::transaction(function () use ($doc) {
                $doc->update(['status' => DocumentStatus::Expired]);
                event(new EmployeeDocumentExpired(
                    legalEntityId: $doc->legal_entity_id,
                    employeeDocumentId: $doc->id,
                    employeeId: $doc->employee_id,
                ));
            });
        }
    }
}
