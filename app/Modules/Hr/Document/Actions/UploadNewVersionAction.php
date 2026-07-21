<?php

namespace App\Modules\Hr\Document\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\HrFileService;
use App\Modules\Hr\Core\Services\MalwareScanner;
use App\Modules\Hr\Core\Services\ScanResult;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Document\Enums\DocumentStatus;
use App\Modules\Hr\Document\Enums\VerificationStatus;
use App\Modules\Hr\Document\Events\EmployeeDocumentUploaded;
use App\Modules\Hr\Document\Models\HrEmployeeDocument;
use App\Modules\Hr\Document\Models\HrEmployeeDocumentVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UploadNewVersionAction
{
    public function __construct(
        private HrFileService $fileService,
        private HrAuditService $auditService,
        private MalwareScanner $malwareScanner,
    ) {}

    public function execute(HrEmployeeDocument $document, UploadedFile $file, ?string $changeReason = null): HrEmployeeDocument
    {
        $tenantId = app(TenantContext::class)->getId();

        // Cross-tenant koruması: başka tüzel kişiliğe ait belgeye sürüm eklenemez.
        abort_unless($document->legal_entity_id === $tenantId, 422, 'Belge başka bir tüzel kişiliğe ait.');

        // Doğrulanmış belgenin üzerine yazılmaz; yeni versiyon oluşturulur.
        $hrFile = $this->fileService->upload($file, 'documents', $document->employee_id, HrEmployeeDocument::class);

        $absolutePath = Storage::disk(config('hr.file.disk', 'private'))->path($hrFile->disk_path);
        $scanResult = $this->malwareScanner->scan($absolutePath);

        if ($scanResult === ScanResult::Infected) {
            $this->fileService->delete($hrFile);
            abort(422, 'Yeni sürüm güvenlik taramasında şüpheli bulundu ve reddedildi.');
        }

        $failClosed = config('hr.malware_scanner.fail_closed');
        if ($failClosed === null) {
            $failClosed = app()->environment('production');
        }

        if (in_array($scanResult, [ScanResult::Unavailable, ScanResult::Error], true) && $failClosed) {
            $this->fileService->delete($hrFile);
            abort(422, 'Güvenlik tarayıcısı kullanılamıyor; yeni sürüm kabul edilemedi (fail-closed).');
        }

        $newVersion = $document->version_number + 1;

        $document = DB::transaction(function () use ($document, $hrFile, $changeReason, $newVersion, $scanResult) {
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
                'scan_result' => $scanResult->value,
            ]);

            return $document->fresh();
        });

        DB::afterCommit(function () use ($document) {
            event(new EmployeeDocumentUploaded(
                legalEntityId: $document->legal_entity_id,
                employeeDocumentId: $document->id,
                employeeId: $document->employee_id,
                actorUserId: auth()->id(),
                documentTypeId: $document->document_type_id,
            ));
        });

        return $document;
    }
}
