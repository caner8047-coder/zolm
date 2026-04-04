<?php

namespace App\Console\Commands;

use App\Models\IntegrationOrderActionRun;
use App\Models\IntegrationPushRun;
use App\Models\IntegrationSyncRun;
use App\Models\IntegrationWebhookEvent;
use App\Models\MarketplaceStore;
use App\Models\ProductMatchIssue;
use App\Services\Marketplace\MarketplaceConnectionReadinessService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class MarketplaceHealthCheckCommand extends Command
{
    protected $signature = 'marketplace:health-check
        {--store= : Sadece belirli mağaza ID}
        {--fail-on-warning : Uyarı varsa başarısız exit code dön}';

    protected $description = 'Pazaryeri entegrasyon altyapısının production öncesi ve sonrası sağlık özetini gösterir.';

    public function __construct(
        protected MarketplaceConnectionReadinessService $connectionReadiness,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $warnings = [];
        $failures = [];

        $this->components->info('Pazaryeri sağlık kontrolü başlatıldı');

        $appUrl = (string) config('app.url');
        $queueConnection = (string) config('queue.default');
        $cacheStore = (string) config('cache.default');
        $sessionDriver = (string) config('session.driver');
        $v2Enabled = (bool) config('marketplace.features.v2_enabled', true);
        $listingPushEnabled = (bool) config('marketplace.features.listing_push_enabled', true);
        $orderActionsEnabled = (bool) config('marketplace.features.order_actions_enabled', true);

        if ($appUrl === '' || str_contains($appUrl, 'localhost')) {
            $warnings[] = 'APP_URL production kullanımına uygun görünmüyor.';
        }

        if ($appUrl !== '' && ! str_starts_with($appUrl, 'https://')) {
            $warnings[] = 'APP_URL HTTPS ile başlamıyor.';
        }

        if ($queueConnection === 'sync') {
            $warnings[] = 'QUEUE_CONNECTION sync. Production için kalıcı queue driver önerilir.';
        }

        $stores = MarketplaceStore::query()
            ->with(['connection', 'syncProfile', 'syncRuns' => fn ($query) => $query->latest('created_at')->limit(1)])
            ->when($this->option('store'), fn ($query, $storeId) => $query->where('id', $storeId))
            ->orderBy('id')
            ->get();

        if ($stores->isEmpty()) {
            $failures[] = 'Kontrol edilecek mağaza bulunamadı.';
        }

        $activeStores = $stores->where('is_active', true)->count();
        $configuredStores = $stores->filter(fn (MarketplaceStore $store) => in_array((string) $store->connection?->status, ['configured', 'connected'], true))->count();
        $webhookEnabled = $stores->filter(fn (MarketplaceStore $store) => (bool) $store->syncProfile?->webhook_enabled)->count();
        $readinessChecks = $stores->mapWithKeys(fn (MarketplaceStore $store) => [$store->id => $this->connectionReadiness->inspect($store)]);
        $readyStores = $readinessChecks->filter(fn (array $result) => $result['is_ready'])->count();
        $notReadyStores = $stores->count() - $readyStores;

        $failedSyncs24h = IntegrationSyncRun::query()
            ->whereIn('store_id', $stores->pluck('id'))
            ->where('created_at', '>=', now()->subDay())
            ->where('status', 'failed')
            ->count();

        $queuedPushes = IntegrationPushRun::query()
            ->whereIn('store_id', $stores->pluck('id'))
            ->whereIn('status', ['queued', 'processing', 'retrying'])
            ->count();

        $failedActions24h = IntegrationOrderActionRun::query()
            ->whereIn('store_id', $stores->pluck('id'))
            ->where('created_at', '>=', now()->subDay())
            ->where('status', 'failed')
            ->count();

        $failedWebhookEvents24h = IntegrationWebhookEvent::query()
            ->whereIn('store_id', $stores->pluck('id'))
            ->where('created_at', '>=', now()->subDay())
            ->where('status', 'failed')
            ->count();

        $invalidWebhookSignatures24h = IntegrationWebhookEvent::query()
            ->whereIn('store_id', $stores->pluck('id'))
            ->where('created_at', '>=', now()->subDay())
            ->where('signature_valid', false)
            ->count();

        $openMatchIssues = ProductMatchIssue::query()
            ->whereIn('store_id', $stores->pluck('id'))
            ->where('match_status', 'pending')
            ->count();

        if ($configuredStores === 0) {
            $warnings[] = 'Yapılandırılmış bağlantı bulunamadı.';
        }

        if ($notReadyStores > 0) {
            $warnings[] = 'Smoke test için eksik alanları olan mağazalar var.';
        }

        if ($failedSyncs24h > 0) {
            $warnings[] = 'Son 24 saatte başarısız sync kayıtları var.';
        }

        if ($failedActions24h > 0) {
            $warnings[] = 'Son 24 saatte başarısız sipariş aksiyonları var.';
        }

        if ($failedWebhookEvents24h > 0) {
            $warnings[] = 'Son 24 saatte başarısız webhook event kayıtları var.';
        }

        if ($invalidWebhookSignatures24h > 0) {
            $warnings[] = 'Son 24 saatte imzası geçersiz webhook kayıtları var.';
        }

        $this->newLine();
        $this->table(
            ['Kontrol', 'Değer'],
            [
                ['APP_URL', $appUrl !== '' ? $appUrl : '-'],
                ['QUEUE_CONNECTION', $queueConnection],
                ['CACHE_STORE', $cacheStore],
                ['SESSION_DRIVER', $sessionDriver],
                ['Marketplace V2', $v2Enabled ? 'acik' : 'kapali'],
                ['Listing push flag', $listingPushEnabled ? 'acik' : 'kapali'],
                ['Order actions flag', $orderActionsEnabled ? 'acik' : 'kapali'],
                ['Aktif mağaza', (string) $activeStores],
                ['Hazır bağlantı', (string) $configuredStores],
                ['Smoke test hazır mağaza', (string) $readyStores],
                ['Eksik alanlı mağaza', (string) $notReadyStores],
                ['Webhook açık mağaza', (string) $webhookEnabled],
                ['Son 24s başarısız sync', (string) $failedSyncs24h],
                ['Kuyrukta push', (string) $queuedPushes],
                ['Son 24s başarısız aksiyon', (string) $failedActions24h],
                ['Son 24s başarısız webhook', (string) $failedWebhookEvents24h],
                ['Son 24s gecersiz imza', (string) $invalidWebhookSignatures24h],
                ['Açık eşleşme issue', (string) $openMatchIssues],
            ]
        );

        $storeRows = $stores->map(function (MarketplaceStore $store) use ($readinessChecks): array {
            $latestRun = $store->syncRuns->first();
            $readiness = $readinessChecks[$store->id];

            return [
                '#' . $store->id,
                $store->store_name,
                $store->marketplace,
                $store->is_active ? 'aktif' : 'pasif',
                $store->connection?->status ?: 'baglanti-yok',
                $readiness['is_ready'] ? 'hazir' : 'eksik',
                $store->syncProfile?->webhook_enabled ? 'acik' : 'kapali',
                $latestRun?->sync_type ?: '-',
                $latestRun?->status ?: '-',
                $latestRun?->created_at ? Carbon::parse($latestRun->created_at)->format('d.m.Y H:i') : '-',
            ];
        })->all();

        if ($storeRows !== []) {
            $this->newLine();
            $this->table(
                ['ID', 'Mağaza', 'Kanal', 'Aktif', 'Bağlantı', 'Hazırlık', 'Webhook', 'Son sync', 'Durum', 'Zaman'],
                $storeRows
            );
        }

        if ($warnings !== []) {
            $this->newLine();
            $this->components->warn('Uyarılar');
            foreach ($warnings as $warning) {
                $this->line('- ' . $warning);
            }
        }

        if ($failures !== []) {
            $this->newLine();
            $this->components->error('Kritik problemler');
            foreach ($failures as $failure) {
                $this->line('- ' . $failure);
            }
        }

        if ($failures !== []) {
            return self::FAILURE;
        }

        if ($warnings !== [] && $this->option('fail-on-warning')) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('Pazaryeri sağlık kontrolü tamamlandı.');

        return self::SUCCESS;
    }
}
