<?php

namespace App\Services\Marketplace;

use App\Models\ChannelClaim;
use App\Models\MarketplaceStore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;

class MarketplaceClaimSyncService
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $context
     * @return array{created: int, updated: int, skipped: int, impacted_order_ids: array<int, int>}
     */
    public function sync(MarketplaceStore $store, array $items, array $context = []): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $impactedOrderIds = [];

        foreach ($items as $item) {
            try {
                $normalized = $this->normalize($store, $item);

                if ($normalized === null) {
                    $skipped++;
                    continue;
                }

                $claim = ChannelClaim::query()->firstOrNew([
                    'store_id' => $store->id,
                    'external_claim_id' => $normalized['external_claim_id'],
                ]);

                $wasRecentlyCreated = !$claim->exists;

                $claim->fill($normalized);
                $claim->last_synced_at = now();
                $claim->save();

                $this->syncItems($claim, $item);

                $wasRecentlyCreated ? $created++ : $updated++;

                $orderId = $this->relatedOrderId($store, $claim->order_number);

                if ($orderId !== null) {
                    $impactedOrderIds[] = $orderId;
                }
            } catch (Throwable) {
                $skipped++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'impacted_order_ids' => array_values(array_unique($impactedOrderIds)),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    protected function normalize(MarketplaceStore $store, array $item): ?array
    {
        $externalId = $this->firstFilled($item, [
            'external_claim_id',
            'claim_id',
            'claimId',
            'claimNumber',
            'claim_number',
            'return_id',
            'returnId',
            'refund_id',
            'refundId',
            'request_id',
            'requestId',
            'number',
            'id',
        ]);

        $orderNumber = $this->firstFilled($item, [
            'order_number',
            'orderNumber',
            'merchantOrderNumber',
            'merchant_order_number',
            'orderNo',
            'order_no',
            'order.id',
            'order.number',
            'order_id',
            'orderId',
        ]);

        $trackingNumber = $this->firstFilled($item, [
            'cargo_tracking_number',
            'cargoTrackingNumber',
            'trackingNumber',
            'tracking_number',
            'cargoSenderNumber',
            'cargo_number',
            'cargoNumber',
            'shipment.trackingNumber',
            'tracking.tracking_number',
        ]);

        if (blank($externalId)) {
            $seed = collect([$orderNumber, $trackingNumber, $this->firstFilled($item, ['createdDate', 'created_at', 'date', 'refundDate'])])
                ->filter(fn ($value) => filled($value))
                ->implode('|');

            $externalId = $seed !== '' ? sha1($store->id.'|'.$seed.'|'.json_encode($item)) : null;
        }

        if (blank($externalId)) {
            return null;
        }

        return [
            'store_id' => $store->id,
            'external_claim_id' => (string) $externalId,
            'order_number' => $orderNumber ? (string) $orderNumber : null,
            'cargo_tracking_number' => $trackingNumber ? (string) $trackingNumber : null,
            'cargo_provider' => $this->nullableString($this->firstFilled($item, [
                'cargo_provider',
                'cargoProvider',
                'cargoProviderName',
                'cargoCompany',
                'cargo_company',
                'carrier',
                'shipmentProvider',
            ])),
            'status' => $this->normalizeStatus((string) ($this->firstFilled($item, [
                'status',
                'claimStatus',
                'claim_status',
                'returnStatus',
                'return_status',
                'refundStatus',
                'refund_status',
                'refundStatusName',
                'requestStatus',
                'state',
            ]) ?: 'pending')),
            'type' => $this->normalizeType((string) ($this->firstFilled($item, [
                'type',
                'claimType',
                'claim_type',
                'requestType',
                'returnType',
                'refundType',
            ]) ?: 'return')),
            'reason' => $this->nullableString($this->firstFilled($item, [
                'reason',
                'claimReason',
                'claim_reason',
                'returnReason',
                'return_reason',
                'refundReason',
                'reason.name',
                'reasonName',
            ])),
            'reason_detail' => $this->nullableString($this->firstFilled($item, [
                'reason_detail',
                'reasonDetail',
                'description',
                'issueDescription',
                'detail',
            ])),
            'customer_note' => $this->nullableString($this->firstFilled($item, [
                'customer_note',
                'customerNote',
                'customerDescription',
                'customer.description',
                'note',
                'message',
            ])),
            'customer_name' => $this->nullableString($this->firstFilled($item, [
                'customer_name',
                'customerName',
                'customer.fullName',
                'customer.name',
                'buyerName',
                'buyer.name',
                'receiverName',
            ])),
            'created_date' => $this->parseDate($this->firstFilled($item, [
                'created_date',
                'createdDate',
                'created_at',
                'creationDate',
                'claimDate',
                'returnDate',
                'refundDate',
                'date',
                'date_created',
            ])) ?? now(),
            'raw_payload' => $item,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function syncItems(ChannelClaim $claim, array $payload): void
    {
        $rows = $this->itemRows($payload);

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $externalItemId = $this->claimItemId($claim, $row, $index);

            if ($externalItemId === '') {
                continue;
            }

            $claim->items()->updateOrCreate([
                'external_item_id' => $externalItemId,
            ], [
                'external_order_line_id' => $this->nullableString($this->firstFilled($row, [
                    'external_order_line_id',
                    'externalOrderLineId',
                    'orderLineId',
                    'order_line_id',
                    'lineItemId',
                    'lineId',
                ])),
                'product_name' => $this->nullableString($this->firstFilled($row, [
                    'product_name',
                    'productName',
                    'product.name',
                    'productTitle',
                    'name',
                    'title',
                ])),
                'barcode' => $this->nullableString($this->firstFilled($row, [
                    'barcode',
                    'product.barcode',
                    'productBarcode',
                    'gtin',
                ])),
                'stock_code' => $this->nullableString($this->firstFilled($row, [
                    'stock_code',
                    'stockCode',
                    'merchantSku',
                    'sku',
                    'product.stockCode',
                    'product.code',
                    'offer_sku',
                    'shop_sku',
                ])),
                'quantity' => max(1, (int) ($this->firstFilled($row, [
                    'quantity',
                    'qty',
                    'claimQuantity',
                    'returnQuantity',
                    'refundQuantity',
                    'amount',
                ]) ?: 1)),
                'price' => $this->toDecimal($this->firstFilled($row, [
                    'price',
                    'unitPrice',
                    'salePrice',
                    'itemPrice',
                    'totalPrice',
                    'total',
                    'subtotal',
                    'refund_total',
                    'refundTotal',
                    'amount.value',
                ])),
                'status' => $this->nullableString($this->firstFilled($row, [
                    'status',
                    'claimItemStatus',
                    'returnStatus',
                    'refundStatus',
                    'state',
                ])),
                'raw_payload' => $row,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, mixed>
     */
    protected function itemRows(array $payload): array
    {
        foreach ([
            'items',
            'claimItems',
            'claim_items',
            'claimLineItems',
            'claimLineItemList',
            'returnItems',
            'return_items',
            'refundItems',
            'refund_line_items',
            'line_items',
            'lines',
            'orderLines',
            'products',
        ] as $key) {
            $candidate = data_get($payload, $key);

            if (is_array($candidate) && $candidate !== []) {
                return array_is_list($candidate) ? $candidate : Arr::wrap($candidate);
            }
        }

        if (filled($this->firstFilled($payload, ['productName', 'product_name', 'barcode', 'stockCode', 'sku']))) {
            return [$payload];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function claimItemId(ChannelClaim $claim, array $row, int $index): string
    {
        $externalItemId = $this->firstFilled($row, [
            'external_item_id',
            'externalItemId',
            'claimLineItemId',
            'claimLineItem.id',
            'claimItemId',
            'claim_item_id',
            'returnItemId',
            'refundLineItemId',
            'lineItemId',
            'lineId',
            'orderLineId',
            'itemId',
            'id',
        ]);

        if (filled($externalItemId)) {
            return (string) $externalItemId;
        }

        $seed = collect([
            $claim->external_claim_id,
            $this->firstFilled($row, ['orderLineId', 'lineItemId', 'lineId']),
            $this->firstFilled($row, ['stockCode', 'merchantSku', 'sku']),
            $this->firstFilled($row, ['barcode', 'product.barcode']),
            $index,
        ])->map(fn ($value) => (string) $value)->implode('|');

        return sha1($seed);
    }

    protected function normalizeStatus(string $status): string
    {
        $normalized = Str::of($status)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/u', ' ')
            ->squish()
            ->toString();

        return match (true) {
            $normalized === '' => 'pending',
            str_contains($normalized, 'cancel') || str_contains($normalized, 'iptal') => 'cancelled',
            preg_match('/\b(reject|rejected|deny|denied|refuse|refused|red|reddedildi|reddet)\b/u', $normalized) === 1 => 'rejected',
            str_contains($normalized, 'approve') || str_contains($normalized, 'accept') || str_contains($normalized, 'refund complete') || str_contains($normalized, 'iade edildi') => 'approved',
            str_contains($normalized, 'delivered') || str_contains($normalized, 'received') || str_contains($normalized, 'teslim') => 'delivered',
            str_contains($normalized, 'transit') || str_contains($normalized, 'yolda') => 'in_transit',
            str_contains($normalized, 'cargo') || str_contains($normalized, 'kargo') || str_contains($normalized, 'ship') => 'shipped',
            str_contains($normalized, 'issue') || str_contains($normalized, 'dispute') || str_contains($normalized, 'analysis') || str_contains($normalized, 'analiz') => 'unresolved',
            default => 'pending',
        };
    }

    protected function normalizeType(string $type): string
    {
        $normalized = Str::of($type)->lower()->ascii()->toString();

        return match (true) {
            str_contains($normalized, 'cancel') || str_contains($normalized, 'iptal') => 'cancel',
            str_contains($normalized, 'exchange') || str_contains($normalized, 'degisim') => 'exchange',
            default => 'return',
        };
    }

    protected function parseDate(mixed $value): ?CarbonImmutable
    {
        if (blank($value)) {
            return null;
        }

        try {
            if (is_numeric($value)) {
                $timestamp = (int) $value;

                if (abs($timestamp) > 9999999999) {
                    return CarbonImmutable::createFromTimestampMs($timestamp);
                }

                return CarbonImmutable::createFromTimestampUTC($timestamp);
            }

            return CarbonImmutable::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }

    protected function toDecimal(mixed $value): ?float
    {
        if (is_array($value)) {
            $value = data_get($value, 'value');
        }

        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }

    /**
     * @param  array<int, string>  $keys
     */
    protected function firstFilled(array $item, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = str_contains($key, '.') ? data_get($item, $key) : ($item[$key] ?? null);

            if (filled($value)) {
                return $value;
            }
        }

        return null;
    }

    protected function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    protected function relatedOrderId(MarketplaceStore $store, ?string $orderNumber): ?int
    {
        if (blank($orderNumber)) {
            return null;
        }

        return \App\Models\ChannelOrder::query()
            ->where('store_id', $store->id)
            ->where(function ($query) use ($orderNumber) {
                $query->where('order_number', $orderNumber)
                    ->orWhere('external_order_id', $orderNumber);
            })
            ->value('id');
    }
}
