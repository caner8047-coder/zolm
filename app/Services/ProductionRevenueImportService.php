<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\ProductionRevenueEntry;
use App\Models\ProductionRevenueImport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

class ProductionRevenueImportService
{
    public function importWorkbook(mixed $file, ?User $user = null): array
    {
        [$path, $filename] = $this->resolveFile($file);
        $parsed = $this->parseWorkbook($path);

        if ($parsed['records']->isEmpty()) {
            throw new RuntimeException('Dosyada okunabilir üretim ciro verisi bulunamadı.');
        }

        $sourceHash = hash_file('sha256', $path) ?: null;
        $latestMonth = $parsed['records']->max('work_date');
        $latestDate = $parsed['records']->max('work_date');

        $summary = DB::transaction(function () use ($user, $filename, $sourceHash, $parsed) {
            $import = ProductionRevenueImport::create([
                'user_id' => $user?->id,
                'filename' => $filename,
                'source_hash' => $sourceHash,
                'imported_at' => now(),
                'sheet_count' => $parsed['sheet_count'],
                'months' => $parsed['months'],
                'meta' => [
                    'workbook_rows' => $parsed['records']->count(),
                ],
            ]);

            $existingEntries = ProductionRevenueEntry::query()
                ->whereIn('work_date', $parsed['records']->pluck('work_date')->all())
                ->get()
                ->keyBy(fn (ProductionRevenueEntry $entry) => $entry->work_date->toDateString());

            $created = 0;
            $updated = 0;
            $unchanged = 0;

            foreach ($parsed['records'] as $record) {
                $existing = $existingEntries->get($record['work_date']);

                if (!$existing) {
                    ProductionRevenueEntry::create([
                        ...$record,
                        'production_revenue_import_id' => $import->id,
                    ]);

                    $created++;
                    continue;
                }

                $hasChanged = $this->hasEntryChanged($existing, $record);

                $existing->fill($record);
                $existing->production_revenue_import_id = $import->id;
                $existing->save();

                if ($hasChanged) {
                    $updated++;
                    continue;
                }

                $unchanged++;
            }

            $import->update([
                'created_rows' => $created,
                'updated_rows' => $updated,
                'unchanged_rows' => $unchanged,
                'skipped_rows' => $parsed['skipped_rows'],
            ]);

            return [
                'import' => $import->fresh(),
                'created' => $created,
                'updated' => $updated,
                'unchanged' => $unchanged,
                'skipped' => $parsed['skipped_rows'],
                'months' => $parsed['months'],
            ];
        });

        ActivityLog::log(
            'import_production_revenue',
            'Üretim ciro dosyası içe aktarıldı',
            ProductionRevenueImport::class,
            $summary['import']->id,
            [
                'filename' => $filename,
                'created_rows' => $summary['created'],
                'updated_rows' => $summary['updated'],
                'unchanged_rows' => $summary['unchanged'],
                'skipped_rows' => $summary['skipped'],
                'months' => $summary['months'],
            ]
        );

        return [
            ...$summary,
            'latest_month' => $latestMonth ? Carbon::parse($latestMonth)->format('Y-m') : null,
            'latest_date' => $latestDate,
        ];
    }

