<?php

namespace App\Console\Commands;

use App\Models\MarketplaceStore;
use App\Services\Marketplace\LegacyFinancialProjectionService;
use Illuminate\Console\Command;

class ProjectLegacyFinancialsCommand extends Command
{
    protected $signature = 'marketplace:project-legacy-financials
        {store : Projection hedefi mağaza ID}
        {--limit=0 : En fazla kaç legacy finans satırı işlensin}
        {--only-unprojected : Sadece henüz project edilmemiş legacy finans satırlarını al}
        {--dry-run : Sadece aday satır sayısını göster, projection yapma}';

    protected $description = 'Legacy MpOrder finans kayıtlarını yeni order_financial_events ledger yapısına taşır.';

    public function __construct(
        protected LegacyFinancialProjectionService $projectionService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $store = MarketplaceStore::query()->with('legalEntity')->findOrFail((int) $this->argument('store'));
        $onlyUnprojected = (bool) $this->option('only-unprojected');
        $limit = max(0, (int) $this->option('limit'));
        $preview = $this->projectionService->previewStore($store, $onlyUnprojected, $limit);

        $this->table(
            ['Alan', 'Deger'],
            [
                ['Magaza', $store->store_name],
                ['Kanal', $store->marketplace],
                ['Firma', $store->legalEntity?->name ?: '-'],
                ['Only unprojected', $onlyUnprojected ? 'evet' : 'hayir'],
                ['Dry run', $this->option('dry-run') ? 'evet' : 'hayir'],
                ['Aday legacy finans satiri', (string) ($preview['projected_rows'] ?? 0)],
            ]
        );

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $result = $this->projectionService->projectStore($store, $onlyUnprojected, $limit);

        $this->table(
            ['Sonuc', 'Deger'],
            [
                ['Projected finans satiri', (string) ($result['projected_rows'] ?? 0)],
                ['Yeni event', (string) ($result['created'] ?? 0)],
                ['Guncellenen event', (string) ($result['updated'] ?? 0)],
                ['Atlanan event', (string) ($result['skipped'] ?? 0)],
                ['Etkilenen channel order', (string) count($result['impacted_order_ids'] ?? [])],
            ]
        );

        return self::SUCCESS;
    }
}
