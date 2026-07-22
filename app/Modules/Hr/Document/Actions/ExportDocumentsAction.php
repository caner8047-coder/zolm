<?php

namespace App\Modules\Hr\Document\Actions;

use App\Models\User;
use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Document\Enums\DocumentCategory;
use App\Modules\Hr\Document\Enums\DocumentSensitivity;
use App\Modules\Hr\Document\Models\HrEmployeeDocument;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
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

        $headers = ['#', 'Çalışan No', 'Ad Soyad', 'Belge Türü', 'Kategori', 'Durum', 'Doğrulama', 'Yükleme', 'Son Kullanma', 'Kalan Gün', 'Versiyon'];
        foreach ($headers as $col => $header) {
            $cell = Coordinate::stringFromColumnIndex($col + 1).'1';
            $sheet->setCellValueExplicit($cell, $this->sanitizeCell($header), DataType::TYPE_STRING);
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
            $values = [
                (string) $doc->id,
                $doc->employee?->employee_number ?? '',
                $doc->employee?->full_name ?? '',
                $type?->name ?? '',
                $type?->category?->label() ?? '',
                $doc->status->label(),
                $doc->verification_status->label(),
                $doc->created_at?->format('d.m.Y') ?? '',
                $doc->expiry_date?->format('d.m.Y') ?? '',
                $daysLeft !== null ? (string) $daysLeft : '',
                (string) $doc->version_number,
            ];
            foreach ($values as $index => $value) {
                $cell = Coordinate::stringFromColumnIndex($index + 1).$row;
                $sheet->setCellValueExplicit($cell, $this->sanitizeCell($value), DataType::TYPE_STRING);
            }

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
            'count' => $row - 2,
            'legal_entity_id' => $tenantId,
        ]);

        return $path;
    }

    /**
     * CSV/XLSX formula enjeksiyonunu önler: =, +, -, @, tab, CR ile başlayan
     * kullanıcı kaynaklı değerlerin başına tek tırnak ekler.
     */
    private function sanitizeCell(mixed $value): string
    {
        $value = (string) $value;
        if (! mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        $value = trim($value);

        if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'" . $value;
        }

        return $value;
    }
}
