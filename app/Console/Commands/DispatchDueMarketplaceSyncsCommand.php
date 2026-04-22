<?php

namespace App\Console\Commands;

use App\Jobs\SyncMarketplaceDataJob;
use App\Models\IntegrationSyncRun;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\MarketplaceConnectionReadinessService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class DispatchDueMarketplaceSyncsCommand extends Command
{
    protected $signature = 'marketplace:dispatch-due-syncs
        {--type= : Sadece orders|products|finance çalıştır}
        {--store= : Sadece belirli mağaza ID}
        {--force : Zaman penceresini beklemeden dispatch et}';

    protected $description = 'Aktif mağazalar için profil ayarlarına göre zamanı gelmiş sync işlerini kuyruğa alır.';

    public function __construct(protected MarketplaceConnectionReadinessService $connectionReadiness)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $requestedType = $this->option('type');

        if ($requestedType && !in_array($requestedType, ['orders', 'products', 'finance'], true)) {
            $this->error('Geçersiz type. orders, products veya finance kullanın.');

            return self::FAILURE;
        }

        $stores = MarketplaceStore::query()
            ->with(['connection', 'syncProfile'])
            ->where('is_active', true)
            ->when($this->option('store'), fn ($query, $storeId) => $query->where('id', $storeId))
            ->get();

        $queuedCount = 0;

        foreach ($stores as $store) {
            if (!$store->connection || !in_array($store->connection->status, ['configured', 'connected'], true)) {
                continue;
            }

            $readiness = $this->connectionReadiness->inspect($store);

            if ($readiness['failures'] !== []) {
                $this->warn("Skipped store #{$store->id} ({$store->store_name}): ".$readiness['failures'][0]);

                continue;
            }

            foreach ($this->syncDefinitions($store) as $syncType => $definition) {
                if ($requestedType && $requestedType !== $syncType) {
                    continue;
                }

                if (!$definition['enabled'] && !$this->option('force')) {
                    continue;
                }

                if (!$this->option('force') && !$this->isDue($store->id, $syncType, (int) $definition['minutes'])) {
                    continue;
                }

                if ($this->hasFreshPendingRun($store->id, $syncType)) {
                    continue;
                }

                $run = IntegrationSyncRun::create([
                    'store_id' => $store->id,
                    'sync_type' => $syncType,
                    'trigger_type' => 'schedule',
                    'status' => 'queued',
                    'notes_json' => [
                        'options' => [],
                    ],
                ]);

                SyncMarketplaceDataJob::dispatch($run->id);
                $queuedCount++;
                $this->line("Queued {$syncType} for store #{$store->id} ({$store->store_name})");
            }
        }

        $this->info("Toplam {$queuedCount} sync işi kuyruğa alındı.");

        return self::SUCCESS;
    }

    /**
     * @return array<string, array{enabled: bool, minutes: int}>
     */
    protected function syncDefinitions(MarketplaceStore $store): array
    {
        $profile = $store->syncProfile;

        return [
            'orders' => [
                'enabled' => (bool) $profile?->orders_enabled,
                'minutes' => (int) ($profile?->orders_poll_minutes ?: 15),
            ],
            'finance' => [
                'enabled' => (bool) $profile?->finance_enabled,
                'minutes' => (int) ($profile?->finance_poll_minutes ?: 60),
            ],
            'products' => [
                'enabled' => (bool) $profile?->products_enabled,
                'minutes' => (int) ($profile?->products_poll_minutes ?: 360),
            ],
        ];
    }

    protected function isDue(int $storeId, string $syncType, int $minutes): bool
    {
        $lastSuccessfulRun = IntegrationSyncRun::query()
            ->where('store_id', $storeId)
            ->where('sync_type', $syncType)
            ->where('status', 'completed')
            ->latest('finished_at')
            ->first();

        if (!$lastSuccessfulRun?->finished_at) {
            return true;
        }

        return CarbonImmutable::parse($lastSuccessfulRun->finished_at)
            ->addMinutes($minutes)
            ->lessThanOrEqualTo(now());
    }

    protected function hasFreshPendingRun(int $storeId, string $syncType): bool
    {
        return IntegrationSyncRun::query()
            ->where('store_id', $storeId)
            ->where('sync_type', $syncType)
            ->whereIn('status', ['queued', 'processing'])
            ->where('created_at', '>=', now()->subHours(2))
            ->exists();
    }
}
