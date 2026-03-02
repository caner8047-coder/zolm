<?php

namespace App\Services;

use App\Models\OptimizationReport;
use App\Models\OptimizationReportItem;
use App\Models\ProductCost;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Tarife Optimizasyon Motoru (Profit Maximizer Engine)
 * 
 * Trendyol komisyon tarifeleri ile ürün maliyetlerini çapraz analiz ederek
 * maksimum net kârı sağlayan fiyat senaryosunu belirler.
 * 
 * Net Kâr = (Fiyat × (1 - Komisyon%)) - (Üretim Maliyeti + Kargo Maliyeti)
 */
class TariffOptimizerService
{
    protected ExcelService $excelService;

    // Maliyet dosyası kolon eşleştirme alternatifleri
    protected array $costColumnAliases = [
        'stock_code'      => ['Stok Kodu', 'STOK KODU', 'StokKodu', 'stok_kodu', 'SATICI STOK KODU'],
        'barcode'         => ['Barkod', 'BARKOD', 'Ürün Barkodu', 'barcode'],
        'product_name'    => ['Ürün Adı', 'ÜRÜN ADI', 'Ürün İsmi', 'ÜRÜN İSMİ', 'urun_adi'],
        'production_cost' => ['Ü.Maliyeti', 'Üretim Maliyeti', 'ÜRETIM MALIYETI', 'Üretim Mal.', 'uretim_maliyeti'],
        'shipping_cost'   => ['Kargo Maliyeti', 'KARGO MALIYETI', 'Kargo Mal.', 'kargo_maliyeti'],
    ];

    // Trendyol tarife dosyası kolon eşleştirme alternatifleri
    protected array $tariffColumnAliases = [
        'stock_code'       => ['SATICI STOK KODU', 'Satıcı Stok Kodu', 'Stok Kodu', 'STOK KODU'],
        'barcode'          => ['BARKOD', 'Barkod', 'Ürün Barkodu'],
        'product_name'     => ['ÜRÜN İSMİ', 'Ürün İsmi', 'Ürün Adı', 'ÜRÜN ADI'],
        'current_price'    => ['GÜNCEL TSF', 'Güncel TSF', 'Güncel Fiyat', 'GÜNCEL FIYAT', 'TSF'],
        'current_commission' => ['GÜNCEL KOMİSYON', 'Güncel Komisyon', 'GÜNCEL KOMISYON', 'Komisyon'],
        'tariff2_price'    => ['2.Fiyat Üst Limiti', '2. Fiyat Üst Limiti', '2.FIYAT ÜST LİMİTİ'],
        'tariff2_commission' => ['2.KOMİSYON', '2. KOMİSYON', '2.Komisyon'],
        'tariff3_price'    => ['3.Fiyat Üst Limiti', '3. Fiyat Üst Limiti', '3.FIYAT ÜST LİMİTİ'],
        'tariff3_commission' => ['3.KOMİSYON', '3. KOMİSYON', '3.Komisyon'],
        'tariff4_price'    => ['4.Fiyat Üst Limiti', '4. Fiyat Üst Limiti', '4.FIYAT ÜST LİMİTİ'],
        'tariff4_commission' => ['4.KOMİSYON', '4. KOMİSYON', '4.Komisyon'],
    ];

    public function __construct(ExcelService $excelService)
    {
        $this->excelService = $excelService;
    }

    // ===============================================
    // 1. MALİYET İMPORT
    // ===============================================

