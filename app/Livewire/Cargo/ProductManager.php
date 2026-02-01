<?php

namespace App\Livewire\Cargo;

use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Ürün/Desi Yönetim Bileşeni
 * 
 * Tab 1: Ürün ve Desi Bilgileri
 * - Ürün CRUD işlemleri
 * - Excel import/export
 * - Inline düzenleme
 */
class ProductManager extends Component
{
    use WithFileUploads, WithPagination;

    // Arama ve filtreleme
    public string $search = '';
    public string $filterCategory = '';
    public string $sortField = 'stok_kodu';
    public string $sortDirection = 'asc';

    // Yeni ürün formu
    public array $newProduct = [
        'stok_kodu' => '',
        'urun_adi' => '',
        'parca' => 1,
        'desi' => 0,
        'tutar' => 0,
    ];
    public bool $showAddForm = false;

    // Düzenleme
    public ?int $editingId = null;
    public array $editingProduct = [];

    // Import
    public $importFile;
    public bool $showImportModal = false;
    public array $importPreview = [];
    public int $importCount = 0;

    // Silme onayı
    public bool $showDeleteModal = false;
    public ?int $deletingId = null;

    // Mesajlar
    public string $message = '';
    public bool $isProcessing = false;
    public string $messageType = 'info';

    protected $queryString = [
        'search' => ['except' => ''],
        'filterCategory' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    protected function rules()
    {
        return [
            'newProduct.stok_kodu' => 'required|string|max:30|unique:products,stok_kodu',
            'newProduct.urun_adi' => 'required|string|max:255',
            'newProduct.parca' => 'required|integer|min:1|max:20',
            'newProduct.desi' => 'required|numeric|min:0|max:1000',
            'newProduct.tutar' => 'required|numeric|min:0',
        ];
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedFilterCategory()
    {
        $this->resetPage();
    }

    /**
     * Tüm ürünleri getir (paginated + filtered)
     */
    #[Computed]
    public function products()
    {
        $query = Product::query();

        // Arama
        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('stok_kodu', 'like', "%{$this->search}%")
                  ->orWhere('urun_adi', 'like', "%{$this->search}%");
            });
        }

        // Kategori filtresi
        if (!empty($this->filterCategory)) {
            $query->where('kategori', $this->filterCategory);
        }

        // Sıralama
        $query->orderBy($this->sortField, $this->sortDirection);

