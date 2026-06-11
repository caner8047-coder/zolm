<?php

namespace App\Services;

use App\Models\OptimizationReport;
use App\Models\OptimizationReportItem;
use App\Services\ProfitabilityMetric;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Sepet indirimi kampanyaları analiz servisi.
 *
 * Trendyol'un "X TL üzeri Y TL indirim" kampanyalarında maksimum tutara
 * göre satıcı indirim payını ve kampanya sonrası kârlılığı hesaplar.
 */
class BasketDiscountCampaignService
{
    protected const DEFAULT_COMMISSION_RATE = 21.0;

    protected ExcelService $excelService;
    protected CampaignAnalysisService $campaignService;

    protected array $columnAliases = [
        'barcode' => ['Barkod', 'BARKOD'],
        'product_name' => ['Ürün Adı', 'ÜRÜN ADI', 'Ürün İsmi', 'ÜRÜN İSMİ'],
        'product_code' => ['Ürün Kodu', 'ÜRÜN KODU', 'Model Kodu', 'MODEL KODU'],
        'category' => ['Kategori', 'KATEGORİ'],
        'brand' => ['Marka', 'MARKA'],
        'color' => ['Renk', 'RENK'],
        'size' => ['Beden', 'BEDEN'],
        'stock_code' => ['Stok Kodu', 'STOK KODU', 'Satıcı Stok Kodu', 'SATICI STOK KODU'],
        'stock' => ['Mevcut Stok', 'MEVCUT STOK', 'Stok'],
        'current_price' => ['Mevcut Satış Fiyatı', 'MEVCUT SATIŞ FİYATI', 'Trendyol Satış Fiyatı', 'Güncel TSF', 'Mevcut Fiyat'],
        'max_price' => ['Maksimum Girebileceğin Fiyat', 'MAKSİMUM GİREBİLECEĞİN FİYAT', 'Maksimum Tutar', 'Maksimum Fiyat'],
        'campaign_price' => ['Kampanyalı Satış Fiyatı', 'KAMPANYALI SATIŞ FİYATI', 'İndirim Uygulanacak Fiyat'],
        'commission_flag' => ['Ürün Komisyon Tarifesi', 'ÜRÜN KOMİSYON TARİFESİ'],
        'listing_id' => ['ListingId', 'Listing ID', 'LISTINGID', 'LISTING ID'],
    ];

    public function __construct(ExcelService $excelService, CampaignAnalysisService $campaignService)
    {
        $this->excelService = $excelService;
        $this->campaignService = $campaignService;
    }

