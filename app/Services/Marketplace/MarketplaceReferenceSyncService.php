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
}
