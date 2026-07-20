<?php

namespace App\Modules\Hr\Personnel\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Personnel\Models\HrEmployee;
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
        $headers = ['#', 'Çalışan No', 'Ad', 'Soyad', 'TC Kimlik', 'Cinsiyet', 'Telefon', 'E-posta',
            'Pozisyon', 'Departman', 'Şube', 'İşe Giriş', 'Kıdem', 'Durum'];

        if ($viewIdentity) {
            $headers[] = 'TC Kimlik (Tam)';
        }

        if ($viewBank) {
            $headers[] = 'IBAN';
        }

        foreach ($headers as $col => $header) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
        }

        // Stil
        $headerStyle = $sheet->getStyle('1');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $headerStyle->getFill()->getStartColor()->setRGB('F3F4F6');

        // Veriler
        $row = 2;
        foreach ($employees as $employee) {
            $sheet->setCellValueByColumnAndRow(1, $row, $employee->id);
            $sheet->setCellValueByColumnAndRow(2, $row, $employee->employee_number);
            $sheet->setCellValueByColumnAndRow(3, $row, $employee->first_name);
            $sheet->setCellValueByColumnAndRow(4, $row, $employee->last_name);
            $sheet->setCellValueByColumnAndRow(5, $row, $viewIdentity ? $employee->national_id_encrypted : '***' . $employee->national_id_last_four);
            $sheet->setCellValueByColumnAndRow(6, $row, $employee->gender ? ucfirst($employee->gender) : '');
            $sheet->setCellValueByColumnAndRow(7, $row, $employee->phone ?? '');
            $sheet->setCellValueByColumnAndRow(8, $row, $employee->personal_email ?? '');
            $sheet->setCellValueByColumnAndRow(9, $row, $employee->activeEmployment?->position?->title ?? '');
            $sheet->setCellValueByColumnAndRow(10, $row, $employee->activeEmployment?->department?->name ?? '');
            $sheet->setCellValueByColumnAndRow(11, $row, $employee->activeEmployment?->branch?->name ?? '');
            $sheet->setCellValueByColumnAndRow(12, $row, $employee->activeEmployment?->start_date?->format('d.m.Y') ?? '');
            $sheet->setCellValueByColumnAndRow(13, $row, $employee->tenure ?? '');
            $sheet->setCellValueByColumnAndRow(14, $row, $employee->status->label());

            $col = 15;
            if ($viewIdentity) {
                $sheet->setCellValueByColumnAndRow($col++, $row, $employee->national_id_encrypted);
            }
            if ($viewBank) {
                $sheet->setCellValueByColumnAndRow($col, $row, ''); // IBAN henüz yok
            }

            $row++;
        }

        // Sütun genişlikleri
        foreach (range(1, count($headers)) as $col) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromCharacter($col + 96))->setAutoSize(true);
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
}
