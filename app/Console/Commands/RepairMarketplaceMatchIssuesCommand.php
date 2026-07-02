<?php

namespace App\Console\Commands;

use App\Models\ChannelOrderItem;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\MarketplaceProductMatcher;
use App\Services\Marketplace\MarketplaceProfitSnapshotService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RepairMarketplaceMatchIssuesCommand extends Command
{
    protected $signature = 'marketplace:repair-match-issues
        {--store=* : Sadece belirli mağaza ID\'lerini işle}
        {--marketplace= : Sadece belirli pazaryerini işle}
        {--limit=0 : En fazla kaç sipariş satırı işlensin}
        {--chunk=100 : Chunk başına sipariş satırı}
        {--all : Filtre olmadan tüm mağazaları işlemeyi açıkça onayla}
        {--no-recalculate : Eşleşen satırlardan sonra kâr snapshot yeniden hesaplama}
        {--dry-run : Sadece aday sipariş satırlarını göster, veri yazma}';

    protected $description = 'Eşleşmemiş sipariş satırlarını Eşleştirme Merkezi için aksiyonlanabilir issue kayıtlarına dönüştürür.';

    public function __construct(
        protected MarketplaceProductMatcher $matcher,
        protected MarketplaceProfitSnapshotService $snapshotService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $storeIds = $this->storeIds();
        $marketplace = trim((string) $this->option('marketplace'));
        $limit = max(0, (int) $this->option('limit'));
        $chunkSize = max(1, min(1000, (int) $this->option('chunk')));
        $dryRun = (bool) $this->option('dry-run');

        if (! (bool) $this->option('all') && $storeIds === [] && $marketplace === '') {
            $this->error('Güvenlik için --store veya --marketplace filtresi verin. Tüm mağazalar için --all kullanın.');

            return self::FAILURE;
        }

        $baseQuery = $this->candidateQuery($storeIds, $marketplace);
        $totalCandidates = (clone $baseQuery)->count();
        $candidateCount = $limit > 0 ? min($totalCandidates, $limit) : $totalCandidates;

        $this->table(
            ['Alan', 'Deger'],
            [
                ['Mağaza filtresi', $storeIds === [] ? '-' : implode(', ', $storeIds)],
                ['Pazaryeri filtresi', $marketplace !== '' ? $marketplace : '-'],
                ['Dry run', $dryRun ? 'evet' : 'hayir'],
                ['Aday satır', (string) $candidateCount],
                ['Toplam eşleşen aday', (string) $totalCandidates],
                ['Limit', $limit > 0 ? (string) $limit : '-'],
                ['Chunk', (string) $chunkSize],
                ['Snapshot yeniden hesaplama', $this->option('no-recalculate') ? 'hayir' : 'evet'],
            ]
        );

        if ($candidateCount === 0) {
            $this->components->info('Onarılacak listelemesiz eşleşme satırı bulunamadı.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->previewItems($baseQuery, $limit);

            return self::SUCCESS;
        }

        $processed = 0;
        $hydrated = 0;
        $matched = 0;
        $stillOpen = 0;
        $impactedByStore = [];

        $processBatch = function (Collection $items) use (&$processed, &$hydrated, &$matched, &$stillOpen, &$impactedByStore): void {
            foreach ($items as $item) {
                /** @var ChannelOrderItem $item */
                $beforeListingId = $item->channel_listing_id;
                $beforeProductId = $item->mp_product_id;

                $this->matcher->applyToOrderItem($item, $item->stock_code, $item->barcode);

                $item->refresh();
                $processed++;

                if (!$beforeListingId && $item->channel_listing_id) {
                    $hydrated++;
                }

                if (!$beforeProductId && $item->mp_product_id && (bool) $item->is_matched) {
                    $matched++;
                }

                if (!$item->mp_product_id || ! (bool) $item->is_matched) {
                    $stillOpen++;
                }

                if ($item->mp_product_id || $beforeProductId !== $item->mp_product_id) {
                    $impactedByStore[(int) $item->store_id][] = (int) $item->channel_order_id;
                }
            }
        };

        if ($limit > 0) {
            $ids = (clone $baseQuery)
                ->orderBy('channel_order_items.id')
                ->limit($limit)
                ->pluck('channel_order_items.id');

            ChannelOrderItem::query()
                ->with(['store.syncProfile', 'listing.product', 'order'])
                ->whereKey($ids->all())
                ->orderBy('id')
                ->get()
                ->chunk($chunkSize)
                ->each($processBatch);
        } else {
            (clone $baseQuery)
                ->with(['store.syncProfile', 'listing.product', 'order'])
                ->orderBy('channel_order_items.id')
                ->chunkById($chunkSize, $processBatch, 'channel_order_items.id', 'id');
        }

        $recalculated = 0;
        if (! (bool) $this->option('no-recalculate')) {
            foreach ($impactedByStore as $storeId => $orderIds) {
                $store = MarketplaceStore::query()->find((int) $storeId);

                if (!$store) {
                    continue;
                }

                $uniqueOrderIds = array_values(array_unique(array_filter($orderIds)));
                if ($uniqueOrderIds === []) {
                    continue;
                }

                $this->snapshotService->recalculateForOrders($store, $uniqueOrderIds);
                $recalculated += count($uniqueOrderIds);
            }
        }

        $this->table(
            ['Sonuç', 'Değer'],
            [
                ['İşlenen satır', (string) $processed],
                ['Listeleme bağı oluşturulan', (string) $hydrated],
                ['Master ürüne eşleşen', (string) $matched],
                ['Hala aksiyon bekleyen', (string) $stillOpen],
                ['Snapshot yeniden hesaplanan sipariş', (string) $recalculated],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * @param  array<int, int>  $storeIds
     */
    protected function candidateQuery(array $storeIds, string $marketplace): Builder
    {
        return ChannelOrderItem::query()
            ->select('channel_order_items.*')
            ->join('marketplace_stores', 'marketplace_stores.id', '=', 'channel_order_items.store_id')
            ->where(function (Builder $query): void {
                $query->whereNull('channel_order_items.mp_product_id')
                    ->orWhere('channel_order_items.is_matched', false);
            })
            ->when($storeIds !== [], fn (Builder $query) => $query->whereIn('channel_order_items.store_id', $storeIds))
            ->when($marketplace !== '', fn (Builder $query) => $query->where('marketplace_stores.marketplace', $marketplace));
    }

    protected function previewItems(Builder $query, int $limit): void
    {
        $rows = (clone $query)
            ->with(['store', 'order'])
            ->orderBy('channel_order_items.id')
            ->limit($limit > 0 ? min($limit, 10) : 10)
            ->get()
            ->map(fn (ChannelOrderItem $item) => [
                (string) $item->id,
                $item->store?->store_name ?: '-',
                $item->store?->marketplace ?: '-',
                $item->order?->order_number ?: '-',
                $item->stock_code ?: '-',
                $item->barcode ?: '-',
                mb_strimwidth((string) $item->product_name, 0, 48, '...'),
            ])
            ->all();

        if ($rows === []) {
            return;
        }

        $this->newLine();
        $this->table(['Satır ID', 'Mağaza', 'Kanal', 'Sipariş', 'Stok Kodu', 'Barkod', 'Ürün'], $rows);
    }

    /**
     * @return array<int, int>
     */
    protected function storeIds(): array
    {
        return collect((array) $this->option('store'))
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $value) => $value > 0)
            ->unique()
            ->values()
            ->all();
    }
}
