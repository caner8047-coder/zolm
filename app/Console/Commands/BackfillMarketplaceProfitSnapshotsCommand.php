<?php

namespace App\Console\Commands;

use App\Models\ChannelOrder;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\MarketplaceProfitSnapshotService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BackfillMarketplaceProfitSnapshotsCommand extends Command
{
    protected $signature = 'marketplace:backfill-profit-snapshots
        {--store=* : Sadece belirli mağaza ID\'lerini işle}
        {--marketplace= : Sadece belirli pazaryerini işle}
        {--from= : Sipariş başlangıç tarihi (YYYY-MM-DD)}
        {--to= : Sipariş bitiş tarihi (YYYY-MM-DD)}
        {--order= : Belirli sipariş numarası veya external order ID}
        {--missing : Sadece order-level profit snapshot olmayan siparişleri işle}
        {--limit=0 : En fazla kaç sipariş işlensin}
        {--chunk=100 : Chunk başına sipariş sayısı}
        {--all : Filtre olmadan tüm siparişleri işlemeyi açıkça onayla}
        {--dry-run : Sadece aday siparişleri göster, snapshot yazma}';

    protected $description = 'V2 channel order kayıtları için order-level profit snapshot backfill/recalculate yapar.';

    public function __construct(
        protected MarketplaceProfitSnapshotService $snapshotService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $storeIds = $this->storeIds();
        $marketplace = trim((string) $this->option('marketplace'));
        try {
            $from = $this->dateOption('from');
            $to = $this->dateOption('to');
        } catch (\InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $order = trim((string) $this->option('order'));
        $limit = max(0, (int) $this->option('limit'));
        $chunkSize = max(1, min(1000, (int) $this->option('chunk')));

        if (! (bool) $this->option('all') && $storeIds === [] && $marketplace === '' && $from === null && $to === null && $order === '') {
            $this->error('Güvenlik için --store, --marketplace, --from/--to veya --order filtresi verin. Tüm siparişler için --all kullanın.');

            return self::FAILURE;
        }

        $baseQuery = $this->candidateQuery($storeIds, $marketplace, $from, $to, $order);
        $totalCandidates = (clone $baseQuery)->count();
        $candidateCount = $limit > 0 ? min($totalCandidates, $limit) : $totalCandidates;

        $this->table(
            ['Alan', 'Deger'],
            [
                ['Magaza filtresi', $storeIds === [] ? '-' : implode(', ', $storeIds)],
                ['Pazaryeri filtresi', $marketplace !== '' ? $marketplace : '-'],
                ['Tarih başlangıç', $from ?? '-'],
                ['Tarih bitiş', $to ?? '-'],
                ['Sipariş filtresi', $order !== '' ? $order : '-'],
                ['Sadece eksikler', $this->option('missing') ? 'evet' : 'hayir'],
                ['Dry run', $this->option('dry-run') ? 'evet' : 'hayir'],
                ['Aday sipariş', (string) $candidateCount],
                ['Toplam eşleşen', (string) $totalCandidates],
                ['Limit', $limit > 0 ? (string) $limit : '-'],
                ['Chunk', (string) $chunkSize],
            ]
        );

        if ($candidateCount === 0) {
            $this->components->info('Backfill için aday sipariş bulunamadı.');

            return self::SUCCESS;
        }

        if ((bool) $this->option('dry-run')) {
            $this->previewOrders($baseQuery, $limit);

            return self::SUCCESS;
        }

        $processed = 0;
        $touchedStoreIds = [];

        $processBatch = function (Collection $orders) use (&$processed, &$touchedStoreIds): void {
            $orders
                ->groupBy('store_id')
                ->each(function (Collection $storeOrders, int|string $storeId) use (&$processed, &$touchedStoreIds): void {
                    /** @var ChannelOrder|null $first */
                    $first = $storeOrders->first();
                    $store = $first?->store ?: MarketplaceStore::query()->find((int) $storeId);

                    if (! $store instanceof MarketplaceStore) {
                        return;
                    }

                    $orderIds = $storeOrders->pluck('id')->map(fn ($id) => (int) $id)->all();
                    $this->snapshotService->recalculateForOrders($store, $orderIds);

                    $processed += count($orderIds);
                    $touchedStoreIds[(int) $store->id] = true;
                });
        };

        if ($limit > 0) {
            $ids = (clone $baseQuery)
                ->orderBy('channel_orders.id')
                ->limit($limit)
                ->pluck('channel_orders.id');

            ChannelOrder::query()
                ->with('store')
                ->whereKey($ids->all())
                ->orderBy('id')
                ->get()
                ->chunk($chunkSize)
                ->each($processBatch);
        } else {
            (clone $baseQuery)
                ->with('store')
                ->orderBy('channel_orders.id')
                ->chunkById($chunkSize, $processBatch, 'channel_orders.id', 'id');
        }

        $this->table(
            ['Sonuc', 'Deger'],
            [
                ['Yeniden hesaplanan sipariş', (string) $processed],
                ['Etkilenen mağaza', (string) count($touchedStoreIds)],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * @param  array<int, int>  $storeIds
     */
    protected function candidateQuery(array $storeIds, string $marketplace, ?string $from, ?string $to, string $order): Builder
    {
        return ChannelOrder::query()
            ->select('channel_orders.*')
            ->when($storeIds !== [], fn (Builder $query) => $query->whereIn('channel_orders.store_id', $storeIds))
            ->when($marketplace !== '', fn (Builder $query) => $query->whereHas('store', fn (Builder $storeQuery) => $storeQuery->where('marketplace', $marketplace)))
            ->when($from !== null, fn (Builder $query) => $query->whereDate('channel_orders.ordered_at', '>=', $from))
            ->when($to !== null, fn (Builder $query) => $query->whereDate('channel_orders.ordered_at', '<=', $to))
            ->when($order !== '', fn (Builder $query) => $query->where(function (Builder $orderQuery) use ($order): void {
                $orderQuery
                    ->where('channel_orders.order_number', $order)
                    ->orWhere('channel_orders.external_order_id', $order);
            }))
            ->when((bool) $this->option('missing'), fn (Builder $query) => $query->whereDoesntHave('profitSnapshots', fn (Builder $snapshotQuery) => $snapshotQuery->whereNull('channel_order_item_id')));
    }

    protected function previewOrders(Builder $query, int $limit): void
    {
        $rows = (clone $query)
            ->with('store')
            ->orderBy('channel_orders.id')
            ->limit($limit > 0 ? min($limit, 10) : 10)
            ->get()
            ->map(fn (ChannelOrder $order) => [
                (string) $order->id,
                $order->store?->store_name ?: '-',
                $order->store?->marketplace ?: '-',
                $order->order_number,
                optional($order->ordered_at)->toDateString() ?: '-',
            ])
            ->all();

        if ($rows === []) {
            return;
        }

        $this->newLine();
        $this->table(['Order ID', 'Mağaza', 'Kanal', 'Sipariş', 'Tarih'], $rows);
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

    protected function dateOption(string $key): ?string
    {
        $value = trim((string) $this->option($key));

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            throw new \InvalidArgumentException("Geçersiz tarih: --{$key}={$value}");
        }
    }
}
