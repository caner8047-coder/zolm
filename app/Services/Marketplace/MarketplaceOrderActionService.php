<?php

namespace App\Services\Marketplace;

use App\Jobs\RunMarketplaceOrderActionJob;
use App\Models\ChannelOrder;
use App\Models\ChannelOrderPackage;
use App\Models\IntegrationOrderActionRun;
use App\Models\IntegrationSyncRun;
use App\Models\Shipment;
use App\Services\Cargo\CargoShipmentService;
use App\Services\Marketplace\Connectors\DemoMarketplaceConnector;
use App\Services\Marketplace\Contracts\ManagesCommonLabels;
use App\Services\Marketplace\Contracts\SendsInvoiceLinks;
use App\Services\Marketplace\Contracts\UpdatesPackageStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

class MarketplaceOrderActionService
{
    /**
     * @var array<string, string>
     */
    public const ACTION_LABELS = [
        'refresh_order' => 'Siparişi yenile',
        'refresh_cargo' => 'Kargo bilgisini yenile',
        'refresh_finance' => 'Finansı yenile',
        'recalculate_profit' => 'Kârı yeniden hesapla',
        'package_picking' => 'Kargola',
        'package_invoiced' => 'Fatura kesildi bildir',
        'package_common_label_create' => 'Ortak barkod talep et',
        'package_common_label_get' => 'Ortak barkod getir',
        'package_invoice_link' => 'Fatura linki gönder',
        'cargo_create_surat_shipment' => 'Sürat gönderisi oluştur',
        'cargo_refresh_surat_tracking' => 'Sürat takibini yenile',
    ];

