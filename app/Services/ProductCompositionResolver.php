<?php

namespace App\Services;

use App\Models\MpProduct;
use App\Models\ProductSet;
use App\Models\ProductSetItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ProductCompositionResolver
{
    /**
     * @return array<string, mixed>
     */
    public function resolve(MpProduct $product, float $quantity = 1): array
    {
        return $this->resolveProduct($product, max(0, $quantity), []);
    }

    /**
     * @param  iterable<int, mixed>  $items
     * @return array<string, mixed>
     */
    public function totalsForOrderItems(iterable $items): array
    {
        $totals = $this->emptyTotals();

        foreach ($items as $item) {
            $product = $item->product ?? null;

            if (!$product instanceof MpProduct) {
                continue;
            }

            $quantity = max(1, (int) ($item->quantity ?? 1));
            $summary = $this->resolve($product, $quantity);

            $totals['cogs_cost'] += (float) $summary['cogs_cost'];
            $totals['packaging_cost'] += (float) $summary['packaging_cost'];
            $totals['own_cargo_cost'] += (float) $summary['own_cargo_cost'];
            $totals['desi'] += (float) $summary['desi'];
            $totals['pieces'] += (int) $summary['pieces'];
            $totals['component_count'] += (int) $summary['component_count'];
            $totals['missing_cost_components'] += (int) $summary['missing_cost_components'];
            $totals['missing_logistics_components'] += (int) $summary['missing_logistics_components'];
            $totals['has_cycle'] = (bool) $totals['has_cycle'] || (bool) $summary['has_cycle'];
        }

        return $this->roundTotals($totals);
    }

    /**
     * @return array<string, mixed>
     */
    public function syncProductTotals(MpProduct $product): array
    {
        if (!Schema::hasTable('product_sets') || !Schema::hasTable('product_set_items')) {
            return $this->singleProductSummary($product, 1);
        }

        $product->loadMissing([
            'productSet.items.componentProduct.productSet.items.componentProduct',
        ]);

        $summary = $this->resolve($product);
        $set = $product->productSet;

        if ($set) {
            $set->forceFill([
                'totals_cache_json' => $summary,
                'calculated_at' => now(),
            ])->save();
        }

        if ($set && $set->status === ProductSet::STATUS_ACTIVE && $set->items->isNotEmpty()) {
            $updates = [
                'product_type' => 'set',
                'cost_source' => $set->cost_mode === ProductSet::MODE_SUM_COMPONENTS ? 'set' : 'manual',
                'logistics_source' => $set->logistics_mode === ProductSet::MODE_SUM_COMPONENTS ? 'set' : 'manual',
            ];

            if ($set->cost_mode === ProductSet::MODE_SUM_COMPONENTS) {
                $updates['cogs'] = (float) $summary['cogs_cost'];
                $updates['packaging_cost'] = (float) $summary['packaging_cost'];
            }

            if ($set->logistics_mode === ProductSet::MODE_SUM_COMPONENTS) {
                $updates['cargo_cost'] = (float) $summary['own_cargo_cost'];
                $updates['desi'] = (float) $summary['desi'];
                $updates['pieces'] = max(1, (int) $summary['pieces']);
            }

            if ($summary['stock_quantity'] !== null) {
                $updates['stock_quantity'] = max(0, (int) $summary['stock_quantity']);
            }

            $logger = app(MpProductChangeLogger::class);
            $beforeSnapshot = $logger->productSnapshot($product);
            $product->forceFill($updates)->saveQuietly();
            $logger->logProductSnapshotChanges(
                $product->fresh() ?: $product,
                $beforeSnapshot,
                'set_composition_sync',
                null,
                'Set/takım bileşenlerinden otomatik toplam'
            );
        } elseif ((string) $product->product_type !== 'single') {
            $logger = app(MpProductChangeLogger::class);
            $beforeSnapshot = $logger->productSnapshot($product);
            $product->forceFill([
                'product_type' => 'single',
                'cost_source' => $product->cost_source === 'set' ? 'manual' : $product->cost_source,
                'logistics_source' => $product->logistics_source === 'set' ? 'manual' : $product->logistics_source,
            ])->saveQuietly();
            $logger->logProductSnapshotChanges(
                $product->fresh() ?: $product,
                $beforeSnapshot,
                'set_composition_sync',
                null,
                'Set/takım tanımı tekil ürüne döndü'
            );
        }

        return $summary;
    }

    public function refreshParentSetsForComponent(MpProduct $component): void
    {
        $this->refreshParentSetsForProductIds([$component->id]);
    }

    /**
     * @param  iterable<int, int|string>  $componentIds
     */
    public function refreshParentSetsForProductIds(iterable $componentIds): void
    {
        if (!Schema::hasTable('product_sets') || !Schema::hasTable('product_set_items')) {
            return;
        }

        $ids = collect($componentIds)
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return;
        }

        $visitedParentIds = [];
        $this->refreshParentSetsForComponentIds($ids, $visitedParentIds);
    }

    public function wouldCreateCycle(MpProduct $parent, MpProduct $component): bool
    {
        if (!Schema::hasTable('product_sets') || !Schema::hasTable('product_set_items')) {
            return false;
        }

        if ((int) $parent->id === (int) $component->id) {
            return true;
        }

        $visited = [];
        $stack = [$component];

        while ($stack !== []) {
            /** @var MpProduct $current */
            $current = array_pop($stack);
            $currentId = (int) $current->id;

            if ($currentId === (int) $parent->id) {
                return true;
            }

            if (isset($visited[$currentId])) {
                continue;
            }

            $visited[$currentId] = true;
            $current->loadMissing('productSet.items.componentProduct');

            foreach ($current->productSet?->items ?? [] as $item) {
                if ($item->componentProduct) {
                    $stack[] = $item->componentProduct;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<int, int>  $visited
     * @return array<string, mixed>
     */
    protected function resolveProduct(MpProduct $product, float $quantity, array $visited): array
    {
        $quantity = max(0, $quantity);
        $productId = (int) $product->id;

        if (!Schema::hasTable('product_sets') || !Schema::hasTable('product_set_items')) {
            return $this->singleProductSummary($product, $quantity);
        }

        if (in_array($productId, $visited, true)) {
            $summary = $this->emptyTotals();
            $summary['is_set'] = true;
            $summary['product_id'] = $productId;
            $summary['product_name'] = $product->product_name;
            $summary['quantity'] = $quantity;
            $summary['has_cycle'] = true;

            return $summary;
        }

        $product->loadMissing('productSet.items.componentProduct');
        $set = $product->productSet;

        if (!$this->isActiveSet($set)) {
            return $this->singleProductSummary($product, $quantity);
        }

        $componentTotals = $this->emptyTotals();
        $components = [];
        $stockLimits = [];

        /** @var ProductSetItem $item */
        foreach ($set->items as $item) {
            $component = $item->componentProduct;

            if (!$component instanceof MpProduct) {
                continue;
            }

            $componentUnitQuantity = max(0, (float) $item->quantity);
            $componentQuantity = $componentUnitQuantity * $quantity;
            $componentSummary = $this->resolveProduct($component, $componentQuantity, [...$visited, $productId]);

            if (!$item->include_cost) {
                $componentSummary['cogs_cost'] = 0.0;
            } elseif ($item->cost_override !== null) {
                $componentSummary['cogs_cost'] = round((float) $item->cost_override * $componentQuantity, 2);
            }

            if (!$item->include_packaging) {
                $componentSummary['packaging_cost'] = 0.0;
            }

            if (!$item->include_logistics) {
                $componentSummary['own_cargo_cost'] = 0.0;
                $componentSummary['desi'] = 0.0;
                $componentSummary['pieces'] = 0;
            } else {
                if ($item->cargo_cost_override !== null) {
                    $componentSummary['own_cargo_cost'] = round((float) $item->cargo_cost_override * $componentQuantity, 2);
                }

                if ($item->desi_override !== null) {
                    $componentSummary['desi'] = round((float) $item->desi_override * $componentQuantity, 2);
                }

                if ($item->pieces_override !== null) {
                    $componentSummary['pieces'] = (int) ceil(max(0, (int) $item->pieces_override) * $componentQuantity);
                }
            }

            $componentTotals['cogs_cost'] += (float) $componentSummary['cogs_cost'];
            $componentTotals['packaging_cost'] += (float) $componentSummary['packaging_cost'];
            $componentTotals['own_cargo_cost'] += (float) $componentSummary['own_cargo_cost'];
            $componentTotals['desi'] += (float) $componentSummary['desi'];
            $componentTotals['pieces'] += (int) $componentSummary['pieces'];
            $componentTotals['component_count']++;
            $componentTotals['missing_cost_components'] += $this->isMissingCost($component, $item) ? 1 : 0;
            $componentTotals['missing_logistics_components'] += $this->isMissingLogistics($component, $item) ? 1 : 0;
            $componentTotals['has_cycle'] = (bool) $componentTotals['has_cycle'] || (bool) $componentSummary['has_cycle'];

            if ($componentUnitQuantity > 0 && $componentSummary['stock_quantity'] !== null) {
                $stockLimits[] = (int) floor(((float) $componentSummary['stock_quantity']) / $componentUnitQuantity);
            }

            $components[] = [
                'item_id' => $item->id,
                'product_id' => $component->id,
                'product_name' => $component->product_name,
                'stock_code' => $component->stock_code,
                'barcode' => $component->barcode,
                'quantity' => $componentUnitQuantity,
                'total_quantity' => $componentQuantity,
                'cogs_cost' => round((float) $componentSummary['cogs_cost'], 2),
                'packaging_cost' => round((float) $componentSummary['packaging_cost'], 2),
                'own_cargo_cost' => round((float) $componentSummary['own_cargo_cost'], 2),
                'desi' => round((float) $componentSummary['desi'], 2),
                'pieces' => (int) $componentSummary['pieces'],
                'stock_quantity' => $componentSummary['stock_quantity'],
                'is_set' => (bool) $componentSummary['is_set'],
                'has_cycle' => (bool) $componentSummary['has_cycle'],
            ];
        }

        $summary = $this->roundTotals($componentTotals);
        $summary['is_set'] = true;
        $summary['product_id'] = $productId;
        $summary['product_name'] = $product->product_name;
        $summary['quantity'] = $quantity;
        $summary['components'] = $components;
        $summary['stock_quantity'] = $stockLimits === [] ? null : max(0, min($stockLimits));
        $summary['cost_mode'] = $set->cost_mode;
        $summary['logistics_mode'] = $set->logistics_mode;

        if ($set->cost_mode === ProductSet::MODE_MANUAL_PARENT) {
            $manual = $this->singleProductSummary($product, $quantity);
            $summary['cogs_cost'] = $manual['cogs_cost'];
            $summary['packaging_cost'] = $manual['packaging_cost'];
        }

        if ($set->logistics_mode === ProductSet::MODE_MANUAL_PARENT) {
            $manual = $this->singleProductSummary($product, $quantity);
            $summary['own_cargo_cost'] = $manual['own_cargo_cost'];
            $summary['desi'] = $manual['desi'];
            $summary['pieces'] = $manual['pieces'];
        }

        return $this->roundTotals($summary);
    }

    /**
     * @param  array<int, int>  $componentIds
     * @param  array<int, bool>  $visitedParentIds
     */
    protected function refreshParentSetsForComponentIds(array $componentIds, array &$visitedParentIds): void
    {
        $parentProducts = ProductSetItem::query()
            ->with('productSet.parentProduct')
            ->whereIn('component_mp_product_id', $componentIds)
            ->get()
            ->map(fn (ProductSetItem $item) => $item->productSet?->parentProduct)
            ->filter(fn ($product) => $product instanceof MpProduct)
            ->unique(fn (MpProduct $product) => (int) $product->id)
            ->values();

        if ($parentProducts->isEmpty()) {
            return;
        }

        $nextComponentIds = [];

        foreach ($parentProducts as $parent) {
            $parentId = (int) $parent->id;

            if (isset($visitedParentIds[$parentId])) {
                continue;
            }

            $visitedParentIds[$parentId] = true;
            $this->syncProductTotals($parent->fresh() ?: $parent);
            $nextComponentIds[] = $parentId;
        }

        if ($nextComponentIds !== []) {
            $this->refreshParentSetsForComponentIds($nextComponentIds, $visitedParentIds);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function singleProductSummary(MpProduct $product, float $quantity): array
    {
        $quantity = max(0, $quantity);
        $pieces = max(1, (int) ($product->pieces ?? 1));

        return $this->roundTotals([
            'is_set' => false,
            'product_id' => $product->id,
            'product_name' => $product->product_name,
            'quantity' => $quantity,
            'cogs_cost' => (float) ($product->cogs ?? 0) * $quantity,
            'packaging_cost' => (float) ($product->packaging_cost ?? 0) * $quantity,
            'own_cargo_cost' => (float) ($product->cargo_cost ?? 0) * $quantity,
            'desi' => (float) ($product->desi ?? 0) * $quantity,
            'pieces' => (int) ceil($pieces * $quantity),
            'stock_quantity' => $product->stock_quantity === null ? null : (int) $product->stock_quantity,
            'component_count' => 0,
            'missing_cost_components' => 0,
            'missing_logistics_components' => 0,
            'has_cycle' => false,
            'components' => [],
            'cost_mode' => 'manual',
            'logistics_mode' => 'manual',
        ]);
    }

    protected function isActiveSet(?ProductSet $set): bool
    {
        return $set instanceof ProductSet
            && $set->status === ProductSet::STATUS_ACTIVE
            && $set->items instanceof Collection
            && $set->items->isNotEmpty();
    }

    protected function isMissingCost(MpProduct $product, ProductSetItem $item): bool
    {
        if (!$item->include_cost || $item->cost_override !== null) {
            return false;
        }

        return (float) ($product->cogs ?? 0) <= 0;
    }

    protected function isMissingLogistics(MpProduct $product, ProductSetItem $item): bool
    {
        if (!$item->include_logistics || $item->desi_override !== null || $item->pieces_override !== null) {
            return false;
        }

        return (float) ($product->desi ?? 0) <= 0 || (int) ($product->pieces ?? 0) <= 0;
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyTotals(): array
    {
        return [
            'is_set' => false,
            'product_id' => null,
            'product_name' => null,
            'quantity' => 0.0,
            'cogs_cost' => 0.0,
            'packaging_cost' => 0.0,
            'own_cargo_cost' => 0.0,
            'desi' => 0.0,
            'pieces' => 0,
            'stock_quantity' => null,
            'component_count' => 0,
            'missing_cost_components' => 0,
            'missing_logistics_components' => 0,
            'has_cycle' => false,
            'components' => [],
            'cost_mode' => ProductSet::MODE_SUM_COMPONENTS,
            'logistics_mode' => ProductSet::MODE_SUM_COMPONENTS,
        ];
    }

    /**
     * @param  array<string, mixed>  $totals
     * @return array<string, mixed>
     */
    protected function roundTotals(array $totals): array
    {
        foreach (['cogs_cost', 'packaging_cost', 'own_cargo_cost', 'desi'] as $key) {
            $totals[$key] = round((float) ($totals[$key] ?? 0), 2);
        }

        $totals['pieces'] = max(0, (int) ($totals['pieces'] ?? 0));

        return $totals;
    }
}
