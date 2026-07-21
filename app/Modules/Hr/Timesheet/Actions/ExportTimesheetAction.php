<?php

namespace App\Modules\Hr\Timesheet\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Timesheet\Models\HrTimesheetPeriod;
use App\Services\ExcelService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExportTimesheetAction
{
    public function __construct(private HrAuditService $audit, private ExcelService $excel) {}

    public function execute(HrTimesheetPeriod $period): string
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.timesheet.view'), 403);
        $tenantId = app(TenantContext::class)->getId();
        abort_unless($period->legal_entity_id === $tenantId, 404);
        $rows = $period->timesheets()->with(['employee', 'latestCorrection'])->orderBy('work_date')->get();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Puantaj');
        $headers = ['Sicil No', 'Çalışan', 'İş Günü', 'Planlanan Dk', 'Çalışılan Dk', 'Mola Dk', 'İzin Dk', 'Fazla Dk', 'Eksik Dk', 'İlk Giriş', 'Son Çıkış', 'Durum', 'Revizyon'];
        foreach ($headers as $index => $header) $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($index + 1).'1', $this->clean($header), DataType::TYPE_STRING);
        $sheet->freezePane('A2');
        $rowNumber = 2;
        foreach ($rows as $row) {
            $values = [$row->employee?->employee_number, $row->employee?->full_name, $row->work_date->format('d.m.Y'), $row->scheduled_minutes, $row->effective('worked_minutes'), $row->effective('break_minutes'), $row->effective('leave_minutes'), $row->effective('overtime_minutes'), $row->effective('missing_minutes'), $row->first_in_at?->format('d.m.Y H:i'), $row->last_out_at?->format('d.m.Y H:i'), $row->status->label(), $row->latestCorrection?->revision_number ?? 0];
            foreach ($values as $index => $value) {
                $clean = $this->clean($value); $type = is_int($clean) || is_float($clean) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING;
                $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($index + 1).$rowNumber, $clean, $type);
            }
            $rowNumber++;
        }
        foreach (range(1, count($headers)) as $column) $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
        $filename = 'puantaj_'.$period->starts_on->format('Y-m-d').'_'.$period->ends_on->format('Y-m-d').'_'.now()->format('His').'.xlsx';
        $relativePath = "hr/{$tenantId}/exports/{$filename}"; $fullPath = storage_path("app/private/{$relativePath}");
        if (!is_dir(dirname($fullPath))) mkdir(dirname($fullPath), 0755, true);
        (new Xlsx($spreadsheet))->save($fullPath);
        $this->audit->logEvent('timesheet_exported', 'Puantaj dönemi dışa aktarıldı', ['period_id' => $period->id, 'count' => $rows->count(), 'legal_entity_id' => $tenantId]);
        return $relativePath;
    }

    private function clean(mixed $value): string|int|float
    {
        if (is_int($value) || is_float($value)) return $value;
        $value = $this->excel->cleanString((string) ($value ?? ''));
        return $value !== '' && preg_match('/^[=+\-@\t\r]/', $value) ? "'{$value}" : $value;
    }
}