    private function parseWorkbook(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);

        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }

        $spreadsheet = $reader->load($path);
        $records = collect();
        $skippedRows = 0;
        $months = [];

        try {
            foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
                $headerRow = $this->detectHeaderRow($sheet);

                if ($headerRow === null) {
                    continue;
                }

                $sheetName = trim($sheet->getTitle());
                $months[] = $sheetName;
                $highestRow = $sheet->getHighestDataRow();

                for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
                    try {
                        $parsedRow = $this->parseRow($sheet, $row, $sheetName);
                    } catch (UnexpectedValueException) {
                        $skippedRows++;
                        continue;
                    }

                    if ($parsedRow === null) {
                        continue;
                    }

                    $records->put($parsedRow['work_date'], $parsedRow);
                }
            }
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }

        return [
            'records' => $records->values(),
            'skipped_rows' => $skippedRows,
            'sheet_count' => count($months),
            'months' => array_values(array_unique($months)),
        ];
    }

    private function parseRow(Worksheet $sheet, int $row, string $sheetName): ?array
    {
        $rowData = $sheet->rangeToArray("A{$row}:F{$row}", null, true, false, false)[0] ?? [];
        $rawDate = $rowData[0] ?? null;
        $formattedDate = trim((string) $sheet->getCell("A{$row}")->getFormattedValue());

        if ($this->isSummaryRow($rawDate, $formattedDate) || $this->isEmptyRow($rowData)) {
            return null;
        }

        $workDate = $this->resolveWorkDate($rawDate, $formattedDate);

        if (!$workDate) {
            throw new UnexpectedValueException("Satır {$row} tarihi okunamadı.");
        }

        $note = $this->extractNote(array_slice($rowData, 2));

        return [
            'work_date' => $workDate->toDateString(),
            'sheet_name' => $sheetName,
            'revenue' => $this->normalizeRevenue($rowData[1] ?? null),
            'note' => $note,
            'status' => $this->determineStatus($note),
            'meta' => [
                'sheet_name' => $sheetName,
                'row' => $row,
                'raw_note' => $note,
            ],
        ];
    }

    private function detectHeaderRow(Worksheet $sheet): ?int
    {
        $limit = min(5, $sheet->getHighestDataRow());

        for ($row = 1; $row <= $limit; $row++) {
            $first = $this->normalizeText($sheet->getCell("A{$row}")->getFormattedValue());
            $second = $this->normalizeText($sheet->getCell("B{$row}")->getFormattedValue());

            if (str_contains($first, 'tarih') && str_contains($second, 'ciro')) {
                return $row;
            }
        }

        return null;
    }

    private function resolveWorkDate(mixed $rawValue, string $formattedValue): ?Carbon
    {
        if ($formattedValue !== '' && str_contains($this->normalizeText($formattedValue), 'toplam')) {
            return null;
        }

        if (is_numeric($rawValue)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $rawValue))->startOfDay();
        }

        $candidate = trim($formattedValue !== '' ? $formattedValue : (string) $rawValue);

        if ($candidate === '') {
            return null;
        }

        foreach (['d.m.Y', 'd/m/Y', 'd-m-Y', 'Y-m-d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $candidate)->startOfDay();
            } catch (Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($candidate)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizeRevenue(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $normalized = preg_replace('/[^\d,\.\-]/u', '', (string) $value) ?? '';

        if ($normalized === '') {
            return 0.0;
        }

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        return round((float) $normalized, 2);
    }

    private function extractNote(array $cells): ?string
    {
        $notes = collect($cells)
            ->map(fn (mixed $value) => $this->cleanCellText($value))
            ->filter()
            ->values();

        return $notes->isEmpty() ? null : $notes->implode(' | ');
    }

    private function determineStatus(?string $note): string
    {
        if (!$note) {
            return ProductionRevenueEntry::STATUS_RECORDED;
        }

        $normalized = $this->normalizeText($note);

        if (str_contains($normalized, 'tatil')) {
            return ProductionRevenueEntry::STATUS_HOLIDAY;
        }

        if (
            str_contains($normalized, 'cikis yapilmadi')
            || str_contains($normalized, 'cikis olmadi')
            || str_contains($normalized, 'uretim olmadi')
        ) {
            return ProductionRevenueEntry::STATUS_NO_OUTPUT;
        }

        return ProductionRevenueEntry::STATUS_NOTE;
    }

    private function hasEntryChanged(ProductionRevenueEntry $entry, array $payload): bool
    {
        return (float) $entry->revenue !== (float) $payload['revenue']
            || $entry->note !== $payload['note']
            || $entry->status !== $payload['status']
            || $entry->sheet_name !== $payload['sheet_name']
            || $entry->meta !== $payload['meta'];
    }

    private function isSummaryRow(mixed $rawValue, string $formattedValue): bool
    {
        if (is_string($rawValue) && str_contains($this->normalizeText($rawValue), 'toplam')) {
            return true;
        }

        return $formattedValue !== '' && str_contains($this->normalizeText($formattedValue), 'toplam');
    }

    private function isEmptyRow(array $rowData): bool
    {
        foreach ($rowData as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeText(mixed $value): string
    {
        return Str::of($this->cleanCellText($value) ?? '')
            ->ascii()
            ->lower()
            ->value();
    }

    private function cleanCellText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;

        return trim($text);
    }

    private function resolveFile(mixed $file): array
    {
        if (is_string($file)) {
            return [$file, basename($file)];
        }

        $path = null;

        if (is_object($file) && method_exists($file, 'getRealPath')) {
            $path = $file->getRealPath();
        }

        if ((!$path || $path === false) && is_object($file) && method_exists($file, 'path')) {
            $path = $file->path();
        }

        if ((!$path || $path === false) && is_object($file) && method_exists($file, 'getPathname')) {
            $path = $file->getPathname();
        }

        if (!$path) {
            throw new RuntimeException('Yüklenen dosyanın yolu çözülemedi.');
        }

        $filename = is_object($file) && method_exists($file, 'getClientOriginalName')
            ? $file->getClientOriginalName()
            : basename($path);

        return [$path, $filename];
    }
}
