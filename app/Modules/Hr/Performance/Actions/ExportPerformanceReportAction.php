<?php

namespace App\Modules\Hr\Performance\Actions;

use App\Models\LegalEntity;
use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Performance\Models\HrPerformanceCycle;
use App\Modules\Hr\Performance\Models\HrPerformanceResult;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Services\ExcelService;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExportPerformanceReportAction
{
    public function __construct(private ExcelService $excel, private HrAuditService $audit) {}

    public function exportCycle(HrPerformanceCycle $cycle): string
    {
        $this->authorize($cycle);
        $results = HrPerformanceResult::withoutGlobalScope('tenant')->where('legal_entity_id', $cycle->legal_entity_id)
            ->where('cycle_id', $cycle->id)->with('employee')->orderByDesc('overall_score')->get();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Performans Sonuçları');
        $headers = ['Sicil No', 'Çalışan', 'Döngü', 'Birleşik Puan', 'Tamamlanan', 'Beklenen', 'Durum', 'Öz', 'Yönetici', 'Ekip Arkadaşı', 'Bağlı Çalışan', 'İK', 'Hesap İzi'];
        $this->writeRow($sheet, 1, $headers, true);
        $row = 2;
        foreach ($results as $result) {
            $breakdown = $result->reviewer_breakdown ?? [];
            $this->writeRow($sheet, $row++, [
                $result->employee?->employee_number, $result->employee?->full_name, $cycle->name,
                $result->overall_score === null ? '' : (float) $result->overall_score, $result->completed_responses, $result->expected_responses,
                $result->status, $breakdown['self']['score'] ?? '', $breakdown['manager']['score'] ?? '',
                $breakdown['peer']['score'] ?? '', $breakdown['direct_report']['score'] ?? '',
                $breakdown['hr']['score'] ?? '', $result->calculation_hash,
            ]);
        }
        $lastRow = max(2, $row - 1);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:M{$lastRow}");
        foreach (range(1, 13) as $column) $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
        $sheet->getStyle("D2:L{$lastRow}")->getNumberFormat()->setFormatCode('0.00');
        $relativePath = 'hr/'.$cycle->legal_entity_id.'/exports/performans_'.$cycle->id.'_'.now()->format('Ymd_His').'.xlsx';
        $fullPath = storage_path('app/private/'.$relativePath);
        if (! is_dir(dirname($fullPath))) mkdir(dirname($fullPath), 0755, true);
        (new Xlsx($spreadsheet))->save($fullPath);
        $spreadsheet->disconnectWorksheets();
        $this->audit->log('performance_cycle_report_exported', $cycle, null, ['result_count' => $results->count(), 'content_hash' => hash_file('sha256', $fullPath)]);

        return $relativePath;
    }

    public function exportEmployee(HrPerformanceCycle $cycle, HrEmployee $employee): string
    {
        abort_unless($cycle->legal_entity_id === app(TenantContext::class)->getId() && $employee->legal_entity_id === $cycle->legal_entity_id, 404);
        abort_unless(in_array($cycle->status->value, ['calibration', 'closed'], true), 422, 'Rapor yalnız kalibrasyon veya kapanış aşamasında alınabilir.');
        $selfAccess = $employee->user_id === auth()->id() && $cycle->status->value === 'closed';
        abort_unless(auth()->user()?->hasHrPermission('hr.performance.export') || $selfAccess, 403);
        $result = HrPerformanceResult::withoutGlobalScope('tenant')->where('legal_entity_id', $cycle->legal_entity_id)
            ->where('cycle_id', $cycle->id)->where('employee_id', $employee->id)->firstOrFail();
        $goals = $cycle->goals()->where('employee_id', $employee->id)->with('checkIns')->get();
        $comments = $this->visibleComments($cycle, $employee);
        $entity = LegalEntity::findOrFail($cycle->legal_entity_id);
        $pdf = Pdf::loadView('pdf.hr.performance-report', compact('entity', 'cycle', 'employee', 'result', 'goals', 'comments'))->setPaper('a4');
        $relativePath = 'hr/'.$cycle->legal_entity_id.'/exports/performans_'.$cycle->id.'_'.$employee->employee_number.'_'.now()->format('Ymd_His').'.pdf';
        $fullPath = storage_path('app/private/'.$relativePath);
        if (! is_dir(dirname($fullPath))) mkdir(dirname($fullPath), 0755, true);
        $pdf->save($fullPath);
        $this->audit->log('performance_employee_report_exported', $result, null, ['content_hash' => hash_file('sha256', $fullPath)]);

        return $relativePath;
    }

    private function visibleComments(HrPerformanceCycle $cycle, HrEmployee $employee): array
    {
        $evaluations = $cycle->evaluations()->where('employee_id', $employee->id)->whereIn('status', ['submitted', 'calibrated'])->with('template')->get();
        $counts = $evaluations->groupBy(fn ($evaluation) => $evaluation->reviewer_type->value)->map->count();
        $comments = [];
        foreach ($evaluations as $evaluation) {
            if ($evaluation->is_anonymous && ($counts[$evaluation->reviewer_type->value] ?? 0) < $cycle->anonymity_threshold) continue;
            foreach ($evaluation->template->sections as $section) foreach ($section['questions'] as $question) {
                if (($question['type'] ?? 'rating') !== 'text') continue;
                $value = trim((string) ($evaluation->answers[$question['id']] ?? ''));
                if ($value !== '') $comments[] = ['type' => $evaluation->reviewer_type->label(), 'question' => $question['label'], 'answer' => $value];
            }
        }

        return $comments;
    }

    private function authorize(HrPerformanceCycle $cycle): void
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.performance.export'), 403);
        abort_unless($cycle->legal_entity_id === app(TenantContext::class)->getId(), 404);
        abort_unless(in_array($cycle->status->value, ['calibration', 'closed'], true), 422, 'Rapor yalnız kalibrasyon veya kapanış aşamasında alınabilir.');
    }

    private function writeRow($sheet, int $row, array $values, bool $header = false): void
    {
        foreach ($values as $index => $value) {
            $value = is_string($value) ? $this->excel->cleanString($value) : $value;
            if (is_string($value) && preg_match('/^[=+\-@\t\r]/', $value)) $value = "'{$value}";
            $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($index + 1).$row, $value, is_int($value) || is_float($value) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
        }
        if ($header) $sheet->getStyle('A1:'.Coordinate::stringFromColumnIndex(count($values)).'1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2E8F0');
    }
}
