<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelService
{
    /**
     * Import an XLS/XLSX file and return data as collection
     */
    public function importOrderXls(UploadedFile $file, string $sheetName = null): Collection
    {
        $spreadsheet = IOFactory::load($file->getPathname());
        
        if ($sheetName) {
            $sheet = $spreadsheet->getSheetByName($sheetName);
        } else {
            $sheet = $spreadsheet->getActiveSheet();
        }

        if (!$sheet) {
            throw new \Exception("Sayfa bulunamadı: {$sheetName}");
        }

        $data = $sheet->toArray(null, true, true, true);
        
        // İlk satırı header olarak kullan
        $headers = array_shift($data);
        
        $result = collect();
        foreach ($data as $row) {
            $item = [];
            foreach ($headers as $col => $header) {
                if ($header) {
                    $item[$header] = $row[$col] ?? null;
                }
            }
            $result->push($item);
        }

        return $result;
    }

    /**
     * Export data to XLSX file with multiple sheets
     */
    public function exportToXlsx(array $sheets, string $outputPath): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        foreach ($sheets as $index => $sheetData) {
            $sheet = $spreadsheet->createSheet($index);
            $sheet->setTitle(substr($sheetData['name'], 0, 31)); // Max 31 chars for sheet name

            if (empty($sheetData['data'])) {
                continue;
            }

            // Header
            $headers = array_keys($sheetData['data'][0] ?? []);
            foreach ($headers as $col => $header) {
                $cellCoordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1) . '1';
                $sheet->setCellValue($cellCoordinate, $header);
            }

            // Data
            foreach ($sheetData['data'] as $rowIndex => $row) {
                foreach (array_values($row) as $col => $value) {
                    $cellCoordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1) . ($rowIndex + 2);
                    $sheet->setCellValue($cellCoordinate, $value);
                }
            }
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);

        return $outputPath;
    }

    /**
     * Create summary table (Ürün bazlı toplam)
     */
    public function createSummary(Collection $data): array
    {
        return $data
            ->groupBy('Ürün')
            ->map(function ($items, $product) {
                return [
                    'Ürün' => $product,
                    'Adet' => $items->sum('Adet'),
                ];
            })
            ->sortBy('Ürün')
            ->values()
            ->toArray();
    }

    /**
     * Create detail table (date sorted)
     */
    public function createDetail(Collection $data): array
    {
        $columns = [
            'Pazaryeri',
            'Mağaza',
            'Sip. Tarihi',
            'Sipariş No',
            'Sevk - Müşteri',
            'Ürün',
            'Adet',
            'Müşteri Notu',
            'Kargoya Son Teslim Tarihi',
        ];

        return $data
            ->sortBy('Sip. Tarihi')
            ->map(function ($item) use ($columns) {
                $filtered = [];
                foreach ($columns as $col) {
                    $filtered[$col] = $item[$col] ?? '';
                }
                return $filtered;
            })
            ->values()
            ->toArray();
    }
}
