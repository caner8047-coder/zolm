<?php

namespace App\Livewire\Cargo;

use App\Models\CargoReport;
use App\Models\CargoReportItem;
use App\Models\Product;
use App\Services\CargoComparisonEngine;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

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

    // Dosya yükleme
    public $cargoFile;
    public $orderFile;

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
    ];

    // Filtreler
    public string $filterErrorType = 'all';
    public string $filterMatched = 'all';
    public string $filterType = 'siparis';  // siparis, iade, parca, all
    public string $searchCustomer = '';

    // Mesajlar
    public string $message = '';
    public string $messageType = 'info';

    // Kaydetme modalı
    public bool $showSaveModal = false;

    // Ürün Düzenleme Modalı
    public bool $showEditModal = false;
    public string $editStokKodu = '';
    public float $editDesi = 0;
    public int $editParca = 1;
    public float $editTutar = 0;

    /**
     * Kargo firması seçenekleri
     */
    #[Computed]
    public function cargoCompanies()
    {
        return collect(config('cargo.companies'))->pluck('name', 'name');
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
        if ($this->filterMatched !== 'all') {
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

        return $query->orderBy('is_iade', 'asc')  // Önce siparişler
            ->orderBy('is_parca_gonderi', 'asc')  // Sonra normal siparişler
            ->orderBy('has_error', 'desc')
            ->orderBy('tutar_fark', 'desc')
            ->paginate(50);
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
            'desi_fazla' => 'Desi Fazla',
            'desi_eksik' => 'Desi Eksik',
            'tutar_fazla' => 'Tutar Fazla',
            'tutar_eksik' => 'Tutar Eksik',
            'parca_eksik' => 'Parça Eksik',
            'eslesmedi' => 'Eşleşmedi',
        ];
    }

    /**
     * Ürün sayısı kontrolü
     */
    #[Computed]
    public function productCount()
    {
        return Product::active()->count();
    }

    /**
     * Karşılaştırma çalıştır
     */
    public function runComparison()
    {
        $this->validate([
            'cargoFile' => 'required|file|mimes:xlsx,xls|max:20480',
            'orderFile' => 'required|file|mimes:xlsx,xls|max:20480',
        ], [
            'cargoFile.required' => 'Kargo raporu dosyası gerekli.',
            'orderFile.required' => 'Sipariş detayları dosyası gerekli.',
        ]);

        // Ürün listesi kontrolü
        if ($this->productCount < 1) {
            $this->showMessage('Önce ürün listesini yüklemelisiniz. "Ürün ve Desi Bilgileri" sekmesinden ürün ekleyin.', 'warning');
            return;
        }

        $this->isProcessing = true;
        $this->message = '';

        try {
            $engine = app(CargoComparisonEngine::class);

            $result = $engine->compare(
                $this->cargoFile,
                $this->orderFile,
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

        $this->stats = [
            'total_orders' => $report->total_orders,
            'matched_orders' => $report->matched_orders,
            'unmatched_orders' => $report->unmatched_orders,
            'error_count' => $report->error_count,
            'iade_count' => $iadeCount,
            'iade_tutar' => $iadeTutar,
            'parca_count' => $parcaCount,
            'parca_tutar' => $parcaTutar,
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
        $sheet->setTitle('Özet');

        // Başlık
        $sheet->setCellValue('A1', 'KARGO KONTROL RAPORU');
        $sheet->mergeCells('A1:D1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);

        // Rapor bilgileri
        $sheet->setCellValue('A3', 'Rapor Adı:');
        $sheet->setCellValue('B3', $report->name);
        $sheet->setCellValue('A4', 'Kargo Firması:');
        $sheet->setCellValue('B4', $report->cargo_company);
        $sheet->setCellValue('A5', 'Tarih:');
        $sheet->setCellValue('B5', $report->report_date->format('d.m.Y'));

        // İstatistikler
        $sheet->setCellValue('A7', 'İSTATİSTİKLER');
        $sheet->getStyle('A7')->getFont()->setBold(true);

        $stats = [
            ['Toplam Sipariş', $report->total_orders],
            ['Eşleşen', $report->matched_orders],
            ['Eşleşmeyen', $report->unmatched_orders],
            ['Hatalı', $report->error_count],
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
            $sheet->setCellValue('A' . $row, $stat[0]);
            $sheet->setCellValue('B' . $row, $stat[1]);
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
        $sheet->setTitle('Tüm Kayıtlar');

        // Headers
        $headers = [
            'Tarih', 'Müşteri', 'Takip Kodu', 'Ürün', 'Adet',
            'Bek. Parça', 'Ger. Parça', 'P. Fark',
            'Bek. Desi', 'Ger. Desi', 'D. Fark',
            'Bek. Tutar', 'Ger. Tutar', 'T. Fark',
            'Hata Tipi', 'Durum'
        ];

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }

        // Header style
        $sheet->getStyle('A1:P1')->getFont()->setBold(true);
        $sheet->getStyle('A1:P1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('1F2937');
        $sheet->getStyle('A1:P1')->getFont()->getColor()->setRGB('FFFFFF');

        // Data
        $row = 2;
        foreach ($report->items as $item) {
            $sheet->setCellValue('A' . $row, $item->tarih?->format('d.m.Y'));
            $sheet->setCellValue('B' . $row, $item->musteri_adi);
            $sheet->setCellValue('C' . $row, $item->takip_kodu);
            $sheet->setCellValue('D' . $row, $item->urun_adi);
            $sheet->setCellValue('E' . $row, $item->adet);
            $sheet->setCellValue('F' . $row, $item->beklenen_parca);
            $sheet->setCellValue('G' . $row, $item->gercek_parca);
            $sheet->setCellValue('H' . $row, $item->parca_fark);
            $sheet->setCellValue('I' . $row, $item->beklenen_desi);
            $sheet->setCellValue('J' . $row, $item->gercek_desi);
            $sheet->setCellValue('K' . $row, $item->desi_fark);
            $sheet->setCellValue('L' . $row, $item->beklenen_tutar);
            $sheet->setCellValue('M' . $row, $item->gercek_tutar);
            $sheet->setCellValue('N' . $row, $item->tutar_fark);
            $sheet->setCellValue('O' . $row, CargoReportItem::ERROR_TYPES[$item->error_type]['label'] ?? $item->error_type);
            $sheet->setCellValue('P' . $row, $item->has_error ? 'HATA' : 'OK');

            // Hatalı satırları renklendir
            if ($item->has_error) {
                $color = $item->isAgainstUs() ? 'FECACA' : 'FEF3C7'; // Kırmızı veya sarı
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
        $sheet->setTitle('Hatalar');

        // Headers
        $headers = [
            'Tarih', 'Müşteri', 'Takip Kodu', 'Ürün',
            'Beklenen Desi', 'Gerçek Desi', 'Desi Fark',
            'Beklenen Tutar', 'Gerçek Tutar', 'Tutar Fark',
            'Hata Tipi', 'Kargo Takip Link'
        ];

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }

        // Header style
        $sheet->getStyle('A1:L1')->getFont()->setBold(true);
        $sheet->getStyle('A1:L1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('DC2626');
        $sheet->getStyle('A1:L1')->getFont()->getColor()->setRGB('FFFFFF');

        // Data
        $row = 2;
        foreach ($errorItems as $item) {
            $sheet->setCellValue('A' . $row, $item->tarih?->format('d.m.Y'));
            $sheet->setCellValue('B' . $row, $item->musteri_adi);
            $sheet->setCellValue('C' . $row, $item->takip_kodu);
            $sheet->setCellValue('D' . $row, $item->urun_adi);
            $sheet->setCellValue('E' . $row, $item->beklenen_desi);
            $sheet->setCellValue('F' . $row, $item->gercek_desi);
            $sheet->setCellValue('G' . $row, $item->desi_fark);
            $sheet->setCellValue('H' . $row, $item->beklenen_tutar);
            $sheet->setCellValue('I' . $row, $item->gercek_tutar);
            $sheet->setCellValue('J' . $row, $item->tutar_fark);
            $sheet->setCellValue('K' . $row, CargoReportItem::ERROR_TYPES[$item->error_type]['label'] ?? $item->error_type);
            $sheet->setCellValue('L' . $row, $item->tracking_url ?? '');

            // Aleyhimize olan satırları kırmızı yap
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

    /**
     * Yeni karşılaştırma başlat (formu temizle)
     */
    public function resetForm()
    {
        $this->reset([
            'cargoFile', 'orderFile', 'reportName',
            'hasResults', 'currentReportId', 'message',
            'filterErrorType', 'filterMatched', 'filterType', 'searchCustomer'
        ]);

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
        ];
    }

    /**
     * Ürün düzenleme modalını aç
     */
    public function openProductEditModal(string $stokKodu, float $desi, float $tutar, int $parca)
    {
        $this->editStokKodu = $stokKodu;
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

        try {
            $product = Product::where('stok_kodu', $this->editStokKodu)->first();

            if (!$product) {
                $this->showMessage("Ürün bulunamadı: {$this->editStokKodu}", 'error');
                return;
            }

            $product->update([
                'desi' => $this->editDesi,
                'parca' => $this->editParca,
                'tutar' => $this->editTutar,
            ]);

            $this->showMessage("✅ {$this->editStokKodu} güncellendi! Desi: {$this->editDesi}, Parça: {$this->editParca}, Tutar: {$this->editTutar}₺", 'success');
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

    public function render()
    {
        return view('livewire.cargo.cargo-checker');
    }
}
