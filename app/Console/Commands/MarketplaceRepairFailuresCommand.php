<?php

namespace App\Console\Commands;

use App\Models\IntegrationOrderActionRun;
use App\Models\IntegrationPushRun;
use App\Models\IntegrationSyncRun;
use App\Models\IntegrationWebhookEvent;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\MarketplaceHealthRetryService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class MarketplaceRepairFailuresCommand extends Command
{
    protected $signature = 'marketplace:repair-failures
        {--store= : Sadece belirli mağaza ID}
        {--type=all : syncs, pushes, actions, webhooks veya all}
        {--limit=25 : Her tip için maksimum kayıt sayısı}
        {--hours=168 : Sadece son N saatteki kayıtlar}
        {--dry-run : Yalnızca aday kayıtları göster, yeniden kuyruğa alma}';

    protected $description = 'Başarısız pazaryeri sync, push, aksiyon ve webhook kayıtlarını toplu şekilde tekrar kuyruğa alır.';

    public function __construct(
        protected MarketplaceHealthRetryService $retryService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $types = $this->normalizedTypes();

        if ($types === []) {
            $this->error('Geçersiz type seçimi. syncs, pushes, actions, webhooks veya all kullanın.');

            return self::FAILURE;
        }

        $limit = max(1, min(250, (int) $this->option('limit')));
        $hours = max(1, (int) $this->option('hours'));
        $dryRun = (bool) $this->option('dry-run');
        $storeId = $this->option('store') ? (int) $this->option('store') : null;

        if ($storeId !== null && ! MarketplaceStore::query()->whereKey($storeId)->exists()) {
            $this->error('Belirtilen mağaza bulunamadı.');

            return self::FAILURE;
        }

        $this->components->info($dryRun ? 'Hata onarım dry-run başlatıldı' : 'Hata onarımı başlatıldı');

        $results = [];

        if (in_array('syncs', $types, true)) {
            $runs = $this->syncQuery($storeId, $hours)->latest('created_at')->limit($limit)->get();
            $results['syncs'] = [
                'count' => $runs->count(),
                'records' => $runs,
                'repaired' => $dryRun ? collect() : $this->retryService->retrySyncBatch($runs),
            ];
        }

        if (in_array('pushes', $types, true)) {
            $runs = $this->pushQuery($storeId, $hours)->latest('created_at')->limit($limit)->get();
            $repairResult = $dryRun
                ? ['runs' => collect(), 'created' => 0, 'coalesced' => 0, 'busy' => 0, 'recent' => 0]
                : $this->retryService->retryPushBatchDetailed($runs);

            $results['pushes'] = [
                'count' => $runs->count(),
                'records' => $runs,
                'repaired' => $repairResult['runs'],
                'created' => $repairResult['created'],
                'coalesced' => $repairResult['coalesced'],
                'busy' => $repairResult['busy'],
                'recent' => $repairResult['recent'],
            ];
        }

        if (in_array('actions', $types, true)) {
            $runs = $this->actionQuery($storeId, $hours)->with('package')->latest('created_at')->limit($limit)->get();
            $repairResult = $dryRun
                ? ['runs' => collect(), 'created' => 0, 'coalesced' => 0, 'busy' => 0, 'recent' => 0]
                : $this->retryService->retryOrderActionBatchDetailed($runs);

            $results['actions'] = [
                'count' => $runs->count(),
                'records' => $runs,
                'repaired' => $repairResult['runs'],
                'created' => $repairResult['created'],
                'coalesced' => $repairResult['coalesced'],
                'busy' => $repairResult['busy'],
                'recent' => $repairResult['recent'],
            ];
        }

        if (in_array('webhooks', $types, true)) {
            $events = $this->webhookQuery($storeId, $hours)->latest('created_at')->limit($limit)->get();
            $results['webhooks'] = [
                'count' => $events->count(),
                'records' => $events,
                'repaired' => $dryRun ? collect() : $this->retryService->replayWebhookBatch($events),
            ];
        }

        $this->newLine();
        $this->table(
            ['Tip', 'Aday kayıt', 'İşlenen', 'Yeni', 'Güncellendi', 'Çalışıyordu', 'Çok yeni', 'Limit', 'Saat penceresi'],
            collect($results)->map(function (array $row, string $type) use ($limit, $hours, $dryRun): array {
                return [
                    $this->typeLabel($type),
                    (string) $row['count'],
                    $dryRun ? '0 (dry-run)' : (string) $row['repaired']->count(),
                    (string) ($row['created'] ?? $row['repaired']->count()),
                    (string) ($row['coalesced'] ?? 0),
                    (string) ($row['busy'] ?? 0),
                    (string) ($row['recent'] ?? 0),
                    (string) $limit,
                    (string) $hours,
                ];
            })->values()->all()
        );

        $totalCandidates = collect($results)->sum('count');
        $totalProcessed = $dryRun ? 0 : collect($results)->sum(fn (array $row) => $row['repaired']->count());

        if ($dryRun) {
            $this->newLine();
            $this->components->warn("Dry-run tamamlandı. Toplam {$totalCandidates} aday kayıt bulundu, yeniden kuyruklama yapılmadı.");

            return self::SUCCESS;
        }

        $this->newLine();
        $this->components->info("Onarım tamamlandı. {$totalProcessed} kayıt yeniden kuyruğa alındı.");

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    protected function normalizedTypes(): array
    {
        $type = trim((string) $this->option('type'));

        if ($type === 'all') {
            return ['syncs', 'pushes', 'actions', 'webhooks'];
        }

        $normalized = collect(explode(',', $type))
            ->map(fn (string $item) => trim($item))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $allowed = ['syncs', 'pushes', 'actions', 'webhooks'];

        return array_values(array_filter($normalized, fn (string $item) => in_array($item, $allowed, true)));
    }

    protected function syncQuery(?int $storeId, int $hours): Builder
    {
        return IntegrationSyncRun::query()
            ->when($storeId, fn (Builder $query) => $query->where('store_id', $storeId))
            ->where('status', 'failed')
            ->where('trigger_type', '!=', 'smoke_test')
            ->where('created_at', '>=', now()->subHours($hours));
    }

    protected function pushQuery(?int $storeId, int $hours): Builder
    {
        return IntegrationPushRun::query()
            ->when($storeId, fn (Builder $query) => $query->where('store_id', $storeId))
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subHours($hours));
    }

    protected function actionQuery(?int $storeId, int $hours): Builder
    {
        return IntegrationOrderActionRun::query()
            ->when($storeId, fn (Builder $query) => $query->where('store_id', $storeId))
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subHours($hours));
    }

    protected function webhookQuery(?int $storeId, int $hours): Builder
    {
        return IntegrationWebhookEvent::query()
            ->when($storeId, fn (Builder $query) => $query->where('store_id', $storeId))
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subHours($hours));
    }

    protected function typeLabel(string $type): string
    {
        return match ($type) {
            'syncs' => 'Sync',
            'pushes' => 'Push',
            'actions' => 'Sipariş aksiyonu',
            'webhooks' => 'Webhook',
            default => $type,
        };
    }
}
