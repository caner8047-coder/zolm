<?php

namespace App\Livewire\Cargo;

use App\Models\CargoReport;
use App\Models\CargoReportItem;
use App\Models\CargoReportLine;
use App\Models\MpOperationalOrder;
use App\Models\MpOperationalOrderItem;
use App\Models\MpProduct;
use App\Models\Product;
use App\Models\ProductReferenceHistory;
use App\Services\CargoComparisonEngine;
use App\Services\ExcelService;
use App\Services\MpSettingsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Kargo Karşılaştırma Bileşeni
 * 
 * Tab 2: Kargo Desi ve Tutar Check
 * - 2 dosya yükleme (Kargo Raporu + Sipariş Detayları)
 * - Karşılaştırma çalıştırma
 * - Sonuç tablosu ve istatistikler
 * - Rapor kaydetme ve export
 */
class CargoChecker extends Component
{
    use WithFileUploads;
    use WithPagination;

    public array $visibleColumns = [
        'date',
        'customer',
        'tracking',
        'product',
        'quantity',
        'actual_desi',
        'actual_amount',
        'status',
        'actions',
    ];
    public static array $sortableColumns = [
        'date' => 'tarih',
        'customer' => 'musteri_adi',
        'tracking' => 'takip_kodu',
        'product' => 'urun_adi',
        'quantity' => 'adet',
        'pieces' => 'gercek_parca',
        'expected_desi' => 'beklenen_desi',
        'actual_desi' => 'gercek_desi',
        'expected_amount' => 'beklenen_tutar',
        'actual_amount' => 'gercek_tutar',
        'status' => 'error_type',
    ];
    public static array $allColumnDefs = [
        'date' => 'Tarih',
        'customer' => 'Müşteri',
        'tracking' => 'Takip',
        'product' => 'Ürün',
        'quantity' => 'Adet',
        'pieces' => 'Parça',
        'expected_desi' => 'Beklenen Desi',
        'actual_desi' => 'Gerçek Desi',
        'expected_amount' => 'Beklenen Tutar',
        'actual_amount' => 'Gerçek Tutar',
        'status' => 'Durum',
        'actions' => 'İşlem',
    ];

    // Dosya yükleme
    public $cargoFile;
    public $orderFile;
    public bool $useLegacyExcelReference = false;
    public ?string $sourceReportDate = null;
    public string $selectedSuratReportDate = '';

    // Kargo firması seçimi
    public string $cargoCompany = 'Sürat Kargo';
    public string $reportName = '';

    // İşlem durumu
    public bool $isProcessing = false;
    public bool $hasResults = false;

    // Sonuçlar
    public ?int $currentReportId = null;
    public array $stats = [
        'total_orders' => 0,
        'matched_orders' => 0,
        'unmatched_orders' => 0,
        'error_count' => 0,
        'iade_count' => 0,
        'iade_tutar' => 0,
        'parca_count' => 0,
        'parca_tutar' => 0,
        'total_desi_diff' => 0,
        'total_tutar_diff' => 0,
        'reference_issue_count' => 0,
    ];

    // Filtreler
    public string $filterErrorType = 'all';
    public string $filterMatched = 'all';
    public string $filterType = 'siparis';  // siparis, iade, parca, all
    public string $searchCustomer = '';
    public string $sortField = '';
    public string $sortDirection = 'desc';

    // Mesajlar
    public string $message = '';
    public string $messageType = 'info';

    // Kaydetme modalı
    public bool $showSaveModal = false;

    // Ürün Düzenleme Modalı
    public bool $showEditModal = false;
    public string $editStokKodu = '';
    public string $editUrunAdi = '';
    public float $editDesi = 0;
    public int $editParca = 1;
    public float $editTutar = 0;

    public function mount(?string $sourceReportDate = null): void
    {
        $settings = app(MpSettingsService::class);
        $savedColumns = $settings->getArray('cargo_reports.cargo_checker.visible_columns', []);
        $compactDefaultsApplied = $settings->getBool('cargo_reports.cargo_checker.compact_defaults_applied', false);
        $allColumns = array_keys(static::$allColumnDefs);

        if ($savedColumns === []) {
            $savedColumns = $this->visibleColumns;
            $settings->setMany([
                'cargo_reports.cargo_checker.visible_columns' => $savedColumns,
                'cargo_reports.cargo_checker.compact_defaults_applied' => true,
            ]);
        } elseif (
            !$compactDefaultsApplied
            && count($savedColumns) === count($allColumns)
            && count(array_intersect($savedColumns, $allColumns)) === count($allColumns)
        ) {
            $savedColumns = $this->visibleColumns;
            $settings->setMany([
                'cargo_reports.cargo_checker.visible_columns' => $savedColumns,
                'cargo_reports.cargo_checker.compact_defaults_applied' => true,
            ]);
        }

        $this->visibleColumns = $this->normalizeVisibleColumns($savedColumns);
        $this->sourceReportDate = $sourceReportDate ? Carbon::parse($sourceReportDate)->toDateString() : null;
        $this->selectedSuratReportDate = $this->sourceReportDate ?: ($this->latestSuratReportDate() ?? '');

        if ($this->sourceReportDate) {
            $this->runFromSavedSuratReport($this->sourceReportDate);
        }
    }

