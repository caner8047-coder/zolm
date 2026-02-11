<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\Report;
use App\Models\ReportFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Collator;   // Türkçe sıralama için
use Normalizer; // Unicode (İ harfi) birleştirme için

/**
 * DynamicTransformEngine (v1.7 - Filter & Backward Compatible Edition)
 *
 * GÜNCELLEME (v1.7):
 * - Sheet Level Filtering: Tek dosya içinde verileri sayfalara göre süzme özelliği eklendi.
 * - Backward Compatibility: 'filter' parametresi yoksa eski sistem aynen çalışır.
 * - v1.6'daki tüm temizlik özellikleri (Cleaner, NFC, Skeleton Key) korundu.
 */
class DynamicTransformEngine
{
    protected ExcelService $excelService;

    public function __construct(ExcelService $excelService)
    {
        $this->excelService = $excelService;
    }

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

            // 1) Veriyi oku
            $inputConfig = $rules['input'] ?? [];
            $data = $this->readInputData($uploadedFile, $inputConfig);

            Log::info('DynamicTransformEngine: Veri okundu', ['count' => $data->count()]);

            if ($data->isEmpty()) {
                throw new \Exception('Dosyadan veri okunamadı.');
            }

            // 2) Kategori kolonunu belirle
            $categoryCol = $this->findCategoryColumn($data, $rules);
            Log::info('DynamicTransformEngine: Kategori kolonu', ['column' => $categoryCol]);

            // 3) Dönüşümleri uygula
            $transformations = $rules['transformations'] ?? [];
            $data = $this->applyTransformations($data, $transformations);

            Log::info('DynamicTransformEngine: Dönüşümler uygulandı', [
                'transform_count' => count($transformations),
                'final_count' => $data->count()
            ]);

            // 4) Çıktıları üret
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

    protected function findCategoryColumn(Collection $data, array $rules): ?string
    {
        $inputConfig = $rules['input'] ?? [];
        if (isset($inputConfig['key_columns']['category_col'])) {
            return $inputConfig['key_columns']['category_col'];
        }

        foreach ($rules['transformations'] ?? [] as $transform) {
            if (($transform['type'] ?? '') === 'map_column' && isset($transform['target'])) {
                return $transform['target'];
            }
        }

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

        $possible = ['Kategori', 'Renk Etiketi', 'Ürün Grubu', 'Grup', 'GRUP', 'CATEGORY', 'Category'];
        $firstItem = $data->first();
        if ($firstItem) {
            foreach ($possible as $col) {
                if (isset($firstItem[$col])) {
                    return $col;
                }
            }
        }

        return null;
    }

    protected function readInputData(UploadedFile $file, array $inputConfig): Collection
    {
        $sheetName = $inputConfig['sheet_name'] ?? null;
        return $this->excelService->importOrderXls($file, $sheetName);
    }

    // ============================================================
    // TRANSFORMATIONS
    // ============================================================

    protected function applyTransformations(Collection $data, array $transformations): Collection
    {
        foreach ($transformations as $index => $transform) {
            $type = $transform['type'] ?? '';
            if ($type === '') {
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
            }
        }

        return $data;
    }

    protected function applyFilter(Collection $data, array $config): Collection
    {
        $condition = $config['condition'] ?? '';
        if ($condition === '') {
            return $data;
        }

        if (preg_match('/(.+?)\s+IN\s+\[(.*?)\]/i', $condition, $matches)) {
            $column = trim($matches[1], " '\"");
            $values = array_map(fn($v) => trim($v, " '\""), explode(',', $matches[2]));

            $firstItem = $data->first();
            if (!$firstItem || !isset($firstItem[$column])) {
                return $data;
            }

            return $data->filter(fn($item) => in_array($item[$column] ?? '', $values, true));
        }

        if (preg_match('/(.+?)\s+IS\s+NOT\s+NULL/i', $condition, $matches)) {
            $column = trim($matches[1], " '\"");
            return $data->filter(function($item) use ($column) {
                if (!array_key_exists($column, $item)) return false;
                $v = $item[$column];
                if ($v === null) return false;
                $s = is_string($v) ? trim($v) : (string)$v;
                return $s !== '';
            });
        }

        if (preg_match('/(.+?)\s*=\s*[\'"](.+?)[\'"]/i', $condition, $matches)) {
            $column = trim($matches[1], " '\"");
            $value = $matches[2];
            return $data->filter(fn($item) => ($item[$column] ?? '') === $value);
        }

        if (preg_match('/(.+?)\s*!=\s*[\'"](.+?)[\'"]/i', $condition, $matches)) {
            $column = trim($matches[1], " '\"");
            $value = $matches[2];
            return $data->filter(fn($item) => ($item[$column] ?? '') !== $value);
        }

        return $data;
    }

