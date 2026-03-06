<?php

namespace App\Services;

use App\Models\OptimizationReport;
use App\Models\OptimizationReportItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Plus Komisyon Tarifeleri Analiz Servisi v2
 * 
 * DB-backed: sonuçlar OptimizationReport + Items'a kaydedilir.
 * Senaryo yapısı: [Mevcut, Plus] → her biri fiyat/komisyon/kâr/margin
 */
class PlusCommissionService
{
    protected ExcelService $excelService;
    protected CampaignAnalysisService $campaignService;

    protected array $columnAliases = [
        'product_name'       => ['Ürün İsmi', 'ÜRÜN İSMİ', 'Ürün Adı'],
        'barcode'            => ['Barkod', 'BARKOD'],
        'stock_code'         => ['Satıcı Stok Kodu', 'SATICI STOK KODU', 'Stok Kodu'],
        'model_code'         => ['Model Kodu', 'MODEL KODU'],
        'category'           => ['Kategori', 'KATEGORİ'],
        'brand'              => ['Marka', 'MARKA'],
        'current_tsf'        => ['Güncel TSF', 'GÜNCEL TSF', 'Trendyol Satış Fiyatı', 'TRENDYOL SATIŞ FİYATI', 'Komisyona Esas Fiyat'],
        'current_commission' => ['Güncel Komisyon', 'GÜNCEL KOMİSYON', 'Mevcut Komisyon Oranı', 'MEVCUT KOMİSYON ORANI', 'Mevcut Komisyon'],
        'plus_price_limit'   => ['Plus Fiyat Üst Limiti', 'PLUS FİYAT ÜST LİMİTİ', 'Plus Fiyat Limit', 'PLUS FİYAT LİMİT'],
        'plus_commission'    => ['Plus Komisyon Teklifi', 'PLUS KOMİSYON TEKLİFİ', 'Plus Komisyon Oranı', 'PLUS KOMİSYON ORANI'],
        'customer_price'     => ['Müşterinin Gördüğü Fiyat', 'MÜŞTERİNİN GÖRDÜĞÜ FİYAT'],
    ];

    public function __construct(ExcelService $excelService, CampaignAnalysisService $campaignService)
    {
        $this->excelService = $excelService;
        $this->campaignService = $campaignService;
    }