    /**
     * Maliyet Excel dosyasını oku ve product_costs tablosuna kaydet
     */
    public function importCosts(UploadedFile $file): array
    {
        try {
            $data = $this->excelService->importOrderXls($file);

            if ($data->isEmpty()) {
                return ['success' => false, 'message' => 'Dosya boş veya okunamadı.', 'count' => 0];
            }

            $columnMap = $this->mapColumns($data->first(), $this->costColumnAliases);

            if (!isset($columnMap['stock_code'])) {
                return [
                    'success' => false,
                    'message' => 'Stok Kodu sütunu bulunamadı! Beklenen: ' . implode(', ', $this->costColumnAliases['stock_code']),
                    'count'   => 0,
                ];
            }

            $imported = 0;
            $skipped = 0;

            DB::beginTransaction();

            foreach ($data as $row) {
                $stockCode = trim($row[$columnMap['stock_code']] ?? '');
                if (empty($stockCode)) {
                    $skipped++;
                    continue;
                }

                ProductCost::updateOrCreate(
                    ['stock_code' => $stockCode],
                    [
                        'barcode'         => trim($row[$columnMap['barcode'] ?? '__none__'] ?? ''),
                        'product_name'    => trim($row[$columnMap['product_name'] ?? '__none__'] ?? ''),
                        'production_cost' => $this->parseNumber($row[$columnMap['production_cost'] ?? '__none__'] ?? 0),
                        'shipping_cost'   => $this->parseNumber($row[$columnMap['shipping_cost'] ?? '__none__'] ?? 0),
                    ]
                );
                $imported++;
            }

            DB::commit();

            Log::info('TariffOptimizer: Maliyet import tamamlandı', ['imported' => $imported, 'skipped' => $skipped]);

            return [
                'success' => true,
                'message' => "{$imported} ürünün maliyet bilgisi güncellendi." . ($skipped > 0 ? " ({$skipped} satır atlandı)" : ''),
                'count'   => $imported,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('TariffOptimizer: Maliyet import hatası', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Import hatası: ' . $e->getMessage(), 'count' => 0];
        }
    }

    // ===============================================
    // 2. TARİFE ANALİZ
    // ===============================================

    /**
     * Trendyol tarife Excel'ini analiz et ve sonuçları veritabanına kaydet
     */
    public function analyze(UploadedFile $file, ?string $reportName = null): array
    {
        try {
            $data = $this->excelService->importOrderXls($file);

            if ($data->isEmpty()) {
                return ['success' => false, 'message' => 'Tarife dosyası boş veya okunamadı.', 'report_id' => null];
            }

            $columnMap = $this->mapColumns($data->first(), $this->tariffColumnAliases);

            if (!isset($columnMap['stock_code'])) {
                return [
                    'success'   => false,
                    'message'   => 'SATICI STOK KODU sütunu bulunamadı! Beklenen: ' . implode(', ', $this->tariffColumnAliases['stock_code']),
                    'report_id' => null,
                ];
            }

            if (!isset($columnMap['current_price']) || !isset($columnMap['current_commission'])) {
                return [
                    'success'   => false,
                    'message'   => 'GÜNCEL TSF veya GÜNCEL KOMİSYON sütunu bulunamadı!',
                    'report_id' => null,
                ];
            }

            // Tüm maliyetleri bir seferde çek + çoklu index oluştur (performans)
            $allCostsRaw = ProductCost::all();
            $costByStockCode = $allCostsRaw->keyBy(fn($c) => mb_strtolower(trim($c->stock_code)));
            $costByBarcode = $allCostsRaw->filter(fn($c) => !empty($c->barcode))->keyBy(fn($c) => mb_strtolower(trim($c->barcode)));
            $costByName = $allCostsRaw->filter(fn($c) => !empty($c->product_name))->keyBy(fn($c) => mb_strtolower(trim($c->product_name)));

            $items = [];
            $totalCurrentProfit = 0;
            $totalOptimizedProfit = 0;
            $opportunityCount = 0;
            $unmatchedCount = 0;

            foreach ($data as $row) {
                $stockCode = trim($row[$columnMap['stock_code']] ?? '');
                if (empty($stockCode)) continue;

                $currentPrice = $this->parseNumber($row[$columnMap['current_price'] ?? '__none__'] ?? 0);
                $currentCommission = $this->parseNumber($row[$columnMap['current_commission'] ?? '__none__'] ?? 0);
                $barcode = trim($row[$columnMap['barcode'] ?? '__none__'] ?? '');
                $productName = trim($row[$columnMap['product_name'] ?? '__none__'] ?? '');

                // === AKILLI EŞLEŞTİRME (3 Katmanlı) ===
                // 1. Stok koduna göre tam eşleşme
                $cost = $costByStockCode->get(mb_strtolower($stockCode));

                // 2. Barkoda göre tam eşleşme
                if (!$cost && !empty($barcode)) {
                    $cost = $costByBarcode->get(mb_strtolower($barcode));
                }

                // 3. Ürün adına göre eşleşme (normalize + contains)
                if (!$cost && !empty($productName)) {
                    $normalizedName = mb_strtolower(trim($productName));

                    // 3a. Tam eşleşme
                    $cost = $costByName->get($normalizedName);

                    // 3b. İçerik araması — Maliyet tablosundaki isim, tarife ismi içinde var mı?
                    if (!$cost) {
                        $cost = $allCostsRaw->first(function ($c) use ($normalizedName) {
                            $costName = mb_strtolower(trim($c->product_name ?? ''));
                            if (empty($costName)) return false;
                            return str_contains($normalizedName, $costName) || str_contains($costName, $normalizedName);
                        });
                    }
                }

                $productionCost = $cost ? (float) $cost->production_cost : 0;
                $shippingCost = $cost ? (float) $cost->shipping_cost : 0;
                $totalCost = $productionCost + $shippingCost;

                if (!$cost) {
                    $unmatchedCount++;
                }

                // === TÜM SENARYOLARI HESAPLA (P&L Analizi) ===
                $currentRevenue = $currentPrice * (1 - $currentCommission / 100);
                $currentNetProfit = round($currentRevenue - $totalCost, 2);

                // Senaryo 1: Mevcut Durum
                $allScenarios = [
                    [
                        'name'       => 'Mevcut Durum',
                        'price'      => $currentPrice,
                        'commission' => $currentCommission,
                        'revenue'    => round($currentRevenue, 2),
                        'total_cost' => $totalCost,
                        'net_profit' => $currentNetProfit,
                        'is_best'    => false,
                    ],
                ];

                // Senaryo 2, 3, 4: Tarife Alternatifleri
                $tariffScenarios = $this->buildScenarios($row, $columnMap);
                foreach ($tariffScenarios as $scenario) {
                    $revenue = $scenario['price'] * (1 - $scenario['commission'] / 100);
                    $netProfit = round($revenue - $totalCost, 2);

                    $allScenarios[] = [
                        'name'       => $scenario['name'],
                        'price'      => $scenario['price'],
                        'commission' => $scenario['commission'],
                        'revenue'    => round($revenue, 2),
                        'total_cost' => $totalCost,
                        'net_profit' => $netProfit,
                        'is_best'    => false,
                    ];
                }

                // En iyi senaryoyu bul ve işaretle
                $bestScenario = $this->findBestScenario($tariffScenarios, $currentNetProfit, $totalCost);
                $bestName = $bestScenario ? $bestScenario['name'] : 'Mevcut Durum';

                foreach ($allScenarios as $key => $sc) {
                    if ($sc['name'] === $bestName) {
                        $allScenarios[$key]['is_best'] = true;
                    }
                }

                // Karar
                $action = 'keep';
                $suggestedTariff = null;
                $suggestedPrice = null;
                $suggestedCommission = null;
                $suggestedNetProfit = null;
                $extraProfit = 0;

                if ($bestScenario) {
                    $action = 'update';
                    $suggestedTariff = $bestScenario['name'];
                    $suggestedPrice = $bestScenario['price'];
                    $suggestedCommission = $bestScenario['commission'];
                    $suggestedNetProfit = $bestScenario['net_profit'];
                    $extraProfit = $bestScenario['net_profit'] - $currentNetProfit;
                    $opportunityCount++;
                }

                // Negatif kâr uyarısı
                if ($cost && $currentNetProfit < 0 && !$bestScenario) {
                    $action = 'warning';
                }

                $totalCurrentProfit += $currentNetProfit;
                $totalOptimizedProfit += ($bestScenario ? $bestScenario['net_profit'] : $currentNetProfit);

                $items[] = [
                    'stock_code'            => $stockCode,
                    'barcode'               => $barcode,
                    'product_name'          => $productName,
                    'current_price'         => $currentPrice,
                    'current_commission'    => $currentCommission,
                    'current_net_profit'    => $currentNetProfit,
                    'suggested_tariff'      => $suggestedTariff,
                    'suggested_price'       => $suggestedPrice,
                    'suggested_commission'  => $suggestedCommission,
                    'suggested_net_profit'  => $suggestedNetProfit,
                    'extra_profit'          => $extraProfit,
                    'production_cost'       => $productionCost,
                    'shipping_cost'         => $shippingCost,
                    'action'                => $action,
                    'is_selected'           => false,
                    'scenario_details'      => $allScenarios,
                ];
            }

            // Raporu veritabanına kaydet
            DB::beginTransaction();

            $report = OptimizationReport::create([
                'user_id'                => auth()->id(),
                'name'                   => $reportName ?: now()->format('d M Y H:i') . ' Analizi',
                'total_products'         => count($items),
                'opportunity_count'      => $opportunityCount,
                'total_current_profit'   => $totalCurrentProfit,
                'total_optimized_profit' => $totalOptimizedProfit,
                'total_extra_profit'     => $totalOptimizedProfit - $totalCurrentProfit,
                'unmatched_count'        => $unmatchedCount,
                'original_filename'      => $file->getClientOriginalName(),
                'status'                 => 'completed',
            ]);

            foreach ($items as $item) {
                $item['report_id'] = $report->id;
                OptimizationReportItem::create($item);
            }

            DB::commit();

            Log::info('TariffOptimizer: Analiz tamamlandı', [
                'report_id'    => $report->id,
                'total'        => count($items),
                'opportunities' => $opportunityCount,
                'extra_profit' => $totalOptimizedProfit - $totalCurrentProfit,
            ]);

            return [
                'success'   => true,
                'message'   => "Analiz tamamlandı! {$opportunityCount} üründe kâr artış fırsatı bulundu.",
                'report_id' => $report->id,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('TariffOptimizer: Analiz hatası', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return ['success' => false, 'message' => 'Analiz hatası: ' . $e->getMessage(), 'report_id' => null];
        }
    }

    // ===============================================
    // 3. EXPORT
    // ===============================================

    /**
     * Seçilen ürünleri Trendyol formatında Excel'e export et
     */
    public function generateExport(int $reportId, array $selectedItemIds = []): ?string
    {
        try {
            $report = OptimizationReport::findOrFail($reportId);

            $query = $report->items();
            if (!empty($selectedItemIds)) {
                $query->whereIn('id', $selectedItemIds);
            } else {
                $query->where('action', 'update');
            }
            $items = $query->get();

            if ($items->isEmpty()) {
                return null;
            }

            // Trendyol fiyat güncelleme formatı
            $exportData = $items->map(function ($item) {
                // 1. Gecerli Senaryoyu Belirle
                // Kullanıcı bir tarife seçtiyse onu baz al, yoksa AI önerisini baz al.
                $selectedScenario = null;
                if ($item->selected_tariff_index !== null && isset($item->scenario_details[$item->selected_tariff_index])) {
                    $selectedScenario = $item->scenario_details[$item->selected_tariff_index];
                }

                // 2. Fiyatı Belirle
                // Custom price varsa öncelikli, yoksa seçili tarife, yoksa önerilen
                $finalPrice = $item->custom_price;
                if (!$finalPrice) {
                    $finalPrice = $selectedScenario ? $selectedScenario['price'] : $item->suggested_price;
                }

                // 3. Komisyonu Belirle
                $finalCommission = $selectedScenario ? $selectedScenario['commission'] : $item->suggested_commission;

                // 4. Net Karı Hesapla (Eğer custom price veya farklı tarife seçildiyse tekrar hesapla)
                // DB'deki suggested_net_profit sadece best case için. 
                // Biz şu anki duruma göre hesaplamalıyız.
                $totalCost = $item->production_cost + $item->shipping_cost;
                $revenue = $finalPrice * (1 - $finalCommission / 100);
                $finalNetProfit = round($revenue - $totalCost, 2);
                
                $extraProfit = $finalNetProfit - $item->current_net_profit;
                $tariffName = $selectedScenario ? $selectedScenario['name'] : $item->suggested_tariff;

                // Seçim yapıldıysa "Manuel Seçim" olarak işaretle
                if ($item->custom_price || $item->selected_tariff_index !== null) {
                    $tariffName .= ' (Seçildi)';
                }

                return [
                    'Barkod'              => $item->barcode,
                    'SATICI STOK KODU'    => $item->stock_code,
                    'Ürün İsmi'           => $item->product_name,
                    'Piyasa Satış Fiyatı' => $finalPrice,
                    'Mevcut Fiyat'        => $item->current_price,
                    'Mevcut Komisyon %'   => $item->current_commission,
                    'Yeni Komisyon %'     => $finalCommission,
                    'Mevcut Net Kâr'      => $item->current_net_profit,
                    'Yeni Net Kâr'        => $finalNetProfit,
                    'Kâr Farkı'           => $extraProfit,
                    'Önerilen Tarife'     => $tariffName,
                ];
            })->toArray();

            $storageDir = storage_path('app/exports');
            if (!is_dir($storageDir)) {
                mkdir($storageDir, 0755, true);
            }

            $filename = 'trendyol_fiyat_guncelleme_' . now()->format('Y-m-d_His') . '.xlsx';
            $outputPath = $storageDir . '/' . $filename;

            $this->excelService->exportToXlsx(
                [['name' => 'Fiyat Güncelleme', 'data' => $exportData]],
                $outputPath
            );

            // Rapor durumunu güncelle
            $report->update(['status' => 'exported']);

            return $outputPath;
        } catch (\Exception $e) {
            Log::error('TariffOptimizer: Export hatası', ['error' => $e->getMessage()]);
            return null;
        }
    }

    // ===============================================
    // YARDIMCI METODLAR
    // ===============================================

    /**
     * Sütun eşleştirme — Esnek kolon isimleri desteği
     */
    protected function mapColumns(array $row, array $aliases): array
    {
        $headers = array_keys($row);
        $map = [];

        foreach ($aliases as $field => $possibleNames) {
            foreach ($possibleNames as $name) {
                // Tam eşleşme
                foreach ($headers as $header) {
                    if (mb_strtolower(trim($header)) === mb_strtolower(trim($name))) {
                        $map[$field] = $header;
                        break 2;
                    }
                }
            }
        }

        Log::info('TariffOptimizer: Kolon eşleştirme', ['mapped' => array_keys($map), 'headers' => $headers]);
        return $map;
    }

    /**
     * Net kâr hesapla
     */
    protected function calculateNetProfit(float $price, float $commissionPercent, float $totalCost): float
    {
        $revenue = $price * (1 - $commissionPercent / 100);
        return round($revenue - $totalCost, 2);
    }

    /**
     * Tarife senaryolarını oluştur
     */
    protected function buildScenarios(array $row, array $columnMap): array
    {
        $scenarios = [];

        for ($i = 2; $i <= 4; $i++) {
            $priceKey = "tariff{$i}_price";
            $commKey = "tariff{$i}_commission";

            if (isset($columnMap[$priceKey]) && isset($columnMap[$commKey])) {
                $price = $this->parseNumber($row[$columnMap[$priceKey]] ?? 0);
                $commission = $this->parseNumber($row[$columnMap[$commKey]] ?? 0);

                // Geçerli senaryo kontrolü (fiyat ve komisyon > 0)
                if ($price > 0 && $commission > 0) {
                    $scenarios[] = [
                        'name'       => "Tarife {$i}",
                        'price'      => $price,
                        'commission' => $commission,
                    ];
                }
            }
        }

        return $scenarios;
    }

    /**
     * En kârlı senaryoyu bul
     * - Mevcut kârdan yüksek olmalı
     * - Kâr pozitif olmalı (negatif kâr koruması)
     */
    protected function findBestScenario(array $scenarios, float $currentNetProfit, float $totalCost): ?array
    {
        $bestScenario = null;
        $maxProfit = $currentNetProfit;

        foreach ($scenarios as $scenario) {
            $netProfit = $this->calculateNetProfit($scenario['price'], $scenario['commission'], $totalCost);

            // KRİTİK: Yeni kâr mevcut kârdan büyük VE pozitif olmalı
            if ($netProfit > $maxProfit && $netProfit > 0) {
                $maxProfit = $netProfit;
                $bestScenario = array_merge($scenario, ['net_profit' => $netProfit]);
            }
        }

        return $bestScenario;
    }

    /**
     * Türkçe sayı formatını parse et (1.049,90 → 1049.90)
     */
    protected function parseNumber($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (!is_string($value)) {
            return 0;
        }

        $value = trim($value);
        $value = str_replace(['%', '₺', ' ', "\xc2\xa0"], '', $value);

        // Türkçe format: 1.049,90
        if (preg_match('/^\d{1,3}(\.\d{3})*(,\d+)?$/', $value)) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }
        // Sadece virgül: 49,90
        elseif (preg_match('/^\d+,\d+$/', $value)) {
            $value = str_replace(',', '.', $value);
        }

        return (float) $value;
    }
}
