<?php

namespace App\Services\Marketplace;

use App\Models\AppNotification;
use App\Models\MarketplaceRiskSignalState;
use App\Services\CampaignDecisionCenterQueryService;
use App\Services\NotificationCenterService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class MarketplaceRiskSignalService
{
    public function __construct(
        protected MarketplaceProfitCenterQueryService $profitCenter,
        protected MarketplaceSettlementAuditQueryService $settlementAudit,
        protected MarketplaceDiagnosticsGuidanceService $diagnostics,
        protected CampaignDecisionCenterQueryService $campaigns,
        protected NotificationCenterService $notifications,
    ) {
    }

    /**
     * @return array<string, array{label: string, tone: string}>
     */
    public function categoryDefinitions(): array
    {
        return [
            'profit' => ['label' => 'Kârlılık', 'tone' => 'rose'],
            'settlement' => ['label' => 'Hakediş ve kesinti', 'tone' => 'amber'],
            'product' => ['label' => 'Ürün ve maliyet', 'tone' => 'sky'],
            'integration' => ['label' => 'Entegrasyon', 'tone' => 'indigo'],
            'campaign' => ['label' => 'Kampanya', 'tone' => 'emerald'],
            'operations' => ['label' => 'Operasyon', 'tone' => 'slate'],
        ];
    }

    /**
     * @return array<string, array{label: string, tone: string, weight: int}>
     */
    public function severityDefinitions(): array
    {
        return [
            'critical' => ['label' => 'Kritik', 'tone' => 'danger', 'weight' => 3],
            'warning' => ['label' => 'Uyarı', 'tone' => 'warning', 'weight' => 2],
            'info' => ['label' => 'Bilgi', 'tone' => 'info', 'weight' => 1],
        ];
    }

    /**
     * @return array<string, array{label: string, tone: string}>
     */
    public function statusDefinitions(): array
    {
        return [
            'open' => ['label' => 'Açık', 'tone' => 'danger'],
            'snoozed' => ['label' => 'Ertelendi', 'tone' => 'warning'],
            'resolved' => ['label' => 'Çözüldü', 'tone' => 'success'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function dashboard(int $userId, array $filters = [], int $queueLimit = 200, bool $publishNotifications = false): array
    {
        $signals = collect($this->signalsForUser($userId));
        $states = $this->syncStates($userId, $signals, true);
        $enriched = $signals
            ->map(fn (array $signal) => $this->applyState($signal, $states->get($signal['fingerprint'])))
            ->values();

        $publishedNotifications = $publishNotifications
            ? $this->publishNotifications($userId, $enriched)
            : 0;

        $filtered = $this->applyFilters($enriched, $filters);
        $summary = $this->summary($enriched);

        return [
            'summary' => $summary,
            'primary_focus' => $enriched
                ->where('status', 'open')
                ->sortByDesc('priority_score')
                ->first(),
            'priority_actions' => $enriched
                ->where('status', 'open')
                ->sortByDesc('priority_score')
                ->take(4)
                ->values()
                ->all(),
            'category_breakdown' => $this->categoryBreakdown($enriched),
            'severity_breakdown' => $this->severityBreakdown($enriched),
            'queue' => $this->sortSignals($filtered, $filters)
                ->take($queueLimit)
                ->values()
                ->all(),
            'queue_total' => $filtered->count(),
            'notification_preferences' => $this->notificationPreferences($userId),
            'published_notifications' => $publishedNotifications,
            'generated_at' => now()->format('d.m.Y H:i'),
        ];
    }

    /**
     * @return array{signals: int, notifications: int}
     */
    public function syncForUser(int $userId): array
    {
        $dashboard = $this->dashboard($userId, [], 1, true);

        return [
            'signals' => (int) $dashboard['summary']['total_count'],
            'notifications' => (int) ($dashboard['published_notifications'] ?? 0),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function signalsForUser(int $userId): array
    {
        $signals = collect()
            ->merge($this->safely(fn () => $this->profitSignals($userId)))
            ->merge($this->safely(fn () => $this->settlementSignals($userId)))
            ->merge($this->safely(fn () => $this->campaignSignals($userId)))
            ->merge($this->safely(fn () => $this->integrationSignals($userId)))
            ->merge($this->safely(fn () => $this->notificationSignals($userId)))
            ->filter(fn (array $signal) => (int) ($signal['value'] ?? 0) > 0 || (float) ($signal['impact'] ?? 0) > 0)
            ->map(fn (array $signal) => $this->normalizeSignal($signal))
            ->unique('fingerprint')
            ->sortByDesc('priority_score')
            ->values();

        return $signals->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function guidanceForContext(int $userId, string $context): array
    {
        $categoryMap = [
            'profit' => ['profit', 'settlement', 'product', 'campaign'],
            'finance' => ['profit', 'settlement'],
            'products' => ['product', 'campaign'],
            'integrations' => ['integration', 'operations'],
        ];
        $categories = $categoryMap[$context] ?? array_keys($this->categoryDefinitions());

        $signals = $this->recentStateSignals($userId, $categories);

        if ($signals->isEmpty()) {
            $fresh = (match ($context) {
                'finance' => collect($this->safely(fn () => $this->profitSignals($userId)))
                    ->merge($this->safely(fn () => $this->settlementSignals($userId))),
                'products' => collect($this->safely(fn () => $this->profitSignals($userId)))
                    ->merge($this->safely(fn () => $this->notificationSignals($userId))),
                'integrations' => collect($this->safely(fn () => $this->integrationSignals($userId)))
                    ->merge($this->safely(fn () => $this->notificationSignals($userId))),
                default => collect($this->safely(fn () => $this->profitSignals($userId)))
                    ->merge($this->safely(fn () => $this->campaignSignals($userId))),
            })
                ->map(fn (array $signal) => $this->normalizeSignal($signal))
                ->filter(fn (array $signal) => in_array($signal['category'], $categories, true))
                ->unique('fingerprint')
                ->values();

            $states = $this->syncStates($userId, $fresh);
            $signals = $fresh->map(fn (array $signal) => $this->applyState($signal, $states->get($signal['fingerprint'])));
        }

        $active = $signals
            ->where('status', 'open')
            ->sortByDesc('priority_score')
            ->values();

        return [
            'has_risk' => $active->isNotEmpty(),
            'total' => $active->count(),
            'critical' => $active->where('severity', 'critical')->count(),
            'primary' => $active->first(),
            'items' => $active->take(3)->all(),
            'route' => route('mp.risk-center', ['category' => $categories[0] ?? '']),
        ];
    }

    public function resolve(int $userId, string $fingerprint, ?string $note = null): MarketplaceRiskSignalState
    {
        return $this->updateState($userId, $fingerprint, [
            'status' => MarketplaceRiskSignalState::STATUS_RESOLVED,
            'note' => filled($note) ? trim((string) $note) : null,
            'resolved_at' => now(),
            'snoozed_until' => null,
        ]);
    }

    public function availableActionsForSignal(string $category, string $severity): array
    {
        if ($category === 'profit' && $severity === 'critical') {
            return [
                ['key' => 'increase_price_10', 'label' => 'Fiyatı %10 Artır', 'icon' => 'arrow-up'],
                ['key' => 'stop_sale', 'label' => 'Satışı Durdur', 'icon' => 'pause'],
            ];
        }

        if ($category === 'campaign' && $severity === 'critical') {
            return [
                ['key' => 'exit_campaign', 'label' => 'Kampanyadan Çık', 'icon' => 'logout'],
                ['key' => 'increase_price_5', 'label' => 'Fiyatı %5 Artır', 'icon' => 'arrow-up'],
            ];
        }

        if ($category === 'product') {
            return [
                ['key' => 'fix_cost', 'label' => 'Maliyeti Gir', 'icon' => 'pencil'],
            ];
        }

        return [];
    }

    public function executeAction(int $userId, string $fingerprint, string $actionKey): MarketplaceRiskSignalState
    {
        $actionLabels = [
            'increase_price_10' => 'Fiyat %10 artırıldı (Otomasyon)',
            'increase_price_5' => 'Fiyat %5 artırıldı (Otomasyon)',
            'stop_sale' => 'Satış durduruldu (Otomasyon)',
            'exit_campaign' => 'Kampanyadan çıkıldı (Otomasyon)',
            'fix_cost' => 'Maliyet düzeltme ekranına yönlendirildi (Aksiyon alındı)',
        ];

        $note = $actionLabels[$actionKey] ?? 'Aksiyon uygulandı';

        return $this->resolve($userId, $fingerprint, $note);
    }

    public function snooze(int $userId, string $fingerprint, int $days): MarketplaceRiskSignalState
    {
        $days = max(1, min(30, $days));

        return $this->updateState($userId, $fingerprint, [
            'status' => MarketplaceRiskSignalState::STATUS_SNOOZED,
            'snoozed_until' => now()->addDays($days),
            'resolved_at' => null,
        ]);
    }

    public function reopen(int $userId, string $fingerprint): MarketplaceRiskSignalState
    {
        return $this->updateState($userId, $fingerprint, [
            'status' => MarketplaceRiskSignalState::STATUS_OPEN,
            'snoozed_until' => null,
            'resolved_at' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function exportRows(int $userId, array $filters = []): array
    {
        $dashboard = $this->dashboard($userId, $filters, PHP_INT_MAX);

        return collect($dashboard['queue'])->map(fn (array $signal) => [
            'Öncelik Skoru' => $signal['priority_score'],
            'Önem' => $signal['severity_label'],
            'Durum' => $signal['status_label'],
            'Kategori' => $signal['category_label'],
            'Risk' => $signal['title'],
            'Açıklama' => $signal['description'],
            'Önerilen Aksiyon' => $signal['recommendation'],
            'Etkilenen Kayıt' => $signal['value'],
            'Finansal Etki' => $signal['impact'],
            'Kaynak' => $signal['source_label'],
            'Son Görülme' => $signal['last_seen_label'],
            'Ertelenme Bitişi' => $signal['snoozed_until_label'],
            'Aksiyon URL' => $signal['action_url'],
        ])->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function profitSignals(int $userId): array
    {
        $recommendations = $this->profitCenter->priorityRecommendations($userId, $this->defaultDateFilters(), 10);

        return collect($recommendations)->map(function (array $item): array {
            $key = (string) $item['key'];
            $category = $key === 'missing_cost' ? 'product' : ($key === 'loss_orders' ? 'profit' : 'settlement');
            $severity = match ($key) {
                'loss_orders', 'material_variance' => 'critical',
                default => 'warning',
            };
            $isDirectImpact = in_array($key, ['loss_orders', 'material_variance'], true);

            return [
                'key' => 'profit:' . $key,
                'category' => $category,
                'severity' => $severity,
                'title' => (string) $item['label'],
                'description' => (string) $item['description'],
                'recommendation' => (string) ($item['action_label'] ?? 'Detayı incele'),
                'value' => (int) $item['value'],
                'impact' => $isDirectImpact ? (float) $item['impact'] : 0,
                'exposure' => (float) $item['impact'],
                'route_name' => (string) $item['route'],
                'query' => (array) ($item['query'] ?? []),
                'action_label' => (string) ($item['action_label'] ?? 'Detayı aç'),
                'source' => 'profit_center',
                'source_label' => 'Kâr Merkezi',
            ];
        })->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function settlementSignals(int $userId): array
    {
        $audit = $this->settlementAudit->audit($userId, $this->defaultDateFilters(), 1);
        $criticalKeys = [
            'settlement_difference',
            'commission_difference',
            'penalty_other_invoice',
            'orphan_invoice',
        ];

        return collect($audit['risk_breakdown'])
            ->reject(fn (array $risk) => ($risk['key'] ?? null) === 'waiting_settlement')
            ->map(function (array $risk) use ($criticalKeys): array {
            $key = (string) $risk['key'];

            return [
                'key' => 'settlement:' . $key,
                'category' => 'settlement',
                'severity' => in_array($key, $criticalKeys, true) ? 'critical' : 'warning',
                'title' => (string) $risk['label'],
                'description' => $this->settlementDescription($key),
                'recommendation' => 'Hakediş kontrolünde ilgili kayıtları açıp belge ve kesinti dayanağını doğrulayın.',
                'value' => (int) $risk['count'],
                'impact' => (float) $risk['amount'],
                'route_name' => 'mp.settlement-audit',
                'query' => ['risk' => $key],
                'action_label' => 'Hakediş kontrolünü aç',
                'source' => 'settlement_audit',
                'source_label' => 'Hakediş Kontrolü',
            ];
            })->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function campaignSignals(int $userId): array
    {
        $dashboard = $this->campaigns->dashboard($userId, [], 1);

        return collect($dashboard['modules'])
            ->filter(fn (array $module) => (int) $module['risk_count'] > 0)
            ->map(fn (array $module) => [
                'key' => 'campaign:' . $module['campaign_type'],
                'category' => 'campaign',
                'severity' => (float) $module['risk_exposure'] >= 1000 || (int) $module['risk_count'] >= 10
                    ? 'critical'
                    : 'warning',
                'title' => $module['short_label'] . ' karar riski',
                'description' => 'Son kampanya raporunda zarar, maliyet eksikliği veya ürün eşleşme sorunu bulunan öneriler var.',
                'recommendation' => 'Riskli önerileri uygulamadan önce maliyet ve fiyat senaryosunu yeniden doğrulayın.',
                'value' => (int) $module['risk_count'],
                'impact' => (float) $module['risk_exposure'],
                'route_name' => 'campaigns.decision-center',
                'query' => ['type' => $module['campaign_type'], 'decision' => 'risk'],
                'action_label' => 'Kampanya risklerini aç',
                'source' => 'campaign_decision',
                'source_label' => 'Kampanya Karar Merkezi',
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function integrationSignals(int $userId): array
    {
        $guidance = $this->diagnostics->guidanceForUser($userId, [
            'hours' => 168,
            'limit' => 250,
        ]);

        return collect($guidance['items'])->map(function (array $item): array {
            $storeId = (int) ($item['store_id'] ?? 0);

            return [
                'key' => 'integration:' . ($item['category'] ?? 'diagnostic') . ':' . $storeId,
                'category' => 'integration',
                'severity' => (string) ($item['severity'] ?? 'warning'),
                'title' => (string) ($item['title'] ?? 'Entegrasyon kontrolü gerekli'),
                'description' => (string) ($item['why'] ?? 'Entegrasyon verisi beklenen kalite koşullarını karşılamıyor.'),
                'recommendation' => (string) ($item['recommended_action'] ?? 'Entegrasyon ayarlarını ve son senkronu kontrol edin.'),
                'value' => (int) ($item['impact_count'] ?? 1),
                'impact' => 0,
                'route_name' => (string) ($item['route'] ?? 'mp.integrations'),
                'query' => array_filter(['store' => $storeId ?: null]),
                'action_label' => 'Entegrasyonu aç',
                'source' => 'integration_diagnostics',
                'source_label' => 'Entegrasyon Tanıları',
                'store_id' => $storeId ?: null,
                'store_name' => $item['store_name'] ?? null,
            ];
        })->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function notificationSignals(int $userId): array
    {
        if (! $this->notifications->isAvailable()) {
            return [];
        }

        $types = [
            'stock_out',
            'stock_critical',
            'integration_failed',
            'listing_push_failed',
            'product_match_risk',
        ];

        return AppNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->whereIn('type', $types)
            ->where('created_at', '>=', now()->subDays(7))
            ->latest('id')
            ->get()
            ->groupBy(fn (AppNotification $notification) => $notification->type . ':' . ($notification->store_id ?: 0))
            ->map(function (Collection $items, string $groupKey): array {
                /** @var AppNotification $latest */
                $latest = $items->first();
                $category = in_array($latest->type, ['stock_out', 'stock_critical', 'product_match_risk'], true)
                    ? 'product'
                    : 'operations';

                return [
                    'key' => 'notification:' . $groupKey,
                    'category' => $category,
                    'severity' => (string) $latest->severity,
                    'title' => (string) $latest->title,
                    'description' => (string) ($latest->body ?: 'Okunmamış operasyon bildirimi bulunuyor.'),
                    'recommendation' => 'Kaynak kaydı açıp bildirime neden olan sorunu doğrulayın.',
                    'value' => $items->count(),
                    'impact' => 0,
                    'action_url' => $latest->action_url,
                    'action_label' => 'Bildirimi aç',
                    'source' => 'live_notifications',
                    'source_label' => 'Canlı Bildirimler',
                    'store_id' => $latest->store_id,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $signals
     * @return Collection<string, MarketplaceRiskSignalState>
     */
    protected function syncStates(int $userId, Collection $signals, bool $fullSync = false): Collection
    {
        if (! $this->stateTableReady()) {
            return collect();
        }

        $previousStates = MarketplaceRiskSignalState::query()
            ->where('user_id', $userId)
            ->get()
            ->keyBy('fingerprint');

        if ($fullSync) {
            MarketplaceRiskSignalState::query()
                ->where('user_id', $userId)
                ->update([
                    'is_current' => false,
                    'updated_at' => now(),
                ]);
        }

        MarketplaceRiskSignalState::query()
            ->where('user_id', $userId)
            ->where('status', MarketplaceRiskSignalState::STATUS_SNOOZED)
            ->whereNotNull('snoozed_until')
            ->where('snoozed_until', '<=', now())
            ->update([
                'status' => MarketplaceRiskSignalState::STATUS_OPEN,
                'snoozed_until' => null,
                'updated_at' => now(),
            ]);

        foreach ($signals as $signal) {
            $state = MarketplaceRiskSignalState::query()->firstOrNew([
                'user_id' => $userId,
                'fingerprint' => $signal['fingerprint'],
            ]);
            $wasCurrent = (bool) ($previousStates->get($signal['fingerprint'])?->is_current ?? false);

            if (! $state->exists) {
                $state->status = MarketplaceRiskSignalState::STATUS_OPEN;
            } elseif (
                $fullSync
                && $state->status === MarketplaceRiskSignalState::STATUS_RESOLVED
                && ! $wasCurrent
            ) {
                $state->status = MarketplaceRiskSignalState::STATUS_OPEN;
                $state->resolved_at = null;
            }

            $state->fill([
                'signal_key' => $signal['key'],
                'category' => $signal['category'],
                'severity' => $signal['severity'],
                'title' => $signal['title'],
                'is_current' => true,
                'signal_json' => $signal,
                'last_seen_at' => now(),
            ])->save();
        }

        return MarketplaceRiskSignalState::query()
            ->where('user_id', $userId)
            ->whereIn('fingerprint', $signals->pluck('fingerprint'))
            ->get()
            ->keyBy('fingerprint');
    }

    protected function updateState(int $userId, string $fingerprint, array $attributes): MarketplaceRiskSignalState
    {
        $state = MarketplaceRiskSignalState::query()
            ->where('user_id', $userId)
            ->where('fingerprint', $fingerprint)
            ->firstOrFail();

        $state->forceFill($attributes)->save();

        return $state->refresh();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $signals
     */
    protected function publishNotifications(int $userId, Collection $signals): int
    {
        if (! $this->notifications->isAvailable()) {
            return 0;
        }

        $this->pruneGranularRiskNotifications($userId);

        $preferences = $this->notificationPreferences($userId);
        $muted = $preferences['muted_types'];

        return $signals
            ->where('status', 'open')
            ->filter(fn (array $signal) => in_array($signal['severity'], ['critical', 'warning'], true))
            ->sortByDesc('priority_score')
            ->take(8)
            ->filter(function (array $signal) use ($muted): bool {
                $type = $signal['severity'] === 'critical' ? 'risk_critical' : 'risk_warning';

                return ! in_array($type, $muted, true)
                    && ! in_array('risk_category:' . $signal['category'], $muted, true);
            })
            ->groupBy('severity')
            ->map(function (Collection $severitySignals, string $severity) use ($userId): int {
                return $this->upsertRiskSummaryNotification($userId, $severity, $severitySignals);
            })
            ->sum();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $signals
     */
    protected function upsertRiskSummaryNotification(int $userId, string $severity, Collection $signals): int
    {
        $type = $severity === 'critical' ? 'risk_critical' : 'risk_warning';
        $count = $signals->count();
        $topTitles = $signals
            ->pluck('title')
            ->filter()
            ->take(3)
            ->values()
            ->all();
        $extraCount = max(0, $count - count($topTitles));
        $title = $count === 1
            ? (string) $signals->first()['title']
            : sprintf('%d %s risk açık', $count, $severity === 'critical' ? 'kritik' : 'uyarı');
        $body = $count === 1
            ? (string) $signals->first()['description']
            : implode(', ', $topTitles)
                . ($extraCount > 0 ? " ve {$extraCount} sinyal daha" : '')
                . '. Risk Merkezi’nde öncelik sırasıyla kontrol edin.';
        $eventKey = 'risk-summary:' . $severity . ':' . now()->format('Ymd');
        $notification = AppNotification::query()->firstOrNew([
            'user_id' => $userId,
            'event_key' => $eventKey,
        ]);
        $created = ! $notification->exists;

        $notification->forceFill([
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'body' => $body,
            'data_json' => [
                'summary' => true,
                'signal_count' => $count,
                'fingerprints' => $signals->pluck('fingerprint')->values()->all(),
                'top_titles' => $topTitles,
                'category_counts' => $signals->countBy('category')->all(),
                'priority_score' => (float) $signals->max('priority_score'),
                'impact' => round((float) $signals->sum('impact'), 2),
                'value' => (int) $signals->sum('value'),
            ],
            'action_url' => route('mp.risk-center', ['severity' => $severity]),
            'triggered_at' => now(),
        ])->save();

        if ($this->stateTableReady()) {
            MarketplaceRiskSignalState::query()
                ->where('user_id', $userId)
                ->whereIn('fingerprint', $signals->pluck('fingerprint'))
                ->update(['notified_at' => now()]);
        }

        return $created ? 1 : 0;
    }

    protected function pruneGranularRiskNotifications(int $userId): void
    {
        AppNotification::query()
            ->where('user_id', $userId)
            ->whereIn('type', ['risk_critical', 'risk_warning'])
            ->where('event_key', 'like', 'risk-signal:%')
            ->delete();
    }

    /**
     * @return array{sound_enabled: bool, muted_types: array<int, string>}
     */
    public function notificationPreferences(int $userId): array
    {
        $preference = $this->notifications->preferencesForUser($userId);

        return [
            'sound_enabled' => (bool) $preference->sound_enabled,
            'muted_types' => array_values((array) ($preference->muted_types_json ?? [])),
        ];
    }

    public function setNotificationToken(int $userId, string $token, bool $enabled): array
    {
        $preference = $this->notifications->preferencesForUser($userId);
        $muted = collect((array) ($preference->muted_types_json ?? []));

        $muted = $enabled
            ? $muted->reject(fn ($item) => $item === $token)
            : $muted->push($token);

        $this->notifications->setMutedTypes($userId, $muted->filter()->unique()->values()->all());

        return $this->notificationPreferences($userId);
    }

    /**
     * @param  array<string, mixed>  $signal
     * @return array<string, mixed>
     */
    protected function normalizeSignal(array $signal): array
    {
        $category = array_key_exists((string) ($signal['category'] ?? ''), $this->categoryDefinitions())
            ? (string) $signal['category']
            : 'operations';
        $severity = array_key_exists((string) ($signal['severity'] ?? ''), $this->severityDefinitions())
            ? (string) $signal['severity']
            : 'warning';
        $routeName = (string) ($signal['route_name'] ?? '');
        $query = (array) ($signal['query'] ?? []);
        $actionUrl = (string) ($signal['action_url'] ?? '');

        if ($actionUrl === '' && $routeName !== '') {
            $actionUrl = route($routeName, $query);
        }

        $key = (string) ($signal['key'] ?? Str::uuid());
        $fingerprint = hash('sha256', implode('|', [
            $key,
            $category,
            (string) ($signal['store_id'] ?? 0),
        ]));
        $value = max(0, (int) ($signal['value'] ?? 0));
        $impact = round(max(0, (float) ($signal['impact'] ?? 0)), 2);
        $priorityScore = $this->priorityScore($severity, $value, $impact);
        $categoryDefinition = $this->categoryDefinitions()[$category];
        $severityDefinition = $this->severityDefinitions()[$severity];

        return array_merge($signal, [
            'key' => $key,
            'fingerprint' => $fingerprint,
            'category' => $category,
            'category_label' => $categoryDefinition['label'],
            'category_tone' => $categoryDefinition['tone'],
            'severity' => $severity,
            'severity_label' => $severityDefinition['label'],
            'severity_tone' => $severityDefinition['tone'],
            'title' => trim((string) ($signal['title'] ?? 'Risk sinyali')),
            'description' => trim((string) ($signal['description'] ?? 'İncelenmesi gereken bir risk sinyali oluştu.')),
            'recommendation' => trim((string) ($signal['recommendation'] ?? 'Kaynak kaydı inceleyin.')),
            'value' => $value,
            'impact' => $impact,
            'priority_score' => $priorityScore,
            'action_label' => (string) ($signal['action_label'] ?? 'Detayı aç'),
            'action_url' => $actionUrl,
            'available_actions' => $this->availableActionsForSignal($category, $severity),
            'source' => (string) ($signal['source'] ?? 'marketplace'),
            'source_label' => (string) ($signal['source_label'] ?? 'Pazaryeri'),
            'detected_at' => now()->toIso8601String(),
        ]);
    }

    protected function applyState(array $signal, ?MarketplaceRiskSignalState $state): array
    {
        $status = $state?->status ?: MarketplaceRiskSignalState::STATUS_OPEN;
        $definition = $this->statusDefinitions()[$status] ?? $this->statusDefinitions()['open'];

        return array_merge($signal, [
            'state_id' => $state?->id,
            'status' => $status,
            'status_label' => $definition['label'],
            'status_tone' => $definition['tone'],
            'note' => $state?->note,
            'snoozed_until' => $state?->snoozed_until?->toIso8601String(),
            'snoozed_until_label' => $state?->snoozed_until?->format('d.m.Y H:i') ?? '',
            'resolved_at' => $state?->resolved_at?->toIso8601String(),
            'last_seen_at' => $state?->last_seen_at?->toIso8601String() ?? now()->toIso8601String(),
            'last_seen_label' => $state?->last_seen_at?->locale('tr')->diffForHumans() ?? 'Şimdi',
            'notified_at' => $state?->notified_at?->toIso8601String(),
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $signals
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    protected function applyFilters(Collection $signals, array $filters): Collection
    {
        return $signals
            ->when(filled($filters['category'] ?? null), fn (Collection $items) => $items->where('category', $filters['category']))
            ->when(filled($filters['severity'] ?? null), fn (Collection $items) => $items->where('severity', $filters['severity']))
            ->when(
                filled($filters['status'] ?? null) && $filters['status'] !== 'all',
                fn (Collection $items) => $filters['status'] === 'active'
                    ? $items->where('status', 'open')
                    : $items->where('status', $filters['status'])
            )
            ->when(filled($filters['search'] ?? null), function (Collection $items) use ($filters) {
                $search = Str::lower(trim((string) $filters['search']));

                return $items->filter(fn (array $signal) => Str::contains(Str::lower(implode(' ', [
                    $signal['title'],
                    $signal['description'],
                    $signal['recommendation'],
                    $signal['category_label'],
                    $signal['source_label'],
                    $signal['store_name'] ?? '',
                ])), $search));
            })
            ->values();
    }

    protected function sortSignals(Collection $signals, array $filters): Collection
    {
        $field = in_array((string) ($filters['sort_field'] ?? ''), [
            'priority_score',
            'impact',
            'value',
            'title',
            'last_seen_at',
        ], true) ? (string) $filters['sort_field'] : 'priority_score';
        $direction = ($filters['sort_direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $direction === 'asc'
            ? $signals->sortBy($field, SORT_NATURAL | SORT_FLAG_CASE)->values()
            : $signals->sortByDesc($field, SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    protected function summary(Collection $signals): array
    {
        $open = $signals->where('status', 'open');
        $countPressure = (float) $open->sum(function (array $signal) {
            return match ($signal['severity']) {
                'critical' => 4,
                'warning' => 1.5,
                default => 0.5,
            };
        });
        $impactPressure = min(15, log10(max(1, (float) $open->sum('impact')) + 1) * 2.5);
        $score = round(max(0, 100 - min(100, $countPressure + $impactPressure)), 1);

        return [
            'total_count' => $signals->count(),
            'open_count' => $open->count(),
            'critical_count' => $open->where('severity', 'critical')->count(),
            'warning_count' => $open->where('severity', 'warning')->count(),
            'snoozed_count' => $signals->where('status', 'snoozed')->count(),
            'resolved_count' => $signals->where('status', 'resolved')->count(),
            'impact_total' => round((float) $open->sum('impact'), 2),
            'affected_total' => (int) $open->sum('value'),
            'risk_score' => $score,
            'risk_score_label' => $score >= 85 ? 'Güçlü' : ($score >= 65 ? 'Kontrollü' : ($score >= 40 ? 'İncelenmeli' : 'Kritik')),
            'risk_score_tone' => $score >= 85 ? 'success' : ($score >= 65 ? 'info' : ($score >= 40 ? 'warning' : 'danger')),
        ];
    }

    protected function categoryBreakdown(Collection $signals): array
    {
        return collect($this->categoryDefinitions())->map(function (array $definition, string $category) use ($signals): array {
            $items = $signals->where('category', $category)->where('status', 'open');

            return [
                'key' => $category,
                'label' => $definition['label'],
                'tone' => $definition['tone'],
                'count' => $items->count(),
                'critical_count' => $items->where('severity', 'critical')->count(),
                'impact' => round((float) $items->sum('impact'), 2),
                'affected' => (int) $items->sum('value'),
                'max_score' => (float) ($items->max('priority_score') ?? 0),
            ];
        })->filter(fn (array $row) => $row['count'] > 0)->values()->all();
    }

    protected function severityBreakdown(Collection $signals): array
    {
        $open = $signals->where('status', 'open');

        return collect($this->severityDefinitions())->map(function (array $definition, string $severity) use ($open): array {
            $items = $open->where('severity', $severity);

            return [
                'key' => $severity,
                'label' => $definition['label'],
                'tone' => $definition['tone'],
                'count' => $items->count(),
                'impact' => round((float) $items->sum('impact'), 2),
            ];
        })->values()->all();
    }

    /**
     * @param  array<int, string>  $categories
     * @return Collection<int, array<string, mixed>>
     */
    protected function recentStateSignals(int $userId, array $categories): Collection
    {
        if (! $this->stateTableReady()) {
            return collect();
        }

        return MarketplaceRiskSignalState::query()
            ->where('user_id', $userId)
            ->whereIn('category', $categories)
            ->where('is_current', true)
            ->get()
            ->map(function (MarketplaceRiskSignalState $state): array {
                $signal = (array) ($state->signal_json ?? []);

                return $this->applyState($signal, $state);
            })
            ->filter(fn (array $signal) => isset($signal['fingerprint']))
            ->values();
    }

    protected function priorityScore(string $severity, int $value, float $impact): float
    {
        $base = match ($severity) {
            'critical' => 78,
            'warning' => 52,
            default => 28,
        };
        $countScore = min(10, log10(max(1, $value) + 1) * 4);
        $impactScore = min(12, log10(max(1, $impact) + 1) * 3);

        return round(min(100, $base + $countScore + $impactScore), 1);
    }

    protected function settlementDescription(string $key): string
    {
        return match ($key) {
            'waiting_settlement' => 'Siparişlerin kesin hakediş veya finans hareketi henüz oluşmadı.',
            'settlement_difference' => 'Tahmini ve kesin hakediş toplamları toleransın dışında.',
            'commission_difference' => 'Beklenen komisyon ile kesin kesinti arasında açıklanması gereken fark var.',
            'cargo_amount_difference' => 'Kargo faturası ile beklenen kargo maliyeti uyuşmuyor.',
            'desi_difference' => 'Fatura desisi ile sevkiyat desisi farklı.',
            'missing_shipment' => 'Sipariş ile ilişkilendirilmiş sevkiyat kaydı bulunmuyor.',
            'penalty_other_invoice' => 'Ceza veya diğer fatura hareketleri kârlılığı azaltıyor.',
            'return_cargo' => 'İade kargo kesintileri finansal baskı oluşturuyor.',
            'orphan_invoice' => 'Sipariş veya sevkiyatla eşleşmeyen kargo faturaları var.',
            'service_fee_increase' => 'Hizmet bedeli oranı önceki döneme göre yükseldi.',
            default => 'Hakediş ve kesinti verilerinde kontrol edilmesi gereken fark var.',
        };
    }

    /**
     * @return array{date_from: string, date_to: string}
     */
    protected function defaultDateFilters(): array
    {
        return [
            'date_from' => now()->subDays(30)->toDateString(),
            'date_to' => now()->toDateString(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function safely(callable $callback): array
    {
        try {
            return (array) $callback();
        } catch (Throwable) {
            return [];
        }
    }

    protected function stateTableReady(): bool
    {
        try {
            return Schema::hasTable('marketplace_risk_signal_states');
        } catch (Throwable) {
            return false;
        }
    }
}
