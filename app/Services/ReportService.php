<?php

namespace App\Services;

use App\Models\MpPeriod;
use App\Models\MpOrder;
use App\Models\MpTransaction;
use App\Models\MpAuditLog;
use App\Models\MpFinancialRule;
use Illuminate\Support\Facades\DB;

/**
 * Pazaryeri Muhasebe — Raporlama Servisi
 *
 * Dashboard için 5 özel KPI kartı ve aylık/yıllık özet raporları üretir.
 * Tüm hesaplamalar mp_orders ve mp_transactions üzerinden Eloquent aggregate
 * sorgularıyla yapılır.
 */
class ReportService
{
    // ═══════════════════════════════════════════════════════════════
    // 5 TEMEL KPI KARTI
    // ═══════════════════════════════════════════════════════════════

    /**
     * KPI 1: Toplam Brüt Ciro
     * Başarılı (Teslim Edildi) siparişlerin müşteri ödeme tutarı toplamı
     */
    public function totalBrutCiro(int|array $periodIds): float
    {
        $periodIds = $this->normalizePeriodIds($periodIds);
        if (empty($periodIds)) return 0.0;

        return (float) MpOrder::whereIn('period_id', $periodIds)
            ->where('status', 'Teslim Edildi')
            ->sum('gross_amount');
    }

    /**
     * KPI 2: Peşin Ödenen Stopaj Toplamı
     * Yıl sonu vergiden düşülecek brüt üzerinden kesilen %1 toplam
     */
    public function totalStopaj(int|array $periodIds): float
    {
        $periodIds = $this->normalizePeriodIds($periodIds);
        if (empty($periodIds)) return 0.0;

        $actualSum = (float) MpOrder::whereIn('period_id', $periodIds)->sum('withholding_tax');
        
        // Eğer E-Ticaret Stopaj Excel'i sisteme yüklenmemişse (çok küçükse), tahmini hesapla
        // Tüm başarılı ve iade edilen (stoktan çıkan) siparişlerin KDV hariç brüt toplamı üzerinden
        if ($actualSum < 10) { 
            $stopajRate = MpFinancialRule::getRuleFloat('stopaj_rate') ?: 0.01;
            $defaultVatRate = (MpFinancialRule::getRuleFloat('default_product_vat_rate') ?: 0.20) * 100;
            
            $theoretical = MpOrder::whereIn('period_id', $periodIds)
                ->whereNotIn('status', ['İptal Edildi']) // İptaller faturaya/vergiye yansımaz
                ->selectRaw("SUM( GREATEST(COALESCE(gross_amount,0) - ABS(COALESCE(discount_amount,0)) - ABS(COALESCE(campaign_discount,0)), 0) / (1 + (COALESCE(product_vat_rate,?) / 100)) * ? ) as total", [$defaultVatRate, $stopajRate])
                ->value('total');
                
            return (float) $theoretical;
        }
        
        return $actualSum;
    }

    /**
     * KPI 3: Lojistik Zararı (Yanık Maliyet)
     * İade siparişlerin geri ödenmeyen gidiş kargosu + Cari Ekstre'den iade kargo cezaları
     */
    public function logisticLoss(int|array $periodIds): array
    {
        $periodIds = $this->normalizePeriodIds($periodIds);
        if (empty($periodIds)) {
            return ['sunk_cargo' => 0, 'return_cargo' => 0, 'total' => 0];
        }

        // Gidiş kargo yanık maliyeti (iade edilmiş siparişlerden)
        $sunkCargo = (float) MpOrder::whereIn('period_id', $periodIds)
            ->where('status', 'İade Edildi')
            ->sum('cargo_amount');

        // Dönüş kargo cezaları (Cari Ekstre'den)
        $returnCargo = (float) MpTransaction::whereIn('period_id', $periodIds)
            ->where(function ($q) {
                $q->where('transaction_type', 'like', '%İade Kargo%')
                  ->orWhere('transaction_type', 'like', '%iade kargo%')
                  ->orWhere('transaction_type', 'like', '%Teslimat Başarısızlık%');
            })
            ->sum('debt');

        $total = $sunkCargo + $returnCargo;

        return [
            'sunk_cargo'   => round($sunkCargo, 2),
            'return_cargo' => round($returnCargo, 2),
            'total'        => round($total, 2),
        ];
    }

