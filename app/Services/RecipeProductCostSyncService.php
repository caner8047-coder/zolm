<?php

namespace App\Services;

use App\Models\MpProduct;
use App\Models\Recipe;
use Illuminate\Support\Collection;

class RecipeProductCostSyncService
{
    public function isEnabled(?int $userId = null): bool
    {
        return (new MpSettingsService($userId))->getBool('marketplace_products.recipe_cost_sync_enabled', false);
    }

    public function syncRecipe(Recipe $recipe, bool $force = false): array
    {
        $userId = (int) $recipe->user_id;

        if (!$force && !$this->isEnabled($userId)) {
            return $this->summary(enabled: false);
        }

        if ($recipe->status !== 'active') {
            return $this->summary(skipped: 1);
        }

        $recipe->loadMissing(['product', 'lines.material', 'lines.subRecipe.lines.material']);

        $totalCost = round((float) $recipe->total_cost, 2);
        if ($totalCost <= 0) {
            return $this->summary(skipped: 1);
        }

        $stockCode = trim((string) ($recipe->stock_code ?: $recipe->product?->stock_code));
        $productId = $recipe->mp_product_id ? (int) $recipe->mp_product_id : null;

        if (!$productId && $stockCode === '') {
            return $this->summary(skipped: 1);
        }

        $productIds = MpProduct::query()
            ->where('user_id', $userId)
            ->where(function ($query) use ($productId, $stockCode) {
                if ($productId) {
                    $query->where('mp_products.id', $productId);
                }

                if ($stockCode !== '') {
                    if ($productId) {
                        $query->orWhere('mp_products.stock_code', $stockCode);
                    } else {
                        $query->where('mp_products.stock_code', $stockCode);
                    }
                }
            })
            ->pluck('id');

        if ($productIds->isEmpty()) {
            return $this->summary(matched: 0, skipped: 1);
        }

        $productIdArray = $productIds
            ->map(static fn ($id) => (int) $id)
            ->values()
            ->all();
        $logger = app(MpProductChangeLogger::class);
        $beforeSnapshots = $logger->productSnapshotsForIds($userId, $productIdArray);
        $batchId = $logger->batchId('recipe_cost_sync');

        $updated = MpProduct::query()
            ->whereIn('id', $productIds)
            ->update(['cogs' => $totalCost]);

        $logger->logProductChangesForIds(
            $productIdArray,
            $beforeSnapshots,
            'recipe_cost_sync',
            null,
            'Aktif reçete toplam maliyeti',
            $batchId,
            ['recipe_id' => $recipe->id]
        );

        app(ProductCompositionResolver::class)->refreshParentSetsForProductIds($productIds);

        return $this->summary(
            matched: $productIds->count(),
            updated: $updated,
            recipes: 1,
            totalCost: $totalCost,
        );
    }

    public function syncAllForUser(int $userId, bool $force = false): array
    {
        if (!$force && !$this->isEnabled($userId)) {
            return $this->summary(enabled: false);
        }

        $summary = $this->summary();

        Recipe::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->with(['product', 'lines.material', 'lines.subRecipe.lines.material'])
            ->chunkById(100, function (Collection $recipes) use (&$summary) {
                foreach ($recipes as $recipe) {
                    $summary = $this->mergeSummary($summary, $this->syncRecipe($recipe, true));
                }
            });

        return $summary;
    }

    public function syncActiveRecipesUsingMaterials(array $materialIds, int $userId, bool $force = false): array
    {
        $materialIds = array_values(array_unique(array_filter(array_map('intval', $materialIds))));

        if ($materialIds === []) {
            return $this->summary(skipped: 1);
        }

        if (!$force && !$this->isEnabled($userId)) {
            return $this->summary(enabled: false);
        }

        $summary = $this->summary();

        Recipe::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereHas('lines', fn ($query) => $query->whereIn('material_id', $materialIds))
            ->with(['product', 'lines.material', 'lines.subRecipe.lines.material'])
            ->chunkById(100, function (Collection $recipes) use (&$summary) {
                foreach ($recipes as $recipe) {
                    $summary = $this->mergeSummary($summary, $this->syncRecipe($recipe, true));
                }
            });

        return $summary;
    }

    protected function summary(
        bool $enabled = true,
        int $matched = 0,
        int $updated = 0,
        int $recipes = 0,
        int $skipped = 0,
        float $totalCost = 0,
    ): array {
        return [
            'enabled' => $enabled,
            'matched_products' => $matched,
            'updated_products' => $updated,
            'synced_recipes' => $recipes,
            'skipped_recipes' => $skipped,
            'last_total_cost' => $totalCost,
        ];
    }

    protected function mergeSummary(array $left, array $right): array
    {
        return [
            'enabled' => (bool) ($left['enabled'] ?? true) && (bool) ($right['enabled'] ?? true),
            'matched_products' => (int) ($left['matched_products'] ?? 0) + (int) ($right['matched_products'] ?? 0),
            'updated_products' => (int) ($left['updated_products'] ?? 0) + (int) ($right['updated_products'] ?? 0),
            'synced_recipes' => (int) ($left['synced_recipes'] ?? 0) + (int) ($right['synced_recipes'] ?? 0),
            'skipped_recipes' => (int) ($left['skipped_recipes'] ?? 0) + (int) ($right['skipped_recipes'] ?? 0),
            'last_total_cost' => (float) ($right['last_total_cost'] ?? $left['last_total_cost'] ?? 0),
        ];
    }
}
