<?php

namespace App\Modules\Hr\Personnel\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExportEmployeesAction
{
    public function __construct(
        private HrAuditService $auditService
    ) {}

    public function execute(array $filters = [], array $options = []): string
    {
        $tenantId = app(TenantContext::class)->getId();
        $viewIdentity = $options['view_identity'] ?? false;
        $viewBank = $options['view_bank'] ?? false;

        $query = HrEmployee::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->with('activeEmployment.position', 'activeEmployment.department', 'activeEmployment.branch');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $employees = $query->orderBy('last_name')->orderBy('first_name')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Başlıklar
        $headers = ['#', 'Çalışan No', 'Ad', 'Soyad', 'TC Kimlik (Maskeli)', 'Cinsiyet', 'Telefon', 'E-posta',
            'Pozisyon', 'Departman', 'Şube', 'İşe Giriş', 'Kıdem', 'Durum'];

        if ($viewIdentity) {
            $headers[] = 'TC Kimlik (Tam)';
        }

        if ($viewBank) {
            $headers[] = 'IBAN';
        }

        foreach ($headers as $col => $header) {
            $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($col + 1).'1', $this->cleanString($header), DataType::TYPE_STRING);
        }

        // Stil
        $headerStyle = $sheet->getStyle('1');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $headerStyle->getFill()->getStartColor()->setRGB('F3F4F6');

        // Veriler
        $row = 2;
        foreach ($employees as $employee) {
            $values = [
                (string) $employee->id,
                $employee->employee_number,
                $employee->first_name,
                $employee->last_name,
                '***'.$employee->national_id_last_four,
                $employee->gender ? ucfirst((string) $employee->gender) : '',
                $employee->phone ?? '',
                $employee->personal_email ?? '',
                $employee->activeEmployment?->position?->title ?? '',
                $employee->activeEmployment?->department?->name ?? '',
                $employee->activeEmployment?->branch?->name ?? '',
                $employee->activeEmployment?->start_date?->format('d.m.Y') ?? '',
                $employee->tenure ?? '',
                $employee->status->label(),
            ];

            foreach ($values as $index => $value) {
                $this->writeString($sheet, $index + 1, $row, $value);
            }

            $col = 15;
            if ($viewIdentity) {
                $this->writeString($sheet, $col++, $row, $employee->national_id_encrypted);
            }
            if ($viewBank) {
                $this->writeString($sheet, $col, $row, ''); // IBAN henüz yok
            }

            $row++;
        }

        // Sütun genişlikleri
        foreach (range(1, count($headers)) as $col) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
        }

        // Dosyayı kaydet
        $filename = 'calisanlar_' . now()->format('Y-m-d_His') . '.xlsx';
        $path = "hr/{$tenantId}/exports/{$filename}";

        $writer = new Xlsx($spreadsheet);
        $tempPath = storage_path("app/private/{$path}");

        if (!is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        $writer->save($tempPath);

        // Audit log
        $this->auditService->logEvent('employee_exported', 'Çalışan listesi dışa aktarıldı', [
            'format' => 'xlsx',
            'count' => $employees->count(),
            'filename' => $filename,
        ]);

        return $path;
    }

    private function writeString($sheet, int $column, int $row, mixed $value): void
    {
        $sheet->setCellValueExplicit(
            Coordinate::stringFromColumnIndex($column).$row,
            $this->cleanString($value),
            DataType::TYPE_STRING
        );
    }

    private function cleanString(mixed $value): string
    {
        $value = (string) $value;
        if (! mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        return $value !== '' && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
