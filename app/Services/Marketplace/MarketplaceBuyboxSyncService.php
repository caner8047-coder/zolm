<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceStore;
use App\Models\MpBuyboxHistory;
use App\Models\MpBuyboxListing;
use App\Models\MpProduct;
use App\Services\Marketplace\Connectors\TrendyolConnector;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MarketplaceBuyboxSyncService
{
    public function __construct(
        protected MarketplaceConnectorManager $connectorManager
    ) {
    }

    public function sync(MarketplaceStore $store): array
    {
        $connector = $this->connectorManager->resolveForStore($store);

        if (!method_exists($connector, 'checkBuyboxRank')) {
            return ['status' => 'skipped', 'message' => 'Connector does not support checkBuyboxRank.'];
        }

        // Fetch active barcodes for the store to check buybox
        $barcodes = MpProduct::query()
            ->where('store_id', $store->id)
            ->whereNotNull('barcode')
            ->pluck('barcode')
            ->unique()
            ->values()
            ->all();

        if (empty($barcodes)) {
            return ['status' => 'completed', 'items_processed' => 0];
        }

        $chunks = array_chunk($barcodes, 10);
        $totalProcessed = 0;
        $totalUpdated = 0;
        $now = Carbon::now();

        foreach ($chunks as $chunk) {
            try {
                $results = $connector->checkBuyboxRank($store, $chunk);
                
                foreach ($results as $item) {
                    $barcode = Arr::get($item, 'barcode');
                    if (!$barcode) continue;

                    $totalProcessed++;
                    
                    $buyboxPrice = data_get($item, 'buyboxPrice');
                    $sellerPrice = data_get($item, 'sellerPrice');
                    $sellerRank = data_get($item, 'sellerRank');

                    DB::transaction(function () use ($store, $barcode, $buyboxPrice, $sellerPrice, $sellerRank, $item, $now, &$totalUpdated) {
                        $current = MpBuyboxListing::where('store_id', $store->id)
                            ->where('barcode', $barcode)
                            ->first();

                        $needsHistory = true;
                        
                        if ($current) {
                            if (
                                (float) $current->buybox_price === (float) $buyboxPrice &&
                                (float) $current->seller_price === (float) $sellerPrice &&
                                $current->seller_rank === $sellerRank
                            ) {
                                $needsHistory = false;
                            }
                        }

                        $data = [
                            'buybox_price' => $buyboxPrice,
                            'seller_price' => $sellerPrice,
                            'seller_rank' => $sellerRank,
                            'raw_payload' => $item,
                            'retrieved_at' => $now,
                        ];

                        MpBuyboxListing::updateOrCreate(
                            ['store_id' => $store->id, 'barcode' => $barcode],
                            $data
                        );

                        if ($needsHistory) {
                            MpBuyboxHistory::create(array_merge(
                                ['store_id' => $store->id, 'barcode' => $barcode],
                                $data
                            ));
                            $totalUpdated++;
                        }
                    });
                }
            } catch (\Throwable $e) {
                // Log and continue
                report($e);
            }
        }

        return [
            'status' => 'completed',
            'items_processed' => $totalProcessed,
            'items_updated' => $totalUpdated,
        ];
    }
}
