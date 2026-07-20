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
            if (count($items) === 0) {
                dump('TrendyolV2Test Debug: items count is 0 for store ' . $store->id, $response);
            }
            $totalProcessed = 0;
            $now = Carbon::now();

            foreach ($items as $item) {
                $invoiceSerialNumber = data_get($item, '_invoice_serial');
                $parcelUniqueId = data_get($item, 'packageId'); // or shipmentPackageId/parcelId
                $orderNumber = data_get($item, 'orderNumber');

                if (!$invoiceSerialNumber && !$parcelUniqueId) continue;

                CargoInvoiceLine::updateOrCreate(
                    [
                        'store_id' => $store->id,
                        'invoice_serial_number' => $invoiceSerialNumber,
                        'parcel_unique_id' => $parcelUniqueId,
                    ],
                    [
                        'user_id' => $store->user_id,
                        'order_number' => $orderNumber,
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
