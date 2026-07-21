<?php

namespace App\Modules\Hr\Leave\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Leave\Models\HrLeaveRequest;
use App\Services\ExcelService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExportLeavesAction
{
    public function __construct(private HrAuditService $audit, private ExcelService $excel) {}

    public function execute(array $filters = []): string
    {
        $tenantId = app(TenantContext::class)->getId();
        $query = HrLeaveRequest::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->with(['employee', 'leaveType'])->latest();
        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['leave_type_id'])) $query->where('leave_type_id', $filters['leave_type_id']);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Izin Talepleri');
        $headers = ['Talep No', 'Sicil No', 'Çalışan', 'İzin Türü', 'Başlangıç', 'Bitiş', 'Süre', 'Birim', 'Durum', 'Gerekçe', 'Oluşturma'];
        foreach ($headers as $index => $header) {
            $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($index + 1) . '1', $this->clean($header), DataType::TYPE_STRING);
        }
        $sheet->freezePane('A2');

        $row = 2;
        foreach ($query->get() as $request) {
            $values = [$request->id, $request->employee?->employee_number, $request->employee?->full_name, $request->leaveType?->name, $request->start_date?->format('d.m.Y'), $request->end_date?->format('d.m.Y'), $request->requested_amount, $request->unit?->label(), $request->status?->label(), $request->reason, $request->created_at?->format('d.m.Y H:i')];
            foreach ($values as $index => $value) {
                $address = Coordinate::stringFromColumnIndex($index + 1) . $row;
                $sheet->setCellValueExplicit($address, $this->clean($value), is_numeric($value) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
            }
            $row++;
        }
        foreach (range(1, count($headers)) as $column) $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);

        $filename = 'izin_talepleri_' . now()->format('Y-m-d_His') . '.xlsx';
        $relativePath = "hr/{$tenantId}/exports/{$filename}";
        $path = storage_path("app/private/{$relativePath}");
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0755, true);
        (new Xlsx($spreadsheet))->save($path);
        $this->audit->logEvent('leaves_exported', 'İzin talepleri dışa aktarıldı', ['count' => $row - 2, 'legal_entity_id' => $tenantId]);

        return $relativePath;
    }

    private function clean(mixed $value): string|int|float
    {
        if (is_int($value) || is_float($value)) return $value;
        $value = $this->excel->cleanString((string) ($value ?? ''));
        return $value !== '' && preg_match('/^[=+\-@\t\r]/', $value) ? "'{$value}" : $value;
    }
}