        return $query->paginate(20);
    }

    /**
     * Benzersiz kategorileri getir
     */
    #[Computed]
    public function categories()
    {
        return Product::distinct()
            ->whereNotNull('kategori')
            ->pluck('kategori')
            ->sort()
            ->values();
    }

    /**
     * Toplam istatistikler
     */
    #[Computed]
    public function stats()
    {
        return [
            'total' => Product::count(),
            'active' => Product::where('is_active', true)->count(),
            'categories' => Product::distinct()->whereNotNull('kategori')->count('kategori'),
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
            $this->sortDirection = 'asc';
        }
    }

    /**
     * Yeni ürün ekle
     */
    public function addProduct()
    {
        $this->validate();

        try {
            Product::create([
                'stok_kodu' => $this->newProduct['stok_kodu'],
                'urun_adi' => $this->newProduct['urun_adi'],
                'parca' => $this->newProduct['parca'],
                'desi' => $this->newProduct['desi'],
                'tutar' => $this->newProduct['tutar'],
                'updated_by' => auth()->id(),
            ]);

            $this->resetNewProduct();
            $this->showAddForm = false;
            $this->showMessage('Ürün başarıyla eklendi.', 'success');

        } catch (\Exception $e) {
            Log::error('ProductManager: Ürün ekleme hatası', ['error' => $e->getMessage()]);
            $this->showMessage('Hata: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Düzenleme moduna geç
     */
    public function startEdit(int $id)
    {
        $product = Product::find($id);
        if (!$product) return;

        $this->editingId = $id;
        $this->editingProduct = [
            'stok_kodu' => $product->stok_kodu,
            'urun_adi' => $product->urun_adi,
            'parca' => $product->parca,
            'desi' => $product->desi,
            'tutar' => $product->tutar,
        ];
    }

    /**
     * Düzenlemeyi kaydet
     */
    public function saveEdit()
    {
        if (!$this->editingId) return;

        try {
            $product = Product::find($this->editingId);
            if (!$product) return;

            $product->update([
                'stok_kodu' => $this->editingProduct['stok_kodu'],
                'urun_adi' => $this->editingProduct['urun_adi'],
                'parca' => $this->editingProduct['parca'],
                'desi' => $this->editingProduct['desi'],
                'tutar' => $this->editingProduct['tutar'],
                'updated_by' => auth()->id(),
            ]);

            $this->cancelEdit();
            $this->showMessage('Ürün güncellendi.', 'success');

        } catch (\Exception $e) {
            Log::error('ProductManager: Ürün güncelleme hatası', ['error' => $e->getMessage()]);
            $this->showMessage('Hata: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Düzenlemeyi iptal et
     */
    public function cancelEdit()
    {
        $this->editingId = null;
        $this->editingProduct = [];
    }

    /**
     * Silme onayı göster
     */
    public function confirmDelete(int $id)
    {
        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    /**
     * Ürünü sil
     */
    public function deleteProduct()
    {
        if (!$this->deletingId) return;

        try {
            Product::destroy($this->deletingId);
            $this->showMessage('Ürün silindi.', 'success');
        } catch (\Exception $e) {
            $this->showMessage('Silme hatası: ' . $e->getMessage(), 'error');
        }

        $this->showDeleteModal = false;
        $this->deletingId = null;
    }

    /**
     * Import modalını aç
     */
    public function openImportModal()
    {
        $this->showImportModal = true;
        $this->importPreview = [];
        $this->importCount = 0;
        $this->importFile = null;
    }

    /**
     * Import dosyası yüklendiğinde önizleme
     */
    public function updatedImportFile()
    {
        if (!$this->importFile) return;

        try {
            $spreadsheet = IOFactory::load($this->importFile->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray(null, true, true, true);

            $headers = array_shift($data);
            $this->importPreview = [];
            $count = 0;

            foreach ($data as $row) {
                if ($count >= 5) break; // Sadece ilk 5 satırı göster

                $item = [];
                foreach ($headers as $col => $header) {
                    $key = $this->mapImportHeader($header);
                    if ($key) {
                        $item[$key] = $row[$col] ?? '';
                    }
                }

                if (!empty($item['stok_kodu'])) {
                    $this->importPreview[] = $item;
                    $count++;
                }
            }

            $this->importCount = count($data);

        } catch (\Exception $e) {
            $this->showMessage('Dosya okuma hatası: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Import header eşleştirme
     */
    protected function mapImportHeader(?string $header): ?string
    {
        if (!$header) return null;

        $header = mb_strtolower(trim($header), 'UTF-8');

        $map = [
            'stok kodu' => 'stok_kodu',
            'stokkodu' => 'stok_kodu',
            'sku' => 'stok_kodu',
            'ürün adı' => 'urun_adi',
            'urun adi' => 'urun_adi',
            'ürün' => 'urun_adi',
            'parça' => 'parca',
            'parca' => 'parca',
            'koli' => 'parca',
            'desi' => 'desi',
            'hacim' => 'desi',
            'tutar' => 'tutar',
            'fiyat' => 'tutar',
            'ücret' => 'tutar',
        ];

        return $map[$header] ?? null;
    }

    /**
     * Import işlemini gerçekleştir
     */
    public function executeImport()
    {
        if (!$this->importFile) {
            $this->showMessage('Dosya seçilmedi.', 'error');
            return;
        }

        $this->isProcessing = true; // Loading state (UI'da kullanılabilir)

        try {
            $spreadsheet = IOFactory::load($this->importFile->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);
            
            // Header analizi
            $headers = array_shift($rows);
            $headerMap = [];
            foreach ($headers as $col => $header) {
                $key = $this->mapImportHeader($header);
                if ($key) {
                    $headerMap[$key] = $col;
                }
            }

            if (!isset($headerMap['stok_kodu'])) {
                $this->showMessage('Stok Kodu kolonu bulunamadı.', 'error');
                return;
            }

            $toInsert = [];
            $upsertData = [];
            $stokKodlari = [];
            $processedCount = 0;
            $errors = 0;
            $userId = auth()->id();
            $now = now();

            foreach ($rows as $row) {
                $stokKodu = trim($row[$headerMap['stok_kodu'] ?? ''] ?? '');
                if (empty($stokKodu)) continue;

                try {
                    // Veriyi hazırla
                    $data = [
                        'stok_kodu' => $stokKodu,
                        'urun_adi' => trim($row[$headerMap['urun_adi'] ?? ''] ?? $stokKodu),
                        'parca' => max(1, (int) ($row[$headerMap['parca'] ?? ''] ?? 1)),
                        'desi' => max(0, (float) ($row[$headerMap['desi'] ?? ''] ?? 0)),
                        'tutar' => max(0, (float) ($row[$headerMap['tutar'] ?? ''] ?? 0)),
                        'updated_by' => $userId,
                        //'created_at' => $now, // Upsert için gerekli olabilir ama Eloquent halleder mi? Upsert raw query'dir.
                        //'updated_at' => $now,
                    ];

                    // Upsert için diziye ekle
                    // Key olarak stok kodunu kullanarak unique olmasını sağla (Excel içindeki tekrarları sonuncusu ezer)
                    $upsertData[$stokKodu] = $data;
                    $processedCount++;

                } catch (\Exception $e) {
                    $errors++;
                }
            }

            if (empty($upsertData)) {
                $this->showMessage('İşlenecek veri bulunamadı.', 'warning');
                return;
            }

            // Bulk Upsert İşlemi
            // uniqueBy: ['stok_kodu'], update: ['urun_adi', 'parca', 'desi', 'tutar', 'updated_by']
            Product::upsert(
                array_values($upsertData), 
                ['stok_kodu'], 
                ['urun_adi', 'parca', 'desi', 'tutar', 'updated_by']
            );

            $this->showImportModal = false;
            $this->importFile = null;
            $this->importPreview = [];

            $this->showMessage(
                "İşlem tamamlandı. Toplam {$processedCount} satır işlendi." . ($errors > 0 ? " ({$errors} hata)" : ""),
                'success'
            );

        } catch (\Exception $e) {
            Log::error('ProductManager: Import hatası', ['error' => $e->getMessage()]);
            $this->showMessage('Import hatası: ' . $e->getMessage(), 'error');
        } finally {
            $this->isProcessing = false;
        }
    }

    /**
     * Excel'e export
     */
    public function exportToExcel()
    {
        try {
            $products = Product::orderBy('stok_kodu')->get();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Ürün Listesi');

            // Headers
            $headers = ['Stok Kodu', 'Ürün Adı', 'Parça', 'Desi', 'Tutar', 'Kategori'];
            $sheet->fromArray($headers, null, 'A1');

            // Style headers
            $sheet->getStyle('A1:F1')->getFont()->setBold(true);
            $sheet->getStyle('A1:F1')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('4F46E5');
            $sheet->getStyle('A1:F1')->getFont()->getColor()->setRGB('FFFFFF');

            // Data
            $row = 2;
            foreach ($products as $product) {
                $sheet->setCellValue('A' . $row, $product->stok_kodu);
                $sheet->setCellValue('B' . $row, $product->urun_adi);
                $sheet->setCellValue('C' . $row, $product->parca);
                $sheet->setCellValue('D' . $row, $product->desi);
                $sheet->setCellValue('E' . $row, $product->tutar);
                $sheet->setCellValue('F' . $row, $product->kategori);
                $row++;
            }

            // Auto width
            foreach (range('A', 'F') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Save
            $fileName = 'urun_listesi_' . now()->format('Y-m-d_H-i') . '.xlsx';
            $tempPath = storage_path('app/temp/' . $fileName);

            if (!is_dir(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save($tempPath);

            return response()->download($tempPath, $fileName)->deleteFileAfterSend();

        } catch (\Exception $e) {
            Log::error('ProductManager: Export hatası', ['error' => $e->getMessage()]);
            $this->showMessage('Export hatası: ' . $e->getMessage(), 'error');
            return null;
        }
    }

    /**
     * Yeni ürün formunu sıfırla
     */
    protected function resetNewProduct()
    {
        $this->newProduct = [
            'stok_kodu' => '',
            'urun_adi' => '',
            'parca' => 1,
            'desi' => 0,
            'tutar' => 0,
        ];
        $this->resetValidation();
    }

    /**
     * Mesaj göster
     */
    protected function showMessage(string $message, string $type = 'info')
    {
        $this->message = $message;
        $this->messageType = $type;

        $this->dispatch('message-shown');
    }

    /**
     * Mesajı temizle
     */
    public function clearMessage()
    {
        $this->message = '';
    }

    public function render()
    {
        return view('livewire.cargo.product-manager');
    }
}
