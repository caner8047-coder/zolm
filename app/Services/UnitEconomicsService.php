<?php

namespace App\Services;

use App\Models\MpPeriod;
use App\Models\MpOrder;
use App\Models\MpTransaction;
use App\Models\MpFinancialRule;
use App\Services\ProfitabilityMetric;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pazaryeri Muhasebe — Birim İktisadı (Unit Economics) Servisi
 *
 * Her sipariş ve SKU/Barkod bazında gerçek net kâr hesaplar.
 * Formül: Gerçek Net Kâr = Hakediş - (COGS + Ambalaj) + Net KDV Avantajı/Yükü
 *
 * KDV Asimetrisi:
 *   Satış KDV'si (%10 veya %20 ürüne göre değişir) → müşteriden tahsil
 *   Gider KDV'si (%20 sabit — komisyon ve kargo) → Trendyol faturasından mahsup
 *   Net KDV = Satış KDV - Gider KDV (pozitifse devlete ödenir, negatifse avantaj)
 */
class UnitEconomicsService
{
    /**
     * @var array Product VAT rates cache by barcode
     */
    protected array $vatRatesCache = [];
    protected array $periodUserCache = [];

    /**
     * Belirli bir sipariş için birim iktisadı hesapla
     */
    public function calculateForOrder(MpOrder $order): array
    {
        $userId = $this->resolveUserIdForOrder($order);
        $resolvedBarcode    = $order->resolved_barcode;
        $resolvedStockCode  = $order->resolved_stock_code;
        $resolvedProductName = $order->resolved_product_name;
        $resolvedQuantity   = $order->resolved_quantity;
        $grossAmount      = (float) $order->gross_amount;
        $hakedis          = (float) $order->net_hakedis;
        $commissionAmount = (float) $order->commission_amount;
        $cargoAmount      = (float) $order->cargo_amount;
        $cogs             = (float) $order->resolved_cogs_at_time;
        $packaging        = (float) $order->resolved_packaging_cost_at_time;
        $svc              = new \App\Services\MpSettingsService($userId);
        $ownCargo         = $svc->usesOwnCargo() ? (float) $order->resolved_own_cargo_cost_at_time : 0.0;
        $defaultVatRate   = $svc->getDefaultProductVatRate();
        
        // Ürün bazlı KDV oranı: MpProduct tablosunda tanımlıysa onu kullan, yoksa sistem ayarını kullan
        $productVatRate = $defaultVatRate; // Sistem ayarındaki oran (0.10 = %10)
        if ($order->resolved_product_vat_rate !== null && (float) $order->resolved_product_vat_rate > 0) {
            $productVatRate = (float) $order->resolved_product_vat_rate / 100;
        } elseif ($resolvedBarcode) {
            $cacheKey = $this->productCacheKey($userId, $resolvedBarcode);

            if (!array_key_exists($cacheKey, $this->vatRatesCache)) {
                $matchedProduct = \App\Models\MpProduct::query()
                    ->when($userId, fn($query) => $query->where('user_id', $userId))
                    ->where('barcode', $resolvedBarcode)
                    ->first(['barcode', 'vat_rate']);
                $this->vatRatesCache[$cacheKey] = $matchedProduct ? $matchedProduct->vat_rate : null;
            }
            
            $dbVatRate = $this->vatRatesCache[$cacheKey];
            if ($dbVatRate !== null) {
                $productVatRate = (float) $dbVatRate / 100; // DB'de 10 olarak saklanır → 0.10
            }
        }

        // ─── KDV Hesaplaması ────────────────────────────────────
        // Satış KDV'si: Brüt tutar içinde dahili KDV
        $salesVat = $grossAmount * $productVatRate / (1 + $productVatRate);

        // Gider KDV'si: Komisyon ve kargo faturalarından (sabit %20)
        // NOT: commission_amount ve cargo_amount DB'de negatif saklanır, abs() ile mutlak değer alınır
        $expenseVatRate = $svc->getExpenseVatRate();
        $commissionVat  = abs($commissionAmount) * $expenseVatRate;
        $cargoVat       = abs($cargoAmount) * $expenseVatRate;
        $totalExpenseVat = $commissionVat + $cargoVat;

        // Net KDV: Pozitif = devlete ödenir, Negatif = avantaj (mahsup fazlası)
        $netVat = round($salesVat - $totalExpenseVat, 2);

        // KDV hesaplama kapalıysa tüm KDV değerlerini sıfırla
        if (!$svc->isKdvEnabled()) {
            $salesVat = 0;
            $totalExpenseVat = 0;
            $netVat = 0;
        }

        // ─── Gerçek Net Kâr ─────────────────────────────────────
        // Eğer KDV negatifse (avantaj), kâra eklenir. Pozitifse (yük), kârdan düşülmez
        // çünkü zaten satış fiyatı içinde tahsil edilmiş — devlete ödenmesi gereken tutar.
        // Gerçek formül: Kâr = Hakediş - COGS - Ambalaj - Kargo - max(0, netVat) [vergi yükü]
        // Ancak kullanıcı "Net KDV Avantajı/Yükü" dahil etmek istiyor
        $realNetProfitWithVat = round($hakedis - $cogs - $packaging - $ownCargo - $netVat, 2);

        // ─── Epik 8 / Düzeltme: Stopaj Yükü (Teorik veya Pratik) ───
        $actualStopaj = (float) $order->withholding_tax;
        if ($actualStopaj <= 0 && !in_array($order->status, ['İptal Edildi'])) {
            // stopaj_rate ayarı 0.01 formatında tutulur (%1)
            $stopajRate = $svc->getStopajRate();
            $totalDiscounts = abs((float) $order->discount_amount) + abs((float) $order->campaign_discount);
            $discountedGross = max(0, $grossAmount - $totalDiscounts);
            $vatExcludedBase = $discountedGross / (1 + $productVatRate);
            $actualStopaj = round($vatExcludedBase * $stopajRate, 2);
        }
        
        // Stopajı da net kârdan (ve hakedişten cebe girenden) düşüyoruz
        $realNetProfitWithVat = round($realNetProfitWithVat - $actualStopaj, 2);

        return [
            'order_number'     => $order->order_number,
            'group_key'        => $this->resolveSkuGroupKey(
                $resolvedBarcode,
                $resolvedStockCode,
                $resolvedProductName,
                $order->order_number
            ),
            'barcode'          => $resolvedBarcode,
            'stock_code'       => $resolvedStockCode,
            'product_name'     => $resolvedProductName,
            'quantity'         => $resolvedQuantity,
            'status'           => $order->status,
            'gross_amount'     => $grossAmount,
            'hakedis'          => $hakedis,
            'commission'       => $commissionAmount,
            'cargo'            => $cargoAmount,
            'own_cargo'        => $ownCargo,
            'cogs'             => $cogs,
            'packaging'        => $packaging,
            'sales_vat'        => round($salesVat, 2),
            'expense_vat'      => round($totalExpenseVat, 2),
            'net_vat'          => $netVat,
            'stopaj_deduction' => $actualStopaj,
            'real_net_profit'  => $realNetProfitWithVat,
            'is_bleeding'      => $realNetProfitWithVat < 0,
            'has_cogs'         => $cogs > 0,
            'cogs_missing_reason' => $order->cogs_missing_reason,
            'margin_percent'   => ProfitabilityMetric::multiplierOrZero(
                $realNetProfitWithVat,
                ProfitabilityMetric::productCost($cogs, $packaging),
            ),
        ];
    }

