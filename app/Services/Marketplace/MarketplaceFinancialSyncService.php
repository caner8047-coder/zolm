<?php

namespace App\Services\Marketplace;

use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
use App\Models\ChannelOrderPackage;
use App\Models\MarketplaceStore;
use App\Models\OrderFinancialEvent;

class MarketplaceFinancialSyncService
{
    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return array{created: int, updated: int, skipped: int, impacted_order_ids: array<int>}
     */
    public function sync(MarketplaceStore $store, array $events): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $impactedOrderIds = [];

        foreach ($events as $eventData) {
            $externalEventId = (string) ($eventData['external_event_id'] ?? '');

            if ($externalEventId === '') {
                $skipped++;

                continue;
            }

            $order = $this->resolveOrder($store->id, (string) ($eventData['order_number'] ?? ''));
            $package = $order ? $this->resolvePackage($order->id, (string) ($eventData['external_package_id'] ?? '')) : null;
            $item = $this->resolveItem(
                storeId: $store->id,
                externalLineId: (string) ($eventData['external_line_id'] ?? ''),
                orderId: $order?->id,
                barcode: $eventData['barcode'] ?? null,
                stockCode: $eventData['stock_code'] ?? null,
            );

            $event = OrderFinancialEvent::firstOrNew([
                'store_id' => $store->id,
                'event_source' => (string) ($eventData['event_source'] ?? 'unknown'),
                'external_event_id' => $externalEventId,
            ]);

            $eventDirty = !$event->exists;

            $event->fill([
                'legal_entity_id' => $store->legal_entity_id,
                'channel_order_id' => $order?->id,
                'channel_order_package_id' => $package?->id,
                'channel_order_item_id' => $item?->id,
                'event_type' => $eventData['event_type'] ?? 'other',
                'reference_number' => $eventData['reference_number'] ?? null,
                'event_date' => $eventData['event_date'] ?? null,
                'due_date' => $eventData['due_date'] ?? null,
                'settlement_date' => $eventData['settlement_date'] ?? null,
                'amount' => $eventData['amount'] ?? 0,
                'currency' => $eventData['currency'] ?? 'TRY',
                'direction' => $eventData['direction'] ?? 'debit',
                'status' => $eventData['status'] ?? 'posted',
                'notes' => $eventData['notes'] ?? null,
                'raw_payload' => $eventData['raw_payload'] ?? $eventData,
            ]);

            $eventChanged = $eventDirty || $event->isDirty();
            $event->save();

            if ($eventChanged) {
                if ($eventDirty) {
                    $created++;
                } else {
                    $updated++;
                }
            } else {
                $skipped++;
            }

            if ($order) {
                $impactedOrderIds[] = $order->id;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'impacted_order_ids' => array_values(array_unique($impactedOrderIds)),
        ];
    }

    protected function resolveOrder(int $storeId, string $orderNumber): ?ChannelOrder
    {
        if ($orderNumber === '') {
            return null;
        }

        return ChannelOrder::query()
            ->where('store_id', $storeId)
            ->where(function ($query) use ($orderNumber) {
                $query->where('order_number', $orderNumber)
                    ->orWhere('external_order_id', $orderNumber);
            })
            ->first();
    }

    protected function resolvePackage(int $orderId, string $externalPackageId): ?ChannelOrderPackage
    {
        if ($externalPackageId === '') {
            return null;
        }

        return ChannelOrderPackage::query()
            ->where('channel_order_id', $orderId)
            ->where('external_package_id', $externalPackageId)
            ->first();
    }

    protected function resolveItem(
        int $storeId,
        string $externalLineId,
        ?int $orderId = null,
        ?string $barcode = null,
        ?string $stockCode = null,
    ): ?ChannelOrderItem {
        if ($externalLineId !== '') {
            return ChannelOrderItem::query()
                ->where('store_id', $storeId)
                ->where('external_line_id', $externalLineId)
                ->first();
        }

        $query = ChannelOrderItem::query()
            ->where('store_id', $storeId);

        if ($orderId) {
            $query->where('channel_order_id', $orderId);
        }

        if ($stockCode) {
            $candidate = (clone $query)->where('stock_code', $stockCode)->first();

            if ($candidate) {
                return $candidate;
            }
        }

        if ($barcode) {
            return $query->where('barcode', $barcode)->first();
        }

        return null;
    }
}