    public function analyze(UploadedFile $file, ?string $reportName = null, array $options = []): array
    {
        try {
            $data = $this->excelService->importOrderXls($file);
            if ($data->isEmpty()) {
                return ['success' => false, 'message' => 'Dosya boş veya okunamadı.'];
            }

            $inferredOptions = $this->inferCampaignOptionsFromFilename($file->getClientOriginalName());
            $thresholdAmount = max(1, (float) ($options['threshold_amount'] ?? $inferredOptions['threshold_amount'] ?? 2000));
            $discountAmount = max(0, (float) ($options['discount_amount'] ?? $inferredOptions['discount_amount'] ?? 150));
            $sellerSharePercent = max(0, min(100, (float) ($options['seller_share_percent'] ?? $inferredOptions['seller_share_percent'] ?? 60)));
            $targetMarginPercent = max(0, (float) ($options['target_margin_percent'] ?? 15));
            $campaignTitle = trim((string) ($options['campaign_title'] ?? $inferredOptions['campaign_title'] ?? 'Sepet İndirimi Kampanyası'));

            $this->campaignService->initProductIndex(auth()->id());
            $columnMap = $this->campaignService->mapColumns($data->first(), $this->columnAliases);

            $report = OptimizationReport::create([
                'user_id' => auth()->id(),
                'name' => $reportName ?: ('Sepet İndirimi - ' . now()->format('d.m.Y H:i')),
                'campaign_type' => 'basket_discount',
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
            $totalCampaignProfit = 0;

            foreach ($data as $row) {
                $productName = trim((string) ($row[$columnMap['product_name'] ?? ''] ?? ''));
                $barcode = trim((string) ($row[$columnMap['barcode'] ?? ''] ?? ''));
                $stockCode = trim((string) ($row[$columnMap['stock_code'] ?? ''] ?? ''));
                $productCode = trim((string) ($row[$columnMap['product_code'] ?? ''] ?? ''));

                if ($productName === '' && $barcode === '' && $stockCode === '') {
                    continue;
                }

                $currentPrice = $this->campaignService->parseNumber($row[$columnMap['current_price'] ?? ''] ?? 0);
                $maxPrice = $this->campaignService->parseNumber($row[$columnMap['max_price'] ?? ''] ?? 0);
                $uploadedCampaignPrice = $this->campaignService->parseNumber($row[$columnMap['campaign_price'] ?? ''] ?? 0);

                $product = $this->campaignService->matchProduct($barcode, $stockCode, $productCode, $productName);
                $costs = $this->campaignService->getProductCosts($product);
                $totalCost = $costs['total_cost'];
                $productCost = ProfitabilityMetric::productCost($costs['cogs'], $costs['packaging_cost']);
                if (!$product) {
                    $unmatchedCount++;
                }

                if ($currentPrice <= 0 && $product && (float) $product->sale_price > 0) {
                    $currentPrice = (float) $product->sale_price;
                }

                $commission = $product && (float) $product->commission_rate > 0
                    ? (float) $product->commission_rate
                    : self::DEFAULT_COMMISSION_RATE;

                $targetPrice = $maxPrice > 0 ? $maxPrice : ($uploadedCampaignPrice > 0 ? $uploadedCampaignPrice : $currentPrice);
                $sellerDiscount = $this->calculateSellerDiscount($targetPrice, $thresholdAmount, $discountAmount, $sellerSharePercent);

                $currentNetProfit = $this->campaignService->calculateNetProfit($currentPrice, $commission, $totalCost);
                $campaignNetProfit = $this->calculateCampaignNetProfit($targetPrice, $commission, $totalCost, $sellerDiscount);

                $targetProfitability = 1 + ($targetMarginPercent / 100);
                $currentMargin = $this->calculateMarginPercent($currentNetProfit, $productCost);
                $campaignMargin = $this->calculateMarginPercent($campaignNetProfit, $productCost);
                $meetsTarget = $productCost > 0 && $campaignNetProfit > 0 && $campaignMargin >= $targetProfitability;

                $scenarios = [
                    [
                        'name' => 'Mevcut',
                        'price' => $currentPrice,
                        'commission' => $commission,
                        'seller_discount' => 0,
                        'net_profit' => $currentNetProfit,
                        'margin_pct' => $currentMargin,
                        'is_best' => false,
                    ],
                    [
                        'name' => 'Maksimum Tutar',
                        'price' => $targetPrice,
                        'commission' => $commission,
                        'seller_discount' => $sellerDiscount,
                        'net_profit' => $campaignNetProfit,
                        'margin_pct' => $campaignMargin,
                        'target_margin_pct' => $targetProfitability,
                        'is_best' => $meetsTarget,
                    ],
                ];

                if (!$meetsTarget && $currentNetProfit >= $campaignNetProfit) {
                    $scenarios[0]['is_best'] = true;
                }

                $action = match (true) {
                    $totalCost <= 0 => 'warning',
                    $campaignNetProfit < 0 => 'warning',
                    $meetsTarget => 'update',
                    default => 'warning',
                };

                OptimizationReportItem::create([
                    'report_id' => $report->id,
                    'stock_code' => $stockCode ?: ($productCode ?: $barcode),
                    'barcode' => $barcode,
                    'product_name' => $productName,
                    'current_price' => $currentPrice,
                    'current_commission' => $commission,
                    'current_net_profit' => $currentNetProfit,
                    'suggested_tariff' => 'Maksimum Tutar',
                    'suggested_price' => $targetPrice,
                    'suggested_commission' => $commission,
                    'suggested_net_profit' => $campaignNetProfit,
                    'extra_profit' => round($campaignNetProfit - $currentNetProfit, 2),
                    'production_cost' => $costs['cogs'],
                    'shipping_cost' => $costs['cargo_cost'] + $costs['packaging_cost'],
                    'action' => $action,
                    'is_selected' => false,
                    'scenario_details' => $scenarios,
                    'selected_tariff_index' => null,
                    'campaign_data' => [
                        'matched' => (bool) $product,
                        'campaign_title' => $campaignTitle,
                        'category' => trim((string) ($row[$columnMap['category'] ?? ''] ?? '')),
                        'brand' => trim((string) ($row[$columnMap['brand'] ?? ''] ?? '')),
                        'color' => trim((string) ($row[$columnMap['color'] ?? ''] ?? '')),
                        'size' => trim((string) ($row[$columnMap['size'] ?? ''] ?? '')),
                        'stock' => (int) $this->campaignService->parseNumber($row[$columnMap['stock'] ?? ''] ?? 0),
                        'product_code' => $productCode,
                        'listing_id' => trim((string) ($row[$columnMap['listing_id'] ?? ''] ?? '')),
                        'max_price' => $maxPrice,
                        'uploaded_campaign_price' => $uploadedCampaignPrice,
                        'threshold_amount' => $thresholdAmount,
                        'discount_amount' => $discountAmount,
                        'seller_share_percent' => $sellerSharePercent,
                        'trendyol_share_percent' => round(100 - $sellerSharePercent, 2),
                        'target_margin_percent' => $targetMarginPercent,
                        'target_profitability_ratio' => $targetProfitability,
                        'packaging_cost' => $costs['packaging_cost'],
                        'commission_source' => $product && (float) $product->commission_rate > 0 ? 'mp_product' : 'default',
                        'commission_flag' => trim((string) ($row[$columnMap['commission_flag'] ?? ''] ?? '')),
                    ],
                ]);

                $totalProducts++;
                $totalCurrentProfit += $currentNetProfit;
                $totalCampaignProfit += $campaignNetProfit;
            }

            $opportunityCount = OptimizationReportItem::where('report_id', $report->id)->where('action', 'update')->count();

            $report->update([
                'total_products' => $totalProducts,
                'opportunity_count' => $opportunityCount,
                'total_current_profit' => round($totalCurrentProfit, 2),
                'total_optimized_profit' => round($totalCampaignProfit, 2),
                'total_extra_profit' => round($totalCampaignProfit - $totalCurrentProfit, 2),
                'unmatched_count' => $unmatchedCount,
            ]);

            $message = $opportunityCount > 0
                ? "{$totalProducts} ürün analiz edildi. {$opportunityCount} ürün hedef kârlılığı karşılıyor."
                : "{$totalProducts} ürün analiz edildi. Hedef kârlılığı karşılayan ürün bulunamadı; maliyet veya marj eşiğini kontrol edin.";

            return ['success' => true, 'message' => $message, 'report_id' => $report->id];
        } catch (\Exception $e) {
            Log::error('BasketDiscountCampaign: Analiz hatası', ['error' => $e->getMessage()]);
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
                    $q->whereNotNull('selected_tariff_index')
                        ->orWhereNotNull('custom_price')
                        ->orWhere('action', 'update');
                });
            }

            $items = $query->orderBy('product_name')->get();
            if ($items->isEmpty()) {
                return null;
            }

            $uploadRows = [];
            $analysisRows = [];

            foreach ($items as $item) {
                $finalPrice = $this->resolveFinalPrice($item);
                $campaignData = $item->campaign_data ?? [];
                $commission = (float) ($item->suggested_commission ?: $item->current_commission);
                $sellerDiscount = $this->calculateSellerDiscount(
                    $finalPrice,
                    (float) data_get($campaignData, 'threshold_amount', 2000),
                    (float) data_get($campaignData, 'discount_amount', 150),
                    (float) data_get($campaignData, 'seller_share_percent', 60)
                );
                $finalNetProfit = $this->calculateCampaignNetProfit($finalPrice, $commission, $item->totalCost(), $sellerDiscount);
                $finalMargin = $this->calculateMarginPercent($finalNetProfit, $item->productCostForProfitability());

                $uploadRows[] = [
                    'Barkod' => $item->barcode,
                    'Ürün Adı' => $item->product_name,
                    'Ürün Kodu' => data_get($campaignData, 'product_code', ''),
                    'Kategori' => data_get($campaignData, 'category', ''),
                    'Marka' => data_get($campaignData, 'brand', ''),
                    'Renk' => data_get($campaignData, 'color', ''),
                    'Beden' => data_get($campaignData, 'size', ''),
                    'Stok Kodu' => $item->stock_code,
                    'Mevcut Stok' => data_get($campaignData, 'stock', ''),
                    'Mevcut Satış Fiyatı' => (float) $item->current_price,
                    'Maksimum Girebileceğin Fiyat' => (float) data_get($campaignData, 'max_price', $item->suggested_price),
                    'Kampanyalı Satış Fiyatı' => $finalPrice,
                    'Ürün Komisyon Tarifesi' => data_get($campaignData, 'commission_flag', ''),
                    'ListingId' => data_get($campaignData, 'listing_id', ''),
                ];

                $analysisRows[] = [
                    'Barkod' => $item->barcode,
                    'Stok Kodu' => $item->stock_code,
                    'Ürün' => $item->product_name,
                    'Mevcut Fiyat' => (float) $item->current_price,
                    'Maksimum Tutar' => (float) data_get($campaignData, 'max_price', $item->suggested_price),
                    'Export Fiyatı' => $finalPrice,
                    'Komisyon %' => $commission,
                    'Satıcı İndirim Payı' => $sellerDiscount,
                    'Üretim Maliyeti' => (float) $item->production_cost,
                    'Kargo + Ambalaj' => (float) $item->shipping_cost,
                    'Toplam Maliyet' => $item->totalCost(),
                    'Mevcut Net Kâr' => (float) $item->current_net_profit,
                    'Kampanya Net Kâr' => $finalNetProfit,
                    'Kâr Farkı' => round($finalNetProfit - (float) $item->current_net_profit, 2),
                    'Kârlılık %' => ProfitabilityMetric::profitPercentFromMultiplier($finalMargin),
                    'Karar' => $item->action === 'update' ? 'Katılabilir' : 'Kontrol Et',
                ];
            }

            $outputPath = storage_path('app/exports/basket-discount-' . date('Y-m-d-His') . '.xlsx');
            $this->excelService->exportToXlsx([
                ['name' => 'Trendyol Yukleme', 'data' => $uploadRows],
                ['name' => 'ZOLM Analiz', 'data' => $analysisRows],
            ], $outputPath);

            $report->update(['status' => 'exported']);

            return $outputPath;
        } catch (\Exception $e) {
            Log::error('BasketDiscountCampaign: Export hatası', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function calculateSellerDiscount(float $price, float $thresholdAmount, float $discountAmount, float $sellerSharePercent): float
    {
        if ($price <= 0 || $thresholdAmount <= 0 || $discountAmount <= 0 || $sellerSharePercent <= 0) {
            return 0.0;
        }

        $shareBase = min($price, $thresholdAmount) / $thresholdAmount;

        return round($discountAmount * ($sellerSharePercent / 100) * $shareBase, 2);
    }

    public function calculateCampaignNetProfit(float $price, float $commissionPercent, float $totalCost, float $sellerDiscount): float
    {
        $revenue = $price * (1 - ($commissionPercent / 100));

        return round($revenue - $sellerDiscount - $totalCost, 2);
    }

    public function calculateMarginPercent(float $netProfit, float $totalCost): float
    {
        return ProfitabilityMetric::multiplierOrZero($netProfit, $totalCost);
    }

    protected function resolveFinalPrice(OptimizationReportItem $item): float
    {
        $campaignData = $item->campaign_data ?? [];
        $maxPrice = (float) data_get($campaignData, 'max_price', 0);
        $price = (float) ($item->custom_price ?: $item->suggested_price ?: $item->current_price);

        if ($item->selected_tariff_index !== null && isset($item->scenario_details[$item->selected_tariff_index])) {
            $scenario = $item->scenario_details[$item->selected_tariff_index];
            $price = (float) ($item->custom_price ?: ($scenario['price'] ?? $price));
        }

        if ($maxPrice > 0) {
            $price = min($price, $maxPrice);
        }

        return round(max(0, $price), 2);
    }

    protected function inferCampaignOptionsFromFilename(string $filename): array
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $normalized = mb_strtolower($name);
        $normalized = strtr($normalized, [
            'ı' => 'i',
            'ğ' => 'g',
            'ü' => 'u',
            'ş' => 's',
            'ö' => 'o',
            'ç' => 'c',
        ]);

        $options = [];

        if (preg_match('/(\d+(?:[._]\d{3})*)[\s\-_]*tl[\s\-_]*(?:uzeri|ve[\s\-_]*uzeri)[\s\-_]*(\d+(?:[._]\d{3})*)[\s\-_]*tl[\s\-_]*indirim/u', $normalized, $matches)) {
            $threshold = (float) str_replace(['.', '_'], '', $matches[1]);
            $discount = (float) str_replace(['.', '_'], '', $matches[2]);
            if ($threshold > 0) {
                $options['threshold_amount'] = $threshold;
            }
            if ($discount > 0) {
                $options['discount_amount'] = $discount;
            }
        }

        if (preg_match('/(\d{1,3})[\s\-_]*trendyol[\s\-_]*karsilamali/u', $normalized, $matches)) {
            $trendyolShare = max(0, min(100, (float) $matches[1]));
            $options['seller_share_percent'] = round(100 - $trendyolShare, 2);
        }

        if (isset($options['threshold_amount'], $options['discount_amount'])) {
            $options['campaign_title'] = number_format($options['threshold_amount'], 0, ',', '.')
                . ' TL Üzeri '
                . number_format($options['discount_amount'], 0, ',', '.')
                . ' TL İndirim';
        }

        return $options;
    }
}
