<?php

namespace App\Services;

use App\Models\OptimizationReport;
use App\Models\OptimizationReportItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Flaş Ürünler Analiz Servisi v2
 * 
 * DB-backed: 3 senaryo (Mevcut, 24h Flaş, 3h Flaş)
 */
class FlashProductsService
{
    protected ExcelService $excelService;
    protected CampaignAnalysisService $campaignService;

    protected array $columnAliases = [
        'product_name'       => ['Ürün Adı', 'ÜRÜN ADI', 'Ürün İsmi', 'ÜRÜN İSMİ'],
        'barcode'            => ['Barkod', 'BARKOD'],
        'stock_code'         => ['Satıcı Stok Kodu', 'SATICI STOK KODU', 'Stok Kodu'],
        'model_code'         => ['Model Kodu', 'MODEL KODU'],
        'category'           => ['Kategori', 'KATEGORİ'],
        'brand'              => ['Marka', 'MARKA'],
        'current_price'      => ['Mevcut Fiyat', 'MEVCUT FİYAT', 'Trendyol Satış Fiyatı', 'TRENDYOL SATIŞ FİYATI'],
        'current_commission' => ['Mevcut Komisyon', 'MEVCUT KOMİSYON', 'Mevcut Komisyon Oranı'],
        'flash_24h_price'    => ['24 Saat Fiyat', '24 SAAT FİYAT', '24 Saat Flaş Fiyat', '24H FLAŞ FİYAT'],
        'flash_3h_price'     => ['3 Saat Fiyat', '3 SAAT FİYAT', '3 Saat Flaş Fiyat', '3H FLAŞ FİYAT'],
        'flash_commission'   => ['Flaş Komisyon Oranı', 'FLAŞ KOMİSYON ORANI', 'Flaş Komisyon'],
        'campaign_start'     => ['24 Saat Flaş Başlangıç Tarihi', '24 SAAT FLAŞ BAŞLANGIÇ TARİHİ', 'Kampanya Başlangıç'],
        'campaign_end'       => ['24 Saat Flaş Bitiş Tarihi', '24 SAAT FLAŞ BİTİŞ TARİHİ', 'Kampanya Bitiş'],
    ];

    public function __construct(ExcelService $excelService, CampaignAnalysisService $campaignService)
    {
        $this->excelService = $excelService;
        $this->campaignService = $campaignService;
    }

