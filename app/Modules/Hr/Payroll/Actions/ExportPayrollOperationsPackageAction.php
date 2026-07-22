<?php

namespace App\Modules\Hr\Payroll\Actions;

use App\Models\LegalEntity;
use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Payroll\Models\HrPayrollExport;
use App\Modules\Hr\Payroll\Models\HrPayrollPeriod;
use App\Services\ExcelService;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use ZipArchive;

class ExportPayrollOperationsPackageAction
{
    public function __construct(
        private VerifyPayrollOutputPreflightAction $preflight,
        private HrAuditService $audit,
        private ExcelService $excel,
    ) {}

    public function execute(HrPayrollPeriod $period): string
    {
        $period = $this->preflight->execute($period);
        $period->load(['records.employee', 'records.payrollProfile', 'timesheetPeriod']);
        $tenantId = app(TenantContext::class)->getId();
        $entity = LegalEntity::findOrFail($tenantId);
        $records = $period->records->sortBy(fn ($record) => $record->employee?->employee_number)->values();

        $missingPaymentProfiles = $records->filter(fn ($record) => ! $record->payrollProfile);
        abort_if($missingPaymentProfiles->isNotEmpty(), 422, 'Banka ve bordro pusulası paketi için tüm çalışanların onaylı bordro profili olmalı.');

        $directory = storage_path("app/private/hr/{$tenantId}/exports");
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        $stamp = now()->format('Ymd_His');
        $baseName = 'bordro_operasyon_paketi_'.$period->timesheetPeriod->starts_on->format('Y-m').'_'.substr((string) $period->calculation_hash, 0, 10).'_'.$stamp;
        $zipPath = "{$directory}/{$baseName}.zip";
        $summaryPath = "{$directory}/{$baseName}_icmal.xlsx";
        $bankPath = "{$directory}/{$baseName}_banka.xlsx";

        $this->writeSummaryWorkbook($summaryPath, $period, $records);
        $this->writeBankWorkbook($bankPath, $period, $records);

        $zip = new ZipArchive();
        abort_unless($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 500, 'Bordro paketi oluşturulamadı.');
        $zip->addFile($summaryPath, 'Bordro_Icmal.xlsx');
        $zip->addFile($bankPath, 'Banka_Odeme_Listesi.xlsx');
        foreach ($records as $record) {
            $pdf = Pdf::loadView('pdf.hr.payroll-payslip', [
                'entity' => $entity,
                'period' => $period,
                'record' => $record,
                'trace' => $record->calculation_trace,
            ])->setPaper('a4');
            $safeEmployeeNumber = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $record->employee?->employee_number);
            $zip->addFromString("Ucret_Pusulalari/{$safeEmployeeNumber}.pdf", $pdf->output());
        }
        $zip->addFromString('README.txt', $this->readme($period, $records->count()));
        $zip->close();
        @unlink($summaryPath);
        @unlink($bankPath);

        $contentHash = hash_file('sha256', $zipPath);
        $export = HrPayrollExport::create([
            'legal_entity_id' => $tenantId,
            'payroll_period_id' => $period->id,
            'classification' => 'operations_package',
            'format' => 'zip',
            'preflight_hash' => $period->output_preflight_hash,
            'content_hash' => $contentHash,
            'generated_by' => auth()->id(),
            'generated_at' => now(),
        ]);
        $this->audit->log('payroll_operations_package_exported', $export, null, [
            'period_id' => $period->id,
            'content_hash' => $contentHash,
            'record_count' => $records->count(),
        ]);