    /**
     * KPI 4: Devlete Ödenecek Net KDV
     * Satış KDV'si (ürün KDV'si) eksi Gider KDV'leri (kargo + komisyon %20)
     *
     * Her sipariş için ayrı hesap (ürün KDV oranı değişken olabilir)
     */
    public function netVatPayable(int|array $periodIds): array
    {
        $periodIds = $this->normalizePeriodIds($periodIds);
        if (empty($periodIds)) {
            return [
                'sales_vat'   => 0,
                'expense_vat' => 0,
                'net_vat'     => 0,
                'is_payable'  => false,
            ];
        }

        $svc = new \App\Services\MpSettingsService();
        
        // KDV hesaplama kapalıysa sıfır döndür
        if (!$svc->isKdvEnabled()) {
            return [
                'sales_vat'   => 0,
                'expense_vat' => 0,
                'net_vat'     => 0,
                'is_payable'  => false,
            ];
        }

        $expenseVatRate = $svc->getExpenseVatRate();
        $defaultVatRate = $svc->getDefaultProductVatRate();

        $totalSalesVat   = 0;
        $totalExpenseVat  = 0;

        MpOrder::whereIn('period_id', $periodIds)
            ->where('status', 'Teslim Edildi')
            ->select(['gross_amount', 'product_vat_rate', 'commission_amount', 'cargo_amount'])
            ->chunk(2000, function ($orders) use (&$totalSalesVat, &$totalExpenseVat, $defaultVatRate, $expenseVatRate) {
                foreach ($orders as $order) {
                    $productVatRate = (float) ($order->product_vat_rate ?? ($defaultVatRate * 100)) / 100;
                    $grossAmount    = (float) $order->gross_amount;

                    // Satış KDV: dahili KDV (brüt tutarın içinde)
                    $salesVat = $grossAmount * $productVatRate / (1 + $productVatRate);
                    $totalSalesVat += $salesVat;

                    // Gider KDV: komisyon ve kargo faturalarından
                    $commissionVat = abs((float) $order->commission_amount) * $expenseVatRate;
                    $cargoVat      = abs((float) $order->cargo_amount) * $expenseVatRate;
                    $totalExpenseVat += ($commissionVat + $cargoVat);
                }
            });

        $netVat = round($totalSalesVat - $totalExpenseVat, 2);

        return [
            'sales_vat'   => round($totalSalesVat, 2),
            'expense_vat' => round($totalExpenseVat, 2),
            'net_vat'     => $netVat,
            'is_payable'  => $netVat > 0, // true = devlete ödeme var
        ];
    }

