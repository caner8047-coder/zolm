<?php

namespace App\Services;

use App\Models\MpPeriod;
use App\Models\MpOrder;
use App\Models\MpTransaction;
use App\Models\MpFinancialRule;
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

    /**
     * Belirli bir sipariş için birim iktisadı hesapla
     */
    public function calculateForOrder(MpOrder $order): array
    {
        $grossAmount      = (float) $order->gross_amount;
        $hakedis          = (float) $order->net_hakedis;
        $commissionAmount = (float) $order->commission_amount;
        $cargoAmount      = (float) $order->cargo_amount;
        $cogs             = (float) ($order->cogs_at_time ?? 0);
        $packaging        = (float) ($order->packaging_cost_at_time ?? 0);
        $svc              = new \App\Services\MpSettingsService();
        $defaultVatRate   = $svc->getDefaultProductVatRate();
        
        // Ürün bazlı KDV oranı: MpProduct tablosunda tanımlıysa onu kullan, yoksa sistem ayarını kullan
        $productVatRate = $defaultVatRate; // Sistem ayarındaki oran (0.10 = %10)
        if ($order->barcode) {
            if (!array_key_exists($order->barcode, $this->vatRatesCache)) {
                $matchedProduct = \App\Models\MpProduct::where('barcode', $order->barcode)->first(['barcode', 'vat_rate']);
                $this->vatRatesCache[$order->barcode] = $matchedProduct ? $matchedProduct->vat_rate : null;
            }
            
            $dbVatRate = $this->vatRatesCache[$order->barcode];
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
        $realNetProfit = round($hakedis - $cogs - $packaging + min(0, $netVat), 2);
        // Eğer KDV negatifse (avantaj), kâra eklenir. Pozitifse (yük), kârdan düşülmez
        // çünkü zaten satış fiyatı içinde tahsil edilmiş — devlete ödenmesi gereken tutar.
        // Gerçek formül: Kâr = Hakediş - COGS - Ambalaj - max(0, netVat) [vergi yükü]
        // Ancak kullanıcı "Net KDV Avantajı/Yükü" dahil etmek istiyor
        $realNetProfitWithVat = round($hakedis - $cogs - $packaging - $netVat, 2);

        // ─── Epik 8 / Düzeltme: Stopaj Yükü (Teorik veya Pratik) ───
        $actualStopaj = (float) $order->withholding_tax;
        if ($actualStopaj <= 0 && !in_array($order->status, ['İptal Edildi'])) {
            $stopajRate = $svc->getStopajRate() / 100; // API returns e.g. 1.0 -> 0.01
            $totalDiscounts = abs((float) $order->discount_amount) + abs((float) $order->campaign_discount);
            $discountedGross = max(0, $grossAmount - $totalDiscounts);
            $vatExcludedBase = $discountedGross / (1 + $productVatRate);
            $actualStopaj = round($vatExcludedBase * $stopajRate, 2);
        }
        
        // Stopajı da net kârdan (ve hakedişten cebe girenden) düşüyoruz
        $realNetProfitWithVat = round($realNetProfitWithVat - $actualStopaj, 2);

        return [
            'order_number'     => $order->order_number,
            'barcode'          => $order->barcode,
            'stock_code'       => $order->stock_code,
            'product_name'     => $order->product_name,
            'quantity'         => $order->quantity,
            'status'           => $order->status,
            'gross_amount'     => $grossAmount,
            'hakedis'          => $hakedis,
            'commission'       => $commissionAmount,
            'cargo'            => $cargoAmount,
            'cogs'             => $cogs,
            'packaging'        => $packaging,
            'sales_vat'        => round($salesVat, 2),
            'expense_vat'      => round($totalExpenseVat, 2),
            'net_vat'          => $netVat,
            'stopaj_deduction' => $actualStopaj,
            'real_net_profit'  => $realNetProfitWithVat,
            'is_bleeding'      => $realNetProfitWithVat < 0,
            'has_cogs'         => $cogs > 0,
            'margin_percent'   => $grossAmount > 0
                ? round(($realNetProfitWithVat / $grossAmount) * 100, 1)
                : 0,
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
            $products = \App\Models\MpProduct::whereIn('barcode', $uniqueBarcodes)
                ->get(['barcode', 'vat_rate']);
            
            foreach ($products as $product) {
                $this->vatRatesCache[$product->barcode] = $product->vat_rate;
            }
        }

        MpOrder::where('period_id', $period->id)
            ->where('status', 'Teslim Edildi')
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
            ->groupBy('barcode')
            ->map(function (Collection $items, string $barcode) {
                $first = $items->first();
                $totalQty       = $items->sum('quantity');
                $totalGross     = $items->sum('gross_amount');
                $totalHakedis   = $items->sum('hakedis');
                $totalCogs      = $items->sum('cogs');
                $totalPackaging = $items->sum('packaging');
                $totalNetProfit = $items->sum('real_net_profit');
                $bleedingCount  = $items->where('is_bleeding', true)->count();
                $hasCogs        = $items->where('has_cogs', true)->isNotEmpty();

                return [
                    'barcode'          => $barcode,
                    'stock_code'       => $first['stock_code'] ?? '-',
                    'product_name'     => $first['product_name'] ?? '-',
                    'order_count'      => $items->count(),
                    'total_quantity'   => $totalQty,
                    'total_gross'      => round($totalGross, 2),
                    'total_hakedis'    => round($totalHakedis, 2),
                    'total_cogs'       => round($totalCogs, 2),
                    'total_packaging'  => round($totalPackaging, 2),
                    'total_net_profit' => round($totalNetProfit, 2),
                    'avg_margin'       => $totalGross > 0
                        ? round(($totalNetProfit / $totalGross) * 100, 1)
                        : 0,
                    'bleeding_count'   => $bleedingCount,
                    'is_bleeding'      => $totalNetProfit < 0,
                    'has_cogs'         => $hasCogs,
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
            ->whereNotNull('cogs_at_time')
            ->get();

        $count = 0;
        foreach ($orders as $order) {
            $result = $this->calculateForOrder($order);
            $order->update(['calculated_net_profit' => $result['real_net_profit']]);
            $count++;
        }

        return $count;
    }
}
