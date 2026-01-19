<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\Report;
use App\Models\ReportFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class DynamicTransformEngine
{
    protected ExcelService $excelService;

    public function __construct(ExcelService $excelService)
    {
        $this->excelService = $excelService;
    }

    /**
     * Run transformation based on AI-generated rules
     */
    public function run(UploadedFile $uploadedFile, Profile $profile, Report $report): array
    {
        $report->update(['status' => 'processing']);

        try {
            $rules = $profile->getRules();
            
            if (empty($rules) || !isset($rules['version'])) {
                throw new \Exception('Profil kuralları bulunamadı veya geçersiz.');
            }

            // 1. Veriyi oku
            $data = $this->readInputData($uploadedFile, $rules['input'] ?? []);

            // 2. Dönüşümleri uygula
            $data = $this->applyTransformations($data, $rules['transformations'] ?? []);

            // 3. Çıktıları oluştur
            $generatedFiles = $this->generateOutputs($data, $rules['outputs'] ?? [], $report);

            $report->update(['status' => 'success']);

            return [
                'success' => true,
                'files' => $generatedFiles,
                'message' => count($generatedFiles) . ' dosya başarıyla oluşturuldu.',
            ];

        } catch (\Exception $e) {
            $report->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'files' => [],
                'message' => 'Hata: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Read input data from file
     */
    protected function readInputData(UploadedFile $file, array $inputConfig): Collection
    {
        $sheetName = $inputConfig['sheet_name'] ?? null;
        return $this->excelService->importOrderXls($file, $sheetName);
    }

    /**
     * Apply all transformations
     */
    protected function applyTransformations(Collection $data, array $transformations): Collection
    {
        foreach ($transformations as $transform) {
            $type = $transform['type'] ?? '';
            
            $data = match ($type) {
                'filter' => $this->applyFilter($data, $transform),
                'map_column' => $this->applyMapColumn($data, $transform),
                'sort' => $this->applySort($data, $transform),
                'convert_type' => $this->applyConvertType($data, $transform),
                default => $data,
            };
        }

        return $data;
    }

    /**
     * Apply filter transformation
     */
    protected function applyFilter(Collection $data, array $config): Collection
    {
        $condition = $config['condition'] ?? '';
        
        // Basit IN koşulu: "Renk Etiketi IN ['BERJER', 'PUF']"
        if (preg_match('/(\w+[\w\s\.]*)\s+IN\s+\[(.*?)\]/i', $condition, $matches)) {
            $column = trim($matches[1]);
            $valuesStr = $matches[2];
            $values = array_map(function ($v) {
                return trim($v, " '\"");
            }, explode(',', $valuesStr));

            return $data->filter(function ($item) use ($column, $values) {
                return in_array($item[$column] ?? '', $values);
            });
        }

        // NOT NULL koşulu
        if (preg_match('/(\w+[\w\s\.]*)\s+IS\s+NOT\s+NULL/i', $condition, $matches)) {
            $column = trim($matches[1]);
            return $data->filter(function ($item) use ($column) {
                return !empty($item[$column]);
            });
        }

        return $data;
    }

    /**
     * Apply column mapping transformation
     */
    protected function applyMapColumn(Collection $data, array $config): Collection
    {
        $source = $config['source'] ?? '';
        $target = $config['target'] ?? '';
        $mapping = $config['mapping'] ?? [];

        if (empty($source) || empty($target)) {
            return $data;
        }

        return $data->map(function ($item) use ($source, $target, $mapping) {
            $sourceValue = $item[$source] ?? '';
            $item[$target] = $mapping[$sourceValue] ?? $sourceValue;
            return $item;
        });
    }

    /**
     * Apply sort transformation
     */
    protected function applySort(Collection $data, array $config): Collection
    {
        $column = $config['column'] ?? '';
        $direction = $config['direction'] ?? 'asc';

        if (empty($column)) {
            return $data;
        }

        return $direction === 'desc' 
            ? $data->sortByDesc($column) 
            : $data->sortBy($column);
    }

    /**
     * Apply type conversion
     */
    protected function applyConvertType(Collection $data, array $config): Collection
    {
        $column = $config['column'] ?? '';
        $type = $config['to_type'] ?? 'string';

        if (empty($column)) {
            return $data;
        }

        return $data->map(function ($item) use ($column, $type) {
            $value = $item[$column] ?? null;
            
            $item[$column] = match ($type) {
                'integer', 'int' => (int) $value,
                'float', 'decimal' => (float) $value,
                'string' => (string) $value,
                default => $value,
            };
            
            return $item;
        });
    }

    /**
     * Generate output files
     */
    protected function generateOutputs(Collection $data, array $outputs, Report $report): array
    {
        $generatedFiles = [];
        $storageDir = 'reports/' . $report->id;
        Storage::disk('local')->makeDirectory($storageDir);

        foreach ($outputs as $output) {
            $files = $this->generateOutput($data, $output, $storageDir, $report);
            $generatedFiles = array_merge($generatedFiles, $files);
        }

        return $generatedFiles;
    }

    /**
     * Generate a single output configuration
     */
    protected function generateOutput(Collection $data, array $output, string $storageDir, Report $report): array
    {
        $filenamePattern = $output['filename_pattern'] ?? 'output.xlsx';
        $sheets = $output['sheets'] ?? [];
        $generatedFiles = [];

        // Eğer pattern'de değişken varsa, grupla
        if (preg_match('/\{(\w+)\}/', $filenamePattern, $matches)) {
            $groupColumn = $matches[1];
            $groups = $data->groupBy($groupColumn);

            foreach ($groups as $groupValue => $groupData) {
                if (empty($groupValue)) continue;
                
                $filename = str_replace('{' . $groupColumn . '}', $groupValue, $filenamePattern);
                $file = $this->createExcelFile($groupData, $sheets, $storageDir, $filename, $report);
                if ($file) {
                    $generatedFiles[] = $file;
                }
            }
        } else {
            // Tek dosya oluştur
            $file = $this->createExcelFile($data, $sheets, $storageDir, $filenamePattern, $report);
            if ($file) {
                $generatedFiles[] = $file;
            }
        }

        return $generatedFiles;
    }

    /**
     * Create Excel file with sheets
     */
    protected function createExcelFile(
        Collection $data,
        array $sheetsConfig,
        string $storageDir,
        string $filename,
        Report $report
    ): ?ReportFile {
        if ($data->isEmpty()) {
            return null;
        }

        $excelSheets = [];

        foreach ($sheetsConfig as $sheetConfig) {
            $sheetData = $this->generateSheetData($data, $sheetConfig);
            
            if (!empty($sheetData)) {
                $excelSheets[] = [
                    'name' => substr($sheetConfig['name'] ?? 'Sheet', 0, 31),
                    'data' => $sheetData,
                ];
            }
        }

        if (empty($excelSheets)) {
            return null;
        }

        $filePath = $storageDir . '/' . $filename;
        $fullPath = Storage::disk('local')->path($filePath);
        
        $this->excelService->exportToXlsx($excelSheets, $fullPath);

        return ReportFile::create([
            'report_id' => $report->id,
            'filename' => $filename,
            'file_path' => $filePath,
            'sheet_type' => 'dynamic',
        ]);
    }

    /**
     * Generate data for a single sheet
     */
    protected function generateSheetData(Collection $data, array $config): array
    {
        $type = $config['type'] ?? 'detail';

        return match ($type) {
            'summary' => $this->generateSummarySheet($data, $config),
            'detail' => $this->generateDetailSheet($data, $config),
            default => $this->generateDetailSheet($data, $config),
        };
    }

    /**
     * Generate summary sheet (grouped aggregation)
     */
    protected function generateSummarySheet(Collection $data, array $config): array
    {
        $groupBy = $config['group_by'] ?? null;
        $aggregates = $config['aggregate'] ?? [];

        if (empty($groupBy)) {
            return [];
        }

        $grouped = $data->groupBy($groupBy);
        $result = [];

        foreach ($grouped as $groupValue => $items) {
            $row = [$groupBy => $groupValue];

            foreach ($aggregates as $agg) {
                $column = $agg['column'] ?? '';
                $function = strtoupper($agg['function'] ?? 'SUM');

                if (empty($column)) continue;

                $value = match ($function) {
                    'SUM' => $items->sum($column),
                    'COUNT' => $items->count(),
                    'AVG' => $items->avg($column),
                    'MIN' => $items->min($column),
                    'MAX' => $items->max($column),
                    default => $items->sum($column),
                };

                $row[$column] = $value;
            }

            $result[] = $row;
        }

        // Sırala
        usort($result, function ($a, $b) use ($groupBy) {
            return ($a[$groupBy] ?? '') <=> ($b[$groupBy] ?? '');
        });

        return $result;
    }

    /**
     * Generate detail sheet (filtered list)
     */
    protected function generateDetailSheet(Collection $data, array $config): array
    {
        $columns = $config['columns'] ?? [];
        $sortBy = $config['sort_by'] ?? null;

        // Sırala
        if ($sortBy) {
            $data = $data->sortBy($sortBy);
        }

        // Kolon filtrele
        if (!empty($columns)) {
            $data = $data->map(function ($item) use ($columns) {
                $filtered = [];
                foreach ($columns as $col) {
                    $filtered[$col] = $item[$col] ?? '';
                }
                return $filtered;
            });
        }

        return $data->values()->toArray();
    }
}
