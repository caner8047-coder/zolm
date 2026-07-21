<?php

namespace App\Modules\Hr\Document\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\HrFileService;
use App\Modules\Hr\Document\Enums\DocumentStatus;
use App\Modules\Hr\Document\Enums\VerificationStatus;
use App\Modules\Hr\Document\Models\HrEmployeeDocument;
use App\Modules\Hr\Document\Models\HrEmployeeDocumentVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class UploadNewVersionAction
{
    public function __construct(
        private HrFileService $fileService,
        private HrAuditService $auditService
    ) {}

    public function execute(HrEmployeeDocument $document, UploadedFile $file, ?string $changeReason = null): HrEmployeeDocument
    {
        return DB::transaction(function () use ($document, $file, $changeReason) {
            // Doğrulanmış belgenin üzerine yazılmaz; yeni versiyon oluşturulur
            $newVersion = $document->version_number + 1;

            $hrFile = $this->fileService->upload($file, 'documents', $document->employee_id, HrEmployeeDocument::class);

            $document->update([
                'current_file_id' => $hrFile->id,
                'version_number' => $newVersion,
                'status' => DocumentStatus::Uploaded,
                'verification_status' => VerificationStatus::Pending,
                'verified_by' => null,
                'verified_at' => null,
                'updated_by' => auth()->id(),
            ]);

            HrEmployeeDocumentVersion::create([
                'employee_document_id' => $document->id,
                'file_id' => $hrFile->id,
                'version_number' => $newVersion,
                'uploaded_by' => auth()->id(),
                'change_reason' => $changeReason,
                'created_at' => now(),
            ]);

            $this->auditService->log('document_new_version', $document, null, [
                'version' => $newVersion,
                'change_reason' => $changeReason,
            ]);

            return $document->fresh();
        });
    }
}
