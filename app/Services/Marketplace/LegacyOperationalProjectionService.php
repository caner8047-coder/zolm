<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceStore;
use App\Models\MpOperationalOrder;
use App\Models\MpOperationalOrderItem;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class LegacyOperationalProjectionService
{
    public function __construct(
        protected MarketplaceOrderSyncService $orderSyncService,
        protected MarketplaceProfitSnapshotService $profitSnapshotService,
    ) {
    }

    /**
     * @param  iterable<int, MpOperationalOrder>  $orders
     * @return array{created:int,updated:int,skipped:int,projected_orders:int,impacted_order_ids:array<int>}
     */
    public function projectOperationalOrders(MarketplaceStore $store, iterable $orders): array
    {
        $payloads = [];
        $projectedLegacyIds = [];

        foreach ($this->normalizeOrders($orders) as $order) {
            $payload = $this->transformOrder($store, $order);

            if ($payload === null) {
                continue;
            }

            $payloads[] = $payload;
            $projectedLegacyIds[] = $order->id;
        }

        if ($payloads === []) {
            return [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'projected_orders' => 0,
                'impacted_order_ids' => [],
            ];
        }

        $sync = $this->orderSyncService->sync($store, $payloads);
        $this->profitSnapshotService->recalculateForOrders($store, $sync['impacted_order_ids']);

        MpOperationalOrder::query()
            ->whereIn('id', $projectedLegacyIds)
            ->update([
                'store_id' => $store->id,
                'legal_entity_id' => $store->legal_entity_id,
                'source_marketplace' => $store->marketplace,
                'projected_at' => now(),
            ]);

        return array_merge($sync, [
            'projected_orders' => count($projectedLegacyIds),
        ]);
    }

    /**
     * @return Collection<int, MpOperationalOrder>
     */
    protected function normalizeOrders(iterable $orders): Collection
    {
        if ($orders instanceof EloquentCollection) {
            return $orders->loadMissing('items');
        }

        return collect($orders)->map(function ($order) {
            if ($order instanceof MpOperationalOrder) {
                $order->loadMissing('items.product');
            }

            return $order;
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function transformOrder(MarketplaceStore $store, MpOperationalOrder $order): ?array
    {
        $items = $order->items;

        if ($items->isEmpty()) {
            return null;
        }

        $externalOrderId = 'legacy-op-order:' . $order->id;
        $packageKey = $order->package_number ?: $order->order_number ?: (string) $order->id;
        $externalPackageId = 'legacy-op-package:' . $order->id . ':' . $packageKey;

        return [
            'order' => [
                'external_order_id' => $externalOrderId,
                'order_number' => (string) $order->order_number,
                'order_status' => (string) ($order->status ?: 'legacy_imported'),
                'commercial_type' => 'legacy_excel',
                'customer_name' => $order->customer_name,
                'customer_email' => $order->email,
                'customer_phone' => $order->customer_phone,
                'billing_name' => $order->billing_name ?: $order->company_name,
                'billing_tax_number' => $order->tax_number,
                'shipment_country' => $order->country ?: 'TR',
                'shipment_city' => $order->customer_city,
                'shipment_district' => $order->customer_district,
                'ordered_at' => optional($order->order_date)?->toDateTimeString(),
                'delivered_at' => optional($order->delivery_date)?->toDateTimeString(),
                'raw_payload' => [
                    'source' => 'legacy_excel',
                    'operational_order_id' => $order->id,
                ],
            ],
            'package' => [
                'external_package_id' => $externalPackageId,
                'package_number' => (string) $packageKey,
                'package_status' => (string) ($order->status ?: 'legacy_imported'),
                'cargo_company' => $order->cargo_company,
                'cargo_tracking_number' => $order->tracking_number,
                'cargo_barcode' => $order->cargo_code,
                'shipment_provider' => 'legacy_excel',
                'delivered_at' => optional($order->delivery_date)?->toDateTimeString(),
                'raw_payload' => [
                    'source' => 'legacy_excel',
                    'operational_order_id' => $order->id,
                ],
            ],
            'items' => $items
                ->values()
                ->map(fn (MpOperationalOrderItem $item, int $index) => $this->transformItem($store, $order, $item, $index))
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function transformItem(MarketplaceStore $store, MpOperationalOrder $order, MpOperationalOrderItem $item, int $index): array
    {
        $grossAmount = (float) ($item->sale_price ?? 0);
        $marketplaceDiscount = (float) ($item->trendyol_discount ?? 0);
        $discountAmount = (float) ($item->discount_amount ?? 0);
        $billableAmount = (float) ($item->billable_amount ?: max(0, $grossAmount - $discountAmount - $marketplaceDiscount));
        $lineKey = $item->barcode ?: $item->stock_code ?: (string) $item->id;

        return [
            'external_line_id' => 'legacy-op-line:' . $store->id . ':' . $order->id . ':' . $item->id . ':' . $index,
            'stock_code' => $item->stock_code,
            'barcode' => $item->barcode,
            'product_name' => $item->product_name,
            'quantity' => (int) ($item->quantity ?: 1),
            'unit_price' => (float) ($item->unit_price ?? 0),
            'gross_amount' => $grossAmount,
            'discount_amount' => $discountAmount,
            'marketplace_discount_amount' => $marketplaceDiscount,
            'billable_amount' => $billableAmount,
            'commission_rate' => (float) ($item->commission_rate ?? 0),
            'vat_rate' => (float) ($item->synced_vat_rate ?? $item->product?->vat_rate ?? 0),
            'line_status' => (string) ($order->status ?: 'legacy_imported'),
            'raw_payload' => [
                'source' => 'legacy_excel',
                'operational_order_id' => $order->id,
                'operational_item_id' => $item->id,
                'line_key' => $lineKey,
            ],
        ];
    }
}