    public function analyze(UploadedFile $file, ?string $reportName = null): array
    {
        try {
            $data = $this->excelService->importOrderXls($file);
            if ($data->isEmpty()) {
                return ['success' => false, 'message' => 'Dosya boş veya okunamadı.'];
            }

            $this->campaignService->initProductIndex(auth()->id());
            $columnMap = $this->campaignService->mapColumns($data->first(), $this->columnAliases);

            // Kampanya tarihi çıkar
            $firstRow = $data->first();
            $campaignStart = trim($firstRow[$columnMap['campaign_start'] ?? ''] ?? '');
            $campaignEnd = trim($firstRow[$columnMap['campaign_end'] ?? ''] ?? '');

            $report = OptimizationReport::create([
                'user_id' => auth()->id(),
                'name' => $reportName ?: ('Flaş Ürünler - ' . now()->format('d.m.Y H:i')),
                'campaign_type' => 'flash',
                'original_filename' => $file->getClientOriginalName(),
                'status' => 'completed',
                'total_products' => 0,
                'opportunity_count' => 0,
                'total_current_profit' => 0,
                'total_optimized_profit' => 0,
                'total_extra_profit' => 0,
                'unmatched_count' => 0,
                'ai_analysis' => json_encode([
                    'campaign_start' => $campaignStart,
                    'campaign_end' => $campaignEnd,
                ]),
            ]);

            $totalProducts = 0;
            $unmatchedCount = 0;
            $totalCurrentProfit = 0;
            $totalBestProfit = 0;

            foreach ($data as $row) {
                $productName = trim($row[$columnMap['product_name'] ?? ''] ?? '');
                $barcode = trim($row[$columnMap['barcode'] ?? ''] ?? '');
                $stockCode = trim($row[$columnMap['stock_code'] ?? ''] ?? '');
                $modelCode = trim($row[$columnMap['model_code'] ?? ''] ?? '');

                if (empty($productName) && empty($barcode)) continue;

                $currentPrice = $this->campaignService->parseNumber($row[$columnMap['current_price'] ?? ''] ?? 0);
                $currentCommission = $this->campaignService->parseNumber($row[$columnMap['current_commission'] ?? ''] ?? 0);
                $flash24hPrice = $this->campaignService->parseNumber($row[$columnMap['flash_24h_price'] ?? ''] ?? 0);
                $flash3hPrice = $this->campaignService->parseNumber($row[$columnMap['flash_3h_price'] ?? ''] ?? 0);
                $flashCommission = $this->campaignService->parseNumber($row[$columnMap['flash_commission'] ?? ''] ?? $currentCommission);

                $product = $this->campaignService->matchProduct($barcode, $stockCode, $modelCode, $productName);
                $costs = $this->campaignService->getProductCosts($product);
                $totalCost = $costs['total_cost'];
                if (!$product) $unmatchedCount++;

                // Mevcut kâr
                $currentNetProfit = $this->campaignService->calculateNetProfit($currentPrice, $currentCommission, $totalCost);
                $currentMargin = $totalCost > 0 ? round(($currentNetProfit / $totalCost) * 100, 1) : 0;

                // 24h Flaş kâr
                $flash24hProfit = $flash24hPrice > 0
                    ? $this->campaignService->calculateNetProfit($flash24hPrice, $flashCommission, $totalCost)
                    : $currentNetProfit;
                $flash24hMargin = $totalCost > 0 ? round(($flash24hProfit / $totalCost) * 100, 1) : 0;

                // 3h Flaş kâr
                $flash3hProfit = $flash3hPrice > 0
                    ? $this->campaignService->calculateNetProfit($flash3hPrice, $flashCommission, $totalCost)
                    : $currentNetProfit;
                $flash3hMargin = $totalCost > 0 ? round(($flash3hProfit / $totalCost) * 100, 1) : 0;

                $scenarios = [
                    [
                        'name' => 'Mevcut',
                        'price' => $currentPrice,
                        'commission' => $currentCommission,
                        'net_profit' => $currentNetProfit,
                        'margin_pct' => $currentMargin,
                        'is_best' => false,
                    ],
                    [
                        'name' => '24h Flaş',
                        'price' => $flash24hPrice,
                        'commission' => $flashCommission,
                        'net_profit' => $flash24hProfit,
                        'margin_pct' => $flash24hMargin,
                        'is_best' => false,
                    ],
                    [
                        'name' => '3h Flaş',
                        'price' => $flash3hPrice,
                        'commission' => $flashCommission,
                        'net_profit' => $flash3hProfit,
                        'margin_pct' => $flash3hMargin,
                        'is_best' => false,
                    ],
                ];

                // En iyi senaryoyu bul
                $bestProfit = $currentNetProfit;
                $bestIndex = 0;
                foreach ([1, 2] as $idx) {
                    if ($scenarios[$idx]['price'] > 0 && $scenarios[$idx]['net_profit'] > $bestProfit) {
                        $bestProfit = $scenarios[$idx]['net_profit'];
                        $bestIndex = $idx;
                    }
                }

                foreach ($scenarios as $i => &$sc) {
                    $sc['is_best'] = ($i === $bestIndex);
                }
                unset($sc);

                $action = $bestProfit < 0 ? 'warning' : ($bestIndex > 0 ? 'update' : 'keep');

                OptimizationReportItem::create([
                    'report_id' => $report->id,
                    'stock_code' => $stockCode ?: $barcode,
                    'barcode' => $barcode,
                    'product_name' => $productName,
                    'current_price' => $currentPrice,
                    'current_commission' => $currentCommission,
                    'current_net_profit' => $currentNetProfit,
                    'suggested_tariff' => $scenarios[$bestIndex]['name'],
                    'suggested_price' => $scenarios[$bestIndex]['price'],
                    'suggested_commission' => $scenarios[$bestIndex]['commission'],
                    'suggested_net_profit' => $bestProfit,
                    'extra_profit' => round($bestProfit - $currentNetProfit, 2),
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
                    ],
                ]);

                $totalProducts++;
                $totalCurrentProfit += $currentNetProfit;
                $totalBestProfit += $bestProfit;
            }

            $report->update([
                'total_products' => $totalProducts,
                'opportunity_count' => OptimizationReportItem::where('report_id', $report->id)->where('action', 'update')->count(),
                'total_current_profit' => round($totalCurrentProfit, 2),
                'total_optimized_profit' => round($totalBestProfit, 2),
                'total_extra_profit' => round($totalBestProfit - $totalCurrentProfit, 2),
                'unmatched_count' => $unmatchedCount,
            ]);

            return ['success' => true, 'message' => $totalProducts . ' ürün analiz edildi.', 'report_id' => $report->id];

        } catch (\Exception $e) {
            Log::error('FlashProducts: Analiz hatası', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Analiz hatası: ' . $e->getMessage()];
        }
    }

    public function generateExport(int $reportId, array $selectedItemIds = []): ?string
    {
        try {
            $report = OptimizationReport::findOrFail($reportId);
            $query = $report->items();
            if (!empty($selectedItemIds)) {
                $query->whereIn('id', $selectedItemIds);
            } else {
                $query->where(function ($q) {
                    $q->whereNotNull('selected_tariff_index')->orWhereNotNull('custom_price')->orWhere('action', 'update');
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
                    'Flaş Komisyon %' => $finalCommission,
                    'Mevcut Net Kâr' => $item->current_net_profit,
                    'Flaş Net Kâr' => $finalNetProfit,
                    'Kâr Farkı' => round($finalNetProfit - $item->current_net_profit, 2),
                    'Kâr Marjı %' => $finalMargin,
                    'Seçilen Senaryo' => $selectedScenario ? $selectedScenario['name'] . ' (Seçildi)' : $item->suggested_tariff,
                ];
            })->toArray();

            $outputPath = storage_path('app/exports/flash-products-' . date('Y-m-d-His') . '.xlsx');
            $this->excelService->exportToXlsx([['name' => 'Flaş Analiz', 'data' => $exportData]], $outputPath);
            $report->update(['status' => 'exported']);
            return $outputPath;

        } catch (\Exception $e) {
            Log::error('FlashProducts: Export hatası', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
