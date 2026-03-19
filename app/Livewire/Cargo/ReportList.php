<?php

namespace App\Livewire\Cargo;

use App\Models\CargoReport;
use App\Models\CargoReportItem;
use App\Services\ExcelService;
use App\Services\MpSettingsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Rapor Geçmişi Bileşeni
 * 
 * Tab 3: Raporlar
 * - Kayıtlı raporları listeleme
 * - Tarih ve kargo firması filtreleme
 * - Detaylı inceleme (modal)
 * - Rapor indirme
 */
class ReportList extends Component
{
    use WithPagination;

    public array $visibleColumns = ['date', 'name', 'company', 'orders', 'errors', 'desi_diff', 'amount_diff', 'actions'];
    public static array $sortableColumns = [
        'date' => 'report_date',
        'name' => 'name',
        'company' => 'cargo_company',
        'orders' => 'total_orders',
        'errors' => 'error_count',
        'desi_diff' => 'total_desi_diff',
        'amount_diff' => 'total_tutar_diff',
    ];
    public static array $allColumnDefs = [
        'date' => 'Tarih',
        'name' => 'Rapor Adı',
        'company' => 'Kargo',
        'orders' => 'Sipariş',
        'errors' => 'Hata',
        'desi_diff' => 'Desi Farkı',
        'amount_diff' => 'Tutar Farkı',
        'actions' => 'İşlem',
    ];

    // Filtreler
    public ?string $filterDate = null;
    public ?string $filterDateEnd = null;
    public string $filterCompany = '';
    public string $filterMarketplace = '';
    public string $filterStore = '';
    public string $filterRecordType = 'all';
    public string $sortField = 'report_date';
    public string $sortDirection = 'desc';

    // Detay modalı
    public bool $showDetailModal = false;
    public ?int $viewingReportId = null;
    public string $itemFilterErrorType = 'all';
    public string $itemFilterType = 'all'; // all, siparis, iade, parca
    public string $itemFilterClaim = 'all'; // all, claimable, with_compensation, without_compensation
    public string $itemSearch = '';

    // Silme modalı
    public bool $showDeleteModal = false;
    public ?int $deletingReportId = null;

    // Mesaj
    public string $message = '';
    public string $messageType = 'info';

    public function mount()
    {
        $this->filterDate = now()->subDays(30)->format('Y-m-d');
        $this->filterDateEnd = now()->format('Y-m-d');
        $this->visibleColumns = $this->normalizeVisibleColumns(
            app(MpSettingsService::class)->getArray('cargo_reports.report_list.visible_columns', $this->visibleColumns)
        );
    }

    public function updatedFilterDate()
    {
        $this->resetPage();
    }

    public function updatedFilterDateEnd()
    {
        $this->resetPage();
    }

    public function updatedFilterCompany()
    {
        $this->resetPage();
    }

    public function updatedFilterMarketplace()
    {
        $this->resetPage();
    }

    public function updatedFilterStore()
    {
        $this->resetPage();
    }

    public function updatedFilterRecordType()
    {
        $this->resetPage();
    }

    /**
     * Raporları getir
     */
    #[Computed]
    public function reports()
    {
        $query = CargoReport::with('user')
            ->withCount(['items', 'errorItems']);

        // Tarih filtresi
        if ($this->filterDate) {
            $query->where('report_date', '>=', $this->filterDate);
        }
        if ($this->filterDateEnd) {
            $query->where('report_date', '<=', $this->filterDateEnd);
        }

        // Kargo firması filtresi
        if (!empty($this->filterCompany)) {
            $query->where('cargo_company', $this->filterCompany);
        }

        $this->applyItemScopeFilters($query);

        // Sıralama
        $query->orderBy($this->sortField, $this->sortDirection);

        return $query->paginate(15);
    }

    /**
     * Kargo firmaları (filtreleme için)
     */
    #[Computed]
    public function cargoCompanies()
    {
        return CargoReport::distinct()
            ->whereNotNull('cargo_company')
            ->pluck('cargo_company')
            ->sort()
            ->values();
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

    /**
     * Toplam istatistikler
     */
    #[Computed]
    public function totalStats()
    {
        $query = CargoReport::query();

        if ($this->filterDate) {
            $query->where('report_date', '>=', $this->filterDate);
        }
        if ($this->filterDateEnd) {
            $query->where('report_date', '<=', $this->filterDateEnd);
        }
        if (!empty($this->filterCompany)) {
            $query->where('cargo_company', $this->filterCompany);
        }

        $this->applyItemScopeFilters($query);

        return [
            'total_reports' => $query->count(),
            'total_orders' => $query->sum('total_orders'),
            'total_errors' => $query->sum('error_count'),
            'total_desi_diff' => $query->sum('total_desi_diff'),
            'total_tutar_diff' => $query->sum('total_tutar_diff'),
        ];
    }

    /**
     * Sıralama değiştir
     */
    public function sortTable(string $columnKey)
    {
        $field = static::$sortableColumns[$columnKey] ?? null;
        if (!$field) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'desc';
        }

        $this->resetPage();
    }

