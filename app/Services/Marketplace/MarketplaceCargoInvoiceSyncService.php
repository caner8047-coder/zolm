<?php

namespace App\Services\Marketplace;

use App\Models\CargoInvoiceLine;
use App\Models\MarketplaceStore;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class MarketplaceCargoInvoiceSyncService
{
    public function __construct(
        protected MarketplaceConnectorManager $connectorManager
    ) {
    }

    public function sync(MarketplaceStore $store, array $options = []): array
    {
        $connector = $this->connectorManager->resolveForStore($store);

        if (!method_exists($connector, 'pullCargoInvoices')) {
            return ['status' => 'skipped'];
        }

        $startDate = $options['start_date'] ?? Carbon::now()->subDays(14)->toIso8601String();
        $endDate = $options['end_date'] ?? Carbon::now()->toIso8601String();

        try {
            $response = $connector->pullCargoInvoices($store, [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

            $items = $response['items'] ?? [];
            $totalProcessed = 0;
            $now = Carbon::now();

            foreach ($items as $item) {
                $invoiceNumber = data_get($item, 'invoiceNumber');
                $packageId = data_get($item, 'packageId'); // or shipmentPackageId/parcelId

                if (!$invoiceNumber && !$packageId) continue;

                CargoInvoiceLine::updateOrCreate(
                    [
                        'store_id' => $store->id,
                        'invoice_number' => $invoiceNumber,
                        'package_id' => $packageId,
                    ],
                    [
                        'invoice_date' => data_get($item, 'invoiceDate'),
                        'cargo_type' => data_get($item, 'cargoType'), // e.g. RETURN, OUTBOUND
                        'desi' => data_get($item, 'desi', 0),
                        'amount' => data_get($item, 'amount', 0),
                        'vat_amount' => data_get($item, 'vatAmount', 0),
                        'total_amount' => data_get($item, 'totalAmount', 0),
                        'currency' => data_get($item, 'currency', 'TRY'),
                        'raw_payload' => $item,
                        'updated_at' => $now,
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
