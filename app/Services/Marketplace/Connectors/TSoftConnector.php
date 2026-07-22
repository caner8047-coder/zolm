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
use App\Services\Marketplace\TSoftRestGateway;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class TSoftConnector extends AbstractMarketplaceConnector implements PullsClaims, PullsFinancials, PullsOrders, PullsProducts, PushesPrice, PushesStock
{
    public function __construct(protected TSoftRestGateway $gateway) {}

    public function providerKey(): string
    {
        return 'tsoft';
    }

    public function displayName(): string
    {
        return 'T-Soft';
    }

    public function defaultApiBaseUrl(): ?string
    {
        return config('marketplace.tsoft.base_url') ?: null;
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
            $payload = $this->gateway->call($store, 'product/get', [
                'start' => 0,
                'limit' => 1,
            ]);

            return [
                'ok' => true,
                'message' => 'T-Soft REST1 token ve ürün okuma yetkisi doğrulandı.',
                'meta' => [
                    'provider' => $this->providerKey(),
                    'sample_count' => count($this->rows($payload)),
                    'store_url' => $this->gateway->baseUrl($store),
                ],
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'message' => 'T-Soft bağlantısı doğrulanamadı: '.$exception->getMessage(),
            ];
        }
    }

    public function pullOrders(MarketplaceStore $store, array $options = []): array
    {
        $result = $this->pullOrderRows($store, $options);

        return [
            'items' => collect($result['items'])
                ->map(fn (array $order) => $this->normalizeOrder($order))
                ->values()
                ->all(),
            'meta' => $this->syncMeta($result),
        ];
    }

    public function pullProducts(MarketplaceStore $store, array $options = []): array
    {
        $products = $this->pullRows($store, 'product/get', $options, 'product_page_size', [
            'orderby' => 'ProductId ASC',
            'FetchImageUrls' => true,
            'FetchDiscountedPriceDetail' => true,
            'FlexiblePrices' => (string) ($options['flexible_prices'] ?? ''),
            'StockFields' => (string) ($options['stock_fields'] ?? ''),
            'ProductId' => $options['product_id'] ?? null,
            'ProductCode' => $options['product_code'] ?? null,
            'Barcode' => $options['barcode'] ?? null,
            'CategoryIds' => $options['category_ids'] ?? null,
            'BrandIds' => $options['brand_ids'] ?? null,
            'f' => $options['filter'] ?? null,
        ]);
        $subProducts = ($options['include_variants'] ?? true)
            ? $this->pullRows($store, 'subProduct/getSubProducts', $options, 'product_page_size', [
                'orderby' => 'SubProductId ASC',
                'MainProductId' => $options['product_id'] ?? null,
                'MainProductCode' => $options['product_code'] ?? null,
                'f' => $options['variant_filter'] ?? null,
            ])
            : ['items' => [], 'pages_processed' => 0, 'more_pages_available' => false, 'cursor_after' => now()->toIso8601String()];
        $variantsByParentId = collect($subProducts['items'])->groupBy(fn (array $variant) => (string) data_get($variant, 'MainProductId'));
        $variantsByParentCode = collect($subProducts['items'])->groupBy(fn (array $variant) => (string) data_get($variant, 'MainProductCode'));
        $seenVariants = [];
        $items = [];

        foreach ($products['items'] as $product) {
            $parentId = (string) data_get($product, 'ProductId');
            $parentCode = (string) data_get($product, 'ProductCode');
            $variants = $variantsByParentId->get($parentId, collect());

            if ($variants->isEmpty()) {
                $variants = $variantsByParentCode->get($parentCode, collect());
            }

            if ($variants->isEmpty()) {
                $items[] = $this->normalizeProduct($product, null, $store);

                continue;
            }

            foreach ($variants as $variant) {
                $variantId = (string) (data_get($variant, 'SubProductId') ?: data_get($variant, 'SubProductCode'));
                $seenVariants[$variantId] = true;
                $items[] = $this->normalizeProduct($product, $variant, $store);
            }
        }

        foreach ($subProducts['items'] as $variant) {
            $variantId = (string) (data_get($variant, 'SubProductId') ?: data_get($variant, 'SubProductCode'));

            if ($variantId === '' || isset($seenVariants[$variantId])) {
                continue;
            }

            $items[] = $this->normalizeProduct([], $variant, $store);
        }

        return [
            'items' => $items,
            'meta' => [
                'items_received' => count($items),
                'product_rows_received' => count($products['items']),
                'variant_rows_received' => count($subProducts['items']),
                'pages_processed' => $products['pages_processed'] + $subProducts['pages_processed'],
                'more_pages_available' => $products['more_pages_available'] || $subProducts['more_pages_available'],
                'cursor_after' => now()->toIso8601String(),
            ],
        ];
    }

    public function pullFinancialEvents(MarketplaceStore $store, array $options = []): array
    {
        $limit = max(1, (int) ($options['order_limit'] ?? config('marketplace.tsoft.finance_order_limit', 500)));
        $orders = array_slice($this->pullOrderRows($store, $options)['items'], 0, $limit);
        $items = collect($orders)
            ->map(fn (array $order) => $this->normalizeFinancialEvent($order))
            ->filter()
            ->values()
            ->all();

        return [
            'items' => $items,
            'meta' => [
                'items_received' => count($items),
                'orders_scanned' => count($orders),
                'order_limit' => $limit,
                'finance_mode' => 'order_payment_summary',
                'cursor_after' => now()->toIso8601String(),
            ],
        ];
    }

    public function pullClaims(MarketplaceStore $store, array $options = []): array
    {
        $result = $this->pullOrderRows($store, array_merge($options, ['include_claim_statuses' => true]));
        $items = collect($result['items'])
            ->filter(fn (array $order) => $this->isClaimOrder($order))
            ->map(fn (array $order) => $this->normalizeClaim($order))
            ->values()
            ->all();

        return [
            'items' => $items,
            'meta' => array_merge($this->syncMeta($result), ['claim_mode' => 'order_status']),
        ];
    }

    public function pushPrice(ChannelListing $listing, float $price, array $context = []): array
    {
        $listing->loadMissing(['store.connection', 'channelProduct']);
        $productCode = $this->resolveWritableProductCode($listing);
        $payload = ['ProductCode' => $productCode, 'SellingPrice' => round($price, 2)];
        $response = $this->gateway->call($listing->store, 'product/updateProducts', [
            'data' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return [
            'status' => 'completed',
            'provider' => $this->providerKey(),
            'listing_id' => $listing->id,
            'product_code' => $productCode,
            'price' => round($price, 2),
            'external_action_id' => $productCode,
            'response' => $response,
        ];
    }

    public function pushStock(ChannelListing $listing, int $quantity, array $context = []): array
    {
        $listing->loadMissing(['store.connection', 'channelProduct']);
        $productCode = $this->resolveWritableProductCode($listing);
        $quantity = max(0, $quantity);
        $payload = ['ProductCode' => $productCode, 'Stock' => $quantity];
        $response = $this->gateway->call($listing->store, 'product/updateProducts', [
            'data' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return [
            'status' => 'completed',
            'provider' => $this->providerKey(),
            'listing_id' => $listing->id,
            'product_code' => $productCode,
            'quantity' => $quantity,
            'external_action_id' => $productCode,
            'response' => $response,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{items: array<int, array<string, mixed>>, pages_processed: int, more_pages_available: bool, cursor_after: string}
     */
    protected function pullOrderRows(MarketplaceStore $store, array $options): array
    {
        $startDate = $this->dateTime($options['start_date'] ?? now()->subDays(7));
        $endDate = $this->dateTime($options['end_date'] ?? now());

        return $this->pullRows($store, 'order/get', $options, 'order_page_size', [
            'orderby' => 'OrderId DESC',
            'OrderDateTimeStart' => $startDate,
            'OrderDateTimeEnd' => $endDate,
            'OrderCode' => $options['order_code'] ?? null,
            'OrderStatusId' => $options['status'] ?? null,
            'IsTransferred' => $options['is_transferred'] ?? null,
            'Archive' => $options['archive'] ?? false,
            'FetchProductData' => true,
            'FetchPackageContent' => true,
            'FetchCustomerData' => true,
            'FetchInvoiceAddress' => true,
            'FetchDeliveryAddress' => true,
            'FetchCampaignData' => true,
            'FetchCargoDetail' => true,
            'FetchCargoService' => true,
            'FetchShipmentDetail' => true,
            'FetchAdditionalProductData' => true,
            'FetchDeleteds' => (bool) ($options['include_deleted'] ?? false),
            'f' => $options['filter'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $parameters
     * @return array{items: array<int, array<string, mixed>>, pages_processed: int, more_pages_available: bool, cursor_after: string}
     */
    protected function pullRows(MarketplaceStore $store, string $path, array $options, string $pageSizeConfig, array $parameters): array
    {
        $pageSize = min(500, max(1, (int) ($options['page_size'] ?? config('marketplace.tsoft.'.$pageSizeConfig, 250))));
        $maxPages = max(1, (int) ($options['max_pages'] ?? config('marketplace.tsoft.max_pages_per_sync', 20)));
        $offset = max(0, (int) ($options['offset'] ?? 0));
        $items = [];
        $pagesProcessed = 0;
        $more = false;

        do {
            $payload = $this->gateway->call($store, $path, array_merge($parameters, [
                'start' => $offset,
                'limit' => $pageSize,
            ]));
            $rows = $this->rows($payload);

            foreach ($rows as $row) {
                $items[] = $row;
            }

            $pagesProcessed++;
            $more = count($rows) >= $pageSize;
            $offset += $pageSize;
        } while ($more && $pagesProcessed < $maxPages);

        return [
            'items' => $items,
            'pages_processed' => $pagesProcessed,
            'more_pages_available' => $more,
            'cursor_after' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    protected function rows(array $payload): array
    {
        $rows = data_get($payload, 'data', []);

        if (! is_array($rows) || $rows === []) {
            return [];
        }

        return array_is_list($rows) ? array_values(array_filter($rows, 'is_array')) : [$rows];
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeOrder(array $payload): array
    {
        $orderId = (string) (data_get($payload, 'OrderId') ?: data_get($payload, 'OrderCode'));
        $orderNumber = (string) (data_get($payload, 'OrderCode') ?: $orderId);
        $status = $this->normalizeOrderStatus($payload);
        $orderedAt = $this->dateTime(data_get($payload, 'OrderDateTimeStamp') ?: data_get($payload, 'OrderDate'));
        $updatedAt = $this->dateTime(data_get($payload, 'UpdateDateTimeStamp') ?: data_get($payload, 'UpdateDate')) ?: $orderedAt;
        $items = $this->orderDetails($payload);
        $commercial = Str::contains(Str::lower((string) data_get($payload, 'InvoiceType')), ['company', 'kurum', 'firma'])
            || filled(data_get($payload, 'InvoiceTaxNumber'))
            || filled(data_get($payload, 'InvoiceTaxno'));

        return [
            'order' => [
                'external_order_id' => $orderId,
                'order_number' => $orderNumber,
                'order_status' => $status,
                'commercial_type' => $commercial ? 'commercial' : 'individual',
                'currency' => Str::upper((string) (data_get($payload, 'Currency') ?: 'TRY')),
                'exchange_rate' => data_get($payload, 'ExchangeRate') ?: 1,
                'customer_name' => data_get($payload, 'CustomerName'),
                'customer_email' => data_get($payload, 'CustomerUsername') ?: data_get($payload, 'Email'),
                'customer_phone' => data_get($payload, 'CustomerPhone') ?: data_get($payload, 'DeliveryMobile'),
                'billing_name' => data_get($payload, 'InvoiceCompanyName') ?: data_get($payload, 'InvoiceName') ?: data_get($payload, 'InvoicePersonName'),
                'billing_tax_number' => data_get($payload, 'InvoiceTaxNumber') ?: data_get($payload, 'InvoiceTaxno') ?: data_get($payload, 'InvoicePersonIdentityNumber'),
                'shipment_country' => data_get($payload, 'DeliveryCountry'),
                'shipment_city' => data_get($payload, 'DeliveryCity'),
                'shipment_district' => data_get($payload, 'DeliveryTown') ?: data_get($payload, 'DeliveryDistrict'),
                'ordered_at' => $orderedAt,
                'approved_at' => in_array($status, ['approved', 'picking', 'shipped', 'delivered'], true) ? $updatedAt : null,
                'delivered_at' => $status === 'delivered' ? $updatedAt : null,
                'cancelled_at' => $status === 'cancelled' ? $updatedAt : null,
                'returned_at' => $status === 'returned' ? $updatedAt : null,
                'raw_payload' => $payload,
            ],
            'package' => [
                'external_package_id' => $orderId,
                'package_number' => $orderNumber,
                'package_status' => $status,
                'cargo_company' => data_get($payload, 'Cargo'),
                'cargo_tracking_number' => data_get($payload, 'CargoTrackingCode'),
                'cargo_barcode' => data_get($payload, 'Shipment.CargoKey') ?: data_get($payload, 'CargoKey'),
                'shipment_provider' => data_get($payload, 'CargoCode') ?: data_get($payload, 'Cargo'),
                'shipped_at' => in_array($status, ['shipped', 'delivered'], true) ? $updatedAt : null,
                'delivered_at' => $status === 'delivered' ? $updatedAt : null,
                'raw_payload' => [
                    'order_id' => $orderId,
                    'cargo' => data_get($payload, 'Cargo'),
                    'cargo_code' => data_get($payload, 'CargoCode'),
                    'cargo_tracking_code' => data_get($payload, 'CargoTrackingCode'),
                    'shipment' => data_get($payload, 'Shipment'),
                ],
            ],
            'items' => collect($items)
                ->map(fn (array $line, int $index) => $this->normalizeOrderLine($line, $orderId, $status, $index))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeProduct(array $product, ?array $variant, MarketplaceStore $store): array
    {
        $isVariant = $variant !== null;
        $source = $variant ?? $product;
        $parentId = (string) (data_get($product, 'ProductId') ?: data_get($source, 'MainProductId'));
        $externalId = (string) ($isVariant
            ? (data_get($source, 'SubProductId') ?: data_get($source, 'SubProductCode'))
            : (data_get($source, 'ProductId') ?: data_get($source, 'ProductCode')));
        $stockCode = (string) ($isVariant
            ? (data_get($source, 'SubProductCode') ?: data_get($source, 'SupplierSubProductCode'))
            : data_get($source, 'ProductCode'));
        $active = $this->boolean(data_get($source, 'IsActive', data_get($product, 'IsActive', true)));
        $currency = Str::upper((string) (data_get($source, 'Currency') ?: data_get($product, 'Currency') ?: $store->currency ?: 'TRY'));
        $price = $this->money(data_get($source, 'DiscountedSellingPrice') ?: data_get($source, 'SellingPrice') ?: data_get($product, 'DiscountedPrice') ?: data_get($product, 'SellingPrice'));
        $listPrice = $this->money(data_get($source, 'SellingPrice') ?: data_get($product, 'SellingPrice'));
        $images = collect([
            data_get($source, 'MainProductImageUrl'),
            data_get($product, 'ImageUrlCdn'),
            data_get($product, 'ImageUrl'),
        ])->filter(fn ($image) => is_string($image) && trim($image) !== '')->unique()->values()->all();

        return [
            'product' => [
                'external_product_id' => $externalId,
                'external_parent_id' => $isVariant && $parentId !== '' ? $parentId : null,
                'stock_code' => $stockCode ?: $externalId,
                'barcode' => data_get($source, 'Barcode') ?: data_get($product, 'Barcode'),
                'title' => data_get($source, 'ProductName') ?: data_get($product, 'ProductName'),
                'brand' => data_get($source, 'Brand') ?: data_get($product, 'Brand'),
                'category_name' => data_get($product, 'DefaultCategoryPath') ?: data_get($product, 'DefaultCategoryName'),
                'vat_rate' => data_get($source, 'Vat') ?? data_get($product, 'Vat'),
                'description' => data_get($product, 'Details') ?: data_get($product, 'ShortDescription'),
                'images' => $images,
                'attributes' => array_filter([
                    'property_1' => data_get($source, 'Property1'),
                    'property_2' => data_get($source, 'Property2'),
                    'model' => data_get($product, 'Model'),
                    'supplier' => data_get($product, 'Supplier'),
                    'additional_1' => data_get($product, 'Additional1'),
                    'additional_2' => data_get($product, 'Additional2'),
                    'additional_3' => data_get($product, 'Additional3'),
                    'weight' => data_get($source, 'Weight') ?: data_get($product, 'Weight'),
                    'desi' => data_get($source, 'CBM') ?: data_get($product, 'CBM'),
                ], fn ($value) => $value !== null && $value !== ''),
                'approval_status' => $active ? 'approved' : 'passive',
                'is_catalog_product' => true,
                'raw_payload' => array_merge($product, ['variant' => $variant]),
            ],
            'listing' => array_merge([
                'listing_id' => $externalId,
                'listing_status' => $active ? 'active' : 'passive',
                'sale_price' => $price,
                'list_price' => $listPrice,
                'currency' => $currency,
                'stock_quantity' => (int) round((float) (data_get($source, 'Stock') ?? data_get($product, 'Stock', 0))),
                'published_at' => $this->dateTime(data_get($source, 'CreateDateTimeStamp') ?: data_get($source, 'CreateDate') ?: data_get($product, 'CreateDateTimeStamp') ?: data_get($product, 'CreateDate')),
            ], $this->catalogDeliveryTermData($source, $product)),
        ];
    }

    protected function normalizeOrderLine(array $line, string $orderId, string $status, int $index): array
    {
        $quantity = max(1, (int) round((float) data_get($line, 'Quantity', 1)));
        $unitPrice = $this->money(data_get($line, 'SellingPrice'));
        $gross = $unitPrice !== null ? round($unitPrice * $quantity, 2) : null;
        $discount = $this->money(data_get($line, 'Discount') ?: data_get($line, 'DiscountTotal'));

        return [
            'external_line_id' => (string) (data_get($line, 'OrderProductId') ?: sha1($orderId.'|'.data_get($line, 'SubProductId', data_get($line, 'ProductId')).'|'.$index)),
            'stock_code' => (string) (data_get($line, 'SubProductCode') ?: data_get($line, 'ProductCode')),
            'barcode' => data_get($line, 'Barcode'),
            'product_name' => data_get($line, 'ProductName'),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'gross_amount' => $gross,
            'discount_amount' => $discount,
            'marketplace_discount_amount' => null,
            'billable_amount' => $gross !== null ? max(0, round($gross - ($discount ?? 0), 2)) : null,
            'commission_rate' => null,
            'vat_rate' => data_get($line, 'Vat'),
            'line_status' => $status,
            'raw_payload' => $line,
        ];
    }

    protected function normalizeFinancialEvent(array $order): ?array
    {
        $amount = $this->money(data_get($order, 'OrderTotalPrice'));

        if ($amount === null) {
            return null;
        }

        $status = $this->normalizeOrderStatus($order);
        $isRefund = $status === 'returned';
        $orderNumber = (string) (data_get($order, 'OrderCode') ?: data_get($order, 'OrderId'));
        $transactionId = (string) (data_get($order, 'TransactionId') ?: $orderNumber);
        $eventDate = $this->dateTime(data_get($order, 'UpdateDateTimeStamp') ?: data_get($order, 'UpdateDate') ?: data_get($order, 'OrderDateTimeStamp') ?: data_get($order, 'OrderDate'));

        return [
            'event_source' => 'tsoft_order_payment_summary',
            'external_event_id' => $transactionId.'-'.($isRefund ? 'refund' : 'payment'),
            'order_number' => $orderNumber,
            'external_package_id' => (string) data_get($order, 'OrderId'),
            'external_line_id' => null,
            'stock_code' => null,
            'barcode' => null,
            'event_type' => $isRefund ? 'refund' : 'payment',
            'reference_number' => $transactionId,
            'event_date' => $eventDate,
            'due_date' => null,
            'settlement_date' => $eventDate,
            'amount' => abs($amount),
            'currency' => Str::upper((string) (data_get($order, 'Currency') ?: 'TRY')),
            'direction' => $isRefund ? 'debit' : 'credit',
            'status' => $status,
            'notes' => collect([
                data_get($order, 'PaymentType'),
                data_get($order, 'Bank'),
                filled(data_get($order, 'Installment')) ? data_get($order, 'Installment').' taksit' : null,
                data_get($order, 'PaymentInfo'),
            ])->filter()->unique()->implode(' | ') ?: null,
            'raw_payload' => $order,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeClaim(array $order): array
    {
        $orderNumber = (string) (data_get($order, 'OrderCode') ?: data_get($order, 'OrderId'));
        $statusId = (int) data_get($order, 'OrderStatusId');
        $isReturn = $statusId === 10 || Str::contains(Str::lower((string) data_get($order, 'OrderStatus')), ['iade', 'return']);

        return [
            'external_claim_id' => 'tsoft-'.$orderNumber.'-'.$statusId,
            'order_number' => $orderNumber,
            'cargo_tracking_number' => data_get($order, 'CargoTrackingCode'),
            'cargo_provider' => data_get($order, 'Cargo'),
            'status' => 'completed',
            'type' => $isReturn ? 'return' : 'cancellation',
            'reason' => data_get($order, 'GeneralOrderNote') ?: data_get($order, 'OrderStatus'),
            'reason_detail' => data_get($order, 'OrderStatus'),
            'customer_note' => data_get($order, 'GeneralOrderNote'),
            'customer_name' => data_get($order, 'CustomerName'),
            'created_date' => $this->dateTime(data_get($order, 'UpdateDateTimeStamp') ?: data_get($order, 'UpdateDate') ?: data_get($order, 'OrderDateTimeStamp') ?: data_get($order, 'OrderDate')),
            'items' => collect($this->orderDetails($order))->map(fn (array $line, int $index) => [
                'external_item_id' => (string) (data_get($line, 'OrderProductId') ?: sha1($orderNumber.'|'.data_get($line, 'SubProductId', data_get($line, 'ProductId')).'|'.$index)),
                'external_order_line_id' => (string) (data_get($line, 'OrderProductId') ?: data_get($line, 'SubProductId') ?: data_get($line, 'ProductId')),
                'product_name' => data_get($line, 'ProductName'),
                'stock_code' => data_get($line, 'SubProductCode') ?: data_get($line, 'ProductCode'),
                'barcode' => data_get($line, 'Barcode'),
                'quantity' => max(1, (int) round((float) data_get($line, 'Quantity', 1))),
                'reason' => data_get($order, 'OrderStatus'),
                'customer_note' => data_get($line, 'OrderNote') ?: data_get($order, 'GeneralOrderNote'),
                'raw_payload' => $line,
            ])->values()->all(),
            'raw_payload' => $order,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function orderDetails(array $order): array
    {
        $details = data_get($order, 'OrderDetails', []);

        if (is_string($details) && trim($details) !== '') {
            try {
                $details = json_decode($details, true, flags: JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                return [];
            }
        }

        if (is_array($details) && isset($details['OrderDetail'])) {
            $details = $details['OrderDetail'];
        }

        if (! is_array($details) || $details === []) {
            return [];
        }

        return array_is_list($details) ? array_values(array_filter($details, 'is_array')) : [$details];
    }

    protected function normalizeOrderStatus(array $order): string
    {
        $statusId = (int) data_get($order, 'OrderStatusId', -1);
        $status = Str::lower(trim((string) data_get($order, 'OrderStatus')));

        if ($statusId === 9 || Str::contains($status, ['iptal', 'cancel'])) {
            return 'cancelled';
        }

        if ($statusId === 10 || Str::contains($status, ['iade', 'return', 'refund'])) {
            return 'returned';
        }

        return match (true) {
            Str::contains($status, ['teslim', 'delivered']) => 'delivered',
            Str::contains($status, ['kargoya', 'gönderildi', 'gonderildi', 'shipped', 'sent to cargo']) => 'shipped',
            Str::contains($status, ['hazır', 'hazir', 'paket', 'tedarik', 'prepar']) => 'picking',
            Str::contains($status, ['onaylandı', 'onaylandi', 'approved']) => 'approved',
            Str::contains($status, ['ödeme', 'odeme', 'payment', 'bekliyor', 'pending']) => 'created',
            default => $status !== '' ? Str::slug($status, '_') : 'created',
        };
    }

    protected function isClaimOrder(array $order): bool
    {
        $statusId = (int) data_get($order, 'OrderStatusId', -1);
        $status = Str::lower((string) data_get($order, 'OrderStatus'));

        return in_array($statusId, [9, 10], true) || Str::contains($status, ['iptal', 'iade', 'cancel', 'return', 'refund']);
    }

    protected function resolveWritableProductCode(ChannelListing $listing): string
    {
        $variant = data_get($listing->channelProduct?->raw_payload, 'variant');

        if (is_array($variant) && filled(data_get($variant, 'SubProductCode'))) {
            throw new \RuntimeException('T-Soft alt ürün fiyat/stok yazma sözleşmesi doğrulanmadı. Yalnız ana ürünler için canary yazma yapılabilir.');
        }

        $code = trim((string) (
            data_get($listing->channelProduct?->raw_payload, 'ProductCode')
            ?: $listing->channelProduct?->stock_code
            ?: $listing->listing_id
        ));

        if ($code === '') {
            throw new \RuntimeException('T-Soft fiyat/stok gönderimi için ana ürün Web Servis Kodu bulunamadı. Önce ürün senkronunu çalıştırın.');
        }

        return $code;
    }

    protected function dateTime(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return CarbonImmutable::createFromTimestamp((int) $value, 'Europe/Istanbul')->toIso8601String();
            }

            return CarbonImmutable::parse($value, 'Europe/Istanbul')->toIso8601String();
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

    protected function boolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(Str::lower(trim((string) $value)), ['1', 'true', 'on', 'yes', 'aktif'], true);
    }

    /**
     * @param  array{items: array<int, array<string, mixed>>, pages_processed: int, more_pages_available: bool, cursor_after: string}  $result
     * @return array<string, mixed>
     */
    protected function syncMeta(array $result): array
    {
        return [
            'items_received' => count($result['items']),
            'pages_processed' => $result['pages_processed'],
            'more_pages_available' => $result['more_pages_available'],
            'cursor_after' => $result['cursor_after'],
        ];
    }
}
