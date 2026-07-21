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
use App\Modules\Hr\Document\Models\HrDocumentType;
use App\Modules\Hr\Document\Models\HrEmployeeDocument;
use App\Modules\Hr\Document\Models\HrEmployeeDocumentVersion;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UploadDocumentAction
{
    public function __construct(
        private HrFileService $fileService,
        private HrAuditService $auditService,
        private MalwareScanner $malwareScanner,
    ) {}

    public function execute(HrEmployee $employee, int $documentTypeId, UploadedFile $file, array $data = []): HrEmployeeDocument
    {
        $tenantId = app(TenantContext::class)->getId();

        // Cross-tenant koruması: başka tüzel kişiliğe ait çalışan/belge türü bağlanamaz.
        // Pasif belge türüyle de belge oluşturulamaz.
        abort_unless($employee->legal_entity_id === $tenantId, 422, 'Çalışan başka bir tüzel kişiliğe ait.');
        HrDocumentType::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->where('id', $documentTypeId)
            ->where('is_active', true)
            ->firstOrFail();

        // Dosya önce fiziksel olarak kaydedilir, ardından güvenlik taramasından geçer.
        // Tarama başarısız olursa dosya belgeye bağlanmaz; orphan fiziksel dosya ve
        // yarım EmployeeDocument kaydı oluşması engellenir.
        $hrFile = $this->fileService->upload($file, 'documents', $employee->id, HrEmployeeDocument::class);

        $absolutePath = Storage::disk(config('hr.file.disk', 'private'))->path($hrFile->disk_path);
        $scanResult = $this->malwareScanner->scan($absolutePath);

        if ($scanResult === ScanResult::Infected) {
            $this->fileService->delete($hrFile);
            abort(422, 'Yüklenen dosya güvenlik taramasında şüpheli bulundu ve reddedildi.');
        }

        $failClosed = config('hr.malware_scanner.fail_closed');
        if ($failClosed === null) {
            $failClosed = app()->environment('production');
        }

        if (in_array($scanResult, [ScanResult::Unavailable, ScanResult::Error], true) && $failClosed) {
            $this->fileService->delete($hrFile);
            abort(422, 'Güvenlik tarayıcısı şu anda kullanılamıyor; dosya kabul edilemedi (fail-closed).');
        }

        $doc = DB::transaction(function () use ($employee, $documentTypeId, $hrFile, $data, $scanResult) {
            $tenantId = app(TenantContext::class)->getId();

            $docNumber = $data['document_number'] ?? null;
            $docNumberHash = null;
            $docNumberLastFour = null;
            if ($docNumber) {
                $docNumberHash = hash('sha256', $docNumber . config('app.key'));
                $docNumberLastFour = substr($docNumber, -4);
            }

            $doc = HrEmployeeDocument::create([
                'legal_entity_id' => $tenantId,
                'employee_id' => $employee->id,
                'document_type_id' => $documentTypeId,
                'current_file_id' => $hrFile->id,
                'document_number_encrypted' => $docNumber,
                'document_number_hash' => $docNumberHash,
                'document_number_last_four' => $docNumberLastFour,
                'issue_date' => $data['issue_date'] ?? null,
                'expiry_date' => $data['expiry_date'] ?? null,
                'status' => DocumentStatus::Uploaded,
                'verification_status' => VerificationStatus::Pending,
                'notes' => $data['notes'] ?? null,
                'version_number' => 1,
                'created_by' => auth()->id(),
            ]);

            HrEmployeeDocumentVersion::create([
                'employee_document_id' => $doc->id,
                'file_id' => $hrFile->id,
                'version_number' => 1,
                'uploaded_by' => auth()->id(),
                'change_reason' => 'İlk yükleme',
                'created_at' => now(),
            ]);

            $this->auditService->log('document_uploaded', $doc, null, [
                'scan_result' => $scanResult->value,
            ]);

            return $doc;
        });

        // Event yalnızca transaction commit edildikten sonra yayınlanır.
        DB::afterCommit(function () use ($doc) {
            event(new EmployeeDocumentUploaded(
                legalEntityId: $doc->legal_entity_id,
                employeeDocumentId: $doc->id,
                employeeId: $doc->employee_id,
                actorUserId: auth()->id(),
                documentTypeId: $doc->document_type_id,
            ));
        });

        return $doc;
    }
}