    public function toggleColumn(string $column): void
    {
        if (!array_key_exists($column, static::$allColumnDefs)) {
            return;
        }

        if (in_array($column, $this->visibleColumns, true)) {
            if (count($this->visibleColumns) === 1) {
                return;
            }

            $this->visibleColumns = array_values(array_diff($this->visibleColumns, [$column]));
        } else {
            $this->visibleColumns[] = $column;
            $this->visibleColumns = $this->normalizeVisibleColumns($this->visibleColumns);
        }

        app(MpSettingsService::class)->set('cargo_reports.report_list.visible_columns', $this->visibleColumns);
    }

    /**
     * Rapor detayını görüntüle
     */
    public function viewReport(int $id)
    {
        $this->viewingReportId = $id;
        $this->resetPage(pageName: 'detailPage');
        $this->showDetailModal = true;
    }

    #[Computed]
    public function viewingItems()
    {
        if (!$this->viewingReportId) {
            return CargoReportItem::query()->whereRaw('1 = 0')->paginate(25, pageName: 'detailPage');
        }

        return $this->buildViewingItemsQuery()
            ->orderBy('has_error', 'desc')
            ->orderByRaw('ABS(tutar_fark) desc')
            ->orderByRaw('ABS(desi_fark) desc')
            ->paginate(25, pageName: 'detailPage');
    }

    #[Computed]
    public function viewingReport(): ?CargoReport
    {
        if (!$this->viewingReportId) {
            return null;
        }

        return CargoReport::find($this->viewingReportId);
    }

    #[Computed]
    public function detailSummary(): array
    {
        if (!$this->viewingReportId) {
            return [
                'total' => 0,
                'errors' => 0,
                'claimable' => 0,
                'with_compensation' => 0,
            ];
        }

        $baseQuery = $this->buildViewingItemsQuery();

        return [
            'total' => (clone $baseQuery)->count(),
            'errors' => (clone $baseQuery)->where('has_error', true)->count(),
            'claimable' => (clone $baseQuery)->whereIn('error_type', $this->claimableErrorTypes())->count(),
            'with_compensation' => (clone $baseQuery)->has('compensation')->count(),
        ];
    }

    protected function buildViewingItemsQuery(): Builder
    {
        $query = CargoReportItem::query()
            ->withExists('compensation')
            ->where('cargo_report_id', $this->viewingReportId);

        if ($this->itemFilterErrorType !== 'all') {
            if ($this->itemFilterErrorType === 'errors') {
                $query->where('has_error', true);
            } else {
                $query->where('error_type', $this->itemFilterErrorType);
            }
        }

        if ($this->itemFilterType === 'siparis') {
            $query->where('is_iade', false)->where('is_parca_gonderi', false);
        } elseif ($this->itemFilterType === 'iade') {
            $query->where('is_iade', true);
        } elseif ($this->itemFilterType === 'parca') {
            $query->where('is_parca_gonderi', true);
        }

        if ($this->itemFilterClaim === 'claimable') {
            $query->whereIn('error_type', $this->claimableErrorTypes());
        } elseif ($this->itemFilterClaim === 'with_compensation') {
            $query->has('compensation');
        } elseif ($this->itemFilterClaim === 'without_compensation') {
            $query->doesntHave('compensation');
        }

        if ($this->itemSearch !== '') {
            $search = trim($this->itemSearch);
            $query->where(function (Builder $subQuery) use ($search) {
                $subQuery
                    ->where('musteri_adi', 'like', '%' . $search . '%')
                    ->orWhere('takip_kodu', 'like', '%' . $search . '%')
                    ->orWhere('stok_kodu', 'like', '%' . $search . '%')
                    ->orWhere('urun_adi', 'like', '%' . $search . '%');
            });
        }

        return $query;
    }

    protected function claimableErrorTypes(): array
    {
        return ['desi_fazla', 'tutar_fazla', 'parca_eksik', 'parca_fazla', 'eslesmedi'];
    }

    /**
     * Item filtresi değiştiğinde
     */
    public function updatedItemFilterErrorType()
    {
        $this->resetPage(pageName: 'detailPage');
    }

    /**
     * Tip filtresi değiştiğinde
     */
    public function updatedItemFilterType()
    {
        $this->resetPage(pageName: 'detailPage');
    }

    public function updatedItemFilterClaim()
    {
        $this->resetPage(pageName: 'detailPage');
    }

    public function updatedItemSearch()
    {
        $this->resetPage(pageName: 'detailPage');
    }

