<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Material;
use App\Models\Recipe;
use App\Models\RecipeLine;
use App\Models\MpProduct;
use App\Services\RecipeCalculationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RecipeBuilder extends Component
{
    // ─── Reçete Bilgisi ────────────────────────────────────
    public ?int $recipeId = null;
    public string $recipeName = '';
    public string $version = 'v1';
    public string $status = 'draft';
    public string $notes = '';
    public ?int $selectedProductId = null;
    public string $productSearch = '';
    public array $productResults = [];

    // ─── Satırlar ──────────────────────────────────────────
    public array $lines = [];
    public string $viewMode = 'all'; // all, operation, bom

    // ─── Malzeme Arama (satır içi) ─────────────────────────
    public array $materialSearchResults = [];
    public ?int $searchingLineIndex = null;

    // ─── Quick-create Material Modal ───────────────────────
    public bool $showQuickMaterialModal = false;
    public string $qmCode = '';
    public string $qmName = '';
    public string $qmCategory = 'other';
    public string $qmUnit = 'pcs';
    public float $qmWasteRate = 0.10;
    public ?float $qmFabricWidth = null;
    public ?int $qmForLineIndex = null;

    // ─── Hesap Sonuçları ───────────────────────────────────
    public array $calculationResults = [];

    public function mount(?int $recipeId = null)
    {
        $this->recipeId = $recipeId;

        if ($recipeId) {
            $recipe = Recipe::where('user_id', Auth::id())
                ->with('lines.material', 'product')
                ->findOrFail($recipeId);

            $this->recipeName = $recipe->name;
            $this->version = $recipe->version;
            $this->status = $recipe->status;
            $this->notes = $recipe->notes ?? '';
            $this->selectedProductId = $recipe->mp_product_id;

            if ($recipe->product) {
                $this->productSearch = $recipe->product->product_name;
            }

            $this->lines = $recipe->lines->map(fn($l) => [
                'id'                    => $l->id,
                'material_id'           => $l->material_id,
                'material_label'        => $l->material ? "{$l->material->code} — {$l->material->name}" : '',
                'unit_price'            => $l->material->unit_price ?? 0,
                'operation'             => $l->operation,
                'usage_area'            => $l->usage_area ?? '',
                'calc_type'             => $l->calc_type,
                'width_cm'              => $l->width_cm,
                'length_cm'             => $l->length_cm,
                'height_cm'             => $l->height_cm,
                'pieces'                => $l->pieces,
                'waste_rate_override'   => $l->waste_rate_override,
                'fabric_width_override' => $l->fabric_width_override,
                'constant_qty'          => $l->constant_qty,
                'calculated_qty'        => $l->calculated_qty,
                'calculated_unit'       => $l->calculated_unit,
                'notes'                 => $l->notes ?? '',
            ])->toArray();

            $this->recalculateAll();
        }
    }

    // ─── Ürün Arama ────────────────────────────────────────

    public function updatedProductSearch()
    {
        if (strlen($this->productSearch) < 2) {
            $this->productResults = [];
            return;
        }
        $this->productResults = MpProduct::where('user_id', Auth::id())
            ->search($this->productSearch)
            ->limit(10)
            ->get(['id', 'product_name', 'stock_code', 'barcode'])
            ->toArray();
    }

    public function selectProduct(int $id)
    {
        $product = MpProduct::find($id);
        if ($product) {
            $this->selectedProductId = $id;
            $this->productSearch = $product->product_name;
            if (empty($this->recipeName)) {
                $this->recipeName = $product->product_name . ' Reçetesi';
            }
        }
        $this->productResults = [];
    }

    public function clearProduct()
    {
        $this->selectedProductId = null;
        $this->productSearch = '';
    }

    // ─── Satır İşlemleri ───────────────────────────────────

    public function addLine()
    {
        $this->lines[] = [
            'id'                    => null,
            'material_id'           => null,
            'material_label'        => '',
            'unit_price'            => 0,
            'operation'             => 'terzihane',
            'usage_area'            => '',
            'calc_type'             => 'fabric_meter',
            'width_cm'              => null,
            'length_cm'             => null,
            'height_cm'             => null,
            'pieces'                => 1,
            'waste_rate_override'   => null,
            'fabric_width_override' => null,
            'constant_qty'          => null,
            'calculated_qty'        => 0,
            'calculated_unit'       => 'm',
            'notes'                 => '',
        ];
    }

    public function removeLine(int $index)
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
        unset($this->calculationResults[$index]);
    }

    public function duplicateLine(int $index)
    {
        if (isset($this->lines[$index])) {
            $copy = $this->lines[$index];
            $copy['id'] = null;
            $this->lines[] = $copy;
        }
    }

    public function moveLine(int $index, string $direction)
    {
        $newIndex = $direction === 'up' ? $index - 1 : $index + 1;
        if ($newIndex < 0 || $newIndex >= count($this->lines)) return;
        [$this->lines[$index], $this->lines[$newIndex]] = [$this->lines[$newIndex], $this->lines[$index]];
        $this->lines = array_values($this->lines);
    }

    // ─── Malzeme Seçimi (satır içi) ────────────────────────

    public function searchMaterial(int $lineIndex, string $term)
    {
        $this->searchingLineIndex = $lineIndex;
        if (strlen($term) < 2) {
            $this->materialSearchResults = [];
            return;
        }
        $this->materialSearchResults = Material::where('user_id', Auth::id())
            ->active()
            ->search($term)
            ->limit(10)
            ->get(['id', 'code', 'name', 'category', 'base_unit', 'fabric_width_cm'])
            ->toArray();
    }

    public function selectMaterial(int $lineIndex, int $materialId)
    {
        $material = Material::find($materialId);
        if (!$material || !isset($this->lines[$lineIndex])) return;

        $this->lines[$lineIndex]['material_id'] = $materialId;
        $this->lines[$lineIndex]['material_label'] = "{$material->code} — {$material->name}";
        $this->lines[$lineIndex]['unit_price'] = $material->unit_price ?? 0;

        // Hesap tipini malzeme kategorisine göre otomatik ayarla
        $this->lines[$lineIndex]['calc_type'] = match ($material->category) {
            'fabric'   => 'fabric_meter',
            'foam'     => 'volume_m3',
            'wood'     => str_contains(mb_strtolower($material->name), 'm2') ? 'area_m2' : 'fixed_qty',
            'textile', 'lining' => 'fabric_meter',
            default    => 'fixed_qty',
        };

        $this->materialSearchResults = [];
        $this->searchingLineIndex = null;
        $this->recalculateLine($lineIndex);
    }

    // ─── Hızlı Malzeme Oluşturma ──────────────────────────

    public function openQuickMaterialModal(?int $lineIndex = null)
    {
        $this->qmForLineIndex = $lineIndex;
        $this->qmCode = '';
        $this->qmName = '';
        $this->qmCategory = 'other';
        $this->qmUnit = 'pcs';
        $this->qmWasteRate = 0.10;
        $this->qmFabricWidth = null;
        $this->showQuickMaterialModal = true;
    }

    public function saveQuickMaterial()
    {
        $this->validate([
            'qmCode' => 'required|max:50',
            'qmName' => 'required|max:255',
        ]);

        $exists = Material::where('user_id', Auth::id())->where('code', $this->qmCode)->exists();
        if ($exists) {
            $this->addError('qmCode', 'Bu stok kodu zaten mevcut.');
            return;
        }

        $material = Material::create([
            'user_id'            => Auth::id(),
            'code'               => $this->qmCode,
            'name'               => $this->qmName,
            'category'           => $this->qmCategory,
            'base_unit'          => $this->qmUnit,
            'default_waste_rate' => $this->qmWasteRate,
            'fabric_width_cm'    => $this->qmCategory === 'fabric' ? $this->qmFabricWidth : null,
        ]);

        $this->showQuickMaterialModal = false;

        // Satıra otomatik ata
        if ($this->qmForLineIndex !== null && isset($this->lines[$this->qmForLineIndex])) {
            $this->selectMaterial($this->qmForLineIndex, $material->id);
        }
    }

    // ─── Hesaplama ─────────────────────────────────────────

    public function recalculateLine(int $index)
    {
        if (!isset($this->lines[$index]) || !$this->lines[$index]['material_id']) return;

        $lineData = $this->lines[$index];
        $material = Material::find($lineData['material_id']);
        if (!$material) return;

        // Geçici RecipeLine oluştur (DB'ye kaydetmeden hesap yapmak için)
        $tempLine = new RecipeLine($lineData);
        $tempLine->setRelation('material', $material);

        $service = new RecipeCalculationService();
        $result = $service->calculateLine($tempLine);

        $this->lines[$index]['calculated_qty'] = round($result['qty'], 6);
        $this->lines[$index]['calculated_unit'] = $result['unit'];
        $this->calculationResults[$index] = $result['debug'];
    }

    public function recalculateAll()
    {
        foreach ($this->lines as $i => $line) {
            if ($line['material_id']) {
                $this->recalculateLine($i);
            }
        }
    }

    public function updatedLines($value, $key)
    {
        // lines.0.width_cm gibi güncellemelerde otomatik hesapla
        $parts = explode('.', $key);
        if (count($parts) >= 2) {
            $index = (int) $parts[0];
            $field = $parts[1] ?? '';
            $calcFields = ['width_cm', 'length_cm', 'height_cm', 'pieces', 'waste_rate_override', 'fabric_width_override', 'constant_qty', 'calc_type'];
            if (in_array($field, $calcFields)) {
                $this->recalculateLine($index);
            }
        }
    }

    // ─── Kaydet ────────────────────────────────────────────

    public function saveRecipe()
    {
        $this->validate([
            'recipeName' => 'required|max:255',
        ]);

        DB::beginTransaction();
        try {
            $recipeData = [
                'user_id'       => Auth::id(),
                'mp_product_id' => $this->selectedProductId,
                'name'          => $this->recipeName,
                'version'       => $this->version,
                'status'        => $this->status,
                'notes'         => $this->notes ?: null,
            ];

            if ($this->recipeId) {
                $recipe = Recipe::where('user_id', Auth::id())->findOrFail($this->recipeId);
                $recipe->update($recipeData);
                // Mevcut satırları sil, yenilerini ekle
                $recipe->lines()->delete();
            } else {
                $recipe = Recipe::create($recipeData);
                $this->recipeId = $recipe->id;
            }

            // Satırları kaydet
            foreach ($this->lines as $i => $lineData) {
                if (!$lineData['material_id']) continue;

                RecipeLine::create([
                    'recipe_id'             => $recipe->id,
                    'material_id'           => $lineData['material_id'],
                    'operation'             => $lineData['operation'],
                    'usage_area'            => $lineData['usage_area'] ?: null,
                    'calc_type'             => $lineData['calc_type'],
                    'width_cm'              => $lineData['width_cm'],
                    'length_cm'             => $lineData['length_cm'],
                    'height_cm'             => $lineData['height_cm'],
                    'pieces'                => $lineData['pieces'] ?? 1,
                    'waste_rate_override'   => $lineData['waste_rate_override'],
                    'fabric_width_override' => $lineData['fabric_width_override'],
                    'constant_qty'          => $lineData['constant_qty'],
                    'calculated_qty'        => $lineData['calculated_qty'] ?? 0,
                    'calculated_unit'       => $lineData['calculated_unit'] ?? 'pcs',
                    'notes'                 => $lineData['notes'] ?: null,
                    'sort_order'            => $i,
                ]);
            }

            // Hesapları güncelle
            $service = new RecipeCalculationService();
            $service->calculateRecipe($recipe);

            DB::commit();
            session()->flash('success', '✅ Reçete kaydedildi.');
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', '❌ Hata: ' . $e->getMessage());
        }
    }

    public function activateRecipe()
    {
        if (!$this->recipeId) {
            $this->saveRecipe();
        }

        $recipe = Recipe::with('lines.material')->find($this->recipeId);
        if (!$recipe) return;

        // Aynı ürünün diğer aktif reçetelerini arşivle
        if ($recipe->mp_product_id) {
            Recipe::where('mp_product_id', $recipe->mp_product_id)
                ->where('id', '!=', $recipe->id)
                ->where('status', 'active')
                ->update(['status' => 'archived']);
        }

        $recipe->update(['status' => 'active']);
        $this->status = 'active';

        // COGS Senkronizasyonu: Bağlı ürünün maliyetini güncelle
        if ($recipe->mp_product_id) {
            $totalCost = $recipe->total_cost;
            if ($totalCost > 0) {
                MpProduct::where('id', $recipe->mp_product_id)
                    ->update(['cogs' => round($totalCost, 2)]);
                session()->flash('success', '✅ Reçete aktifleştirildi ve ürün COGS güncellendi: ' . number_format($totalCost, 2) . ' ₺');
            } else {
                session()->flash('success', '✅ Reçete aktifleştirildi.');
            }
        } else {
            session()->flash('success', '✅ Reçete aktifleştirildi.');
        }
    }

    public function duplicateRecipe()
    {
        if (!$this->recipeId) return;
        $recipe = Recipe::where('user_id', Auth::id())->findOrFail($this->recipeId);
        $copy = $recipe->duplicate();
        return redirect()->route('recipe.builder', ['recipeId' => $copy->id]);
    }

    // ─── BOM Çıktısı ──────────────────────────────────────

    public function getConsolidatedBomProperty()
    {
        if (!$this->recipeId) return collect();
        $recipe = Recipe::with('lines.material')->find($this->recipeId);
        return $recipe ? $recipe->getConsolidatedBom() : collect();
    }

    public function getOperationGroupsProperty()
    {
        return collect($this->lines)->groupBy('operation');
    }

    /**
     * Toplam reçete maliyeti (tüm satır maliyetlerinin toplamı)
     */
    public function getTotalCostProperty(): float
    {
        return collect($this->lines)->sum(function ($line) {
            $unitPrice = (float) ($line['unit_price'] ?? 0);
            $qty = (float) ($line['calculated_qty'] ?? 0);
            return $unitPrice * $qty;
        });
    }

    /**
     * Operasyon bazlı maliyet dağılımı
     */
    public function getOperationCostsProperty(): array
    {
        return collect($this->lines)
            ->groupBy('operation')
            ->map(function ($group, $op) {
                $cost = $group->sum(function ($line) {
                    return (float) ($line['unit_price'] ?? 0) * (float) ($line['calculated_qty'] ?? 0);
                });
                return [
                    'label' => RecipeLine::OPERATIONS[$op] ?? $op,
                    'cost'  => $cost,
                    'count' => $group->count(),
                ];
            })
            ->filter(fn($g) => $g['cost'] > 0)
            ->toArray();
    }

    // ─── Render ────────────────────────────────────────────

    public function render()
    {
        return view('livewire.recipe-builder')
            ->layout('layouts.app');
    }
}