    /**
     * Dönem içindeki başarılı siparişler için birim iktisadı listesi
     */
    public function calculateForPeriod(MpPeriod $period): Collection
    {
        $results = collect();

        // N+1 Önlemi: Dönemdeki tüm farklı barkodların KDV oranlarını tek sorgu ile önbelleğe al
        // chunk içi N+1 olmasın diye önce tüm period unique barkodları bulup önbellekleyelim.
        $uniqueBarcodes = MpOrder::where('period_id', $period->id)
            ->where('status', 'Teslim Edildi')
            ->distinct()
            ->pluck('barcode')
            ->filter()
            ->values()
            ->toArray();
            
        if (!empty($uniqueBarcodes)) {
            $products = \App\Models\MpProduct::query()
                ->when($period->user_id, fn($query) => $query->where('user_id', $period->user_id))
                ->whereIn('barcode', $uniqueBarcodes)
                ->get(['barcode', 'vat_rate']);
            
            foreach ($products as $product) {
                $this->vatRatesCache[$this->productCacheKey($period->user_id, $product->barcode)] = $product->vat_rate;
            }
        }

        MpOrder::where('period_id', $period->id)
            ->where('status', 'Teslim Edildi')
            ->with(['period', 'operationalOrder.items'])
            ->orderByDesc('order_date')
            ->chunk(1000, function ($orders) use (&$results) {
                foreach ($orders as $order) {
                    $results->push($this->calculateForOrder($order));
                }
            });

        return $results;
    }

