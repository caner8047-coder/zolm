<?php

namespace App\Modules\Hr\Core\Http\Controllers;

use App\Models\HrFile;
use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\HrFileService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Expense\Models\HrExpense;
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

    public function downloadExpenseReceipt(HrFile $file)
    {
        $tenantId = app(TenantContext::class)->getId();
        abort_unless($file->legal_entity_id === $tenantId && $file->category === 'expenses' && $file->subject_type === HrExpense::class, 404);
        $expense = HrExpense::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('receipt_file_id', $file->id)->with('employee')->firstOrFail();
        $isOwner = $expense->employee?->user_id === auth()->id();
        abort_unless($isOwner || auth()->user()?->hasHrPermission('hr.expenses.view'), 403);
        $this->auditService->log('expense_receipt_downloaded', $file, null, ['expense_id' => $expense->id]);
        return $this->fileService->download($file);
    }
}
