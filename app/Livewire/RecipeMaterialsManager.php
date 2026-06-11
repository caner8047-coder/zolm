<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Livewire\Attributes\Computed;
use App\Models\Material;
use App\Models\Recipe;
use App\Services\RecipeProductCostSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;

class RecipeMaterialsManager extends Component
{
    use WithPagination, WithFileUploads;

    // ─── Filtre & Arama ────────────────────────────────────
    public string $search = '';
    public string $categoryFilter = 'all';
    public string $unitFilter = 'all';
    public string $supplierFilter = 'all';
    public string $statusFilter = 'active'; // active, inactive, all
    public int $perPage = 25;

    public string $searchRecipe = '';
    public string $statusFilterRecipe = 'active'; // active, inactive, all
    public bool $showRiskOnlyRecipe = false;

    // ─── Reçete Sıralama & Toplu İşlem ─────────────────────
    public string $recipeSortField = 'updated_at'; // updated_at, stock_code, name
    public string $recipeSortDirection = 'desc'; // desc, asc
    public array $selectedRecipes = [];
    public bool $selectAllRecipes = false;
    public string $bulkActionRecipe = '';

    // ─── Modal State ───────────────────────────────────────
    public bool $showCreateModal = false;
    public bool $showImportModal = false;
    public ?int $editingMaterialId = null;
    public bool $showQuickViewModal = false;
    public ?int $quickViewRecipeId = null;

    // ─── Vendor Sync State ─────────────────────────────────
    public bool $showVendorSyncModal = false;
    public $vendorSyncFile;
    public array $vendorSyncPreview = [];
    public bool $vendorSyncReady = false;

    #[Computed]
    public function quickViewRecipe()
    {
        if (!$this->quickViewRecipeId) return null;
        return Recipe::with('lines.material', 'product')
            ->where('user_id', Auth::id())
            ->find($this->quickViewRecipeId);
    }

    // ─── Form Fields ───────────────────────────────────────
    public string $formCode = '';
    public string $formName = '';
    public string $formCategory = 'other';
    public string $formUnit = 'pcs';
    public float $formWasteRate = 0.10;
    public ?float $formFabricWidth = null;
    public string $formFabricMethod = 'area_div_width';
    public ?float $formDensity = null;
    public ?float $formThickness = null;
    public string $formRoundingMode = 'none';
    public ?float $formRoundingStep = null;
    public ?float $formUnitPrice = null;
    public string $formCurrency = 'TRY';
    public string $formSupplier = '';
    public string $formNotes = '';
    public string $formTags = ''; // Comma separated tags
    public $formImage;
    public ?string $formImagePreview = null;

    // ─── Toplu İşlem ───────────────────────────────────────
    public array $selectedMaterials = [];
    public bool $selectAll = false;
    public string $bulkAction = '';
    public ?float $bulkWasteRate = null;
    public ?string $bulkCategory = null;
    public ?float $bulkPricePercent = null;

    // ─── Inline Edit ───────────────────────────────────────
    public ?int $inlineEditingId = null;
    public string $inlineEditingField = '';
    public string $inlineEditingValue = '';

    // ─── Import ────────────────────────────────────────────
    public $importFile;

    // ─── Reçete Import ─────────────────────────────────────
    public bool $showRecipeImportModal = false;
    public $recipeImportFile;
    public string $recipeImportName = '';

    // ─── Fabrika Import ────────────────────────────────────
    public bool $showFactoryImportModal = false;
    public string $factoryImportStep = 'ready'; // ready, importing_materials, importing_recipes, done
    public array $factoryImportResult = [];

    // ─── Tab (Malzeme / Reçeteler) ─────────────────────────
    public string $activeTab = 'materials'; // materials, recipes

    // ─── Yeni Özellik State'leri (Faz 2) ───────────────────
    public string $viewMode = 'table'; // table, grid
    public ?int $usageTreeMaterialId = null;
    public array $usageTreeData = [];
    public bool $showUsageTreeModal = false;

    public ?int $replaceOldMaterialId = null;
    public string $replaceAction = ''; // 'merge' or 'replace'
    public bool $showReplaceModal = false;
    public string $replaceSearchTerm = '';
    public array $replaceSearchResults = [];

    // ─── Fiyat Sync (Excel) ────────────────────────────────
    public bool $showPriceSyncModal = false;
    public $priceSyncFile;

    protected $queryString = ['search', 'categoryFilter', 'activeTab', 'viewMode', 'recipeSortField', 'recipeSortDirection'];

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedSearchRecipe()
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter()
    {
        $this->resetPage();
    }

    public function updatedSupplierFilter()
    {
        $this->resetPage();
    }

    public function updatedFormName($value)
    {
        if (empty($value) || $this->editingMaterialId) return;

        $val = mb_strtolower($value);
        if (str_contains($val, 'sünger')) {
            $this->formCategory = 'foam';
            $this->formUnit = 'pcs';
            $this->formWasteRate = 0.15;
        } elseif (str_contains($val, 'kumaş') || str_contains($val, 'keten') || str_contains($val, 'kadife')) {
            $this->formCategory = 'fabric';
            $this->formUnit = 'm';
            $this->formWasteRate = 0.10;
        } elseif (str_contains($val, 'mdf') || str_contains($val, 'sunta') || str_contains($val, 'ahşap') || str_contains($val, 'kontra') || str_contains($val, 'tahta')) {
            $this->formCategory = 'wood';
            $this->formUnit = 'm2';
            $this->formWasteRate = 0.20;
        } elseif (str_contains($val, 'koli') || str_contains($val, 'karton') || str_contains($val, 'naylon') || str_contains($val, 'ambalaj')) {
            $this->formCategory = 'packaging';
            $this->formUnit = 'pcs';
            $this->formWasteRate = 0.05;
        } elseif (str_contains($val, 'vida') || str_contains($val, 'zımba') || str_contains($val, 'civata') || str_contains($val, 'somun')) {
            $this->formCategory = 'hardware';
            $this->formUnit = 'pcs';
            $this->formWasteRate = 0.02;
        }
    }

    // ─── CRUD İşlemleri ────────────────────────────────────

    public function openCreateModal()
    {
        $this->resetForm();
        $this->editingMaterialId = null;
        $this->showCreateModal = true;
    }

    public function editMaterial(int $id)
    {
        $material = Material::where('user_id', Auth::id())->findOrFail($id);
        $this->editingMaterialId = $id;
        $this->formCode = $material->code;
        $this->formName = $material->name;
        $this->formCategory = $material->category;
        $this->formUnit = $material->base_unit;
        $this->formWasteRate = $material->default_waste_rate;
        $this->formFabricWidth = $material->fabric_width_cm;
        $this->formFabricMethod = $material->fabric_calc_method;
        $this->formDensity = $material->density_kg_m3;
        $this->formThickness = $material->thickness_cm;
        $this->formRoundingMode = $material->rounding_mode;
        $this->formRoundingStep = $material->rounding_step;
        $this->formUnitPrice = $material->unit_price;
        $this->formCurrency = $material->currency;
        $this->formSupplier = $material->supplier ?? '';
        $this->formNotes = $material->notes ?? '';
        $this->formTags = is_array($material->tags) ? implode(', ', $material->tags) : '';
        $this->formImagePreview = $material->image_path;
        $this->formImage = null;
        $this->showCreateModal = true;
    }

    public function saveMaterial()
    {
        $this->validate([
            'formCode' => 'required|max:50',
            'formName' => 'required|max:255',
            'formCategory' => 'required',
            'formUnit' => 'required',
            'formWasteRate' => 'required|numeric|min:0|max:1',
            'formImage' => 'nullable|image|max:5120', // Max 5MB
        ]);

        $data = [
            'user_id'            => Auth::id(),
            'code'               => $this->formCode,
            'name'               => $this->formName,
            'category'           => $this->formCategory,
            'base_unit'          => $this->formUnit,
            'default_waste_rate' => $this->formWasteRate,
            'fabric_width_cm'    => $this->formCategory === 'fabric' ? $this->formFabricWidth : null,
            'fabric_calc_method' => $this->formFabricMethod,
            'density_kg_m3'      => $this->formCategory === 'foam' ? $this->formDensity : null,
            'thickness_cm'       => in_array($this->formCategory, ['foam', 'wood']) ? $this->formThickness : null,
            'rounding_mode'      => $this->formRoundingMode,
            'rounding_step'      => $this->formRoundingStep,
            'unit_price'         => $this->formUnitPrice,
            'currency'           => $this->formCurrency,
            'supplier'           => $this->formSupplier ?: null,
            'notes'              => $this->formNotes ?: null,
            'tags'               => $this->formTags ? array_map('trim', explode(',', $this->formTags)) : null,
        ];

        if ($this->formImage) {
            $data['image_path'] = $this->formImage->store('materials', 'public');
        }

        if ($this->editingMaterialId) {
            $material = Material::where('user_id', Auth::id())->findOrFail($this->editingMaterialId);

            if ($material->unit_price != $this->formUnitPrice && $this->formUnitPrice > 0) {
                \App\Models\MaterialPriceHistory::create([
                    'material_id' => $material->id,
                    'user_id' => Auth::id(),
                    'old_price' => $material->unit_price ?? 0,
                    'new_price' => $this->formUnitPrice,
                    'reason' => 'manual_edit'
                ]);
            }

            $material->update($data);
            $syncSummary = app(RecipeProductCostSyncService::class)->syncActiveRecipesUsingMaterials([$material->id], (int) Auth::id(), false);
            session()->flash('success', $this->withRecipeCostSyncMessage("✅ {$material->code} güncellendi.", $syncSummary));
        } else {
            // Mükerrer kontrol
            $exists = Material::where('user_id', Auth::id())
                ->where('code', $this->formCode)->exists();
            if ($exists) {
                $this->addError('formCode', 'Bu stok kodu zaten mevcut.');
                return;
            }
            Material::create($data);
            session()->flash('success', "✅ {$this->formCode} eklendi.");
        }

        $this->showCreateModal = false;
        $this->resetForm();
    }

