<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceStore;
use App\Models\MpBrand;
use App\Models\MpCategory;
use App\Models\MpClaimReason;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class MarketplaceReferenceSyncService
{
    public function __construct(
        protected MarketplaceConnectorManager $connectorManager
    ) {
    }

    public function syncBrands(MarketplaceStore $store): array
    {
        $connector = $this->connectorManager->resolveForStore($store);

        if (!method_exists($connector, 'getBrands')) {
            return ['status' => 'skipped'];
        }

        $page = 0;
        $size = 500;
        $totalProcessed = 0;
        $now = Carbon::now();
        $marketplace = $store->marketplace;

        do {
            try {
                $brands = $connector->getBrands($store, $page, $size);
                if (empty($brands)) {
                    break;
                }

                foreach ($brands as $brand) {
                    $platformId = data_get($brand, 'id');
                    $name = data_get($brand, 'name');

                    if (!$platformId || !$name) continue;

                    MpBrand::updateOrCreate(
                        [
                            'marketplace' => $marketplace,
                            'platform_brand_id' => $platformId,
                        ],
                        [
                            'name' => $name,
                            'normalized_name' => strtolower(trim($name)),
                            'is_active' => true,
                            'raw_payload' => $brand,
                            'last_synced_at' => $now,
                        ]
                    );
                    $totalProcessed++;
                }

                if (count($brands) < $size) {
                    break;
                }
                $page++;
            } catch (\Throwable $e) {
                report($e);
                break;
            }
        } while (true);

        return ['status' => 'completed', 'items_processed' => $totalProcessed];
    }

    public function syncCategories(MarketplaceStore $store): array
    {
        $connector = $this->connectorManager->resolveForStore($store);

        if (!method_exists($connector, 'getCategories')) {
            return ['status' => 'skipped'];
        }

        try {
            $categories = $connector->getCategories($store);
            $totalProcessed = 0;
            $now = Carbon::now();
            $marketplace = $store->marketplace;

            // Categories often come in tree structure or flat array, depending on platform.
            // Trendyol returns flat array with parentId or a nested tree. 
            // The connector normalized it to an array. We flatten and store.
            
            $this->processCategoriesRecursively($categories, $marketplace, $now, null, 0, $totalProcessed);

            return ['status' => 'completed', 'items_processed' => $totalProcessed];
        } catch (\Throwable $e) {
            report($e);
            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    protected function processCategoriesRecursively(array $categories, string $marketplace, Carbon $now, ?string $parentId, int $level, int &$totalProcessed): void
    {
        foreach ($categories as $cat) {
            $platformId = data_get($cat, 'id');
            $name = data_get($cat, 'name');
            $subCategories = data_get($cat, 'subCategories', []);

            if (!$platformId || !$name) continue;

            MpCategory::updateOrCreate(
                [
                    'marketplace' => $marketplace,
                    'platform_category_id' => $platformId,
                ],
                [
                    'parent_id' => $parentId,
                    'name' => $name,
                    'level' => $level,
                    'is_leaf' => empty($subCategories),
                    'is_active' => true,
                    'raw_payload' => $cat,
                    'last_synced_at' => $now,
                ]
            );
            $totalProcessed++;

            if (!empty($subCategories)) {
                $this->processCategoriesRecursively($subCategories, $marketplace, $now, $platformId, $level + 1, $totalProcessed);
            }
        }
    }

    public function syncClaimReasons(MarketplaceStore $store): array
    {
        $connector = $this->connectorManager->resolveForStore($store);

        if (!method_exists($connector, 'getClaimIssueReasons')) {
            return ['status' => 'skipped'];
        }

        try {
            $reasons = $connector->getClaimIssueReasons($store);
            $totalProcessed = 0;
            
            foreach ($reasons as $reason) {
                $platformId = data_get($reason, 'id');
                $name = data_get($reason, 'name');
                
                if (!$platformId || !$name) continue;

                MpClaimReason::updateOrCreate(
                    [
                        'store_id' => $store->id,
                        'platform_reason_id' => $platformId,
                    ],
                    [
                        'name' => $name,
                        'is_active' => true,
                        'raw_payload' => $reason,
                    ]
                );
                $totalProcessed++;
            }
            
            return ['status' => 'completed', 'items_processed' => $totalProcessed];
        } catch (\Throwable $e) {
            report($e);
            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    public function syncCategoryAttributes(MarketplaceStore $store): array
    {
        $connector = $this->connectorManager->resolveForStore($store);

        if (!method_exists($connector, 'getCategoryAttributes')) {
            return ['status' => 'skipped'];
        }

        $marketplace = $store->marketplace;
        // Fetch leaf categories to get their attributes
        $categories = MpCategory::query()
            ->where('marketplace', $marketplace)
            ->where('is_leaf', true)
            ->where('is_active', true)
            ->get();

        $totalProcessed = 0;
        $now = Carbon::now();

        foreach ($categories as $category) {
            try {
                $response = $connector->getCategoryAttributes($store, $category->platform_category_id);
                $attributes = $response['attributes'] ?? [];

                $syncedAttributeIds = [];

                foreach ($attributes as $attr) {
                    $platformAttributeId = $attr['platform_attribute_id'];
                    $name = $attr['name'];

                    $attribute = \App\Models\MpCategoryAttribute::updateOrCreate(
                        [
                            'marketplace' => $marketplace,
                            'platform_category_id' => $category->platform_category_id,
                            'platform_attribute_id' => $platformAttributeId,
                        ],
                        [
                            'name' => $name,
                            'is_required' => $attr['is_required'] ?? false,
                            'is_variant' => $attr['is_variant'] ?? false,
                            'is_multi_select' => $attr['is_multi_select'] ?? false,
                            'data_type' => $attr['data_type'] ?? null,
                            'raw_payload' => $attr['raw_payload'] ?? null,
                            'last_synced_at' => $now,
                        ]
                    );

                    $syncedAttributeIds[] = $attribute->id;
                    $totalProcessed++;

                    $values = $attr['values'] ?? [];
                    $syncedValueIds = [];

                    foreach ($values as $val) {
                        $platformValueId = $val['platform_value_id'];
                        $valName = $val['name'];

                        $value = \App\Models\MpCategoryAttributeValue::updateOrCreate(
                            [
                                'mp_category_attribute_id' => $attribute->id,
                                'platform_value_id' => $platformValueId,
                            ],
                            [
                                'name' => $valName,
                                'raw_payload' => $val['raw_payload'] ?? null,
                            ]
                        );

                        $syncedValueIds[] = $value->id;
                    }

                    \App\Models\MpCategoryAttributeValue::query()
                        ->where('mp_category_attribute_id', $attribute->id)
                        ->whereNotIn('id', $syncedValueIds)
                        ->delete();
                }

                \App\Models\MpCategoryAttribute::query()
                    ->where('marketplace', $marketplace)
                    ->where('platform_category_id', $category->platform_category_id)
                    ->whereNotIn('id', $syncedAttributeIds)
                    ->delete();

            } catch (\Throwable $e) {
                report($e);
                continue;
            }
        }

        return ['status' => 'completed', 'items_processed' => $totalProcessed];
    }
}

