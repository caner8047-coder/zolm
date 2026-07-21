<?php

namespace App\Modules\Hr\Payroll\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Payroll\Models\HrPayrollExport;
use App\Modules\Hr\Payroll\Models\HrPayrollPeriod;
use App\Services\ExcelService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExportPayrollControlOutputAction
{
    public function __construct(
        private VerifyPayrollOutputPreflightAction $preflight,
        private HrAuditService $audit,
        private ExcelService $excel,
    ) {}

    public function execute(HrPayrollPeriod $period): string
    {
        $period = $this->preflight->execute($period);
        $tenant = app(TenantContext::class)->getId();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Bordro Kontrol');
        $headers = ['Sicil No', 'Çalışan', 'Dönem', 'Para Birimi', 'Brüt', 'Çalışan SGK', 'Çalışan İşsizlik', 'Gelir Vergisi', 'Damga Vergisi', 'Toplam Kesinti', 'Net', 'İşveren Katkısı', 'İşveren Toplam Maliyet', 'Hesap İzi'];
        $this->writeHeaders($sheet, $headers);
        $rowNumber = 2;
        foreach ($period->records->sortBy(fn ($record) => $record->employee?->employee_number) as $record) {
            $trace = $record->calculation_trace;
            $values = [
                $record->employee?->employee_number, $record->employee?->full_name, $period->name, $trace['currency'],
                $trace['gross_pay_cents'] / 100, $trace['employee_social_security_cents'] / 100,
                $trace['employee_unemployment_cents'] / 100, $trace['income_tax_cents'] / 100,
                $trace['stamp_tax_cents'] / 100, $trace['employee_deductions_cents'] / 100,
                $trace['net_pay_cents'] / 100, $trace['employer_contributions_cents'] / 100,
                $trace['employer_total_cost_cents'] / 100, $record->calculation_hash,
            ];
            $this->writeRow($sheet, $rowNumber++, $values);
        }
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:N'.max(2, $rowNumber - 1));
        foreach (range(1, count($headers)) as $column) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
        }
        foreach (range('E', 'M') as $column) {
            $sheet->getStyle("{$column}2:{$column}".max(2, $rowNumber - 1))->getNumberFormat()->setFormatCode('#,##0.00');
        }

        $meta = $spreadsheet->createSheet();
        $meta->setTitle('Çıktı Bilgisi');
        $metadata = [
            ['Sınıflandırma', 'BORDRO KONTROL ÇIKTISI'],
            ['Uyarı', 'RESMÎ BEYAN DEĞİLDİR; onay ve aktarım öncesi yetkili uzman kontrolü gerekir.'],
            ['Dönem', $period->name],
            ['Başlangıç', $period->timesheetPeriod->starts_on->format('d.m.Y')],
            ['Bitiş', $period->timesheetPeriod->ends_on->format('d.m.Y')],
            ['Kaynak Hash', $period->source_hash],
            ['Hesap Hash', $period->calculation_hash],
            ['Ön Kontrol Hash', $period->output_preflight_hash],
            ['Üretilme Zamanı', now()->toIso8601String()],
        ];
        foreach ($metadata as $index => $values) {
            $this->writeRow($meta, $index + 1, $values);
        }
        $meta->getColumnDimension('A')->setWidth(24);
        $meta->getColumnDimension('B')->setWidth(90);

        $filename = 'bordro_kontrol_'.$period->timesheetPeriod->starts_on->format('Y-m-d').'_'.$period->timesheetPeriod->ends_on->format('Y-m-d').'_'.now()->format('His').'.xlsx';
        $relativePath = "hr/{$tenant}/exports/{$filename}";
        $fullPath = storage_path("app/private/{$relativePath}");
        if (! is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }
        (new Xlsx($spreadsheet))->save($fullPath);
        $contentHash = hash_file('sha256', $fullPath);
        $export = HrPayrollExport::create([
            'legal_entity_id' => $tenant, 'payroll_period_id' => $period->id, 'classification' => 'control_output',
            'format' => 'xlsx', 'preflight_hash' => $period->output_preflight_hash, 'content_hash' => $contentHash,
            'generated_by' => auth()->id(), 'generated_at' => now(),
        ]);
        $this->audit->log('payroll_control_output_exported', $export, null, ['period_id' => $period->id, 'content_hash' => $contentHash, 'record_count' => $period->records->count()]);
        $spreadsheet->disconnectWorksheets();
        return $relativePath;
    }

    private function writeHeaders($sheet, array $headers): void
    {
        foreach ($headers as $index => $header) {
            $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($index + 1).'1', $this->clean($header), DataType::TYPE_STRING);
        }
        $sheet->getStyle('A1:'.Coordinate::stringFromColumnIndex(count($headers)).'1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2E8F0');
    }

    private function writeRow($sheet, int $rowNumber, array $values): void
    {
        foreach ($values as $index => $value) {
            $clean = $this->clean($value);
            $type = is_int($clean) || is_float($clean) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING;
            $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($index + 1).$rowNumber, $clean, $type);
        }
    }

    private function clean(mixed $value): string|int|float
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        $value = $this->excel->cleanString((string) ($value ?? ''));
        return $value !== '' && preg_match('/^[=+\-@\t\r]/', $value) ? "'{$value}" : $value;
    }
}
