<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\Report;
use App\Models\ReportFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * DynamicTransformEngine
 * 
 * AI tarafından üretilen JSON kurallarına göre Excel dosyalarını dönüştürür.
 * 
 * Desteklenen Transformation Tipleri:
 * - filter: Veriyi filtreler (IN, IS NOT NULL, =, !=, >, <)
 * - map_column: Kolon değerlerini eşler veya dönüştürür
 * - sort: Veriyi sıralar
 * - normalize_product: Ürün adlarını temizler ve standardize eder
 * - add_column: Yeni kolon ekler
 * - remove_column: Kolon kaldırır
 * 
 * Desteklenen Sheet Tipleri:
 * - summary: Gruplu özet (group_by + aggregate)
 * - detail: Detay listesi (sort_by + columns)
 */
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
            
            Log::info('DynamicTransformEngine: Kurallar yüklendi', [
                'profile' => $profile->name,
                'version' => $rules['version'] ?? 'unknown'
            ]);
            
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

            // 3. Dönüşümleri uygula (filter, map_column, sort, normalize_product vb.)
            $transformations = $rules['transformations'] ?? [];
            $data = $this->applyTransformations($data, $transformations);
            
            Log::info('DynamicTransformEngine: Dönüşümler uygulandı', [
                'transform_count' => count($transformations),
                'final_count' => $data->count()
            ]);

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
            Log::error('DynamicTransformEngine: Hata', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
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

    // ============================================================
    // TRANSFORMATIONS
    // ============================================================

    /**
     * Apply all transformations in order
     */
    protected function applyTransformations(Collection $data, array $transformations): Collection
    {
        foreach ($transformations as $index => $transform) {
            $type = $transform['type'] ?? '';
            
            if (empty($type)) {
                continue;
            }
            
            try {
                $beforeCount = $data->count();
                
                $newData = match ($type) {
                    'filter' => $this->applyFilter($data, $transform),
                    'map_column' => $this->applyMapColumn($data, $transform),
                    'sort' => $this->applySort($data, $transform),
                    'normalize_product' => $this->applyNormalizeProduct($data, $transform),
                    'add_column' => $this->applyAddColumn($data, $transform),
                    'remove_column' => $this->applyRemoveColumn($data, $transform),
                    'rename_column' => $this->applyRenameColumn($data, $transform),
                    default => $data,
                };
                
                // Boş collection döndüyse orijinali koru
                if ($newData->isNotEmpty()) {
                    $data = $newData;
                }
                
                Log::debug("DynamicTransformEngine: {$type} uygulandı", [
                    'index' => $index,
                    'before' => $beforeCount,
                    'after' => $data->count()
                ]);
                
            } catch (\Exception $e) {
                Log::warning("DynamicTransformEngine: {$type} hatası", [
                    'index' => $index,
                    'error' => $e->getMessage()
                ]);
                // Hata olsa bile devam et, veri kaybetme
            }
        }

        return $data;
    }

    /**
     * Apply filter transformation
     * 
     * Desteklenen formatlar:
     * - "column IN ['val1','val2']"
     * - "column IS NOT NULL"
     * - "column = 'value'"
     * - "column != 'value'"
     * - "column > 10"
     */
    protected function applyFilter(Collection $data, array $config): Collection
    {
        $condition = $config['condition'] ?? '';
        
        if (empty($condition)) {
            return $data;
        }
        
        // IN operatörü
        if (preg_match('/(.+?)\s+IN\s+\[(.*?)\]/i', $condition, $matches)) {
            $column = trim($matches[1], " '\"");
            $values = array_map(fn($v) => trim($v, " '\""), explode(',', $matches[2]));
            
            $firstItem = $data->first();
            if (!$firstItem || !isset($firstItem[$column])) {
                return $data;
            }

            return $data->filter(fn($item) => in_array($item[$column] ?? '', $values));
        }

        // IS NOT NULL
        if (preg_match('/(.+?)\s+IS\s+NOT\s+NULL/i', $condition, $matches)) {
            $column = trim($matches[1], " '\"");
            return $data->filter(fn($item) => !empty($item[$column]));
        }

        // Eşitlik (=)
        if (preg_match('/(.+?)\s*=\s*[\'"](.+?)[\'"]/i', $condition, $matches)) {
            $column = trim($matches[1], " '\"");
            $value = $matches[2];
            return $data->filter(fn($item) => ($item[$column] ?? '') === $value);
        }

        // Eşitsizlik (!=)
        if (preg_match('/(.+?)\s*!=\s*[\'"](.+?)[\'"]/i', $condition, $matches)) {
            $column = trim($matches[1], " '\"");
            $value = $matches[2];
            return $data->filter(fn($item) => ($item[$column] ?? '') !== $value);
        }

        return $data;
    }

    /**
     * Apply column mapping transformation
     * 
     * Config:
     * - source: Kaynak kolon
     * - target: Hedef kolon (opsiyonel, varsayılan = source)
     * - mapping: { "eski_değer": "yeni_değer" } 
     * - regex: Regex pattern (opsiyonel)
     * - regex_replacement: Regex ile değiştirilecek değer
     */
    protected function applyMapColumn(Collection $data, array $config): Collection
    {
        $source = $config['source'] ?? '';
        $target = $config['target'] ?? $source;
        $mapping = $config['mapping'] ?? [];
        $regexPattern = $config['regex'] ?? null;
        $regexReplacement = $config['regex_replacement'] ?? '';

        if (empty($source)) {
            return $data;
        }

        $firstItem = $data->first();
        if (!$firstItem || !isset($firstItem[$source])) {
            return $data;
        }

        return $data->map(function ($item) use ($source, $target, $mapping, $regexPattern, $regexReplacement) {
            $sourceValue = (string)($item[$source] ?? '');
            
            // Önce mapping kontrol et
            if (!empty($mapping) && isset($mapping[$sourceValue])) {
                $item[$target] = $mapping[$sourceValue];
            }
            // Partial match için mapping'de ara
            elseif (!empty($mapping)) {
                $matched = false;
                foreach ($mapping as $key => $value) {
                    if (stripos($sourceValue, $key) !== false) {
                        $item[$target] = $value;
                        $matched = true;
                        break;
                    }
                }
                if (!$matched && $regexPattern) {
                    $item[$target] = preg_replace($regexPattern, $regexReplacement, $sourceValue);
                } elseif (!$matched) {
                    $item[$target] = $sourceValue;
                }
            }
            // Regex varsa uygula
            elseif ($regexPattern) {
                $item[$target] = preg_replace($regexPattern, $regexReplacement, $sourceValue);
            }
            else {
                $item[$target] = $sourceValue;
            }
            
            return $item;
        });
    }

    /**
     * Apply sort transformation
     */
    protected function applySort(Collection $data, array $config): Collection
    {
        $column = $config['column'] ?? '';
        if (empty($column)) {
            return $data;
        }
        
        $firstItem = $data->first();
        if (!$firstItem || !isset($firstItem[$column])) {
            return $data;
        }
        
        $direction = strtolower($config['direction'] ?? 'asc');
        return $direction === 'desc' ? $data->sortByDesc($column) : $data->sortBy($column);
    }

    /**
     * Apply product name normalization (ULTRA FINAL VERSION)
     * 
     * Ürün adlarını temizler ve standardize eder:
     * - Marka prefix'lerini kaldırır (Zem, Zemary...)
     * - Model kodlarını kaldırır (ZEMLİNES, ZEMLOUCA, ZEMJRV, ZEMOR...)
     * - Sondaki ZEM kodlarını kaldırır (... ZEMOR)
     * - Renk etiketlerini kaldırır ([Renk: ...])
     * - Beden bilgilerini kaldırır (one size, tek beden)
     * - Entegratör kodlarını kaldırır (TYZ, TCY, TBZ...)
     * - Alfanumerik ürün kodlarını kaldırır (LOUCA01, TCY00468831285...)
     * - Case normalizasyonu (lowercase) - Bench/bench farkını çözer
     * 
     * Config:
     * - source: Kaynak kolon (varsayılan: 'Ürün')
     * - target: Hedef kolon (varsayılan: source)
     */
    protected function applyNormalizeProduct(Collection $data, array $config): Collection
    {
        $source = $config['source'] ?? 'Ürün';
        $target = $config['target'] ?? $source;
        
        $firstItem = $data->first();
        if (!$firstItem || !isset($firstItem[$source])) {
            // Source kolon yoksa alternatif dene
            $alternatives = ['Ürün', 'Ürün Adı', 'Product', 'ProductName', 'Urun'];
            foreach ($alternatives as $alt) {
                if (isset($firstItem[$alt])) {
                    $source = $alt;
                    break;
                }
            }
        }
        
        return $data->map(function ($item) use ($source, $target) {
            $text = (string)($item[$source] ?? '');
            
            if (empty($text)) {
                $item[$target] = $text;
                return $item;
            }

            // 1) Baştaki "Zem " prefix
            $text = preg_replace('/^\s*Zem\s+/iu', '', $text);

            // 2) Sondaki [Renk: ...] kısmı
            $text = preg_replace('/\[\s*Renk\s*:\s*.*?\]\s*$/iu', '', $text);

            // 3) "one size" varyasyonları
            $text = preg_replace('/\bone\s*size\b/iu', '', $text);
            $text = preg_replace('/\btek\s*beden\b/iu', '', $text);

            // 4) ZEM ile başlayan tüm ürün kodlarını temizle (min 2 karakter - ZEMOR dahil)
            // Örn: ZEMLİNES, ZEMLOUCA, ZEMOR, ZEMJRV...
            $text = preg_replace('/\bZEM[0-9A-ZÇĞİÖŞÜ_-]{2,}\b/iu', '', $text);

            // 5) Sonda tek kelime olarak duran ZEM kodları (örn: "... ZEMOR")
            $text = preg_replace('/\s+ZEM[0-9A-ZÇĞİÖŞÜ_-]{2,}\s*$/iu', '', $text);

            // 6) Entegratör/kod prefixleri (varsa) - güvenli temizlik
            $text = preg_replace('/\b(?:TYZ|TCY|TYC|TBZ|TBY|TCT)[0-9A-ZÇĞİÖŞÜ_-]{3,}\b/iu', '', $text);

            // 7) Rakam içeren uzun alfanumerik kodlar (örn: LOUCA01, TCY00468831285)
            // (>=6 karakter ve içinde en az 1 rakam varsa)
            $text = preg_replace('/\b(?=[0-9A-ZÇĞİÖŞÜ-]{6,}\b)(?=.*\d)[0-9A-ZÇĞİÖŞÜ-]{6,}\b/iu', '', $text);

            // 8) Noktalama/boşluk standardizasyonu
            $text = preg_replace('/\s*,\s*/u', ', ', $text);
            $text = preg_replace('/\s*\/\s*/u', '/', $text);
            $text = preg_replace('/\s+/u', ' ', trim($text));
            $text = trim($text, " ,");

            // 9) Case standardizasyonu: birleşme garantisi (Bench/bench, Teddy/teddy)
            $text = mb_strtolower($text, 'UTF-8');

            $item[$target] = $text;
            return $item;
        });
    }

    /**
     * Add new column with value
     */
    protected function applyAddColumn(Collection $data, array $config): Collection
    {
        $column = $config['column'] ?? '';
        $value = $config['value'] ?? '';
        
        if (empty($column)) {
            return $data;
        }
        
        return $data->map(function ($item) use ($column, $value) {
            $item[$column] = $value;
            return $item;
        });
    }

    /**
     * Remove column
     */
    protected function applyRemoveColumn(Collection $data, array $config): Collection
    {
        $column = $config['column'] ?? '';
        
        if (empty($column)) {
            return $data;
        }
        
        return $data->map(function ($item) use ($column) {
            unset($item[$column]);
            return $item;
        });
    }

    /**
     * Rename column
     */
    protected function applyRenameColumn(Collection $data, array $config): Collection
    {
        $from = $config['from'] ?? '';
        $to = $config['to'] ?? '';
        
        if (empty($from) || empty($to)) {
            return $data;
        }
        
        return $data->map(function ($item) use ($from, $to) {
            if (isset($item[$from])) {
                $item[$to] = $item[$from];
                unset($item[$from]);
            }
            return $item;
        });
    }

    // ============================================================
    // OUTPUT GENERATION
    // ============================================================

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
                if (isset($sheet['name']) && preg_match('/\{CATEGORY\}/', $sheet['name'])) {
                    $sheetsHavePlaceholder = true;
                    break;
                }
            }

            if (($hasPlaceholder || $sheetsHavePlaceholder) && $categoryCol) {
                $categories = $data->pluck($categoryCol)->unique()->filter()->values()->toArray();
                
                if (empty($categories)) {
                    Log::warning("DynamicTransformEngine: Kategori değeri bulunamadı", ['col' => $categoryCol]);
                    $file = $this->createSingleOutput($data, $sheetsConfig, $storageDir, $filenamePattern, $report);
                    if ($file) $generatedFiles[] = $file;
                    continue;
                }

                Log::info("DynamicTransformEngine: Kategoriler", ['categories' => $categories]);

                // TEK DOSYA İÇİNDE TÜM KATEGORİLER İÇİN AYRI SHEET'LER
                $filename = str_replace('{' . $placeholderKey . '}', 'TÜM', $filenamePattern);
                $filename = preg_replace('/\{[A-Z_]+\}/', '', $filename);
                $filename = trim($filename, '_ ');
                
                $allSheets = [];
                foreach ($categories as $category) {
                    $categoryData = $data->filter(fn($item) => ($item[$categoryCol] ?? '') === $category);
                    
                    if ($categoryData->isEmpty()) {
                        continue;
                    }

                    foreach ($sheetsConfig as $sheetConfig) {
                        $sheetName = $sheetConfig['name'] ?? 'Sheet';
                        
                        // {CATEGORY} yerine gerçek kategori adını koy
                        $sheetName = str_replace('{CATEGORY}', $category, $sheetName);
                        $sheetName = str_replace('{' . $placeholderKey . '}', $category, $sheetName);
                        $sheetName = mb_substr($sheetName, 0, 31); // Excel max 31 karakter
                        
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
                // Kategori yok, tek dosya oluştur
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
        // Filename'den placeholder temizle
        $filename = preg_replace('/\{[A-Z_]+\}/', '', $filename);
        $filename = trim($filename, '_ ');
        
        if (empty($filename)) {
            $filename = 'output.xlsx';
        }
        
        $sheets = [];
        foreach ($sheetsConfig as $sheetConfig) {
            $sheetName = $sheetConfig['name'] ?? 'Sheet';
            $sheetName = preg_replace('/\{[A-Z_]+\}/', '', $sheetName);
            $sheetName = mb_substr(trim($sheetName), 0, 31);
            
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
        if (empty($sheets)) {
            return null;
        }

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

    // ============================================================
    // SHEET DATA GENERATION
    // ============================================================

    /**
     * Generate sheet data based on type
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
     * Generate summary sheet (grouped with aggregations)
     * 
     * Config:
     * - group_by: Gruplama kolonu
     * - aggregate veya aggregations: Toplama kuralları (geriye uyumluluk)
     * - columns: Gösterilecek kolonlar
     */
    protected function generateSummarySheet(Collection $data, array $config): array
    {
        $groupBy = $config['group_by'] ?? null;
        
        // GERIYE UYUMLULUK: hem "aggregate" hem "aggregations" kabul et
        $aggregates = $config['aggregate'] ?? $config['aggregations'] ?? [];
        
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
            // Alternatif kolon isimleri dene
            $alternatives = ['Ürün', 'Ürün Adı', 'Product'];
            foreach ($alternatives as $alt) {
                if (isset($firstItem[$alt])) {
                    $groupBy = $alt;
                    break;
                }
            }
            
            if (!isset($firstItem[$groupBy])) {
                return $data->values()->toArray();
            }
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
                    
                    if (empty($column)) {
                        continue;
                    }

                    $row[$column] = match ($function) {
                        'SUM' => $items->sum(function($item) use ($column) {
                            return (float)($item[$column] ?? 0);
                        }),
                        'COUNT' => $items->count(),
                        'AVG' => $items->avg(function($item) use ($column) {
                            return (float)($item[$column] ?? 0);
                        }),
                        'MIN' => $items->min($column),
                        'MAX' => $items->max($column),
                        default => $items->sum(function($item) use ($column) {
                            return (float)($item[$column] ?? 0);
                        }),
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
                    $row[$adetCol] = $items->sum(function($item) use ($adetCol) {
                        return (float)($item[$adetCol] ?? 0);
                    });
                } else {
                    $row['Adet'] = $items->count();
                }
            }

            $result[] = $row;
        }

        // Gruplamaya göre sırala
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
