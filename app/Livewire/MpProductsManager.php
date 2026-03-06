<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Livewire\Attributes\Computed;
use App\Models\MpProduct;
use App\Services\MpProductImportService;
use Illuminate\Support\Facades\Auth;

class MpProductsManager extends Component
{
    use WithPagination, WithFileUploads;

    // ─── Kolon Tanımları ──────────────────────────────────
    public static array $allColumnDefs = [
        'urun'      => 'Ürün',
        'fiyat'     => 'Fiyat',
        'cogs'      => 'COGS',
        'kargo'     => 'Kargo',
        'stok'      => 'Stok',
        'kdv'       => 'KDV',
        'karlilik'  => 'ROI',
        'durum'     => 'Durum',
        'islem'     => 'İşlem',
    ];

    public static array $sortableColumns = [
        'urun'  => 'product_name',
        'fiyat' => 'sale_price',
        'cogs'  => 'cogs',
        'stok'  => 'stock_quantity',
        'kdv'   => 'vat_rate',
    ];

    // ─── Arama & Filtreleme ────────────────────────────────
    public string $search = '';
    public string $filterStatus = 'all';
    public string $filterCategory = 'all';
    public string $filterBrand = 'all';
    public string $filterStockLevel = 'all';
    public string $filterCostDefined = 'all';
    public string $sortField = 'product_name';
    public string $sortDirection = 'asc';
    public int $perPage = 25;
    public array $visibleColumns = ['urun','fiyat','cogs','kargo','stok','kdv','karlilik','durum','islem'];

    // ─── Import ────────────────────────────────────────────
    public $importFile;
    public bool $showImportModal = false;
    public bool $importing = false;
    public ?array $importResult = null;

    // ─── Ürün Düzenleme Modal ──────────────────────────────
    public bool $showEditModal = false;
    public ?int $editingId = null;
    public string $editTab = 'basic'; // basic, pricing, logistics, images

    // Form alanları
    public string $f_barcode = '';
    public string $f_stock_code = '';
    public string $f_product_name = '';
    public string $f_model_code = '';
    public string $f_brand = '';
    public string $f_category_name = '';
    public string $f_color = '';
    public string $f_size = '';
    public string $f_variant = '';
    public $f_cogs = 0;
    public $f_packaging_cost = 0;
    public $f_cargo_cost = 0;
    public $f_vat_rate = 10;
    public $f_market_price = 0;
    public $f_sale_price = 0;
    public $f_commission_rate = 0;
    public $f_stock_quantity = 0;
    public $f_desi = 0;
    public $f_pieces = 1;
    public string $f_status = 'active';
    public string $f_platforms = '';
    public string $f_description = '';

    // ─── Toplu İşlem ───────────────────────────────────────
    public array $selectedProducts = [];
    public bool $selectAll = false;

    // ─── Query String ──────────────────────────────────────
    protected $queryString = [
        'search'          => ['except' => ''],
        'filterStatus'    => ['except' => 'all'],
        'filterCategory'  => ['except' => 'all'],
        'filterBrand'     => ['except' => 'all'],
        'filterStockLevel' => ['except' => 'all'],
        'sortField'       => ['except' => 'product_name'],
        'sortDirection'   => ['except' => 'asc'],
    ];

    protected function rules(): array
    {
        return [
            'f_barcode'        => 'required|string|max:255',
            'f_product_name'   => 'nullable|string|max:500',
            'f_cogs'           => 'required|numeric|min:0',
            'f_packaging_cost' => 'required|numeric|min:0',
            'f_cargo_cost'     => 'required|numeric|min:0',
            'f_vat_rate'       => 'required|numeric|in:1,10,20',
            'f_stock_quantity' => 'required|integer|min:0',
            'f_sale_price'     => 'required|numeric|min:0',
            'f_market_price'   => 'required|numeric|min:0',
            'f_commission_rate' => 'required|numeric|min:0|max:100',
            'f_desi'           => 'required|numeric|min:0',
            'f_pieces'         => 'required|integer|min:1',
            'f_status'         => 'required|in:active,out_of_stock,pending,suspended',
        ];
    }