    public function toggleActive(int $id)
    {
        $material = Material::where('user_id', Auth::id())->findOrFail($id);
        $material->update(['is_active' => !$material->is_active]);
    }

    public function deleteMaterial(int $id)
    {
        $material = Material::where('user_id', Auth::id())->findOrFail($id);
        // Reçetede kullanılıyor mu kontrol
        if ($material->recipeLines()->exists()) {
            session()->flash('error', "⚠️ {$material->code} bir reçetede kullanılıyor, silinemiyor.");
            return;
        }
        $material->delete();
        session()->flash('success', "🗑️ {$material->code} silindi.");
    }

    // ─── Inline Edit İşlemleri ─────────────────────────────

    public function startInlineEdit(int $id, string $field, string $currentValue)
    {
        $this->inlineEditingId = $id;
        $this->inlineEditingField = $field;
        $this->inlineEditingValue = is_numeric($currentValue) ? rtrim(rtrim(number_format((float)$currentValue, 4, '.', ''), '0'), '.') : $currentValue;
        if ($this->inlineEditingValue === '') $this->inlineEditingValue = '0';
    }

    public function saveInlineEdit()
    {
        if (!$this->inlineEditingId) return;

        $material = Material::where('user_id', Auth::id())->findOrFail($this->inlineEditingId);

        $field = $this->inlineEditingField;
        $value = $this->inlineEditingValue;

        if ($field === 'unit_price' || $field === 'default_waste_rate') {
            $value = $this->parseFloat($value);
            if ($field === 'default_waste_rate' && $value > 1) {
                $value = $value / 100;
            }
        }

        if ($field === 'unit_price' && $material->unit_price != $value && $value > 0) {
            \App\Models\MaterialPriceHistory::create([
                'material_id' => $material->id,
                'user_id' => Auth::id(),
                'old_price' => $material->unit_price ?? 0,
                'new_price' => $value,
                'reason' => 'inline_edit'
            ]);
        }

        $material->$field = $value;
        $material->save();

        if ($field === 'unit_price') {
            $syncSummary = app(RecipeProductCostSyncService::class)->syncActiveRecipesUsingMaterials([$material->id], (int) Auth::id(), false);

            if (($syncSummary['enabled'] ?? false) && (int) ($syncSummary['matched_products'] ?? 0) > 0) {
                session()->flash('success', $this->withRecipeCostSyncMessage('✅ Malzeme fiyatı güncellendi.', $syncSummary));
            }
        }

        $this->inlineEditingId = null;
        $this->inlineEditingField = '';
    }

    public function cancelInlineEdit()
    {
        $this->inlineEditingId = null;
        $this->inlineEditingField = '';
    }

