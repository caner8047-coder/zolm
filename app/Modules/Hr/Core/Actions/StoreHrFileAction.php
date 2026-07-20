<?php

namespace App\Modules\Hr\Core\Actions;

use App\Models\HrFile;
use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\HrFileService;
use Illuminate\Http\UploadedFile;

class StoreHrFileAction
{
    public function __construct(
        private HrFileService $fileService,
        private HrAuditService $auditService
    ) {}

    public function execute(
        UploadedFile $file,
        string $category,
        ?int $subjectId = null,
        ?string $subjectType = null
    ): HrFile {
        $hrFile = $this->fileService->upload($file, $category, $subjectId, $subjectType);

        $this->auditService->log('file_uploaded', $hrFile, null, [
            'original_name' => $hrFile->original_name,
            'category' => $category,
            'size_bytes' => $hrFile->size_bytes,
        ]);

        return $hrFile;
    }
}
