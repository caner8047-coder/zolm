<?php

namespace App\Livewire;

use App\Models\ChannelListing;
use App\Models\ChannelOrder;
use App\Models\IntegrationOrderActionRun;
use App\Models\IntegrationPushRun;
use App\Models\IntegrationSyncRun;
use App\Models\IntegrationWebhookEvent;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\MpProduct;
use App\Models\OrderFinancialEvent;
use App\Models\OrderProfitSnapshot;
use App\Models\ProductMatchIssue;
use App\Services\Marketplace\MarketplaceConnectionReadinessService;
use App\Services\Marketplace\MarketplaceDiagnosticsGuidanceService;
use App\Services\Marketplace\MarketplaceDiagnosticsReportService;
use App\Services\Marketplace\MarketplaceHealthRetryService;
use App\Services\Marketplace\LegacyFinancialProjectionService;
use App\Services\Marketplace\LegacyFinancialProjectionInsightsService;
use App\Services\Marketplace\MarketplaceManualSyncDispatchService;
use App\Services\Marketplace\MarketplaceOrderActionService;
use App\Services\Marketplace\MarketplaceProviderRegistry;
use App\Services\Marketplace\MarketplaceReconciliationQueryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

class MarketplaceOverview extends Component
{
    public ?string $flashMessage = null;

    public string $flashTone = 'success';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $legacyProjectionPreviews = [];