    /**
     * Detay modalını kapat
     */
    public function closeDetailModal()
    {
        $this->showDetailModal = false;
        $this->viewingReportId = null;
        $this->itemFilterErrorType = 'all';
        $this->itemFilterType = 'all';
        $this->itemFilterClaim = 'all';
        $this->itemSearch = '';
        $this->resetPage(pageName: 'detailPage');
    }

    /**
     * Raporu indir (Ayrı sayfalarla)
     */
    public function downloadReport(int $id)
    {
        try {
            $report = CargoReport::with('items')->find($id);
            if (!$report) {
                $this->showMessage('Rapor bulunamadı.', 'error');
                return;
            }

            $fileName = 'rapor_' . $report->id . '_' . $report->report_date->format('Y-m-d') . '.xlsx';
            $tempPath = storage_path('app/temp/' . $fileName);

            $sheets = [
                [
                    'name' => 'Özet',
                    'data' => $this->buildSummaryRows($report),
                ],
                [
                    'name' => 'Siparişler',
                    'data' => $this->buildItemRows($report->items->where('is_iade', false)->where('is_parca_gonderi', false)),
                ],
                [
                    'name' => 'İadeler',
                    'data' => $this->buildItemRows($report->items->where('is_iade', true)),
                ],
                [
                    'name' => 'Parça Gönderileri',
                    'data' => $this->buildItemRows($report->items->where('is_parca_gonderi', true)),
                ],
            ];

            app(ExcelService::class)->exportToXlsx($sheets, $tempPath);

            return response()->download($tempPath, $fileName)->deleteFileAfterSend();

        } catch (\Exception $e) {
            Log::error('ReportList: Download hatası', ['error' => $e->getMessage()]);
            $this->showMessage('İndirme hatası: ' . $e->getMessage(), 'error');
            return null;
        }
    }

    public function downloadFilteredReportItems()
    {
        if (!$this->viewingReportId) {
            return null;
        }

        try {
            $report = CargoReport::find($this->viewingReportId);

            if (!$report) {
                $this->showMessage('Rapor bulunamadı.', 'error');
                return null;
            }

            $fileName = 'rapor_filtreli_' . $report->id . '_' . now()->format('Y-m-d_His') . '.xlsx';
            $tempPath = storage_path('app/temp/' . $fileName);

            $items = $this->buildViewingItemsQuery()
                ->orderBy('has_error', 'desc')
                ->orderByRaw('ABS(tutar_fark) desc')
                ->orderByRaw('ABS(desi_fark) desc')
                ->get();

            $sheets = [
                [
                    'name' => 'Filtreli Detay',
                    'data' => $this->buildFilteredItemRows($items),
                ],
            ];

            app(ExcelService::class)->exportToXlsx($sheets, $tempPath);

            return response()->download($tempPath, $fileName)->deleteFileAfterSend();
        } catch (\Exception $e) {
            Log::error('ReportList: Filtreli export hatası', ['error' => $e->getMessage()]);
            $this->showMessage('Filtreli export hatası: ' . $e->getMessage(), 'error');
            return null;
        }
    }

    /**
     * Excel sayfasına item'ları chunk halinde yaz
     */
    protected function buildSummaryRows(CargoReport $report): array
    {
        $normalSiparisCount = $report->items->where('is_iade', false)->where('is_parca_gonderi', false)->count();
        $iadeCount = $report->items->where('is_iade', true)->count();
        $iadeTutar = $report->items->where('is_iade', true)->sum('gercek_tutar');
        $parcaCount = $report->items->where('is_parca_gonderi', true)->count();
        $parcaTutar = $report->items->where('is_parca_gonderi', true)->sum('gercek_tutar');
        $referenceIssueCount = $report->items->where('error_type', 'referans_eksik')->count();

        return [
            ['Alan' => 'Rapor', 'Değer' => $report->name],
            ['Alan' => 'Tarih', 'Değer' => $report->report_date->format('d.m.Y')],
            ['Alan' => 'Kargo', 'Değer' => $report->cargo_company],
            ['Alan' => 'Toplam Sipariş', 'Değer' => $report->total_orders],
            ['Alan' => 'Eşleşen', 'Değer' => $report->matched_orders],
            ['Alan' => 'Hatalı', 'Değer' => $report->error_count],
            ['Alan' => 'Referans Uyarısı', 'Değer' => $referenceIssueCount],
            ['Alan' => 'Desi Farkı', 'Değer' => number_format($report->total_desi_diff, 2, '.', '')],
            ['Alan' => 'Tutar Farkı', 'Değer' => number_format($report->total_tutar_diff, 2, '.', '')],
            ['Alan' => 'Normal Sipariş', 'Değer' => $normalSiparisCount],
            ['Alan' => 'İade/Değişim', 'Değer' => $iadeCount . ' (' . number_format($iadeTutar, 2, ',', '.') . ' TL)'],
            ['Alan' => 'Parça Gönderisi', 'Değer' => $parcaCount . ' (' . number_format($parcaTutar, 2, ',', '.') . ' TL)'],
        ];
    }

