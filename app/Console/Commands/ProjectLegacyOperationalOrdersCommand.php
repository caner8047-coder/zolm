<?php

namespace App\Console\Commands;

use App\Models\MarketplaceStore;
use App\Models\MpOperationalOrder;
use App\Services\Marketplace\LegacyOperationalProjectionService;
use Illuminate\Console\Command;

class ProjectLegacyOperationalOrdersCommand extends Command
{
    protected $signature = 'marketplace:project-legacy-orders
        {store : Projection hedefi mağaza ID}
        {--limit=0 : En fazla kaç legacy sipariş işlensin}
        {--only-unprojected : Sadece henüz project edilmemiş kayıtları al}
        {--include-unassigned : store_id boş legacy kayıtları da dahil et}
        {--dry-run : Sadece aday kayıtları göster, projection yapma}';

    protected $description = 'Legacy operasyon siparişlerini yeni channel order projection yapısına taşır.';

    public function __construct(
        protected LegacyOperationalProjectionService $projectionService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $store = MarketplaceStore::query()->with('legalEntity')->findOrFail((int) $this->argument('store'));

        $query = MpOperationalOrder::query()
            ->with('items.product')
            ->where(function ($builder) use ($store) {
                $builder->where('store_id', $store->id);

                if ($this->option('include-unassigned')) {
                    $builder->orWhereNull('store_id');
                }
            })
            ->orderByDesc('order_date')
            ->orderByDesc('id');

        if ($this->option('only-unprojected')) {
            $query->whereNull('projected_at');
        }

        $limit = max(0, (int) $this->option('limit'));

        if ($limit > 0) {
            $query->limit($limit);
        }

        $orders = $query->get();

        $this->table(
            ['Alan', 'Deger'],
            [
                ['Magaza', $store->store_name],
                ['Kanal', $store->marketplace],
                ['Firma', $store->legalEntity?->name ?: '-'],
                ['Aday legacy sipariş', (string) $orders->count()],
                ['Dry run', $this->option('dry-run') ? 'evet' : 'hayir'],
            ]
        );

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $result = $this->projectionService->projectOperationalOrders($store, $orders);

        $this->table(
            ['Sonuc', 'Deger'],
            [
                ['Projected legacy sipariş', (string) ($result['projected_orders'] ?? 0)],
                ['Yeni kayit', (string) ($result['created'] ?? 0)],
                ['Guncellenen kayit', (string) ($result['updated'] ?? 0)],
                ['Atlanan kayit', (string) ($result['skipped'] ?? 0)],
                ['Etkilenen channel order', (string) count($result['impacted_order_ids'] ?? [])],
            ]
        );

        return self::SUCCESS;
    }
}