    protected function applyMapColumn(Collection $data, array $config): Collection
    {
        $source = $config['source'] ?? '';
        $target = $config['target'] ?? $source;
        $mapping = $config['mapping'] ?? [];
        $regexPattern = $config['regex'] ?? null;
        $regexReplacement = $config['regex_replacement'] ?? '';
        $onlyIfEmpty = (bool)($config['only_if_empty'] ?? false);

        if ($source === '') {
            return $data;
        }

        $firstItem = $data->first();
        if (!$firstItem || !isset($firstItem[$source])) {
            return $data;
        }

        return $data->map(function ($item) use ($source, $target, $mapping, $regexPattern, $regexReplacement, $onlyIfEmpty) {
            $sourceValue = (string)($item[$source] ?? '');

            // Stock code guard
            $isStockContext = (stripos($source, 'stok') !== false) || (stripos($target, 'urun_key') !== false) || ($target === 'URUN_KEY');
            if ($isStockContext) {
                $svTrim = trim($sourceValue);
                if ($svTrim === '' || $svTrim === '0') {
                    return $item;
                }
            }

            if ($onlyIfEmpty && isset($item[$target]) && trim((string)$item[$target]) !== '') {
                return $item;
            }

            if (!empty($mapping) && isset($mapping[$sourceValue])) {
                $item[$target] = $mapping[$sourceValue];
            } elseif (!empty($mapping)) {
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
            } elseif ($regexPattern) {
                $item[$target] = preg_replace($regexPattern, $regexReplacement, $sourceValue);
            } else {
                $item[$target] = $sourceValue;
            }

            return $item;
        });
    }

