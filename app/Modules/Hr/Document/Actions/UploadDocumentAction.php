<?php

namespace App\Modules\Hr\Document\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\HrFileService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Document\Enums\DocumentStatus;
use App\Modules\Hr\Document\Enums\VerificationStatus;
use App\Modules\Hr\Document\Models\HrEmployeeDocument;
use App\Modules\Hr\Document\Models\HrEmployeeDocumentVersion;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class UploadDocumentAction
{
    public function __construct(
        private HrFileService $fileService,
        private HrAuditService $auditService
    ) {}

    public function execute(HrEmployee $employee, int $documentTypeId, UploadedFile $file, array $data = []): HrEmployeeDocument
    {
        return DB::transaction(function () use ($employee, $documentTypeId, $file, $data) {
            $tenantId = app(TenantContext::class)->getId();

            // Dosya yükle
            $hrFile = $this->fileService->upload($file, 'documents', $employee->id, HrEmployeeDocument::class);

            // Document number hash
            $docNumber = $data['document_number'] ?? null;
            $docNumberHash = null;
            $docNumberLastFour = null;
            if ($docNumber) {
                $docNumberHash = hash('sha256', $docNumber . config('app.key'));
                $docNumberLastFour = substr($docNumber, -4);
            }

            // Employee document oluştur
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

            // İlk versiyon kaydı
            HrEmployeeDocumentVersion::create([
                'employee_document_id' => $doc->id,
                'file_id' => $hrFile->id,
                'version_number' => 1,
                'uploaded_by' => auth()->id(),
                'change_reason' => 'İlk yükleme',
                'created_at' => now(),
            ]);

            // Audit
            $this->auditService->log('document_uploaded', $doc);

            return $doc;
        });
    }
}
