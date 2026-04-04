<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class MarketplacePayloadDiagnosticsService
{
    /**
     * @param  array<int, array<string, mixed>>  $packages
     * @return array<string, mixed>
     */
    public function analyzeOrders(array $packages): array
    {
        $packageCollection = collect($packages);
        $items = $packageCollection->flatMap(fn (array $row) => Arr::wrap($row['items'] ?? []))->values();

        return [
            'package_count' => $packageCollection->count(),
            'order_count' => $packageCollection
                ->pluck('order.order_number')
                ->filter(fn ($value) => filled($value))
                ->unique()
                ->count(),
            'item_count' => $items->count(),
            'missing_order_number_count' => $packageCollection->filter(fn (array $row) => blank(data_get($row, 'order.order_number')))->count(),
            'missing_package_id_count' => $packageCollection->filter(fn (array $row) => blank(data_get($row, 'package.external_package_id')))->count(),
            'missing_tracking_number_count' => $packageCollection->filter(fn (array $row) => blank(data_get($row, 'package.cargo_tracking_number')))->count(),
            'missing_item_line_id_count' => $items->filter(fn (array $row) => blank(data_get($row, 'external_line_id')))->count(),
            'missing_stock_code_count' => $items->filter(fn (array $row) => blank(data_get($row, 'stock_code')))->count(),
            'missing_barcode_count' => $items->filter(fn (array $row) => blank(data_get($row, 'barcode')))->count(),
            'missing_commission_rate_count' => $items->filter(fn (array $row) => data_get($row, 'commission_rate') === null)->count(),
            'missing_vat_rate_count' => $items->filter(fn (array $row) => data_get($row, 'vat_rate') === null)->count(),
            'status_breakdown' => $this->topCounts($packageCollection->pluck('package.package_status')),
            'examples' => [
                'missing_order_number' => $this->packageExamples($packageCollection, fn (array $row) => blank(data_get($row, 'order.order_number'))),
                'missing_package_id' => $this->packageExamples($packageCollection, fn (array $row) => blank(data_get($row, 'package.external_package_id'))),
                'missing_stock_code' => $this->itemExamples($packageCollection, fn (array $row) => blank(data_get($row, 'stock_code'))),
                'missing_barcode' => $this->itemExamples($packageCollection, fn (array $row) => blank(data_get($row, 'barcode'))),
            ],
            'warnings' => array_values(array_filter([
                $packageCollection->isEmpty() ? 'Sipariş cevabı boş geldi.' : null,
                $items->count() === 0 && $packageCollection->isNotEmpty() ? 'Sipariş paketleri geldi fakat satır verisi bulunamadı.' : null,
                $items->count() > 0 && $items->filter(fn (array $row) => blank(data_get($row, 'stock_code')) && blank(data_get($row, 'barcode')))->isNotEmpty()
                    ? 'Bazı sipariş satırlarında hem stok kodu hem barkod eksik.' : null,
            ])),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    public function analyzeProducts(array $items): array
    {
        $collection = collect($items);

        return [
            'product_count' => $collection->count(),
            'missing_external_product_id_count' => $collection->filter(fn (array $row) => blank(data_get($row, 'product.external_product_id')))->count(),
            'missing_listing_id_count' => $collection->filter(fn (array $row) => blank(data_get($row, 'listing.listing_id')))->count(),
            'missing_stock_code_count' => $collection->filter(fn (array $row) => blank(data_get($row, 'product.stock_code')))->count(),
            'missing_barcode_count' => $collection->filter(fn (array $row) => blank(data_get($row, 'product.barcode')))->count(),
            'missing_title_count' => $collection->filter(fn (array $row) => blank(data_get($row, 'product.title')))->count(),
            'missing_sale_price_count' => $collection->filter(fn (array $row) => data_get($row, 'listing.sale_price') === null)->count(),
            'missing_stock_quantity_count' => $collection->filter(fn (array $row) => data_get($row, 'listing.stock_quantity') === null)->count(),
            'status_breakdown' => $this->topCounts($collection->pluck('listing.listing_status')),
            'examples' => [
                'missing_stock_code' => $this->productExamples($collection, fn (array $row) => blank(data_get($row, 'product.stock_code'))),
                'missing_barcode' => $this->productExamples($collection, fn (array $row) => blank(data_get($row, 'product.barcode'))),
            ],
            'warnings' => array_values(array_filter([
                $collection->isEmpty() ? 'Ürün cevabı boş geldi.' : null,
                $collection->isNotEmpty() && $collection->filter(fn (array $row) => blank(data_get($row, 'product.stock_code')) && blank(data_get($row, 'product.barcode')))->isNotEmpty()
                    ? 'Bazı listing kayıtlarında hem stok kodu hem barkod eksik.' : null,
            ])),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return array<string, mixed>
     */
    public function analyzeFinancialEvents(array $events): array
    {
        $collection = collect($events);

        return [
            'event_count' => $collection->count(),
            'missing_event_id_count' => $collection->filter(fn (array $row) => blank(data_get($row, 'external_event_id')))->count(),
            'missing_order_number_count' => $collection->filter(fn (array $row) => blank(data_get($row, 'order_number')))->count(),
            'missing_package_id_count' => $collection->filter(fn (array $row) => blank(data_get($row, 'external_package_id')))->count(),
            'missing_line_id_count' => $collection->filter(fn (array $row) => blank(data_get($row, 'external_line_id')))->count(),
            'missing_amount_count' => $collection->filter(fn (array $row) => data_get($row, 'amount') === null)->count(),
            'missing_settlement_date_count' => $collection->filter(fn (array $row) => blank(data_get($row, 'settlement_date')))->count(),
            'event_type_breakdown' => $this->topCounts($collection->pluck('event_type')),
            'direction_breakdown' => $this->topCounts($collection->pluck('direction')),
            'examples' => [
                'missing_order_number' => $this->financialExamples($collection, fn (array $row) => blank(data_get($row, 'order_number'))),
                'missing_amount' => $this->financialExamples($collection, fn (array $row) => data_get($row, 'amount') === null),
            ],
            'warnings' => array_values(array_filter([
                $collection->isEmpty() ? 'Finans cevabı boş geldi.' : null,
                $collection->isNotEmpty() && $collection->filter(fn (array $row) => blank(data_get($row, 'order_number')))->isNotEmpty()
                    ? 'Bazı finans kayıtlarında order number eksik.' : null,
            ])),
        ];
    }

    /**
     * @param  Collection<int, mixed>  $values
     * @return array<string, int>
     */
    protected function topCounts(Collection $values, int $limit = 8): array
    {
        return $values
            ->map(fn ($value) => filled($value) ? (string) $value : '(bos)')
            ->countBy()
            ->sortDesc()
            ->take($limit)
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $packages
     * @return array<int, array<string, string|null>>
     */
    protected function packageExamples(Collection $packages, callable $predicate, int $limit = 3): array
    {
        return $packages
            ->filter($predicate)
            ->take($limit)
            ->map(fn (array $row) => [
                'order_number' => data_get($row, 'order.order_number'),
                'package_id' => data_get($row, 'package.external_package_id'),
                'tracking_number' => data_get($row, 'package.cargo_tracking_number'),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $packages
     * @return array<int, array<string, string|null>>
     */
    protected function itemExamples(Collection $packages, callable $predicate, int $limit = 3): array
    {
        $examples = [];

        foreach ($packages as $packageRow) {
            foreach (Arr::wrap($packageRow['items'] ?? []) as $itemRow) {
                if (!is_array($itemRow) || !$predicate($itemRow)) {
                    continue;
                }

                $examples[] = [
                    'order_number' => data_get($packageRow, 'order.order_number'),
                    'package_id' => data_get($packageRow, 'package.external_package_id'),
                    'line_id' => data_get($itemRow, 'external_line_id'),
                    'stock_code' => data_get($itemRow, 'stock_code'),
                    'barcode' => data_get($itemRow, 'barcode'),
                ];

                if (count($examples) >= $limit) {
                    return $examples;
                }
            }
        }

        return $examples;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $products
     * @return array<int, array<string, string|null>>
     */
    protected function productExamples(Collection $products, callable $predicate, int $limit = 3): array
    {
        return $products
            ->filter($predicate)
            ->take($limit)
            ->map(fn (array $row) => [
                'external_product_id' => data_get($row, 'product.external_product_id'),
                'listing_id' => data_get($row, 'listing.listing_id'),
                'stock_code' => data_get($row, 'product.stock_code'),
                'barcode' => data_get($row, 'product.barcode'),
                'title' => data_get($row, 'product.title'),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $events
     * @return array<int, array<string, string|null|float>>
     */
    protected function financialExamples(Collection $events, callable $predicate, int $limit = 3): array
    {
        return $events
            ->filter($predicate)
            ->take($limit)
            ->map(fn (array $row) => [
                'event_id' => data_get($row, 'external_event_id'),
                'order_number' => data_get($row, 'order_number'),
                'package_id' => data_get($row, 'external_package_id'),
                'event_type' => data_get($row, 'event_type'),
                'amount' => data_get($row, 'amount'),
            ])
            ->values()
            ->all();
    }
}
