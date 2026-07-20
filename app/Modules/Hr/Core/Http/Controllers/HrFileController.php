<?php

namespace App\Modules\Hr\Core\Http\Controllers;

use App\Models\HrFile;
use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\HrFileService;
use App\Modules\Hr\Core\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class HrFileController extends Controller
{
    public function __construct(
        private HrFileService $fileService,
        private HrAuditService $auditService
    ) {}

    public function download(HrFile $file)
    {
        abort_if(
            $file->legal_entity_id !== app(TenantContext::class)->getId(),
            403,
            'Bu dosyaya erişim yetkiniz bulunmuyor.'
        );

        $this->auditService->log('file_downloaded', $file, null, [
            'original_name' => $file->original_name,
        ]);

        return $this->fileService->download($file);
    }

    public function signedUrl(HrFile $file): JsonResponse
    {
        abort_if(
            $file->legal_entity_id !== app(TenantContext::class)->getId(),
            403,
            'Bu dosyaya erişim yetkiniz bulunmuyor.'
        );

        $url = $this->fileService->getSignedUrl($file);

        return response()->json(['url' => $url, 'expires_in' => 900]);
    }
}
