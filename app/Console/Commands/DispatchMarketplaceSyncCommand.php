<?php

namespace App\Console\Commands;

use App\Jobs\SyncMarketplaceDataJob;
use App\Models\IntegrationSyncRun;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\MarketplaceConnectionReadinessService;
use Illuminate\Console\Command;

class DispatchMarketplaceSyncCommand extends Command
{
    protected $signature = 'marketplace:dispatch-sync
        {store : Mağaza ID}
        {syncType : orders|products|finance}
        {--start= : ISO tarih başlangıcı}
        {--end= : ISO tarih bitişi}
        {--order-number= : Tek sipariş no ile sınırla}';

    protected $description = 'Tek bir mağaza için pazaryeri senkronunu kuyruğa alır.';

    public function __construct(protected MarketplaceConnectionReadinessService $connectionReadiness)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $store = MarketplaceStore::query()
            ->with(['connection', 'syncProfile'])
            ->find($this->argument('store'));

        if (!$store) {
            $this->error('Mağaza bulunamadı.');

            return self::FAILURE;
        }

        $syncType = (string) $this->argument('syncType');

        if (!in_array($syncType, ['orders', 'products', 'finance'], true)) {
            $this->error('Geçersiz sync tipi. orders, products veya finance kullanın.');

            return self::FAILURE;
        }

        if (!$store->is_active || $store->connection?->status === 'draft') {
            $this->error('Mağaza aktif değil veya bağlantı taslak durumda.');

            return self::FAILURE;
        }

        $readiness = $this->connectionReadiness->inspect($store);

        if ($readiness['failures'] !== []) {
            $this->error('Mağaza hazır değil: '.($readiness['failures'][0] ?? 'Bilinmeyen doğrulama hatası.'));

            return self::FAILURE;
        }

        $run = IntegrationSyncRun::create([
            'store_id' => $store->id,
            'sync_type' => $syncType,
            'trigger_type' => 'manual',
            'status' => 'queued',
            'notes_json' => [
                'options' => array_filter([
                    'start_date' => $this->option('start'),
                    'end_date' => $this->option('end'),
                    'order_number' => $this->option('order-number'),
                ]),
            ],
        ]);

        SyncMarketplaceDataJob::dispatch($run->id);

        $this->info("Sync kuyruğa alındı. Run ID: {$run->id}");

        return self::SUCCESS;
    }
}
