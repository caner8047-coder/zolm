<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Recipe;

class ProductionPlanner extends Component
{
    public array $productionLines = [];
    public array $recipeSearchResults = [];
    public ?int $searchingIndex = null;

    public array $masterBom = [];

    public function mount()
    {
        $this->addLine();
    }

    public function addLine()
    {
        $this->productionLines[] = [
            'recipe_id' => null,
            'recipe_name' => '',
            'quantity' => 1,
        ];
    }

    public function removeLine(int $index)
    {
        unset($this->productionLines[$index]);
        $this->productionLines = array_values($this->productionLines);
        $this->calculateMasterBom();
    }

    public function searchRecipe(int $index, string $term)
    {
        $this->searchingIndex = $index;
        if (strlen($term) < 2) {
            $this->recipeSearchResults = [];
            return;
        }

        $recipes = Recipe::where('user_id', auth()->id())
            ->where('status', 'active')
            ->where('name', 'like', "%{$term}%")
            ->limit(10)
            ->get(['id', 'name', 'version']);

        $this->recipeSearchResults = $recipes->map(function ($recipe) {
            return [
                'id' => $recipe->id,
                'name' => $recipe->name,
                'version' => $recipe->version,
                'total_cost' => $recipe->total_cost,
            ];
        })->toArray();
    }

    public function selectRecipe(int $index, int $recipeId)
    {
        $recipe = Recipe::find($recipeId);
        if (!$recipe || !isset($this->productionLines[$index])) return;

        $this->productionLines[$index]['recipe_id'] = $recipe->id;
        $this->productionLines[$index]['recipe_name'] = $recipe->name . ' (' . $recipe->version . ')';

        $this->recipeSearchResults = [];
        $this->searchingIndex = null;

        $this->calculateMasterBom();
    }

    public function updatedProductionLines()
    {
        $this->calculateMasterBom();
    }

    public function calculateMasterBom()
    {
        $master = collect();

        foreach ($this->productionLines as $line) {
            if (empty($line['recipe_id']) || $line['quantity'] <= 0) continue;

            $recipe = Recipe::with('lines.material', 'lines.subRecipe')->find($line['recipe_id']);
            if (!$recipe) continue;

            // Reçetenin tüm alt reçeteleri dahil düzleştirilmiş satırlarını hedef miktar ile çarpıyoruz
            $flatLines = $recipe->getFlatLines((float)$line['quantity']);
            $master = $master->merge($flatLines);
        }

        // Konsolide et
        $this->masterBom = $master->groupBy('material_id')->map(function ($group) {
            $first = $group->first();
            if (!$first || !$first->material) return null;

            $totalQty = $group->sum('calculated_qty');
            $unitPrice = $first->material->unit_price ?? 0;

            return [
                'material_id'   => $first->material_id,
                'material_code' => $first->material->code,
                'material_name' => $first->material->name,
                'category'      => $first->material->category,
                'total_qty'     => $totalQty,
                'unit'          => $first->calculated_unit,
                'operations'    => $group->pluck('operation')->unique()->values()->toArray(),
                'unit_price'    => $unitPrice,
                'total_cost'    => $totalQty * $unitPrice,
            ];
        })->filter()->sortBy('material_code')->values()->toArray();
    }

    public function render()
    {
        return view('livewire.production-planner')
            ->layout('layouts.app');
    }
}