    /**
     * KPI 5: Toplam Gerçek Net Kâr
     * Hakediş - COGS - Ambalaj - Net KDV Yükü (devlete ödenecek)
     */
    public function totalRealNetProfit(int|array $periodIds): array
    {
        $periodIds = $this->normalizePeriodIds($periodIds);
        if (empty($periodIds)) {
            return ['total_profit' => 0, 'profitable_count' => 0, 'bleeding_count' => 0, 'has_cogs' => false, 'no_cogs_count' => 0];
        }

        $unitService = new UnitEconomicsService();
        $results = collect();

        MpPeriod::whereIn('id', $periodIds)->get()->each(function (MpPeriod $period) use ($unitService, &$results) {
            $results = $results->merge($unitService->calculateForPeriod($period));
        });

        $totalProfit     = $results->sum('real_net_profit');
        $bleedingCount   = $results->where('is_bleeding', true)->count();
        $profitableCount = $results->where('is_bleeding', false)->count();
        $hasCogs         = $results->where('has_cogs', true)->isNotEmpty();
        $noCogs          = $results->where('has_cogs', false)->count();

        return [
            'total_profit'     => round($totalProfit, 2),
            'profitable_count' => $profitableCount,
            'bleeding_count'   => $bleedingCount,
            'has_cogs'         => $hasCogs,
            'no_cogs_count'    => $noCogs,
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // KPI 6: NAKİT AKIŞI KANBAN (Cash Flow)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Vadesi gelen/gelecek hakedişleri (Settlements) haftalık vizyona göre gruplar.
     */
    public function expectedCashFlow(int|array $periodIds): array
    {
        $periodIds = $this->normalizePeriodIds($periodIds);
        if (empty($periodIds)) {
            return ['total_expected' => 0, 'kanban' => []];
        }

        $settlements = \App\Models\MpSettlement::whereIn('period_id', $periodIds)
            ->whereNotNull('due_date')
            ->orderBy('due_date', 'asc')
            ->get();

        $grouped = [];
        $totalExpected = 0;

        foreach ($settlements as $s) {
            $date = \Carbon\Carbon::parse($s->due_date)->startOfDay();
            $today = \Carbon\Carbon::today();
            
            if ($date->isBefore($today) && !$s->is_reconciled) {
                $status = 'Geciken Ödemeler';
                $color = 'red';
            } elseif ($date->isBetween($today->copy()->startOfWeek(), $today->copy()->endOfWeek())) {
                $status = 'Bu Hafta Yatacaklar';
                $color = 'emerald';
            } elseif ($date->isBetween($today->copy()->addWeek()->startOfWeek(), $today->copy()->addWeek()->endOfWeek())) {
                $status = 'Gelecek Hafta';
                $color = 'blue';
            } else {
                if ($date->isBefore($today) && $s->is_reconciled) {
                    continue; // Geçmiş ve yatmış olanları nakit akışında gösterme
                }
                $status = 'Daha Sonraki Vadeler';
                $color = 'gray';
            }

            if (!isset($grouped[$status])) {
                $grouped[$status] = [
                    'label'  => $status,
                    'color'  => $color,
                    'amount' => 0,
                    'count'  => 0
                ];
            }
            
            // Satıcıya ödenecek net rakam (Eksi de olabilir ceza varsa)
            $amount = (float) $s->seller_hakedis;
            
            if ($amount != 0) {
                $grouped[$status]['amount'] += $amount;
                $grouped[$status]['count']++;
                if($amount > 0) {
                    $totalExpected += $amount;
                }
            }
        }

        // Görüntüleme sırasını garantile
        $order = ['Geciken Ödemeler', 'Bu Hafta Yatacaklar', 'Gelecek Hafta', 'Daha Sonraki Vadeler'];
        $final = [];
        foreach ($order as $o) {
            if (isset($grouped[$o]) && $grouped[$o]['count'] > 0) {
                $final[] = $grouped[$o];
            }
        }

        return [
            'total_expected' => round($totalExpected, 2),
            'kanban'         => $final
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // DASHBOARD İÇİN 5 KPI TOPLUCA
    // ═══════════════════════════════════════════════════════════════

    /**
     * Tüm KPI'ları tek seferde döndür (Dashboard'a optimal)
     */
    public function getDashboardKpis(int|array $periodIds): array
    {
        $periodIds = $this->normalizePeriodIds($periodIds);

        $brut     = $this->totalBrutCiro($periodIds);
        $stopaj   = $this->totalStopaj($periodIds);
        $logistic = $this->logisticLoss($periodIds);
        $vat      = $this->netVatPayable($periodIds);
        $profit   = $this->totalRealNetProfit($periodIds);
        $cashFlow  = $this->expectedCashFlow($periodIds);

        // Temel sipariş sayıları (ek context)
        $totalOrders  = MpOrder::whereIn('period_id', $periodIds)->count();
        $totalReturns = MpOrder::whereIn('period_id', $periodIds)->where('status', 'İade Edildi')->count();
        $totalCancels = MpOrder::whereIn('period_id', $periodIds)->where('status', 'İptal Edildi')->count();
        $returnRate   = $totalOrders > 0 ? round(($totalReturns / $totalOrders) * 100, 1) : 0;

        // Toplam Hakediş
        $totalHakedis = (float) MpOrder::whereIn('period_id', $periodIds)
            ->where('status', 'Teslim Edildi')
            ->sum('net_hakedis');

        // Audit toplamları
        $auditCount = MpAuditLog::whereIn('period_id', $periodIds)
            ->whereIn('severity', ['critical', 'warning'])
            ->where('status', 'open')
            ->count();
        $auditAmount = (float) MpAuditLog::whereIn('period_id', $periodIds)
            ->whereIn('severity', ['critical', 'warning'])
            ->where('status', 'open')
            ->sum('difference');

        return [
            // 5 Ana KPI
            'total_brut'       => $brut,
            'total_stopaj'     => $stopaj,
            'logistic_loss'    => $logistic,
            'net_vat'          => $vat,
            'real_profit'      => $profit,
            'cash_flow'        => $cashFlow,

            // Ek context
            'total_hakedis'    => round($totalHakedis, 2),
            'total_orders'     => $totalOrders,
            'total_returns'    => $totalReturns,
            'total_cancels'    => $totalCancels,
            'return_rate'      => $returnRate,
            'audit_count'      => $auditCount,
            'audit_amount'     => round($auditAmount, 2),
        ];
    }

    protected function normalizePeriodIds(int|array $periodIds): array
    {
        $ids = is_array($periodIds) ? $periodIds : [$periodIds];

        return collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->toArray();
    }

    // ═══════════════════════════════════════════════════════════════
    // AYLIK PİVOT RAPORU
    // ═══════════════════════════════════════════════════════════════

    /**
     * Aylık pivot özeti (Excel export ve UI için)
     */
    public function monthlyPivot(int $periodId): array
    {
        $kpis = $this->getDashboardKpis($periodId);
        $period = MpPeriod::findOrFail($periodId);

        // Kargo firması bazlı dağılım
        $cargoBreakdown = MpOrder::where('period_id', $periodId)
            ->where('status', 'Teslim Edildi')
            ->select('cargo_company', DB::raw('COUNT(*) as count'), DB::raw('SUM(cargo_amount) as total_cargo'))
            ->groupBy('cargo_company')
            ->get()
            ->toArray();

        // Durum dağılımı
        $statusBreakdown = MpOrder::where('period_id', $periodId)
            ->select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(gross_amount) as total_amount'))
            ->groupBy('status')
            ->get()
            ->toArray();

        // Komisyon toplamı
        $totalCommission = (float) MpOrder::where('period_id', $periodId)
            ->where('status', 'Teslim Edildi')
            ->sum('commission_amount');

        $totalCargo = (float) MpOrder::where('period_id', $periodId)
            ->where('status', 'Teslim Edildi')
            ->sum('cargo_amount');

        return [
            'period_name'      => $period->period_name,
            'year'             => $period->year,
            'month'            => $period->month,
            'kpis'             => $kpis,
            'total_commission' => round($totalCommission, 2),
            'total_cargo'      => round($totalCargo, 2),
            'cargo_breakdown'  => $cargoBreakdown,
            'status_breakdown' => $statusBreakdown,
        ];
    }
}
