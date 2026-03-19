<?php

namespace App\Livewire\Cargo;

use App\Models\CargoReport;
use App\Models\CargoReportItem;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
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
    public string $filterCargoCompany = '';
    public string $filterMarketplace = '';
    public string $filterStore = '';
    public string $filterRecordType = 'all';

    public function mount()
    {
        $this->customStartDate = now()->subDays(30)->format('Y-m-d');
        $this->customEndDate = now()->format('Y-m-d');
    }

    #[Computed]
    public function cargoCompanies()
    {
        return CargoReport::query()
            ->whereNotNull('cargo_company')
            ->distinct()
            ->orderBy('cargo_company')
            ->pluck('cargo_company');
    }

    #[Computed]
    public function marketplaces()
    {
        return CargoReportItem::query()
            ->whereNotNull('pazaryeri')
            ->where('pazaryeri', '!=', '')
            ->distinct()
            ->orderBy('pazaryeri')
            ->pluck('pazaryeri');
    }

    #[Computed]
    public function stores()
    {
        return CargoReportItem::query()
            ->whereNotNull('magaza')
            ->where('magaza', '!=', '')
            ->distinct()
            ->orderBy('magaza')
            ->pluck('magaza');
    }

    protected function filteredItemsQuery(): Builder
    {
        [$startDate, $endDate] = $this->getDateRange();

        $query = CargoReportItem::query()
            ->whereHas('report', function (Builder $reportQuery) use ($startDate, $endDate) {
                $reportQuery->whereBetween('report_date', [$startDate, $endDate]);

                if ($this->filterCargoCompany !== '') {
                    $reportQuery->where('cargo_company', $this->filterCargoCompany);
                }
            });

        if ($this->filterMarketplace !== '') {
            $query->where('pazaryeri', $this->filterMarketplace);
        }

        if ($this->filterStore !== '') {
            $query->where('magaza', $this->filterStore);
        }

        if ($this->filterRecordType === 'siparis') {
            $query->where('is_iade', false)->where('is_parca_gonderi', false);
        } elseif ($this->filterRecordType === 'iade') {
            $query->where('is_iade', true);
        } elseif ($this->filterRecordType === 'parca') {
            $query->where('is_parca_gonderi', true);
        }

        return $query;
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
                $this->customStartDate ? Carbon::parse($this->customStartDate)->startOfDay() : now()->subDays(30)->startOfDay(),
                $this->customEndDate ? Carbon::parse($this->customEndDate)->endOfDay() : now()->endOfDay(),
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
        $items = $this->filteredItemsQuery();
        $normalOrders = (clone $items)->where('is_iade', false)->where('is_parca_gonderi', false);

        $totalReports = (clone $items)->distinct('cargo_report_id')->count('cargo_report_id');
        $totalOrders = (clone $normalOrders)->count();
        $matchedOrders = (clone $normalOrders)->where('is_matched', true)->count();
        $errorCount = (clone $items)->where('has_error', true)->count();
        $totalDesiDiff = (clone $items)->sum('desi_fark');
        $totalTutarDiff = (clone $items)->sum('tutar_fark');

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
        $items = $this->filteredItemsQuery()->where('has_error', true);

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
        $data = $this->filteredItemsQuery()
            ->join('cargo_reports', 'cargo_report_items.cargo_report_id', '=', 'cargo_reports.id')
            ->select(
                DB::raw('DATE(cargo_reports.report_date) as date'),
                DB::raw('SUM(CASE WHEN cargo_report_items.is_iade = 0 AND cargo_report_items.is_parca_gonderi = 0 THEN 1 ELSE 0 END) as orders'),
                DB::raw('SUM(CASE WHEN cargo_report_items.has_error = 1 THEN 1 ELSE 0 END) as errors'),
                DB::raw('SUM(cargo_report_items.tutar_fark) as tutar_diff')
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
        // İade trendi
        $iadeData = $this->filteredItemsQuery()
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
        $parcaData = $this->filteredItemsQuery()
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
        $query = $this->filteredItemsQuery()->where('has_error', true);

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
            'referans_eksik' => ['name' => 'Referans Eksik', 'icon' => '🧩', 'color' => '#D97706'],
            'parca_eksik' => ['name' => 'Parça Eksik', 'icon' => '📦', 'color' => '#DC2626'],
            'parca_fazla' => ['name' => 'Parça Fazla', 'icon' => '🧱', 'color' => '#F59E0B'],
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
        return $this->filteredItemsQuery()
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

    #[Computed]
    public function channelInsights(): array
    {
        $marketplaces = $this->filteredItemsQuery()
            ->whereNotNull('pazaryeri')
            ->where('pazaryeri', '!=', '')
            ->select(
                'pazaryeri',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN has_error = 1 THEN 1 ELSE 0 END) as errors'),
                DB::raw('SUM(ABS(tutar_fark)) as tutar_impact')
            )
            ->groupBy('pazaryeri')
            ->orderByDesc('tutar_impact')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'label' => $row->pazaryeri,
                'total' => (int) $row->total,
                'errors' => (int) $row->errors,
                'tutar_impact' => (float) $row->tutar_impact,
            ])
            ->toArray();

        $stores = $this->filteredItemsQuery()
            ->whereNotNull('magaza')
            ->where('magaza', '!=', '')
            ->select(
                'magaza',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN has_error = 1 THEN 1 ELSE 0 END) as errors'),
                DB::raw('SUM(ABS(tutar_fark)) as tutar_impact')
            )
            ->groupBy('magaza')
            ->orderByDesc('tutar_impact')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'label' => $row->magaza,
                'total' => (int) $row->total,
                'errors' => (int) $row->errors,
                'tutar_impact' => (float) $row->tutar_impact,
            ])
            ->toArray();

        $categories = $this->filteredItemsQuery()
            ->whereNotNull('stok_kodu')
            ->where('stok_kodu', '!=', '')
            ->select(
                DB::raw("SUBSTRING(stok_kodu, 2, 3) as category_code"),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN has_error = 1 THEN 1 ELSE 0 END) as errors'),
                DB::raw('SUM(ABS(tutar_fark)) as tutar_impact')
            )
            ->groupBy('category_code')
            ->orderByDesc('tutar_impact')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'label' => Product::getCategoryName($row->category_code),
                'total' => (int) $row->total,
                'errors' => (int) $row->errors,
                'tutar_impact' => (float) $row->tutar_impact,
            ])
            ->toArray();

        return [
            'marketplaces' => $marketplaces,
            'stores' => $stores,
            'categories' => $categories,
        ];
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

    public function updatedCustomStartDate()
    {
        $this->dispatch('chartsUpdated');
    }

    public function updatedCustomEndDate()
    {
        $this->dispatch('chartsUpdated');
    }

    public function updatedFilterCargoCompany()
    {
        $this->dispatch('chartsUpdated');
    }

    public function updatedFilterMarketplace()
    {
        $this->dispatch('chartsUpdated');
    }

    public function updatedFilterStore()
    {
        $this->dispatch('chartsUpdated');
    }

    public function updatedFilterRecordType()
    {
        $this->dispatch('chartsUpdated');
    }

    public function render()
    {
        return view('livewire.cargo.cargo-dashboard');
    }
}