    public function updatedFilterErrorType(): void
    {
        $this->resetPage();
    }

    public function updatedFilterMatched(): void
    {
        $this->resetPage();
    }

    public function updatedFilterType(): void
    {
        if (in_array($this->filterType, ['iade', 'parca'], true) && $this->filterMatched !== 'all') {
            $this->filterMatched = 'all';
        }

        $this->resetPage();
    }

    public function updatedSearchCustomer(): void
    {
        $this->resetPage();
    }

    /**
     * Kargo firması seçenekleri
     */
    #[Computed]
    public function cargoCompanies()
    {
        return collect(config('cargo.companies'))->pluck('name', 'name');
    }

    #[Computed]
    public function suratReportOptions()
    {
        if (!Schema::hasTable('cargo_report_lines')) {
            return collect();
        }

        return CargoReportLine::query()
            ->where('user_id', auth()->id())
            ->where('carrier_code', 'surat')
            ->selectRaw('report_date, COUNT(*) as row_count, SUM(pieces) as pieces, SUM(desi) as desi, SUM(total_amount) as total_amount')
            ->groupBy('report_date')
            ->orderByDesc('report_date')
            ->limit(30)
            ->get();
    }

    /**
     * Mevcut rapor
     */
    #[Computed]
    public function report()
    {
        if (!$this->currentReportId) return null;
        return CargoReport::with('items')->find($this->currentReportId);
    }

