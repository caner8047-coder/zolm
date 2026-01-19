<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

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
        
        $headers = array_shift($data);
        
        $result = collect();
        foreach ($data as $row) {
            $item = [];
            foreach ($headers as $col => $header) {
                if ($header) {
                    $value = $row[$col] ?? null;
                    $item[$this->cleanString($header)] = $this->cleanString($value);
                }
            }
            if (!empty(array_filter($item, fn($v) => $v !== null && $v !== ''))) {
                $result->push($item);
            }
        }

        return $result;
    }

    /**
     * Clean string for Excel compatibility
     */
    protected function cleanString($value): mixed
    {
        if ($value === null) {
            return null;
        }
        
        if (!is_string($value)) {
            return $value;
        }
        
        // Boş string kontrolü
        if (trim($value) === '') {
            return '';
        }
        
        // İlk olarak mb_convert_encoding ile temizle
        if (!mb_check_encoding($value, 'UTF-8')) {
            // Türkçe Windows encoding'lerini dene
            $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1254');
        }
        
        // Kontrol karakterlerini kaldır (tab ve newline hariç)
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        
        // XML'de sorun çıkaran karakterleri temizle
        $value = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', '', $value);
        
        return $value;
    }

    /**
     * Export data to XLSX file with multiple sheets
     */
    public function exportToXlsx(array $sheets, string $outputPath): string
    {
        try {
            $spreadsheet = new Spreadsheet();
            $sheetCount = 0;

            foreach ($sheets as $index => $sheetData) {
                $sheetName = $this->sanitizeSheetName($sheetData['name'] ?? 'Sheet' . ($index + 1));
                
                $data = $sheetData['data'] ?? [];
                if (empty($data)) {
                    continue;
                }

                if ($sheetCount === 0) {
                    $sheet = $spreadsheet->getActiveSheet();
                } else {
                    $sheet = $spreadsheet->createSheet();
                }
                
                $sheet->setTitle($sheetName);
                $sheetCount++;

                $firstRow = reset($data);
                if (!is_array($firstRow)) {
                    continue;
                }

                $headers = array_keys($firstRow);
                
                // Header yaz - explicit string type
                foreach ($headers as $colIndex => $header) {
                    $cellAddress = Coordinate::stringFromColumnIndex($colIndex + 1) . '1';
                    $cleanHeader = $this->cleanString($header);
                    $sheet->setCellValueExplicit($cellAddress, $cleanHeader, DataType::TYPE_STRING);
                }

                // Veri yaz
                $rowNum = 2;
                foreach ($data as $row) {
                    if (!is_array($row)) continue;
                    
                    $colIndex = 0;
                    foreach ($headers as $header) {
                        $value = $row[$header] ?? '';
                        $cleanValue = $this->cleanString($value);
                        $cellAddress = Coordinate::stringFromColumnIndex($colIndex + 1) . $rowNum;
                        
                        // Değer tipine göre yaz
                        if (is_numeric($cleanValue)) {
                            $sheet->setCellValueExplicit($cellAddress, $cleanValue, DataType::TYPE_NUMERIC);
                        } else {
                            $sheet->setCellValueExplicit($cellAddress, (string)$cleanValue, DataType::TYPE_STRING);
                        }
                        
                        $colIndex++;
                    }
                    $rowNum++;
                }
            }

            if ($sheetCount === 0) {
                $spreadsheet->getActiveSheet()->setTitle('Bos');
                $spreadsheet->getActiveSheet()->setCellValue('A1', 'Veri yok');
            }

            $dir = dirname($outputPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save($outputPath);

            Log::info("ExcelService: Dosya olusturuldu", ['path' => $outputPath, 'sheets' => $sheetCount]);

            return $outputPath;

        } catch (\Exception $e) {
            Log::error("ExcelService: Hata", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Sanitize sheet name
     */
    protected function sanitizeSheetName(string $name): string
    {
        $name = $this->cleanString($name);
        
        // Yasak karakterleri kaldır
        $name = str_replace([':', '\\', '/', '?', '*', '[', ']'], '', $name);
        $name = trim($name);
        
        // Max 31 karakter
        if (mb_strlen($name) > 31) {
            $name = mb_substr($name, 0, 31);
        }
        
        return $name ?: 'Sheet';
    }

    /**
     * Create summary table
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
     * Create detail table
     */
    public function createDetail(Collection $data): array
    {
        $columns = [
            'Pazaryeri', 'Mağaza', 'Sip. Tarihi', 'Sipariş No',
            'Sevk - Müşteri', 'Ürün', 'Adet', 'Müşteri Notu',
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