    // ─── Toplu İşlemler ────────────────────────────────────

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedMaterials = Material::where('user_id', Auth::id())
                ->pluck('id')->map(fn($id) => (string) $id)->toArray();
        } else {
            $this->selectedMaterials = [];
        }
    }

    public function applyBulkAction()
    {
        if (empty($this->selectedMaterials)) return;

        $materials = Material::where('user_id', Auth::id())
            ->whereIn('id', $this->selectedMaterials);

        $count = $materials->count();

        switch ($this->bulkAction) {
            case 'activate':
                $materials->update(['is_active' => true]);
                session()->flash('success', "✅ {$count} malzeme aktifleştirildi.");
                break;
            case 'deactivate':
                $materials->update(['is_active' => false]);
                session()->flash('success', "⏸️ {$count} malzeme pasife alındı.");
                break;
            case 'change_waste':
                if ($this->bulkWasteRate !== null) {
                    $materials->update(['default_waste_rate' => $this->bulkWasteRate]);
                    session()->flash('success', "✅ {$count} malzemenin fire oranı güncellendi.");
                }
                break;
            case 'change_category':
                if ($this->bulkCategory) {
                    $materials->update(['category' => $this->bulkCategory]);
                    session()->flash('success', "✅ {$count} malzemenin kategorisi güncellendi.");
                }
                break;
            case 'update_price_percent':
                if ($this->bulkPricePercent !== null && $this->bulkPricePercent != 0) {
                    $multiplier = 1 + ($this->bulkPricePercent / 100);
                    $updatedMaterialIds = [];
                    foreach ($materials->get() as $material) {
                        if ($material->unit_price > 0) {
                            $newPrice = $material->unit_price * $multiplier;

                            \App\Models\MaterialPriceHistory::create([
                                'material_id' => $material->id,
                                'user_id' => Auth::id(),
                                'old_price' => $material->unit_price,
                                'new_price' => $newPrice,
                                'reason' => 'bulk_update_percent'
                            ]);

                            $material->update(['unit_price' => $newPrice]);
                            $updatedMaterialIds[] = (int) $material->id;
                        }
                    }
                    $syncSummary = app(RecipeProductCostSyncService::class)->syncActiveRecipesUsingMaterials($updatedMaterialIds, (int) Auth::id(), false);
                    session()->flash('success', $this->withRecipeCostSyncMessage("💰 {$count} malzemenin fiyatına %{$this->bulkPricePercent} zam/indirim uygulandı.", $syncSummary));
                }
                break;
            case 'delete':
                // Reçetede kullanılmayanları sil
                $used = \App\Models\RecipeLine::whereIn('material_id', $this->selectedMaterials)->pluck('material_id')->toArray();
                $deletable = array_diff($this->selectedMaterials, $used);
                Material::whereIn('id', $deletable)->where('user_id', Auth::id())->delete();
                $deleted = count($deletable);
                $skipped = count($used);
                session()->flash('success', "🗑️ {$deleted} silindi" . ($skipped ? ", {$skipped} reçetede kullanıldığı için atlandı." : "."));
                break;
        }

        $this->selectedMaterials = [];
        $this->selectAll = false;
        $this->bulkAction = '';
        $this->bulkPricePercent = null;
    }

    public function updatedSelectAllRecipes($value)
    {
        if ($value) {
            $hasRecipeStockCode = Schema::hasColumn('recipes', 'stock_code');

            // Sadece şu anki sayfanın IDsini mi alalım yoksa tümünü mü? Tümü alalım
            $this->selectedRecipes = Recipe::where('user_id', Auth::id())
                ->when($this->searchRecipe, function ($q) use ($hasRecipeStockCode) {
                    $q->where(function ($subq) use ($hasRecipeStockCode) {
                        $subq->where('name', 'like', '%' . $this->searchRecipe . '%')
                             ->orWhere('version', 'like', '%' . $this->searchRecipe . '%');

                        if ($hasRecipeStockCode) {
                            $subq->orWhere('stock_code', 'like', '%' . $this->searchRecipe . '%');
                        }

                        $subq->orWhereHas('product', function ($pq) {
                            $pq->where('stock_code', 'like', '%' . $this->searchRecipe . '%')
                               ->orWhere('product_name', 'like', '%' . $this->searchRecipe . '%');
                        });
                    });
                })
                ->when($this->statusFilterRecipe === 'active', fn($q) => $q->where('status', 'active'))
                ->when($this->statusFilterRecipe === 'inactive', fn($q) => $q->where('status', 'inactive'))
                ->pluck('id')->map(fn($id) => (string) $id)->toArray();
        } else {
            $this->selectedRecipes = [];
        }
    }

    public function applyBulkActionRecipe()
    {
        if (empty($this->selectedRecipes) || empty($this->bulkActionRecipe)) return;

        $recipes = Recipe::where('user_id', Auth::id())
            ->whereIn('id', $this->selectedRecipes);

        $count = $recipes->count();

        switch ($this->bulkActionRecipe) {
            case 'activate':
                $recipes->update(['status' => 'active']);
                $syncSummary = app(RecipeProductCostSyncService::class)->syncAllForUser((int) Auth::id(), false);
                session()->flash('success', $this->withRecipeCostSyncMessage("✅ {$count} reçete aktifleştirildi.", $syncSummary));
                break;
            case 'deactivate':
                $recipes->update(['status' => 'inactive']);
                session()->flash('success', "⏸️ {$count} reçete pasife alındı.");
                break;
            case 'delete':
                foreach ($recipes->get() as $recipe) {
                    $recipe->lines()->delete();
                    $recipe->delete();
                }
                session()->flash('success', "🗑️ {$count} reçete silindi.");
                break;
        }

        $this->selectedRecipes = [];
        $this->selectAllRecipes = false;
        $this->bulkActionRecipe = '';
    }

    // ─── Excel Import ──────────────────────────────────────

    public function importExcel()
    {
        $this->validate(['importFile' => 'required|mimes:xlsx,xls|max:10240']);

        try {
            $path = $this->importFile->getRealPath();
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);

            // İlk sayfayı oku (stok kartları)
            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray(null, true, true, true);

            $headerRow = $this->detectMaterialImportHeaderRow($data);
            $headers = $this->materialImportHeaders($data[$headerRow] ?? []);

            $codeColumn = $this->findImportColumn($headers, ['stok kodu', 'malzeme kodu', 'hammadde kodu', 'code', 'sku']) ?? 'A';
            $nameColumn = $this->findImportColumn($headers, ['stok adi', 'stok adı', 'malzeme', 'malzeme adi', 'malzeme adı', 'hammadde aciklamasi', 'hammadde açıklaması', 'aciklama', 'açıklama']) ?? ($codeColumn === 'A' ? 'B' : 'C');
            $priceColumn = $this->findImportColumn($headers, [
                'yeni fiyat',
                'fiyat',
                'mf fiyati',
                'mf fiyatı',
                '(mf) fiyati',
                '(mf) fiyatı',
                'mf birim fiyati',
                'mf birim fiyatı',
                '(mf) birim fiyati',
                '(mf) birim fiyatı',
                'birim fiyat',
                'birim fiyati',
                'birim fiyatı',
                'guncel fiyat',
                'güncel fiyat',
                'price',
                'unit price',
            ]);
            $categoryColumn = $this->findImportColumn($headers, ['kategori', 'category']);
            $unitColumn = $this->findImportColumn($headers, ['birim', 'unit']);
            $currencyColumn = $this->findImportColumn($headers, ['para birimi', 'currency']);
            $wasteColumn = $this->findImportColumn($headers, ['fire orani', 'fire oranı', 'fire']);
            $fabricWidthColumn = $this->findImportColumn($headers, ['kumas eni cm', 'kumaş eni cm', 'kumas eni', 'kumaş eni']);
            $statusColumn = $this->findImportColumn($headers, ['durum', 'status']);

            $imported = 0;
            $updated = 0;
            $skipped = 0;
            $updatedMaterialIds = [];

            foreach ($data as $i => $row) {
                if ($i <= $headerRow) continue; // Header atla

                $code = trim((string) ($row[$codeColumn] ?? ''));
                $name = trim((string) ($row[$nameColumn] ?? ''));
                if (empty($code) || empty($name)) continue;

                // Kategoriyi addan tahmin et
                $category = $this->resolveImportCategory($row[$categoryColumn] ?? null) ?? $this->guessCategory($name);
                $unit = $this->resolveImportUnit($row[$unitColumn] ?? null) ?? $this->guessUnit($name, $category);
                $unitPrice = $priceColumn ? $this->parseFloat($row[$priceColumn] ?? null) : null;
                $currency = $this->normalizeImportCurrency($row[$currencyColumn] ?? null);
                $wasteRate = $this->parseFloat($row[$wasteColumn] ?? null) ?? 0.10;

                // Kumaş eni tahmin (ad'da "XXX CM" veya "XXXCM" varsa)
                $fabricWidth = $this->parseFloat($row[$fabricWidthColumn] ?? null);
                if ($category === 'fabric' || $category === 'textile') {
                    if ($fabricWidth === null && preg_match('/(\d+)\s*CM/i', $name, $m)) {
                        $fabricWidth = (float) $m[1];
                    }
                }

                $existing = Material::where('user_id', Auth::id())
                    ->where('code', $code)->first();

                if ($existing) {
                    $changes = [];

                    if ($unitPrice !== null && ($existing->unit_price === null || abs((float) $existing->unit_price - $unitPrice) > 0.0001)) {
                        \App\Models\MaterialPriceHistory::create([
                            'material_id' => $existing->id,
                            'user_id' => Auth::id(),
                            'old_price' => $existing->unit_price ?? 0,
                            'new_price' => $unitPrice,
                            'reason' => 'material_excel_import',
                        ]);

                        $changes['unit_price'] = $unitPrice;
                        $changes['last_price_updated_at'] = now();
                    }

                    if ($unitPrice !== null && $currency !== '' && $existing->currency !== $currency) {
                        $changes['currency'] = $currency;
                    }

                    if ($changes !== []) {
                        $existing->update($changes);
                        $updatedMaterialIds[] = (int) $existing->id;
                        $updated++;
                    } else {
                        $skipped++;
                    }

                    continue;
                }

                Material::create([
                    'user_id'          => Auth::id(),
                    'code'             => $code,
                    'name'             => $name,
                    'category'         => $category,
                    'base_unit'        => $unit,
                    'default_waste_rate' => $wasteRate,
                    'fabric_width_cm'  => $fabricWidth,
                    'unit_price'       => $unitPrice,
                    'currency'         => $currency !== '' ? $currency : 'TRY',
                    'is_active'        => $this->resolveImportStatus($row[$statusColumn] ?? null),
                ]);
                $imported++;
            }

            $syncSummary = app(RecipeProductCostSyncService::class)->syncActiveRecipesUsingMaterials($updatedMaterialIds, (int) Auth::id(), false);
            $message = "✅ {$imported} malzeme eklendi";
            $message .= $updated ? ", {$updated} fiyat güncellendi" : '';
            $message .= $skipped ? ", {$skipped} mükerrer değişmeden atlandı" : '';

            session()->flash('success', $this->withRecipeCostSyncMessage($message . '.', $syncSummary));
        } catch (\Exception $e) {
            Log::error('Material import error', ['error' => $e->getMessage()]);
            session()->flash('error', '❌ Import hatası: ' . $e->getMessage());
        }

        $this->showImportModal = false;
        $this->importFile = null;
    }

    private function guessCategory(string $name): string
    {
        $n = mb_strtolower($name);
        if (str_contains($n, 'kumaş') || str_contains($n, 'kadife') || str_contains($n, 'peluş') || str_contains($n, 'babyface') || str_contains($n, 'welsoft')) return 'fabric';
        if (str_contains($n, 'sünger') || str_contains($n, 'danste')) return 'foam';
        if (str_contains($n, 'panel') || str_contains($n, 'mdf') || str_contains($n, 'sunta') || str_contains($n, 'duralit') || str_contains($n, 'ahşap') || str_contains($n, 'kereste') || str_contains($n, 'karton')) return 'wood';
        if (str_contains($n, 'tela') || str_contains($n, 'astar')) return 'textile';
        if (str_contains($n, 'elyaf') || str_contains($n, 'vatka')) return 'lining';
        if (str_contains($n, 'ambalaj') || str_contains($n, 'naylon') || str_contains($n, 'jelatin') || str_contains($n, 'koli') || str_contains($n, 'streç')) return 'packaging';
        if (str_contains($n, 'zımba') || str_contains($n, 'vida') || str_contains($n, 'lastik') || str_contains($n, 'fermuar') || str_contains($n, 'yapıştırıcı') || str_contains($n, 'çivi')) return 'hardware';
        return 'other';
    }

    private function guessUnit(string $name, string $category): string
    {
        if (in_array($category, ['fabric', 'textile', 'lining'])) return 'm';
        if ($category === 'foam') return 'm3';
        if ($category === 'wood') {
            $n = mb_strtolower($name);
            if (str_contains($n, 'm2') || str_contains($n, 'metreka')) return 'm2';
            if (str_contains($n, 'm3')) return 'm3';
        }
        return 'pcs';
    }

    // ─── Reçete Excel Import ───────────────────────────────

    public function openRecipeImportModal()
    {
        $this->recipeImportFile = null;
        $this->recipeImportName = '';
        $this->showRecipeImportModal = true;
    }

    /**
     * Excel dosyasından reçete import et
     * "ürün ağacı" sayfasını okuyarak malzemeler + reçete satırları oluşturur
     */
    public function importRecipeExcel()
    {
        $this->validate([
            'recipeImportFile' => 'required|mimes:xlsx,xls|max:10240',
            'recipeImportName' => 'required|max:255',
        ]);

        try {
            $path = $this->recipeImportFile->getRealPath();
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);

            $sheetNames = $spreadsheet->getSheetNames();

            // "ürün ağacı" sayfasını bul
            $treeSheet = null;
            foreach ($sheetNames as $idx => $name) {
                $nl = mb_strtolower($name);
                if (str_contains($nl, 'ürün ağacı') || str_contains($nl, 'urun agaci') || str_contains($nl, 'ağacı') || str_contains($nl, 'agaci')) {
                    $treeSheet = $spreadsheet->getSheet($idx);
                    break;
                }
            }

            // Bulunamadıysa ikinci sayfayı dene (genellikle index 1)
            if (!$treeSheet && count($sheetNames) >= 2) {
                $treeSheet = $spreadsheet->getSheet(1);
            }

            if (!$treeSheet) {
                session()->flash('error', '❌ "ürün ağacı" sayfası bulunamadı.');
                return;
            }

            $data = $treeSheet->toArray(null, true, true, true);

            // Reçeteyi oluştur
            $recipe = Recipe::create([
                'user_id' => Auth::id(),
                'name'    => $this->recipeImportName,
                'version' => 'v1',
                'status'  => 'draft',
            ]);

            $currentOperation = 'diger';
            $currentMaterialCode = null;
            $materialsCreated = 0;
            $linesCreated = 0;
            $sortOrder = 0;

            foreach ($data as $rowIdx => $row) {
                // Header satırlarını atla (D=toplam, E=KULLANILAN YER...)
                $colD = trim($row['D'] ?? '');
                $colE = trim($row['E'] ?? '');
                if ($colE === 'KULLANILAN YER' || $colD === 'toplam') continue;

                $colA = trim($row['A'] ?? '');
                $colB = trim($row['B'] ?? '');
                $colC = trim($row['C'] ?? '');

                // Operasyon grup başlığını tespit et (A sütununda)
                if (!empty($colA) && empty($colB)) continue; // Sadece başlık
                if (!empty($colA)) {
                    $currentOperation = $this->mapOperationGroup($colA);
                }

                // Malzeme satırı: B sütununda stok kodu olmalı
                if (empty($colB)) continue;

                // Yeni malzeme kodu başlığı (C sütununda ad var)
                if (!empty($colC)) {
                    $currentMaterialCode = $colB;
                }

                $materialCode = $currentMaterialCode ?? $colB;

                // Malzemeyi bul veya oluştur
                $material = Material::where('user_id', Auth::id())
                    ->where('code', $materialCode)->first();

                if (!$material) {
                    $materialName = $colC ?: $materialCode;
                    $category = $this->guessCategory($materialName);
                    $unit = $this->guessUnit($materialName, $category);
                    $fabricWidth = null;
                    if (in_array($category, ['fabric', 'textile'])) {
                        if (preg_match('/(\d+)\s*CM/i', $materialName, $m)) {
                            $fabricWidth = (float) $m[1];
                        }
                    }
                    $material = Material::create([
                        'user_id'          => Auth::id(),
                        'code'             => $materialCode,
                        'name'             => $materialName,
                        'category'         => $category,
                        'base_unit'        => $unit,
                        'default_waste_rate' => 0.10,
                        'fabric_width_cm'  => $fabricWidth,
                    ]);
                    $materialsCreated++;
                }

                // Ölçüleri oku
                $width  = $this->parseFloat($row['F'] ?? null);
                $length = $this->parseFloat($row['G'] ?? null);
                $height = $this->parseFloat($row['H'] ?? null);
                $pieces = $this->parseFloat($row['I'] ?? null) ?: 1;
                $fireRate = $this->parseFloat($row['L'] ?? null); // Fire oranı (0.1 = %10)
                $resultQty = $this->parseFloat($row['K'] ?? $row['D'] ?? null);

                // Hesap tipini belirle
                $calcType = $this->detectCalcType($material, $width, $length, $height);

                // Reçete satırını oluştur
                \App\Models\RecipeLine::create([
                    'recipe_id'           => $recipe->id,
                    'material_id'         => $material->id,
                    'operation'           => $currentOperation,
                    'usage_area'          => $colE ?: null,
                    'calc_type'           => $calcType,
                    'width_cm'            => $width,
                    'length_cm'           => $length,
                    'height_cm'           => $calcType === 'volume_m3' ? $height : null,
                    'pieces'              => $pieces,
                    'waste_rate_override' => $fireRate,
                    'constant_qty'        => $calcType === 'fixed_qty' ? $resultQty : null,
                    'calculated_qty'      => $resultQty ?? 0,
                    'calculated_unit'     => $material->base_unit,
                    'sort_order'          => $sortOrder++,
                ]);
                $linesCreated++;
            }

            // Hesapları yeniden çalıştır
            $calcService = new \App\Services\RecipeCalculationService();
            $calcService->calculateRecipe($recipe);

            $this->showRecipeImportModal = false;
            $this->recipeImportFile = null;
            $this->recipeImportName = '';
            $this->activeTab = 'recipes';

            session()->flash('success', "✅ Reçete import edildi: {$linesCreated} satır, {$materialsCreated} yeni malzeme oluşturuldu.");

        } catch (\Exception $e) {
            Log::error('Recipe import error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            session()->flash('error', '❌ Reçete import hatası: ' . $e->getMessage());
        }
    }

    /**
     * Excel A sütunundaki grup adını operasyon enum'ına çevir
     */
    private function mapOperationGroup(string $group): string
    {
        $g = mb_strtoupper(trim($group));
        return match (true) {
            str_contains($g, 'KUMAŞ')    => 'terzihane',
            str_contains($g, 'TELA')     => 'terzihane',
            str_contains($g, 'AHŞAP')    => 'marangoz',
            str_contains($g, 'SÜNGER')   => 'sunger',
            str_contains($g, 'NAYLON')   => 'paketleme',
            str_contains($g, 'AMBALAJ')  => 'paketleme',
            str_contains($g, 'DEMİR')    => 'demirhane',
            str_contains($g, 'DÖŞEME')   => 'doseme',
            str_contains($g, 'KOL')      => 'marangoz',
            default                      => 'diger',
        };
    }

    /**
     * Malzeme kategorisi ve ölçülere göre hesap tipini belirle
     */
    private function detectCalcType(Material $material, ?float $w, ?float $l, ?float $h): string
    {
        $cat = $material->category;

        // Kumaş/tela/astar → kumaş metre
        if (in_array($cat, ['fabric', 'textile', 'lining'])) {
            return 'fabric_meter';
        }
        // Sünger → hacim
        if ($cat === 'foam') {
            return 'volume_m3';
        }
        // Ahşap → ölçü varsa alan/hacim, yoksa sabit
        if ($cat === 'wood') {
            if ($w && $l && $h && $h > 1) return 'volume_m3';
            if ($w && $l) return 'area_m2';
        }
        // Ambalaj → ölçü varsa alan, yoksa sabit
        if ($cat === 'packaging') {
            if ($w && $l) return 'area_m2';
        }
        // Ölçü yoksa sabit miktar
        if (!$w && !$l) return 'fixed_qty';

        return 'fixed_qty';
    }

    private function detectMaterialImportHeaderRow(array $data): int
    {
        foreach ($data as $index => $row) {
            if ((int) $index > 10) break;

            $headers = $this->materialImportHeaders($row);
            if ($this->findImportColumn($headers, ['stok kodu', 'malzeme kodu', 'hammadde kodu']) !== null) {
                return (int) $index;
            }
        }

        return 1;
    }

    private function materialImportHeaders(array $row): array
    {
        $headers = [];
        foreach ($row as $column => $value) {
            $normalized = $this->normalizeImportHeader($value);
            if ($normalized !== '') {
                $headers[$column] = $normalized;
            }
        }

        return $headers;
    }

    private function findImportColumn(array $headers, array $aliases): ?string
    {
        foreach ($aliases as $alias) {
            $normalizedAlias = $this->normalizeImportHeader($alias);
            foreach ($headers as $column => $header) {
                if ($header === $normalizedAlias) {
                    return (string) $column;
                }
            }
        }

        return null;
    }

    private function normalizeImportHeader($value): string
    {
        $value = mb_strtolower(trim((string) $value), 'UTF-8');
        $value = strtr($value, [
            'ı' => 'i',
            'ğ' => 'g',
            'ü' => 'u',
            'ş' => 's',
            'ö' => 'o',
            'ç' => 'c',
        ]);

        return trim((string) preg_replace('/[^a-z0-9]+/u', ' ', $value));
    }

    private function resolveImportCategory($value): ?string
    {
        $normalized = $this->normalizeImportHeader($value);
        if ($normalized === '') return null;

        return [
            'fabric' => 'fabric',
            'kumas' => 'fabric',
            'foam' => 'foam',
            'sunger' => 'foam',
            'wood' => 'wood',
            'ahsap' => 'wood',
            'ahsap panel' => 'wood',
            'panel' => 'wood',
            'hardware' => 'hardware',
            'hirdavat' => 'hardware',
            'packaging' => 'packaging',
            'ambalaj' => 'packaging',
            'textile' => 'textile',
            'tela astar' => 'textile',
            'tela' => 'textile',
            'astar' => 'textile',
            'lining' => 'lining',
            'elyaf vatka' => 'lining',
            'elyaf' => 'lining',
            'vatka' => 'lining',
            'other' => 'other',
            'diger' => 'other',
        ][$normalized] ?? null;
    }

    private function resolveImportUnit($value): ?string
    {
        $normalized = $this->normalizeImportHeader($value);
        if ($normalized === '') return null;

        return [
            'm' => 'm',
            'metre' => 'm',
            'meter' => 'm',
            'm2' => 'm2',
            'metrekare' => 'm2',
            'm3' => 'm3',
            'metrekup' => 'm3',
            'pcs' => 'pcs',
            'adet' => 'pcs',
            'kg' => 'kg',
            'kilogram' => 'kg',
            'set' => 'set',
        ][$normalized] ?? null;
    }

    private function normalizeImportCurrency($value): string
    {
        $value = trim((string) $value);
        if ($value === '') return '';

        $upper = mb_strtoupper($value, 'UTF-8');
        if (str_contains($upper, '₺') || str_contains($upper, 'TL')) {
            return 'TRY';
        }

        $currency = preg_replace('/[^A-Z]/', '', $upper);

        return $currency ? substr($currency, 0, 3) : '';
    }

    private function resolveImportStatus($value): bool
    {
        $normalized = $this->normalizeImportHeader($value);

        return !in_array($normalized, ['pasif', 'inactive', '0', 'hayir'], true);
    }

    private function parseFloat($val): ?float
    {
        if ($val === null) return null;
        if (is_int($val) || is_float($val)) return (float) $val;

        $v = trim((string) $val);
        if ($v === '') return null;

        $v = str_replace(["\xc2\xa0", '₺', '%'], '', $v);
        $v = preg_replace('/[^\d,.\-]/u', '', $v);
        if ($v === '' || $v === '-' || $v === ',' || $v === '.') return null;

        $lastComma = strrpos($v, ',');
        $lastDot = strrpos($v, '.');

        if ($lastComma !== false && $lastDot !== false) {
            if ($lastComma > $lastDot) {
                $v = str_replace('.', '', $v);
                $v = str_replace(',', '.', $v);
            } else {
                $v = str_replace(',', '', $v);
            }
        } elseif ($lastComma !== false) {
            $v = str_replace('.', '', $v);
            $v = str_replace(',', '.', $v);
        }

        return is_numeric($v) ? (float) $v : null;
    }

    // ─── Export ────────────────────────────────────────────

    public function exportMaterials()
    {
        $materials = Material::where('user_id', Auth::id())
            ->search($this->search)
            ->byCategory($this->categoryFilter)
            ->byUnit($this->unitFilter)
            ->when($this->statusFilter === 'active', fn($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn($q) => $q->where('is_active', false))
            ->orderBy('category')
            ->orderBy('code')
            ->get();

        $data = $materials->map(function ($material) {
            return [
                'Stok Kodu' => $material->code,
                'Stok Adı' => $material->name,
                'Kategori' => $material->category_label ?? $material->category,
                'Birim' => $material->base_unit,
                'Fiyat' => $material->unit_price,
                'Para Birimi' => $material->currency,
                'Fire Oranı' => $material->default_waste_rate,
                'Kumaş Eni (cm)' => $material->fabric_width_cm,
                'Durum' => $material->is_active ? 'Aktif' : 'Pasif',
            ];
        })->toArray();

        if (empty($data)) {
            session()->flash('error', 'Dışa aktarılacak malzeme bulunamadı.');
            return;
        }

        $fileName = 'HamMaddeler_' . date('Y-m-d_H-i') . '.xlsx';
        $path = storage_path('app/public/' . $fileName);

        $excelService = new \App\Services\ExcelService();
        $excelService->exportToXlsx([
            ['name' => 'Ham Maddeler', 'data' => $data]
        ], $path);

        return response()->download($path)->deleteFileAfterSend(true);
    }

    // ─── Yeni Özellikler: "Nerede Kullanılıyor" (Where Used) ─
    public function showUsageTree(int $materialId)
    {
        $this->usageTreeMaterialId = $materialId;
        $material = Material::where('user_id', Auth::id())->findOrFail($materialId);

        // Bu malzemenin kullanıldığı tüm reçeteleri bul
        $lines = \App\Models\RecipeLine::with('recipe.product')->where('material_id', $materialId)->get();

        $this->usageTreeData = $lines->map(function($line) {
            return [
                'recipe_name' => $line->recipe->name . ' (' . $line->recipe->version . ')',
                'operation'   => $line->operation_label ?? $line->operation,
                'qty'         => $line->calculated_qty . ' ' . $line->calculated_unit,
                'product'     => $line->recipe->product ? $line->recipe->product->product_name : 'Bağımsız Reçete',
            ];
        })->toArray();

        $this->showUsageTreeModal = true;
    }

    // ─── Yeni Özellikler: Değiştir / Birleştir Sihirbazı ─────
    public function openReplaceModal(int $materialId, string $action = 'replace')
    {
        $this->replaceOldMaterialId = $materialId;
        $this->replaceAction = $action; // 'replace' or 'merge'
        $this->replaceSearchTerm = '';
        $this->replaceSearchResults = [];
        $this->showReplaceModal = true;
    }

    public function updatedReplaceSearchTerm()
    {
        if (strlen($this->replaceSearchTerm) < 2) {
            $this->replaceSearchResults = [];
            return;
        }

        $this->replaceSearchResults = Material::where('user_id', Auth::id())
            ->where('id', '!=', $this->replaceOldMaterialId)
            ->search($this->replaceSearchTerm)
            ->limit(10)
            ->get(['id', 'code', 'name', 'category', 'unit_price'])
            ->toArray();
    }

    public function executeReplace(int $newMaterialId)
    {
        if (!$this->replaceOldMaterialId) return;

        $oldMaterial = Material::where('user_id', Auth::id())->findOrFail($this->replaceOldMaterialId);
        $newMaterial = Material::where('user_id', Auth::id())->findOrFail($newMaterialId);

        // Tüm reçete satırlarında malzemeyi değiştir
        $updatedCount = \App\Models\RecipeLine::where('material_id', $oldMaterial->id)
            ->update(['material_id' => $newMaterial->id]);

        if ($this->replaceAction === 'merge') {
            // Birleştirme ise eskisini sil
            $oldMaterial->delete();
            session()->flash('success', "✅ {$oldMaterial->code} başarıyla {$newMaterial->code} ile birleştirildi. {$updatedCount} satır güncellendi.");
        } else {
            session()->flash('success', "✅ Tüm reçetelerde {$oldMaterial->code} yerine {$newMaterial->code} atandı. {$updatedCount} satır güncellendi.");
        }

        $this->showReplaceModal = false;
        $this->replaceOldMaterialId = null;
    }

    // ─── Yeni Özellikler: Reçete Klonlama ───────────────────
    public function cloneRecipe(int $recipeId)
    {
        $recipe = Recipe::where('user_id', Auth::id())->findOrFail($recipeId);
        $newRecipe = $recipe->duplicate($recipe->version . '-kopya');

        session()->flash('success', "✅ Reçete başarıyla klonlandı: {$newRecipe->name} ({$newRecipe->version})");
    }

    // ─── Akıllı Fiyat Senkronizasyonu (Dışa Aktar) ─────────
    public function exportPriceSyncTemplate()
    {
        $materials = Material::where('user_id', Auth::id())->orderBy('category')->orderBy('code')->get();

        $data = $materials->map(function ($material) {
            return [
                'ID (DEĞİŞTİRMEYİN)' => $material->id,
                'Stok Kodu' => $material->code,
                'Stok Adı' => $material->name,
                'Güncel Fiyat' => $material->unit_price,
                'YENİ FİYAT' => '', // Kullanıcı burayı dolduracak
            ];
        })->toArray();

        $fileName = 'Fiyat_Guncelleme_Sablonu_' . date('Y-m-d') . '.xlsx';
        $path = storage_path('app/public/' . $fileName);

        $excelService = new \App\Services\ExcelService();
        $excelService->exportToXlsx([['name' => 'Fiyatlar', 'data' => $data]], $path);

        return response()->download($path)->deleteFileAfterSend(true);
    }

    public function importPriceSync()
    {
        $this->validate(['priceSyncFile' => 'required|mimes:xlsx,xls|max:10240']);

        try {
            $path = $this->priceSyncFile->getRealPath();
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray(null, true, true, true);

            $headerRow = $this->detectMaterialImportHeaderRow($data);
            $headers = $this->materialImportHeaders($data[$headerRow] ?? []);

            $idColumn = $this->findImportColumn($headers, ['id degistirmeyin', 'id değiştirmeyin', 'id']);
            $codeColumn = $this->findImportColumn($headers, ['stok kodu', 'malzeme kodu', 'hammadde kodu', 'code', 'sku']);
            $nameColumn = $this->findImportColumn($headers, ['stok adi', 'stok adı', 'malzeme', 'malzeme adi', 'malzeme adı', 'hammadde aciklamasi', 'hammadde açıklaması', 'aciklama', 'açıklama']);
            $priceColumn = $this->findImportColumn($headers, [
                'yeni fiyat',
                'fiyat',
                'mf fiyati',
                'mf fiyatı',
                '(mf) fiyati',
                '(mf) fiyatı',
                'mf birim fiyati',
                'mf birim fiyatı',
                '(mf) birim fiyati',
                '(mf) birim fiyatı',
                'birim fiyat',
                'birim fiyati',
                'birim fiyatı',
                'guncel fiyat',
                'güncel fiyat',
                'price',
                'unit price',
            ]) ?? 'E';

            $updatedCount = 0;
            $updatedMaterialIds = [];

            foreach ($data as $i => $row) {
                if ($i <= $headerRow) continue; // Header

                $id = trim((string) ($row[$idColumn] ?? ''));
                $code = trim((string) ($row[$codeColumn] ?? ''));
                $name = trim((string) ($row[$nameColumn] ?? ''));
                $newPriceFloat = $this->parseFloat($row[$priceColumn] ?? null);

                if ($newPriceFloat === null) continue;

                $material = null;
                if ($id !== '' && ctype_digit($id)) {
                    $material = Material::where('user_id', Auth::id())->find((int) $id);
                }
                if (!$material && $code !== '') {
                    $material = Material::where('user_id', Auth::id())->where('code', $code)->first();
                }
                if (!$material && $name !== '') {
                    $material = Material::where('user_id', Auth::id())->where('name', $name)->first();
                }

                if ($material) {
                    if ($material->unit_price != $newPriceFloat && $newPriceFloat >= 0) {
                        \App\Models\MaterialPriceHistory::create([
                            'material_id' => $material->id,
                            'user_id' => Auth::id(),
                            'old_price' => $material->unit_price ?? 0,
                            'new_price' => $newPriceFloat,
                            'reason' => 'smart_excel_sync'
                        ]);
                        $material->update([
                            'unit_price' => $newPriceFloat,
                            'last_price_updated_at' => now(),
                        ]);
                        $updatedMaterialIds[] = (int) $material->id;
                        $updatedCount++;
                    }
                }
            }

            $syncSummary = app(RecipeProductCostSyncService::class)->syncActiveRecipesUsingMaterials($updatedMaterialIds, (int) Auth::id(), false);
            session()->flash('success', $this->withRecipeCostSyncMessage("✅ Fiyat senkronizasyonu tamamlandı. Toplam {$updatedCount} malzemenin fiyatı güncellendi.", $syncSummary));
        } catch (\Exception $e) {
            session()->flash('error', '❌ Import hatası: ' . $e->getMessage());
        }

        $this->showPriceSyncModal = false;
        $this->priceSyncFile = null;
    }

    public function exportRecipes()
    {
        $hasRecipeStockCode = Schema::hasColumn('recipes', 'stock_code');

        $recipes = Recipe::where('user_id', Auth::id())
            ->when($this->searchRecipe, function ($q) use ($hasRecipeStockCode) {
                $q->where(function ($subq) use ($hasRecipeStockCode) {
                    $subq->where('name', 'like', '%' . $this->searchRecipe . '%')
                         ->orWhere('version', 'like', '%' . $this->searchRecipe . '%');

                    if ($hasRecipeStockCode) {
                        $subq->orWhere('stock_code', 'like', '%' . $this->searchRecipe . '%');
                    }

                    $subq->orWhereHas('product', function ($pq) {
                        $pq->where('stock_code', 'like', '%' . $this->searchRecipe . '%')
                           ->orWhere('product_name', 'like', '%' . $this->searchRecipe . '%');
                    });
                });
            })
            ->when($this->statusFilterRecipe === 'active', fn($q) => $q->where('status', 'active'))
            ->when($this->statusFilterRecipe === 'inactive', fn($q) => $q->where('status', 'inactive'))
            ->with('product', 'lines.material')
            ->orderByDesc('updated_at')
            ->get();

        if ($recipes->isEmpty()) {
            session()->flash('error', 'Dışa aktarılacak reçete bulunamadı.');
            return;
        }

        $sheets = [];

        // Özet Sayfası
        $summaryData = $recipes->map(function ($recipe) {
            return [
                'Stok Kodu' => $recipe->identity_code,
                'Reçete Adı' => $recipe->name,
                'Versiyon' => $recipe->version,
                'Bağlı Ürün' => $recipe->product ? $recipe->product->product_name : '',
                'Satır Sayısı' => $recipe->lines->count(),
                'Toplam Maliyet' => number_format($recipe->total_cost, 2, ',', ''),
                'Durum' => $recipe->status_label ?? $recipe->status,
                'Oluşturulma' => $recipe->created_at->format('Y-m-d H:i'),
            ];
        })->toArray();

        if (!empty($summaryData)) {
            $sheets[] = ['name' => 'Reçete Özeti', 'data' => $summaryData];
        }

        // Tüm Satırlar (Detay) Sayfası
        $linesData = [];
        foreach ($recipes as $recipe) {
            foreach ($recipe->lines as $line) {
                $material = $line->material;
                $linesData[] = [
                    'Mamul Stok Kodu' => $recipe->identity_code,
                    'Reçete Adı' => $recipe->name,
                    'Reçete Versiyon' => $recipe->version,
                    'Operasyon' => $line->operation_label ?? $line->operation,
                    'Malzeme Kodu' => $material ? $material->code : '',
                    'Malzeme Adı' => $material ? $material->name : '',
                    'Hesaplanan Miktar' => number_format($line->calculated_qty, 4, ',', ''),
                    'Birim' => $line->calculated_unit,
                    'Birim Fiyat' => $material ? number_format($material->unit_price, 2, ',', '') : '',
                    'Toplam Maliyet' => number_format($line->line_cost, 2, ',', ''),
                ];
            }
        }

        if (!empty($linesData)) {
            $sheets[] = ['name' => 'Reçete Satırları', 'data' => $linesData];
        }

        $fileName = 'Receteler_' . date('Y-m-d_H-i') . '.xlsx';
        $path = storage_path('app/public/' . $fileName);

        $excelService = new \App\Services\ExcelService();
        $excelService->exportToXlsx($sheets, $path);

        return response()->download($path)->deleteFileAfterSend(true);
    }

    public function exportProductionTicket(int $id)
    {
        $recipe = Recipe::where('user_id', Auth::id())->with('lines.material', 'product')->findOrFail($id);

        $sheets = [];
        $data = [];

        $groupedLines = $recipe->lines->sortBy('sort_order')->groupBy('operation');

        foreach ($groupedLines as $operation => $lines) {
            $opName = \App\Models\RecipeLine::OPERATIONS[$operation] ?? $operation;
            $data[] = [
                'Operasyon' => "--- $opName ---",
                'Malzeme Kodu' => '',
                'Malzeme Adı' => '',
                'Miktar' => '',
                'Birim' => '',
                'Açıklama' => '',
            ];
            foreach ($lines as $line) {
                $material = $line->material;
                $data[] = [
                    'Operasyon' => '',
                    'Malzeme Kodu' => $material ? $material->code : '-',
                    'Malzeme Adı' => $material ? $material->name : 'Bilinmiyor',
                    'Miktar' => number_format($line->calculated_qty, 4, ',', ''),
                    'Birim' => $line->calculated_unit,
                    'Açıklama' => '',
                ];
            }
        }

        $sheets[] = [
            'name' => 'Üretim Fişi',
            'data' => $data
        ];

        $fileName = 'UretimFisi_' . \Illuminate\Support\Str::slug($recipe->name) . '_' . date('Ymd_Hi') . '.xlsx';
        $path = storage_path('app/public/' . $fileName);

        $excelService = new \App\Services\ExcelService();
        $excelService->exportToXlsx($sheets, $path);

        return response()->download($path)->deleteFileAfterSend(true);
    }

    // ─── Reçete Listesi (Tab) ──────────────────────────────

    public function openQuickViewRecipe(int $id)
    {
        $this->quickViewRecipeId = $id;
        $this->showQuickViewModal = true;
    }

    public function toggleRecipeStatus(int $id)
    {
        $recipe = Recipe::where('user_id', Auth::id())->findOrFail($id);
        $recipe->status = $recipe->status === 'active' ? 'inactive' : 'active';
        $recipe->save();

        if ($recipe->status === 'active') {
            $syncSummary = app(RecipeProductCostSyncService::class)->syncRecipe($recipe->refresh(), false);

            if (($syncSummary['enabled'] ?? false) && (int) ($syncSummary['matched_products'] ?? 0) > 0) {
                session()->flash('success', $this->withRecipeCostSyncMessage('✅ Reçete aktifleştirildi.', $syncSummary));
            }
        }

        if ($this->quickViewRecipeId === $id) {
            unset($this->quickViewRecipe);
        }
    }

    public function createRecipe()
    {
        return redirect()->route('recipe.builder');
    }

    public function editRecipe(int $id)
    {
        return redirect()->route('recipe.builder', ['recipeId' => $id]);
    }

    public function duplicateRecipe(int $id)
    {
        $originalRecipe = Recipe::where('user_id', Auth::id())->with('lines')->findOrFail($id);

        $newRecipe = $originalRecipe->replicate();
        $newRecipe->name = $originalRecipe->name . ' (Kopya)';
        $newRecipe->status = 'inactive';
        $newRecipe->push();

        foreach ($originalRecipe->lines as $line) {
            $newLine = $line->replicate();
            $newLine->recipe_id = $newRecipe->id;
            $newLine->push();
        }

        session()->flash('success', "📋 Reçete başarıyla kopyalandı. Lütfen yeni reçeteyi düzenleyin.");
        return redirect()->route('recipe.builder', ['recipeId' => $newRecipe->id]);
    }

    public function deleteRecipe(int $id)
    {
        $recipe = Recipe::where('user_id', Auth::id())->findOrFail($id);
        $recipe->lines()->delete();
        $recipe->delete();
        session()->flash('success', "🗑️ Reçete silindi.");
    }

    // ─── Fabrika Import ────────────────────────────────────

    public function openFactoryImportModal()
    {
        $this->factoryImportStep = 'ready';
        $this->factoryImportResult = [];
        $this->showFactoryImportModal = true;
    }

    /**
     * İki aşamalı fabrika import başlat
     */
    public function startFactoryImport()
    {
        $this->factoryImportResult = [];

        // Aşama 1: Ham Maddeler
        $this->factoryImportStep = 'importing_materials';
        $materialResult = $this->importMaterialsFromFactory();
        $this->factoryImportResult['materials'] = $materialResult;

        // Aşama 2: Reçeteler
        $this->factoryImportStep = 'importing_recipes';
        $recipeResult = $this->importRecipesFromFactory();
        $this->factoryImportResult['recipes'] = $recipeResult;
        $syncSummary = app(RecipeProductCostSyncService::class)->syncAllForUser((int) Auth::id(), false);

        $this->factoryImportStep = 'done';

        session()->flash('success', $this->withRecipeCostSyncMessage(
            "✅ Fabrika import tamamlandı! " .
            "{$materialResult['imported']} malzeme, {$materialResult['price_updated']} fiyat güncellendi. " .
            "{$recipeResult['recipes_created']} reçete, {$recipeResult['lines_created']} satır oluşturuldu.",
            $syncSummary
        ));
    }

    /**
     * ham_madde_list.json'dan ham madde kartlarını import et
     */
    protected function importMaterialsFromFactory(): array
    {
        $result = ['imported' => 0, 'skipped' => 0, 'price_updated' => 0, 'errors' => 0];

        $jsonPath = base_path('fabrika/ham_madde_list.json');
        if (!file_exists($jsonPath)) {
            $result['errors'] = -1;
            Log::error('Fabrika import: ham_madde_list.json bulunamadı', ['path' => $jsonPath]);
            return $result;
        }

        $json = json_decode(file_get_contents($jsonPath), true);
        $data = $json['data'] ?? [];

        foreach ($data as $row) {
            try {
                $code = trim($row['Stok Kodu'] ?? '');
                $name = trim($row['Stok Adı'] ?? '');
                if (empty($code) || empty($name)) continue;

                $unitPrice = $this->parseFloat($row['(MF) Birim Fiyatı'] ?? null);
                $category = $this->guessCategory($name);
                $unit = $this->guessUnit($name, $category);

                // Kumaş eni tahmin
                $fabricWidth = null;
                if (in_array($category, ['fabric', 'textile'])) {
                    if (preg_match('/(\d+)\s*CM/i', $name, $m)) {
                        $fabricWidth = (float) $m[1];
                    }
                }

                $existing = Material::where('user_id', Auth::id())
                    ->where('code', $code)->first();

                if ($existing) {
                    // Fiyat güncelle (eğer farklıysa)
                    if ($unitPrice && $existing->unit_price != $unitPrice) {
                        \App\Models\MaterialPriceHistory::create([
                            'material_id' => $existing->id,
                            'user_id' => Auth::id(),
                            'old_price' => $existing->unit_price ?? 0,
                            'new_price' => $unitPrice,
                            'reason' => 'factory_json_import'
                        ]);
                        $existing->update(['unit_price' => $unitPrice]);
                        $result['price_updated']++;
                    }
                    $result['skipped']++;
                    continue;
                }

                Material::create([
                    'user_id'            => Auth::id(),
                    'code'               => $code,
                    'name'               => $name,
                    'category'           => $category,
                    'base_unit'          => $unit,
                    'default_waste_rate' => 0.10,
                    'fabric_width_cm'    => $fabricWidth,
                    'unit_price'         => $unitPrice,
                    'currency'           => 'TRY',
                ]);
                $result['imported']++;
            } catch (\Exception $e) {
                $result['errors']++;
                Log::error('Material import satır hatası', ['code' => $code ?? '', 'error' => $e->getMessage()]);
            }
        }

        return $result;
    }

    /**
     * recete_list.json'dan reçeteleri import et
     * Her Mamul Kodu + Reçete No kombinasyonu bir Recipe oluşturur
     * Her satır (Hammadde) bir RecipeLine olur
     */
    protected function importRecipesFromFactory(): array
    {
        $result = ['recipes_created' => 0, 'recipes_skipped' => 0, 'lines_created' => 0, 'materials_created' => 0, 'errors' => 0];

        $jsonPath = base_path('fabrika/recete_list.json');
        if (!file_exists($jsonPath)) {
            $result['errors'] = -1;
            Log::error('Fabrika import: recete_list.json bulunamadı', ['path' => $jsonPath]);
            return $result;
        }

        $json = json_decode(file_get_contents($jsonPath), true);
        $data = $json['data'] ?? [];
        $hasRecipeStockCode = Schema::hasColumn('recipes', 'stock_code');

        // Mamul Kodu + Reçete No bazında grupla
        $grouped = [];
        foreach ($data as $row) {
            $mamulKodu = trim($row['Mamul Kodu'] ?? '');
            $receteNo = trim($row['Reçete No'] ?? '1');
            if (empty($mamulKodu)) continue;

            $key = $mamulKodu . '|' . $receteNo;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'mamul_kodu'   => $mamulKodu,
                    'mamul_adi'    => trim($row['Mamul Açıklaması'] ?? $mamulKodu),
                    'recete_no'    => $receteNo,
                    'recete_tarih' => trim($row['Reçete Tarihi'] ?? ''),
                    'aciklama'     => trim($row['Reçete Açıklaması'] ?? ''),
                    'maliyet'      => $this->parseFloat($row['Reçete Maliyeti'] ?? null),
                    'lines'        => [],
                ];
            }
            $grouped[$key]['lines'][] = $row;
        }

        // Her grup için reçete oluştur
        foreach ($grouped as $key => $group) {
            try {
                // Mükerrer kontrol: stok kodu + versiyon ana kimliktir; eski importlar için isim + versiyon da korunur.
                $recipeName = $group['mamul_adi'];
                $recipeVersion = 'v' . $group['recete_no'];
                $stockCode = $group['mamul_kodu'];

                $existingRecipe = Recipe::where('user_id', Auth::id())
                    ->where('version', $recipeVersion)
                    ->where(function ($query) use ($recipeName, $stockCode, $hasRecipeStockCode) {
                        if ($hasRecipeStockCode) {
                            $query->where('stock_code', $stockCode)
                                ->orWhere(function ($nameQuery) use ($recipeName) {
                                    $nameQuery->where('name', $recipeName)
                                        ->whereNull('stock_code');
                                });
                            return;
                        }

                        $query->where('name', $recipeName);
                    })
                    ->first();

                if ($existingRecipe) {
                    if ($hasRecipeStockCode && empty($existingRecipe->stock_code)) {
                        $existingRecipe->update(['stock_code' => $stockCode]);
                    }
                    $result['recipes_skipped']++;
                    continue;
                }

                $recipeData = [
                    'user_id'    => Auth::id(),
                    'name'       => $recipeName,
                    'version'    => $recipeVersion,
                    'status'     => 'active',
                    'notes'      => $group['aciklama'] ?: ('Import: ' . $group['recete_tarih']),
                ];
                if ($hasRecipeStockCode) {
                    $recipeData['stock_code'] = $stockCode;
                }

                $recipe = Recipe::create($recipeData);
                $result['recipes_created']++;

                $sortOrder = 0;
                foreach ($group['lines'] as $lineRow) {
                    $hmCode = trim($lineRow['Hammadde Kodu'] ?? '');
                    $hmName = trim($lineRow['Hammadde Açıklaması'] ?? '');
                    if (empty($hmCode)) continue;

                    // Malzemeyi bul veya oluştur
                    $material = Material::where('user_id', Auth::id())
                        ->where('code', $hmCode)->first();

                    if (!$material) {
                        $cat = $this->guessCategory($hmName);
                        $un = $this->guessUnit($hmName, $cat);
                        $fWidth = null;
                        if (in_array($cat, ['fabric', 'textile'])) {
                            if (preg_match('/(\d+)\s*CM/i', $hmName, $m)) {
                                $fWidth = (float) $m[1];
                            }
                        }
                        $birimFiyat = $this->parseFloat($lineRow['Birim Fiyat'] ?? null);

                        $material = Material::create([
                            'user_id'            => Auth::id(),
                            'code'               => $hmCode,
                            'name'               => $hmName ?: $hmCode,
                            'category'           => $cat,
                            'base_unit'          => $un,
                            'default_waste_rate' => 0.10,
                            'fabric_width_cm'    => $fWidth,
                            'unit_price'         => $birimFiyat,
                            'currency'           => 'TRY',
                        ]);
                        $result['materials_created']++;
                    }

                    $miktar = $this->parseFloat($lineRow['Miktar'] ?? null) ?? 0;

                    \App\Models\RecipeLine::create([
                        'recipe_id'      => $recipe->id,
                        'material_id'    => $material->id,
                        'operation'      => $this->guessOperationFromMaterial($hmName),
                        'calc_type'      => 'fixed_qty',
                        'constant_qty'   => $miktar,
                        'calculated_qty' => $miktar,
                        'calculated_unit' => $material->base_unit,
                        'sort_order'     => $sortOrder++,
                    ]);
                    $result['lines_created']++;
                }
            } catch (\Exception $e) {
                $result['errors']++;
                Log::error('Recipe import hatası', ['key' => $key, 'error' => $e->getMessage()]);
            }
        }

        return $result;
    }

    /**
     * Malzeme adından operasyon grubu tahmin et (reçete listesi import için)
     */
    private function guessOperationFromMaterial(string $name): string
    {
        $n = mb_strtolower($name);
        if (str_contains($n, 'kumaş') || str_contains($n, 'kadife') || str_contains($n, 'peluş')
            || str_contains($n, 'welsoft') || str_contains($n, 'tela') || str_contains($n, 'astar')
            || str_contains($n, 'iplik') || str_contains($n, 'fermuar') || str_contains($n, 'kaytan')
            || str_contains($n, 'elkamet') || str_contains($n, 'fitil')) return 'terzihane';
        if (str_contains($n, 'sünger') || str_contains($n, 'danste') || str_contains($n, 'elyaf')
            || str_contains($n, 'vatka')) return 'sunger';
        if (str_contains($n, 'panel') || str_contains($n, 'mdf') || str_contains($n, 'sunta')
            || str_contains($n, 'duralit') || str_contains($n, 'ahşap') || str_contains($n, 'kereste')
            || str_contains($n, 'iskelet')) return 'marangoz';
        if (str_contains($n, 'demir') || str_contains($n, 'civata') || str_contains($n, 'somun')
            || str_contains($n, 'vida') || str_contains($n, 'pul') || str_contains($n, 'rondela')
            || str_contains($n, 'zımba') || str_contains($n, 'saplama') || str_contains($n, 'perçin')
            || str_contains($n, 'mekanizma')) return 'demirhane';
        if (str_contains($n, 'naylon') || str_contains($n, 'ambalaj') || str_contains($n, 'karton')
            || str_contains($n, 'koli') || str_contains($n, 'streç') || str_contains($n, 'balonlu')
            || str_contains($n, 'çember') || str_contains($n, 'etiket')) return 'paketleme';
        if (str_contains($n, 'yapıştırıcı') || str_contains($n, 'tutkal')
            || str_contains($n, 'döşeme') || str_contains($n, 'elastik') || str_contains($n, 'kolon')) return 'doseme';
        if (str_contains($n, 'ayak') || str_contains($n, 'bağlantı')) return 'marangoz';
        if (str_contains($n, 'işçilik') || str_contains($n, 'kesim')) return 'diger';
        return 'diger';
    }

    // ─── Yardımcı ──────────────────────────────────────────

    private function resetForm()
    {
        $this->formCode = '';
        $this->formName = '';
        $this->formCategory = 'other';
        $this->formUnit = 'pcs';
        $this->formWasteRate = 0.10;
        $this->formFabricWidth = null;
        $this->formFabricMethod = 'area_div_width';
        $this->formDensity = null;
        $this->formThickness = null;
        $this->formRoundingMode = 'none';
        $this->formRoundingStep = null;
        $this->formUnitPrice = null;
        $this->formCurrency = 'TRY';
        $this->formSupplier = '';
        $this->formNotes = '';
        $this->formTags = '';
        $this->formImage = null;
        $this->formImagePreview = null;
        $this->resetErrorBag();
    }

    private function withRecipeCostSyncMessage(string $message, array $summary): string
    {
        if (($summary['enabled'] ?? false) && (int) ($summary['matched_products'] ?? 0) > 0) {
            return $message . " Reçete maliyeti {$summary['matched_products']} stok kartına işlendi.";
        }

        return $message;
    }

    // ─── Vendor Sync İşlemleri (Faz 2) ─────────────────────

    public function openVendorSyncModal()
    {
        $this->vendorSyncFile = null;
        $this->vendorSyncPreview = [];
        $this->vendorSyncReady = false;
        $this->showVendorSyncModal = true;
    }

    public function updatedVendorSyncFile()
    {
        $this->validate([
            'vendorSyncFile' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $path = $this->vendorSyncFile->getRealPath();
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        if (count($rows) < 2) {
            $this->addError('vendorSyncFile', 'Dosya boş veya çok az satır var.');
            return;
        }

        $headers = array_map('mb_strtolower', $rows[0]);
        $codeIndex = -1;
        $nameIndex = -1;
        $priceIndex = -1;

        foreach ($headers as $i => $header) {
            if (str_contains($header, 'kod')) $codeIndex = $i;
            if (str_contains($header, 'ad') || str_contains($header, 'isim')) $nameIndex = $i;
            if (str_contains($header, 'fiyat')) $priceIndex = $i;
        }

        if ($codeIndex === -1 && $nameIndex === -1) {
            $this->addError('vendorSyncFile', 'Excel dosyasında "Kod" veya "Ad" sütunu bulunamadı.');
            return;
        }
        if ($priceIndex === -1) {
            $this->addError('vendorSyncFile', 'Excel dosyasında "Fiyat" sütunu bulunamadı.');
            return;
        }

        $preview = [];
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $code = $codeIndex !== -1 ? trim($row[$codeIndex] ?? '') : '';
            $name = $nameIndex !== -1 ? trim($row[$nameIndex] ?? '') : '';
            $price = $priceIndex !== -1 ? $this->parseFloat((string)($row[$priceIndex] ?? '')) : 0;

            if ((empty($code) && empty($name)) || empty($price)) continue;

            $material = null;
            if (!empty($code)) {
                $material = Material::where('user_id', Auth::id())->where('code', $code)->first();
            }
            if (!$material && !empty($name)) {
                $material = Material::where('user_id', Auth::id())->where('name', 'like', "%{$name}%")->first();
            }

            if ($material) {
                if ($material->unit_price != $price) {
                    $preview[] = [
                        'id' => $material->id,
                        'code' => $material->code,
                        'name' => $material->name,
                        'old_price' => $material->unit_price,
                        'new_price' => $price,
                    ];
                }
            }
        }

        $this->vendorSyncPreview = $preview;
        $this->vendorSyncReady = true;
    }

    public function processVendorSync()
    {
        if (empty($this->vendorSyncPreview)) return;

        $count = 0;
        $updatedMaterialIds = [];
        foreach ($this->vendorSyncPreview as $item) {
            $material = Material::where('user_id', Auth::id())->find($item['id']);
            if ($material) {
                \App\Models\MaterialPriceHistory::create([
                    'material_id' => $material->id,
                    'user_id' => Auth::id(),
                    'old_price' => $material->unit_price ?? 0,
                    'new_price' => $item['new_price'],
                    'reason' => 'vendor_sync'
                ]);
                $material->update(['unit_price' => $item['new_price']]);
                $updatedMaterialIds[] = (int) $material->id;
                $count++;
            }
        }

        $syncSummary = app(RecipeProductCostSyncService::class)->syncActiveRecipesUsingMaterials($updatedMaterialIds, (int) Auth::id(), false);
        session()->flash('success', $this->withRecipeCostSyncMessage("✅ {$count} malzemenin fiyatı tedarikçi listesinden güncellendi.", $syncSummary));
        $this->showVendorSyncModal = false;
        $this->vendorSyncFile = null;
        $this->vendorSyncPreview = [];
    }

    // ─── Render ────────────────────────────────────────────

    public function render()
    {
        $hasRecipeStockCode = Schema::hasColumn('recipes', 'stock_code');
        $recipeSortField = in_array($this->recipeSortField, ['updated_at', 'stock_code', 'name'], true)
            ? $this->recipeSortField
            : 'updated_at';
        if ($recipeSortField === 'stock_code' && !$hasRecipeStockCode) {
            $recipeSortField = 'updated_at';
        }
        $recipeSortDirection = $this->recipeSortDirection === 'asc' ? 'asc' : 'desc';

        $materials = Material::where('user_id', Auth::id())
            ->with('latestPriceHistory')
            ->search($this->search)
            ->byCategory($this->categoryFilter)
            ->byUnit($this->unitFilter)
            ->when($this->statusFilter === 'active', fn($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn($q) => $q->where('is_active', false))
            ->when($this->supplierFilter !== 'all', fn($q) => $q->where('supplier', $this->supplierFilter))
            ->orderBy('category')
            ->orderBy('code')
            ->paginate($this->perPage);

        $suppliers = Material::where('user_id', Auth::id())
            ->whereNotNull('supplier')
            ->where('supplier', '!=', '')
            ->distinct()
            ->pluck('supplier')
            ->toArray();

        $recipes = Recipe::where('user_id', Auth::id())
            ->when($this->searchRecipe, function ($q) use ($hasRecipeStockCode) {
                $q->where(function ($subq) use ($hasRecipeStockCode) {
                    $subq->where('name', 'like', '%' . $this->searchRecipe . '%')
                         ->orWhere('version', 'like', '%' . $this->searchRecipe . '%');

                    if ($hasRecipeStockCode) {
                        $subq->orWhere('stock_code', 'like', '%' . $this->searchRecipe . '%');
                    }

                    $subq->orWhereHas('product', function ($pq) {
                        $pq->where('stock_code', 'like', '%' . $this->searchRecipe . '%')
                           ->orWhere('product_name', 'like', '%' . $this->searchRecipe . '%');
                    });
                });
            })
            ->when($this->statusFilterRecipe === 'active', fn($q) => $q->where('status', 'active'))
            ->when($this->statusFilterRecipe === 'inactive', fn($q) => $q->where('status', 'inactive'))
            ->when($this->showRiskOnlyRecipe, function ($q) {
                $q->whereHas('lines', function ($lq) {
                    $lq->whereHas('material', function ($mq) {
                        $mq->where('unit_price', '<=', 0)
                           ->orWhere('is_active', false);
                    })->orWhereNull('material_id');
                });
            })
            ->with('product', 'lines.material')
            ->orderBy($recipeSortField, $recipeSortDirection)
            ->paginate($this->perPage);

        $riskCount = Recipe::where('user_id', Auth::id())
            ->whereHas('lines', function ($q) {
                $q->whereHas('material', function ($mq) {
                    $mq->where('unit_price', '<=', 0)
                       ->orWhere('is_active', false);
                })->orWhereNull('material_id');
            })->count();

        $stats = [
            'total'      => Material::where('user_id', Auth::id())->count(),
            'fabric'     => Material::where('user_id', Auth::id())->where('category', 'fabric')->count(),
            'foam'       => Material::where('user_id', Auth::id())->where('category', 'foam')->count(),
            'wood'       => Material::where('user_id', Auth::id())->where('category', 'wood')->count(),
            'hardware'   => Material::where('user_id', Auth::id())->where('category', 'hardware')->count(),
            'recipes'    => $recipes->total(), // since we use paginate, total is correct
            'risk_recipes' => $riskCount,
        ];

        return view('livewire.recipe-materials-manager', [
            'materials' => $materials,
            'suppliers' => $suppliers,
            'recipes'   => $recipes,
            'stats'     => $stats,
        ])->layout('layouts.app');
    }
}
