<?php

namespace App\Services\Marketplace;

use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
use App\Models\ChannelOrderPackage;
use App\Models\MarketplaceStore;

class MarketplaceOrderSyncService
{
    public function __construct(
        protected MarketplaceProductMatcher $matcher,
    ) {
    }

    /**
     * @param  array<int, array<string, mixed>>  $packages
     * @return array{created: int, updated: int, skipped: int, impacted_order_ids: array<int>}
     */
    public function sync(MarketplaceStore $store, array $packages): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $impactedOrderIds = [];

        foreach ($packages as $payload) {
            $orderData = $payload['order'] ?? [];
            $packageData = $payload['package'] ?? [];
            $itemRows = $payload['items'] ?? [];

            $externalOrderId = (string) ($orderData['external_order_id'] ?? $orderData['order_number'] ?? '');
            $externalPackageId = (string) ($packageData['external_package_id'] ?? '');

            if ($externalOrderId === '' || $externalPackageId === '') {
                $skipped++;

                continue;
            }

            $order = ChannelOrder::firstOrNew([
                'store_id' => $store->id,
                'external_order_id' => $externalOrderId,
            ]);

            $orderDirty = !$order->exists;

            $order->fill([
                'legal_entity_id' => $store->legal_entity_id,
                'order_number' => (string) ($orderData['order_number'] ?? $externalOrderId),
                'order_status' => $orderData['order_status'] ?? 'new',
                'commercial_type' => $orderData['commercial_type'] ?? null,
                'customer_name' => $orderData['customer_name'] ?? null,
                'customer_email' => $orderData['customer_email'] ?? null,
                'customer_phone' => $orderData['customer_phone'] ?? null,
                'billing_name' => $orderData['billing_name'] ?? null,
                'billing_tax_number' => $orderData['billing_tax_number'] ?? null,
                'shipment_country' => $orderData['shipment_country'] ?? null,
                'shipment_city' => $orderData['shipment_city'] ?? null,
                'shipment_district' => $orderData['shipment_district'] ?? null,
                'ordered_at' => $orderData['ordered_at'] ?? null,
                'approved_at' => $orderData['approved_at'] ?? null,
                'delivered_at' => $orderData['delivered_at'] ?? null,
                'cancelled_at' => $orderData['cancelled_at'] ?? null,
                'returned_at' => $orderData['returned_at'] ?? null,
                'raw_payload' => $orderData['raw_payload'] ?? $payload,
                'last_synced_at' => now(),
            ]);

            $orderChanged = $orderDirty || $order->isDirty();
            $order->save();

            $package = ChannelOrderPackage::firstOrNew([
                'channel_order_id' => $order->id,
                'external_package_id' => $externalPackageId,
            ]);

            $packageDirty = !$package->exists;

            $package->fill([
                'store_id' => $store->id,
                'package_number' => $packageData['package_number'] ?? $externalPackageId,
                'package_status' => $packageData['package_status'] ?? ($orderData['order_status'] ?? 'new'),
                'cargo_company' => $packageData['cargo_company'] ?? null,
                'cargo_tracking_number' => $packageData['cargo_tracking_number'] ?? null,
                'cargo_barcode' => $packageData['cargo_barcode'] ?? null,
                'cargo_desi' => $packageData['cargo_desi'] ?? null,
                'shipment_provider' => $packageData['shipment_provider'] ?? null,
                'shipped_at' => $packageData['shipped_at'] ?? null,
                'delivered_at' => $packageData['delivered_at'] ?? null,
                'raw_payload' => $packageData['raw_payload'] ?? $payload,
                'last_synced_at' => now(),
            ]);

            $packageChanged = $packageDirty || $package->isDirty();
            $package->save();

            $touchedItem = false;

            foreach ($itemRows as $itemData) {
                $externalLineId = (string) ($itemData['external_line_id'] ?? '');

                if ($externalLineId === '') {
                    continue;
                }

                $item = ChannelOrderItem::firstOrNew([
                    'store_id' => $store->id,
                    'external_line_id' => $externalLineId,
                ]);

                $itemDirty = !$item->exists;

                $item->fill([
                    'channel_order_id' => $order->id,
                    'channel_order_package_id' => $package->id,
                    'stock_code' => $itemData['stock_code'] ?? null,
                    'barcode' => $itemData['barcode'] ?? null,
                    'product_name' => $itemData['product_name'] ?? null,
                    'quantity' => $itemData['quantity'] ?? 1,
                    'unit_price' => $itemData['unit_price'] ?? null,
                    'gross_amount' => $itemData['gross_amount'] ?? null,
                    'discount_amount' => $itemData['discount_amount'] ?? 0,
                    'marketplace_discount_amount' => $itemData['marketplace_discount_amount'] ?? 0,
                    'billable_amount' => $itemData['billable_amount'] ?? null,
                    'commission_rate' => $itemData['commission_rate'] ?? null,
                    'vat_rate' => $itemData['vat_rate'] ?? null,
                    'line_status' => $itemData['line_status'] ?? ($package->package_status ?: 'new'),
                    'raw_payload' => $itemData['raw_payload'] ?? $itemData,
                    'last_synced_at' => now(),
                ]);

                $itemChanged = $itemDirty || $item->isDirty();
                $item->save();
                $this->matcher->applyToOrderItem(
                    $item,
                    $item->stock_code,
                    $item->barcode,
                );

                $touchedItem = $touchedItem || $itemChanged || $itemDirty;
            }

            if ($orderChanged || $packageChanged || $touchedItem) {
                if ($orderDirty || $packageDirty) {
                    $created++;
                } else {
                    $updated++;
                }
            } else {
                $skipped++;
            }

            $impactedOrderIds[] = $order->id;
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'impacted_order_ids' => array_values(array_unique($impactedOrderIds)),
        ];
    }
}
