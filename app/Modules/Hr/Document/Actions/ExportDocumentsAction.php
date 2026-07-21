<?php

namespace App\Modules\Hr\Document\Actions;

use App\Models\User;
use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Document\Enums\DocumentCategory;
use App\Modules\Hr\Document\Enums\DocumentSensitivity;
use App\Modules\Hr\Document\Models\HrEmployeeDocument;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExportDocumentsAction
{
    public function __construct(
        private HrAuditService $auditService
    ) {}

    public function execute(array $filters = []): string
    {
        $tenantId = app(TenantContext::class)->getId();
        $user = auth()->user();

        $canViewSensitive = $user instanceof User && $user->hasHrPermission('hr.documents.view_sensitive');
        $canViewHealth = $user instanceof User && $user->hasHrPermission('hr.documents.view_health');

        $query = HrEmployeeDocument::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->with('employee', 'documentType');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }
        if (!empty($filters['document_type_id'])) {
            $query->where('document_type_id', $filters['document_type_id']);
        }

        $documents = $query->latest()->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['#', 'Calisan No', 'Ad Soyad', 'Belge Turu', 'Kategori', 'Durum', 'Dogrulama', 'Yukleme', 'Son Kullanma', 'Kalan Gun', 'Versiyon'];
        foreach ($headers as $col => $header) {
            $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1) . '1';
            $sheet->setCellValue($cell, $header);
        }

        $row = 2;
        foreach ($documents as $doc) {
            $type = $doc->documentType;

            // Hassas/sağlık erişim filtresi: yetkisiz satırlar export'a dahil edilmez.
            if ($type && $type->sensitivity === DocumentSensitivity::HighlySensitive && !$canViewSensitive) {
                continue;
            }
            if ($type && $type->category === DocumentCategory::Health && !$canViewHealth) {
                continue;
            }

            $daysLeft = $doc->days_until_expiry;

            // CSV/XLSX formula enjeksiyonuna karşı kullanıcı kaynaklı metin hücreleri sanitize edilir.
            $sheet->setCellValue('A' . $row, $doc->id);
            $sheet->setCellValue('B' . $row, $this->sanitizeCell($doc->employee?->employee_number ?? ''));
            $sheet->setCellValue('C' . $row, $this->sanitizeCell($doc->employee?->full_name ?? ''));
            $sheet->setCellValue('D' . $row, $this->sanitizeCell($type?->name ?? ''));
            $sheet->setCellValue('E' . $row, $this->sanitizeCell($type?->category?->label() ?? ''));
            $sheet->setCellValue('F' . $row, $this->sanitizeCell($doc->status->label()));
            $sheet->setCellValue('G' . $row, $this->sanitizeCell($doc->verification_status->label()));
            $sheet->setCellValue('H' . $row, $doc->created_at?->format('d.m.Y') ?? '');
            $sheet->setCellValue('I' . $row, $doc->expiry_date?->format('d.m.Y') ?? '');
            $sheet->setCellValue('J' . $row, $daysLeft !== null ? (string) $daysLeft : '');
            $sheet->setCellValue('K' . $row, (string) $doc->version_number);

            $row++;
        }

        $filename = 'belgeler_' . now()->format('Y-m-d_His') . '.xlsx';
        $path = "hr/{$tenantId}/exports/{$filename}";
        $tempPath = storage_path("app/private/{$path}");

        if (!is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        (new Xlsx($spreadsheet))->save($tempPath);

        $this->auditService->logEvent('documents_exported', 'Belge listesi disa aktarildi', [
            'format' => 'xlsx',
            'count' => $documents->count(),
            'legal_entity_id' => $tenantId,
        ]);

        return $path;
    }

    /**
     * CSV/XLSX formula enjeksiyonunu önler: =, +, -, @, tab, CR ile başlayan
     * kullanıcı kaynaklı değerlerin başına tek tırnak ekler.
     */
    private function sanitizeCell(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $value = trim($value);

        if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'" . $value;
        }

        return $value;
    }
}
