<?php

namespace App\Services;

use App\Models\OptimizationReport;
use App\Models\OptimizationReportItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Avantajlı Ürün Etiketleri Analiz Servisi v2
 * 
 * Excel yapısı: 1/2/3 YILDIZ ÜST FİYAT / ALT FİYAT (komisyon sütunu yok)
 * DB-backed: 4 senaryo (Mevcut, ⭐1, ⭐⭐2, ⭐⭐⭐3)
 */
class BadgePricingService
{
    protected ExcelService $excelService;
    protected CampaignAnalysisService $campaignService;

    protected array $columnAliases = [
        'product_name'       => ['ÜRÜN İSMİ', 'Ürün İsmi', 'Ürün Adı'],
        'barcode'            => ['BARKOD', 'Barkod'],
        'model_code'         => ['MODEL KODU', 'Model Kodu'],
        'category'           => ['KATEGORİ', 'Kategori'],
        'brand'              => ['MARKA', 'Marka'],
        'current_tsf'        => ['TRENDYOL SATIŞ FİYATI', 'Trendyol Satış Fiyatı'],
        'customer_price'     => ['MÜŞTERİNİN GÖRDÜĞÜ FİYAT', 'Müşterinin Gördüğü Fiyat'],
        'star1_upper'        => ['1 YILDIZ ÜST FİYAT', '1 Yıldız Üst Fiyat'],
        'star1_lower'        => ['1 YILDIZ ALT FİYAT', '1 Yıldız Alt Fiyat'],
        'star2_upper'        => ['2 YILDIZ ÜST FİYAT', '2 Yıldız Üst Fiyat'],
        'star2_lower'        => ['2 YILDIZ ALT FİYAT', '2 Yıldız Alt Fiyat'],
        'star3_upper'        => ['3 YILDIZ ÜST FİYAT', '3 Yıldız Üst Fiyat'],
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

            $report = OptimizationReport::create([
                'user_id' => auth()->id(),
                'name' => $reportName ?: ('Avantajlı Etiketler - ' . now()->format('d.m.Y H:i')),
                'campaign_type' => 'badge',
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
            $unmatchedCount = 0;
            $totalCurrentProfit = 0;
            $totalBestProfit = 0;

            foreach ($data as $row) {
                $productName = trim($row[$columnMap['product_name'] ?? ''] ?? '');
                $barcode = trim($row[$columnMap['barcode'] ?? ''] ?? '');
                $modelCode = trim($row[$columnMap['model_code'] ?? ''] ?? '');

                if (empty($productName) && empty($barcode)) continue;

                $currentTsf = $this->campaignService->parseNumber($row[$columnMap['current_tsf'] ?? ''] ?? 0);

                // Yıldız fiyat limitleri
                $star1Upper = $this->campaignService->parseNumber($row[$columnMap['star1_upper'] ?? ''] ?? 0);
                $star2Upper = $this->campaignService->parseNumber($row[$columnMap['star2_upper'] ?? ''] ?? 0);
                $star3Upper = $this->campaignService->parseNumber($row[$columnMap['star3_upper'] ?? ''] ?? 0);

                // MpProduct eşleştir (barcode + model_code — bu dosyada stok kodu yok)
                $product = $this->campaignService->matchProduct($barcode, null, $modelCode, $productName);
                $costs = $this->campaignService->getProductCosts($product);
                $totalCost = $costs['total_cost'];

                if (!$product) $unmatchedCount++;

                // Komisyon: MpProduct'tan veya varsayılan
                $commission = $product ? ((float) ($product->commission_rate ?? 21)) : 21;

                // Mevcut kâr
                $currentNetProfit = $this->campaignService->calculateNetProfit($currentTsf, $commission, $totalCost);
                $currentMargin = $totalCost > 0 ? round(($currentNetProfit / $totalCost) * 100, 1) : 0;

                // Senaryolar
                $scenarios = [
                    [
                        'name' => 'Mevcut',
                        'price' => $currentTsf,
                        'commission' => $commission,
                        'net_profit' => $currentNetProfit,
                        'margin_pct' => $currentMargin,
                        'is_best' => false,
                    ],
                ];

                $bestProfit = $currentNetProfit;
                $bestIndex = 0;

                $starPrices = [1 => $star1Upper, 2 => $star2Upper, 3 => $star3Upper];
                $starNames = [1 => '⭐ 1 Yıldız', 2 => '⭐⭐ 2 Yıldız', 3 => '⭐⭐⭐ 3 Yıldız'];

                foreach ($starPrices as $star => $maxPrice) {
                    $starProfit = ($maxPrice > 0)
                        ? $this->campaignService->calculateNetProfit($maxPrice, $commission, $totalCost)
                        : 0;
                    $starMargin = ($maxPrice > 0 && $totalCost > 0)
                        ? round(($starProfit / $totalCost) * 100, 1)
                        : 0;

                    $scenarios[] = [
                        'name' => $starNames[$star],
                        'price' => $maxPrice,
                        'commission' => $commission,
                        'net_profit' => $starProfit,
                        'margin_pct' => $starMargin,
                        'is_best' => false,
                    ];

                    if ($maxPrice > 0 && $starProfit > $bestProfit) {
                        $bestProfit = $starProfit;
                        $bestIndex = $star;
                    }
                }

                // En iyi senaryoyu işaretle
                foreach ($scenarios as $i => &$sc) {
                    $sc['is_best'] = ($i === $bestIndex);
                }
                unset($sc);

                $action = $bestProfit < 0 ? 'warning' : ($bestIndex > 0 ? 'update' : 'keep');

                OptimizationReportItem::create([
                    'report_id' => $report->id,
                    'stock_code' => $barcode,
                    'barcode' => $barcode,
                    'product_name' => $productName,
                    'current_price' => $currentTsf,
                    'current_commission' => $commission,
                    'current_net_profit' => $currentNetProfit,
                    'suggested_tariff' => $scenarios[$bestIndex]['name'],
                    'suggested_price' => $scenarios[$bestIndex]['price'],
                    'suggested_commission' => $commission,
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
            Log::error('BadgePricing: Analiz hatası', ['error' => $e->getMessage()]);
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
                $totalCost = $item->totalCost();
                $commission = $item->current_commission;
                $revenue = $finalPrice * (1 - $commission / 100);
                $finalNetProfit = round($revenue - $totalCost, 2);
                $finalMargin = $totalCost > 0 ? round(($finalNetProfit / $totalCost) * 100, 1) : 0;

                return [
                    'BARKOD' => $item->barcode,
                    'Ürün İsmi' => $item->product_name,
                    'YENİ TSF (FİYAT GÜNCELLE)' => $finalPrice,
                    'Mevcut Fiyat' => $item->current_price,
                    'Komisyon %' => $commission,
                    'Mevcut Net Kâr' => $item->current_net_profit,
                    'Yeni Net Kâr' => $finalNetProfit,
                    'Kâr Farkı' => round($finalNetProfit - $item->current_net_profit, 2),
                    'Kâr Marjı %' => $finalMargin,
                    'Seçilen Yıldız' => $selectedScenario ? $selectedScenario['name'] : $item->suggested_tariff,
                ];
            })->toArray();

            $outputPath = storage_path('app/exports/badge-pricing-' . date('Y-m-d-His') . '.xlsx');
            $this->excelService->exportToXlsx([['name' => 'Etiket Analiz', 'data' => $exportData]], $outputPath);
            $report->update(['status' => 'exported']);
            return $outputPath;

        } catch (\Exception $e) {
            Log::error('BadgePricing: Export hatası', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
