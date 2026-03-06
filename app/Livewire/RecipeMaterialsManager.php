<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\Material;
use App\Models\Recipe;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class RecipeMaterialsManager extends Component
{
    use WithPagination, WithFileUploads;

    // ─── Filtre & Arama ────────────────────────────────────
    public string $search = '';
    public string $categoryFilter = 'all';
    public string $unitFilter = 'all';
    public string $statusFilter = 'active'; // active, inactive, all
    public int $perPage = 25;

    // ─── Modal State ───────────────────────────────────────
    public bool $showCreateModal = false;
    public bool $showImportModal = false;
    public ?int $editingMaterialId = null;

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

    // ─── Toplu İşlem ───────────────────────────────────────
    public array $selectedMaterials = [];
    public bool $selectAll = false;
    public string $bulkAction = '';
    public ?float $bulkWasteRate = null;
    public ?string $bulkCategory = null;

    // ─── Import ────────────────────────────────────────────
    public $importFile;

    // ─── Reçete Import ─────────────────────────────────────
    public bool $showRecipeImportModal = false;
    public $recipeImportFile;
    public string $recipeImportName = '';

    // ─── Tab (Malzeme / Reçeteler) ─────────────────────────
    public string $activeTab = 'materials'; // materials, recipes

    protected $queryString = ['search', 'categoryFilter', 'activeTab'];

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter()
    {
        $this->resetPage();
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
        ];

        if ($this->editingMaterialId) {
            $material = Material::where('user_id', Auth::id())->findOrFail($this->editingMaterialId);
            $material->update($data);
            session()->flash('success', "✅ {$material->code} güncellendi.");
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

            $imported = 0;
            $skipped = 0;

            foreach ($data as $i => $row) {
                if ($i <= 1) continue; // Header atla

                $code = trim($row['A'] ?? $row['B'] ?? '');
                $name = trim($row['B'] ?? $row['C'] ?? '');
                if (empty($code) || empty($name)) continue;

                // Kategoriyi addan tahmin et
                $category = $this->guessCategory($name);
                $unit = $this->guessUnit($name, $category);

                // Kumaş eni tahmin (ad'da "XXX CM" veya "XXXCM" varsa)
                $fabricWidth = null;
                if ($category === 'fabric' || $category === 'textile') {
                    if (preg_match('/(\d+)\s*CM/i', $name, $m)) {
                        $fabricWidth = (float) $m[1];
                    }
                }

                $exists = Material::where('user_id', Auth::id())
                    ->where('code', $code)->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                Material::create([
                    'user_id'          => Auth::id(),
                    'code'             => $code,
                    'name'             => $name,
                    'category'         => $category,
                    'base_unit'        => $unit,
                    'default_waste_rate' => 0.10,
                    'fabric_width_cm'  => $fabricWidth,
                ]);
                $imported++;
            }

            session()->flash('success', "✅ {$imported} malzeme eklendi" . ($skipped ? ", {$skipped} mükerrer atlandı." : "."));
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

    private function parseFloat($val): ?float
    {
        if ($val === null || $val === '') return null;
        $v = (float) $val;
        return $v != 0 ? $v : null;
    }

    // ─── Reçete Listesi (Tab) ──────────────────────────────

    public function createRecipe()
    {
        return redirect()->route('recipe.builder');
    }

    public function editRecipe(int $id)
    {
        return redirect()->route('recipe.builder', ['recipeId' => $id]);
    }

    public function deleteRecipe(int $id)
    {
        $recipe = Recipe::where('user_id', Auth::id())->findOrFail($id);
        $recipe->lines()->delete();
        $recipe->delete();
        session()->flash('success', "🗑️ Reçete silindi.");
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
        $this->resetErrorBag();
    }

    // ─── Render ────────────────────────────────────────────

    public function render()
    {
        $materials = Material::where('user_id', Auth::id())
            ->search($this->search)
            ->byCategory($this->categoryFilter)
            ->byUnit($this->unitFilter)
            ->when($this->statusFilter === 'active', fn($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn($q) => $q->where('is_active', false))
            ->orderBy('category')
            ->orderBy('code')
            ->paginate($this->perPage);

        $recipes = Recipe::where('user_id', Auth::id())
            ->with('product', 'lines.material')
            ->orderByDesc('updated_at')
            ->get();

        $stats = [
            'total'      => Material::where('user_id', Auth::id())->count(),
            'fabric'     => Material::where('user_id', Auth::id())->where('category', 'fabric')->count(),
            'foam'       => Material::where('user_id', Auth::id())->where('category', 'foam')->count(),
            'wood'       => Material::where('user_id', Auth::id())->where('category', 'wood')->count(),
            'hardware'   => Material::where('user_id', Auth::id())->where('category', 'hardware')->count(),
            'recipes'    => $recipes->count(),
        ];

        return view('livewire.recipe-materials-manager', [
            'materials' => $materials,
            'recipes'   => $recipes,
            'stats'     => $stats,
        ])->layout('layouts.app');
    }
}