    // ═══════════════════════════════════════════════════════════
    //  LIFECYCLE
    // ═══════════════════════════════════════════════════════════

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingFilterStatus()
    {
        $this->resetPage();
    }

    public function updatingFilterCategory()
    {
        $this->resetPage();
    }

    public function updatingFilterBrand()
    {
        $this->resetPage();
    }

    public function updatingFilterStockLevel()
    {
        $this->resetPage();
    }

    public function updatingPerPage()
    {
        $this->resetPage();
    }

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedProducts = $this->products->pluck('id')->map(fn($id) => (string) $id)->toArray();
        } else {
            $this->selectedProducts = [];
        }
    }

    // ═══════════════════════════════════════════════════════════
    //  COMPUTED PROPERTIES
    // ═══════════════════════════════════════════════════════════

    #[Computed]
    public function products()
    {
        return MpProduct::where('user_id', Auth::id() ?? 1)
            ->search($this->search)
            ->byStatus($this->filterStatus)
            ->byCategory($this->filterCategory)
            ->byBrand($this->filterBrand)
            ->byStockLevel($this->filterStockLevel)
            ->when($this->filterCostDefined === 'yes', fn($q) => $q->withCost())
            ->when($this->filterCostDefined === 'no', fn($q) => $q->withoutCost())
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);
    }

    #[Computed]
    public function stats()
    {
        $userId = Auth::id() ?? 1;
        $base = MpProduct::where('user_id', $userId);

        $total       = (clone $base)->count();
        $active      = (clone $base)->where('status', 'active')->count();
        $outOfStock  = (clone $base)->where('stock_quantity', '<=', 0)->count();
        $withCost    = (clone $base)->where('cogs', '>', 0)->count();
        $withoutCost = $total - $withCost;
        $totalStock  = (clone $base)->sum('stock_quantity');
        $avgCogs     = (clone $base)->where('cogs', '>', 0)->avg('cogs');
        $stockValue  = (clone $base)->selectRaw('SUM(stock_quantity * cogs) as value')->value('value');

        return [
            'total'        => $total,
            'active'       => $active,
            'out_of_stock' => $outOfStock,
            'with_cost'    => $withCost,
            'without_cost' => $withoutCost,
            'total_stock'  => (int) $totalStock,
            'avg_cogs'     => $avgCogs ? round($avgCogs, 2) : 0,
            'stock_value'  => round((float) $stockValue, 2),
        ];
    }

    #[Computed]
    public function categories()
    {
        return MpProduct::where('user_id', Auth::id() ?? 1)
            ->whereNotNull('category_name')
            ->where('category_name', '!=', '')
            ->distinct()
            ->pluck('category_name')
            ->sort()
            ->values();
    }

    #[Computed]
    public function brands()
    {
        return MpProduct::where('user_id', Auth::id() ?? 1)
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->distinct()
            ->pluck('brand')
            ->sort()
            ->values();
    }

    // ═══════════════════════════════════════════════════════════
    //  SIRALAMA
    // ═══════════════════════════════════════════════════════════

    public function sortBy(string $field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function sortTable(string $columnKey)
    {
        $dbCol = static::$sortableColumns[$columnKey] ?? null;
        if (!$dbCol) return;

        if ($this->sortField === $dbCol) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $dbCol;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function toggleColumn(string $column)
    {
        if (in_array($column, $this->visibleColumns)) {
            $this->visibleColumns = array_values(array_diff($this->visibleColumns, [$column]));
        } else {
            $this->visibleColumns[] = $column;
        }
    }

    // ═══════════════════════════════════════════════════════════
    //  İMPORT / EXPORT
    // ═══════════════════════════════════════════════════════════

    public function openImportModal()
    {
        $this->importFile = null;
        $this->importResult = null;
        $this->importing = false;
        $this->showImportModal = true;
    }

    public function closeImportModal()
    {
        $this->showImportModal = false;
        $this->importFile = null;
        $this->importResult = null;
        $this->importing = false;
    }

    public function importExcel()
    {
        $this->validate([
            'importFile' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        $this->importing = true;

        try {
            $service = new MpProductImportService();
            $this->importResult = $service->import($this->importFile);
        } catch (\Throwable $e) {
            $this->importResult = [
                'success' => false,
                'message' => 'Hata: ' . $e->getMessage(),
                'imported' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => [],
            ];
        }

        $this->importing = false;
        $this->importFile = null;

        // Sayfa verilerini yenile
        unset($this->products, $this->stats, $this->categories, $this->brands);
    }

    public function exportExcel()
    {
        $service = new MpProductImportService();
        return $service->exportProducts([
            'search'      => $this->search,
            'status'      => $this->filterStatus !== 'all' ? $this->filterStatus : null,
            'category'    => $this->filterCategory !== 'all' ? $this->filterCategory : null,
            'brand'       => $this->filterBrand !== 'all' ? $this->filterBrand : null,
            'stock_level' => $this->filterStockLevel !== 'all' ? $this->filterStockLevel : null,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    //  CRUD — ÜRÜN DÜZENLEME
    // ═══════════════════════════════════════════════════════════

    public function openCreateModal()
    {
        $this->resetForm();
        $this->editTab = 'basic';
        $this->showEditModal = true;
    }

    public function editProduct(int $id)
    {
        $product = MpProduct::where('user_id', Auth::id() ?? 1)->findOrFail($id);

        $this->editingId       = $product->id;
        $this->f_barcode       = $product->barcode ?? '';
        $this->f_stock_code    = $product->stock_code ?? '';
        $this->f_product_name  = $product->product_name ?? '';
        $this->f_model_code    = $product->model_code ?? '';
        $this->f_brand         = $product->brand ?? '';
        $this->f_category_name = $product->category_name ?? '';
        $this->f_color         = $product->color ?? '';
        $this->f_size          = $product->size ?? '';
        $this->f_variant       = $product->variant ?? '';
        $this->f_cogs          = $product->cogs;
        $this->f_packaging_cost = $product->packaging_cost;
        $this->f_cargo_cost    = $product->cargo_cost;
        $this->f_vat_rate      = $product->vat_rate;
        $this->f_market_price  = $product->market_price;
        $this->f_sale_price    = $product->sale_price;
        $this->f_commission_rate = $product->commission_rate;
        $this->f_stock_quantity = $product->stock_quantity;
        $this->f_desi          = $product->desi;
        $this->f_pieces        = $product->pieces;
        $this->f_status        = $product->status ?? 'active';
        $this->f_platforms     = $product->platforms ?? '';
        $this->f_description   = $product->description ?? '';

        $this->editTab = 'basic';
        $this->showEditModal = true;
    }

    public function saveProduct()
    {
        $this->validate();

        $data = [
            'user_id'         => Auth::id() ?? 1,
            'barcode'         => $this->f_barcode,
            'stock_code'      => $this->f_stock_code ?: null,
            'product_name'    => $this->f_product_name ?: null,
            'model_code'      => $this->f_model_code ?: null,
            'brand'           => $this->f_brand ?: null,
            'category_name'   => $this->f_category_name ?: null,
            'color'           => $this->f_color ?: null,
            'size'            => $this->f_size ?: null,
            'variant'         => $this->f_variant ?: null,
            'cogs'            => $this->f_cogs,
            'packaging_cost'  => $this->f_packaging_cost,
            'cargo_cost'      => $this->f_cargo_cost,
            'vat_rate'        => $this->f_vat_rate,
            'market_price'    => $this->f_market_price,
            'sale_price'      => $this->f_sale_price,
            'commission_rate' => $this->f_commission_rate,
            'stock_quantity'  => $this->f_stock_quantity,
            'desi'            => $this->f_desi,
            'pieces'          => $this->f_pieces,
            'status'          => $this->f_status,
            'platforms'       => $this->f_platforms ?: null,
            'description'     => $this->f_description ?: null,
            'import_source'   => 'manual_form',
        ];

        if ($this->editingId) {
            $product = MpProduct::where('user_id', Auth::id() ?? 1)->findOrFail($this->editingId);
            $product->update($data);
            session()->flash('success', 'Ürün başarıyla güncellendi.');
        } else {
            MpProduct::create($data);
            session()->flash('success', 'Yeni ürün başarıyla eklendi.');
        }

        $this->closeEditModal();
        unset($this->products, $this->stats, $this->categories, $this->brands);
    }

    public function deleteProduct(int $id)
    {
        MpProduct::where('user_id', Auth::id() ?? 1)->findOrFail($id)->delete();
        session()->flash('success', 'Ürün başarıyla silindi.');
        unset($this->products, $this->stats);
    }

    public function duplicateProduct(int $id)
    {
        $original = MpProduct::where('user_id', Auth::id() ?? 1)->findOrFail($id);
        $clone = $original->replicate();
        $clone->product_name = $original->product_name . ' (Kopya)';
        $clone->save();
        session()->flash('success', 'Ürün başarıyla çoğaltıldı.');
        unset($this->products, $this->stats);
    }

    /**
     * Inline fiyat güncelleme (tablo üzerinden)
     */
    public function updateSalePrice(int $id, $newPrice)
    {
        $newPrice = max(0, (float) $newPrice);
        $product = MpProduct::where('user_id', Auth::id() ?? 1)->findOrFail($id);
        $product->update(['sale_price' => $newPrice]);
    }

    public function closeEditModal()
    {
        $this->showEditModal = false;
        $this->resetForm();
    }

    private function resetForm()
    {
        $this->editingId = null;
        $this->f_barcode = '';
        $this->f_stock_code = '';
        $this->f_product_name = '';
        $this->f_model_code = '';
        $this->f_brand = '';
        $this->f_category_name = '';
        $this->f_color = '';
        $this->f_size = '';
        $this->f_variant = '';
        $this->f_cogs = 0;
        $this->f_packaging_cost = 0;
        $this->f_cargo_cost = 0;
        $this->f_vat_rate = 10;
        $this->f_market_price = 0;
        $this->f_sale_price = 0;
        $this->f_commission_rate = 0;
        $this->f_stock_quantity = 0;
        $this->f_desi = 0;
        $this->f_pieces = 1;
        $this->f_status = 'active';
        $this->f_platforms = '';
        $this->f_description = '';
    }

    // ═══════════════════════════════════════════════════════════
    //  TOPLU İŞLEMLER
    // ═══════════════════════════════════════════════════════════

    public function bulkDelete()
    {
        if (empty($this->selectedProducts)) return;

        MpProduct::where('user_id', Auth::id() ?? 1)
            ->whereIn('id', $this->selectedProducts)
            ->delete();

        $count = count($this->selectedProducts);
        $this->selectedProducts = [];
        $this->selectAll = false;
        session()->flash('success', "{$count} ürün başarıyla silindi.");
        unset($this->products, $this->stats);
    }

    public function bulkUpdateStatus(string $status)
    {
        if (empty($this->selectedProducts)) return;

        MpProduct::where('user_id', Auth::id() ?? 1)
            ->whereIn('id', $this->selectedProducts)
            ->update(['status' => $status]);

        $count = count($this->selectedProducts);
        $this->selectedProducts = [];
        $this->selectAll = false;
        session()->flash('success', "{$count} ürünün durumu güncellendi.");
        unset($this->products, $this->stats);
    }

    // ═══════════════════════════════════════════════════════════
    //  FİLTRELERİ SIFIRLA
    // ═══════════════════════════════════════════════════════════

    public function resetFilters()
    {
        $this->search = '';
        $this->filterStatus = 'all';
        $this->filterCategory = 'all';
        $this->filterBrand = 'all';
        $this->filterStockLevel = 'all';
        $this->filterCostDefined = 'all';
        $this->resetPage();
    }

    // ═══════════════════════════════════════════════════════════
    //  RENDER
    // ═══════════════════════════════════════════════════════════

    public function render()
    {
        return view('livewire.mp-products-manager');
    }
}
