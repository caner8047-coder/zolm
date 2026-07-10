<?php

namespace App\Livewire\Accounting;

use App\Models\MpProduct;
use Livewire\Component;
use Livewire\WithPagination;

class Products extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterStatus = '';
    public string $filterCritical = '';
    public string $sortField = 'product_name';
    public string $sortDirection = 'asc';

    public bool $showForm = false;
    public bool $isEditing = false;
    public ?int $editingProductId = null;

    public string $barcode = '';
    public string $stockCode = '';
    public string $productName = '';
    public string $brand = '';
    public string $categoryName = '';
    public string $unitName = 'adet';
    public float $vatRate = 20.0;
    public float $cogs = 0.0;
    public float $salePrice = 0.0;
    public int $stockQuantity = 0;
    public ?int $criticalStockThreshold = null;
    public string $status = 'active';
    public string $description = '';

    public string $message = '';
    public string $messageType = 'success';

    protected $queryString = [
        'search' => ['except' => ''],
        'filterStatus' => ['except' => ''],
        'filterCritical' => ['except' => ''],
        'sortField' => ['except' => 'product_name'],
        'sortDirection' => ['except' => 'asc'],
    ];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterCritical(): void
    {
        $this->resetPage();
    }

    public function sortTable(string $field): void
    {
        $allowed = ['product_name', 'stock_code', 'barcode', 'category_name', 'sale_price', 'stock_quantity', 'status'];
        if (! in_array($field, $allowed, true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
            return;
        }

        $this->sortField = $field;
        $this->sortDirection = 'asc';
    }

    public function openCreateForm(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function editProduct(int $productId): void
    {
        $product = MpProduct::where('user_id', auth()->id())->findOrFail($productId);

        $this->editingProductId = $product->id;
        $this->isEditing = true;
        $this->showForm = true;
        $this->barcode = (string) $product->barcode;
        $this->stockCode = (string) ($product->stock_code ?? '');
        $this->productName = (string) ($product->product_name ?? '');
        $this->brand = (string) ($product->brand ?? '');
        $this->categoryName = (string) ($product->category_name ?? '');
        $this->unitName = (string) ($product->unit_name ?? 'adet');
        $this->vatRate = (float) $product->vat_rate;
        $this->cogs = (float) $product->cogs;
        $this->salePrice = (float) $product->sale_price;
        $this->stockQuantity = (int) $product->stock_quantity;
        $this->criticalStockThreshold = $product->critical_stock_threshold !== null ? (int) $product->critical_stock_threshold : null;
        $this->status = (string) ($product->status ?? 'active');
        $this->description = (string) ($product->description ?? '');
    }

    public function saveProduct(): void
    {
        $userId = (int) auth()->id();

        $this->validate([
            'barcode' => [
                'required',
                'string',
                'max:191',
                function ($attribute, $value, $fail) use ($userId): void {
                    $exists = MpProduct::where('user_id', $userId)
                        ->where('barcode', $value)
                        ->when($this->editingProductId, fn ($q) => $q->where('id', '!=', $this->editingProductId))
                        ->exists();

                    if ($exists) {
                        $fail('Bu barkod bu kullanıcı için zaten kayıtlı.');
                    }
                },
            ],
            'stockCode' => 'required|string|max:100',
            'productName' => 'required|string|max:255',
            'brand' => 'nullable|string|max:255',
            'categoryName' => 'nullable|string|max:255',
            'unitName' => 'required|string|max:30',
            'vatRate' => 'required|numeric|min:0|max:100',
            'cogs' => 'required|numeric|min:0',
            'salePrice' => 'required|numeric|min:0',
            'stockQuantity' => 'required|integer|min:0',
            'criticalStockThreshold' => 'nullable|integer|min:0',
            'status' => 'required|in:active,out_of_stock,pending,suspended',
            'description' => 'nullable|string|max:2000',
        ], [
            'barcode.required' => 'Barkod zorunludur.',
            'stockCode.required' => 'SKU / stok kodu zorunludur.',
            'productName.required' => 'Ürün adı zorunludur.',
            'unitName.required' => 'Birim zorunludur.',
        ]);

        $payload = [
            'user_id' => $userId,
            'barcode' => trim($this->barcode),
            'stock_code' => trim($this->stockCode),
            'product_name' => trim($this->productName),
            'brand' => $this->blankToNull($this->brand),
            'category_name' => $this->blankToNull($this->categoryName),
            'unit_name' => trim($this->unitName),
            'vat_rate' => $this->vatRate,
            'cogs' => $this->cogs,
            'sale_price' => $this->salePrice,
            'stock_quantity' => $this->stockQuantity,
            'critical_stock_threshold' => $this->criticalStockThreshold,
            'status' => $this->status,
            'description' => $this->blankToNull($this->description),
            'import_source' => $this->isEditing ? 'erp_manual_update' : 'erp_manual',
        ];

        if ($this->isEditing && $this->editingProductId) {
            $product = MpProduct::where('user_id', $userId)->findOrFail($this->editingProductId);
            $product->update($payload);
        } else {
            MpProduct::create($payload);
        }

        $this->message = $this->isEditing ? 'Ürün kartı güncellendi.' : 'Ürün kartı oluşturuldu.';
        $this->messageType = 'success';
        $this->resetForm();
    }

    public function markPassive(int $productId): void
    {
        $product = MpProduct::where('user_id', auth()->id())->findOrFail($productId);
        $product->update(['status' => 'suspended']);
        $this->message = 'Ürün satıştan pasife alındı.';
        $this->messageType = 'success';
    }

    public function markActive(int $productId): void
    {
        $product = MpProduct::where('user_id', auth()->id())->findOrFail($productId);
        $product->update(['status' => 'active']);
        $this->message = 'Ürün tekrar aktif edildi.';
        $this->messageType = 'success';
    }

    public function resetForm(): void
    {
        $this->showForm = false;
        $this->isEditing = false;
        $this->editingProductId = null;
        $this->barcode = '';
        $this->stockCode = '';
        $this->productName = '';
        $this->brand = '';
        $this->categoryName = '';
        $this->unitName = 'adet';
        $this->vatRate = 20.0;
        $this->cogs = 0.0;
        $this->salePrice = 0.0;
        $this->stockQuantity = 0;
        $this->criticalStockThreshold = null;
        $this->status = 'active';
        $this->description = '';
        $this->resetValidation();
    }

    public function getKpisProperty(): array
    {
        $userId = (int) auth()->id();
        $base = MpProduct::where('user_id', $userId);

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', 'active')->count(),
            'critical' => (clone $base)
                ->whereNotNull('critical_stock_threshold')
                ->whereColumn('stock_quantity', '<=', 'critical_stock_threshold')
                ->count(),
            'stock_value' => (float) (clone $base)->selectRaw('COALESCE(SUM(stock_quantity * cogs), 0) as total')->value('total'),
        ];
    }

    public function getProductsProperty()
    {
        $userId = (int) auth()->id();
        $sort = in_array($this->sortField, ['product_name', 'stock_code', 'barcode', 'category_name', 'sale_price', 'stock_quantity', 'status'], true) ? $this->sortField : 'product_name';
        $direction = in_array(strtolower($this->sortDirection), ['asc', 'desc'], true) ? strtolower($this->sortDirection) : 'asc';

        return MpProduct::where('user_id', $userId)
            ->when($this->filterStatus !== '', fn ($query) => $query->where('status', $this->filterStatus))
            ->when($this->filterCritical === 'critical', fn ($query) => $query->whereNotNull('critical_stock_threshold')->whereColumn('stock_quantity', '<=', 'critical_stock_threshold'))
            ->when($this->search !== '', function ($query): void {
                $term = '%' . $this->search . '%';
                $query->where(function ($q) use ($term): void {
                    $q->where('product_name', 'like', $term)
                        ->orWhere('stock_code', 'like', $term)
                        ->orWhere('barcode', 'like', $term)
                        ->orWhere('brand', 'like', $term)
                        ->orWhere('category_name', 'like', $term);
                });
            })
            ->orderBy($sort, $direction)
            ->paginate(15);
    }

    protected function blankToNull(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    public function render()
    {
        return view('livewire.accounting.products')
            ->layout('layouts.app');
    }
}