    public function __construct(
        protected MarketplaceConnectorManager $connectorManager,
        protected MarketplaceProfitSnapshotService $profitSnapshotService,
        protected MarketplaceSyncService $syncService,
        protected CargoShipmentService $cargoShipmentService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{
     *     created: bool,
     *     coalesced: bool,
     *     busy: bool,
     *     recent: bool,
     *     reason: string|null,
     *     action_run: IntegrationOrderActionRun,
     *     debounce_seconds: int
     * }
     */
    public function dispatch(
        ChannelOrder $order,
        string $actionType,
        array $context = [],
        ?int $triggeredBy = null,
        ?ChannelOrderPackage $package = null,
    ): array {
        if (!array_key_exists($actionType, self::ACTION_LABELS)) {
            throw new \RuntimeException('Desteklenmeyen sipariş aksiyonu: ' . $actionType);
        }

        $debounceSeconds = $this->debounceWindow();
        $contextSignature = $this->contextSignature($context);

        return DB::transaction(function () use (
            $order,
            $actionType,
            $context,
            $contextSignature,
            $triggeredBy,
            $package,
            $debounceSeconds,
        ) {
            $activeRun = $this->findActiveRun($order, $actionType, $package);

            if ($activeRun && $activeRun->status === 'processing') {
                return [
                    'created' => false,
                    'coalesced' => false,
                    'busy' => true,
                    'recent' => false,
                    'reason' => 'active',
                    'action_run' => $activeRun,
                    'debounce_seconds' => $debounceSeconds,
                ];
            }

            $mergeableRun = $this->findMergeableRun($order, $actionType, $package);

            if ($mergeableRun) {
                $existingContext = $mergeableRun->request_context_json ?? [];
                $mergeCount = (int) data_get($existingContext, '_merged_action_count', 0) + 1;

                $mergeableRun->update([
                    'triggered_by' => $triggeredBy ?? $mergeableRun->triggered_by,
                    'request_context_json' => array_merge($existingContext, $context, [
                        '_coalesced_at' => now()->toIso8601String(),
                        '_merged_action_count' => $mergeCount,
                    ]),
                    'error_message' => null,
                ]);

                return [
                    'created' => false,
                    'coalesced' => true,
                    'busy' => false,
                    'recent' => false,
                    'reason' => 'queued',
                    'action_run' => $mergeableRun->fresh(),
                    'debounce_seconds' => $debounceSeconds,
                ];
            }

            $recentRun = $this->findRecentCompletedRun($order, $actionType, $package, $contextSignature, $debounceSeconds);

            if ($recentRun) {
                return [
                    'created' => false,
                    'coalesced' => false,
                    'busy' => false,
                    'recent' => true,
                    'reason' => 'recent',
                    'action_run' => $recentRun,
                    'debounce_seconds' => $debounceSeconds,
                ];
            }

            $actionRun = $this->createActionRun($order, $actionType, $context, $triggeredBy, $package);

            RunMarketplaceOrderActionJob::dispatch($actionRun->id);

            return [
                'created' => true,
                'coalesced' => false,
                'busy' => false,
                'recent' => false,
                'reason' => null,
                'action_run' => $actionRun,
                'debounce_seconds' => $debounceSeconds,
            ];
        });
    }

    public function queue(
        ChannelOrder $order,
        string $actionType,
        array $context = [],
        ?int $triggeredBy = null,
        ?ChannelOrderPackage $package = null,
    ): IntegrationOrderActionRun {
        if (!array_key_exists($actionType, self::ACTION_LABELS)) {
            throw new \RuntimeException('Desteklenmeyen sipariş aksiyonu: ' . $actionType);
        }

        $actionRun = $this->createActionRun($order, $actionType, $context, $triggeredBy, $package);

        RunMarketplaceOrderActionJob::dispatch($actionRun->id);

        return $actionRun;
    }

    /**
     * @param  array{
     *     created: bool,
     *     coalesced: bool,
     *     busy: bool,
     *     recent: bool,
     *     reason: string|null,
     *     action_run: IntegrationOrderActionRun,
     *     debounce_seconds: int
     * }  $result
     * @return array{message: string, tone: string}
     */
    public function feedback(array $result, string $actionType, ?string $storeName = null): array
    {
        $prefix = filled($storeName) ? "{$storeName} için " : '';
        $label = self::ACTION_LABELS[$actionType] ?? $actionType;
        $actionRun = $result['action_run'];

        if ($result['created']) {
            return [
                'message' => "{$prefix}{$label} kuyruğa alındı. İşlem no: #{$actionRun->id}",
                'tone' => 'success',
            ];
        }

        if ($result['coalesced']) {
            return [
                'message' => "{$prefix}{$label} bekleyen işlem üzerinde güncellendi. İşlem no: #{$actionRun->id}",
                'tone' => 'success',
            ];
        }

        if ($result['busy']) {
            return [
                'message' => "{$prefix}{$label} zaten çalışıyor. Mevcut işlem #{$actionRun->id} tamamlanınca tekrar deneyin.",
                'tone' => 'info',
            ];
        }

        return [
            'message' => "{$prefix}{$label} az önce tamamlandı. {$result['debounce_seconds']} sn içinde yeni kayıt açılmadı (#{$actionRun->id}).",
            'tone' => 'info',
        ];
    }

    public function run(int $actionRunId, int $attempt): void
    {
        $actionRun = IntegrationOrderActionRun::query()
            ->with([
                'store.connection',
                'store.syncProfile',
                'order.packages',
                'package.items:id,channel_order_package_id,external_line_id,quantity',
            ])
            ->findOrFail($actionRunId);

        $actionRun->update([
            'status' => 'processing',
            'started_at' => $actionRun->started_at ?: now(),
            'attempt_count' => max(1, $attempt),
            'error_message' => null,
        ]);

        try {
            $result = match ($actionRun->action_type) {
                'refresh_order' => $this->runSyncAction($actionRun, 'orders'),
                'refresh_cargo' => $this->runSyncAction($actionRun, 'orders'),
                'refresh_finance' => $this->runSyncAction($actionRun, 'finance'),
                'recalculate_profit' => $this->recalculateProfit($actionRun),
                'package_picking' => $this->notifyPackagePicking($actionRun),
                'package_invoiced' => $this->notifyPackageInvoiced($actionRun),
                'package_common_label_create' => $this->createCommonLabel($actionRun),
                'package_common_label_get' => $this->getCommonLabel($actionRun),
                'package_invoice_link' => $this->sendInvoiceLink($actionRun),
                'cargo_create_surat_shipment' => $this->createSuratShipment($actionRun),
                'cargo_refresh_surat_tracking' => $this->refreshSuratTracking($actionRun),
                default => throw new \RuntimeException('Desteklenmeyen sipariş aksiyonu: ' . $actionRun->action_type),
            };

            $actionRun->update([
                'status' => 'completed',
                'response_json' => $result,
                'external_action_id' => (string) (data_get($result, 'sync_run_id') ?: data_get($result, 'external_action_id') ?: ''),
                'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $actionRun->update([
                'status' => $attempt >= 3 ? 'failed' : 'retrying',
                'error_message' => $exception->getMessage(),
                'finished_at' => $attempt >= 3 ? now() : null,
            ]);

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function runSyncAction(IntegrationOrderActionRun $actionRun, string $syncType): array
    {
        $order = $actionRun->order;
        $window = $this->resolveWindow($order);

        $notes = [
            'action_type' => $actionRun->action_type,
            'source' => 'order_action',
            'action_run_id' => $actionRun->id,
            'options' => [
                'start_date' => $window['start_date'],
                'end_date' => $window['end_date'],
                'order_number' => $order->order_number,
                'shipment_package_ids' => $actionRun->package
                    ? [$actionRun->package->external_package_id]
                    : $order->packages->pluck('external_package_id')->filter()->values()->all(),
            ],
        ];

        $syncRun = IntegrationSyncRun::create([
            'store_id' => $actionRun->store_id,
            'sync_type' => $syncType,
            'trigger_type' => 'order_action',
            'status' => 'queued',
            'notes_json' => $notes,
        ]);

        $this->syncService->run($syncRun->id);

        $syncRun->refresh();

        return [
            'sync_run_id' => $syncRun->id,
            'sync_type' => $syncType,
            'sync_status' => $syncRun->status,
            'items_received' => $syncRun->items_received,
            'items_created' => $syncRun->items_created,
            'items_updated' => $syncRun->items_updated,
            'items_skipped' => $syncRun->items_skipped,
            'finished_at' => optional($syncRun->finished_at)?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function recalculateProfit(IntegrationOrderActionRun $actionRun): array
    {
        $this->profitSnapshotService->recalculateForOrders($actionRun->store, [$actionRun->channel_order_id]);

        $latestSnapshot = $actionRun->order->profitSnapshots()
            ->whereNull('channel_order_item_id')
            ->latest('calculated_at')
            ->first();

        return [
            'order_id' => $actionRun->channel_order_id,
            'profit_state' => $latestSnapshot?->profit_state,
            'estimated_profit' => (float) ($latestSnapshot?->estimated_profit ?? 0),
            'confirmed_profit' => (float) ($latestSnapshot?->confirmed_profit ?? 0),
            'calculated_at' => optional($latestSnapshot?->calculated_at)?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function notifyPackagePicking(IntegrationOrderActionRun $actionRun): array
    {
        $package = $this->ensurePackage($actionRun);
        $connector = $this->connectorManager->resolveForStore($actionRun->store);

        if (!$connector instanceof UpdatesPackageStatus || !$this->connectorSupportsAction($connector->capabilities(), 'package_picking')) {
            throw new \RuntimeException('Bu kanal paket statü güncellemesini desteklemiyor.');
        }

        $result = $connector->notifyPackagePicking($package, $actionRun->request_context_json ?? []);

        $package->update([
            'package_status' => 'Picking',
            'last_synced_at' => now(),
        ]);

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    protected function notifyPackageInvoiced(IntegrationOrderActionRun $actionRun): array
    {
        $package = $this->ensurePackage($actionRun);
        $connector = $this->connectorManager->resolveForStore($actionRun->store);
        $context = $actionRun->request_context_json ?? [];

        if (blank($context['invoice_number'] ?? null)) {
            throw new \RuntimeException('Fatura kesildi bildirimi için fatura numarası zorunludur.');
        }

        if (!$connector instanceof UpdatesPackageStatus || !$this->connectorSupportsAction($connector->capabilities(), 'package_invoiced')) {
            throw new \RuntimeException('Bu kanal paket statü güncellemesini desteklemiyor.');
        }

        $result = $connector->notifyPackageInvoiced($package, $context);

        $package->update([
            'package_status' => 'Invoiced',
            'last_synced_at' => now(),
        ]);

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    protected function createCommonLabel(IntegrationOrderActionRun $actionRun): array
    {
        $package = $this->ensurePackage($actionRun);
        $connector = $this->connectorManager->resolveForStore($actionRun->store);

        if (!$connector instanceof ManagesCommonLabels || !$this->connectorSupportsAction($connector->capabilities(), 'package_common_label_create')) {
            throw new \RuntimeException('Bu kanal ortak barkod servisini desteklemiyor.');
        }

        return $connector->createCommonLabel($package, $actionRun->request_context_json ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getCommonLabel(IntegrationOrderActionRun $actionRun): array
    {
        $package = $this->ensurePackage($actionRun);
        $connector = $this->connectorManager->resolveForStore($actionRun->store);

        if (!$connector instanceof ManagesCommonLabels || !$this->connectorSupportsAction($connector->capabilities(), 'package_common_label_get')) {
            throw new \RuntimeException('Bu kanal ortak barkod servisini desteklemiyor.');
        }

        return $connector->getCommonLabel($package, $actionRun->request_context_json ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    protected function sendInvoiceLink(IntegrationOrderActionRun $actionRun): array
    {
        $package = $this->ensurePackage($actionRun);
        $connector = $this->connectorManager->resolveForStore($actionRun->store);
        $context = $actionRun->request_context_json ?? [];
        $invoiceLink = trim((string) ($context['invoice_link'] ?? ''));

        if ($invoiceLink === '') {
            throw new \RuntimeException('Fatura linki gönderimi için link zorunludur.');
        }

        if (!$connector instanceof SendsInvoiceLinks || !$this->connectorSupportsAction($connector->capabilities(), 'package_invoice_link')) {
            throw new \RuntimeException('Bu kanal fatura linki gönderimini desteklemiyor.');
        }

        return $connector->sendInvoiceLink($package, $invoiceLink, $context);
    }

    /**
     * @return array<string, mixed>
     */
    protected function createSuratShipment(IntegrationOrderActionRun $actionRun): array
    {
        $package = $this->ensurePackage($actionRun);
        $connector = $this->connectorManager->resolveForStore($actionRun->store);

        if ($connector instanceof DemoMarketplaceConnector) {
            return $connector->simulateAction('create_shipment', [
                $actionRun->store_id,
                $package->external_package_id ?: $package->getKey(),
            ]);
        }

        $shipment = $this->cargoShipmentService->createOrUpdateFromPackage($package);
        $shipment = $this->cargoShipmentService->pushToCarrier($shipment);

        return $this->shipmentActionResponse($shipment, [
            'external_action_id' => $shipment->external_shipment_id ?: $shipment->tracking_number ?: $shipment->barcode,
            'action' => 'create_shipment',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function refreshSuratTracking(IntegrationOrderActionRun $actionRun): array
    {
        $package = $this->ensurePackage($actionRun);
        $connector = $this->connectorManager->resolveForStore($actionRun->store);

        if ($connector instanceof DemoMarketplaceConnector) {
            return array_merge($connector->simulateAction('refresh_tracking', [
                $actionRun->store_id,
                $package->external_package_id ?: $package->getKey(),
            ]), [
                'tracking_ready' => true,
            ]);
        }

        $shipment = Shipment::query()
            ->where('channel_order_package_id', $package->id)
            ->where('carrier_code', 'surat')
            ->latest('id')
            ->first();

        if (!$shipment) {
            $shipment = $this->cargoShipmentService->createOrUpdateFromPackage($package);
        }

        if (blank($shipment->tracking_number) && blank($shipment->barcode) && blank($shipment->external_shipment_id)) {
            return $this->shipmentActionResponse($shipment, [
                'action' => 'refresh_tracking',
                'tracking_ready' => false,
                'message' => 'Bu paket için takip numarası oluşmadı. Önce Sürat gönderisi oluşturun.',
            ]);
        }

        $shipment = $this->cargoShipmentService->refreshTracking($shipment);

        return $this->shipmentActionResponse($shipment, [
            'external_action_id' => $shipment->tracking_number ?: $shipment->barcode ?: $shipment->external_shipment_id,
            'action' => 'refresh_tracking',
            'tracking_ready' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function shipmentActionResponse(Shipment $shipment, array $extra = []): array
    {
        return array_merge([
            'shipment_id' => $shipment->id,
            'shipment_no' => $shipment->shipment_no,
            'status' => $shipment->status,
            'status_label' => $shipment->status_label,
            'external_shipment_id' => $shipment->external_shipment_id,
            'tracking_number' => $shipment->tracking_number,
            'barcode' => $shipment->barcode,
            'expected_cost' => (float) $shipment->expected_cost,
            'actual_cost' => (float) $shipment->actual_cost,
            'invoice_cost' => (float) $shipment->invoice_cost,
            'cost_delta' => (float) $shipment->cost_delta,
            'last_tracked_at' => optional($shipment->last_tracked_at)?->toIso8601String(),
            'delivered_at' => optional($shipment->delivered_at)?->toIso8601String(),
        ], $extra);
    }

    /**
     * @param  array<string, bool>  $capabilities
     */
    protected function connectorSupportsAction(array $capabilities, string $actionCapability): bool
    {
        if (($capabilities[$actionCapability] ?? false) === true) {
            return true;
        }

        $fallback = match ($actionCapability) {
            'package_picking', 'package_invoiced' => 'package_status',
            'package_common_label_create', 'package_common_label_get' => 'common_label',
            'package_invoice_link' => 'invoice_link',
            default => null,
        };

        return $fallback !== null && (($capabilities[$fallback] ?? false) === true);
    }

    /**
     * @return array<string, string>
     */
    protected function resolveWindow(ChannelOrder $order): array
    {
        $anchor = $order->ordered_at
            ? CarbonImmutable::parse($order->ordered_at)
            : CarbonImmutable::now();

        $startDate = $anchor->subDays(30);
        $endDate = CarbonImmutable::now()->addDay();

        return [
            'start_date' => $startDate->toIso8601String(),
            'end_date' => $endDate->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function createActionRun(
        ChannelOrder $order,
        string $actionType,
        array $context = [],
        ?int $triggeredBy = null,
        ?ChannelOrderPackage $package = null,
    ): IntegrationOrderActionRun {
        return IntegrationOrderActionRun::create([
            'store_id' => $order->store_id,
            'channel_order_id' => $order->id,
            'channel_order_package_id' => $package?->id,
            'triggered_by' => $triggeredBy,
            'action_type' => $actionType,
            'status' => 'queued',
            'attempt_count' => 0,
            'request_context_json' => $context,
        ]);
    }

    protected function debounceWindow(): int
    {
        return max(1, (int) config('marketplace.order_actions.debounce_seconds', 45));
    }

    protected function activeRunWindow(): int
    {
        return max(1, (int) config('marketplace.order_actions.active_run_block_seconds', 900));
    }

    protected function findActiveRun(
        ChannelOrder $order,
        string $actionType,
        ?ChannelOrderPackage $package = null,
    ): ?IntegrationOrderActionRun {
        return IntegrationOrderActionRun::query()
            ->where('channel_order_id', $order->id)
            ->where('action_type', $actionType)
            ->when($package, fn ($query) => $query->where('channel_order_package_id', $package->id), fn ($query) => $query->whereNull('channel_order_package_id'))
            ->whereIn('status', config('marketplace.order_actions.active_statuses', ['queued', 'processing', 'retrying']))
            ->where('created_at', '>=', now()->subSeconds($this->activeRunWindow()))
            ->latest('created_at')
            ->lockForUpdate()
            ->first();
    }

    protected function findMergeableRun(
        ChannelOrder $order,
        string $actionType,
        ?ChannelOrderPackage $package = null,
    ): ?IntegrationOrderActionRun {
        return IntegrationOrderActionRun::query()
            ->where('channel_order_id', $order->id)
            ->where('action_type', $actionType)
            ->when($package, fn ($query) => $query->where('channel_order_package_id', $package->id), fn ($query) => $query->whereNull('channel_order_package_id'))
            ->whereIn('status', config('marketplace.order_actions.merge_statuses', ['queued', 'retrying']))
            ->where('created_at', '>=', now()->subSeconds($this->activeRunWindow()))
            ->latest('created_at')
            ->lockForUpdate()
            ->first();
    }

    protected function findRecentCompletedRun(
        ChannelOrder $order,
        string $actionType,
        ?ChannelOrderPackage $package,
        string $contextSignature,
        int $debounceSeconds,
    ): ?IntegrationOrderActionRun {
        return IntegrationOrderActionRun::query()
            ->where('channel_order_id', $order->id)
            ->where('action_type', $actionType)
            ->when($package, fn ($query) => $query->where('channel_order_package_id', $package->id), fn ($query) => $query->whereNull('channel_order_package_id'))
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subSeconds($debounceSeconds))
            ->latest('created_at')
            ->lockForUpdate()
            ->get()
            ->first(fn (IntegrationOrderActionRun $run) => $this->contextSignature($run->request_context_json ?? []) === $contextSignature);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function contextSignature(array $context): string
    {
        $normalized = $this->sortContextRecursive($context);

        return md5(json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  array<string|int, mixed>  $payload
     * @return array<string|int, mixed>
     */
    protected function sortContextRecursive(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->sortContextRecursive($value);
            }
        }

        if (array_is_list($payload)) {
            return $payload;
        }

        ksort($payload);

        return $payload;
    }

    protected function ensurePackage(IntegrationOrderActionRun $actionRun): ChannelOrderPackage
    {
        if (!$actionRun->package) {
            throw new \RuntimeException('Bu aksiyon için paket kaydı zorunludur.');
        }

        return $actionRun->package;
    }
}
