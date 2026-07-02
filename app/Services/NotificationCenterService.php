<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\ChannelListing;
use App\Models\ChannelOrder;
use App\Models\IntegrationPushRun;
use App\Models\IntegrationSyncRun;
use App\Models\MarketplaceQuestion;
use App\Models\MarketplaceStore;
use App\Models\MpProduct;
use App\Models\ProductMatchIssue;
use App\Models\UserNotificationPreference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

class NotificationCenterService
{
    protected static ?bool $notificationsTablesReady = null;

    public function isAvailable(): bool
    {
        if (!config('marketplace.features.notifications_enabled', true)) {
            return false;
        }

        if (self::$notificationsTablesReady !== null) {
            return self::$notificationsTablesReady;
        }

        try {
            return self::$notificationsTablesReady = Schema::hasTable('app_notifications')
                && Schema::hasTable('user_notification_preferences');
        } catch (Throwable) {
            return self::$notificationsTablesReady = false;
        }
    }

    /**
     * @return array<string, array{label: string, tone: string}>
     */
    public function typeMeta(): array
    {
        return [
            'new_order' => ['label' => 'Sipariş', 'tone' => 'success'],
            'order_cancelled' => ['label' => 'İptal', 'tone' => 'warning'],
            'order_returned' => ['label' => 'İade', 'tone' => 'warning'],
            'stock_out' => ['label' => 'Stok bitti', 'tone' => 'danger'],
            'stock_critical' => ['label' => 'Kritik stok', 'tone' => 'warning'],
            'integration_failed' => ['label' => 'Entegrasyon', 'tone' => 'danger'],
            'listing_push_failed' => ['label' => 'Gönderim', 'tone' => 'danger'],
            'product_match_risk' => ['label' => 'Eşleşme', 'tone' => 'warning'],
            'question_received' => ['label' => 'Soru', 'tone' => 'info'],
            'risk_critical' => ['label' => 'Kritik risk', 'tone' => 'danger'],
            'risk_warning' => ['label' => 'Risk uyarısı', 'tone' => 'warning'],
            'booster_price_drop' => ['label' => 'Booster fiyat', 'tone' => 'info'],
            'booster_price_rise' => ['label' => 'Booster fiyat', 'tone' => 'warning'],
            'booster_stock_sales' => ['label' => 'Booster stok', 'tone' => 'warning'],
            'booster_stock_change' => ['label' => 'Booster stok', 'tone' => 'info'],
            'booster_store_change' => ['label' => 'Booster rakip', 'tone' => 'warning'],
            'booster_keyword_change' => ['label' => 'Booster kelime', 'tone' => 'warning'],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createForUser(int $userId, array $attributes): ?AppNotification
    {
        if (!$this->isAvailable()) {
            return null;
        }

        if ($userId <= 0) {
            return null;
        }

        $type = trim((string) ($attributes['type'] ?? ''));

        if ($type !== '' && $this->isTypeMutedForUser($userId, $type)) {
            return null;
        }

        $data = array_merge([
            'user_id' => $userId,
            'severity' => 'info',
            'triggered_at' => now(),
        ], $attributes);

        $eventKey = trim((string) ($data['event_key'] ?? ''));

        if ($eventKey !== '') {
            $notification = AppNotification::query()->firstOrCreate(
                [
                    'user_id' => $userId,
                    'event_key' => $eventKey,
                ],
                $data
            );

            return $notification->wasRecentlyCreated ? $notification : null;
        }

        return AppNotification::create($data);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createForStore(MarketplaceStore $store, array $attributes): ?AppNotification
    {
        return $this->createForUser((int) $store->user_id, array_merge([
            'store_id' => $store->id,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function notifyOrder(MarketplaceStore $store, ChannelOrder $order, string $kind, array $context = []): ?AppNotification
    {
        $order->loadMissing('items');

        $total = (float) $order->items->sum(fn ($item) => (float) ($item->billable_amount ?: $item->gross_amount ?: 0));
        $quantity = (int) $order->items->sum('quantity');
        $orderNumber = (string) ($order->order_number ?: $order->external_order_id);
        $body = $this->formatNotificationBody([
            $orderNumber,
            $quantity > 0 ? "{$quantity} adet" : null,
            $total > 0 ? '₺' . number_format($total, 2, ',', '.') : null,
        ]);

        $meta = match ($kind) {
            'cancelled' => [
                'type' => 'order_cancelled',
                'severity' => 'warning',
                'title' => 'Sipariş iptal edildi',
                'body' => $body,
            ],
            'returned' => [
                'type' => 'order_returned',
                'severity' => 'warning',
                'title' => 'İade bildirimi geldi',
                'body' => $body,
            ],
            default => [
                'type' => 'new_order',
                'severity' => 'info',
                'title' => 'Yeni sipariş geldi',
                'body' => $body,
            ],
        };

        return $this->createForStore($store, [
            'type' => $meta['type'],
            'severity' => $meta['severity'],
            'event_key' => "order:{$kind}:{$order->id}",
            'title' => $meta['title'],
            'body' => $meta['body'],
            'subject_type' => get_class($order),
            'subject_id' => $order->id,
            'data_json' => [
                'order_id' => $order->id,
                'order_number' => $orderNumber,
                'order_status' => $order->order_status,
                'quantity' => $quantity,
                'total' => $total,
                'trigger_type' => $context['trigger_type'] ?? null,
                'sync_type' => $context['sync_type'] ?? null,
            ],
            'action_url' => route('mp.orders', array_filter([
                'search' => $orderNumber,
                'storeFilter' => $store->id,
            ])),
            'triggered_at' => $this->resolveTriggeredAt($order->ordered_at, $context),
        ]);
    }

    public function syncProductStockAlert(MpProduct $product): ?AppNotification
    {
        $quantity = (int) ($product->stock_quantity ?? 0);
        $threshold = $product->critical_stock_threshold !== null
            ? (int) $product->critical_stock_threshold
            : null;
        $level = $this->stockAlertLevel($quantity, $threshold);

        if ($level === null) {
            $this->clearStockAlertState($product);

            return null;
        }

        if (!$this->shouldSendStockAlert($product, $level, $quantity)) {
            return null;
        }

        $this->rememberStockAlertState($product, $level, $quantity);

        return $this->createForUser((int) $product->user_id, $this->stockNotificationPayload(
            type: $level,
            product: $product,
            listing: null,
            quantity: $quantity,
            threshold: $threshold,
            source: 'master_product'
        ));
    }

    public function syncListingStockAlert(ChannelListing $listing): ?AppNotification
    {
        $listing->loadMissing(['store', 'product', 'channelProduct']);

        $quantity = (int) ($listing->stock_quantity ?? 0);
        $product = $listing->product;
        $threshold = $product?->critical_stock_threshold !== null
            ? (int) $product->critical_stock_threshold
            : null;
        $level = $this->stockAlertLevel($quantity, $threshold);

        if ($level === null) {
            $this->clearStockAlertState($listing);

            return null;
        }

        if (!$this->shouldSendStockAlert($listing, $level, $quantity)) {
            return null;
        }

        $this->rememberStockAlertState($listing, $level, $quantity);

        return $this->createForStore($listing->store, $this->stockNotificationPayload(
            type: $level,
            product: $product,
            listing: $listing,
            quantity: $quantity,
            threshold: $threshold,
            source: 'channel_listing'
        ));
    }

    public function notifyIntegrationFailure(IntegrationSyncRun $run, Throwable $exception): ?AppNotification
    {
        $run->loadMissing('store');

        if (!$run->store || $run->isSmokeTest()) {
            return null;
        }

        $errorSummary = $this->summarizeErrorMessage($exception->getMessage(), 90);

        return $this->createForStore($run->store, [
            'type' => 'integration_failed',
            'severity' => 'critical',
            'event_key' => $this->cooldownEventKey('integration-failed', [
                $run->store_id,
                $run->sync_type,
                $errorSummary,
            ]),
            'title' => $this->syncTypeLabel($run->sync_type) . ' senkronu başarısız',
            'body' => $errorSummary,
            'subject_type' => get_class($run),
            'subject_id' => $run->id,
            'data_json' => [
                'sync_run_id' => $run->id,
                'sync_type' => $run->sync_type,
                'sync_label' => $this->syncTypeLabel($run->sync_type),
                'trigger_type' => $run->trigger_type,
                'error' => $exception->getMessage(),
                'error_summary' => $errorSummary,
            ],
            'action_url' => route('mp.overview'),
        ]);
    }

    public function notifyPushFailure(IntegrationPushRun $run, Throwable $exception): ?AppNotification
    {
        $run->loadMissing(['store', 'listing.channelProduct', 'listing.product']);

        if (!$run->store) {
            return null;
        }

        $stockCode = $this->stockCodeForListing($run->listing);
        $productName = $this->productNameForListing($run->listing);
        $operation = $run->push_type === 'stock' ? 'stok' : 'fiyat';
        $errorSummary = $this->summarizeErrorMessage($exception->getMessage(), 90);

        return $this->createForStore($run->store, [
            'type' => 'listing_push_failed',
            'severity' => 'critical',
            'event_key' => "listing-push-failed:{$run->id}",
            'title' => ucfirst($operation) . ' gönderimi başarısız',
            'body' => $this->formatNotificationBody([
                $productName,
                $stockCode,
                $errorSummary,
            ]),
            'subject_type' => get_class($run),
            'subject_id' => $run->id,
            'data_json' => [
                'push_run_id' => $run->id,
                'push_type' => $run->push_type,
                'stock_code' => $stockCode,
                'error' => $exception->getMessage(),
                'error_summary' => $errorSummary,
            ],
            'action_url' => $run->mp_product_id
                ? route('mp.products', ['edit' => $run->mp_product_id, 'tab' => 'logistics'])
                : route('mp.products', array_filter(['search' => $stockCode])),
        ]);
    }

    public function notifyProductMatchIssue(ProductMatchIssue $issue): ?AppNotification
    {
        $issue->loadMissing(['store', 'channelListing.channelProduct']);

        if (!$issue->store) {
            return null;
        }

        $stockCode = $this->stockCodeForListing($issue->channelListing);

        return $this->createForStore($issue->store, [
            'type' => 'product_match_risk',
            'severity' => 'warning',
            'event_key' => "product-match-issue:{$issue->id}",
            'title' => 'Ürün eşleşme riski oluştu',
            'body' => $this->formatNotificationBody([
                $stockCode,
                $this->matchReasonLabel($issue->match_reason),
            ]),
            'subject_type' => get_class($issue),
            'subject_id' => $issue->id,
            'data_json' => [
                'issue_id' => $issue->id,
                'match_reason' => $issue->match_reason,
                'stock_code' => $stockCode,
            ],
            'action_url' => route('mp.matching', array_filter([
                'storeFilter' => $issue->store_id,
                'statusFilter' => 'pending',
            ])),
        ]);
    }

    public function notifyQuestionReceived(MarketplaceQuestion $question): ?AppNotification
    {
        $question->loadMissing('store');

        if (!$question->store) {
            return null;
        }

        return $this->createForStore($question->store, [
            'type' => 'question_received',
            'severity' => 'info',
            'event_key' => "marketplace-question:{$question->id}",
            'title' => 'Yeni müşteri sorusu geldi',
            'body' => $this->formatNotificationBody([
                $question->product_name,
                $question->product_sku,
                Str::limit($question->question_text, 90),
            ]),
            'subject_type' => get_class($question),
            'subject_id' => $question->id,
            'data_json' => [
                'question_id' => $question->id,
                'product_sku' => $question->product_sku,
                'external_question_id' => $question->external_question_id,
                'status' => $question->status,
            ],
            'action_url' => route('marketplace-messages', [
                'question' => $question->id,
                'storeFilter' => $question->store_id,
            ]),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function feedForUser(int $userId, int $limit = 25): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        $mutedTypes = $this->mutedTypesForUser($userId);

        return AppNotification::query()
            ->with('store:id,store_name,marketplace')
            ->where('user_id', $userId)
            ->when($mutedTypes !== [], fn ($query) => $query->whereNotIn('type', $mutedTypes))
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (AppNotification $notification) => $this->toPayload($notification))
            ->all();
    }

    public function unreadCountForUser(int $userId): int
    {
        if (!$this->isAvailable()) {
            return 0;
        }

        $mutedTypes = $this->mutedTypesForUser($userId);

        return AppNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->when($mutedTypes !== [], fn ($query) => $query->whereNotIn('type', $mutedTypes))
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(AppNotification $notification): array
    {
        $notification->loadMissing('store:id,store_name,marketplace');

        $meta = $this->typeMeta()[$notification->type] ?? [
            'label' => Str::headline($notification->type),
            'tone' => $this->toneForSeverity($notification->severity),
        ];
        $marketplaceLabel = $this->marketplaceLabel($notification->store?->marketplace);

        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'type_label' => $meta['label'],
            'tone' => $meta['tone'],
            'severity' => $notification->severity,
            'title' => $notification->title,
            'body' => $notification->body,
            'store_name' => $notification->store?->store_name,
            'marketplace' => $notification->store?->marketplace,
            'marketplace_label' => $marketplaceLabel,
            'context_label' => $this->formatNotificationBody([
                $notification->store?->store_name,
                $marketplaceLabel,
                $meta['label'],
            ]),
            'action_url' => $this->resolvedNotificationActionUrl($notification),
            'unread' => $notification->read_at === null,
            'created_at' => $notification->created_at?->toIso8601String(),
            'created_at_label' => $notification->created_at?->diffForHumans() ?? '',
            'data' => $notification->data_json ?? [],
        ];
    }

    public function preferencesForUser(int $userId): UserNotificationPreference
    {
        if (!$this->isAvailable()) {
            return new UserNotificationPreference([
                'user_id' => $userId,
                'sound_enabled' => false,
            ]);
        }

        return UserNotificationPreference::query()->firstOrCreate([
            'user_id' => $userId,
        ], [
            'sound_enabled' => false,
            'muted_types_json' => [],
        ]);
    }

    public function setSoundEnabled(int $userId, bool $enabled): UserNotificationPreference
    {
        $preference = $this->preferencesForUser($userId);
        $preference->forceFill(['sound_enabled' => $enabled]);

        if ($preference->exists) {
            $preference->save();
        }

        return $preference;
    }

    /**
     * @param  array<int, string>  $types
     */
    public function setMutedTypes(int $userId, array $types): UserNotificationPreference
    {
        $preference = $this->preferencesForUser($userId);
        $preference->forceFill([
            'muted_types_json' => collect($types)
                ->map(fn ($type) => trim((string) $type))
                ->filter()
                ->unique()
                ->values()
                ->all(),
        ]);

        if ($preference->exists) {
            $preference->save();
        }

        return $preference;
    }

    /**
     * @return array<int, string>
     */
    public function mutedTypesForUser(int $userId): array
    {
        if (!$this->isAvailable() || $userId <= 0) {
            return [];
        }

        $preference = $this->preferencesForUser($userId);

        return collect((array) ($preference->muted_types_json ?? []))
            ->map(fn ($type) => trim((string) $type))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function isTypeMutedForUser(int $userId, string $type): bool
    {
        return in_array($type, $this->mutedTypesForUser($userId), true);
    }

    /**
     * @return array{read_deleted: int, unread_deleted: int, total_deleted: int}
     */
    public function pruneExpiredNotifications(?int $readRetentionHours = null, ?int $unreadRetentionDays = null): array
    {
        if (!$this->isAvailable()) {
            return [
                'read_deleted' => 0,
                'unread_deleted' => 0,
                'total_deleted' => 0,
            ];
        }

        $readRetentionHours = max(
            1,
            $readRetentionHours ?? (int) config('marketplace.notifications.read_retention_hours', 24),
        );
        $unreadRetentionDays = max(
            1,
            $unreadRetentionDays ?? (int) config('marketplace.notifications.unread_retention_days', 7),
        );

        $readDeleted = AppNotification::query()
            ->whereNotNull('read_at')
            ->where('read_at', '<', now()->subHours($readRetentionHours))
            ->delete();

        $unreadDeleted = AppNotification::query()
            ->whereNull('read_at')
            ->where('created_at', '<', now()->subDays($unreadRetentionDays))
            ->delete();

        return [
            'read_deleted' => (int) $readDeleted,
            'unread_deleted' => (int) $unreadDeleted,
            'total_deleted' => (int) $readDeleted + (int) $unreadDeleted,
        ];
    }

    protected function stockAlertLevel(int $quantity, ?int $threshold): ?string
    {
        if ($quantity <= 0) {
            return 'stock_out';
        }

        if ($threshold !== null && $quantity <= $threshold) {
            return 'stock_critical';
        }

        return null;
    }

    protected function shouldSendStockAlert(Model $model, string $level, int $quantity): bool
    {
        return (string) ($model->last_stock_alert_level ?? '') !== $level
            || (int) ($model->last_stock_alert_quantity ?? PHP_INT_MIN) !== $quantity;
    }

    protected function rememberStockAlertState(Model $model, string $level, int $quantity): void
    {
        $model->forceFill([
            'last_stock_alert_level' => $level,
            'last_stock_alert_quantity' => $quantity,
            'last_stock_alerted_at' => now(),
        ])->save();
    }

    protected function clearStockAlertState(Model $model): void
    {
        if (($model->last_stock_alert_level ?? null) === null) {
            return;
        }

        $model->forceFill([
            'last_stock_alert_level' => null,
            'last_stock_alert_quantity' => null,
            'last_stock_alerted_at' => null,
        ])->save();
    }

    /**
     * @return array<string, mixed>
     */
    protected function stockNotificationPayload(
        string $type,
        ?MpProduct $product,
        ?ChannelListing $listing,
        int $quantity,
        ?int $threshold,
        string $source,
    ): array {
        $store = $listing?->store;
        $stockCode = $product?->stock_code ?: $this->stockCodeForListing($listing);
        $productName = $product?->product_name ?: $this->productNameForListing($listing);
        $title = $type === 'stock_out' ? 'Stok bitti' : 'Kritik stok eşiğine düşüldü';
        $severity = $type === 'stock_out' ? 'critical' : 'warning';
        $subject = $listing ?: $product;
        $marketplaceLabel = $this->marketplaceLabel($store?->marketplace);

        return [
            'type' => $type,
            'severity' => $severity,
            'event_key' => "stock-alert:{$source}:" . ($listing?->id ?: $product?->id) . ":{$type}:" . now()->format('YmdHis'),
            'title' => $title,
            'body' => $this->formatNotificationBody([
                $marketplaceLabel,
                $productName,
                $stockCode,
                "stok {$quantity}",
                $threshold !== null ? "eşik {$threshold}" : null,
            ]),
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id,
            'data_json' => [
                'product_id' => $product?->id,
                'listing_id' => $listing?->id,
                'stock_code' => $stockCode,
                'quantity' => $quantity,
                'critical_stock_threshold' => $threshold,
                'source' => $source,
                'store_name' => $store?->store_name,
                'marketplace' => $store?->marketplace,
            ],
            'action_url' => $this->stockNotificationActionUrl($product, $listing, $stockCode, $type, $store?->marketplace),
        ];
    }

    protected function resolvedNotificationActionUrl(AppNotification $notification): ?string
    {
        if (in_array($notification->type, ['stock_out', 'stock_critical'], true)) {
            $data = $notification->data_json ?? [];
            $product = null;
            $listing = null;

            if ($notification->subject_type === MpProduct::class && $notification->subject_id) {
                $product = MpProduct::query()
                    ->where('user_id', $notification->user_id)
                    ->find((int) $notification->subject_id);
            }

            if ($notification->subject_type === ChannelListing::class && $notification->subject_id) {
                $listing = ChannelListing::query()
                    ->with(['store', 'product', 'channelProduct'])
                    ->find((int) $notification->subject_id);

                if ($listing?->product?->user_id === $notification->user_id) {
                    $product = $listing->product;
                }
            }

            if (!empty($data['product_id'])) {
                $product = MpProduct::query()
                    ->where('user_id', $notification->user_id)
                    ->find((int) $data['product_id']);
            }

            if (!empty($data['listing_id'])) {
                $listing = ChannelListing::query()
                    ->with(['store', 'product', 'channelProduct'])
                    ->find((int) $data['listing_id']);

                if (!$product && $listing?->product?->user_id === $notification->user_id) {
                    $product = $listing->product;
                }
            }

            $stockCode = trim((string) ($data['stock_code'] ?? ''));
            $marketplace = (string) ($data['marketplace'] ?? $notification->store?->marketplace ?? $listing?->store?->marketplace ?? '');

            if (!$product && $stockCode !== '') {
                $product = MpProduct::query()
                    ->where('user_id', $notification->user_id)
                    ->where(function ($query) use ($stockCode) {
                        $query->where('stock_code', $stockCode)
                            ->orWhere('barcode', $stockCode);
                    })
                    ->first();
            }

            return $this->stockNotificationActionUrl(
                $product,
                $listing,
                $stockCode,
                $notification->type,
                $marketplace !== '' ? $marketplace : null,
            );
        }

        return $notification->action_url;
    }

    protected function stockNotificationActionUrl(
        ?MpProduct $product,
        ?ChannelListing $listing,
        string $stockCode,
        string $type,
        ?string $marketplace,
    ): string {
        if ($product) {
            return route('mp.products', array_filter([
                'edit' => $product->id,
                'tab' => 'logistics',
                'marketplaceFilter' => $marketplace,
            ], fn ($value) => $value !== null && $value !== ''));
        }

        $stockCode = $stockCode !== '' ? $stockCode : $this->stockCodeForListing($listing);

        return route('mp.products', array_filter([
            'search' => $stockCode,
            'marketplaceFilter' => $marketplace,
            'filterStockLevel' => $type === 'stock_critical' ? 'critical' : 'out_of_stock',
        ]));
    }

    protected function stockCodeForListing(?ChannelListing $listing): string
    {
        if (!$listing) {
            return '';
        }

        return trim((string) (
            $listing->product?->stock_code
            ?: $listing->channelProduct?->stock_code
            ?: data_get($listing->channelProduct?->raw_payload ?? [], 'stockCode')
            ?: data_get($listing->channelProduct?->raw_payload ?? [], 'sku')
            ?: $listing->listing_id
        ));
    }

    protected function productNameForListing(?ChannelListing $listing): string
    {
        if (!$listing) {
            return 'Ürün';
        }

        return trim((string) (
            $listing->product?->product_name
            ?: $listing->channelProduct?->title
            ?: data_get($listing->channelProduct?->raw_payload ?? [], 'productName')
            ?: data_get($listing->channelProduct?->raw_payload ?? [], 'name')
            ?: 'Ürün'
        ));
    }

    protected function marketplaceLabel(?string $marketplace): ?string
    {
        $marketplace = trim((string) $marketplace);

        return $marketplace !== '' ? Str::headline($marketplace) : null;
    }

    /**
     * @param  array<int, mixed>  $parts
     */
    protected function formatNotificationBody(array $parts): string
    {
        $filtered = array_values(array_filter(array_map(function (mixed $part): ?string {
            $value = trim((string) $part);

            return $value !== '' ? $value : null;
        }, $parts), fn (?string $part) => $part !== null));

        return implode(' · ', $filtered);
    }

    /**
     * @param  array<int, mixed>  $parts
     */
    protected function cooldownEventKey(string $prefix, array $parts): string
    {
        $cooldownSeconds = max(1, (int) config('marketplace.notifications.failure_cooldown_seconds', 300));
        $bucket = (int) floor(now()->timestamp / $cooldownSeconds);
        $fingerprint = sha1(implode('|', array_map(
            fn (mixed $part) => Str::lower(trim((string) $part)),
            $parts
        )));

        return "{$prefix}:{$bucket}:" . substr($fingerprint, 0, 32);
    }

    protected function summarizeErrorMessage(string $message, int $limit = 110): string
    {
        $clean = trim((string) preg_replace('/\s+/u', ' ', $message));

        if ($clean === '') {
            return 'Bilinmeyen hata';
        }

        if (preg_match('/status code\s+(\d{3})/i', $clean, $matches) === 1) {
            return 'HTTP ' . $matches[1];
        }

        if (preg_match('/cURL error\s+(\d+)/i', $clean, $matches) === 1) {
            return 'cURL ' . $matches[1];
        }

        return Str::limit($clean, $limit);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function resolveTriggeredAt(mixed $fallback, array $context): Carbon
    {
        $value = $context['triggered_at'] ?? $fallback ?? null;

        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value) {
            try {
                return Carbon::parse($value);
            } catch (Throwable) {
                //
            }
        }

        return now();
    }

    protected function toneForSeverity(?string $severity): string
    {
        return match ($severity) {
            'critical' => 'danger',
            'warning' => 'warning',
            'success' => 'success',
            default => 'info',
        };
    }

    protected function syncTypeLabel(?string $syncType): string
    {
        return match ($syncType) {
            'orders', 'webhook_refresh' => 'Sipariş',
            'products' => 'Ürün',
            'finance' => 'Finans',
            'questions' => 'Soru',
            'claims' => 'İade',
            default => Str::headline((string) $syncType),
        };
    }

    protected function matchReasonLabel(?string $reason): string
    {
        return match ($reason) {
            'auto_match_disabled' => 'otomatik eşleşme kapalı',
            'ambiguous_stock_code' => 'stok kodu birden fazla ürüne denk geliyor',
            'ambiguous_barcode' => 'barkod birden fazla ürüne denk geliyor',
            'not_found' => 'ürün bulunamadı',
            default => Str::headline((string) $reason),
        };
    }
}