        return "hr/{$tenantId}/exports/{$baseName}.zip";
    }

    private function writeSummaryWorkbook(string $path, HrPayrollPeriod $period, $records): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Bordro İcmal');
        $headers = ['Sicil No', 'Çalışan', 'Dönem', 'Brüt', 'SGK İşçi', 'İşsizlik İşçi', 'Gelir Vergisi', 'Damga Vergisi', 'Diğer Kesintiler', 'Toplam Kesinti', 'Net', 'SGK İşveren', 'İşsizlik İşveren', 'Teşvik', 'İşveren Maliyeti'];
        $this->writeHeaders($sheet, $headers);
        $totals = array_fill(3, 12, 0);
        $row = 2;
        foreach ($records as $record) {
            $trace = $record->calculation_trace;
            $values = [
                $record->employee?->employee_number,
                $record->employee?->full_name,
                $period->name,
                $trace['gross_pay_cents'] / 100,
                $trace['employee_social_security_cents'] / 100,
                $trace['employee_unemployment_cents'] / 100,
                $trace['income_tax_cents'] / 100,
                $trace['stamp_tax_cents'] / 100,
                (($trace['pre_tax_deduction_cents'] ?? 0) + ($trace['post_tax_deduction_cents'] ?? 0)) / 100,
                $trace['employee_deductions_cents'] / 100,
                $trace['net_pay_cents'] / 100,
                $trace['employer_social_security_cents'] / 100,
                $trace['employer_unemployment_cents'] / 100,
                ($trace['employer_incentive_cents'] ?? 0) / 100,
                $trace['employer_total_cost_cents'] / 100,
            ];
            foreach (range(3, 14) as $index) {
                $totals[$index] += $values[$index];
            }
            $this->writeRow($sheet, $row++, $values);
        }
        $totalRow = ['', 'GENEL TOPLAM', $period->name];
        foreach (range(3, 14) as $index) {
            $totalRow[] = $totals[$index];
        }
        $this->writeRow($sheet, $row, $totalRow);
        $sheet->getStyle("A{$row}:O{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF1F5F9');
        $sheet->getStyle("D2:O{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        $this->finishSheet($sheet, count($headers), $row);
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
    }

    private function writeBankWorkbook(string $path, HrPayrollPeriod $period, $records): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Banka Ödeme');
        $headers = ['Sicil No', 'Çalışan', 'Hesap Sahibi', 'Banka', 'IBAN', 'Tutar', 'Para Birimi', 'Açıklama', 'Ödeme Yöntemi'];
        $this->writeHeaders($sheet, $headers);
        $row = 2;
        foreach ($records->filter(fn ($record) => $record->payrollProfile->payment_method === 'bank') as $record) {
            $profile = $record->payrollProfile;
            $trace = $record->calculation_trace;
            $this->writeRow($sheet, $row++, [
                $record->employee?->employee_number,
                $record->employee?->full_name,
                $profile->bank_account_holder ?: $record->employee?->full_name,
                $profile->bank_name,
                $profile->iban_encrypted,
                $trace['net_pay_cents'] / 100,
                $trace['currency'],
                $period->name.' net ücret ödemesi',
                'Banka',
            ]);
        }
        $sheet->getStyle('F2:F'.max(2, $row - 1))->getNumberFormat()->setFormatCode('#,##0.00');
        $this->finishSheet($sheet, count($headers), max(2, $row - 1));

        $cashRecords = $records->filter(fn ($record) => $record->payrollProfile->payment_method === 'cash');
        if ($cashRecords->isNotEmpty()) {
            $cash = $spreadsheet->createSheet();
            $cash->setTitle('Nakit Ödeme');
            $cashHeaders = ['Sicil No', 'Çalışan', 'Tutar', 'Para Birimi', 'Açıklama'];
            $this->writeHeaders($cash, $cashHeaders);
            $cashRow = 2;
            foreach ($cashRecords as $record) {
                $trace = $record->calculation_trace;
                $this->writeRow($cash, $cashRow++, [
                    $record->employee?->employee_number,
                    $record->employee?->full_name,
                    $trace['net_pay_cents'] / 100,
                    $trace['currency'],
                    $period->name.' net ücret ödemesi',
                ]);
            }
            $cash->getStyle('C2:C'.max(2, $cashRow - 1))->getNumberFormat()->setFormatCode('#,##0.00');
            $this->finishSheet($cash, count($cashHeaders), max(2, $cashRow - 1));
        }
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
    }

    private function writeHeaders($sheet, array $headers): void
    {
        foreach ($headers as $index => $header) {
            $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($index + 1).'1', $this->clean($header), DataType::TYPE_STRING);
        }
        $sheet->getStyle('A1:'.Coordinate::stringFromColumnIndex(count($headers)).'1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2E8F0');
    }

    private function writeRow($sheet, int $row, array $values): void
    {
        foreach ($values as $index => $value) {
            $clean = $this->clean($value);
            $sheet->setCellValueExplicit(
                Coordinate::stringFromColumnIndex($index + 1).$row,
                $clean,
                is_int($clean) || is_float($clean) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING,
            );
        }
    }

    private function finishSheet($sheet, int $columnCount, int $lastRow): void
    {
        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");
        foreach (range(1, $columnCount) as $column) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
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

    private function readme(HrPayrollPeriod $period, int $recordCount): string
    {
        return implode("\n", [
            'ZOLM Bordro Operasyon Paketi',
            'Dönem: '.$period->name,
            'Çalışan sayısı: '.$recordCount,
            'Hesap izi: '.$period->calculation_hash,
            'Ön kontrol izi: '.$period->output_preflight_hash,
            'Üretilme: '.now()->toIso8601String(),
            '',
            'Bu paket bordro operasyonu ve banka aktarım kontrolü içindir.',
            'MUHSGK veya başka bir resmî beyan dosyası değildir; yetkili uzman kontrolü zorunludur.',
        ]);
    }
}