    /**
     * Plus Komisyon Excel dosyasını analiz et ve DB'ye kaydet
     */
    public function analyze(UploadedFile $file, ?string $reportName = null): array
    {
        try {
            $data = $this->excelService->importOrderXls($file);

            if ($data->isEmpty()) {
                return ['success' => false, 'message' => 'Dosya boş veya okunamadı.'];
            }

            $this->campaignService->initProductIndex(auth()->id());
            $columnMap = $this->campaignService->mapColumns($data->first(), $this->columnAliases);

            // Rapor oluştur
            $report = OptimizationReport::create([
                'user_id' => auth()->id(),
                'name' => $reportName ?: ('Plus Komisyon - ' . now()->format('d.m.Y H:i')),
                'campaign_type' => 'plus',
                'original_filename' => $file->getClientOriginalName(),
                'status' => 'completed',
                'total_products' => 0,
                'opportunity_count' => 0,
                'total_current_profit' => 0,
                'total_optimized_profit' => 0,
                'total_extra_profit' => 0,
                'unmatched_count' => 0,
            ]);

            $totalProducts = 0;
            $matchedCount = 0;
            $unmatchedCount = 0;
            $totalCurrentProfit = 0;
            $totalOptimizedProfit = 0;

            foreach ($data as $row) {
                $productName = trim($row[$columnMap['product_name'] ?? ''] ?? '');
                $barcode = trim($row[$columnMap['barcode'] ?? ''] ?? '');
                $stockCode = trim($row[$columnMap['stock_code'] ?? ''] ?? '');
                $modelCode = trim($row[$columnMap['model_code'] ?? ''] ?? '');

                if (empty($productName) && empty($barcode)) continue;

                $currentTsf = $this->campaignService->parseNumber($row[$columnMap['current_tsf'] ?? ''] ?? 0);
                $currentCommission = $this->campaignService->parseNumber($row[$columnMap['current_commission'] ?? ''] ?? 0);
                $plusPriceLimit = $this->campaignService->parseNumber($row[$columnMap['plus_price_limit'] ?? ''] ?? 0);
                $plusCommission = $this->campaignService->parseNumber($row[$columnMap['plus_commission'] ?? ''] ?? 0);

                // MpProduct eşleştir
                $product = $this->campaignService->matchProduct($barcode, $stockCode, $modelCode, $productName);
                $costs = $this->campaignService->getProductCosts($product);
                $totalCost = $costs['total_cost'];

                if ($product) $matchedCount++;
                else $unmatchedCount++;

                // Mevcut kâr
                $currentNetProfit = $this->campaignService->calculateNetProfit($currentTsf, $currentCommission, $totalCost);
                // Plus kâr
                $plusNetProfit = $plusPriceLimit > 0
                    ? $this->campaignService->calculateNetProfit($plusPriceLimit, $plusCommission, $totalCost)
                    : $currentNetProfit;

                // Kâr marjı %
                $currentMargin = $totalCost > 0 ? round(($currentNetProfit / $totalCost) * 100, 1) : 0;
                $plusMargin = $totalCost > 0 ? round(($plusNetProfit / $totalCost) * 100, 1) : 0;

                // Senaryolar (TariffOptimizer pattern)
                $scenarios = [
                    [
                        'name' => 'Mevcut',
                        'price' => $currentTsf,
                        'commission' => $currentCommission,
                        'net_profit' => $currentNetProfit,
                        'margin_pct' => $currentMargin,
                        'is_best' => $currentNetProfit >= $plusNetProfit,
                    ],
                    [
                        'name' => 'Plus',
                        'price' => $plusPriceLimit,
                        'commission' => $plusCommission,
                        'net_profit' => $plusNetProfit,
                        'margin_pct' => $plusMargin,
                        'is_best' => $plusNetProfit > $currentNetProfit,
                    ],
                ];

                // Varsayılan en iyi senaryoyu seç
                $bestIndex = $plusNetProfit > $currentNetProfit && $plusNetProfit > 0 ? 1 : 0;
                $action = $plusNetProfit < 0 ? 'warning' : ($bestIndex === 1 ? 'update' : 'keep');

                OptimizationReportItem::create([
                    'report_id' => $report->id,
                    'stock_code' => $stockCode ?: $barcode,
                    'barcode' => $barcode,
                    'product_name' => $productName,
                    'current_price' => $currentTsf,
                    'current_commission' => $currentCommission,
                    'current_net_profit' => $currentNetProfit,
                    'suggested_tariff' => $scenarios[$bestIndex]['name'],
                    'suggested_price' => $scenarios[$bestIndex]['price'],
                    'suggested_commission' => $scenarios[$bestIndex]['commission'],
                    'suggested_net_profit' => $scenarios[$bestIndex]['net_profit'],
                    'extra_profit' => round($scenarios[$bestIndex]['net_profit'] - $currentNetProfit, 2),
                    'production_cost' => $costs['cogs'],
                    'shipping_cost' => $costs['cargo_cost'] + $costs['packaging_cost'],
                    'action' => $action,
                    'is_selected' => false,
                    'scenario_details' => $scenarios,
                    'selected_tariff_index' => null,
                    'campaign_data' => [
                        'matched' => (bool) $product,
                        'category' => trim($row[$columnMap['category'] ?? ''] ?? ''),
                        'brand' => trim($row[$columnMap['brand'] ?? ''] ?? ''),
                        'customer_price' => $this->campaignService->parseNumber($row[$columnMap['customer_price'] ?? ''] ?? 0),
                        'packaging_cost' => $costs['packaging_cost'],
                    ],
                ]);

                $totalProducts++;
                $totalCurrentProfit += $currentNetProfit;
                $bestProfit = $scenarios[$bestIndex]['net_profit'];
                $totalOptimizedProfit += $bestProfit;
            }

            // Raporu güncelle
            $report->update([
                'total_products' => $totalProducts,
                'opportunity_count' => OptimizationReportItem::where('report_id', $report->id)->where('action', 'update')->count(),
                'total_current_profit' => round($totalCurrentProfit, 2),
                'total_optimized_profit' => round($totalOptimizedProfit, 2),
                'total_extra_profit' => round($totalOptimizedProfit - $totalCurrentProfit, 2),
                'unmatched_count' => $unmatchedCount,
            ]);

            Log::info('PlusCommission: Analiz tamamlandı', ['report_id' => $report->id, 'items' => $totalProducts]);

            return [
                'success' => true,
                'message' => $totalProducts . ' ürün analiz edildi.',
                'report_id' => $report->id,
            ];

        } catch (\Exception $e) {
            Log::error('PlusCommission: Analiz hatası', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Analiz hatası: ' . $e->getMessage()];
        }
    }

    /**
     * Seçilen ürünleri Trendyol Plus formatında export et
     */
    public function generateExport(int $reportId, array $selectedItemIds = []): ?string
    {
        try {
            $report = OptimizationReport::findOrFail($reportId);
            
            $query = $report->items();
            if (!empty($selectedItemIds)) {
                $query->whereIn('id', $selectedItemIds);
            } else {
                $query->where(function ($q) {
                    $q->whereNotNull('selected_tariff_index')
                      ->orWhereNotNull('custom_price')
                      ->orWhere('action', 'update');
                });
            }
            $items = $query->get();

            if ($items->isEmpty()) return null;

            $exportData = $items->map(function ($item) {
                $selectedScenario = null;
                if ($item->selected_tariff_index !== null && isset($item->scenario_details[$item->selected_tariff_index])) {
                    $selectedScenario = $item->scenario_details[$item->selected_tariff_index];
                }

                $finalPrice = $item->custom_price ?: ($selectedScenario ? $selectedScenario['price'] : $item->suggested_price);
                $finalCommission = $selectedScenario ? $selectedScenario['commission'] : $item->suggested_commission;
                $totalCost = $item->totalCost();
                $revenue = $finalPrice * (1 - $finalCommission / 100);
                $finalNetProfit = round($revenue - $totalCost, 2);
                $finalMargin = $totalCost > 0 ? round(($finalNetProfit / $totalCost) * 100, 1) : 0;

                return [
                    'Barkod' => $item->barcode,
                    'SATICI STOK KODU' => $item->stock_code,
                    'Ürün İsmi' => $item->product_name,
                    'Piyasa Satış Fiyatı' => $finalPrice,
                    'Mevcut Fiyat' => $item->current_price,
                    'Mevcut Komisyon %' => $item->current_commission,
                    'Yeni Komisyon %' => $finalCommission,
                    'Mevcut Net Kâr' => $item->current_net_profit,
                    'Yeni Net Kâr' => $finalNetProfit,
                    'Kâr Farkı' => round($finalNetProfit - $item->current_net_profit, 2),
                    'Kâr Marjı %' => $finalMargin,
                    'Seçim' => $selectedScenario ? $selectedScenario['name'] . ' (Seçildi)' : $item->suggested_tariff,
                ];
            })->toArray();

            $outputPath = storage_path('app/exports/plus-commission-' . date('Y-m-d-His') . '.xlsx');

            $this->excelService->exportToXlsx([
                ['name' => 'Plus Analiz', 'data' => $exportData],
            ], $outputPath);

            $report->update(['status' => 'exported']);
            return $outputPath;

        } catch (\Exception $e) {
            Log::error('PlusCommission: Export hatası', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