    protected function buildItemRows($items): array
    {
        return collect($items)
            ->values()
            ->map(function ($item) {
                return [
                    'Tarih' => $item->tarih?->format('d.m.Y') ?? '',
                    'Müşteri' => $item->musteri_adi,
                    'Stok Kodu' => $item->stok_kodu,
                    'Takip Kodu' => $item->takip_kodu,
                    'Ürün' => $item->urun_adi,
                    'Adet' => (int) $item->adet,
                    'Bek.Desi' => (float) $item->beklenen_desi,
                    'Ger.Desi' => (float) $item->gercek_desi,
                    'Desi Fark' => (float) $item->desi_fark,
                    'Bek.Tutar' => (float) $item->beklenen_tutar,
                    'Ger.Tutar' => (float) $item->gercek_tutar,
                    'Tutar Fark' => (float) $item->tutar_fark,
                    'Pazaryeri' => $item->pazaryeri,
                    'Mağaza' => $item->magaza,
                    'Durum' => $item->has_error ? 'HATA' : 'OK',
                ];
            })
            ->all();
    }

    protected function buildFilteredItemRows($items): array
    {
        return collect($items)
            ->values()
            ->map(function ($item) {
                return [
                    'Tarih' => $item->tarih?->format('d.m.Y') ?? '',
                    'Müşteri' => $item->musteri_adi,
                    'Stok Kodu' => $item->stok_kodu,
                    'Takip Kodu' => $item->takip_kodu,
                    'Ürün' => $item->urun_adi,
                    'Adet' => (int) $item->adet,
                    'Bek.Desi' => (float) $item->beklenen_desi,
                    'Ger.Desi' => (float) $item->gercek_desi,
                    'Desi Fark' => (float) $item->desi_fark,
                    'Bek.Tutar' => (float) $item->beklenen_tutar,
                    'Ger.Tutar' => (float) $item->gercek_tutar,
                    'Tutar Fark' => (float) $item->tutar_fark,
                    'Pazaryeri' => $item->pazaryeri,
                    'Mağaza' => $item->magaza,
                    'Talep Var' => ($item->compensation_exists ?? false) ? 'Evet' : 'Hayır',
                    'Durum' => $item->has_error ? 'HATA' : 'OK',
                ];
            })
            ->all();
    }

    /**
     * Silme onayı göster
     */
    public function confirmDelete(int $id)
    {
        $this->deletingReportId = $id;
        $this->showDeleteModal = true;
    }

    /**
     * Raporu sil
     */
    public function deleteReport()
    {
        if (!$this->deletingReportId) return;

        try {
            $report = CargoReport::find($this->deletingReportId);
            if ($report) {
                $report->delete(); // items cascade ile silinir
                $this->showMessage('Rapor silindi.', 'success');
            }
        } catch (\Exception $e) {
            $this->showMessage('Silme hatası: ' . $e->getMessage(), 'error');
        }

        $this->showDeleteModal = false;
        $this->deletingReportId = null;
    }

    /**
     * Mesaj göster
     */
    protected function showMessage(string $message, string $type = 'info')
    {
        $this->message = $message;
        $this->messageType = $type;
    }

    public function render()
    {
        return view('livewire.cargo.report-list');
    }

    protected function normalizeVisibleColumns(array $columns): array
    {
        $allowed = array_keys(static::$allColumnDefs);
        $normalized = array_values(array_intersect($allowed, $columns));

        return $normalized !== [] ? $normalized : ['date', 'name', 'company', 'orders', 'errors', 'desi_diff', 'amount_diff', 'actions'];
    }

    protected function applyItemScopeFilters(Builder $query): void
    {
        if ($this->filterMarketplace !== '') {
            $query->whereHas('items', fn (Builder $itemQuery) => $itemQuery->where('pazaryeri', $this->filterMarketplace));
        }

        if ($this->filterStore !== '') {
            $query->whereHas('items', fn (Builder $itemQuery) => $itemQuery->where('magaza', $this->filterStore));
        }

        if ($this->filterRecordType === 'siparis') {
            $query->whereHas('items', fn (Builder $itemQuery) => $itemQuery->where('is_iade', false)->where('is_parca_gonderi', false));
        } elseif ($this->filterRecordType === 'iade') {
            $query->whereHas('items', fn (Builder $itemQuery) => $itemQuery->where('is_iade', true));
        } elseif ($this->filterRecordType === 'parca') {
            $query->whereHas('items', fn (Builder $itemQuery) => $itemQuery->where('is_parca_gonderi', true));
        }
    }
}