    /**
     * SKU/Barkod bazında kârlılık özeti (Kâr Motoru)
     * Her barkod için aggregate: toplam adet, toplam ciro, toplam kâr, ortalama margin
     */
    public function profitBySku(MpPeriod $period): Collection
    {
        $allOrders = $this->calculateForPeriod($period);

        return $allOrders
            ->groupBy('group_key')
            ->map(function (Collection $items) {
                $first = $items->first();
                $totalQty       = $items->sum('quantity');
                $totalGross     = $items->sum('gross_amount');
                $totalHakedis   = $items->sum('hakedis');
                $totalCogs      = $items->sum('cogs');
                $totalPackaging = $items->sum('packaging');
                $totalNetProfit = $items->sum('real_net_profit');
                $bleedingCount  = $items->where('is_bleeding', true)->count();
                $hasCogs        = $items->where('has_cogs', true)->isNotEmpty();
                $cogsMissingReason = $items
                    ->where('has_cogs', false)
                    ->pluck('cogs_missing_reason')
                    ->filter()
                    ->first();

                return [
                    'barcode'          => $first['barcode'] ?: '-',
                    'stock_code'       => $first['stock_code'] ?: '-',
                    'product_name'     => $first['product_name'] ?: ('Sipariş #' . ($first['order_number'] ?? '-')),
                    'order_count'      => $items->count(),
                    'total_quantity'   => $totalQty,
                    'total_gross'      => round($totalGross, 2),
                    'total_hakedis'    => round($totalHakedis, 2),
                    'total_cogs'       => round($totalCogs, 2),
                    'total_packaging'  => round($totalPackaging, 2),
                    'total_net_profit' => round($totalNetProfit, 2),
                    'avg_margin'       => ProfitabilityMetric::multiplierOrZero(
                        $totalNetProfit,
                        ProfitabilityMetric::productCost($totalCogs, $totalPackaging),
                    ),
                    'bleeding_count'   => $bleedingCount,
                    'is_bleeding'      => $totalNetProfit < 0,
                    'has_cogs'         => $hasCogs,
                    'cogs_missing_reason' => $cogsMissingReason,
                ];
            })
            ->sortBy('total_net_profit') // En zararlı ürün en üstte
            ->values();
    }

    /**
     * Tüm siparişler için calculated_net_profit güncelle (batch)
     */
    public function recalculateAll(MpPeriod $period): int
    {
        $orders = MpOrder::where('period_id', $period->id)
            ->where('status', 'Teslim Edildi')
            ->with(['period', 'operationalOrder.items'])
            ->get();

        $count = 0;
        foreach ($orders as $order) {
            $result = $this->calculateForOrder($order);
            $order->update(['calculated_net_profit' => $result['real_net_profit']]);
            $count++;
        }

        return $count;
    }

    protected function resolveUserIdForOrder(MpOrder $order): ?int
    {
        if ($order->relationLoaded('period')) {
            return $order->period?->user_id;
        }

        if (array_key_exists($order->period_id, $this->periodUserCache)) {
            return $this->periodUserCache[$order->period_id];
        }

        return $this->periodUserCache[$order->period_id] = \App\Models\MpPeriod::whereKey($order->period_id)->value('user_id');
    }

    protected function productCacheKey(?int $userId, string $barcode): string
    {
        return ($userId ?? 0) . '|' . $barcode;
    }

    protected function resolveSkuGroupKey(
        ?string $barcode,
        ?string $stockCode,
        ?string $productName,
        string $orderNumber
    ): string {
        if (filled($barcode)) {
            return 'barcode:' . $barcode;
        }

        if (filled($stockCode)) {
            return 'stock:' . $stockCode;
        }

        if (filled($productName)) {
            return 'name:' . mb_strtolower(trim($productName));
        }

        return 'order:' . $orderNumber;
    }
}
