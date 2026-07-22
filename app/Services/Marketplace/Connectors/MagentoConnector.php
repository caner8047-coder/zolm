<?php

namespace App\Services\Marketplace\Connectors;

use App\Models\ChannelListing;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Contracts\PullsClaims;
use App\Services\Marketplace\Contracts\PullsFinancials;
use App\Services\Marketplace\Contracts\PullsOrders;
use App\Services\Marketplace\Contracts\PullsProducts;
use App\Services\Marketplace\Contracts\PushesPrice;
use App\Services\Marketplace\Contracts\PushesStock;
use App\Services\Marketplace\MagentoRestGateway;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class MagentoConnector extends AbstractMarketplaceConnector implements PullsClaims, PullsFinancials, PullsOrders, PullsProducts, PushesPrice, PushesStock
{
    public function __construct(protected MagentoRestGateway $gateway) {}

    public function providerKey(): string
    {
        return 'magento';
    }

    public function displayName(): string
    {
        return 'Adobe Commerce / Magento';
    }

    public function defaultApiBaseUrl(): ?string
    {
        return config('marketplace.magento.base_url') ?: null;
    }

    /**
     * @return array<string, bool>
     */
    public function capabilities(): array
    {
        return [
            'orders' => true,
            'products' => true,
            'finance' => true,
            'webhooks' => false,
            'price_push' => true,
            'stock_push' => true,
            'package_status' => false,
            'package_picking' => false,
            'package_invoiced' => false,
            'common_label' => false,
            'package_common_label_create' => false,
            'package_common_label_get' => false,
            'invoice_link' => false,
            'package_invoice_link' => false,
            'questions' => false,
            'question_answer' => false,
            'claims' => true,
            'claim_approve' => false,
            'claim_reject' => false,
        ];
    }

    public function testConnection(MarketplaceStore $store): array
    {
        try {
            $payload = $this->gateway->call($store, 'GET', 'products', $this->searchCriteria(pageSize: 1, currentPage: 1));

            return [
                'ok' => true,
                'message' => 'Magento REST erişim anahtarı ve ürün okuma yetkisi doğrulandı.',
                'meta' => [
                    'provider' => $this->providerKey(),
                    'sample_count' => count($this->items($payload)),
                    'api_base_url' => $this->gateway->apiBaseUrl($store),
                ],
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'message' => 'Magento bağlantısı doğrulanamadı: '.$exception->getMessage(),
            ];
        }
    }

    public function pullOrders(MarketplaceStore $store, array $options = []): array
    {
        $filters = $this->dateFilters('created_at', $options['start_date'] ?? now()->subDays(7), $options['end_date'] ?? now());

        if (filled($options['status'] ?? null)) {
            $filters[] = ['field' => 'status', 'value' => (string) $options['status'], 'condition_type' => 'eq'];
        }

        $result = $this->pullPaginated($store, 'orders', $options, 'order_page_size', $filters, 'entity_id');

        return [
            'items' => collect($result['items'])->map(fn (array $order) => $this->normalizeOrder($order))->values()->all(),
            'meta' => $this->syncMeta($result),
        ];
    }

    public function pullProducts(MarketplaceStore $store, array $options = []): array
    {
        $filters = [];

        if (filled($options['updated_after'] ?? $options['start_date'] ?? null)) {
            $filters = $this->dateFilters(
                'updated_at',
                $options['updated_after'] ?? $options['start_date'],
                $options['updated_before'] ?? $options['end_date'] ?? now(),
            );
        }

        if (filled($options['sku'] ?? null)) {
            $filters[] = ['field' => 'sku', 'value' => (string) $options['sku'], 'condition_type' => 'eq'];
        }

        $result = $this->pullPaginated($store, 'products', $options, 'product_page_size', $filters, 'entity_id');

        return [
            'items' => collect($result['items'])->map(fn (array $product) => $this->normalizeProduct($product, $store))->values()->all(),
            'meta' => $this->syncMeta($result),
        ];
    }

    public function pullFinancialEvents(MarketplaceStore $store, array $options = []): array
    {
        $filters = $this->dateFilters('created_at', $options['start_date'] ?? now()->subDays(7), $options['end_date'] ?? now());
        $result = $this->pullPaginated($store, 'invoices', $options, 'finance_page_size', $filters, 'entity_id');

        return [
            'items' => collect($result['items'])->map(fn (array $invoice) => $this->normalizeFinancialEvent($invoice))->values()->all(),
            'meta' => array_merge($this->syncMeta($result), ['finance_mode' => 'invoice_summary']),
        ];
    }

    public function pullClaims(MarketplaceStore $store, array $options = []): array
    {
        $filters = $this->dateFilters('created_at', $options['start_date'] ?? now()->subDays(30), $options['end_date'] ?? now());
        $result = $this->pullPaginated($store, 'creditmemos', $options, 'claim_page_size', $filters, 'entity_id');

        return [
            'items' => collect($result['items'])->map(fn (array $creditMemo) => $this->normalizeClaim($creditMemo))->values()->all(),
            'meta' => array_merge($this->syncMeta($result), ['claim_mode' => 'credit_memo']),
        ];
    }

    public function pushPrice(ChannelListing $listing, float $price, array $context = []): array
    {
        $listing->loadMissing(['store.connection', 'channelProduct']);
        $sku = $this->listingSku($listing);
        $storeId = max(0, (int) ($context['store_id'] ?? config('marketplace.magento.default_price_store_id', 0)));
        $payload = ['prices' => [[
            'price' => round($price, 2),
            'store_id' => $storeId,
            'sku' => $sku,
        ]]];
        $response = $this->gateway->call($listing->store, 'POST', 'products/base-prices', [], $payload);

        return [
            'status' => 'completed',
            'provider' => $this->providerKey(),
            'listing_id' => $listing->id,
            'sku' => $sku,
            'price' => round($price, 2),
            'external_action_id' => $sku,
            'response' => $response,
        ];
    }

    public function pushStock(ChannelListing $listing, int $quantity, array $context = []): array
    {
        $listing->loadMissing(['store.connection', 'channelProduct']);
        $sku = $this->listingSku($listing);
        $quantity = max(0, $quantity);
        $sourceCode = trim((string) ($context['source_code'] ?? $this->gateway->sourceCode($listing->store)));

        if ($sourceCode === '' || preg_match('/^[A-Za-z0-9_-]+$/', $sourceCode) !== 1) {
            throw new \RuntimeException('Magento stok kaynak kodu geçersiz.');
        }

        $payload = ['sourceItems' => [[
            'sku' => $sku,
            'source_code' => $sourceCode,
            'quantity' => $quantity,
            'status' => $quantity > 0 ? 1 : 0,
        ]]];
        $response = $this->gateway->call($listing->store, 'POST', 'inventory/source-items', [], $payload);

        return [
            'status' => 'completed',
            'provider' => $this->providerKey(),
            'listing_id' => $listing->id,
            'sku' => $sku,
            'source_code' => $sourceCode,
            'quantity' => $quantity,
            'external_action_id' => $sku.'@'.$sourceCode,
            'response' => $response,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<int, array{field: string, value: string, condition_type: string}>  $filters
     * @return array{items: array<int, array<string, mixed>>, pages_processed: int, total_count: int, more_pages_available: bool, cursor_after: string}
     */
    protected function pullPaginated(MarketplaceStore $store, string $path, array $options, string $pageSizeConfig, array $filters, string $sortField): array
    {
        $pageSize = min(200, max(1, (int) ($options['page_size'] ?? config('marketplace.magento.'.$pageSizeConfig, 100))));
        $maxPages = max(1, (int) ($options['max_pages'] ?? config('marketplace.magento.max_pages_per_sync', 50)));
        $page = max(1, (int) ($options['page'] ?? 1));
        $items = [];
        $pagesProcessed = 0;
        $totalCount = 0;
        $more = false;

        do {
            $payload = $this->gateway->call($store, 'GET', $path, $this->searchCriteria(
                pageSize: $pageSize,
                currentPage: $page,
                filters: $filters,
                sortField: $sortField,
            ));
            $rows = $this->items($payload);
            $totalCount = max($totalCount, (int) data_get($payload, 'total_count', count($rows)));

            foreach ($rows as $row) {
                $items[] = $row;
            }

            $pagesProcessed++;
            $more = ($page * $pageSize) < $totalCount && $rows !== [];
            $page++;
        } while ($more && $pagesProcessed < $maxPages);

        return [
            'items' => $items,
            'pages_processed' => $pagesProcessed,
            'total_count' => $totalCount,
            'more_pages_available' => $more,
            'cursor_after' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<int, array{field: string, value: string, condition_type: string}>  $filters
     * @return array<string, mixed>
     */
    protected function searchCriteria(int $pageSize, int $currentPage, array $filters = [], string $sortField = 'entity_id'): array
    {
        $criteria = [
            'pageSize' => $pageSize,
            'currentPage' => $currentPage,
            'sortOrders' => [[
                'field' => $sortField,
                'direction' => 'ASC',
            ]],
        ];

        foreach (array_values($filters) as $index => $filter) {
            $criteria['filter_groups'][$index]['filters'][] = $filter;
        }

        return ['searchCriteria' => $criteria];
    }

    /**
     * @return array<int, array{field: string, value: string, condition_type: string}>
     */
    protected function dateFilters(string $field, mixed $from, mixed $to): array
    {
        return array_values(array_filter([
            $this->apiDateTime($from) ? ['field' => $field, 'value' => $this->apiDateTime($from), 'condition_type' => 'gteq'] : null,
            $this->apiDateTime($to) ? ['field' => $field, 'value' => $this->apiDateTime($to), 'condition_type' => 'lteq'] : null,
        ]));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function items(mixed $payload): array
    {
        $items = is_array($payload) ? data_get($payload, 'items', []) : [];

        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeOrder(array $order): array
    {
        $entityId = (string) (data_get($order, 'entity_id') ?: data_get($order, 'increment_id'));
        $number = (string) (data_get($order, 'increment_id') ?: $entityId);
        $status = $this->normalizeOrderStatus((string) (data_get($order, 'status') ?: data_get($order, 'state')));
        $updatedAt = $this->dateTime(data_get($order, 'updated_at')) ?: $this->dateTime(data_get($order, 'created_at'));
        $billing = (array) data_get($order, 'billing_address', []);
        $shippingAssignment = (array) data_get($order, 'extension_attributes.shipping_assignments.0', []);
        $shipping = (array) data_get($shippingAssignment, 'shipping.address', []);
        $tracks = (array) (data_get($order, 'tracks') ?: data_get($order, 'extension_attributes.shipping_assignments.0.shipping.tracks') ?: []);
        $track = is_array(data_get($tracks, '0')) ? (array) data_get($tracks, '0') : [];

        return [
            'order' => [
                'external_order_id' => $entityId,
                'order_number' => $number,
                'order_status' => $status,
                'commercial_type' => filled(data_get($billing, 'vat_id')) || filled(data_get($billing, 'company')) ? 'commercial' : 'individual',
                'currency' => Str::upper((string) (data_get($order, 'order_currency_code') ?: data_get($order, 'base_currency_code') ?: 'TRY')),
                'exchange_rate' => data_get($order, 'base_to_order_rate') ?: 1,
                'customer_name' => trim((string) data_get($order, 'customer_firstname').' '.(string) data_get($order, 'customer_lastname')),
                'customer_email' => data_get($order, 'customer_email'),
                'customer_phone' => data_get($shipping, 'telephone') ?: data_get($billing, 'telephone'),
                'billing_name' => trim((string) data_get($billing, 'firstname').' '.(string) data_get($billing, 'lastname')),
                'billing_tax_number' => data_get($billing, 'vat_id'),
                'shipment_country' => data_get($shipping, 'country_id'),
                'shipment_city' => data_get($shipping, 'city'),
                'shipment_district' => data_get($shipping, 'region'),
                'ordered_at' => $this->dateTime(data_get($order, 'created_at')),
                'approved_at' => in_array($status, ['approved', 'picking', 'shipped', 'delivered'], true) ? $updatedAt : null,
                'delivered_at' => $status === 'delivered' ? $updatedAt : null,
                'cancelled_at' => $status === 'cancelled' ? $updatedAt : null,
                'returned_at' => $status === 'returned' ? $updatedAt : null,
                'raw_payload' => $order,
            ],
            'package' => [
                'external_package_id' => (string) (data_get($shippingAssignment, 'shipping.extension_attributes.source_code') ?: $entityId),
                'package_number' => $number,
                'package_status' => $status,
                'cargo_company' => data_get($track, 'title') ?: data_get($order, 'shipping_description'),
                'cargo_tracking_number' => data_get($track, 'track_number'),
                'cargo_barcode' => data_get($track, 'track_number'),
                'shipment_provider' => data_get($track, 'carrier_code') ?: data_get($shipping, 'method'),
                'shipped_at' => in_array($status, ['shipped', 'delivered'], true) ? $updatedAt : null,
                'delivered_at' => $status === 'delivered' ? $updatedAt : null,
                'raw_payload' => $shippingAssignment,
            ],
            'items' => $this->normalizeOrderItems((array) data_get($order, 'items', []), $entityId, $status),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeOrderItems(array $rows, string $orderId, string $status): array
    {
        $rows = array_values(array_filter($rows, 'is_array'));
        $children = collect($rows)->filter(fn (array $row) => filled(data_get($row, 'parent_item_id')))->groupBy(fn (array $row) => (string) data_get($row, 'parent_item_id'));

        return collect($rows)
            ->filter(fn (array $row) => blank(data_get($row, 'parent_item_id')))
            ->values()
            ->map(function (array $row, int $index) use ($children, $orderId, $status): array {
                $child = (array) ($children->get((string) data_get($row, 'item_id'))?->first() ?? []);
                $skuSource = $child !== [] ? $child : $row;
                $quantity = max(1, (int) round((float) (data_get($row, 'qty_ordered') ?: data_get($skuSource, 'qty_ordered') ?: 1)));
                $unitPrice = $this->money(data_get($row, 'price_incl_tax') ?? data_get($row, 'price'));
                $gross = $this->money(data_get($row, 'row_total_incl_tax') ?? data_get($row, 'row_total'));
                $discount = abs($this->money(data_get($row, 'discount_amount')) ?? 0);

                return [
                    'external_line_id' => (string) (data_get($row, 'item_id') ?: sha1($orderId.'|'.data_get($skuSource, 'sku').'|'.$index)),
                    'stock_code' => (string) data_get($skuSource, 'sku'),
                    'barcode' => $this->customAttributeValue($skuSource, ['barcode', 'ean', 'gtin']),
                    'product_name' => data_get($row, 'name') ?: data_get($skuSource, 'name'),
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'gross_amount' => $gross ?? ($unitPrice !== null ? round($unitPrice * $quantity, 2) : null),
                    'discount_amount' => $discount,
                    'marketplace_discount_amount' => null,
                    'billable_amount' => $this->money(data_get($row, 'row_total_incl_tax')) ?? ($gross !== null ? max(0, round($gross - $discount, 2)) : null),
                    'commission_rate' => null,
                    'vat_rate' => data_get($row, 'tax_percent'),
                    'line_status' => $status,
                    'raw_payload' => ['parent' => $row, 'child' => $child ?: null],
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeProduct(array $product, MarketplaceStore $store): array
    {
        $sku = (string) (data_get($product, 'sku') ?: data_get($product, 'id'));
        $status = (int) data_get($product, 'status', 1) === 1;
        $stock = data_get($product, 'extension_attributes.stock_item.qty');
        $images = collect((array) data_get($product, 'media_gallery_entries', []))
            ->filter(fn ($entry) => is_array($entry))
            ->map(fn (array $entry) => data_get($entry, 'file'))
            ->filter()
            ->values()
            ->all();
        $attributes = $this->customAttributes($product);

        return [
            'product' => [
                'external_product_id' => (string) (data_get($product, 'id') ?: $sku),
                'external_parent_id' => null,
                'stock_code' => $sku,
                'barcode' => $this->firstFilled($attributes, ['barcode', 'ean', 'gtin', 'upc']),
                'title' => data_get($product, 'name'),
                'brand' => $this->firstFilled($attributes, ['manufacturer', 'brand']),
                'category_name' => null,
                'vat_rate' => data_get($product, 'tax_class_id'),
                'description' => $this->firstFilled($attributes, ['description', 'short_description']),
                'images' => $images,
                'attributes' => array_merge($attributes, [
                    'type_id' => data_get($product, 'type_id'),
                    'attribute_set_id' => data_get($product, 'attribute_set_id'),
                    'category_ids' => data_get($product, 'extension_attributes.category_links'),
                    'website_ids' => data_get($product, 'extension_attributes.website_ids'),
                ]),
                'approval_status' => $status ? 'approved' : 'passive',
                'is_catalog_product' => true,
                'raw_payload' => $product,
            ],
            'listing' => array_merge([
                'listing_id' => $sku,
                'listing_status' => $status ? 'active' : 'passive',
                'sale_price' => $this->money(data_get($product, 'price')),
                'list_price' => $this->money(data_get($product, 'price')),
                'currency' => Str::upper((string) ($store->currency ?: 'TRY')),
                'stock_quantity' => (int) round((float) ($stock ?? 0)),
                'published_at' => $this->dateTime(data_get($product, 'created_at')),
            ], $this->catalogDeliveryTermData($attributes, $product)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeFinancialEvent(array $invoice): array
    {
        $id = (string) (data_get($invoice, 'entity_id') ?: data_get($invoice, 'increment_id'));
        $orderReference = (string) (data_get($invoice, 'order_increment_id') ?: data_get($invoice, 'order_id'));

        return [
            'event_source' => 'magento_invoice_summary',
            'external_event_id' => 'invoice-'.$id,
            'order_number' => $orderReference,
            'external_package_id' => null,
            'external_line_id' => null,
            'stock_code' => null,
            'barcode' => null,
            'event_type' => 'payment',
            'reference_number' => (string) (data_get($invoice, 'increment_id') ?: $id),
            'event_date' => $this->dateTime(data_get($invoice, 'created_at')),
            'due_date' => null,
            'settlement_date' => $this->dateTime(data_get($invoice, 'updated_at') ?: data_get($invoice, 'created_at')),
            'amount' => abs($this->money(data_get($invoice, 'grand_total')) ?? 0),
            'currency' => Str::upper((string) (data_get($invoice, 'order_currency_code') ?: data_get($invoice, 'base_currency_code') ?: 'TRY')),
            'direction' => 'credit',
            'status' => $this->invoiceStatus(data_get($invoice, 'state')),
            'notes' => 'Magento fatura özeti; ödeme kuruluşu hakedişi değildir.',
            'raw_payload' => $invoice,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeClaim(array $creditMemo): array
    {
        $id = (string) (data_get($creditMemo, 'entity_id') ?: data_get($creditMemo, 'increment_id'));
        $orderReference = (string) (data_get($creditMemo, 'order_increment_id') ?: data_get($creditMemo, 'order_id'));

        return [
            'external_claim_id' => 'magento-creditmemo-'.$id,
            'order_number' => $orderReference,
            'cargo_tracking_number' => null,
            'cargo_provider' => null,
            'status' => $this->creditMemoStatus(data_get($creditMemo, 'state')),
            'type' => 'return',
            'reason' => data_get($creditMemo, 'customer_note') ?: 'Credit memo',
            'reason_detail' => data_get($creditMemo, 'comments.0.comment'),
            'customer_note' => data_get($creditMemo, 'customer_note'),
            'customer_name' => null,
            'created_date' => $this->dateTime(data_get($creditMemo, 'created_at')),
            'items' => collect((array) data_get($creditMemo, 'items', []))->filter(fn ($item) => is_array($item))->map(fn (array $item) => [
                'external_item_id' => (string) (data_get($item, 'entity_id') ?: data_get($item, 'order_item_id') ?: sha1($id.'|'.data_get($item, 'sku'))),
                'external_order_line_id' => (string) data_get($item, 'order_item_id'),
                'product_name' => data_get($item, 'name'),
                'stock_code' => data_get($item, 'sku'),
                'barcode' => null,
                'quantity' => max(1, (int) round((float) data_get($item, 'qty', 1))),
                'reason' => data_get($creditMemo, 'customer_note'),
                'customer_note' => data_get($creditMemo, 'customer_note'),
                'raw_payload' => $item,
            ])->values()->all(),
            'raw_payload' => $creditMemo,
        ];
    }

    protected function listingSku(ChannelListing $listing): string
    {
        $sku = trim((string) ($listing->channelProduct?->stock_code ?: $listing->listing_id));

        if ($sku === '') {
            throw new \RuntimeException('Magento fiyat/stok gönderimi için SKU bulunamadı. Önce ürün senkronunu çalıştırın.');
        }

        return $sku;
    }

    protected function normalizeOrderStatus(string $status): string
    {
        $status = Str::lower(trim($status));

        return match (true) {
            Str::contains($status, ['delivered']) => 'delivered',
            Str::contains($status, ['complete', 'shipping', 'shipped']) => 'shipped',
            Str::contains($status, ['processing', 'holded', 'fraud']) => 'picking',
            Str::contains($status, ['closed', 'refund', 'return']) => 'returned',
            Str::contains($status, ['cancel']) => 'cancelled',
            Str::contains($status, ['pending_payment', 'pending payment', 'new', 'pending']) => 'created',
            default => $status !== '' ? Str::slug($status, '_') : 'created',
        };
    }

    protected function invoiceStatus(mixed $state): string
    {
        return match ((int) $state) {
            2 => 'paid',
            3 => 'cancelled',
            default => 'open',
        };
    }

    protected function creditMemoStatus(mixed $state): string
    {
        return match ((int) $state) {
            2 => 'completed',
            3 => 'rejected',
            default => 'pending',
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function customAttributes(array $payload): array
    {
        return collect((array) data_get($payload, 'custom_attributes', []))
            ->filter(fn ($attribute) => is_array($attribute))
            ->mapWithKeys(fn (array $attribute) => filled(data_get($attribute, 'attribute_code'))
                ? [(string) data_get($attribute, 'attribute_code') => data_get($attribute, 'value')]
                : [])
            ->all();
    }

    protected function customAttributeValue(array $payload, array $keys): mixed
    {
        return $this->firstFilled($this->customAttributes($payload), $keys);
    }

    protected function firstFilled(array $payload, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = Arr::get($payload, $key);

            if (filled($value)) {
                return $value;
            }
        }

        return null;
    }

    protected function dateTime(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, 'UTC')->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function apiDateTime(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, 'UTC')->utc()->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function money(mixed $value): ?float
    {
        if (is_string($value)) {
            $value = str_replace(',', '.', trim($value));
        }

        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    /**
     * @param  array{items: array<int, array<string, mixed>>, pages_processed: int, total_count: int, more_pages_available: bool, cursor_after: string}  $result
     * @return array<string, mixed>
     */
    protected function syncMeta(array $result): array
    {
        return [
            'items_received' => count($result['items']),
            'total_count' => $result['total_count'],
            'pages_processed' => $result['pages_processed'],
            'more_pages_available' => $result['more_pages_available'],
            'cursor_after' => $result['cursor_after'],
        ];
    }
}
