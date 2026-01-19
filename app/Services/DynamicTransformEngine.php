<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\Report;
use App\Models\ReportFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
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
            
            Log::info('DynamicTransformEngine: Kurallar yüklendi', ['profile' => $profile->name]);
            
            if (empty($rules) || !isset($rules['version'])) {
                throw new \Exception('Profil kuralları bulunamadı veya geçersiz.');
            }

            // 1. Veriyi oku
            $inputConfig = $rules['input'] ?? [];
            $data = $this->readInputData($uploadedFile, $inputConfig);
            
            Log::info('DynamicTransformEngine: Veri okundu', ['count' => $data->count()]);
            
            if ($data->isEmpty()) {
                throw new \Exception('Dosyadan veri okunamadı.');
            }

            // 2. Kategori kolonunu belirle (AI kurallarından veya varsayılan)
            $categoryCol = $this->findCategoryColumn($data, $rules);
            Log::info('DynamicTransformEngine: Kategori kolonu', ['column' => $categoryCol]);

            // 3. Dönüşümleri uygula (filter, map_column, sort vb.)
            $data = $this->applyTransformations($data, $rules['transformations'] ?? []);

            // 4. Çıktıları oluştur - KATEGORİ BAZLI
            $outputs = $rules['outputs'] ?? [];
            $generatedFiles = $this->generateCategoryBasedOutputs($data, $outputs, $report, $categoryCol);
            
            Log::info('DynamicTransformEngine: Dosyalar oluşturuldu', ['count' => count($generatedFiles)]);

            $report->update(['status' => 'success']);

            return [
                'success' => true,
                'files' => $generatedFiles,
                'message' => count($generatedFiles) . ' dosya başarıyla oluşturuldu.',
            ];

        } catch (\Exception $e) {
            Log::error('DynamicTransformEngine: Hata', ['error' => $e->getMessage()]);
            
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
     * Find the category column from data or rules
     */
    protected function findCategoryColumn(Collection $data, array $rules): ?string
    {
        // 1. Kurallardan al
        $inputConfig = $rules['input'] ?? [];
        if (isset($inputConfig['key_columns']['category_col'])) {
            return $inputConfig['key_columns']['category_col'];
        }

        // 2. Transformations'dan map_column target'ını ara
        foreach ($rules['transformations'] ?? [] as $transform) {
            if (($transform['type'] ?? '') === 'map_column' && isset($transform['target'])) {
                return $transform['target'];
            }
            if (($transform['type'] ?? '') === 'group_by' && isset($transform['column'])) {
                return $transform['column'];
            }
        }

        // 3. Outputs'tan {CATEGORY} yerine kullanılacak kolonu bul
        foreach ($rules['outputs'] ?? [] as $output) {
            $pattern = $output['filename_pattern'] ?? '';
            if (preg_match('/\{(\w+)\}/', $pattern, $matches)) {
                $groupCol = $matches[1];
                $firstItem = $data->first();
                if ($firstItem && isset($firstItem[$groupCol])) {
                    return $groupCol;
                }
            }
        }

        // 4. Varsayılan kategori kolon adları dene
        $possibleCategoryColumns = ['Kategori', 'Renk Etiketi', 'Ürün Grubu', 'Grup', 'CATEGORY', 'Category'];
        $firstItem = $data->first();
        if ($firstItem) {
            foreach ($possibleCategoryColumns as $col) {
                if (isset($firstItem[$col])) {
                    return $col;
                }
            }
        }

        return null;
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
     * Apply transformations
     */
    protected function applyTransformations(Collection $data, array $transformations): Collection
    {
        foreach ($transformations as $transform) {
            $type = $transform['type'] ?? '';
            
            try {
                $newData = match ($type) {
                    'filter' => $this->applyFilter($data, $transform),
                    'map_column' => $this->applyMapColumn($data, $transform),
                    'sort' => $this->applySort($data, $transform),
                    default => $data,
                };
                
                if ($newData->isNotEmpty()) {
                    $data = $newData;
                }
            } catch (\Exception $e) {
                Log::warning("DynamicTransformEngine: {$type} hatası: " . $e->getMessage());
            }
        }

        return $data;
    }

    /**
     * Apply filter
     */
    protected function applyFilter(Collection $data, array $config): Collection
    {
        $condition = $config['condition'] ?? '';
        
        if (empty($condition)) return $data;
        
        if (preg_match('/(.+?)\s+IN\s+\[(.*?)\]/i', $condition, $matches)) {
            $column = trim($matches[1], " '\"");
            $values = array_map(fn($v) => trim($v, " '\""), explode(',', $matches[2]));
            
            $firstItem = $data->first();
            if (!$firstItem || !isset($firstItem[$column])) return $data;

            return $data->filter(fn($item) => in_array($item[$column] ?? '', $values));
        }

        if (preg_match('/(.+?)\s+IS\s+NOT\s+NULL/i', $condition, $matches)) {
            $column = trim($matches[1], " '\"");
            return $data->filter(fn($item) => !empty($item[$column]));
        }

        return $data;
    }

    /**
     * Apply column mapping
     */
    protected function applyMapColumn(Collection $data, array $config): Collection
    {
        $source = $config['source'] ?? '';
        $target = $config['target'] ?? $source;
        $mapping = $config['mapping'] ?? [];

        if (empty($source)) return $data;

        $firstItem = $data->first();
        if (!$firstItem || !isset($firstItem[$source])) return $data;

        return $data->map(function ($item) use ($source, $target, $mapping) {
            $sourceValue = $item[$source] ?? '';
            $item[$target] = !empty($mapping) ? ($mapping[$sourceValue] ?? $sourceValue) : $sourceValue;
            return $item;
        });
    }

    /**
     * Apply sort
     */
    protected function applySort(Collection $data, array $config): Collection
    {
        $column = $config['column'] ?? '';
        if (empty($column)) return $data;
        
        $direction = $config['direction'] ?? 'asc';
        return $direction === 'desc' ? $data->sortByDesc($column) : $data->sortBy($column);
    }

    /**
     * Generate category-based outputs with proper sheet names
     */
    protected function generateCategoryBasedOutputs(
        Collection $data, 
        array $outputs, 
        Report $report, 
        ?string $categoryCol
    ): array {
        $generatedFiles = [];
        $storageDir = 'private/reports/' . $report->id;
        Storage::disk('local')->makeDirectory($storageDir);

        foreach ($outputs as $output) {
            $filenamePattern = $output['filename_pattern'] ?? 'output.xlsx';
            $sheetsConfig = $output['sheets'] ?? [];
            
            // {CATEGORY} veya benzeri placeholder var mı kontrol et
            $hasPlaceholder = preg_match('/\{(\w+)\}/', $filenamePattern, $matches);
            $placeholderKey = $matches[1] ?? 'CATEGORY';
            
            // Sheet isimlerinde de placeholder var mı?
            $sheetsHavePlaceholder = false;
            foreach ($sheetsConfig as $sheet) {
                if (str_contains($sheet['name'] ?? '', '{')) {
                    $sheetsHavePlaceholder = true;
                    break;
                }
            }

            // Eğer placeholder varsa ve kategori kolonu varsa, kategoriye göre işle
            if (($hasPlaceholder || $sheetsHavePlaceholder) && $categoryCol) {
                // Kategorilere göre grupla
                $categories = $data->pluck($categoryCol)->unique()->filter()->values()->toArray();
                
                Log::info('DynamicTransformEngine: Kategoriler bulundu', [
                    'categories' => $categories,
                    'column' => $categoryCol
                ]);

                if (!empty($categories)) {
                    // TEK DOSYA İÇİNDE TÜM KATEGORİLER İÇİN AYRI SHEET'LER
                    $filename = str_replace('{' . $placeholderKey . '}', 'TÜM', $filenamePattern);
                    $filename = preg_replace('/\{[A-Z_]+\}/', '', $filename); // Diğer placeholders temizle
                    
                    $allSheets = [];
                    
                    foreach ($categories as $category) {
                        $categoryData = $data->filter(fn($item) => ($item[$categoryCol] ?? '') === $category);
                        
                        if ($categoryData->isEmpty()) continue;
                        
                        // Her kategori için sheet'leri oluştur
                        foreach ($sheetsConfig as $sheetConfig) {
                            $sheetName = $sheetConfig['name'] ?? 'Sheet';
                            
                            // {CATEGORY} yerine gerçek kategori adını koy
                            $sheetName = str_replace('{CATEGORY}', $category, $sheetName);
                            $sheetName = str_replace('{' . $placeholderKey . '}', $category, $sheetName);
                            $sheetName = substr($sheetName, 0, 31); // Excel max 31 karakter
                            
                            $sheetData = $this->generateSheetData($categoryData, $sheetConfig);
                            
                            if (!empty($sheetData)) {
                                $allSheets[] = [
                                    'name' => $sheetName,
                                    'data' => $sheetData,
                                ];
                            }
                        }
                    }

                    if (!empty($allSheets)) {
                        $file = $this->createExcelFile($allSheets, $storageDir, $filename, $report);
                        if ($file) {
                            $generatedFiles[] = $file;
                        }
                    }
                } else {
                    // Kategori bulunamadı, tek dosya oluştur
                    $file = $this->createSingleOutput($data, $sheetsConfig, $storageDir, $filenamePattern, $report);
                    if ($file) {
                        $generatedFiles[] = $file;
                    }
                }
            } else {
                // Placeholder yok, tek dosya oluştur
                $file = $this->createSingleOutput($data, $sheetsConfig, $storageDir, $filenamePattern, $report);
                if ($file) {
                    $generatedFiles[] = $file;
                }
            }
        }

        // Hiç dosya oluşturulamadıysa varsayılan çıktı oluştur
        if (empty($generatedFiles)) {
            $file = $this->createDefaultOutput($data, $storageDir, $report);
            if ($file) {
                $generatedFiles[] = $file;
            }
        }

        return $generatedFiles;
    }

    /**
     * Create single output file
     */
    protected function createSingleOutput(
        Collection $data, 
        array $sheetsConfig, 
        string $storageDir, 
        string $filename, 
        Report $report
    ): ?ReportFile {
        $filename = preg_replace('/\{[A-Z_]+\}/', 'CIKTI', $filename);
        
        $sheets = [];
        foreach ($sheetsConfig as $sheetConfig) {
            $sheetName = preg_replace('/\{[A-Z_]+\}/', '', $sheetConfig['name'] ?? 'Sheet');
            $sheetName = trim($sheetName) ?: 'Veriler';
            $sheetName = substr($sheetName, 0, 31);
            
            $sheetData = $this->generateSheetData($data, $sheetConfig);
            if (!empty($sheetData)) {
                $sheets[] = ['name' => $sheetName, 'data' => $sheetData];
            }
        }

        if (empty($sheets)) {
            $sheets[] = ['name' => 'Veriler', 'data' => $data->values()->toArray()];
        }

        return $this->createExcelFile($sheets, $storageDir, $filename, $report);
    }

    /**
     * Create default output
     */
    protected function createDefaultOutput(Collection $data, string $storageDir, Report $report): ?ReportFile
    {
        $sheets = [['name' => 'Tüm Veriler', 'data' => $data->values()->toArray()]];
        return $this->createExcelFile($sheets, $storageDir, 'VARSAYILAN_CIKTI.xlsx', $report);
    }

    /**
     * Create Excel file
     */
    protected function createExcelFile(
        array $sheets, 
        string $storageDir, 
        string $filename, 
        Report $report
    ): ?ReportFile {
        if (empty($sheets)) return null;

        $filePath = $storageDir . '/' . $filename;
        $fullPath = Storage::disk('local')->path($filePath);
        
        $this->excelService->exportToXlsx($sheets, $fullPath);

        return ReportFile::create([
            'report_id' => $report->id,
            'filename' => $filename,
            'file_path' => $filePath,
            'sheet_type' => 'dynamic',
        ]);
    }

    /**
     * Generate sheet data
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
     * Generate summary sheet
     */
    protected function generateSummarySheet(Collection $data, array $config): array
    {
        $groupBy = $config['group_by'] ?? null;
        $aggregates = $config['aggregate'] ?? [];
        $columns = $config['columns'] ?? [];

        // Eğer group_by yoksa ama columns varsa, o kolonlara göre grupla
        if (empty($groupBy) && !empty($columns)) {
            $groupBy = $columns[0];
        }

        if (empty($groupBy)) {
            return $data->values()->toArray();
        }

        $firstItem = $data->first();
        if (!$firstItem || !isset($firstItem[$groupBy])) {
            return $data->values()->toArray();
        }

        $grouped = $data->groupBy($groupBy);
        $result = [];

        foreach ($grouped as $groupValue => $items) {
            $row = [$groupBy => $groupValue];

            // Aggregates varsa uygula
            if (!empty($aggregates)) {
                foreach ($aggregates as $agg) {
                    $column = $agg['column'] ?? '';
                    $function = strtoupper($agg['function'] ?? 'SUM');
                    if (empty($column)) continue;

                    $row[$column] = match ($function) {
                        'SUM' => $items->sum($column),
                        'COUNT' => $items->count(),
                        'AVG' => $items->avg($column),
                        default => $items->sum($column),
                    };
                }
            } else {
                // Aggregates yoksa, Adet kolonunu topla veya count yap
                $adetCol = null;
                foreach (['Adet', 'Miktar', 'Quantity', 'Qty'] as $possibleCol) {
                    if (isset($firstItem[$possibleCol])) {
                        $adetCol = $possibleCol;
                        break;
                    }
                }

                if ($adetCol) {
                    $row[$adetCol] = $items->sum($adetCol);
                } else {
                    $row['Adet'] = $items->count();
                }
            }

            $result[] = $row;
        }

        usort($result, fn($a, $b) => ($a[$groupBy] ?? '') <=> ($b[$groupBy] ?? ''));

        return $result;
    }

    /**
     * Generate detail sheet
     */
    protected function generateDetailSheet(Collection $data, array $config): array
    {
        $columns = $config['columns'] ?? [];
        $sortBy = $config['sort_by'] ?? null;

        if ($sortBy) {
            $firstItem = $data->first();
            if ($firstItem && isset($firstItem[$sortBy])) {
                $data = $data->sortBy($sortBy);
            }
        }

        if (!empty($columns)) {
            $firstItem = $data->first();
            $validColumns = $firstItem ? array_filter($columns, fn($c) => isset($firstItem[$c])) : $columns;
            
            if (!empty($validColumns)) {
                $data = $data->map(function ($item) use ($validColumns) {
                    $filtered = [];
                    foreach ($validColumns as $col) {
                        $filtered[$col] = $item[$col] ?? '';
                    }
                    return $filtered;
                });
            }
        }

        return $data->values()->toArray();
    }
}
