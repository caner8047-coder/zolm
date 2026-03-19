<?php

namespace App\Livewire\Cargo;

use App\Models\Product;
use App\Models\ProductReferenceHistory;
use App\Services\ExcelService;
use App\Services\MpSettingsService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use PhpOffice\PhpSpreadsheet\IOFactory;

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

    public array $visibleColumns = ['stok_kodu', 'urun_adi', 'parca', 'desi', 'tutar', 'kategori'];
    public static array $sortableColumns = [
        'stok_kodu' => 'stok_kodu',
        'urun_adi' => 'urun_adi',
        'parca' => 'parca',
        'desi' => 'desi',
        'tutar' => 'tutar',
        'kategori' => 'kategori',
    ];
    public static array $allColumnDefs = [
        'stok_kodu' => 'Stok Kodu',
        'urun_adi' => 'Ürün Adı',
        'parca' => 'Parça',
        'desi' => 'Desi',
        'tutar' => 'Tutar',
        'kategori' => 'Kategori',
    ];

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

    public function mount()
    {
        $this->visibleColumns = $this->normalizeVisibleColumns(
            app(MpSettingsService::class)->getArray('cargo_reports.product_manager.visible_columns', $this->visibleColumns)
        );
    }

    public function updatedSearch()
    {
        $this->resetPage('productsPage');
    }

    public function updatedFilterCategory()
    {
        $this->resetPage('productsPage');
    }

    /**
     * Tüm ürünleri getir (paginated + filtered)
     */
    #[Computed]
    public function products()
    {
        $query = Product::query();
        $searchTerm = trim($this->search);

        // Arama
        if ($searchTerm !== '') {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('stok_kodu', 'like', "%{$searchTerm}%")
                  ->orWhere('urun_adi', 'like', "%{$searchTerm}%")
                  ->orWhere('kategori', 'like', "%{$searchTerm}%");
            });
        }

        // Kategori filtresi
        if (!empty($this->filterCategory)) {
            $query->where('kategori', $this->filterCategory);
        }

        // Sıralama
        $query->orderBy($this->sortField, $this->sortDirection);

        return $query->paginate(20, ['*'], 'productsPage');
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

    #[Computed]
    public function recentHistory()
    {
        if (!$this->supportsReferenceHistory()) {
            return collect();
        }

        return ProductReferenceHistory::query()
            ->with('changedByUser')
            ->latest()
            ->limit(8)
            ->get();
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
            $this->sortDirection = 'asc';
        }

        $this->resetPage('productsPage');
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

        app(MpSettingsService::class)->set('cargo_reports.product_manager.visible_columns', $this->visibleColumns);
    }

    /**
     * Yeni ürün ekle
     */
    public function addProduct()
    {
        $this->validate();

        try {
            $product = Product::create([
                'stok_kodu' => $this->newProduct['stok_kodu'],
                'urun_adi' => $this->newProduct['urun_adi'],
                'parca' => $this->newProduct['parca'],
                'desi' => $this->newProduct['desi'],
                'tutar' => $this->newProduct['tutar'],
                'updated_by' => auth()->id(),
            ]);

            $this->logProductHistory($product, null, 'manual_create', 'Manuel ürün oluşturuldu.');

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

            $before = $product->toReferenceSnapshot();

            $product->update([
                'stok_kodu' => $this->editingProduct['stok_kodu'],
                'urun_adi' => $this->editingProduct['urun_adi'],
                'parca' => $this->editingProduct['parca'],
                'desi' => $this->editingProduct['desi'],
                'tutar' => $this->editingProduct['tutar'],
                'updated_by' => auth()->id(),
            ]);

            $this->logProductHistory($product->fresh(), $before, 'manual_update', 'Ürün referansı güncellendi.');

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
            $product = Product::find($this->deletingId);
            $before = $product?->toReferenceSnapshot();
            Product::destroy($this->deletingId);
            if ($product && $before) {
                $this->logProductHistory($product, $before, 'manual_delete', 'Ürün referansı silindi.', null);
            }
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
            $stokKodlari = array_keys($upsertData);
            $existingProducts = Product::query()
                ->whereIn('stok_kodu', $stokKodlari)
                ->get()
                ->keyBy('stok_kodu');

            Product::upsert(
                array_values($upsertData),
                ['stok_kodu'],
                ['urun_adi', 'parca', 'desi', 'tutar', 'updated_by']
            );

            $freshProducts = Product::query()
                ->whereIn('stok_kodu', $stokKodlari)
                ->get()
                ->keyBy('stok_kodu');

            foreach ($freshProducts as $stokKodu => $product) {
                $existingProduct = $existingProducts->get($stokKodu);
                $before = $existingProduct?->toReferenceSnapshot();
                $after = $product->toReferenceSnapshot();

                if ($before === null) {
                    $this->logProductHistory($product, null, 'excel_import', 'Excel import ile yeni ürün eklendi.');
                    continue;
                }

                if ($before !== $after) {
                    $this->logProductHistory($product, $before, 'excel_import', 'Excel import ile referans güncellendi.');
                }
            }

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
            $fileName = 'urun_listesi_' . now()->format('Y-m-d_H-i') . '.xlsx';
            $tempPath = storage_path('app/temp/' . $fileName);

            if (!is_dir(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }

            app(ExcelService::class)->exportToXlsx([
                [
                    'name' => 'Ürün Listesi',
                    'data' => collect($products)->map(fn (Product $product) => [
                        'Stok Kodu' => $product->stok_kodu,
                        'Ürün Adı' => $product->urun_adi,
                        'Parça' => (int) $product->parca,
                        'Desi' => (float) $product->desi,
                        'Tutar' => (float) $product->tutar,
                        'Kategori' => $product->kategori,
                    ])->all(),
                ],
            ], $tempPath);

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

    protected function logProductHistory(?Product $product, ?array $beforeSnapshot, string $source, ?string $note = null, ?array $afterSnapshot = null): void
    {
        if (!$this->supportsReferenceHistory()) {
            return;
        }

        ProductReferenceHistory::create([
            'product_id' => $product?->id,
            'stok_kodu' => $product?->stok_kodu ?? ($beforeSnapshot['stok_kodu'] ?? ''),
            'change_source' => $source,
            'note' => $note,
            'previous_snapshot' => $beforeSnapshot,
            'new_snapshot' => $afterSnapshot ?? $product?->toReferenceSnapshot(),
            'changed_by' => auth()->id(),
        ]);
    }

    protected function normalizeVisibleColumns(array $columns): array
    {
        $allowed = array_keys(static::$allColumnDefs);
        $normalized = array_values(array_intersect($allowed, $columns));

        return $normalized !== [] ? $normalized : ['stok_kodu', 'urun_adi', 'parca', 'desi', 'tutar', 'kategori'];
    }

    protected function supportsReferenceHistory(): bool
    {
        static $supportsReferenceHistory;

        if ($supportsReferenceHistory === null) {
            $supportsReferenceHistory = Schema::hasTable('product_reference_histories');
        }

        return $supportsReferenceHistory;
    }
}