    #[Computed]
    public function heroStats(): array
    {
        $storeQuery = MarketplaceStore::query()->where('user_id', $this->userId());
        $orderQuery = ChannelOrder::query()->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()));
        $listingQuery = ChannelListing::query()->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()));
        $snapshotQuery = OrderProfitSnapshot::query()
            ->whereNull('channel_order_item_id')
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()));

        return [
            'active_stores' => (clone $storeQuery)->where('is_active', true)->count(),
            'total_stores' => (clone $storeQuery)->count(),
            'total_orders' => (clone $orderQuery)->count(),
            'finance_ready_orders' => (clone $orderQuery)->has('financialEvents')->count(),
            'total_listings' => (clone $listingQuery)->count(),
            'active_listings' => (clone $listingQuery)->whereIn('listing_status', ['active', 'approved', 'live', 'on_sale', 'onsale', 'published', 'publish', 'enabled'])->count(),
            'net_receivable' => round((float) (clone $snapshotQuery)->sum('net_receivable'), 2),
            'confirmed_profit' => round((float) (clone $snapshotQuery)->where('profit_state', 'confirmed')->sum('confirmed_profit'), 2),
        ];
    }

    #[Computed]
    public function reconciliationStats(): array
    {
        $reconciliation = app(MarketplaceReconciliationQueryService::class);
        $itemAggregate = $reconciliation->itemAggregate();
        $financialAggregate = $reconciliation->financialAggregate();
        $snapshotAggregate = $reconciliation->snapshotAggregate();
        $expr = $reconciliation->expressions();

        $rawStats = ChannelOrder::query()
            ->join('marketplace_stores', 'marketplace_stores.id', '=', 'channel_orders.store_id')
            ->leftJoinSub($itemAggregate, 'item_agg', fn ($join) => $join->on('item_agg.channel_order_id', '=', 'channel_orders.id'))
            ->leftJoinSub($financialAggregate, 'fin_agg', fn ($join) => $join->on('fin_agg.channel_order_id', '=', 'channel_orders.id'))
            ->leftJoinSub($snapshotAggregate, 'order_snapshot', fn ($join) => $join->on('order_snapshot.channel_order_id', '=', 'channel_orders.id'))
            ->where('marketplace_stores.user_id', $this->userId())
            ->selectRaw("
                COUNT(*) as total_orders,
                SUM(CASE WHEN {$expr['reconciliation_state']} = 'waiting' THEN 1 ELSE 0 END) as waiting_orders,
                SUM(CASE WHEN {$expr['reconciliation_state']} = 'snapshot_missing' THEN 1 ELSE 0 END) as snapshot_missing_orders,
                SUM(CASE WHEN {$expr['reconciliation_state']} = 'aligned' THEN 1 ELSE 0 END) as aligned_orders,
                SUM(CASE WHEN {$expr['reconciliation_state']} = 'minor' THEN 1 ELSE 0 END) as minor_orders,
                SUM(CASE WHEN {$expr['reconciliation_state']} = 'material' THEN 1 ELSE 0 END) as material_orders,
                COALESCE(SUM(ABS({$expr['profit_delta']})), 0) as total_profit_delta_abs,
                COALESCE(SUM(ABS({$expr['deduction_delta']})), 0) as total_deduction_delta_abs
            ")
            ->first();

        return [
            'total_orders' => (int) ($rawStats->total_orders ?? 0),
            'waiting_orders' => (int) ($rawStats->waiting_orders ?? 0),
            'snapshot_missing_orders' => (int) ($rawStats->snapshot_missing_orders ?? 0),
            'aligned_orders' => (int) ($rawStats->aligned_orders ?? 0),
            'minor_orders' => (int) ($rawStats->minor_orders ?? 0),
            'material_orders' => (int) ($rawStats->material_orders ?? 0),
            'total_profit_delta_abs' => (float) ($rawStats->total_profit_delta_abs ?? 0),
            'total_deduction_delta_abs' => (float) ($rawStats->total_deduction_delta_abs ?? 0),
        ];
    }

    #[Computed]
    public function healthStats(): array
    {
        $syncQuery = IntegrationSyncRun::query()->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()));
        $pushQuery = IntegrationPushRun::query()->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()));
        $actionQuery = IntegrationOrderActionRun::query()->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()));
        $webhookQuery = IntegrationWebhookEvent::query()->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()));
        $issueQuery = ProductMatchIssue::query()->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()));
        $eventQuery = OrderFinancialEvent::query()->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()));

        return [
            'completed_syncs' => (clone $syncQuery)->where('created_at', '>=', now()->subDay())->where('status', 'completed')->count(),
            'failed_syncs' => (clone $syncQuery)->where('created_at', '>=', now()->subDay())->where('status', 'failed')->count(),
            'processing_syncs' => (clone $syncQuery)->whereIn('status', ['queued', 'processing'])->count(),
            'queued_pushes' => (clone $pushQuery)->whereIn('status', ['queued', 'processing', 'retrying'])->count(),
            'failed_pushes' => (clone $pushQuery)->where('created_at', '>=', now()->subDay())->where('status', 'failed')->count(),
            'failed_actions' => (clone $actionQuery)->where('created_at', '>=', now()->subDay())->where('status', 'failed')->count(),
            'queued_actions' => (clone $actionQuery)->whereIn('status', ['queued', 'processing', 'retrying'])->count(),
            'failed_webhooks' => (clone $webhookQuery)->where('created_at', '>=', now()->subDay())->whereIn('status', ['failed'])->count(),
            'open_match_issues' => (clone $issueQuery)->where('match_status', 'pending')->count(),
            'pending_financial_events' => (clone $eventQuery)
                ->where(function (Builder $query) {
                    $query->whereNull('settlement_date')
                        ->orWhereNotIn('status', ['posted', 'completed', 'settled']);
                })
                ->count(),
        ];
    }

    #[Computed]
    public function connectionReadinessSummary(): array
    {
        $stores = MarketplaceStore::query()
            ->with(['connection', 'syncProfile', 'legalEntity'])
            ->where('user_id', $this->userId())
            ->orderBy('store_name')
            ->get();

        return app(MarketplaceConnectionReadinessService::class)->inspectCollection($stores);
    }

    #[Computed]
    public function diagnosticsSummary(): array
    {
        return app(MarketplaceDiagnosticsReportService::class)->summaryForUser($this->userId(), [
            'hours' => 168,
            'limit' => 200,
        ]);
    }

    #[Computed]
    public function diagnosticsGuidance(): array
    {
        return app(MarketplaceDiagnosticsGuidanceService::class)->guidanceForUser($this->userId(), [
            'hours' => 168,
            'limit' => 200,
        ]);
    }

    #[Computed]
    public function legacyProjectionSummary(): array
    {
        return app(LegacyFinancialProjectionInsightsService::class)->summaryForUser($this->userId());
    }

    #[Computed]
    public function legacyProjectionStoreRows(): array
    {
        $service = app(LegacyFinancialProjectionInsightsService::class);

        return MarketplaceStore::query()
            ->with('legalEntity:id,name')
            ->where('user_id', $this->userId())
            ->where('is_active', true)
            ->orderBy('store_name')
            ->get(['id', 'legal_entity_id', 'marketplace', 'store_name'])
            ->map(function (MarketplaceStore $store) use ($service) {
                $summary = $service->summaryForUser($this->userId(), $store->id, $store->legal_entity_id);

                return [
                    'store_id' => (int) $store->id,
                    'store_name' => $store->store_name,
                    'marketplace' => $store->marketplace,
                    'legal_entity_name' => $store->legalEntity?->name,
                    'pending_rows' => (int) ($summary['pending_rows'] ?? 0),
                    'projected_rows' => (int) ($summary['projected_rows'] ?? 0),
                    'legacy_event_orders' => (int) ($summary['legacy_event_orders'] ?? 0),
                    'confirmed_orders' => (int) ($summary['confirmed_orders'] ?? 0),
                    'last_projected_at' => $summary['last_projected_at'] ?? null,
                ];
            })
            ->filter(fn (array $row) => $row['pending_rows'] > 0 || $row['projected_rows'] > 0 || $row['legacy_event_orders'] > 0 || $row['confirmed_orders'] > 0)
            ->sortByDesc(fn (array $row) => ($row['pending_rows'] * 1000000) + ($row['confirmed_orders'] * 1000) + $row['projected_rows'])
            ->values()
            ->all();
    }

    #[Computed]
    public function pilotRolloutRows(): array
    {
        $readinessRows = collect($this->connectionReadinessSummary()['rows'] ?? [])
            ->keyBy('store_id');

        $legacyRows = collect($this->legacyProjectionStoreRows())
            ->keyBy('store_id');

        $guidanceRows = collect($this->diagnosticsGuidance()['items'] ?? [])
            ->filter(fn (array $item) => filled($item['store_id'] ?? null))
            ->groupBy('store_id')
            ->map(fn ($items) => $items->first());

        $smokeRuns = IntegrationSyncRun::query()
            ->with('store:id,store_name,marketplace')
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()))
            ->where('trigger_type', 'smoke_test')
            ->latest('created_at')
            ->limit(50)
            ->get()
            ->unique('store_id')
            ->keyBy('store_id');

        return MarketplaceStore::query()
            ->with('legalEntity:id,name')
            ->where('user_id', $this->userId())
            ->where('is_active', true)
            ->orderBy('store_name')
            ->get(['id', 'legal_entity_id', 'marketplace', 'store_name'])
            ->map(function (MarketplaceStore $store) use ($readinessRows, $legacyRows, $guidanceRows, $smokeRuns) {
                $readiness = $readinessRows->get($store->id, [
                    'state' => 'missing',
                    'summary' => 'Hazırlık analizi yok.',
                    'first_failure' => null,
                    'first_warning' => null,
                ]);
                $legacy = $legacyRows->get($store->id, [
                    'pending_rows' => 0,
                    'projected_rows' => 0,
                    'legacy_event_orders' => 0,
                    'confirmed_orders' => 0,
                    'last_projected_at' => null,
                ]);
                $guidance = $guidanceRows->get($store->id);
                $smokeRun = $smokeRuns->get($store->id);

                $smokeStatus = $smokeRun?->status;
                $smokeWarnings = (int) ($smokeRun?->diagnosticWarningCount() ?? 0);
                $stage = match (true) {
                    ($readiness['state'] ?? 'missing') === 'missing' => 'credentials_missing',
                    ($readiness['state'] ?? null) === 'warning' => 'readiness_review',
                    !$smokeRun => 'smoke_pending',
                    $smokeStatus === 'failed' => 'smoke_failed',
                    $smokeWarnings > 0 => 'mapping_hardening',
                    (int) ($legacy['pending_rows'] ?? 0) > 0 => 'legacy_projection',
                    default => 'pilot_ready',
                };

                return [
                    'store_id' => (int) $store->id,
                    'store_name' => $store->store_name,
                    'marketplace' => $store->marketplace,
                    'legal_entity_name' => $store->legalEntity?->name,
                    'readiness_state' => (string) ($readiness['state'] ?? 'missing'),
                    'readiness_summary' => (string) ($readiness['first_failure'] ?: ($readiness['first_warning'] ?: ($readiness['summary'] ?? 'Hazırlık analizi yok.'))),
                    'smoke_status' => $smokeStatus,
                    'smoke_warning_count' => $smokeWarnings,
                    'smoke_last_error' => (string) data_get($smokeRun?->notes_json, 'last_error', ''),
                    'smoke_ran_at' => $smokeRun?->created_at,
                    'legacy_pending_rows' => (int) ($legacy['pending_rows'] ?? 0),
                    'legacy_confirmed_orders' => (int) ($legacy['confirmed_orders'] ?? 0),
                    'legacy_projected_rows' => (int) ($legacy['projected_rows'] ?? 0),
                    'last_projected_at' => $legacy['last_projected_at'] ?? null,
                    'guidance_title' => (string) ($guidance['title'] ?? ''),
                    'guidance_severity' => $guidance['severity'] ?? null,
                    'guidance_route' => $guidance ? $this->guidanceRoute($guidance) : route('mp.integrations', ['store' => $store->id]),
                    'guidance_action' => (string) ($guidance['recommended_action'] ?? ''),
                    'stage' => $stage,
                    'priority_score' => $this->pilotRolloutPriorityScore($stage, $smokeWarnings, (int) ($legacy['pending_rows'] ?? 0), (int) ($legacy['confirmed_orders'] ?? 0)),
                ];
            })
            ->sortByDesc('priority_score')
            ->values()
            ->all();
    }

    #[Computed]
    public function moduleStats(): array
    {
        $productQuery = MpProduct::query()->where('user_id', $this->userId());
        $storeQuery = MarketplaceStore::query()->where('user_id', $this->userId());
        $snapshotQuery = OrderProfitSnapshot::query()
            ->whereNull('channel_order_item_id')
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()));

        return [
            'master_products' => (clone $productQuery)->count(),
            'listed_products' => (clone $productQuery)->has('channelListings')->count(),
            'unlisted_products' => (clone $productQuery)->doesntHave('channelListings')->count(),
            'price_push_ready' => (clone $storeQuery)->whereHas('syncProfile', fn (Builder $query) => $query->where('price_push_enabled', true))->count(),
            'stock_push_ready' => (clone $storeQuery)->whereHas('syncProfile', fn (Builder $query) => $query->where('stock_push_enabled', true))->count(),
            'confirmed_snapshots' => (clone $snapshotQuery)->where('profit_state', 'confirmed')->count(),
            'estimated_snapshots' => (clone $snapshotQuery)->where('profit_state', 'estimated')->count(),
        ];
    }

    #[Computed]
    public function legalEntitySummary()
    {
        return LegalEntity::query()
            ->where('user_id', $this->userId())
            ->where('is_active', true)
            ->withCount([
                'stores',
                'stores as active_stores_count' => fn (Builder $query) => $query->where('is_active', true),
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'tax_number']);
    }

    #[Computed]
    public function recentSyncRuns()
    {
        return IntegrationSyncRun::query()
            ->with('store:id,store_name,marketplace')
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()))
            ->where('trigger_type', '!=', 'smoke_test')
            ->latest('created_at')
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function recentSmokeRuns()
    {
        return IntegrationSyncRun::query()
            ->with('store:id,store_name,marketplace')
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()))
            ->where('trigger_type', 'smoke_test')
            ->latest('created_at')
            ->limit(6)
            ->get();
    }

    #[Computed]
    public function recentPushRuns()
    {
        return IntegrationPushRun::query()
            ->with(['store:id,store_name,marketplace', 'listing:id,listing_id'])
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()))
            ->latest('created_at')
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function recentActionRuns()
    {
        return IntegrationOrderActionRun::query()
            ->with(['store:id,store_name,marketplace', 'order:id,order_number', 'triggeredBy:id,name'])
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()))
            ->latest('created_at')
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function recentWebhookEvents()
    {
        return IntegrationWebhookEvent::query()
            ->with('store:id,store_name,marketplace')
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()))
            ->latest('created_at')
            ->limit(8)
            ->get();
    }

    public function retrySyncRun(int $runId): void
    {
        $run = IntegrationSyncRun::query()
            ->whereKey($runId)
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()))
            ->firstOrFail();

        $retryRun = app(MarketplaceHealthRetryService::class)->retrySync($run);

        $this->notify("Sync tekrar kuyruğa alındı. Yeni kayıt #{$retryRun->id}");
    }

    public function retryPushRun(int $runId): void
    {
        $run = IntegrationPushRun::query()
            ->whereKey($runId)
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()))
            ->firstOrFail();

        $result = app(MarketplaceHealthRetryService::class)->retryPushDetailed($run, $this->userId());
        $retryRun = $result['push_run'];

        if ($result['coalesced']) {
            $this->notify("Push işlemi bekleyen kayıt üzerinde güncellendi. Kayıt #{$retryRun->id}");

            return;
        }

        if ($result['busy']) {
            $this->notify("Push işlemi zaten çalışıyor. Mevcut kayıt #{$retryRun->id}", 'info');

            return;
        }

        if ($result['recent']) {
            $this->notify("Push işlemi çok yeni tamamlandığı için yeni kayıt açılmadı. Kayıt #{$retryRun->id}", 'info');

            return;
        }

        $this->notify("Push işlemi tekrar kuyruğa alındı. İşlem no #{$retryRun->id}");
    }

    public function retryOrderActionRun(int $runId): void
    {
        $run = IntegrationOrderActionRun::query()
            ->with('package')
            ->whereKey($runId)
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()))
            ->firstOrFail();

        $result = app(MarketplaceHealthRetryService::class)->retryOrderActionDetailed($run, $this->userId());
        $retryRun = $result['action_run'];

        if ($result['coalesced']) {
            $this->notify("Sipariş aksiyonu bekleyen kayıt üzerinde güncellendi. Kayıt #{$retryRun->id}");

            return;
        }

        if ($result['busy']) {
            $this->notify("Sipariş aksiyonu zaten çalışıyor. Mevcut kayıt #{$retryRun->id}", 'info');

            return;
        }

        if ($result['recent']) {
            $this->notify("Sipariş aksiyonu çok yeni tamamlandığı için yeni kayıt açılmadı. Kayıt #{$retryRun->id}", 'info');

            return;
        }

        $this->notify("Sipariş aksiyonu tekrar kuyruğa alındı. İşlem no #{$retryRun->id}");
    }

    public function replayWebhookEvent(int $eventId): void
    {
        $event = IntegrationWebhookEvent::query()
            ->whereKey($eventId)
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()))
            ->firstOrFail();

        $syncRun = app(MarketplaceHealthRetryService::class)->replayWebhook($event);

        $this->notify("Webhook yeniden işlendi. Senkron kayıt no #{$syncRun->id}");
    }

    public function retryFailedSyncs(): void
    {
        $runs = $this->failedSyncRunQuery()
            ->latest('created_at')
            ->limit(25)
            ->get();

        if ($runs->isEmpty()) {
            $this->notify('Tekrar denenebilecek hatalı senkron kaydı bulunamadı.', 'info');

            return;
        }

        $retried = app(MarketplaceHealthRetryService::class)->retrySyncBatch($runs);

        $this->notify($retried->count() . ' hatalı senkron tekrar kuyruğa alındı.');
    }

    public function retryFailedPushes(): void
    {
        $runs = $this->failedPushRunQuery()
            ->latest('created_at')
            ->limit(25)
            ->get();

        if ($runs->isEmpty()) {
            $this->notify('Tekrar denenebilecek hatalı gönderim kaydı bulunamadı.', 'info');

            return;
        }

        $retried = app(MarketplaceHealthRetryService::class)->retryPushBatchDetailed($runs, $this->userId());

        $parts = [];

        if ($retried['created'] > 0) {
            $parts[] = $retried['created'] . ' gönderim yeniden kuyruğa alındı';
        }

        if ($retried['coalesced'] > 0) {
            $parts[] = $retried['coalesced'] . ' gönderim bekleyen kayıt üzerinde güncellendi';
        }

        if ($retried['busy'] > 0) {
            $parts[] = $retried['busy'] . ' gönderim zaten çalışıyordu';
        }

        if ($retried['recent'] > 0) {
            $parts[] = $retried['recent'] . ' gönderim çok yeni tamamlandığı için atlandı';
        }

        $this->notify(implode('. ', $parts) . '.', ($retried['created'] + $retried['coalesced']) > 0 ? 'success' : 'info');
    }

    public function retryFailedOrderActions(): void
    {
        $runs = $this->failedActionRunQuery()
            ->with('package')
            ->latest('created_at')
            ->limit(25)
            ->get();

        if ($runs->isEmpty()) {
            $this->notify('Tekrar denenebilecek hatalı sipariş aksiyonu bulunamadı.', 'info');

            return;
        }

        $retried = app(MarketplaceHealthRetryService::class)->retryOrderActionBatchDetailed($runs, $this->userId());

        $parts = [];

        if ($retried['created'] > 0) {
            $parts[] = $retried['created'] . ' sipariş aksiyonu yeniden kuyruğa alındı';
        }

        if ($retried['coalesced'] > 0) {
            $parts[] = $retried['coalesced'] . ' sipariş aksiyonu bekleyen kayıt üzerinde güncellendi';
        }

        if ($retried['busy'] > 0) {
            $parts[] = $retried['busy'] . ' sipariş aksiyonu zaten çalışıyordu';
        }

        if ($retried['recent'] > 0) {
            $parts[] = $retried['recent'] . ' sipariş aksiyonu çok yeni tamamlandığı için atlandı';
        }

        $this->notify(implode('. ', $parts) . '.', ($retried['created'] + $retried['coalesced']) > 0 ? 'success' : 'info');
    }

    public function replayFailedWebhooks(): void
    {
        $events = $this->failedWebhookEventQuery()
            ->latest('created_at')
            ->limit(25)
            ->get();

        if ($events->isEmpty()) {
            $this->notify('Yeniden işlenecek hatalı webhook kaydı bulunamadı.', 'info');

            return;
        }

        $replayed = app(MarketplaceHealthRetryService::class)->replayWebhookBatch($events);

        $this->notify($replayed->count() . ' hatalı webhook yeniden işleme kuyruğuna alındı.');
    }

    public function repairFailedOperations(): void
    {
        $retriedSyncs = app(MarketplaceHealthRetryService::class)->retrySyncBatch(
            $this->failedSyncRunQuery()->latest('created_at')->limit(15)->get()
        );
        $retriedPushes = app(MarketplaceHealthRetryService::class)->retryPushBatchDetailed(
            $this->failedPushRunQuery()->latest('created_at')->limit(15)->get(),
            $this->userId()
        );
        $retriedActions = app(MarketplaceHealthRetryService::class)->retryOrderActionBatchDetailed(
            $this->failedActionRunQuery()->with('package')->latest('created_at')->limit(15)->get(),
            $this->userId()
        );
        $replayedWebhooks = app(MarketplaceHealthRetryService::class)->replayWebhookBatch(
            $this->failedWebhookEventQuery()->latest('created_at')->limit(15)->get()
        );

        $total = $retriedSyncs->count() + $retriedPushes['runs']->count() + $retriedActions['runs']->count() + $replayedWebhooks->count();

        if ($total === 0) {
            $this->notify('Toplu onarım için bekleyen hatalı kayıt bulunamadı.', 'info');

            return;
        }

        $this->notify(
            "Toplu onarım başlatıldı: {$retriedSyncs->count()} senkron, {$retriedPushes['created']} yeni gönderim, {$retriedPushes['coalesced']} güncellenen gönderim, {$retriedActions['created']} yeni aksiyon, {$retriedActions['coalesced']} güncellenen aksiyon, {$replayedWebhooks->count()} webhook."
        );
    }

    public function humanMarketplace(?string $marketplace): string
    {
        return (string) (MarketplaceProviderRegistry::get((string) $marketplace)['label'] ?? ucfirst((string) $marketplace));
    }

    public function syncStatusTone(?string $status): string
    {
        return match ($status) {
            'completed' => 'success',
            'failed' => 'danger',
            'processing', 'queued' => 'warning',
            default => 'default',
        };
    }

    public function pushStatusTone(?string $status): string
    {
        return match ($status) {
            'completed' => 'success',
            'failed' => 'danger',
            'retrying' => 'warning',
            'processing', 'queued' => 'info',
            default => 'default',
        };
    }

    public function actionStatusTone(?string $status): string
    {
        return match ($status) {
            'completed' => 'success',
            'failed' => 'danger',
            'retrying' => 'warning',
            'processing', 'queued' => 'info',
            default => 'default',
        };
    }

    public function actionStatusLabel(?string $status): string
    {
        return match ($status) {
            'queued' => 'Sırada',
            'processing' => 'İşleniyor',
            'completed' => 'Tamamlandı',
            'failed' => 'Hata',
            'retrying' => 'Tekrar denenecek',
            'replayed' => 'Yeniden işlendi',
            default => Str::headline((string) $status),
        };
    }

    public function orderActionLabel(?string $actionType): string
    {
        return MarketplaceOrderActionService::ACTION_LABELS[$actionType] ?? Str::headline((string) $actionType);
    }

    public function syncTypeLabel(?string $syncType): string
    {
        return match ($syncType) {
            'orders' => 'Sipariş',
            'products' => 'Ürün',
            'finance' => 'Finans',
            'webhook_refresh' => 'Webhook yenileme',
            default => Str::headline((string) $syncType),
        };
    }

    public function webhookStatusTone(?string $status): string
    {
        return match ($status) {
            'processed', 'completed', 'replayed' => 'success',
            'failed' => 'danger',
            'debounced' => 'info',
            'ignored' => 'default',
            'received' => 'warning',
            default => 'default',
        };
    }

    public function webhookStatusLabel(?string $status): string
    {
        return match ($status) {
            'received' => 'Alındı',
            'debounced' => 'Debounce',
            'ignored' => 'Filtrelendi',
            'processed' => 'İşlendi',
            'replayed' => 'Yeniden işlendi',
            'failed' => 'Hata',
            default => Str::headline((string) $status),
        };
    }

    public function exportHealthReportCsv()
    {
        $filename = 'pazaryeri_saglik_raporu_' . now()->format('Ymd_His') . '.csv';
        $heroStats = $this->heroStats();
        $reconciliationStats = $this->reconciliationStats();
        $healthStats = $this->healthStats();
        $readinessSummary = $this->connectionReadinessSummary();
        $recentSmokeRuns = $this->recentSmokeRuns();

        return response()->stream(function () use ($filename, $heroStats, $reconciliationStats, $healthStats, $readinessSummary, $recentSmokeRuns) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF");

            fputcsv($file, ['Bölüm', 'Metrik', 'Değer', 'Not'], ';');

            $summaryRows = [
                ['Özet', 'Aktif mağaza', $heroStats['active_stores'], $heroStats['total_stores'] . ' toplam bağlantı'],
                ['Özet', 'Toplam sipariş', $heroStats['total_orders'], $heroStats['finance_ready_orders'] . ' siparişte finans hazır'],
                ['Özet', 'Toplam listing', $heroStats['total_listings'], $heroStats['active_listings'] . ' yayında'],
                ['Özet', 'Net alacak', $heroStats['net_receivable'], 'Kesin kâr ' . $heroStats['confirmed_profit']],
                ['Mutabakat', 'Materyal fark', $reconciliationStats['material_orders'], $reconciliationStats['minor_orders'] . ' sipariş izleme alanında'],
                ['Mutabakat', 'Brüt kâr farkı', $reconciliationStats['total_profit_delta_abs'], $reconciliationStats['snapshot_missing_orders'] . ' snapshot eksik'],
                ['Sağlık', 'Hatalı senkron', $healthStats['failed_syncs'], 'Son 24 saat'],
                ['Sağlık', 'Gönderim hatası', $healthStats['failed_pushes'], 'Son 24 saat'],
                ['Sağlık', 'Aksiyon hatası', $healthStats['failed_actions'], 'Son 24 saat'],
                ['Sağlık', 'Webhook hatası', $healthStats['failed_webhooks'], 'Son 24 saat'],
                ['Sağlık', 'Açık eşleşme sorunu', $healthStats['open_match_issues'], 'Bekleyen eşleştirme'],
            ];

            foreach ($summaryRows as [$section, $metric, $value, $note]) {
                fputcsv($file, [
                    $this->cleanExportString($section),
                    $this->cleanExportString($metric),
                    is_numeric($value) ? $value : $this->cleanExportString($value),
                    $this->cleanExportString($note),
                ], ';');
            }

            fputcsv($file, [], ';');
            fputcsv($file, ['Hazırlık', 'Mağaza', 'Pazaryeri', 'Durum', 'Özet'], ';');

            foreach ($readinessSummary['rows'] as $row) {
                fputcsv($file, [
                    'Hazırlık',
                    $this->cleanExportString($row['store_name']),
                    $this->cleanExportString($this->humanMarketplace($row['marketplace'])),
                    $this->cleanExportString($this->readinessStateLabel($row['state'])),
                    $this->cleanExportString($row['first_failure'] ?: ($row['first_warning'] ?: $row['summary'])),
                ], ';');
            }

            fputcsv($file, [], ';');
            fputcsv($file, ['Ön Test', 'Mağaza', 'Senkron Türü', 'Durum', 'Uyarı Sayısı', 'Son Hata'], ';');

            foreach ($recentSmokeRuns as $run) {
                fputcsv($file, [
                    'Ön Test',
                    $this->cleanExportString($run->store?->store_name),
                    $this->cleanExportString($this->syncTypeLabel($run->sync_type)),
                    $this->cleanExportString($this->actionStatusLabel($run->status)),
                    $run->diagnosticWarningCount(),
                    $this->cleanExportString(data_get($run->notes_json, 'last_error')),
                ], ';');
            }

            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function exportFailureReportCsv()
    {
        $filename = 'pazaryeri_hata_raporu_' . now()->format('Ymd_His') . '.csv';
        $failedSyncRuns = $this->failedSyncRunQuery()
            ->with('store:id,store_name,marketplace')
            ->latest('created_at')
            ->limit(100)
            ->get();
        $failedPushRuns = $this->failedPushRunQuery()
            ->with(['store:id,store_name,marketplace', 'listing:id,listing_id'])
            ->latest('created_at')
            ->limit(100)
            ->get();
        $failedActionRuns = $this->failedActionRunQuery()
            ->with(['store:id,store_name,marketplace', 'order:id,order_number'])
            ->latest('created_at')
            ->limit(100)
            ->get();
        $failedWebhookEvents = $this->failedWebhookEventQuery()
            ->with('store:id,store_name,marketplace')
            ->latest('created_at')
            ->limit(100)
            ->get();

        return response()->stream(function () use ($failedSyncRuns, $failedPushRuns, $failedActionRuns, $failedWebhookEvents) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF");

            fputcsv($file, ['Tür', 'Mağaza', 'Pazaryeri', 'Kayıt', 'Durum', 'Tarih', 'Detay', 'Hata'], ';');

            foreach ($failedSyncRuns as $run) {
                fputcsv($file, [
                    'Senkron',
                    $this->cleanExportString($run->store?->store_name),
                    $this->cleanExportString($this->humanMarketplace($run->store?->marketplace)),
                    $this->cleanExportString($this->syncTypeLabel($run->sync_type)),
                    $this->cleanExportString($this->actionStatusLabel($run->status)),
                    $run->created_at?->format('d.m.Y H:i:s'),
                    $this->cleanExportString($run->triggerLabel()),
                    $this->cleanExportString((string) data_get($run->notes_json, 'last_error')),
                ], ';');
            }

            foreach ($failedPushRuns as $run) {
                fputcsv($file, [
                    'Gönderim',
                    $this->cleanExportString($run->store?->store_name),
                    $this->cleanExportString($this->humanMarketplace($run->store?->marketplace)),
                    $this->cleanExportString($run->push_type === 'price' ? 'Fiyat gönderimi' : 'Stok gönderimi'),
                    $this->cleanExportString($this->actionStatusLabel($run->status)),
                    $run->created_at?->format('d.m.Y H:i:s'),
                    $this->cleanExportString((string) ($run->listing?->listing_id ?: 'Listeleme yok')),
                    $this->cleanExportString($run->error_message),
                ], ';');
            }

            foreach ($failedActionRuns as $run) {
                fputcsv($file, [
                    'Sipariş aksiyonu',
                    $this->cleanExportString($run->store?->store_name),
                    $this->cleanExportString($this->humanMarketplace($run->store?->marketplace)),
                    $this->cleanExportString($this->orderActionLabel($run->action_type)),
                    $this->cleanExportString($this->actionStatusLabel($run->status)),
                    $run->created_at?->format('d.m.Y H:i:s'),
                    $this->cleanExportString((string) ($run->order?->order_number ?: 'Sipariş yok')),
                    $this->cleanExportString($run->error_message),
                ], ';');
            }

            foreach ($failedWebhookEvents as $event) {
                fputcsv($file, [
                    'Webhook',
                    $this->cleanExportString($event->store?->store_name),
                    $this->cleanExportString($this->humanMarketplace($event->store?->marketplace)),
                    $this->cleanExportString((string) Str::headline((string) $event->event_type)),
                    $this->cleanExportString($this->webhookStatusLabel($event->status)),
                    $event->created_at?->format('d.m.Y H:i:s'),
                    $this->cleanExportString((string) ($event->external_event_id ?: 'Olay ID yok')),
                    $this->cleanExportString($event->error_message),
                ], ';');
            }

            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function exportDiagnosticsReportCsv()
    {
        $filename = 'pazaryeri_diagnostik_raporu_' . now()->format('Ymd_His') . '.csv';
        $summary = $this->diagnosticsSummary();

        return response()->stream(function () use ($summary) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF");

            fputcsv($file, ['Mağaza', 'Pazaryeri', 'Tip', 'Toplam Çalıştırma', 'Ön Test Çalıştırması', 'Uyarılı Çalıştırma', 'Toplam Uyarı', 'Eksik Sipariş No', 'Eksik Paket ID', 'Eksik Satır ID', 'Eksik Stok Kodu', 'Eksik Barkod', 'Eksik Tutar', 'Eksik Ödeme Tarihi', 'Eksik Listeleme ID', 'Eksik Satış Fiyatı', 'Eksik Stok Miktarı', 'Öne Çıkan Uyarı'], ';');

            foreach ($summary['rows'] as $row) {
                fputcsv($file, [
                    $this->cleanExportString($row['store_name']),
                    $this->cleanExportString($this->humanMarketplace($row['marketplace'])),
                    $this->cleanExportString($this->syncTypeLabel($row['sync_type'])),
                    $row['total_runs'],
                    $row['smoke_runs'],
                    $row['warning_runs'],
                    $row['total_warning_count'],
                    $row['missing_order_number_count'],
                    $row['missing_package_id_count'],
                    ($row['missing_item_line_id_count'] + $row['missing_line_id_count']),
                    $row['missing_stock_code_count'],
                    $row['missing_barcode_count'],
                    $row['missing_amount_count'],
                    $row['missing_settlement_date_count'],
                    $row['missing_listing_id_count'],
                    $row['missing_sale_price_count'],
                    $row['missing_stock_quantity_count'],
                    $this->cleanExportString($row['top_warning']),
                ], ';');
            }

            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function exportDiagnosticsGuidanceCsv()
    {
        $filename = 'pazaryeri_karar_destegi_' . now()->format('Ymd_His') . '.csv';
        $guidance = $this->diagnosticsGuidance();

        return response()->stream(function () use ($guidance) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF");

            fputcsv($file, ['Mağaza', 'Pazaryeri', 'Tip', 'Kategori', 'Seviye', 'Etkilenen', 'Başlık', 'Önerilen aksiyon', 'Neden önemli', 'Yönlenecek ekran'], ';');

            foreach ($guidance['items'] as $item) {
                fputcsv($file, [
                    $this->cleanExportString($item['store_name']),
                    $this->cleanExportString($this->humanMarketplace($item['marketplace'])),
                    $this->cleanExportString($this->syncTypeLabel($item['sync_type'])),
                    $this->cleanExportString($this->guidanceCategoryLabel($item['category'])),
                    $this->cleanExportString($this->guidanceSeverityLabel($item['severity'])),
                    $item['impact_count'],
                    $this->cleanExportString($item['title']),
                    $this->cleanExportString($item['recommended_action']),
                    $this->cleanExportString($item['why']),
                    $this->cleanExportString($this->guidanceRouteLabel($item['route'])),
                ], ';');
            }

            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function exportLegacyProjectionCsv()
    {
        $filename = 'pazaryeri_legacy_projection_' . now()->format('Ymd_His') . '.csv';
        $summary = $this->legacyProjectionSummary();
        $storeRows = $this->legacyProjectionStoreRows();

        return response()->stream(function () use ($summary, $storeRows) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF");

            fputcsv($file, ['Bölüm', 'Metrik', 'Değer', 'Not'], ';');

            $summaryRows = [
                ['Eski Veri Yansıtması', 'Bekleyen eski veri satırı', $summary['pending_rows'], 'Yansıtma bekleyen finans kuyruğu'],
                ['Eski Veri Yansıtması', 'Yansıtması tamamlanan', $summary['projected_rows'], 'Yeni kayıt defterine taşınan eski veri finans satırı'],
                ['Eski Veri Yansıtması', 'Eski veri olay siparişi', $summary['legacy_event_orders'], 'Eski veri kaynaklı finans olayı taşıyan sipariş'],
                ['Eski Veri Yansıtması', 'Kesine dönen sipariş', $summary['confirmed_orders'], 'Eski veri yansıtması sonrası kesin anlık kayıt alan sipariş'],
                ['Eski Veri Yansıtması', 'Son yansıtma', $summary['last_projected_at'] ?: 'Henüz yansıtma yapılmadı', 'Son başarılı yansıtma zamanı'],
            ];

            foreach ($summaryRows as [$section, $metric, $value, $note]) {
                fputcsv($file, [
                    $this->cleanExportString($section),
                    $this->cleanExportString($metric),
                    is_numeric($value) ? $value : $this->cleanExportString($value),
                    $this->cleanExportString($note),
                ], ';');
            }

            fputcsv($file, [], ';');
            fputcsv($file, ['Mağaza', 'Pazaryeri', 'Firma', 'Bekleyen', 'Yansıtılan', 'Eski Veri Olay Siparişi', 'Kesine Dönen', 'Son Yansıtma'], ';');

            foreach ($storeRows as $row) {
                fputcsv($file, [
                    $this->cleanExportString($row['store_name']),
                    $this->cleanExportString($this->humanMarketplace($row['marketplace'])),
                    $this->cleanExportString($row['legal_entity_name']),
                    $row['pending_rows'],
                    $row['projected_rows'],
                    $row['legacy_event_orders'],
                    $row['confirmed_orders'],
                    $this->cleanExportString($row['last_projected_at'] ? \Illuminate\Support\Carbon::parse($row['last_projected_at'])->format('d.m.Y H:i:s') : 'Henüz yok'),
                ], ';');
            }

            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function exportPilotRolloutCsv()
    {
        $filename = 'pazaryeri_pilot_rollout_' . now()->format('Ymd_His') . '.csv';
        $rows = $this->pilotRolloutRows;

        return response()->stream(function () use ($rows) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF");

            fputcsv($file, ['Mağaza', 'Pazaryeri', 'Firma', 'Pilot Durumu', 'Hazırlık', 'Son Ön Test', 'Ön Test Uyarısı', 'Eski Veri Bekleyen', 'Eski Veri Kesin', 'İlk Öneri', 'Önerilen Aksiyon', 'Ön Test Komutu'], ';');

            foreach ($rows as $row) {
                fputcsv($file, [
                    $this->cleanExportString($row['store_name']),
                    $this->cleanExportString($this->humanMarketplace($row['marketplace'])),
                    $this->cleanExportString($row['legal_entity_name']),
                    $this->cleanExportString($this->pilotRolloutStageLabel($row['stage'])),
                    $this->cleanExportString($this->readinessStateLabel($row['readiness_state'])),
                    $this->cleanExportString($this->pilotRolloutSmokeLabel($row)),
                    $row['smoke_warning_count'],
                    $row['legacy_pending_rows'],
                    $row['legacy_confirmed_orders'],
                    $this->cleanExportString($row['guidance_title']),
                    $this->cleanExportString($row['guidance_action']),
                    $this->cleanExportString($this->pilotSmokeCommand($row)),
                ], ';');
            }

            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function previewLegacyProjection(int $storeId): void
    {
        $store = MarketplaceStore::query()
            ->with('legalEntity')
            ->whereKey($storeId)
            ->where('user_id', $this->userId())
            ->firstOrFail();

        $preview = app(LegacyFinancialProjectionService::class)->previewStore($store, true);

        $this->legacyProjectionPreviews[$storeId] = $preview + [
            'store_name' => $store->store_name,
            'generated_at' => now()->toDateTimeString(),
        ];

        $this->notify($store->store_name . ' için eski veri yansıtma ön izlemesi hazırlandı: ' . ($preview['projected_rows'] ?? 0) . ' aday satır.', 'success');
    }

    public function runLegacyProjection(int $storeId): void
    {
        $store = MarketplaceStore::query()
            ->with('legalEntity')
            ->whereKey($storeId)
            ->where('user_id', $this->userId())
            ->firstOrFail();

        $preview = app(LegacyFinancialProjectionService::class)->previewStore($store, true);

        if ((int) ($preview['projected_rows'] ?? 0) === 0) {
            $this->legacyProjectionPreviews[$storeId] = $preview + [
                'store_name' => $store->store_name,
                'generated_at' => now()->toDateTimeString(),
            ];

            $this->notify($store->store_name . ' için taşınacak eski veri finans satırı bulunamadı.', 'info');

            return;
        }

        $result = app(LegacyFinancialProjectionService::class)->projectStore($store, true);

        $this->legacyProjectionPreviews[$storeId] = [
            'projected_rows' => (int) ($result['projected_rows'] ?? 0),
            'created' => (int) ($result['created'] ?? 0),
            'updated' => (int) ($result['updated'] ?? 0),
            'skipped' => (int) ($result['skipped'] ?? 0),
            'impacted_orders' => count($result['impacted_order_ids'] ?? []),
            'store_name' => $store->store_name,
            'generated_at' => now()->toDateTimeString(),
            'executed' => true,
        ];

        $this->notify(
            $store->store_name . ' için eski veri yansıtması tamamlandı. '
            . ($result['projected_rows'] ?? 0) . ' satır işlendi, '
            . ($result['created'] ?? 0) . ' yeni olay, '
            . ($result['updated'] ?? 0) . ' güncelleme.',
            'success'
        );
    }

    public function notify(string $message, string $tone = 'success'): void
    {
        $this->flashMessage = $message;
        $this->flashTone = $tone;
    }

    protected function readinessStateLabel(string $state): string
    {
        return match ($state) {
            'ready' => 'Hazır',
            'warning' => 'Kontrol et',
            default => 'Eksik',
        };
    }

    public function pilotRolloutStageTone(string $stage): string
    {
        return match ($stage) {
            'credentials_missing' => 'danger',
            'readiness_review', 'smoke_failed', 'mapping_hardening', 'legacy_projection' => 'warning',
            'pilot_ready' => 'success',
            default => 'default',
        };
    }

    public function pilotRolloutStageLabel(string $stage): string
    {
        return match ($stage) {
            'credentials_missing' => 'Kimlik bilgisi eksik',
            'readiness_review' => 'Hazırlığı kontrol et',
            'smoke_pending' => 'Ön test çalıştır',
            'smoke_failed' => 'Ön test hatalı',
            'mapping_hardening' => 'Eşlemeyi sıkılaştır',
            'legacy_projection' => 'Eski veri yansıtmasını çalıştır',
            'pilot_ready' => 'Pilot hazır',
            default => 'İzle',
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function pilotRolloutSummary(array $row): string
    {
        return match ($row['stage'] ?? null) {
            'credentials_missing' => (string) ($row['readiness_summary'] ?? 'Zorunlu kimlik bilgisi alanları eksik.'),
            'readiness_review' => (string) ($row['readiness_summary'] ?? 'Hazırlık uyarılarını kapatıp ön teste geçin.'),
            'smoke_pending' => 'Mağaza hazır görünüyor. İlk ön testi bu mağazada çalıştırın.',
            'smoke_failed' => filled($row['smoke_last_error'] ?? null)
                ? 'Son ön test hata verdi: ' . $row['smoke_last_error']
                : 'Son ön test hata verdi. Kimlik bilgisi ve normalize eşleme alanlarını kontrol edin.',
            'mapping_hardening' => 'Ön test tamamlandı ama eşleme uyarıları var. Önce bunları sıkılaştırın.',
            'legacy_projection' => 'Ön test tarafı temiz. Eski veri finans kuyruğunu yansıtıp kesin etkisini doğrulayın.',
            'pilot_ready' => 'Ön test ve eski veri yansıtması tarafı temiz görünüyor. Pilot canlı test için en hazır mağaza.',
            default => 'Mağaza durumunu kontrol edin.',
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function pilotRolloutSmokeLabel(array $row): string
    {
        if (!filled($row['smoke_status'] ?? null)) {
            return 'Henüz smoke yok';
        }

        $status = match ($row['smoke_status']) {
            'completed' => 'Tamamlandı',
            'failed' => 'Hatalı',
            'processing' => 'İşleniyor',
            'queued' => 'Sırada',
            default => Str::headline((string) $row['smoke_status']),
        };

        if ((int) ($row['smoke_warning_count'] ?? 0) > 0) {
            return $status . ' · ' . $row['smoke_warning_count'] . ' uyarı';
        }

        return $status . ' · temiz';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function pilotSmokeCommand(array $row): string
    {
        return 'php artisan marketplace:smoke-test ' . $row['store_id'] . ' --type=all --hours=24 --preview=2 --persist';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function pilotRolloutIntegrationsRoute(array $row): string
    {
        return route('mp.integrations', ['store' => $row['store_id']]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function pilotRolloutOrdersRoute(array $row): string
    {
        return route('mp.orders', [
            'storeFilter' => $row['store_id'],
            'projectionStoreId' => $row['store_id'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function pilotRolloutFinanceRoute(array $row, string $mode = 'backlog'): string
    {
        return route('mp.finance', array_filter([
            'storeFilter' => $row['store_id'],
            'marketplaceFilter' => $row['marketplace'] ?? null,
            'legacyProjectionFilter' => $mode,
            'financialStateFilter' => $mode === 'confirmed' ? 'ready' : null,
        ], fn ($value) => $value !== null && $value !== ''));
    }

    public function guidanceSeverityTone(?string $severity): string
    {
        return match ($severity) {
            'critical' => 'danger',
            'warning' => 'warning',
            'info' => 'info',
            default => 'default',
        };
    }

    public function guidanceSeverityLabel(?string $severity): string
    {
        return match ($severity) {
            'critical' => 'Kritik',
            'warning' => 'Uyarı',
            'info' => 'Bilgi',
            default => Str::headline((string) $severity),
        };
    }

    public function guidanceCategoryLabel(?string $category): string
    {
        return match ($category) {
            'product_matching' => 'Ürün eşleşme',
            'order_identity' => 'Sipariş kimliği',
            'finance_mapping' => 'Finans eşleme',
            'listing_completeness' => 'Listeleme tamlığı',
            'legacy_financial_projection' => 'Eski veri finans köprüsü',
            default => Str::headline((string) $category),
        };
    }

    public function guidanceRouteLabel(?string $route): string
    {
        return match ($route) {
            'mp.matching' => 'Eşleştirme Merkezi',
            'mp.integrations' => 'Entegrasyonlar',
            'mp.finance' => 'Finans',
            'mp.products' => 'Ürünler',
            'mp.orders' => 'Siparişler',
            default => 'Kontrol Merkezi',
        };
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function guidanceRoute(array $item): string
    {
        $route = (string) ($item['route'] ?? 'mp.overview');
        $storeId = $item['store_id'] ?? null;
        $marketplace = $item['marketplace'] ?? null;

        return match ($route) {
            'mp.matching' => route('mp.matching', array_filter([
                'storeFilter' => $storeId,
                'statusFilter' => 'pending',
            ], fn ($value) => $value !== null && $value !== '')),
            'mp.integrations' => route('mp.integrations', array_filter([
                'store' => $storeId,
            ], fn ($value) => $value !== null && $value !== '')),
            'mp.finance' => route('mp.finance', array_filter([
                'storeFilter' => $storeId,
            ], fn ($value) => $value !== null && $value !== '')),
            'mp.orders' => route('mp.orders', array_filter([
                'storeFilter' => $storeId,
            ], fn ($value) => $value !== null && $value !== '')),
            'mp.products' => route('mp.products', array_filter([
                'marketplaceFilter' => $marketplace,
            ], fn ($value) => $value !== null && $value !== '' && $value !== 'all')),
            default => route('mp.overview'),
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function legacyProjectionOrdersRoute(array $row): string
    {
        return route('mp.orders', array_filter([
            'storeFilter' => $row['store_id'] ?? null,
        ], fn ($value) => $value !== null && $value !== ''));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function legacyProjectionFinanceRoute(array $row, string $mode = 'backlog'): string
    {
        return route('mp.finance', array_filter([
            'storeFilter' => $row['store_id'] ?? null,
            'marketplaceFilter' => $row['marketplace'] ?? null,
            'legacyProjectionFilter' => $mode,
            'financialStateFilter' => $mode === 'confirmed' ? 'ready' : null,
        ], fn ($value) => $value !== null && $value !== ''));
    }

    public function guidanceFocusLabel(): string
    {
        $topItem = $this->diagnosticsGuidance['items'][0] ?? null;

        if (!$topItem) {
            return 'Aksiyon yok';
        }

        return match ($topItem['route'] ?? null) {
            'mp.finance' => 'Finansa git',
            'mp.orders' => 'Siparişlere git',
            'mp.matching' => 'Eşleştirmeye git',
            'mp.products' => 'Ürünlere git',
            'mp.integrations' => 'Mağazaya git',
            default => 'Detayı aç',
        };
    }

    public function guidanceSyncLabel(): string
    {
        $topItem = $this->diagnosticsGuidance['items'][0] ?? null;

        return match ($this->resolveGuidanceSyncType($topItem)) {
            'finance' => 'Finans senkronunu başlat',
            'products' => 'Ürün senkronunu başlat',
            'legacy_projection' => 'Yansıtma ekranına git',
            default => 'Sipariş senkronunu başlat',
        };
    }

    public function focusTopGuidance()
    {
        $topItem = $this->diagnosticsGuidance['items'][0] ?? null;

        if (!$topItem) {
            $this->notify('Odaklanacak bir diagnostik öneri bulunamadı.', 'warning');

            return null;
        }

        return $this->redirect($this->guidanceRoute($topItem), navigate: true);
    }

    public function syncTopGuidance(): void
    {
        $topItem = $this->diagnosticsGuidance['items'][0] ?? null;
        $storeId = (int) ($topItem['store_id'] ?? 0);

        if ($storeId <= 0) {
            $this->notify('Senkron başlatmak için mağaza bilgisi içeren bir tanı kaydı bulunamadı.', 'warning');

            return;
        }

        $store = MarketplaceStore::query()
            ->with('connection')
            ->whereKey($storeId)
            ->where('user_id', $this->userId())
            ->first();

        if (!$store || !$store->connection || $store->connection->status === 'draft') {
            $this->notify('Önce seçili mağazanın bağlantı bilgilerini tamamlayın.', 'warning');

            return;
        }

        $syncType = $this->resolveGuidanceSyncType($topItem);

        if ($syncType === 'legacy_projection') {
            $this->redirect($this->guidanceRoute($topItem), navigate: true);

            return;
        }

        $result = app(MarketplaceManualSyncDispatchService::class)->dispatch($store, $syncType, [
            'options' => [],
            'source' => 'guidance_shortcut',
            'category' => $topItem['category'] ?? null,
            'origin_screen' => 'overview',
        ]);

        $feedback = app(MarketplaceManualSyncDispatchService::class)->feedback(
            $result,
            $this->syncTypeLabel($syncType),
            $store->store_name,
        );

        $this->notify($feedback['message'], $feedback['tone']);
    }

    protected function cleanExportString(mixed $value): mixed
    {
        return app(\App\Services\ExcelService::class)->cleanString($value);
    }

    /**
     * @param  array<string, mixed>|null  $item
     */
    protected function resolveGuidanceSyncType(?array $item): string
    {
        return match ($item['category'] ?? null) {
            'finance_mapping' => 'finance',
            'product_matching', 'listing_completeness' => 'products',
            'legacy_financial_projection' => 'legacy_projection',
            default => 'orders',
        };
    }

    protected function failedSyncRunQuery(): Builder
    {
        return IntegrationSyncRun::query()
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()))
            ->where('status', 'failed')
            ->where('trigger_type', '!=', 'smoke_test');
    }

    protected function failedPushRunQuery(): Builder
    {
        return IntegrationPushRun::query()
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()))
            ->where('status', 'failed');
    }

    protected function failedActionRunQuery(): Builder
    {
        return IntegrationOrderActionRun::query()
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()))
            ->where('status', 'failed');
    }

    protected function failedWebhookEventQuery(): Builder
    {
        return IntegrationWebhookEvent::query()
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()))
            ->where('status', 'failed');
    }

    protected function userId(): int
    {
        return Auth::id() ?? 1;
    }

    protected function pilotRolloutPriorityScore(string $stage, int $smokeWarnings, int $legacyPendingRows, int $confirmedOrders): int
    {
        $base = match ($stage) {
            'credentials_missing' => 700000,
            'readiness_review' => 600000,
            'smoke_failed' => 500000,
            'smoke_pending' => 450000,
            'mapping_hardening' => 400000,
            'legacy_projection' => 300000,
            'pilot_ready' => 100000,
            default => 0,
        };

        return $base + ($smokeWarnings * 1000) + ($legacyPendingRows * 100) + $confirmedOrders;
    }

    public function render()
    {
        return view('livewire.marketplace-overview', [
            'heroStats' => $this->heroStats,
            'reconciliationStats' => $this->reconciliationStats,
            'healthStats' => $this->healthStats,
            'connectionReadinessSummary' => $this->connectionReadinessSummary,
            'diagnosticsSummary' => $this->diagnosticsSummary,
            'diagnosticsGuidance' => $this->diagnosticsGuidance,
            'legacyProjectionSummary' => $this->legacyProjectionSummary,
            'legacyProjectionStoreRows' => $this->legacyProjectionStoreRows,
            'pilotRolloutRows' => $this->pilotRolloutRows,
            'moduleStats' => $this->moduleStats,
            'legalEntities' => $this->legalEntitySummary,
            'recentSyncRuns' => $this->recentSyncRuns,
            'recentSmokeRuns' => $this->recentSmokeRuns,
            'recentPushRuns' => $this->recentPushRuns,
            'recentActionRuns' => $this->recentActionRuns,
            'recentWebhookEvents' => $this->recentWebhookEvents,
        ])->layout('layouts.app', ['title' => 'Pazaryeri Kontrol Merkezi']);
    }
}
