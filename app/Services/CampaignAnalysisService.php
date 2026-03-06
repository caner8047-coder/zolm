<?php

namespace App\Services;

use App\Models\MpProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Ortak Kampanya Analiz Servisi
 * 
 * Tüm kampanya modülleri (Ürün Komisyon Tarifeleri, Plus Komisyon, Avantajlı Ürün Etiketleri, Flaş Ürünler)
 * bu servisteki paylaşılan fonksiyonları kullanır:
 * - MpProduct eşleştirme
 * - Net kâr hesaplama
 * - Türkçe sayı format parse
 */
class CampaignAnalysisService
{
    protected ExcelService $excelService;

    // MpProduct lookup index'leri (performans için bir kez oluşturulur)
    protected ?Collection $allProducts = null;
    protected ?Collection $byBarcode = null;
    protected ?Collection $byStockCode = null;
    protected ?Collection $byModelCode = null;
    protected ?Collection $byName = null;

    public function __construct(ExcelService $excelService)
    {
        $this->excelService = $excelService;
    }

    // ===============================================
    // ÜRÜN EŞLEŞTİRME (MpProduct)
    // ===============================================

    /**
     * MpProduct lookup index'lerini oluştur (bir kez)
     */
    public function initProductIndex(?int $userId = null): void
    {
        $query = MpProduct::query();
        if ($userId) {
            $query->where('user_id', $userId);
        }
        $this->allProducts = $query->get();

        $this->byBarcode = $this->allProducts
            ->filter(fn($p) => !empty($p->barcode))
            ->keyBy(fn($p) => mb_strtolower(trim($p->barcode)));

        $this->byStockCode = $this->allProducts
            ->filter(fn($p) => !empty($p->stock_code))
            ->keyBy(fn($p) => mb_strtolower(trim($p->stock_code)));

        $this->byModelCode = $this->allProducts
            ->filter(fn($p) => !empty($p->model_code))
            ->keyBy(fn($p) => mb_strtolower(trim($p->model_code)));

        $this->byName = $this->allProducts
            ->filter(fn($p) => !empty($p->product_name))
            ->keyBy(fn($p) => mb_strtolower(trim($p->product_name)));
    }

    /**
     * Akıllı MpProduct eşleştirme (4 katmanlı)
     * 
     * 1. Barkod → tam eşleşme
     * 2. Stok Kodu → tam eşleşme
     * 3. Model Kodu → tam eşleşme
     * 4. Ürün Adı → tam eşleşme + contains
     */
    public function matchProduct(
        ?string $barcode = null,
        ?string $stockCode = null,
        ?string $modelCode = null,
        ?string $productName = null
    ): ?MpProduct {
        if ($this->allProducts === null) {
            $this->initProductIndex(auth()->id());
        }

        // 1. Barkod
        if (!empty($barcode)) {
            $product = $this->byBarcode->get(mb_strtolower(trim($barcode)));
            if ($product) return $product;
        }

        // 2. Stok Kodu
        if (!empty($stockCode)) {
            $product = $this->byStockCode->get(mb_strtolower(trim($stockCode)));
            if ($product) return $product;
        }

        // 3. Model Kodu
        if (!empty($modelCode)) {
            $product = $this->byModelCode->get(mb_strtolower(trim($modelCode)));
            if ($product) return $product;
        }

        // 4. Ürün Adı
        if (!empty($productName)) {
            $normalized = mb_strtolower(trim($productName));

            // 4a. Tam eşleşme
            $product = $this->byName->get($normalized);
            if ($product) return $product;

            // 4b. Contains araması
            $product = $this->allProducts->first(function ($p) use ($normalized) {
                $pName = mb_strtolower(trim($p->product_name ?? ''));
                if (empty($pName)) return false;
                return str_contains($normalized, $pName) || str_contains($pName, $normalized);
            });
            if ($product) return $product;
        }

        return null;
    }

    /**
     * MpProduct'tan toplam maliyet al
     */
    public function getProductCosts(?MpProduct $product): array
    {
        if (!$product) {
            return [
                'cogs' => 0,
                'packaging_cost' => 0,
                'cargo_cost' => 0,
                'total_cost' => 0,
                'vat_rate' => 0,
            ];
        }

        $cogs = (float) $product->cogs;
        $packaging = (float) $product->packaging_cost;
        $cargo = (float) $product->cargo_cost;

        return [
            'cogs' => $cogs,
            'packaging_cost' => $packaging,
            'cargo_cost' => $cargo,
            'total_cost' => $cogs + $packaging + $cargo,
            'vat_rate' => (float) $product->vat_rate,
        ];
    }

    // ===============================================
    // KÂR HESAPLAMA
    // ===============================================

    /**
     * Net kâr hesapla
     * Net Kâr = (Fiyat × (1 - Komisyon%/100)) - Toplam Maliyet
     */
    public function calculateNetProfit(float $price, float $commissionPercent, float $totalCost): float
    {
        $revenue = $price * (1 - $commissionPercent / 100);
        return round($revenue - $totalCost, 2);
    }

    /**
     * Gelir hesapla (komisyon düşülmüş)
     */
    public function calculateRevenue(float $price, float $commissionPercent): float
    {
        return round($price * (1 - $commissionPercent / 100), 2);
    }

    // ===============================================
    // KOLON EŞLEŞTİRME
    // ===============================================

    /**
     * Esnek kolon isimleri eşleştirme
     * Excel başlıklarını standart alan adlarına eşler
     */
    public function mapColumns(array $row, array $aliases): array
    {
        $headers = array_keys($row);
        $map = [];

        foreach ($aliases as $field => $possibleNames) {
            foreach ($possibleNames as $name) {
                foreach ($headers as $header) {
                    if (mb_strtolower(trim($header)) === mb_strtolower(trim($name))) {
                        $map[$field] = $header;
                        break 2;
                    }
                }
            }
        }

        Log::info('CampaignAnalysis: Kolon eşleştirme', ['mapped' => array_keys($map), 'headers' => $headers]);
        return $map;
    }

    // ===============================================
    // SAYI PARSE
    // ===============================================

    /**
     * Türkçe sayı formatını parse et (1.049,90 → 1049.90)
     */
    public function parseNumber($value): float
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

    /**
     * MpProduct sayısını döndür
     */
    public function getProductCount(?int $userId = null): int
    {
        $query = MpProduct::query();
        if ($userId) {
            $query->where('user_id', $userId);
        }
        return $query->count();
    }

    /**
     * Maliyeti tanımlı ürün sayısı
     */
    public function getProductWithCostCount(?int $userId = null): int
    {
        $query = MpProduct::where('cogs', '>', 0);
        if ($userId) {
            $query->where('user_id', $userId);
        }
        return $query->count();
    }
}
