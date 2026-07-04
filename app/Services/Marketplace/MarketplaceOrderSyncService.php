<?php

namespace App\Services\Marketplace;

use App\Models\ChannelListing;
use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
use App\Models\ChannelOrderPackage;
use App\Models\MarketplaceStore;
use App\Services\NotificationCenterService;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class MarketplaceOrderSyncService
{
    public function __construct(
        protected MarketplaceProductMatcher $matcher,
    ) {
    }

    /**
     * @param  array<int, array<string, mixed>>  $packages
     * @param  array<string, mixed>  $context
     * @return array{created: int, updated: int, skipped: int, impacted_order_ids: array<int>}
     */
    public function sync(MarketplaceStore $store, array $packages, array $context = []): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $impactedOrderIds = [];

        foreach ($packages as $payload) {
            $orderData = $payload['order'] ?? [];
            $packageData = $payload['package'] ?? [];
            $itemRows = $payload['items'] ?? [];

            [
                'order' => $orderData,
                'package' => $packageData,
                'items' => $itemRows,
            ] = $this->normalizeMarketplacePayload($store, $orderData, $packageData, $itemRows);

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
            $previousOrderStatus = $order->exists ? (string) $order->order_status : null;

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
                $this->syncListingCommissionFromOrderItem($item);

                $touchedItem = $touchedItem || $itemChanged || $itemDirty;
            }

            $this->emitOrderNotifications($store, $order, $orderDirty, $previousOrderStatus, $context);

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

    /**
     * @param  array<string, mixed>  $orderData
     * @param  array<string, mixed>  $packageData
     * @param  array<int, array<string, mixed>>  $itemRows
     * @return array{order: array<string, mixed>, package: array<string, mixed>, items: array<int, array<string, mixed>>}
     */
    protected function normalizeMarketplacePayload(
        MarketplaceStore $store,
        array $orderData,
        array $packageData,
        array $itemRows,
    ): array {
        if (Str::lower(trim((string) $store->marketplace)) !== 'pazarama') {
            return [
                'order' => $orderData,
                'package' => $packageData,
                'items' => $itemRows,
            ];
        }

        return $this->normalizePazaramaPayload($orderData, $packageData, $itemRows);
    }

    /**
     * @param  array<string, mixed>  $orderData
     * @param  array<string, mixed>  $packageData
     * @param  array<int, array<string, mixed>>  $itemRows
     * @return array{order: array<string, mixed>, package: array<string, mixed>, items: array<int, array<string, mixed>>}
     */
    protected function normalizePazaramaPayload(
        array $orderData,
        array $packageData,
        array $itemRows,
    ): array {
        $packagePayload = Arr::wrap($packageData['raw_payload'] ?? []);
        $orderPayload = Arr::wrap($orderData['raw_payload'] ?? []);
        $trackingNumber = $this->firstFilled([
            $packageData['cargo_tracking_number'] ?? null,
            data_get($packagePayload, 'trackingNumber'),
            data_get($orderPayload, 'trackingNumber'),
        ]);

        $packageDeliveredAt = $packageData['delivered_at'] ?? data_get($packagePayload, 'deliveredDate');
        $orderDeliveredAt = $orderData['delivered_at'] ?? data_get($orderPayload, 'deliveredDate');

        $normalizedItems = [];
        $itemStatuses = [];
        $itemStatusNames = [];
        $itemEstimatedShippingDates = [];

        foreach ($itemRows as $itemData) {
            $itemPayload = Arr::wrap($itemData['raw_payload'] ?? []);

            $itemStatusName = trim((string) data_get($itemPayload, 'orderItemStatusName'));

            if ($itemStatusName !== '') {
                $itemStatusNames[] = $itemStatusName;
            }

            $itemEstimatedShippingDate = trim((string) data_get($itemPayload, 'estimatedShippingDate'));

            if ($itemEstimatedShippingDate !== '') {
                $itemEstimatedShippingDates[] = $itemEstimatedShippingDate;
            }

            $lineStatus = $this->resolvePazaramaCanonicalStatus(
                $itemPayload,
                $itemData['line_status'] ?? null,
                $trackingNumber,
                $packageDeliveredAt ?: $orderDeliveredAt,
            );

            if ($lineStatus !== null) {
                $itemData['line_status'] = $lineStatus;
                $itemStatuses[] = $lineStatus;
            }

            $normalizedItems[] = $itemData;
        }

        $aggregatedItemStatus = $this->aggregateCanonicalStatuses($itemStatuses);
        $firstItemStatusName = $this->firstFilled($itemStatusNames);
        $firstEstimatedShippingDate = $this->firstFilled($itemEstimatedShippingDates);

        if ($firstItemStatusName !== null) {
            if (!filled(data_get($packagePayload, 'orderItemStatusName'))) {
                $packagePayload['orderItemStatusName'] = $firstItemStatusName;
            }

            if (!filled(data_get($orderPayload, 'orderItemStatusName'))) {
                $orderPayload['orderItemStatusName'] = $firstItemStatusName;
            }
        }

        if ($firstEstimatedShippingDate !== null) {
            if (!filled(data_get($packagePayload, 'estimatedShippingDate'))) {
                $packagePayload['estimatedShippingDate'] = $firstEstimatedShippingDate;
            }

            if (!filled(data_get($orderPayload, 'estimatedShippingDate'))) {
                $orderPayload['estimatedShippingDate'] = $firstEstimatedShippingDate;
            }
        }

        $packageData['raw_payload'] = $packagePayload;
        $orderData['raw_payload'] = $orderPayload;

        $packageStatus = $this->resolvePazaramaCanonicalStatus(
            $packagePayload,
            $packageData['package_status'] ?? null,
            $trackingNumber,
            $packageDeliveredAt,
        ) ?: $aggregatedItemStatus;

        $orderStatus = $this->resolvePazaramaCanonicalStatus(
            $orderPayload,
            $orderData['order_status'] ?? null,
            $trackingNumber,
            $orderDeliveredAt ?: $packageDeliveredAt,
        ) ?: $packageStatus ?: $aggregatedItemStatus;

        if ($packageStatus !== null) {
            $packageData['package_status'] = $packageStatus;
        }

        if ($orderStatus !== null) {
            $orderData['order_status'] = $orderStatus;
        }

        if ($this->shouldClearPazaramaShippedAt(
            $packageStatus,
            $trackingNumber,
            $packageDeliveredAt ?: $orderDeliveredAt,
            $packagePayload,
            $normalizedItems,
        )) {
            $packageData['shipped_at'] = null;
        }

        return [
            'order' => $orderData,
            'package' => $packageData,
            'items' => $normalizedItems,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolvePazaramaCanonicalStatus(
        array $payload,
        ?string $fallbackStatus = null,
        ?string $trackingNumber = null,
        mixed $deliveredAt = null,
    ): ?string {
        $statusLabel = $this->firstFilled([
            data_get($payload, 'orderItemStatusName'),
            data_get($payload, 'orderStatusName'),
            data_get($payload, 'shipmentPackageStatusName'),
            data_get($payload, 'statusName'),
            data_get($payload, 'status'),
        ]);

        $resolvedFromLabel = $this->canonicalizeStatusLabel($statusLabel);

        if ($resolvedFromLabel !== null) {
            return $resolvedFromLabel;
        }

        $statusCode = data_get($payload, 'orderItemStatus');

        if ($statusCode === null || $statusCode === '') {
            $statusCode = data_get($payload, 'orderStatus');
        }

        if ((int) $statusCode === 3 && blank($trackingNumber) && !filled($deliveredAt)) {
            return 'approved';
        }

        return $this->canonicalizeStatusLabel($fallbackStatus);
    }

    protected function syncListingCommissionFromOrderItem(ChannelOrderItem $item): void
    {
        $commissionRate = $this->normalizeCommissionRate($item->commission_rate);

        if ($commissionRate === null || !$item->channel_listing_id) {
            return;
        }

        ChannelListing::query()
            ->whereKey($item->channel_listing_id)
            ->update([
                'commission_rate' => $commissionRate,
                'commission_source' => 'order_item',
                'commission_synced_at' => now(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function emitOrderNotifications(
        MarketplaceStore $store,
        ChannelOrder $order,
        bool $orderDirty,
        ?string $previousOrderStatus,
        array $context,
    ): void {
        $currentStatus = $this->canonicalizeStatusLabel($order->order_status) ?: $this->normalizeStatusText($order->order_status);
        $previousStatus = $this->canonicalizeStatusLabel($previousOrderStatus) ?: $this->normalizeStatusText($previousOrderStatus);
        $notificationCenter = app(NotificationCenterService::class);

        if ($orderDirty && !in_array($currentStatus, ['cancelled', 'returned'], true) && $this->shouldNotifyFreshOrder($order, $context)) {
            $notificationCenter->notifyOrder($store, $order, 'created', $context);
        }

        if ($currentStatus === 'cancelled' && $previousStatus !== 'cancelled' && $this->shouldNotifyStatusTransition($order, $orderDirty, $context, 'cancelled_at')) {
            $notificationCenter->notifyOrder($store, $order, 'cancelled', $context);
        }

        if ($currentStatus === 'returned' && $previousStatus !== 'returned' && $this->shouldNotifyStatusTransition($order, $orderDirty, $context, 'returned_at')) {
            $notificationCenter->notifyOrder($store, $order, 'returned', $context);
        }

        // WhatsApp sipariş onayı için domain event
        if ($order->isDirty('order_status') && $previousOrderStatus !== null && $previousOrderStatus !== $order->order_status) {
            \App\Events\OrderStatusChanged::dispatch(
                $order,
                $previousOrderStatus,
                $order->order_status,
                'sync',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function shouldNotifyFreshOrder(ChannelOrder $order, array $context): bool
    {
        if ($this->isRealtimeTrigger($context)) {
            return true;
        }

        return $this->isRecentOperationalDate($order->ordered_at);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function shouldNotifyStatusTransition(ChannelOrder $order, bool $orderDirty, array $context, string $dateColumn): bool
    {
        if ($this->isRealtimeTrigger($context)) {
            return true;
        }

        if (!$orderDirty) {
            return true;
        }

        return $this->isRecentOperationalDate($order->{$dateColumn} ?: $order->ordered_at);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function isRealtimeTrigger(array $context): bool
    {
        return in_array((string) ($context['trigger_type'] ?? ''), ['webhook', 'webhook_replay'], true);
    }

    protected function isRecentOperationalDate(mixed $value): bool
    {
        if (!$value) {
            return false;
        }

        try {
            $date = $value instanceof CarbonInterface
                ? $value
                : \Illuminate\Support\Carbon::parse($value);
        } catch (\Throwable) {
            return false;
        }

        return $date->greaterThanOrEqualTo(now()->subHours(12));
    }

    protected function normalizeCommissionRate(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $rate = (float) str_replace(',', '.', (string) $value);

        if ($rate < 0 || $rate > 100) {
            return null;
        }

        return round($rate, 2);
    }

    protected function canonicalizeStatusLabel(?string $status): ?string
    {
        $normalized = $this->normalizeStatusText($status);

        if ($normalized === '') {
            return null;
        }

        return match (true) {
            str_contains($normalized, 'deliver'),
            str_contains($normalized, 'teslim') => 'delivered',
            str_contains($normalized, 'cancel'),
            str_contains($normalized, 'iptal') => 'cancelled',
            str_contains($normalized, 'return'),
            str_contains($normalized, 'iade') => 'returned',
            str_contains($normalized, 'hazirlaniyor'),
            str_contains($normalized, 'packing'),
            str_contains($normalized, 'hazir') => 'packing',
            str_contains($normalized, 'kargoya verildi'),
            str_contains($normalized, 'shipped'),
            str_contains($normalized, 'ship') => 'shipped',
            str_contains($normalized, 'siparisiniz alindi'),
            str_contains($normalized, 'siparis alindi'),
            str_contains($normalized, 'approved'),
            str_contains($normalized, 'onay'),
            str_contains($normalized, 'processing') => 'approved',
            str_contains($normalized, 'new'),
            str_contains($normalized, 'created') => 'new',
            default => null,
        };
    }

    protected function normalizeStatusText(?string $status): string
    {
        $value = trim((string) $status);

        if ($value === '') {
            return '';
        }

        return Str::of(Str::ascii($value))
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/u', ' ')
            ->squish()
            ->toString();
    }

    /**
     * @param  array<int, string>  $statuses
     */
    protected function aggregateCanonicalStatuses(array $statuses): ?string
    {
        $statuses = array_values(array_filter($statuses, fn ($status) => is_string($status) && $status !== ''));

        if ($statuses === []) {
            return null;
        }

        foreach (['returned', 'cancelled', 'delivered', 'shipped', 'packing', 'approved', 'new'] as $candidate) {
            if (in_array($candidate, $statuses, true)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $packagePayload
     * @param  array<int, array<string, mixed>>  $itemRows
     */
    protected function shouldClearPazaramaShippedAt(
        ?string $packageStatus,
        ?string $trackingNumber,
        mixed $deliveredAt,
        array $packagePayload,
        array $itemRows,
    ): bool {
        if (!in_array($packageStatus, ['approved', 'packing', 'new'], true)) {
            return false;
        }

        if (filled($trackingNumber) || filled($deliveredAt)) {
            return false;
        }

        $payloads = [$packagePayload];

        foreach ($itemRows as $itemData) {
            $payloads[] = Arr::wrap($itemData['raw_payload'] ?? []);
        }

        foreach ($payloads as $payload) {
            $estimatedShippingDate = data_get($payload, 'estimatedShippingDate');
            $actualShippingDate = data_get($payload, 'shippedDate') ?: data_get($payload, 'shippingDate');
            $payloadTrackingNumber = data_get($payload, 'trackingNumber') ?: data_get($payload, 'cargoTrackingNumber');

            if (filled($estimatedShippingDate) && !filled($actualShippingDate) && !filled($payloadTrackingNumber)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $values
     */
    protected function firstFilled(array $values): ?string
    {
        foreach ($values as $value) {
            if (!filled($value)) {
                continue;
            }

            return trim((string) $value);
        }

        return null;
    }
}
