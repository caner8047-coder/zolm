<?php

namespace App\Modules\Hr\Expense\Actions;

use App\Models\HrFile;
use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\HrFileService;
use App\Modules\Hr\Core\Services\MalwareScanner;
use App\Modules\Hr\Core\Services\ScanResult;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Expense\Enums\ExpenseStatus;
use App\Modules\Hr\Expense\Models\HrExpense;
use App\Modules\Hr\Expense\Models\HrExpenseCategory;
use App\Modules\Hr\Expense\Models\HrExpenseStatusHistory;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateExpenseAction
{
    public function __construct(
        private HrFileService $fileService,
        private MalwareScanner $malwareScanner,
        private HrAuditService $audit,
    ) {}

    public function execute(HrEmployee $employee, HrExpenseCategory $category, array $data, ?UploadedFile $receipt = null, ?string $sourceKey = null): HrExpense
    {
        $tenantId = app(TenantContext::class)->getId();
        abort_unless(auth()->user()?->hasHrPermission('hr.expenses.create'), 403);
        abort_unless($employee->legal_entity_id === $tenantId && $category->legal_entity_id === $tenantId && $category->is_active, 422, 'Çalışan veya kategori bu tüzel kişilik için geçerli değil.');
        abort_unless($employee->user_id === auth()->id() || auth()->user()?->hasHrPermission('hr.expenses.approve'), 403);
        abort_if($category->requires_receipt && ! $receipt, 422, 'Bu masraf kategorisi için fiş veya fatura zorunludur.');

        $netAmount = round((float) ($data['net_amount'] ?? 0), 2);
        $vatRate = round((float) ($data['vat_rate'] ?? $category->default_vat_rate), 2);
        abort_if($netAmount <= 0 || $vatRate < 0 || $vatRate > 100, 422, 'Tutar veya KDV oranı geçersiz.');
        $vatAmount = round($netAmount * $vatRate / 100, 2);
        $grossAmount = round($netAmount + $vatAmount, 2);
        $sourceKey ??= (string) Str::uuid();
        abort_unless(Str::isUuid($sourceKey), 422, 'Kaynak anahtarı geçersiz.');

        $payload = [
            'employee_id' => $employee->id,
            'expense_category_id' => $category->id,
            'expense_date' => $data['expense_date'] ?? null,
            'currency' => strtoupper($data['currency'] ?? 'TRY'),
            'net_amount' => number_format($netAmount, 2, '.', ''),
            'vat_rate' => number_format($vatRate, 2, '.', ''),
            'merchant_name' => trim((string) ($data['merchant_name'] ?? '')),
            'document_number' => trim((string) ($data['document_number'] ?? '')),
            'description' => trim((string) ($data['description'] ?? '')),
            'project_reference' => trim((string) ($data['project_reference'] ?? '')),
            'order_reference' => trim((string) ($data['order_reference'] ?? '')),
            'customer_reference' => trim((string) ($data['customer_reference'] ?? '')),
        ];
        abort_if(blank($payload['expense_date']) || blank($payload['description']), 422, 'Masraf tarihi ve açıklama zorunludur.');
        abort_unless(in_array($payload['currency'], ['TRY', 'EUR', 'USD', 'GBP'], true), 422, 'Para birimi desteklenmiyor.');
        $payloadHash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));

        $existing = HrExpense::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('source_key', $sourceKey)->first();
        if ($existing) {
            abort_unless(hash_equals($existing->payload_hash, $payloadHash), 409, 'Aynı kaynak anahtarı farklı bir masraf içeriğiyle kullanılamaz.');
            return $existing;
        }

        $hrFile = $receipt ? $this->uploadAndScan($receipt) : null;

        try {
            return DB::transaction(function () use ($tenantId, $payload, $payloadHash, $sourceKey, $netAmount, $vatRate, $vatAmount, $grossAmount, $hrFile) {
                $expense = HrExpense::create(array_merge($payload, [
                    'legal_entity_id' => $tenantId,
                    'receipt_file_id' => $hrFile?->id,
                    'net_amount' => $netAmount,
                    'vat_rate' => $vatRate,
                    'vat_amount' => $vatAmount,
                    'gross_amount' => $grossAmount,
                    'status' => ExpenseStatus::PendingManager,
                    'source_key' => $sourceKey,
                    'payload_hash' => $payloadHash,
                    'requested_by' => auth()->id(),
                ]));

                if ($hrFile) {
                    $hrFile->update(['subject_type' => HrExpense::class, 'subject_id' => $expense->id]);
                }
                HrExpenseStatusHistory::create(['legal_entity_id' => $tenantId, 'expense_id' => $expense->id, 'to_status' => ExpenseStatus::PendingManager, 'note' => 'Masraf talebi oluşturuldu.', 'acted_by' => auth()->id(), 'created_at' => now()]);
                $this->audit->log('expense_created', $expense, null, ['gross_amount' => $grossAmount, 'currency' => $payload['currency']]);
                return $expense->fresh(['receipt', 'category', 'employee']);
            });
        } catch (\Throwable $exception) {
            if ($hrFile?->exists) {
                $this->fileService->delete($hrFile);
            }
            throw $exception;
        }
    }

    private function uploadAndScan(UploadedFile $receipt): HrFile
    {
        $file = $this->fileService->upload($receipt, 'expenses');
        $path = Storage::disk(config('hr.file.disk', 'private'))->path($file->disk_path);
        $result = $this->malwareScanner->scan($path);
        $failClosed = config('hr.malware_scanner.fail_closed');
        $failClosed ??= app()->environment('production');
        if ($result === ScanResult::Infected || ($failClosed && in_array($result, [ScanResult::Unavailable, ScanResult::Error], true))) {
            $this->fileService->delete($file);
            abort(422, $result === ScanResult::Infected ? 'Fiş/fatura güvenlik taramasında şüpheli bulundu.' : 'Güvenlik tarayıcısı kullanılamadığı için fiş/fatura kabul edilemedi.');
        }
        return $file;
    }
}
