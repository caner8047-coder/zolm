<?php

namespace App\Modules\Hr\Document\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Document\Enums\DocumentStatus;
use App\Modules\Hr\Document\Enums\VerificationStatus;
use App\Modules\Hr\Document\Models\HrEmployeeDocument;
use Illuminate\Support\Facades\DB;

class VerifyDocumentAction
{
    public function __construct(
        private HrAuditService $auditService
    ) {}

    public function verify(HrEmployeeDocument $document, ?string $comment = null): HrEmployeeDocument
    {
        return DB::transaction(function () use ($document, $comment) {
            $document->update([
                'verification_status' => VerificationStatus::Verified,
                'status' => DocumentStatus::Active,
                'verified_by' => auth()->id(),
                'verified_at' => now(),
                'notes' => $comment ?? $document->notes,
            ]);

            $this->auditService->log('document_verified', $document);

            return $document->fresh();
        });
    }

    public function reject(HrEmployeeDocument $document, string $reason): HrEmployeeDocument
    {
        if (empty($reason)) {
            abort(422, 'Ret gerekçesi zorunludur.');
        }

        return DB::transaction(function () use ($document, $reason) {
            $document->update([
                'verification_status' => VerificationStatus::Rejected,
                'status' => DocumentStatus::Rejected,
                'rejection_reason' => $reason,
            ]);

            $this->auditService->log('document_rejected', $document, null, ['reason' => $reason]);

            return $document->fresh();
        });
    }
}
