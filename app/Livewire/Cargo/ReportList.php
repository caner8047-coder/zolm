<?php

namespace App\Livewire\Cargo;

use App\Models\CargoReport;
use App\Models\CargoReportItem;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;

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

    // Filtreler
    public ?string $filterDate = null;
    public ?string $filterDateEnd = null;
    public string $filterCompany = '';
    public string $sortField = 'report_date';
    public string $sortDirection = 'desc';

    // Detay modalı
    public bool $showDetailModal = false;
    public ?int $viewingReportId = null;
    public array $viewingItems = [];
    public string $itemFilterErrorType = 'all';
    public string $itemFilterType = 'all'; // all, siparis, iade, parca

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
    public function sortBy(string $field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'desc';
        }
    }

    /**
     * Rapor detayını görüntüle
     */
    public function viewReport(int $id)
    {
        $this->viewingReportId = $id;
        $this->loadReportItems();
        $this->showDetailModal = true;
    }

    /**
     * Rapor satırlarını yükle
     */
    protected function loadReportItems()
    {
        if (!$this->viewingReportId) return;

        $query = CargoReportItem::where('cargo_report_id', $this->viewingReportId);

        // Hata filtresi
        if ($this->itemFilterErrorType !== 'all') {
            if ($this->itemFilterErrorType === 'errors') {
                $query->where('has_error', true);
            } else {
                $query->where('error_type', $this->itemFilterErrorType);
            }
        }

        // Tip filtresi
        if ($this->itemFilterType === 'siparis') {
            $query->where('is_iade', false)->where('is_parca_gonderi', false);
        } elseif ($this->itemFilterType === 'iade') {
            $query->where('is_iade', true);
        } elseif ($this->itemFilterType === 'parca') {
            $query->where('is_parca_gonderi', true);
        }

        $this->viewingItems = $query
            ->orderBy('has_error', 'desc')
            ->orderBy('tutar_fark', 'desc')
            ->limit(100)
            ->get()
            ->toArray();
    }

    /**
     * Item filtresi değiştiğinde
     */
    public function updatedItemFilterErrorType()
    {
        $this->loadReportItems();
    }

    /**
     * Tip filtresi değiştiğinde
     */
    public function updatedItemFilterType()
    {
        $this->loadReportItems();
    }

    /**
     * Detay modalını kapat
     */
    public function closeDetailModal()
    {
        $this->showDetailModal = false;
        $this->viewingReportId = null;
        $this->viewingItems = [];
        $this->itemFilterErrorType = 'all';
        $this->itemFilterType = 'all';
    }

    /**
     * Raporu indir (Ayrı sayfalarla)
     */
    public function downloadReport(int $id)
    {
        try {
            $report = CargoReport::find($id); // with('items') kaldırıldı
            if (!$report) {
                $this->showMessage('Rapor bulunamadı.', 'error');
                return;
            }

            $spreadsheet = new Spreadsheet();

            // 1. Özet Sayfası
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Özet');

            $sheet->setCellValue('A1', 'Rapor: ' . $report->name);
            $sheet->setCellValue('A2', 'Tarih: ' . $report->report_date->format('d.m.Y'));
            $sheet->setCellValue('A3', 'Kargo: ' . $report->cargo_company);
            $sheet->setCellValue('A5', 'Toplam Sipariş: ' . $report->total_orders);
            $sheet->setCellValue('A6', 'Eşleşen: ' . $report->matched_orders);
            $sheet->setCellValue('A7', 'Hatalı: ' . $report->error_count);
            $sheet->setCellValue('A8', 'Desi Farkı: ' . number_format($report->total_desi_diff, 2));
            $sheet->setCellValue('A9', 'Tutar Farkı: ' . number_format($report->total_tutar_diff, 2) . ' TL');

            // İstatistikler (DB Aggregates)
            $normalSiparisCount = $report->items()
                ->where('is_iade', false)
                ->where('is_parca_gonderi', false)
                ->count();
            
            $iadeQuery = $report->items()->where('is_iade', true);
            $iadeCount = $iadeQuery->count();
            $iadeTutar = $iadeQuery->sum('gercek_tutar');

            $parcaQuery = $report->items()->where('is_parca_gonderi', true);
            $parcaCount = $parcaQuery->count();
            $parcaTutar = $parcaQuery->sum('gercek_tutar');

            $hataliCount = $report->items()->where('has_error', true)->count();

            $sheet->setCellValue('A11', 'İstatistikler');
            $sheet->setCellValue('A12', 'Normal Sipariş: ' . $normalSiparisCount);
            $sheet->setCellValue('A13', 'İade/Değişim: ' . $iadeCount . ' (' . number_format($iadeTutar, 2) . ' TL)');
            $sheet->setCellValue('A14', 'Parça Gönderisi: ' . $parcaCount . ' (' . number_format($parcaTutar, 2) . ' TL)');
            $sheet->setCellValue('A15', 'Hatalı Kayıt: ' . $hataliCount);

            // 2. Siparişler Sayfası
            $siparisSheet = $spreadsheet->createSheet();
            $siparisSheet->setTitle('Siparişler');
            $this->fillItemSheet($siparisSheet, $report->items()
                ->where('is_iade', false)
                ->where('is_parca_gonderi', false)
                ->orderBy('id'));

            // 3. İadeler Sayfası
            $iadeSheet = $spreadsheet->createSheet();
            $iadeSheet->setTitle('İadeler');
            $this->fillItemSheet($iadeSheet, $report->items()
                ->where('is_iade', true)
                ->orderBy('id'));

            // 4. Parça Gönderileri Sayfası
            $parcaSheet = $spreadsheet->createSheet();
            $parcaSheet->setTitle('Parça Gönderileri');
            $this->fillItemSheet($parcaSheet, $report->items()
                ->where('is_parca_gonderi', true)
                ->orderBy('id'));

            // Kaydet
            $fileName = 'rapor_' . $report->id . '_' . $report->report_date->format('Y-m-d') . '.xlsx';
            $tempPath = storage_path('app/temp/' . $fileName);

            if (!is_dir(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save($tempPath);

            return response()->download($tempPath, $fileName)->deleteFileAfterSend();

        } catch (\Exception $e) {
            Log::error('ReportList: Download hatası', ['error' => $e->getMessage()]);
            $this->showMessage('İndirme hatası: ' . $e->getMessage(), 'error');
            return null;
        }
    }

    /**
     * Excel sayfasına item'ları chunk halinde yaz
     */
    protected function fillItemSheet($sheet, $query)
    {
        $headers = ['Tarih', 'Müşteri', 'Stok Kodu', 'Takip Kodu', 'Ürün', 'Adet', 'Bek.Desi', 'Ger.Desi', 'Fark', 'Bek.Tutar', 'Ger.Tutar', 'Fark', 'Durum'];
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col . '1', $h);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $col++;
        }

        $row = 2;
        
        // Chunking with 1000 items
        $query->chunk(1000, function ($items) use ($sheet, &$row) {
            foreach ($items as $item) {
                $sheet->setCellValue('A' . $row, $item->tarih?->format('d.m.Y'));
                $sheet->setCellValue('B' . $row, $item->musteri_adi);
                $sheet->setCellValue('C' . $row, $item->stok_kodu);
                $sheet->setCellValue('D' . $row, $item->takip_kodu);
                $sheet->setCellValue('E' . $row, $item->urun_adi);
                $sheet->setCellValue('F' . $row, $item->adet);
                $sheet->setCellValue('G' . $row, $item->beklenen_desi);
                $sheet->setCellValue('H' . $row, $item->gercek_desi);
                $sheet->setCellValue('I' . $row, $item->desi_fark);
                $sheet->setCellValue('J' . $row, $item->beklenen_tutar);
                $sheet->setCellValue('K' . $row, $item->gercek_tutar);
                $sheet->setCellValue('L' . $row, $item->tutar_fark);
                $sheet->setCellValue('M' . $row, $item->has_error ? 'HATA' : 'OK');

                // Hatalı satırları renklendir
                if ($item->has_error) {
                    $sheet->getStyle('A' . $row . ':M' . $row)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('FECACA');
                }

                $row++;
            }
        });

        // Kolon genişliklerini ayarla (Sadece ilk chunk verilerine göre değil genel ayarlarız ama autosize tüm sheet'e bakar)
        foreach (range('A', 'M') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
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
}
