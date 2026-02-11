<?php

namespace App\Livewire;

use App\Models\SupplyOrder;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SupplyReports extends Component
{
    use WithFileUploads, WithPagination;

    // Dosya yükleme
    public $excelFile;
    public $isUploading = false;
    public $uploadProgress = 0;
    public $lastImportCount = 0;

    // Filtreler
    public string $search = '';
    public string $durumFiltre = 'hepsi';
    public string $sebebiyetFiltre = 'hepsi';
    public string $gecikmeFiltre = 'hepsi'; // hepsi, gecikmis, zamaninda

    // Tarih filtreleme
    public ?string $baslangicTarihi = null;
    public ?string $bitisTarihi = null;
    public string $tarihAlani = 'soz_tarihi'; // soz_tarihi veya kayit_tarihi

    // Sıralama
    public string $sortField = 'soz_tarihi';
    public string $sortDirection = 'asc';

    // Toplu işlem
    public array $selectedIds = [];
    public bool $selectAll = false;

    // Modal
    public bool $showImportModal = false;
    public bool $showBulkModal = false;
    public string $bulkAction = '';
    public string $bulkDurum = 'uretim';
    public string $bulkSebebiyet = 'yok';

    protected $queryString = [
        'search' => ['except' => ''],
        'durumFiltre' => ['except' => 'hepsi'],
        'sebebiyetFiltre' => ['except' => 'hepsi'],
        'baslangicTarihi' => ['except' => ''],
        'bitisTarihi' => ['except' => ''],
    ];

    protected $listeners = ['refreshList' => '$refresh'];

    /**
     * Excel kolon eşleştirmesi
     */
    protected array $columnMap = [
        'C' => 'kayit_tarihi',
        'D' => 'siparis_no',
        'T' => 'telefon',
        'U' => 'musteri_adi',
        'Y' => 'adres',
        'AA' => 'ilce',
        'AB' => 'il',
        'AN' => 'urun_adi',
        'AP' => 'kategori',
        'AQ' => 'adet',
        'BP' => 'soz_tarihi',
        'BW' => 'renk_etiketi',
    ];

    public function mount()
    {
        // Başlangıç ayarları
    }

    /**
     * Siparişleri getir (flat list)
     */
    public function getOrdersProperty()
    {
        return SupplyOrder::query()
            ->arama($this->search)
            ->durumFiltre($this->durumFiltre)
            ->sebebiyetFiltre($this->sebebiyetFiltre)
            ->when($this->gecikmeFiltre === 'gecikmis', fn($q) => $q->gecikmis())
            ->when($this->gecikmeFiltre === 'zamaninda', fn($q) => $q->where(function($q) {
                $q->whereNull('soz_tarihi')
                  ->orWhereDate('soz_tarihi', '>=', Carbon::today())
                  ->orWhere('durum', 'gonderildi');
            }))
            // Tarih aralığı filtreleme
            ->when($this->baslangicTarihi, fn($q) => $q->whereDate($this->tarihAlani, '>=', $this->baslangicTarihi))
            ->when($this->bitisTarihi, fn($q) => $q->whereDate($this->tarihAlani, '<=', $this->bitisTarihi))
            // Önce bekliyor olanlar, sonra diğerleri
            ->orderByRaw("FIELD(durum, 'bekliyor', 'uretim', 'paketleme', 'kargo', 'gonderildi')")
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(50);
    }

    /**
     * Gruplanmış siparişleri getir (siparis_no'ya göre)
     * Her sipariş grubunda birden fazla ürün olabilir
     */
    public function getGroupedOrdersProperty()
    {
        $orders = $this->orders;
        
        // Siparişleri siparis_no'ya göre grupla
        $grouped = collect($orders->items())->groupBy('siparis_no')->map(function($items, $siparisNo) {
            $firstItem = $items->first();
            return [
                'siparis_no' => $siparisNo,
                'musteri_adi' => $firstItem->musteri_adi,
                'kayit_tarihi' => $firstItem->kayit_tarihi,
                'soz_tarihi' => $firstItem->soz_tarihi,
                'telefon' => $firstItem->telefon,
                'tam_adres' => $firstItem->tam_adres,
                'is_gecikmis' => $items->contains('is_gecikmis', true),
                'items' => $items, // Tüm ürünler
                'item_count' => $items->count(),
                'total_adet' => $items->sum('adet'),
            ];
        })->values();
        
        return $grouped;
    }

    /**
     * İstatistikleri getir (benzersiz sipariş sayıları)
     */
    public function getStatsProperty(): array
    {
        return [
            'bekleyen' => SupplyOrder::bekleyen()->distinct('siparis_no')->count('siparis_no'),
            'bugun_gonderilen' => SupplyOrder::bugünGonderilen()->distinct('siparis_no')->count('siparis_no'),
            'gecikmis' => SupplyOrder::gecikmis()->distinct('siparis_no')->count('siparis_no'),
            'sebebiyet' => [
                'uretim' => SupplyOrder::bekleyen()->where('sebebiyet', 'uretim')->distinct('siparis_no')->count('siparis_no'),
                'paketleme' => SupplyOrder::bekleyen()->where('sebebiyet', 'paketleme')->distinct('siparis_no')->count('siparis_no'),
                'kargo' => SupplyOrder::bekleyen()->where('sebebiyet', 'kargo')->distinct('siparis_no')->count('siparis_no'),
            ],
        ];
    }

    /**
     * Sıralama değiştir
     */
    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    /**
     * Excel dosyasını yükle ve parse et
     */
    public function importExcel(): void
    {
        $this->validate([
            'excelFile' => 'required|file|mimes:xlsx,xls|max:10240',
        ], [
            'excelFile.required' => 'Lütfen bir Excel dosyası seçin.',
            'excelFile.mimes' => 'Dosya Excel formatında (.xlsx, .xls) olmalıdır.',
            'excelFile.max' => 'Dosya boyutu en fazla 10MB olabilir.',
        ]);

        $this->isUploading = true;
        $this->lastImportCount = 0;

        try {
            $filePath = $this->excelFile->getRealPath();
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray(null, true, true, true);

            // İlk satır başlık, atla
            array_shift($data);

            $imported = 0;
            $skipped = 0;

        foreach ($data as $rowIndex => $row) {
                // Renk etiketi kontrolü - sadece "TEDARİK EDİLECEKLER" olanları al
                $renkEtiketi = $this->cleanValue($row['BW'] ?? '');
                
                // Boş satırları atla
                $siparisNo = $this->cleanValue($row['D'] ?? '');
                $musteriAdi = $this->cleanValue($row['U'] ?? '');
                $urunAdi = $this->cleanValue($row['AN'] ?? '');

                if (empty($siparisNo) || empty($musteriAdi) || empty($urunAdi)) {
                    $skipped++;
                    continue;
                }

                // Sipariş durumunu kontrol et
                $siparisStaturDurumu = mb_strtolower(trim($this->cleanValue($row['AI'] ?? '')));
                
                // İPTAL durumundaki siparişleri veritabanından sil
                if ($siparisStaturDurumu === 'iptal' || $siparisStaturDurumu === 'i̇ptal') {
                    $deleted = SupplyOrder::where('siparis_no', $siparisNo)
                        ->where('urun_adi', $urunAdi)
                        ->delete();
                    
                    if ($deleted > 0) {
                        Log::info('SupplyReports: İptal edilen sipariş silindi', [
                            'siparis_no' => $siparisNo,
                            'urun_adi' => $urunAdi,
                        ]);
                    }
                    $skipped++;
                    continue;
                }

                // SADECE "TEDARİK EDİLECEKLER" yazanları al (case-insensitive)
                if (mb_strtoupper(trim($renkEtiketi)) !== 'TEDARİK EDİLECEKLER') {
                    $skipped++;
                    continue;
                }

                // Tarihleri parse et
                $kayitTarihi = $this->parseDate($row['C'] ?? null);
                $sozTarihi = $this->parseDate($row['BP'] ?? null);

                // AI kolonundan (Sipariş Satır Durumu) durum oku
                $siparisStaturDurumu = $this->cleanValue($row['AI'] ?? '');
                $durum = $this->mapExcelDurumToDbDurum($siparisStaturDurumu);

                // AJ kolonundan sebebiyet oku (Üretim, Paketleme, Kargo)
                $excelSebebiyet = $this->cleanValue($row['AJ'] ?? '');
                $sebebiyet = $this->mapExcelSebebiyetToDbSebebiyet($excelSebebiyet);

                // Upsert - siparis_no + urun_adi kombinasyonu benzersiz anahtar
                // Aynı siparişte birden fazla ürün satırı olabilir
                SupplyOrder::updateOrCreate(
                    [
                        'siparis_no' => $siparisNo,
                        'urun_adi' => $urunAdi,
                    ],
                    [
                        'kayit_tarihi' => $kayitTarihi,
                        'musteri_adi' => $musteriAdi,
                        'telefon' => $this->cleanValue($row['T'] ?? ''),
                        'adres' => $this->cleanValue($row['Y'] ?? ''),
                        'ilce' => $this->cleanValue($row['AA'] ?? ''),
                        'il' => $this->cleanValue($row['AB'] ?? ''),
                        'kategori' => $this->cleanValue($row['AP'] ?? ''),
                        'adet' => max(1, (int) ($row['AQ'] ?? 1)),
                        'soz_tarihi' => $sozTarihi,
                        'renk_etiketi' => $renkEtiketi,
                        'durum' => $durum,
                        'sebebiyet' => $sebebiyet,
                    ]
                );

                $imported++;
            }

            $this->lastImportCount = $imported;
            $this->showImportModal = false;
            $this->excelFile = null;

            session()->flash('success', "{$imported} sipariş başarıyla içeri aktarıldı. ({$skipped} satır atlandı)");

            Log::info('SupplyReports: Excel import completed', [
                'imported' => $imported,
                'skipped' => $skipped,
            ]);

        } catch (\Exception $e) {
            Log::error('SupplyReports: Excel import hatası', [
                'error' => $e->getMessage(),
            ]);
            session()->flash('error', 'Excel dosyası işlenirken hata oluştu: ' . $e->getMessage());
        } finally {
            $this->isUploading = false;
        }
    }

    /**
     * Tek sipariş durumunu güncelle
     */
    public function updateDurum(int $orderId, string $newDurum): void
    {
        $order = SupplyOrder::find($orderId);
        if (!$order) return;

        $order->durum = $newDurum;

        // Gönderildi ise tarihi set et
        if ($newDurum === 'gonderildi') {
            $order->gonderim_tarihi = Carbon::today();
        }

        $order->save();

        session()->flash('success', "Sipariş durumu '{$order->durum_label}' olarak güncellendi.");
    }

    /**
     * Tek sipariş sebebiyetini güncelle
     */
    public function updateSebebiyet(int $orderId, string $newSebebiyet): void
    {
        $order = SupplyOrder::find($orderId);
        if (!$order) return;

        $order->sebebiyet = $newSebebiyet;
        $order->save();

        session()->flash('success', "Sipariş sebebiyeti güncellendi.");
    }

    /**
     * Toplu seçimi aç/kapa
     */
    public function toggleSelectAll(): void
    {
        if ($this->selectAll) {
            $this->selectedIds = $this->orders->pluck('id')->map(fn($id) => (string) $id)->toArray();
        } else {
            $this->selectedIds = [];
        }
    }

    /**
     * Toplu işlem modal'ını aç
     */
    public function openBulkModal(string $action): void
    {
        if (empty($this->selectedIds)) {
            session()->flash('error', 'Lütfen en az bir sipariş seçin.');
            return;
        }

        $this->bulkAction = $action;
        $this->showBulkModal = true;
    }

    /**
     * Toplu durum güncelle
     */
    public function bulkUpdateDurum(): void
    {
        if (empty($this->selectedIds)) return;

        $updateData = ['durum' => $this->bulkDurum];

        if ($this->bulkDurum === 'gonderildi') {
            $updateData['gonderim_tarihi'] = Carbon::today();
        }

        SupplyOrder::whereIn('id', $this->selectedIds)->update($updateData);

        $count = count($this->selectedIds);
        $this->selectedIds = [];
        $this->selectAll = false;
        $this->showBulkModal = false;

        session()->flash('success', "{$count} sipariş durumu güncellendi.");
    }

    /**
     * Toplu sebebiyet güncelle
     */
    public function bulkUpdateSebebiyet(): void
    {
        if (empty($this->selectedIds)) return;

        SupplyOrder::whereIn('id', $this->selectedIds)->update([
            'sebebiyet' => $this->bulkSebebiyet,
        ]);

        $count = count($this->selectedIds);
        $this->selectedIds = [];
        $this->selectAll = false;
        $this->showBulkModal = false;

        session()->flash('success', "{$count} sipariş sebebiyeti güncellendi.");
    }

    /**
     * Toplu sil
     */
    public function bulkDelete(): void
    {
        if (empty($this->selectedIds)) return;

        $count = count($this->selectedIds);
        SupplyOrder::whereIn('id', $this->selectedIds)->delete();

        $this->selectedIds = [];
        $this->selectAll = false;
        $this->showBulkModal = false;

        session()->flash('success', "{$count} sipariş silindi.");
    }

    /**
     * Excel olarak dışa aktar
     */
    public function exportExcel()
    {
        // use importları kontrol et
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Başlık satırı
        $headers = ['Sipariş No', 'Kayıt Tarihi', 'Müşteri', 'Telefon', 'Adres', 'İlçe', 'İl', 'Ürün', 'Adet', 'Söz Tarihi', 'Sebebiyet', 'Durum'];
        $sheet->fromArray($headers, null, 'A1');

        // Başlık stilini ayarla
        $headerStyle = [
            'font' => ['bold' => true],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']],
        ];
        $sheet->getStyle('A1:L1')->applyFromArray($headerStyle);

        // Filtrelenmiş verileri al (pagination olmadan)
        $orders = SupplyOrder::query()
            ->arama($this->search)
            ->durumFiltre($this->durumFiltre)
            ->sebebiyetFiltre($this->sebebiyetFiltre)
            ->when($this->gecikmeFiltre === 'gecikmis', fn($q) => $q->gecikmis())
            ->when($this->baslangicTarihi, fn($q) => $q->whereDate($this->tarihAlani, '>=', $this->baslangicTarihi))
            ->when($this->bitisTarihi, fn($q) => $q->whereDate($this->tarihAlani, '<=', $this->bitisTarihi))
            ->orderByRaw("FIELD(durum, 'bekliyor', 'uretim', 'paketleme', 'kargo', 'gonderildi')")
            ->get();

        // Verileri yaz
        $row = 2;
        foreach ($orders as $order) {
            $sheet->setCellValue('A' . $row, $order->siparis_no);
            $sheet->setCellValue('B' . $row, $order->kayit_tarihi?->format('d.m.Y'));
            $sheet->setCellValue('C' . $row, $order->musteri_adi);
            $sheet->setCellValue('D' . $row, $order->telefon);
            $sheet->setCellValue('E' . $row, $order->adres);
            $sheet->setCellValue('F' . $row, $order->ilce);
            $sheet->setCellValue('G' . $row, $order->il);
            $sheet->setCellValue('H' . $row, $order->urun_adi);
            $sheet->setCellValue('I' . $row, $order->adet);
            $sheet->setCellValue('J' . $row, $order->soz_tarihi?->format('d.m.Y'));
            $sheet->setCellValue('K' . $row, $order->sebebiyet_label);
            $sheet->setCellValue('L' . $row, $order->durum_label);
            $row++;
        }

        // Kolon genişliklerini otomatik ayarla
        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Dosyayı oluştur ve indir
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $filename = 'tedarik-raporu-' . now()->format('Y-m-d-His') . '.xlsx';
        $tempPath = storage_path('app/' . $filename);
        $writer->save($tempPath);

        return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Değeri temizle
     */
    protected function cleanValue($value): string
    {
        if ($value === null) return '';
        return trim((string) $value);
    }

    /**
     * Tarihi parse et
     */
    protected function parseDate($value): ?string
    {
        if (empty($value)) return null;

        try {
            // Excel serial date
            if (is_numeric($value)) {
                return Carbon::instance(
                    \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)
                )->format('Y-m-d');
            }

            // String tarih
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Excel AI kolonundaki durum değerini DB enum'una dönüştür
     */
    protected function mapExcelDurumToDbDurum(string $excelDurum): string
    {
        $excelDurum = mb_strtolower(trim($excelDurum));

        // Excel'deki değerler → DB enum değerleri
        $mapping = [
            'teslim edildi' => 'gonderildi',
            'kargolandi' => 'kargo',
            'kargolandı' => 'kargo',
            'kargoya verildi' => 'kargo',
            'paketlendi' => 'paketleme',
            'paketleniyor' => 'paketleme',
            'üretimde' => 'uretim',
            'üretiliyor' => 'uretim',
            'onaylandi' => 'bekliyor',
            'onaylandı' => 'bekliyor',
            'beklemede' => 'bekliyor',
        ];

        return $mapping[$excelDurum] ?? 'bekliyor';
    }

    /**
     * Excel AJ kolonundaki sebebiyet değerini DB enum'una dönüştür
     */
    protected function mapExcelSebebiyetToDbSebebiyet(string $excelSebebiyet): string
    {
        $excelSebebiyet = mb_strtolower(trim($excelSebebiyet));

        // Excel'deki değerler → DB enum değerleri
        $mapping = [
            'üretim' => 'uretim',
            'uretim' => 'uretim',
            'paketleme' => 'paketleme',
            'kargo' => 'kargo',
        ];

        return $mapping[$excelSebebiyet] ?? 'yok';
    }

    /**
     * Filtreleri sıfırla
     */
    public function resetFilters(): void
    {
        $this->search = '';
        $this->durumFiltre = 'hepsi';
        $this->sebebiyetFiltre = 'hepsi';
        $this->gecikmeFiltre = 'hepsi';
        $this->baslangicTarihi = null;
        $this->bitisTarihi = null;
        $this->tarihAlani = 'soz_tarihi';
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.supply-reports', [
            'orders' => $this->orders,
            'stats' => $this->stats,
            'durumOptions' => SupplyOrder::DURUM_OPTIONS,
            'sebebiyetOptions' => SupplyOrder::SEBEBIYET_OPTIONS,
        ]);
    }
}
