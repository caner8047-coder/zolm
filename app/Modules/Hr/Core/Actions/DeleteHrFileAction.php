<?php

namespace App\Modules\Hr\Core\Actions;

use App\Models\HrFile;
use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\HrFileService;

class DeleteHrFileAction
{
    public function __construct(
        private HrFileService $fileService,
        private HrAuditService $auditService
    ) {}

    public function execute(HrFile $file): bool
    {
        $this->auditService->log('file_deleted', $file, [
            'original_name' => $file->original_name,
            'category' => $file->category,
        ]);

        return $this->fileService->delete($file);
    }
}