    /**
     * Filtrelenmiş sonuçlar
     */
    #[Computed]
    public function filteredItems()
    {
        if (!$this->currentReportId) return collect();

        $query = CargoReportItem::where('cargo_report_id', $this->currentReportId);

        // Hata tipi filtresi
        if ($this->filterErrorType !== 'all') {
            if ($this->filterErrorType === 'errors') {
                $query->where('has_error', true);
            } else {
                $query->where('error_type', $this->filterErrorType);
            }
        }

        // Eşleşme filtresi
        if ($this->filterMatched !== 'all' && !in_array($this->filterType, ['iade', 'parca'], true)) {
            $query->where('is_matched', $this->filterMatched === 'matched');
        }

        // Müşteri arama
        if (!empty($this->searchCustomer)) {
            $query->where('musteri_adi', 'like', "%{$this->searchCustomer}%");
        }

        // Tip filtresi: siparis, iade, parca, all
        if ($this->filterType === 'siparis') {
            $query->where('is_iade', false)->where('is_parca_gonderi', false);
        } elseif ($this->filterType === 'iade') {
            $query->where('is_iade', true);
        } elseif ($this->filterType === 'parca') {
            $query->where('is_parca_gonderi', true);
        }
        // 'all' = tüm kayıtları göster

        if ($this->sortField !== '') {
            if ($this->sortField === 'error_type') {
                $direction = $this->sortDirection === 'asc' ? 'asc' : 'desc';

                $query->orderByRaw("
                    CASE error_type
                        WHEN 'none' THEN 0
                        WHEN 'referans_eksik' THEN 1
                        WHEN 'desi_eksik' THEN 2
                        WHEN 'desi_fazla' THEN 3
                        WHEN 'tutar_eksik' THEN 4
                        WHEN 'tutar_fazla' THEN 5
                        WHEN 'parca_eksik' THEN 6
                        WHEN 'parca_fazla' THEN 7
                        WHEN 'eslesmedi' THEN 8
                        ELSE 99
                    END {$direction}
                ")->orderBy('tutar_fark', 'desc');
            } else {
                $query->orderBy($this->sortField, $this->sortDirection)
                    ->orderBy('has_error', 'desc')
                    ->orderBy('tutar_fark', 'desc');
            }
        } else {
            $query->orderBy('is_iade', 'asc')
                ->orderBy('is_parca_gonderi', 'asc')
                ->orderBy('has_error', 'desc')
                ->orderBy('tutar_fark', 'desc');
        }

        return $query->paginate(50);
    }

    public function sortTable(string $columnKey): void
    {
        $field = static::$sortableColumns[$columnKey] ?? null;
        if (!$field) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = in_array($columnKey, ['customer', 'tracking', 'product'], true) ? 'asc' : 'desc';
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

        app(MpSettingsService::class)->set('cargo_reports.cargo_checker.visible_columns', $this->visibleColumns);
    }

    /**
     * Hata tipleri (filtreleme için)
     */
    #[Computed]
    public function errorTypes()
    {
        return [
            'all' => 'Tümü',
            'errors' => 'Sadece Hatalar',
            'none' => 'Hata Yok',
            'referans_eksik' => 'Referans Eksik',
            'desi_fazla' => 'Desi Fazla',
            'desi_eksik' => 'Desi Eksik',
            'tutar_fazla' => 'Tutar Fazla',
            'tutar_eksik' => 'Tutar Eksik',
            'parca_eksik' => 'Parça Eksik',
            'eslesmedi' => 'Eşleşmedi',
        ];
    }

    /**
     * Legacy ürün sayısı kontrolü
     */
    #[Computed]
    public function productCount()
    {
        return Product::active()->count();
    }

    /**
     * Pazaryeri veri kaynağı hazır mı?
     */
    #[Computed]
    public function marketplaceStats(): array
    {
        $userId = auth()->id() ?? 1;

        $productQuery = MpProduct::query()->where('user_id', $userId);
        $orderQuery = MpOperationalOrder::query();
        $itemQuery = MpOperationalOrderItem::query();

        return [
            'products_total' => (clone $productQuery)->count(),
            'products_ready' => (clone $productQuery)
                ->whereNotNull('stock_code')
                ->where('stock_code', '!=', '')
                ->where('pieces', '>', 0)
                ->where('desi', '>', 0)
                ->where('cargo_cost', '>', 0)
                ->count(),
            'orders_total' => (clone $orderQuery)->count(),
            'orders_with_tracking' => (clone $orderQuery)->whereNotNull('tracking_number')->where('tracking_number', '!=', '')->count(),
            'order_items_total' => (clone $itemQuery)->count(),
        ];
    }

    /**
     * Karşılaştırma çalıştır
     */
    public function runComparison()
    {
        $rules = [
            'cargoFile' => 'required|file|mimes:xlsx,xls|max:20480',
        ];

        $messages = [
            'cargoFile.required' => 'Kargo raporu dosyası gerekli.',
        ];

        if ($this->useLegacyExcelReference) {
            $rules['orderFile'] = 'required|file|mimes:xlsx,xls|max:20480';
            $messages['orderFile.required'] = 'Sipariş detayları dosyası gerekli.';
        }

        $this->validate($rules, $messages);

        if ($this->useLegacyExcelReference) {
            if ($this->productCount < 1) {
                $this->showMessage('Legacy Excel modunda önce ürün listesini yüklemelisiniz. "Ürün ve Desi Bilgileri" sekmesinden ürün ekleyin.', 'warning');
                return;
            }
        } elseif (!$this->marketplaceReferencesReady()) {
            return;
        }

        $this->isProcessing = true;
        $this->message = '';

        try {
            $engine = app(CargoComparisonEngine::class);

            $result = $engine->compare(
                $this->cargoFile,
                $this->useLegacyExcelReference ? $this->orderFile : null,
                $this->reportName ?: 'Kargo Raporu - ' . now()->format('d.m.Y H:i'),
                $this->cargoCompany
            );

            if ($result['success']) {
                $this->currentReportId = $result['report']->id;
                $this->hasResults = true;
                $this->updateStats($result['report']);
                $this->showMessage($result['message'], 'success');
            } else {
                $this->showMessage($result['message'], 'error');
            }

        } catch (\Exception $e) {
            Log::error('CargoChecker: Karşılaştırma hatası', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->showMessage('Karşılaştırma hatası: ' . $e->getMessage(), 'error');
        }

        $this->isProcessing = false;
    }

    public function runFromSavedSuratReport(?string $date = null): void
    {
        $date = $date ?: $this->selectedSuratReportDate;

        if (!$date) {
            $this->showMessage('Kontrol için önce kayıtlı Sürat raporu tarihi seçin.', 'warning');
            return;
        }

        $date = Carbon::parse($date)->toDateString();
        $this->sourceReportDate = $date;
        $this->selectedSuratReportDate = $date;

        if (!$this->marketplaceReferencesReady()) {
            return;
        }

        if (!Schema::hasTable('cargo_report_lines')) {
            $this->showMessage('Kayıtlı Sürat rapor tabloları henüz hazır değil.', 'warning');
            return;
        }

        $lines = CargoReportLine::query()
            ->where('user_id', auth()->id())
            ->where('carrier_code', 'surat')
            ->whereDate('report_date', $date)
            ->orderBy('id')
            ->get();

        if ($lines->isEmpty()) {
            $this->showMessage(Carbon::parse($date)->format('d.m.Y') . ' için kayıtlı Sürat raporu bulunamadı.', 'warning');
            return;
        }

        $this->isProcessing = true;
        $this->message = '';

        try {
            $labelDate = Carbon::parse($date)->format('d.m.Y');
            $result = app(CargoComparisonEngine::class)->compareFromCargoRows(
                $this->cargoRowsFromReportLines($lines),
                $this->reportName ?: 'Sürat Günlük Check - ' . $labelDate,
                'Sürat Kargo',
                'Kayıtlı Sürat raporu - ' . $labelDate
            );

            if ($result['success']) {
                $result['report']->forceFill(['report_date' => $date])->save();
                $this->currentReportId = $result['report']->id;
                $this->hasResults = true;
                $this->updateStats($result['report']);
                $this->showMessage($labelDate . ' Sürat raporu check modülünde kontrol edildi. ' . $result['message'], 'success');
            } else {
                $this->showMessage($result['message'], 'error');
            }
        } catch (\Exception $e) {
            Log::error('CargoChecker: Kayıtlı Sürat raporu karşılaştırma hatası', [
                'date' => $date,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->showMessage('Kayıtlı Sürat raporu kontrol hatası: ' . $e->getMessage(), 'error');
        } finally {
            $this->isProcessing = false;
        }
    }

    /**
     * İstatistikleri güncelle
     */
    protected function updateStats(CargoReport $report)
    {
        // İade sayısı ve tutarını hesapla (DB Aggregate)
        $iadeCount = CargoReportItem::where('cargo_report_id', $report->id)
            ->where('is_iade', true)
            ->count();
        
        $iadeTutar = CargoReportItem::where('cargo_report_id', $report->id)
            ->where('is_iade', true)
            ->sum('gercek_tutar');

        // Parça gönderisi sayısı ve tutarını hesapla (DB Aggregate)
        $parcaCount = CargoReportItem::where('cargo_report_id', $report->id)
            ->where('is_parca_gonderi', true)
            ->count();
            
        $parcaTutar = CargoReportItem::where('cargo_report_id', $report->id)
            ->where('is_parca_gonderi', true)
            ->sum('gercek_tutar');

        $referenceIssueCount = CargoReportItem::where('cargo_report_id', $report->id)
            ->where('error_type', 'referans_eksik')
            ->count();

        $this->stats = [
            'total_orders' => $report->total_orders,
            'matched_orders' => $report->matched_orders,
            'unmatched_orders' => $report->unmatched_orders,
            'error_count' => $report->error_count,
            'iade_count' => $iadeCount,
            'iade_tutar' => $iadeTutar,
            'parca_count' => $parcaCount,
            'parca_tutar' => $parcaTutar,
            'reference_issue_count' => $referenceIssueCount,
            'total_desi_diff' => $report->total_desi_diff,
            'total_tutar_diff' => $report->total_tutar_diff,
            'total_expected_desi' => $report->total_expected_desi,
            'total_actual_desi' => $report->total_actual_desi,
            'total_expected_tutar' => $report->total_expected_tutar,
            'total_actual_tutar' => $report->total_actual_tutar,
        ];
    }

    /**
     * Raporu Excel'e export et
     */
    public function exportReport()
    {
        if (!$this->currentReportId) {
            $this->showMessage('Export edilecek rapor yok.', 'error');
            return;
        }

        try {
            $report = CargoReport::with('items')->find($this->currentReportId);
            if (!$report) return;

            $spreadsheet = new Spreadsheet();

            // Özet sayfası
            $this->createSummarySheet($spreadsheet, $report);

            // Detay sayfası
            $this->createDetailSheet($spreadsheet, $report);

            // Sadece hatalar sayfası
            $this->createErrorsSheet($spreadsheet, $report);

            // Dosyayı kaydet
            $fileName = 'kargo_kontrol_' . $report->report_date->format('Y-m-d') . '_' . now()->format('H-i') . '.xlsx';
            $tempPath = storage_path('app/temp/' . $fileName);

            if (!is_dir(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save($tempPath);

            return response()->download($tempPath, $fileName)->deleteFileAfterSend();

        } catch (\Exception $e) {
            Log::error('CargoChecker: Export hatası', ['error' => $e->getMessage()]);
            $this->showMessage('Export hatası: ' . $e->getMessage(), 'error');
            return null;
        }
    }

    /**
     * Özet sayfası oluştur
     */
    protected function createSummarySheet(Spreadsheet $spreadsheet, CargoReport $report)
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->sanitizeSheetName('Özet'));

        $this->writeCell($sheet, 'A1', 'KARGO KONTROL RAPORU');
        $sheet->mergeCells('A1:D1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);

        $this->writeCell($sheet, 'A3', 'Rapor Adı:');
        $this->writeCell($sheet, 'B3', $report->name);
        $this->writeCell($sheet, 'A4', 'Kargo Firması:');
        $this->writeCell($sheet, 'B4', $report->cargo_company);
        $this->writeCell($sheet, 'A5', 'Tarih:');
        $this->writeCell($sheet, 'B5', $report->report_date->format('d.m.Y'));

        $this->writeCell($sheet, 'A7', 'İSTATİSTİKLER');
        $sheet->getStyle('A7')->getFont()->setBold(true);

        $stats = [
            ['Toplam Sipariş', $report->total_orders],
            ['Eşleşen', $report->matched_orders],
            ['Eşleşmeyen', $report->unmatched_orders],
            ['Hatalı', $report->error_count],
            ['Referans Uyarısı', $report->items()->where('error_type', 'referans_eksik')->count()],
            ['Hata Oranı', number_format($report->error_percentage, 1) . '%'],
            ['', ''],
            ['DESİ KARŞILAŞTIRMASI', ''],
            ['Beklenen Toplam Desi', number_format($report->total_expected_desi, 2)],
            ['Gerçek Toplam Desi', number_format($report->total_actual_desi, 2)],
            ['Desi Farkı', number_format($report->total_desi_diff, 2)],
            ['', ''],
            ['TUTAR KARŞILAŞTIRMASI', ''],
            ['Beklenen Toplam Tutar', number_format($report->total_expected_tutar, 2) . ' TL'],
            ['Gerçek Toplam Tutar', number_format($report->total_actual_tutar, 2) . ' TL'],
            ['Tutar Farkı', number_format($report->total_tutar_diff, 2) . ' TL'],
        ];

        $row = 8;
        foreach ($stats as $stat) {
            $this->writeCell($sheet, 'A' . $row, $stat[0]);
            $this->writeCell($sheet, 'B' . $row, $stat[1]);
            if (str_contains($stat[0] ?? '', 'KARŞILAŞTIRMASI')) {
                $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            }
            $row++;
        }

        // Kolon genişlikleri
        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(20);
    }

    /**
     * Detay sayfası oluştur
     */
    protected function createDetailSheet(Spreadsheet $spreadsheet, CargoReport $report)
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($this->sanitizeSheetName('Tüm Kayıtlar'));

        $headers = [
            'Tarih', 'Müşteri', 'Takip Kodu', 'Ürün', 'Adet',
            'Bek. Parça', 'Ger. Parça', 'P. Fark',
            'Bek. Desi', 'Ger. Desi', 'D. Fark',
            'Bek. Tutar', 'Ger. Tutar', 'T. Fark',
            'Hata Tipi', 'Durum'
        ];

        $col = 'A';
        foreach ($headers as $header) {
            $this->writeCell($sheet, $col . '1', $header);
            $col++;
        }

        $sheet->getStyle('A1:P1')->getFont()->setBold(true);
        $sheet->getStyle('A1:P1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('1F2937');
        $sheet->getStyle('A1:P1')->getFont()->getColor()->setRGB('FFFFFF');

        $row = 2;
        foreach ($report->items as $item) {
            $this->writeCell($sheet, 'A' . $row, $item->tarih?->format('d.m.Y'));
            $this->writeCell($sheet, 'B' . $row, $item->musteri_adi);
            $this->writeCell($sheet, 'C' . $row, $item->takip_kodu);
            $this->writeCell($sheet, 'D' . $row, $item->urun_adi);
            $this->writeCell($sheet, 'E' . $row, $item->adet, DataType::TYPE_NUMERIC);
            $this->writeCell($sheet, 'F' . $row, $item->beklenen_parca, DataType::TYPE_NUMERIC);
            $this->writeCell($sheet, 'G' . $row, $item->gercek_parca, DataType::TYPE_NUMERIC);
            $this->writeCell($sheet, 'H' . $row, $item->parca_fark, DataType::TYPE_NUMERIC);
            $this->writeCell($sheet, 'I' . $row, $item->beklenen_desi, DataType::TYPE_NUMERIC);
            $this->writeCell($sheet, 'J' . $row, $item->gercek_desi, DataType::TYPE_NUMERIC);
            $this->writeCell($sheet, 'K' . $row, $item->desi_fark, DataType::TYPE_NUMERIC);
            $this->writeCell($sheet, 'L' . $row, $item->beklenen_tutar, DataType::TYPE_NUMERIC);
            $this->writeCell($sheet, 'M' . $row, $item->gercek_tutar, DataType::TYPE_NUMERIC);
            $this->writeCell($sheet, 'N' . $row, $item->tutar_fark, DataType::TYPE_NUMERIC);
            $this->writeCell($sheet, 'O' . $row, CargoReportItem::ERROR_TYPES[$item->error_type]['label'] ?? $item->error_type);
            $this->writeCell($sheet, 'P' . $row, $item->has_error ? 'HATA' : 'OK');

            if ($item->has_error) {
                $color = $item->isAgainstUs() ? 'FECACA' : 'FEF3C7';
                $sheet->getStyle('A' . $row . ':P' . $row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB($color);
            }

            $row++;
        }

        // Auto width
        foreach (range('A', 'P') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /**
     * Sadece hatalar sayfası
     */
    protected function createErrorsSheet(Spreadsheet $spreadsheet, CargoReport $report)
    {
        $errorItems = $report->items->where('has_error', true);
        if ($errorItems->isEmpty()) return;

        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($this->sanitizeSheetName('Hatalar'));

        $headers = [
            'Tarih', 'Müşteri', 'Takip Kodu', 'Ürün',
            'Beklenen Desi', 'Gerçek Desi', 'Desi Fark',
            'Beklenen Tutar', 'Gerçek Tutar', 'Tutar Fark',
            'Hata Tipi', 'Kargo Takip Link'
        ];

        $col = 'A';
        foreach ($headers as $header) {
            $this->writeCell($sheet, $col . '1', $header);
            $col++;
        }

        $sheet->getStyle('A1:L1')->getFont()->setBold(true);
        $sheet->getStyle('A1:L1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('DC2626');
        $sheet->getStyle('A1:L1')->getFont()->getColor()->setRGB('FFFFFF');

        $row = 2;
        foreach ($errorItems as $item) {
            $this->writeCell($sheet, 'A' . $row, $item->tarih?->format('d.m.Y'));
            $this->writeCell($sheet, 'B' . $row, $item->musteri_adi);
            $this->writeCell($sheet, 'C' . $row, $item->takip_kodu);
            $this->writeCell($sheet, 'D' . $row, $item->urun_adi);
            $this->writeCell($sheet, 'E' . $row, $item->beklenen_desi, DataType::TYPE_NUMERIC);
            $this->writeCell($sheet, 'F' . $row, $item->gercek_desi, DataType::TYPE_NUMERIC);
            $this->writeCell($sheet, 'G' . $row, $item->desi_fark, DataType::TYPE_NUMERIC);
            $this->writeCell($sheet, 'H' . $row, $item->beklenen_tutar, DataType::TYPE_NUMERIC);
            $this->writeCell($sheet, 'I' . $row, $item->gercek_tutar, DataType::TYPE_NUMERIC);
            $this->writeCell($sheet, 'J' . $row, $item->tutar_fark, DataType::TYPE_NUMERIC);
            $this->writeCell($sheet, 'K' . $row, CargoReportItem::ERROR_TYPES[$item->error_type]['label'] ?? $item->error_type);
            $this->writeCell($sheet, 'L' . $row, $item->tracking_url ?? '');

            if ($item->isAgainstUs()) {
                $sheet->getStyle('A' . $row . ':L' . $row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FECACA');
            }

            $row++;
        }

        // Auto width
        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    protected function writeCell(Worksheet $sheet, string $cell, $value, string $type = DataType::TYPE_STRING): void
    {
        $excel = app(ExcelService::class);

        if ($type === DataType::TYPE_NUMERIC) {
            $sheet->setCellValueExplicit($cell, (float) ($value ?? 0), DataType::TYPE_NUMERIC);
            return;
        }

        $sheet->setCellValueExplicit(
            $cell,
            (string) $excel->cleanString($value ?? ''),
            DataType::TYPE_STRING
        );
    }

    protected function sanitizeSheetName(string $name): string
    {
        $excel = app(ExcelService::class);
        $name = (string) $excel->cleanString($name);
        $name = str_replace([':', '\\', '/', '?', '*', '[', ']'], '', $name);

        if (mb_strlen($name) > 31) {
            $name = mb_substr($name, 0, 31);
        }

        return $name !== '' ? $name : 'Sheet';
    }

    /**
     * Yeni karşılaştırma başlat (formu temizle)
     */
    public function resetForm()
    {
        $this->reset([
            'cargoFile', 'orderFile', 'reportName', 'useLegacyExcelReference',
            'hasResults', 'currentReportId', 'message', 'sourceReportDate',
            'filterErrorType', 'filterMatched', 'filterType', 'searchCustomer',
            'sortField', 'sortDirection'
        ]);

        $this->selectedSuratReportDate = $this->latestSuratReportDate() ?? '';

        $this->resetPage();

        $this->stats = [
            'total_orders' => 0,
            'matched_orders' => 0,
            'unmatched_orders' => 0,
            'error_count' => 0,
            'iade_count' => 0,
            'iade_tutar' => 0,
            'parca_count' => 0,
            'parca_tutar' => 0,
            'total_desi_diff' => 0,
            'total_tutar_diff' => 0,
            'reference_issue_count' => 0,
        ];
    }

    protected function marketplaceReferencesReady(): bool
    {
        $stats = $this->marketplaceStats;

        if (($stats['products_ready'] ?? 0) < 1) {
            $this->showMessage('Pazaryeri Ürünlerim modülünde desi, parça ve kargo fiyatı dolu en az 1 ürün bulunmalı.', 'warning');
            return false;
        }

        if (($stats['orders_total'] ?? 0) < 1 || ($stats['order_items_total'] ?? 0) < 1) {
            $this->showMessage('Pazaryeri Siparişlerim modülünde içe aktarılmış sipariş verisi bulunamadı. Önce operasyonel sipariş Excelini yükleyin.', 'warning');
            return false;
        }

        return true;
    }

    protected function latestSuratReportDate(): ?string
    {
        if (!Schema::hasTable('cargo_report_lines')) {
            return null;
        }

        $date = CargoReportLine::query()
            ->where('user_id', auth()->id())
            ->where('carrier_code', 'surat')
            ->max('report_date');

        return $date ? Carbon::parse($date)->toDateString() : null;
    }

    protected function cargoRowsFromReportLines($lines)
    {
        return $lines->values()->map(function (CargoReportLine $line, int $index) {
            $totalAmount = (float) ($line->total_amount ?? 0);
            if ($totalAmount <= 0) {
                $totalAmount = (float) ($line->amount ?? 0) + (float) ($line->measurement_amount ?? 0);
            }
            if ($totalAmount <= 0) {
                $totalAmount = (float) ($line->amount ?? 0);
            }

            $invoiceDate = $line->document_date ?: $line->carrier_created_at ?: $line->report_date;

            return [
                '_row_number' => $index + 2,
                'web_siparis_kodu' => (string) ($line->web_order_code ?: $line->sales_code ?: ''),
                'takip_no' => (string) ($line->tracking_number ?: ''),
                'alici' => (string) ($line->customer_name ?: ''),
                'gonderen' => (string) ($line->sender_name ?: ''),
                'borclu_unvan' => (string) ($line->sender_name ?: ''),
                'adet' => max(1, (int) ($line->pieces ?? 1)),
                'desi' => (float) ($line->desi ?? 0),
                'tutar' => $totalAmount,
                'cikis_il' => '',
                'teslim_tarihi' => $line->delivered_at?->toDateString(),
                'fatura_tarihi' => $invoiceDate?->toDateString(),
                'tesellum_fatura_no' => (string) ($line->sales_code ?: ''),
                'barkod' => (string) ($line->tracking_number ?: ''),
                'alici_il' => (string) ($line->destination_city ?: ''),
                'alici_ilce' => (string) ($line->destination_district ?: ''),
                'durum' => (string) ($line->status ?: ''),
            ];
        });
    }

    /**
     * Ürün düzenleme modalını aç
     */
    public function openProductEditModal(string $stokKodu, float $desi, float $tutar, int $parca, string $urunAdi = '')
    {
        $this->editStokKodu = trim($stokKodu);
        $this->editUrunAdi = trim($urunAdi);
        $this->editDesi = $desi;
        $this->editTutar = $tutar;
        $this->editParca = max(1, $parca);
        $this->showEditModal = true;
    }

    /**
     * Ürün düzenleme modalını kapat
     */
    public function closeProductEditModal()
    {
        $this->showEditModal = false;
        $this->editStokKodu = '';
        $this->editUrunAdi = '';
        $this->editDesi = 0;
        $this->editTutar = 0;
        $this->editParca = 1;
    }

    /**
     * Modaldan ürün bilgilerini güncelle (Sadece Admin)
     */
    public function updateProductFromModal()
    {
        // Admin kontrolü
        if (!auth()->user()?->isAdmin()) {
            $this->showMessage('Bu işlem için yetkiniz yok.', 'error');
            $this->closeProductEditModal();
            return;
        }

        $validated = $this->validate([
            'editStokKodu' => ['required', 'string', 'max:30'],
            'editUrunAdi' => ['nullable', 'string', 'max:255'],
            'editDesi' => ['required', 'numeric', 'min:0'],
            'editParca' => ['required', 'integer', 'min:1', 'max:99'],
            'editTutar' => ['required', 'numeric', 'min:0'],
        ], [
            'editStokKodu.required' => 'Stok kodu boş olamaz.',
            'editStokKodu.max' => 'Stok kodu en fazla 30 karakter olmalı.',
            'editDesi.min' => 'Desi negatif olamaz.',
            'editParca.min' => 'Parça en az 1 olmalı.',
            'editTutar.min' => 'Tutar negatif olamaz.',
        ]);

        try {
            $userId = auth()->id() ?? 1;
            $stockCode = trim((string) $validated['editStokKodu']);
            $productName = trim((string) ($validated['editUrunAdi'] ?? '')) ?: $stockCode;
            $desi = round((float) $validated['editDesi'], 2);
            $pieces = max(1, (int) $validated['editParca']);
            $cargoCost = round((float) $validated['editTutar'], 2);

            [$mpProduct, $product, $createdMpProduct, $createdReference] = DB::transaction(function () use ($userId, $stockCode, $productName, $desi, $pieces, $cargoCost) {
                $matchingMpProducts = $this->matchingMarketplaceProducts($userId, $stockCode);
                $mpProduct = $matchingMpProducts->first();
                $createdMpProduct = false;

                if (!$mpProduct) {
                    $mpProduct = MpProduct::query()->create([
                        'user_id' => $userId,
                        'barcode' => $stockCode,
                        'stock_code' => $stockCode,
                        'product_name' => $productName,
                        'desi' => $desi,
                        'pieces' => $pieces,
                        'cargo_cost' => $cargoCost,
                        'status' => 'active',
                        'import_source' => 'cargo_checker_manual',
                        'logistics_source' => 'manual',
                    ]);
                    $createdMpProduct = true;
                } else {
                    $matchingMpProducts->each(function (MpProduct $product) use ($stockCode, $productName, $desi, $pieces, $cargoCost): void {
                        $product->update([
                            'stock_code' => $product->stock_code ?: $stockCode,
                            'product_name' => $product->product_name ?: $productName,
                            'desi' => $desi,
                            'pieces' => $pieces,
                            'cargo_cost' => $cargoCost,
                            'logistics_source' => 'manual',
                        ]);
                    });
                }

                $product = $this->matchingReferenceProduct($stockCode);
                $before = $product?->toReferenceSnapshot();
                $createdReference = false;

                if (!$product) {
                    $product = Product::query()->create([
                        'stok_kodu' => $stockCode,
                        'urun_adi' => $productName,
                        'parca' => $pieces,
                        'desi' => $desi,
                        'tutar' => $cargoCost,
                        'is_active' => true,
                        'updated_by' => $userId,
                    ]);
                    $createdReference = true;
                } else {
                    $product->update([
                        'urun_adi' => $product->urun_adi ?: $productName,
                        'parca' => $pieces,
                        'desi' => $desi,
                        'tutar' => $cargoCost,
                        'is_active' => true,
                        'updated_by' => $userId,
                    ]);
                }

                $this->logProductHistory(
                    $product->fresh(),
                    $before,
                    $createdReference ? 'cargo_checker_create' : 'cargo_checker_update',
                    $createdReference
                        ? 'Desi ve tutar check ekranından eksik referans oluşturuldu.'
                        : 'Desi ve tutar check ekranından referans güncellendi.'
                );

                return [$mpProduct->fresh(), $product->fresh(), $createdMpProduct, $createdReference];
            });

            $action = $createdMpProduct || $createdReference ? 'oluşturuldu ve güncellendi' : 'güncellendi';

            $this->showMessage("✅ {$stockCode} ürün referansı {$action}. Desi: {$desi}, Parça: {$pieces}, Tutar: {$cargoCost}₺", 'success');
            $this->closeProductEditModal();

        } catch (\Exception $e) {
            Log::error('CargoChecker: Ürün güncelleme hatası', [
                'stok_kodu' => $this->editStokKodu,
                'error' => $e->getMessage(),
            ]);
            $this->showMessage('Güncelleme hatası: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Mesaj göster
     */
    protected function showMessage(string $message, string $type = 'info')
    {
        $this->message = $message;
        $this->messageType = $type;
    }

    protected function logProductHistory(?Product $product, ?array $beforeSnapshot, string $source, ?string $note = null): void
    {
        if (!Schema::hasTable('product_reference_histories')) {
            return;
        }

        ProductReferenceHistory::create([
            'product_id' => $product?->id,
            'stok_kodu' => $product?->stok_kodu ?? ($beforeSnapshot['stok_kodu'] ?? ''),
            'change_source' => $source,
            'note' => $note,
            'previous_snapshot' => $beforeSnapshot,
            'new_snapshot' => $product?->toReferenceSnapshot(),
            'changed_by' => auth()->id(),
        ]);
    }

    protected function matchingMarketplaceProducts(int $userId, string $stockCode)
    {
        $normalizedStockCode = $this->normalizeReferenceCode($stockCode);

        if ($normalizedStockCode === '') {
            return collect();
        }

        return MpProduct::query()
            ->where('user_id', $userId)
            ->where(function ($query) {
                $query->whereNotNull('stock_code')
                    ->orWhereNotNull('barcode');
            })
            ->get()
            ->filter(function (MpProduct $product) use ($normalizedStockCode): bool {
                return $this->normalizeReferenceCode($product->stock_code) === $normalizedStockCode
                    || $this->normalizeReferenceCode($product->barcode) === $normalizedStockCode;
            })
            ->values();
    }

    protected function matchingReferenceProduct(string $stockCode): ?Product
    {
        $normalizedStockCode = $this->normalizeReferenceCode($stockCode);

        if ($normalizedStockCode === '') {
            return null;
        }

        return Product::query()
            ->get()
            ->first(fn(Product $product): bool => $this->normalizeReferenceCode($product->stok_kodu) === $normalizedStockCode);
    }

    protected function normalizeReferenceCode(mixed $value): string
    {
        $value = trim((string) $value);
        $value = preg_replace('/\s+/u', '', $value) ?? $value;

        return mb_strtoupper($value, 'UTF-8');
    }

    public function render()
    {
        return view('livewire.cargo.cargo-checker');
    }

    protected function normalizeVisibleColumns(array $columns): array
    {
        $allowed = array_keys(static::$allColumnDefs);
        $normalized = array_values(array_unique(array_values(array_intersect($allowed, $columns))));

        return $normalized !== [] ? $normalized : $this->visibleColumns;
    }
}
