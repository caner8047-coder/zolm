<?php

namespace App\Services;

use App\Models\ChannelListing;
use App\Models\MpProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MpProductChangeLogger
{
    private const PRODUCT_FIELDS = [
        'sale_price' => ['label' => 'Satış fiyatı', 'type' => 'money'],
        'market_price' => ['label' => 'Piyasa fiyatı', 'type' => 'money'],
        'buybox_price' => ['label' => 'Buybox fiyatı', 'type' => 'money'],
        'cogs' => ['label' => 'Ürün maliyeti', 'type' => 'money'],
        'packaging_cost' => ['label' => 'Ambalaj maliyeti', 'type' => 'money'],
        'cargo_cost' => ['label' => 'Kargo maliyeti', 'type' => 'money'],
        'extra_cost_fixed' => ['label' => 'Sabit ek gider', 'type' => 'money'],
        'extra_cost_percentage' => ['label' => 'Yüzdesel ek gider', 'type' => 'percent'],
        'commission_rate' => ['label' => 'Komisyon oranı', 'type' => 'percent'],
        'vat_rate' => ['label' => 'KDV oranı', 'type' => 'percent'],
        'cost_vat_rate' => ['label' => 'Maliyet KDV oranı', 'type' => 'percent'],
        'stock_quantity' => ['label' => 'Stok', 'type' => 'integer'],
        'critical_stock_threshold' => ['label' => 'Kritik stok eşiği', 'type' => 'integer'],
        'desi' => ['label' => 'Desi', 'type' => 'decimal'],
        'pieces' => ['label' => 'Parça', 'type' => 'integer'],
        'return_rate' => ['label' => 'İade oranı', 'type' => 'percent'],
        'fast_delivery_type' => ['label' => 'Teslimat tipi', 'type' => 'string'],
        'shipping_days' => ['label' => 'Termin günü', 'type' => 'integer'],
        'shipping_type' => ['label' => 'Sevkiyat tipi', 'type' => 'string'],
        'status' => ['label' => 'Ürün durumu', 'type' => 'string'],
        'profit_commission_override_enabled' => ['label' => 'Manuel komisyon kâr hesabı', 'type' => 'boolean'],
    ];

    private const LISTING_FIELDS = [
        'sale_price' => ['label' => 'Kanal satış fiyatı', 'type' => 'money'],
        'list_price' => ['label' => 'Kanal liste fiyatı', 'type' => 'money'],
        'commission_rate' => ['label' => 'Kanal komisyon oranı', 'type' => 'percent'],
        'commission_source' => ['label' => 'Komisyon kaynağı', 'type' => 'string'],
        'stock_quantity' => ['label' => 'Kanal stoku', 'type' => 'integer'],
        'shipping_days' => ['label' => 'Kanal termin günü', 'type' => 'integer'],
        'shipping_type' => ['label' => 'Kanal sevkiyat tipi', 'type' => 'string'],
        'fast_delivery_type' => ['label' => 'Kanal teslimat tipi', 'type' => 'string'],
        'listing_status' => ['label' => 'Kanal durumu', 'type' => 'string'],
        'mp_product_id' => ['label' => 'ZOLM ürün eşleşmesi', 'type' => 'integer'],
    ];

    private const SOURCE_LABELS = [
        'manual_create' => 'Manuel ürün ekleme',
        'manual_form' => 'Manuel form',
        'inline_edit' => 'Satır içi düzenleme',
        'manual_match' => 'Manuel eşleştirme',
        'bulk_status_update' => 'Toplu durum güncelleme',
        'bulk_commission_override' => 'Toplu komisyon ayarı',
        'bulk_stock_update' => 'Toplu stok güncelleme',
        'bulk_price_update' => 'Toplu fiyat güncelleme',
        'bulk_profit_target' => 'Hedef kârlılık',
        'bulk_packaging_update' => 'Toplu ambalaj güncelleme',
        'bulk_logistics_update' => 'Toplu lojistik güncelleme',
        'bulk_stock_threshold' => 'Toplu kritik stok güncelleme',
        'return_rate_refresh' => 'İade oranı hesaplama',
        'cost_excel_update' => 'Maliyet Excel güncelleme',
        'manual_excel_import' => 'Manuel Excel import',
        'trendyol_import' => 'Trendyol Excel import',
        'catalog_sync' => 'Pazaryeri ürün senkronu',
        'recipe_cost_sync' => 'Reçete maliyet senkronu',
        'set_composition_sync' => 'Set/takım toplam senkronu',
        'price_push' => 'Pazaryerine fiyat gönderimi',
        'stock_push' => 'Pazaryerine stok gönderimi',
    ];

    public function isAvailable(): bool
    {
        return Schema::hasTable('mp_product_change_logs');
    }

    public function batchId(string $source): string
    {
        return Str::limit($source, 34, '') . '-' . now()->format('YmdHis') . '-' . Str::lower(Str::random(8));
    }

    public function productSnapshot(MpProduct $product): array
    {
        return $this->snapshot($product, self::PRODUCT_FIELDS) + [
            'id' => $product->id,
            'user_id' => $product->user_id,
            'barcode' => $product->barcode,
            'stock_code' => $product->stock_code,
            'product_name' => $product->product_name,
        ];
    }

    public function listingSnapshot(ChannelListing $listing): array
    {
        return $this->snapshot($listing, self::LISTING_FIELDS) + [
            'id' => $listing->id,
            'user_id' => $listing->relationLoaded('store') ? $listing->store?->user_id : null,
            'store_id' => $listing->store_id,
            'mp_product_id' => $listing->mp_product_id,
            'listing_id' => $listing->listing_id,
        ];
    }

    /**
     * @param  array<int, int|string>  $productIds
     * @return array<int, array<string, mixed>>
     */
    public function productSnapshotsForIds(int $userId, array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        return MpProduct::query()
            ->where('user_id', $userId)
            ->whereIn('id', $productIds)
            ->get()
            ->mapWithKeys(fn (MpProduct $product) => [(int) $product->id => $this->productSnapshot($product)])
            ->all();
    }

    /**
     * @param  array<int, int|string>  $listingIds
     * @return array<int, array<string, mixed>>
     */
    public function listingSnapshotsForIds(array $listingIds, ?int $userId = null): array
    {
        if ($listingIds === []) {
            return [];
        }

        return ChannelListing::query()
            ->with('store:id,user_id,marketplace,store_name')
            ->when($userId, fn ($query) => $query->whereHas('store', fn ($storeQuery) => $storeQuery->where('user_id', $userId)))
            ->whereIn('id', $listingIds)
            ->get()
            ->mapWithKeys(fn (ChannelListing $listing) => [(int) $listing->id => $this->listingSnapshot($listing)])
            ->all();
    }

    public function logProductCreated(MpProduct $product, string $source, ?int $changedBy = null, ?string $note = null, ?string $batchId = null, array $metadata = []): int
    {
        return $this->logProductSnapshotChanges($product, [], $source, $changedBy, $note, $batchId, $metadata);
    }

    public function logProductSnapshotChanges(MpProduct $product, array $beforeSnapshot, string $source, ?int $changedBy = null, ?string $note = null, ?string $batchId = null, array $metadata = []): int
    {
        if (!$this->isAvailable()) {
            return 0;
        }

        return $this->insertChanges(
            scope: 'product',
            trackedFields: self::PRODUCT_FIELDS,
            beforeSnapshot: $beforeSnapshot,
            afterSnapshot: $this->productSnapshot($product),
            source: $source,
            changedBy: $changedBy,
            note: $note,
            batchId: $batchId,
            metadata: $metadata,
        );
    }

    public function logListingSnapshotChanges(ChannelListing $listing, array $beforeSnapshot, string $source, ?int $changedBy = null, ?string $note = null, ?string $batchId = null, array $metadata = []): int
    {
        if (!$this->isAvailable()) {
            return 0;
        }

        return $this->insertChanges(
            scope: 'listing',
            trackedFields: self::LISTING_FIELDS,
            beforeSnapshot: $beforeSnapshot,
            afterSnapshot: $this->listingSnapshot($listing),
            source: $source,
            changedBy: $changedBy,
            note: $note,
            batchId: $batchId,
            metadata: $metadata,
        );
    }

    /**
     * @param  array<int, int|string>  $productIds
     * @param  array<int, array<string, mixed>>  $beforeSnapshots
     */
    public function logProductChangesForIds(array $productIds, array $beforeSnapshots, string $source, ?int $changedBy = null, ?string $note = null, ?string $batchId = null, array $metadata = []): int
    {
        if (!$this->isAvailable() || $productIds === []) {
            return 0;
        }

        $count = 0;
        MpProduct::query()
            ->whereIn('id', $productIds)
            ->chunkById(200, function (Collection $products) use ($beforeSnapshots, $source, $changedBy, $note, $batchId, $metadata, &$count) {
                foreach ($products as $product) {
                    $count += $this->logProductSnapshotChanges(
                        $product,
                        $beforeSnapshots[(int) $product->id] ?? [],
                        $source,
                        $changedBy,
                        $note,
                        $batchId,
                        $metadata,
                    );
                }
            });

        return $count;
    }

    /**
     * @param  array<int, int|string>  $listingIds
     * @param  array<int, array<string, mixed>>  $beforeSnapshots
     */
    public function logListingChangesForIds(array $listingIds, array $beforeSnapshots, string $source, ?int $changedBy = null, ?string $note = null, ?string $batchId = null, array $metadata = []): int
    {
        if (!$this->isAvailable() || $listingIds === []) {
            return 0;
        }

        $count = 0;
        ChannelListing::query()
            ->whereIn('id', $listingIds)
            ->chunkById(200, function (Collection $listings) use ($beforeSnapshots, $source, $changedBy, $note, $batchId, $metadata, &$count) {
                foreach ($listings as $listing) {
                    $count += $this->logListingSnapshotChanges(
                        $listing,
                        $beforeSnapshots[(int) $listing->id] ?? [],
                        $source,
                        $changedBy,
                        $note,
                        $batchId,
                        $metadata,
                    );
                }
            });

        return $count;
    }

    public function sourceLabel(string $source): string
    {
        return self::SOURCE_LABELS[$source] ?? Str::headline(str_replace('_', ' ', $source));
    }

    /**
     * @param  array<string, array{label: string, type: string}>  $trackedFields
     */
    private function insertChanges(string $scope, array $trackedFields, array $beforeSnapshot, array $afterSnapshot, string $source, ?int $changedBy, ?string $note, ?string $batchId, array $metadata): int
    {
        $rows = [];
        $now = now();
        $changedBy ??= Auth::id();

        foreach ($trackedFields as $field => $definition) {
            $oldValue = $beforeSnapshot[$field] ?? null;
            $newValue = $afterSnapshot[$field] ?? null;

            if (!$this->valuesDiffer($oldValue, $newValue, $definition['type'])) {
                continue;
            }

            [$oldNumber, $newNumber, $deltaNumber, $deltaPercent] = $this->numericMetrics($oldValue, $newValue, $definition['type']);

            $rows[] = [
                'user_id' => $afterSnapshot['user_id'] ?? $beforeSnapshot['user_id'] ?? null,
                'mp_product_id' => $scope === 'product'
                    ? ($afterSnapshot['id'] ?? $beforeSnapshot['id'] ?? null)
                    : ($afterSnapshot['mp_product_id'] ?? $beforeSnapshot['mp_product_id'] ?? null),
                'channel_listing_id' => $scope === 'listing' ? ($afterSnapshot['id'] ?? $beforeSnapshot['id'] ?? null) : null,
                'store_id' => $afterSnapshot['store_id'] ?? $beforeSnapshot['store_id'] ?? null,
                'batch_id' => $batchId,
                'change_scope' => $scope,
                'field_key' => $field,
                'field_label' => $definition['label'],
                'value_type' => $definition['type'],
                'old_value' => $this->stringValue($oldValue),
                'new_value' => $this->stringValue($newValue),
                'old_value_number' => $oldNumber,
                'new_value_number' => $newNumber,
                'delta_number' => $deltaNumber,
                'delta_percent' => $deltaPercent,
                'source' => $source,
                'source_label' => $this->sourceLabel($source),
                'note' => $note,
                'old_snapshot' => $beforeSnapshot !== [] ? json_encode($beforeSnapshot, JSON_UNESCAPED_UNICODE) : null,
                'new_snapshot' => json_encode($afterSnapshot, JSON_UNESCAPED_UNICODE),
                'metadata' => $metadata !== [] ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
                'changed_by' => $changedBy,
                'changed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            return 0;
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('mp_product_change_logs')->insert($chunk);
        }

        return count($rows);
    }

    /**
     * @param  array<string, array{label: string, type: string}>  $trackedFields
     */
    private function snapshot(object $model, array $trackedFields): array
    {
        $snapshot = [];

        foreach (array_keys($trackedFields) as $field) {
            $snapshot[$field] = $model->{$field};
        }

        return $snapshot;
    }

    private function valuesDiffer(mixed $oldValue, mixed $newValue, string $type): bool
    {
        if (in_array($type, ['money', 'decimal', 'percent'], true)) {
            $old = $this->numericValue($oldValue);
            $new = $this->numericValue($newValue);

            if ($old === null || $new === null) {
                return $old !== $new;
            }

            return abs($old - $new) > 0.0001;
        }

        if ($type === 'integer') {
            return $this->nullableInteger($oldValue) !== $this->nullableInteger($newValue);
        }

        if ($type === 'boolean') {
            return $this->nullableBoolean($oldValue) !== $this->nullableBoolean($newValue);
        }

        return trim((string) $oldValue) !== trim((string) $newValue);
    }

    /**
     * @return array{0: ?float, 1: ?float, 2: ?float, 3: ?float}
     */
    private function numericMetrics(mixed $oldValue, mixed $newValue, string $type): array
    {
        if (!in_array($type, ['money', 'decimal', 'percent', 'integer'], true)) {
            return [null, null, null, null];
        }

        $old = $type === 'integer' ? $this->nullableInteger($oldValue) : $this->numericValue($oldValue);
        $new = $type === 'integer' ? $this->nullableInteger($newValue) : $this->numericValue($newValue);

        if ($old === null || $new === null) {
            return [$old, $new, null, null];
        }

        $delta = round((float) $new - (float) $old, 4);
        $percent = abs((float) $old) > 0.0001 ? round(($delta / (float) $old) * 100, 4) : null;

        return [$old, $new, $delta, $percent];
    }

    private function numericValue(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) str_replace(',', '.', (string) $value), 4);
    }

    private function nullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function nullableBoolean(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (bool) $value;
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}
