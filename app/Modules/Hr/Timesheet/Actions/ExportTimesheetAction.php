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
        $rows = $period->timesheets()->with(['employee.activeEmployment.branch', 'employee.activeEmployment.department', 'employee.activeEmployment.position', 'latestCorrection'])->orderBy('work_date')->get();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Puantaj');
        $headers = ['Sicil No', 'Çalışan', 'İş Günü', 'Gün Türü', 'Planlanan Dk', 'Çalışılan Dk', 'Mola Dk', 'Talep Edilen İzin Dk', 'Mahsup Edilen İzin Dk', 'Fazla Dk', 'Resmî Tatil Çalışması Dk', 'Hafta Tatili Çalışması Dk', 'Eksik Dk', 'İlk Giriş', 'Son Çıkış', 'Anomali', 'Hesap Bayrakları', 'Hesap Sürümü', 'Durum', 'Revizyon'];
        foreach ($headers as $index => $header) $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($index + 1).'1', $this->clean($header), DataType::TYPE_STRING);
        $sheet->freezePane('A2');
        $rowNumber = 2;
        foreach ($rows as $row) {
            $values = [$row->employee?->employee_number, $row->employee?->full_name, $row->work_date->format('d.m.Y'), $row->day_type->label(), $row->scheduled_minutes, $row->effective('worked_minutes'), $row->effective('break_minutes'), $row->requested_leave_minutes, $row->effective('leave_minutes'), $row->effective('overtime_minutes'), $row->holiday_work_minutes, $row->weekly_rest_work_minutes, $row->effective('missing_minutes'), $row->first_in_at?->format('d.m.Y H:i'), $row->last_out_at?->format('d.m.Y H:i'), $row->anomaly_count, implode(', ', $row->calculation_flags ?? []), $row->calculation_version, $row->status->label(), $row->latestCorrection?->revision_number ?? 0];
            foreach ($values as $index => $value) {
                $clean = $this->clean($value); $type = is_int($clean) || is_float($clean) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING;
                $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($index + 1).$rowNumber, $clean, $type);
            }
            $rowNumber++;
        }
        foreach (range(1, count($headers)) as $column) $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
        $sheet->setAutoFilter('A1:'.Coordinate::stringFromColumnIndex(count($headers)).max(2, $rowNumber - 1));

        $summary = $spreadsheet->createSheet();
        $summary->setTitle('Çalışan Özeti');
        $summaryHeaders = ['Sicil No', 'Çalışan', 'Şube', 'Departman', 'Pozisyon', 'Planlanan Dk', 'Çalışılan Dk', 'Talep Edilen İzin Dk', 'Mahsup Edilen İzin Dk', 'Normal Fazla Dk', 'Resmî Tatil Çalışması Dk', 'Hafta Tatili Çalışması Dk', 'Eksik Dk', 'Açık Anomali'];
        foreach ($summaryHeaders as $index => $header) $summary->setCellValueExplicit(Coordinate::stringFromColumnIndex($index + 1).'1', $this->clean($header), DataType::TYPE_STRING);
        $summaryRow = 2;
        foreach ($rows->groupBy('employee_id') as $employeeRows) {
            $employee = $employeeRows->first()->employee;
            $employment = $employee?->activeEmployment;
            $values = [
                $employee?->employee_number,
                $employee?->full_name,
                $employment?->branch?->name,
                $employment?->department?->name,
                $employment?->position?->title,
                $employeeRows->sum('scheduled_minutes'),
                $employeeRows->sum(fn ($row) => (int) $row->effective('worked_minutes')),
                $employeeRows->sum('requested_leave_minutes'),
                $employeeRows->sum(fn ($row) => (int) $row->effective('leave_minutes')),
                $employeeRows->sum(fn ($row) => (int) $row->effective('overtime_minutes')),
                $employeeRows->sum('holiday_work_minutes'),
                $employeeRows->sum('weekly_rest_work_minutes'),
                $employeeRows->sum(fn ($row) => (int) $row->effective('missing_minutes')),
                $employeeRows->sum('anomaly_count'),
            ];
            foreach ($values as $index => $value) {
                $clean = $this->clean($value);
                $summary->setCellValueExplicit(Coordinate::stringFromColumnIndex($index + 1).$summaryRow, $clean, is_int($clean) || is_float($clean) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
            }
            $summaryRow++;
        }
        $summary->freezePane('A2');
        $summary->setAutoFilter('A1:'.Coordinate::stringFromColumnIndex(count($summaryHeaders)).max(2, $summaryRow - 1));
        foreach (range(1, count($summaryHeaders)) as $column) $summary->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
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
