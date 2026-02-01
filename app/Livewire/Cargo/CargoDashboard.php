<?php

namespace App\Livewire\Cargo;

use App\Models\CargoReport;
use App\Models\CargoReportItem;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Kargo Dashboard Bileşeni
 * 
 * Profesyonel analitik dashboard:
 * - Özet istatistikler
 * - Trend grafikleri
 * - En çok hatalı ürünler
 * - Maliyet analizi
 */
class CargoDashboard extends Component
{
    // Dönem seçimi
    public string $period = '30days'; // today, 7days, 30days, thisMonth, custom
    public ?string $customStartDate = null;
    public ?string $customEndDate = null;

    public function mount()
    {
        $this->customStartDate = now()->subDays(30)->format('Y-m-d');
        $this->customEndDate = now()->format('Y-m-d');
    }

    /**
     * Dönem tarih aralığını hesapla
     */
    protected function getDateRange(): array
    {
        return match($this->period) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            '7days' => [now()->subDays(7)->startOfDay(), now()->endOfDay()],
            '30days' => [now()->subDays(30)->startOfDay(), now()->endOfDay()],
            'thisMonth' => [now()->startOfMonth(), now()->endOfMonth()],
            'custom' => [
                $this->customStartDate ? now()->parse($this->customStartDate)->startOfDay() : now()->subDays(30)->startOfDay(),
                $this->customEndDate ? now()->parse($this->customEndDate)->endOfDay() : now()->endOfDay(),
            ],
            default => [now()->subDays(30)->startOfDay(), now()->endOfDay()],
        };
    }

    /**
     * Özet istatistikler
     */
    #[Computed]
    public function summaryStats(): array
    {
        [$startDate, $endDate] = $this->getDateRange();

        $reports = CargoReport::whereBetween('report_date', [$startDate, $endDate]);

        // Toplam raporlar
        $totalReports = $reports->count();
        $totalOrders = $reports->sum('total_orders');
        $matchedOrders = $reports->sum('matched_orders');
        $errorCount = $reports->sum('error_count');
        $totalDesiDiff = $reports->sum('total_desi_diff');
        $totalTutarDiff = $reports->sum('total_tutar_diff');

        // İade ve Parça istatistikleri (items tablosundan)
        $items = CargoReportItem::whereHas('report', function ($q) use ($startDate, $endDate) {
            $q->whereBetween('report_date', [$startDate, $endDate]);
        });

        $iadeCount = (clone $items)->where('is_iade', true)->count();
        $iadeTutar = (clone $items)->where('is_iade', true)->sum('gercek_tutar');
        
        $parcaCount = (clone $items)->where('is_parca_gonderi', true)->count();
        $parcaTutar = (clone $items)->where('is_parca_gonderi', true)->sum('gercek_tutar');

        return [
            'total_reports' => $totalReports,
            'total_orders' => $totalOrders,
            'matched_orders' => $matchedOrders,
            'error_count' => $errorCount,
            'iade_count' => $iadeCount,
            'iade_tutar' => $iadeTutar,
            'parca_count' => $parcaCount,
            'parca_tutar' => $parcaTutar,
            'total_desi_diff' => $totalDesiDiff,
            'total_tutar_diff' => $totalTutarDiff,
        ];
    }

    /**
     * Bize karşı vs Lehimize analizi
     */
    #[Computed]
    public function costAnalysis(): array
    {
        [$startDate, $endDate] = $this->getDateRange();

        $items = CargoReportItem::whereHas('report', function ($q) use ($startDate, $endDate) {
            $q->whereBetween('report_date', [$startDate, $endDate]);
        })->where('has_error', true);

        // Bize karşı (fazla ödeme) - tutar_fark > 0
        $againstUs = (clone $items)->where('tutar_fark', '>', 0);
        $againstUsCount = $againstUs->count();
        $againstUsTutar = $againstUs->sum('tutar_fark');

        // Lehimize (az ödeme) - tutar_fark < 0
        $forUs = (clone $items)->where('tutar_fark', '<', 0);
        $forUsCount = $forUs->count();
        $forUsTutar = abs($forUs->sum('tutar_fark'));

        return [
            'against_us_count' => $againstUsCount,
            'against_us_tutar' => $againstUsTutar,
            'for_us_count' => $forUsCount,
            'for_us_tutar' => $forUsTutar,
            'net_cost' => $againstUsTutar - $forUsTutar,
        ];
    }

    /**
     * Günlük trend verisi (Chart.js için)
     */
    #[Computed]
    public function dailyTrendData(): array
    {
        [$startDate, $endDate] = $this->getDateRange();

        $data = CargoReport::whereBetween('report_date', [$startDate, $endDate])
            ->select(
                DB::raw('DATE(report_date) as date'),
                DB::raw('SUM(total_orders) as orders'),
                DB::raw('SUM(error_count) as errors'),
                DB::raw('SUM(total_tutar_diff) as tutar_diff')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'labels' => $data->pluck('date')->map(fn($d) => \Carbon\Carbon::parse($d)->format('d.m'))->toArray(),
            'orders' => $data->pluck('orders')->toArray(),
            'errors' => $data->pluck('errors')->toArray(),
            'tutar_diff' => $data->pluck('tutar_diff')->toArray(),
        ];
    }

    /**
     * İade/Parça maliyet trendi
     */
    #[Computed]
    public function costTrendData(): array
    {
        [$startDate, $endDate] = $this->getDateRange();

        // İade trendi
        $iadeData = CargoReportItem::whereHas('report', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('report_date', [$startDate, $endDate]);
            })
            ->where('is_iade', true)
            ->join('cargo_reports', 'cargo_report_items.cargo_report_id', '=', 'cargo_reports.id')
            ->select(
                DB::raw('DATE(cargo_reports.report_date) as date'),
                DB::raw('SUM(cargo_report_items.gercek_tutar) as tutar')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('tutar', 'date')
            ->toArray();

        // Parça trendi
        $parcaData = CargoReportItem::whereHas('report', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('report_date', [$startDate, $endDate]);
            })
            ->where('is_parca_gonderi', true)
            ->join('cargo_reports', 'cargo_report_items.cargo_report_id', '=', 'cargo_reports.id')
            ->select(
                DB::raw('DATE(cargo_reports.report_date) as date'),
                DB::raw('SUM(cargo_report_items.gercek_tutar) as tutar')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('tutar', 'date')
            ->toArray();

        // Tüm tarihleri birleştir
        $allDates = array_unique(array_merge(array_keys($iadeData), array_keys($parcaData)));
        sort($allDates);

        return [
            'labels' => array_map(fn($d) => \Carbon\Carbon::parse($d)->format('d.m'), $allDates),
            'iade' => array_map(fn($d) => $iadeData[$d] ?? 0, $allDates),
            'parca' => array_map(fn($d) => $parcaData[$d] ?? 0, $allDates),
        ];
    }

    /**
     * Hata tipi dağılımı (Detaylı - Pie chart ve istatistikler için)
     */
    #[Computed]
    public function errorTypeDistribution(): array
    {
        [$startDate, $endDate] = $this->getDateRange();

        $query = CargoReportItem::whereHas('report', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('report_date', [$startDate, $endDate]);
            })
            ->where('has_error', true);

        // Hata tipleri ve tutarlar
        $data = (clone $query)
            ->select(
                'error_type', 
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(ABS(tutar_fark)) as total_tutar'),
                DB::raw('SUM(ABS(desi_fark)) as total_desi')
            )
            ->groupBy('error_type')
            ->get()
            ->keyBy('error_type')
            ->toArray();

        $labels = [
            'parca_eksik' => ['name' => 'Parça Eksik', 'icon' => '📦', 'color' => '#DC2626'],
            'desi_fazla' => ['name' => 'Desi Fazla', 'icon' => '📏', 'color' => '#EA580C'],
            'desi_eksik' => ['name' => 'Desi Eksik', 'icon' => '📉', 'color' => '#14B8A6'],
            'tutar_fazla' => ['name' => 'Tutar Fazla', 'icon' => '💰', 'color' => '#D97706'],
            'tutar_eksik' => ['name' => 'Tutar Eksik', 'icon' => '💸', 'color' => '#10B981'],
            'eslesmedi' => ['name' => 'Eşleşmedi', 'icon' => '❓', 'color' => '#6B7280'],
        ];

        // Gösterilen hataların toplamını hesapla (yüzdelerin doğru çıkması için)
        $totalErrors = 0;
        foreach ($labels as $key => $info) {
            if (isset($data[$key])) {
                $totalErrors += $data[$key]['count'];
            }
        }

        $details = [];
        $chartLabels = [];
        $chartData = [];
        $chartColors = [];

        foreach ($labels as $key => $info) {
            if (isset($data[$key]) && $data[$key]['count'] > 0) {
                $count = $data[$key]['count'];
                $tutar = $data[$key]['total_tutar'] ?? 0;
                $desi = $data[$key]['total_desi'] ?? 0;
                $percentage = $totalErrors > 0 ? round(($count / $totalErrors) * 100, 1) : 0;

                $details[] = [
                    'key' => $key,
                    'name' => $info['name'],
                    'icon' => $info['icon'],
                    'color' => $info['color'],
                    'count' => $count,
                    'percentage' => $percentage,
                    'tutar' => $tutar,
                    'desi' => $desi,
                ];

                $chartLabels[] = $info['name'];
                $chartData[] = $count;
                $chartColors[] = $info['color'];
            }
        }

        return [
            'labels' => $chartLabels,
            'data' => $chartData,
            'colors' => $chartColors,
            'details' => $details,
            'total' => $totalErrors,
        ];
    }

    /**
     * En çok hatalı ürünler (Top 10)
     */
    #[Computed]
    public function topErrorProducts(): array
    {
        [$startDate, $endDate] = $this->getDateRange();

        return CargoReportItem::whereHas('report', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('report_date', [$startDate, $endDate]);
            })
            ->where('has_error', true)
            ->whereNotNull('stok_kodu')
            ->select(
                'stok_kodu',
                DB::raw('MAX(urun_adi) as urun_adi'),
                DB::raw('COUNT(*) as error_count'),
                DB::raw('SUM(tutar_fark) as total_tutar_fark'),
                DB::raw('SUM(desi_fark) as total_desi_fark')
            )
            ->groupBy('stok_kodu')
            ->orderByDesc('error_count')
            ->limit(10)
            ->get()
            ->toArray();
    }

    /**
     * Dönem değiştiğinde
     */
    public function updatedPeriod()
    {
        // Computed property'ler otomatik yenilenir
        // Grafikleri yenilemek için event dispatch et
        $this->dispatch('chartsUpdated');
    }

    public function render()
    {
        return view('livewire.cargo.cargo-dashboard');
    }
}
