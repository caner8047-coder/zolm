<?php

namespace App\Services\Marketplace;

use App\Models\TrendyolBoosterCollection;
use App\Models\TrendyolBoosterProduct;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TrendyolBoosterCollectionService
{
    /** @return array<string, mixed> */
    public function dashboard(int $userId, ?int $selectedId = null): array
    {
        if (! $this->ready()) {
            return ['ready' => false, 'collections' => collect(), 'selected' => null, 'products' => collect()];
        }

        $collections = TrendyolBoosterCollection::query()
            ->where('user_id', $userId)
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $selected = $collections->firstWhere('id', $selectedId) ?: $collections->first();
        $selected?->load(['products' => fn ($query) => $query->latest('trendyol_booster_collection_items.created_at')->limit(30)]);

        return [
            'ready' => true,
            'collections' => $collections,
            'selected' => $selected,
            'products' => TrendyolBoosterProduct::query()->where('user_id', $userId)->latest('updated_at')->limit(100)->get(['id', 'title', 'brand', 'sale_price']),
        ];
    }

    public function create(int $userId, string $name): TrendyolBoosterCollection
    {
        $name = $this->normalizeName($name);

        return TrendyolBoosterCollection::query()->firstOrCreate(
            ['user_id' => $userId, 'name' => $name],
            ['color' => 'slate', 'sort_order' => 0],
        );
    }

    public function toggleProduct(int $userId, int $collectionId, int $productId): bool
    {
        $collection = TrendyolBoosterCollection::query()->where('user_id', $userId)->findOrFail($collectionId);
        $product = TrendyolBoosterProduct::query()->where('user_id', $userId)->findOrFail($productId);
        $attached = $collection->products()->whereKey($product->id)->exists();

        if ($attached) {
            $collection->products()->detach($product->id);
        } else {
            $collection->products()->attach($product->id);
        }

        return ! $attached;
    }

    public function normalizeName(string $name): string
    {
        $name = preg_replace('/\s+/u', ' ', strip_tags($name)) ?: '';

        return trim(Str::limit($name, 80, ''));
    }

    protected function ready(): bool
    {
        return Schema::hasTable('trendyol_booster_collections') && Schema::hasTable('trendyol_booster_collection_items');
    }
}