    protected function applySort(Collection $data, array $config): Collection
    {
        $column = $config['column'] ?? '';
        if ($column === '') {
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
     * normalize_product (v1.6 GÜNCELLENDİ)
     * - Tsccv, V01, V03 gibi manuel temizlik kuralları eklendi.
     * - Unicode NFC Normalizasyonu eklendi.
     * - Skeleton Key (İskelet Anahtar) oluşturma devam ediyor.
     */
    protected function applyNormalizeProduct(Collection $data, array $config): Collection
    {
        $source = $config['source'] ?? 'Ürün';
        $keyTarget = $config['key_target'] ?? null;
        $displayTarget = $config['display_target'] ?? null;
        $overwriteColumn = $config['overwrite_column'] ?? $source;
        $titleCaseTr = (bool)($config['title_case_tr'] ?? false);
        $stopwords = $config['stopwords'] ?? ['one size', 'tek beden', 'tek ebat'];
        $stripPatterns = $config['strip_patterns'] ?? [];
        $replacePatterns = $config['replace_patterns'] ?? [];

        $firstItem = $data->first();
        if ($firstItem && !isset($firstItem[$source])) {
            foreach (['Ürün', 'Ürün Adı', 'Product', 'ProductName', 'Urun'] as $alt) {
                if (isset($firstItem[$alt])) {
                    $source = $alt;
                    break;
                }
            }
        }

        // --- 1. ÖZEL TEMİZLİK LİSTESİ (Manuel Eklemeler) ---
        // JSON'dan gelmese bile bu kodları her zaman siler.
        $hardcodedStrip = [
            '/Tsccv/iu',         // Sizin belirttiğiniz kod
            '/V[0-9]{2,}/iu',    // V01, V02, V03 gibi versiyon kodları
            '/ -$/',             // Sonda kalan tire işareti
        ];

        // JSON'dan gelenleri regex yap
        $stripPatterns = is_array($stripPatterns) ? $stripPatterns : [];
        $stripPatterns = array_values(array_filter(array_map(function ($p) {
            if (!is_string($p) || $p === '') return null;
            $first = mb_substr($p, 0, 1);
            if (in_array($first, ['/', '#', '~'])) return $p;
            return '/' . str_replace('/', '\/', $p) . '/iu';
        }, $stripPatterns)));

        // Hepsini birleştir
        $allPatterns = array_merge($hardcodedStrip, $stripPatterns);

        $replacePatterns = is_array($replacePatterns) ? $replacePatterns : [];
        $replacePatterns = array_values(array_filter(array_map(function ($rp) {
            if (!is_array($rp)) return null;
            $p = (string)($rp['pattern'] ?? '');
            $r = (string)($rp['replacement'] ?? '');
            if ($p === '') return null;
            $first = mb_substr($p, 0, 1);
            if (!in_array($first, ['/', '#', '~'])) {
                $p = '/' . str_replace('/', '\/', $p) . '/iu';
            }
            return ['pattern' => $p, 'replacement' => $r];
        }, $replacePatterns)));

        $stopwords = is_array($stopwords) ? $stopwords : [];

        return $data->map(function ($item) use (
            $source,
            $overwriteColumn,
            $keyTarget,
            $displayTarget,
            $titleCaseTr,
            $stopwords,
            $allPatterns,
            $replacePatterns
        ) {
            $raw = (string)($item[$source] ?? '');

            // --- 0. UNICODE NORMALIZASYONU (NFC) ---
            // "i" harfinin iki parçalı (nokta + ı) olmasını engeller, tek parça yapar.
            if (class_exists('Normalizer')) {
                $raw = \Normalizer::normalize($raw, \Normalizer::FORM_C);
            }

            // 1. Görünmez Boşluk (NBSP) Temizliği
            $text = str_replace(["\xc2\xa0", "\xa0"], ' ', $raw);

            // 2. Regex temizlikleri (Tsccv burada silinir)
            foreach ($allPatterns as $pattern) {
                $text = preg_replace($pattern, '', $text);
            }

            foreach ($stopwords as $w) {
                if (!is_string($w) || $w === '') continue;
                $wq = preg_quote($w, '/');
                $text = preg_replace('/\b' . $wq . '\b/iu', '', $text);
            }

            foreach ($replacePatterns as $rp) {
                $text = preg_replace($rp['pattern'], $rp['replacement'], $text);
            }

            // 3. Noktalama ve boşluk normalizasyonu
            $text = preg_replace('/\s*,\s*/u', ', ', $text);
            $text = preg_replace('/\s*\/\s*/u', '/', $text);
            $text = preg_replace('/\s*-\s*/u', ' - ', $text);
            $text = preg_replace('/\s+/u', ' ', trim($text));
            $text = trim($text, " ,");

            if ($text === '') {
                $text = trim($raw);
            }

            // --- SKELETON KEY OLUŞTURMA ---
            $skeleton = mb_strtolower($text, 'UTF-8');
            $map = ['ı'=>'i', 'ğ'=>'g', 'ü'=>'u', 'ş'=>'s', 'ö'=>'o', 'ç'=>'c', 'İ'=>'i', 'I'=>'i', 'Ğ'=>'g', 'Ü'=>'u', 'Ş'=>'s', 'Ö'=>'o', 'Ç'=>'c'];
            $skeleton = strtr($skeleton, $map);
            $skeleton = preg_replace('/[^a-z0-9]/', '', $skeleton);
            
            $item['URUN_KEY'] = $skeleton;

            $display = $text;
            if ($titleCaseTr) {
                $display = mb_convert_case($display, MB_CASE_TITLE, 'UTF-8');
            }

            if ($keyTarget) {
                 $item[$keyTarget] = $item[$keyTarget] ?? $skeleton; 
            }
            if ($displayTarget) {
                $item[$displayTarget] = $display;
            }

            // DETAIL sheet temizliği için Ürün kolonunu overwrite et
            $item[$overwriteColumn] = $display;

            return $item;
        });
    }

    protected function applyAddColumn(Collection $data, array $config): Collection
    {
        $column = $config['column'] ?? '';
        $value = $config['value'] ?? '';
        if ($column === '') return $data;

        return $data->map(function ($item) use ($column, $value) {
            $item[$column] = $value;
            return $item;
        });
    }

    protected function applyRemoveColumn(Collection $data, array $config): Collection
    {
        $column = $config['column'] ?? '';
        if ($column === '') return $data;

        return $data->map(function ($item) use ($column) {
            unset($item[$column]);
            return $item;
        });
    }

    protected function applyRenameColumn(Collection $data, array $config): Collection
    {
        $from = $config['from'] ?? '';
        $to = $config['to'] ?? '';
        if ($from === '' || $to === '') return $data;

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

    protected function generateCategoryBasedOutputs(Collection $data, array $outputs, Report $report, ?string $categoryCol): array
    {
        $generatedFiles = [];
        $storageDir = 'private/reports/' . $report->id;
        Storage::disk('local')->makeDirectory($storageDir);

        foreach ($outputs as $output) {
            $filenamePattern = $output['filename_pattern'] ?? 'output.xlsx';
            $sheetsConfig = $output['sheets'] ?? [];

            $hasPlaceholder = preg_match('/\{(\w+)\}/', $filenamePattern, $matches);
            $placeholderKey = $matches[1] ?? 'CATEGORY';

            if ($hasPlaceholder && $categoryCol) {
                $categories = $data->pluck($categoryCol)->unique()->filter()->values()->toArray();

                foreach ($categories as $category) {
                    $categoryData = $data->filter(fn($item) => ($item[$categoryCol] ?? '') === $category);
                    if ($categoryData->isEmpty()) continue;

                    $filename = str_replace('{' . $placeholderKey . '}', $category, $filenamePattern);
                    $filename = str_replace('{CATEGORY}', $category, $filename);
                    $filename = preg_replace('/\{[A-Z_]+\}/', '', $filename);
                    $filename = trim($filename, '_ ');

                    $sheets = [];
                    foreach ($sheetsConfig as $sheetConfig) {
                        $sheetName = $sheetConfig['name'] ?? 'Sheet';
                        $sheetName = str_replace('{CATEGORY}', $category, $sheetName);
                        $sheetName = str_replace('{' . $placeholderKey . '}', $category, $sheetName);
                        $sheetName = mb_substr($sheetName, 0, 31);

                        // --- KRİTİK DEĞİŞİKLİK: Eski Profiller için Uyum ---
                        // Eğer JSON'da 'filter' yoksa, eski yöntemle sadece o kategoriye ait veriyi gönder.
                        // Yeni JSON'da 'filter' varsa, tüm data'yı gönder, filtreleme içeride yapılsın.
                        $hasFilter = isset($sheetConfig['filter']) && is_array($sheetConfig['filter']);
                        $dataToPass = $hasFilter ? $data : $categoryData;

                        $sheetData = $this->generateSheetData($dataToPass, $sheetConfig);
                        if (!empty($sheetData)) {
                            $sheets[] = ['name' => $sheetName, 'data' => $sheetData];
                        }
                    }

                    if (!empty($sheets)) {
                        $file = $this->createExcelFile($sheets, $storageDir, $filename, $report);
                        if ($file) $generatedFiles[] = $file;
                    }
                }
            } else {
                $file = $this->createSingleOutput($data, $sheetsConfig, $storageDir, $filenamePattern, $report);
                if ($file) $generatedFiles[] = $file;
            }
        }

        if (empty($generatedFiles)) {
            $file = $this->createDefaultOutput($data, $storageDir, $report);
            if ($file) $generatedFiles[] = $file;
        }

        return $generatedFiles;
    }

    protected function createSingleOutput(Collection $data, array $sheetsConfig, string $storageDir, string $filename, Report $report): ?ReportFile
    {
        $filename = preg_replace('/\{[A-Z_]+\}/', '', $filename);
        $filename = trim($filename, '_ ');
        if ($filename === '') $filename = 'output.xlsx';

        $sheets = [];
        foreach ($sheetsConfig as $sheetConfig) {
            $sheetName = $sheetConfig['name'] ?? 'Sheet';
            $sheetName = preg_replace('/\{[A-Z_]+\}/', '', $sheetName);
            $sheetName = mb_substr(trim($sheetName), 0, 31);

            $sheetData = $this->generateSheetData($data, $sheetConfig);
            if (!empty($sheetData)) $sheets[] = ['name' => $sheetName, 'data' => $sheetData];
        }

        if (empty($sheets)) {
            $sheets[] = ['name' => 'Veriler', 'data' => $data->values()->toArray()];
        }

        return $this->createExcelFile($sheets, $storageDir, $filename, $report);
    }

    protected function createDefaultOutput(Collection $data, string $storageDir, Report $report): ?ReportFile
    {
        $sheets = [['name' => 'Tüm Veriler', 'data' => $data->values()->toArray()]];
        return $this->createExcelFile($sheets, $storageDir, 'VARSAYILAN_CIKTI.xlsx', $report);
    }

    protected function createExcelFile(array $sheets, string $storageDir, string $filename, Report $report): ?ReportFile
    {
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

    // ============================================================
    // SHEET DATA (GÜNCELLENDİ)
    // ============================================================

    protected function generateSheetData(Collection $data, array $config): array
    {
        // --- 1. YENİ EKLENEN KISIM: SAYFA BAZLI FİLTRELEME ---
        // Sadece JSON'da "filter" komutu varsa çalışır.
        // Yoksa, data'ya dokunmaz (eski sistem devam eder).
        if (isset($config['filter']) && is_array($config['filter'])) {
            $col = $config['filter']['column'] ?? '';
            $val = $config['filter']['value'] ?? '';
            if ($col && $val) {
                // Sadece istenen kategoriye ait ürünleri al
                $data = $data->filter(fn($item) => ($item[$col] ?? '') === $val);
            }
        }
        // -----------------------------------------------------

        $type = $config['type'] ?? 'detail';
        return match ($type) {
            'summary' => $this->generateSummarySheet($data, $config),
            'detail' => $this->generateDetailSheet($data, $config),
            default => $this->generateDetailSheet($data, $config),
        };
    }

    protected function generateSummarySheet(Collection $data, array $config): array
    {
        $groupBy = $config['group_by'] ?? null;
        $displayColumn = $config['display_column'] ?? null;
        $renameColumns = $config['rename_columns'] ?? [];
        $aggregates = $config['aggregate'] ?? $config['aggregations'] ?? [];
        $columns = $config['columns'] ?? [];

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

        // --- SKELETON KEY İLE GRUPLAMA ---
        $useSkeletonKey = ($groupBy === 'Ürün' && isset($firstItem['URUN_KEY']));
        $groupingKey = $useSkeletonKey ? 'URUN_KEY' : $groupBy;
        
        $grouped = $data->groupBy($groupingKey);

        $result = [];

        foreach ($grouped as $groupValue => $items) {
            $outputLabelCol = $displayColumn ?: $groupBy;
            $row = [];

            if ($useSkeletonKey) {
                // En uzun/temiz ismi seç
                $bestName = $items->first()['Ürün'];
                foreach ($items as $item) {
                    if (mb_strlen($item['Ürün']) > mb_strlen($bestName)) {
                        $bestName = $item['Ürün'];
                    }
                }
                $row[$outputLabelCol] = $bestName;
            } elseif ($displayColumn) {
                $best = (string)$groupValue;
                $bestLen = mb_strlen($best, 'UTF-8');
                foreach ($items as $it) {
                    $cand = isset($it[$displayColumn]) ? trim((string)$it[$displayColumn]) : '';
                    if ($cand === '') continue;
                    $len = mb_strlen($cand, 'UTF-8');
                    if ($len > $bestLen) {
                        $best = $cand;
                        $bestLen = $len;
                    }
                }
                $row[$outputLabelCol] = $best;
            } else {
                $row[$outputLabelCol] = $groupValue;
            }

            if (!empty($aggregates)) {
                foreach ($aggregates as $agg) {
                    $column = $agg['column'] ?? '';
                    $function = strtoupper($agg['function'] ?? 'SUM');
                    if ($column === '') continue;

                    $row[$column] = match ($function) {
                        'SUM' => $items->sum(fn($item) => (float)($item[$column] ?? 0)),
                        'COUNT' => $items->count(),
                        'AVG' => $items->avg(fn($item) => (float)($item[$column] ?? 0)),
                        'MIN' => $items->min($column),
                        'MAX' => $items->max($column),
                        default => $items->sum(fn($item) => (float)($item[$column] ?? 0)),
                    };
                }
            } else {
                $adetCol = isset($firstItem['Adet']) ? 'Adet' : null;
                $row[$adetCol ?? 'Adet'] = $adetCol
                    ? $items->sum(fn($item) => (float)($item[$adetCol] ?? 0))
                    : $items->count();
            }

            $result[] = $row;
        }

        $sortKey = $displayColumn ?: $groupBy;

        // --- TÜRKÇE A-Z SIRALAMA ---
        if (class_exists('Collator')) {
            $collator = new \Collator('tr_TR');
            usort($result, function($a, $b) use ($collator, $sortKey) {
                $valA = (string)($a[$sortKey] ?? '');
                $valB = (string)($b[$sortKey] ?? '');
                return $collator->compare($valA, $valB);
            });
        } else {
            usort($result, function($a, $b) use ($sortKey) {
                $valA = (string)($a[$sortKey] ?? '');
                $valB = (string)($b[$sortKey] ?? '');
                
                $map = ['İ'=>'I', 'ı'=>'i', 'Ş'=>'S', 'ş'=>'s', 'Ğ'=>'G', 'ğ'=>'g', 'Ü'=>'U', 'ü'=>'u', 'Ö'=>'O', 'ö'=>'o', 'Ç'=>'C', 'ç'=>'c'];
                $valA = strtr($valA, $map);
                $valB = strtr($valB, $map);
                
                return strcasecmp($valA, $valB);
            });
        }

        if (is_array($renameColumns) && !empty($renameColumns)) {
            $result = array_map(function ($row) use ($renameColumns) {
                $newRow = [];
                foreach ($row as $k => $v) {
                    $newKey = $renameColumns[$k] ?? $k;
                    $newRow[$newKey] = $v;
                }
                return $newRow;
            }, $result);
        }

        return $result;
    }

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