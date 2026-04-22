<?php

namespace App\Console\Commands;

use App\Jobs\SyncMarketplaceDataJob;
use App\Models\ChannelOrder;
use App\Models\IntegrationSyncRun;
use App\Models\MarketplaceStore;
use App\Models\OrderProfitSnapshot;
use App\Services\Marketplace\MarketplaceConnectionReadinessService;
use App\Services\Marketplace\MarketplaceProfitSnapshotService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NightlyRepairSyncCommand extends Command
{
    protected $signature = 'marketplace:nightly-repair
        {--store= : Sadece belirli mağaza ID}
        {--days=30 : Kaç gün geriye bakılacağı}
        {--dry-run : Sadece analiz yap, aksiyon alma}';

    protected $description = 'Gece onarım sync: eksik finansları tamamla, eşleşmeyen ürünleri yeniden eşleştir, snapshot'ları yeniden hesapla.';

    public function __construct(protected MarketplaceConnectionReadinessService $connectionReadiness)
    {
        parent::__construct();
    }

    public function handle(MarketplaceProfitSnapshotService $profitService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $lookbackDays = (int) $this->option('days');
        $startTime = now();

        $this->info('🌙 Gece onarım sync başlatılıyor...');
        $this->line("   Lookback: Son {$lookbackDays} gün" . ($dryRun ? ' [DRY-RUN]' : ''));

        $stores = MarketplaceStore::query()
            ->with(['connection', 'syncProfile'])
            ->where('is_active', true)
            ->when($this->option('store'), fn ($query, $storeId) => $query->where('id', $storeId))
            ->get()
            ->filter(fn (MarketplaceStore $store) => (bool) $store->syncProfile?->nightly_repair_sync_enabled);

        if ($stores->isEmpty()) {
            $this->warn('Nightly repair sync aktif mağaza bulunamadı.');

            return self::SUCCESS;
        }

        $totalRepaired = 0;
        $totalSnapshotsRecalculated = 0;
        $totalFinanceDispatched = 0;

        foreach ($stores as $store) {
            $this->newLine();
            $this->line("━━━ Mağaza #{$store->id}: {$store->store_name} ({$store->marketplace}) ━━━");

            $readiness = $this->connectionReadiness->inspect($store);

            if ($readiness['failures'] !== []) {
                $this->warn('   ⏭️  Readiness başarısız, mağaza atlanıyor: '.($readiness['failures'][0] ?? 'Bilinmeyen hata'));

                continue;
            }

            // 1. Finans eksik siparişleri tespit et
            $sinceDate = CarbonImmutable::now()->subDays($lookbackDays);

            $ordersWithoutFinance = ChannelOrder::query()
                ->where('store_id', $store->id)
                ->where('ordered_at', '>=', $sinceDate)
                ->whereDoesntHave('financialEvents')
                ->whereNotIn('order_status', ['Cancelled', 'cancelled', 'İptal', 'iptal'])
                ->count();

            $this->line("   📊 Finans eksik sipariş sayısı: {$ordersWithoutFinance}");

            // 2. Eksik snapshot'lar
            $ordersWithoutSnapshot = ChannelOrder::query()
                ->where('store_id', $store->id)
                ->where('ordered_at', '>=', $sinceDate)
                ->whereDoesntHave('profitSnapshot')
                ->count();

            $this->line("   📊 Snapshot eksik sipariş sayısı: {$ordersWithoutSnapshot}");

            // 3. Stale snapshot'lar (finans geldikten sonra yeniden hesaplanmamış)
            $staleSnapshots = OrderProfitSnapshot::query()
                ->where('store_id', $store->id)
                ->where('profit_state', 'estimated')
                ->whereHas('order', function ($query) use ($sinceDate) {
                    $query->where('ordered_at', '>=', $sinceDate);
                })
                ->whereHas('order.financialEvents')
                ->count();

            $this->line("   📊 Stale snapshot sayısı (estimated ama finans var): {$staleSnapshots}");

            if ($dryRun) {
                $this->line('   ⏭️  Dry-run modu, aksiyon alınmıyor.');

                continue;
            }

            // Aksiyon 1: Finans eksik siparişler için repair finance sync dispatch et
            if ($ordersWithoutFinance > 0 && $store->connection && in_array($store->connection->status, ['configured', 'connected'], true)) {
                if (!$this->hasFreshPendingRun($store->id, 'finance')) {
                    $run = IntegrationSyncRun::create([
                        'store_id' => $store->id,
                        'sync_type' => 'finance',
                        'trigger_type' => 'nightly_repair',
                        'status' => 'queued',
                        'notes_json' => [
                            'options' => [
                                'start_date' => $sinceDate->toIso8601String(),
                                'end_date' => CarbonImmutable::now()->toIso8601String(),
                            ],
                            'repair_reason' => 'nightly_repair_missing_finance',
                            'missing_count' => $ordersWithoutFinance,
                        ],
                    ]);

                    SyncMarketplaceDataJob::dispatch($run->id);
                    $totalFinanceDispatched++;
                    $this->line("   ✅ Finance repair sync kuyruğa alındı (#{$run->id})");
                } else {
                    $this->line('   ⏭️  Aktif finance sync var, atlanıyor.');
                }
            }

            // Aksiyon 2: Eksik snapshot'ları hesapla
            if ($ordersWithoutSnapshot > 0) {
                $orderIdsWithoutSnapshot = ChannelOrder::query()
                    ->where('store_id', $store->id)
                    ->where('ordered_at', '>=', $sinceDate)
                    ->whereDoesntHave('profitSnapshot')
                    ->pluck('id')
                    ->all();

                $profitService->recalculateForOrders($store, $orderIdsWithoutSnapshot);
                $recalculated = count($orderIdsWithoutSnapshot);
                $totalSnapshotsRecalculated += $recalculated;
                $this->line("   ✅ {$recalculated} eksik snapshot hesaplandı.");
            }

            // Aksiyon 3: Stale snapshot'ları yeniden hesapla
            if ($staleSnapshots > 0) {
                $staleOrderIds = OrderProfitSnapshot::query()
                    ->where('store_id', $store->id)
                    ->where('profit_state', 'estimated')
                    ->whereHas('order', function ($query) use ($sinceDate) {
                        $query->where('ordered_at', '>=', $sinceDate);
                    })
                    ->whereHas('order.financialEvents')
                    ->pluck('channel_order_id')
                    ->all();

                $profitService->recalculateForOrders($store, $staleOrderIds);
                $recalculated = count($staleOrderIds);
                $totalSnapshotsRecalculated += $recalculated;
                $totalRepaired += $recalculated;
                $this->line("   ✅ {$recalculated} stale snapshot güncellendi.");
            }
        }

        $this->newLine();
        $elapsed = now()->diffInSeconds($startTime);
        $this->info("🌙 Gece onarım sync tamamlandı ({$elapsed}s):");
        $this->line("   Finance repair dispatch: {$totalFinanceDispatched}");
        $this->line("   Snapshot hesaplanan: {$totalSnapshotsRecalculated}");
        $this->line("   Stale snapshot onarılan: {$totalRepaired}");

        Log::info('[NightlyRepairSync] Tamamlandı.', [
            'stores_processed' => $stores->count(),
            'finance_dispatched' => $totalFinanceDispatched,
            'snapshots_recalculated' => $totalSnapshotsRecalculated,
            'stale_repaired' => $totalRepaired,
            'elapsed_seconds' => $elapsed,
        ]);

        return self::SUCCESS;
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
