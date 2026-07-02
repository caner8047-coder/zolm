<?php

namespace App\Services\Marketplace;

use App\Models\MpProfitActionEvent;
use App\Models\MpProfitActionItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class MarketplaceProfitActionService
{
    public function __construct(
        protected MarketplaceProfitActionBlueprintService $blueprints,
    ) {
    }

    /**
     * @param  array<string, mixed>  $recommendation
     * @param  array<string, mixed>  $filters
     */
    public function trackRecommendation(int $userId, array $recommendation, array $filters): MpProfitActionItem
    {
        $recommendation = $this->recommendationWithBlueprint($recommendation);
        $scopeHash = $this->scopeHash($filters);
        $fingerprint = $this->fingerprint((string) ($recommendation['key'] ?? 'unknown'), $scopeHash);
        $existing = MpProfitActionItem::query()
            ->where('user_id', $userId)
            ->where('fingerprint', $fingerprint)
            ->first();
        $recommendation = $this->withBaselineSignal($recommendation, $existing);

        $payload = [
            'scope_hash' => $scopeHash,
            'action_key' => (string) ($recommendation['key'] ?? 'unknown'),
            'title' => (string) ($recommendation['label'] ?? 'Kar Merkezi aksiyonu'),
            'description' => (string) ($recommendation['description'] ?? ''),
            'action_label' => (string) ($recommendation['action_label'] ?? 'Aksiyonu aç'),
            'route_name' => (string) ($recommendation['route'] ?? 'mp.finance'),
            'query_json' => is_array($recommendation['query'] ?? null) ? $recommendation['query'] : [],
            'filters_json' => $this->normalizeFilters($filters),
            'recommendation_json' => $recommendation,
            'value' => max(0, (int) ($recommendation['value'] ?? 0)),
            'impact' => round((float) ($recommendation['impact'] ?? 0), 2),
            'score' => round((float) ($recommendation['score'] ?? 0), 2),
            'last_seen_at' => now(),
        ];

        if ($existing) {
            $previousStatus = (string) $existing->status;
            $existing->fill($payload);
            $reopenedBySignal = false;

            if (trim((string) $existing->owner_label) === '') {
                $existing->owner_label = $this->defaultOwner($recommendation);
            }

            if ($existing->status === MpProfitActionItem::STATUS_RESOLVED) {
                $existing->status = MpProfitActionItem::STATUS_OPEN;
                $existing->resolved_at = null;
                $reopenedBySignal = true;
            }

            $existing->save();
            $this->recordEvent(
                $existing,
                $reopenedBySignal ? MpProfitActionEvent::TYPE_REOPENED_BY_SIGNAL : MpProfitActionEvent::TYPE_REFRESHED,
                $previousStatus,
                (string) $existing->status,
                ['source' => 'recommendation']
            );

            return $existing;
        }

        $item = MpProfitActionItem::query()->create($payload + [
            'user_id' => $userId,
            'fingerprint' => $fingerprint,
            'status' => MpProfitActionItem::STATUS_OPEN,
            'priority' => $this->defaultPriority($recommendation),
            'due_date' => $this->defaultDueDate($recommendation),
            'owner_label' => $this->defaultOwner($recommendation),
        ]);

        $this->recordEvent($item, MpProfitActionEvent::TYPE_CREATED, null, MpProfitActionItem::STATUS_OPEN, [
            'source' => 'recommendation',
        ]);

        return $item;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function actionItems(
        int $userId,
        array $filters,
        int $limit = 8,
        string $statusFilter = 'active',
        array $actionFilters = [],
        string $sort = 'priority',
        array $currentRecommendations = [],
        ?array $currentCalculationHealth = null
    ): array
    {
        $focus = (string) ($actionFilters['focus'] ?? '');
        $query = MpProfitActionItem::query()
            ->where('user_id', $userId)
            ->where('scope_hash', $this->scopeHash($filters));

        if ($statusFilter === 'resolved') {
            $query->where('status', MpProfitActionItem::STATUS_RESOLVED);
        } elseif ($statusFilter !== 'all') {
            $query->whereIn('status', [
                MpProfitActionItem::STATUS_OPEN,
                MpProfitActionItem::STATUS_IN_PROGRESS,
                MpProfitActionItem::STATUS_SNOOZED,
            ]);
        }

        $this->applyActionListFilters($query, $actionFilters);
        $this->applyActionListSort($query, $sort);

        $queryLimit = $focus === 'plan_gap' ? max($limit * 10, 100) : $limit;
        $items = $query
            ->limit($queryLimit)
            ->get()
            ->map(fn (MpProfitActionItem $item) => $this->serialize($item, $currentRecommendations, $currentCalculationHealth));

        if ($focus === 'plan_gap') {
            $items = $items->filter(fn (array $action) => (int) ($action['playbook_progress']['total_steps'] ?? 0) > 0
                && ! (bool) ($action['playbook_progress']['is_complete'] ?? false));
        }

        return $items->take($limit)->values()->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, string>
     */
    public function ownerOptions(int $userId, array $filters): array
    {
        return MpProfitActionItem::query()
            ->where('user_id', $userId)
            ->where('scope_hash', $this->scopeHash($filters))
            ->whereNotNull('owner_label')
            ->where('owner_label', '!=', '')
            ->select('owner_label')
            ->distinct()
            ->orderBy('owner_label')
            ->pluck('owner_label')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $actionFilters
     * @return array<int, array{name: string, data: array<int, array<string, mixed>>}>
     */
    public function managerReportExportSheets(
        int $userId,
        array $filters,
        string $statusFilter = 'active',
        array $actionFilters = [],
        string $sort = 'priority',
        array $currentRecommendations = [],
        ?array $currentCalculationHealth = null
    ): array
    {
        $summary = $this->summary($userId, $filters);
        $report = $this->managerReport($userId, $filters);
        $actions = $this->actionItems($userId, $filters, 500, $statusFilter, $actionFilters, $sort, $currentRecommendations, $currentCalculationHealth);

        return [
            [
                'name' => 'Yonetici Ozeti',
                'data' => $this->managerSummaryExportRows($summary, $report, $filters, $statusFilter, $actionFilters, $sort),
            ],
            [
                'name' => 'Aksiyonlar',
                'data' => $this->actionExportRows($actions),
            ],
            [
                'name' => 'Komuta Kuyrugu',
                'data' => $this->commandQueueExportRows($this->commandQueue($actions, 12)),
            ],
            [
                'name' => 'Aksiyon Dagilimi',
                'data' => $this->actionDistributionExportRows($report['action_distribution'] ?? []),
            ],
            [
                'name' => 'Aksiyon Sagligi',
                'data' => $this->actionHealthExportRows($report['action_health'] ?? []),
            ],
            [
                'name' => 'Sorumlu Yuku',
                'data' => $this->ownerWorkloadExportRows($report['owner_workload'] ?? []),
            ],
            [
                'name' => 'Kapanis Kalitesi',
                'data' => $this->closureQualityExportRows($report['closure_quality'] ?? []),
            ],
            [
                'name' => 'Haftalik Trend',
                'data' => $this->weeklyTrendExportRows($report['weekly_trend'] ?? []),
            ],
            [
                'name' => 'Yaklasan Hedefler',
                'data' => $this->deadlineExportRows($report['next_deadlines'] ?? []),
            ],
            [
                'name' => 'Son Hareketler',
                'data' => $this->historyExportRows($report['recent_history'] ?? []),
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $actions
     * @return array<int, array<string, mixed>>
     */
    public function commandQueue(array $actions, int $limit = 3): array
    {
        return collect($actions)
            ->filter(fn (array $action) => ($action['status'] ?? '') !== MpProfitActionItem::STATUS_RESOLVED)
            ->map(function (array $action) {
                $priorityWeight = $this->priorityWeight((string) ($action['priority'] ?? MpProfitActionItem::PRIORITY_MEDIUM));
                $impactScore = min(9999, abs((float) ($action['impact'] ?? 0)) / 100);
                $commandScore = ($action['is_overdue'] ?? false ? 100000 : 0)
                    + ($action['is_due_soon'] ?? false ? 50000 : 0)
                    + ($priorityWeight * 10000)
                    + $impactScore
                    + min(999, (float) ($action['score'] ?? 0));

                $action['command_score'] = round($commandScore, 2);
                $action['command_reason'] = $this->commandReason($action);
                $action['command_owner'] = trim((string) ($action['owner_label'] ?? '')) !== ''
                    ? trim((string) $action['owner_label'])
                    : (trim((string) ($action['default_owner'] ?? '')) !== '' ? trim((string) $action['default_owner']) : 'Sahipsiz');
                $action['command_rank_explanation'] = $this->commandRankExplanation($action);
                $action['command_next_step'] = $this->commandNextStep($action);

                return $action;
            })
            ->sortByDesc('command_score')
            ->values()
            ->take(max(1, $limit))
            ->all();
    }

    public function updateMeta(int $userId, int $id, string $priority, ?string $dueDate, ?string $ownerLabel): ?MpProfitActionItem
    {
        if (! in_array($priority, $this->allowedPriorities(), true)) {
            return null;
        }

        $item = MpProfitActionItem::query()
            ->where('user_id', $userId)
            ->whereKey($id)
            ->first();

        if (! $item) {
            return null;
        }

        $previous = [
            'priority' => (string) $item->priority,
            'due_date' => $item->due_date instanceof Carbon ? $item->due_date->toDateString() : null,
            'owner_label' => (string) ($item->owner_label ?? ''),
        ];

        $normalizedDueDate = $this->normalizeDueDate($dueDate);
        $item->priority = $priority;
        $item->due_date = $normalizedDueDate;
        $item->owner_label = trim((string) $ownerLabel) !== '' ? trim((string) $ownerLabel) : null;
        $item->save();

        $current = [
            'priority' => (string) $item->priority,
            'due_date' => $item->due_date instanceof Carbon ? $item->due_date->toDateString() : null,
            'owner_label' => (string) ($item->owner_label ?? ''),
        ];

        if ($previous !== $current) {
            $this->recordEvent($item, MpProfitActionEvent::TYPE_PLAN_UPDATED, (string) $item->status, (string) $item->status, [
                'previous' => $previous,
                'current' => $current,
            ]);
        }

        return $item;
    }

    public function updateNote(int $userId, int $id, ?string $note): ?MpProfitActionItem
    {
        $item = MpProfitActionItem::query()
            ->where('user_id', $userId)
            ->whereKey($id)
            ->first();

        if (! $item) {
            return null;
        }

        $previousNote = (string) ($item->note ?? '');
        $item->note = trim((string) $note) !== '' ? trim((string) $note) : null;
        $item->save();

        if ($previousNote !== (string) ($item->note ?? '')) {
            $this->recordEvent($item, MpProfitActionEvent::TYPE_NOTE_UPDATED, (string) $item->status, (string) $item->status, [
                'previous_has_note' => $previousNote !== '',
                'current_has_note' => (string) ($item->note ?? '') !== '',
            ]);
        }

        return $item;
    }

    public function togglePlaybookStep(int $userId, int $id, int $stepIndex): ?MpProfitActionItem
    {
        $item = MpProfitActionItem::query()
            ->where('user_id', $userId)
            ->whereKey($id)
            ->first();

        if (! $item) {
            return null;
        }

        $recommendation = $this->recommendationWithBlueprint(
            is_array($item->recommendation_json) ? $item->recommendation_json : [],
            (string) $item->action_key
        );
        $steps = is_array($recommendation['playbook_steps'] ?? null)
            ? array_values(array_filter(array_map('strval', $recommendation['playbook_steps'])))
            : [];

        if ($stepIndex < 0 || $stepIndex >= count($steps)) {
            return null;
        }

        $previousCompleted = $this->normalizedCompletedSteps($recommendation, count($steps));
        $completed = $previousCompleted;

        if (in_array($stepIndex, $completed, true)) {
            $completed = array_values(array_diff($completed, [$stepIndex]));
        } else {
            $completed[] = $stepIndex;
        }

        sort($completed);
        $recommendation['completed_playbook_steps'] = $completed;
        $item->recommendation_json = $recommendation;
        $item->save();

        if ($previousCompleted !== $completed) {
            $this->recordEvent($item, MpProfitActionEvent::TYPE_PLAN_UPDATED, (string) $item->status, (string) $item->status, [
                'previous' => [
                    'completed_steps' => $previousCompleted,
                    'completed_count' => count($previousCompleted),
                ],
                'current' => [
                    'completed_steps' => $completed,
                    'completed_count' => count($completed),
                    'total_steps' => count($steps),
                    'changed_step' => $stepIndex + 1,
                    'changed_step_label' => $steps[$stepIndex] ?? '',
                ],
            ]);
        }

        return $item;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summary(int $userId, array $filters): array
    {
        $base = MpProfitActionItem::query()
            ->where('user_id', $userId)
            ->where('scope_hash', $this->scopeHash($filters));

        return [
            'open' => (clone $base)->where('status', MpProfitActionItem::STATUS_OPEN)->count(),
            'in_progress' => (clone $base)->where('status', MpProfitActionItem::STATUS_IN_PROGRESS)->count(),
            'snoozed' => (clone $base)->where('status', MpProfitActionItem::STATUS_SNOOZED)->count(),
            'resolved' => (clone $base)->where('status', MpProfitActionItem::STATUS_RESOLVED)->count(),
            'resolved_impact' => round((float) (clone $base)->where('status', MpProfitActionItem::STATUS_RESOLVED)->sum('impact'), 2),
            'active_impact' => round((float) (clone $base)->where('status', '!=', MpProfitActionItem::STATUS_RESOLVED)->sum('impact'), 2),
            'high_priority' => (clone $base)
                ->where('status', '!=', MpProfitActionItem::STATUS_RESOLVED)
                ->whereIn('priority', [MpProfitActionItem::PRIORITY_HIGH, MpProfitActionItem::PRIORITY_CRITICAL])
                ->count(),
            'overdue' => (clone $base)
                ->where('status', '!=', MpProfitActionItem::STATUS_RESOLVED)
                ->whereDate('due_date', '<', now()->toDateString())
                ->count(),
            'due_soon' => (clone $base)
                ->where('status', '!=', MpProfitActionItem::STATUS_RESOLVED)
                ->whereBetween('due_date', [now()->toDateString(), now()->addDays(3)->toDateString()])
                ->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function managerReport(int $userId, array $filters): array
    {
        $items = MpProfitActionItem::query()
            ->where('user_id', $userId)
            ->where('scope_hash', $this->scopeHash($filters))
            ->get();

        $today = now()->startOfDay();
        $activeItems = $items->filter(fn (MpProfitActionItem $item) => $item->status !== MpProfitActionItem::STATUS_RESOLVED);
        $resolvedSince = now()->subDays(7)->startOfDay();
        $recentlyResolved = $items->filter(
            fn (MpProfitActionItem $item) => $item->status === MpProfitActionItem::STATUS_RESOLVED
                && $item->resolved_at instanceof Carbon
                && $item->resolved_at->gte($resolvedSince)
        );
        $overdueItems = $activeItems->filter(
            fn (MpProfitActionItem $item) => $item->due_date instanceof Carbon
                && $item->due_date->isBefore($today)
        );

        $ownerWorkload = $activeItems
            ->groupBy(fn (MpProfitActionItem $item) => trim((string) $item->owner_label) !== '' ? trim((string) $item->owner_label) : 'Sahipsiz')
            ->map(fn ($group, string $owner) => [
                'owner' => $owner,
                'count' => $group->count(),
                'open' => $group->where('status', MpProfitActionItem::STATUS_OPEN)->count(),
                'in_progress' => $group->where('status', MpProfitActionItem::STATUS_IN_PROGRESS)->count(),
                'snoozed' => $group->where('status', MpProfitActionItem::STATUS_SNOOZED)->count(),
                'overdue' => $group->filter(
                    fn (MpProfitActionItem $item) => $item->due_date instanceof Carbon
                        && $item->due_date->isBefore($today)
                )->count(),
                'impact' => round((float) $group->sum('impact'), 2),
            ])
            ->sortByDesc('impact')
            ->values()
            ->take(5)
            ->all();

        $nextDeadlines = $activeItems
            ->filter(fn (MpProfitActionItem $item) => $item->due_date instanceof Carbon)
            ->sortBy('due_date')
            ->take(4)
            ->map(fn (MpProfitActionItem $item) => $this->serializeReportItem($item))
            ->values()
            ->all();

        $recentEvents = MpProfitActionEvent::query()
            ->with('actionItem')
            ->where('user_id', $userId)
            ->whereHas('actionItem', fn ($query) => $query->where('scope_hash', $this->scopeHash($filters)))
            ->latest('created_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (MpProfitActionEvent $event) => $this->serializeEvent($event))
            ->all();

        $closureQuality = $this->closureQuality($items);
        $weeklyTrend = $this->weeklyTrend($items);
        $actionDistribution = $this->actionDistribution($items, $today);
        $unowned = $activeItems->filter(fn (MpProfitActionItem $item) => trim((string) $item->owner_label) === '')->count();
        $actionHealth = $this->actionHealth(
            $actionDistribution,
            $closureQuality,
            $ownerWorkload,
            $weeklyTrend,
            $unowned,
            round((float) $recentlyResolved->sum('impact'), 2),
            round((float) $overdueItems->sum('impact'), 2)
        );

        return [
            'resolved_7d' => $recentlyResolved->count(),
            'resolved_impact_7d' => round((float) $recentlyResolved->sum('impact'), 2),
            'overdue_impact' => round((float) $overdueItems->sum('impact'), 2),
            'unowned' => $unowned,
            'top_owner' => (string) ($ownerWorkload[0]['owner'] ?? 'Yok'),
            'action_health' => $actionHealth,
            'action_distribution' => $actionDistribution,
            'closure_quality' => $closureQuality,
            'weekly_trend' => $weeklyTrend,
            'owner_workload' => $ownerWorkload,
            'next_deadlines' => $nextDeadlines,
            'recent_history' => $recentEvents !== [] ? $recentEvents : $items
                ->sortByDesc('updated_at')
                ->take(5)
                ->map(fn (MpProfitActionItem $item) => $this->serializeReportItem($item))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>|null
     */
    public function actionTimeline(int $userId, int $id, array $filters): ?array
    {
        $item = MpProfitActionItem::query()
            ->where('user_id', $userId)
            ->where('scope_hash', $this->scopeHash($filters))
            ->whereKey($id)
            ->first();

        if (! $item) {
            return null;
        }

        $events = MpProfitActionEvent::query()
            ->with('actionItem')
            ->where('user_id', $userId)
            ->where('mp_profit_action_item_id', $item->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (MpProfitActionEvent $event) => $this->serializeEvent($event))
            ->all();

        return [
            'action' => $this->serialize($item),
            'events' => $events,
            'event_count' => count($events),
        ];
    }

    public function setStatus(int $userId, int $id, string $status): ?MpProfitActionItem
    {
        if (! in_array($status, [
            MpProfitActionItem::STATUS_OPEN,
            MpProfitActionItem::STATUS_IN_PROGRESS,
            MpProfitActionItem::STATUS_SNOOZED,
            MpProfitActionItem::STATUS_RESOLVED,
        ], true)) {
            return null;
        }

        $item = MpProfitActionItem::query()
            ->where('user_id', $userId)
            ->whereKey($id)
            ->first();

        if (! $item) {
            return null;
        }

        if ($status === MpProfitActionItem::STATUS_RESOLVED && ! $this->hasActionClosureEvidence($item)) {
            return null;
        }

        $previousStatus = (string) $item->status;
        $item->status = $status;
        $item->resolved_at = $status === MpProfitActionItem::STATUS_RESOLVED ? now() : null;
        $item->snoozed_until = $status === MpProfitActionItem::STATUS_SNOOZED ? now()->addDays(3) : null;
        $item->save();

        if ($previousStatus !== $status) {
            $eventMeta = [
                'source' => 'single_action',
            ];

            if ($status === MpProfitActionItem::STATUS_RESOLVED) {
                $eventMeta['closure_evidence'] = $this->closureEvidenceMeta($item);
            }

            $this->recordEvent($item, MpProfitActionEvent::TYPE_STATUS_CHANGED, $previousStatus, $status, $eventMeta);
        }

        return $item;
    }

    public function hasClosureEvidence(int $userId, int $id): bool
    {
        $item = MpProfitActionItem::query()
            ->where('user_id', $userId)
            ->whereKey($id)
            ->first();

        return $item instanceof MpProfitActionItem && $this->hasActionClosureEvidence($item);
    }

    /**
     * @param  array<int, int|string>  $ids
     * @param  array<string, mixed>  $filters
     */
    public function bulkSetStatus(int $userId, array $ids, string $status, array $filters): int
    {
        if (! in_array($status, [
            MpProfitActionItem::STATUS_OPEN,
            MpProfitActionItem::STATUS_IN_PROGRESS,
            MpProfitActionItem::STATUS_SNOOZED,
            MpProfitActionItem::STATUS_RESOLVED,
        ], true)) {
            return 0;
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id) => $id > 0)));

        if ($ids === []) {
            return 0;
        }

        $items = MpProfitActionItem::query()
            ->where('user_id', $userId)
            ->where('scope_hash', $this->scopeHash($filters))
            ->whereIn('id', $ids)
            ->get();

        foreach ($items as $item) {
            if ($status === MpProfitActionItem::STATUS_RESOLVED && ! $this->hasActionClosureEvidence($item)) {
                continue;
            }

            $previousStatus = (string) $item->status;
            $item->status = $status;
            $item->resolved_at = $status === MpProfitActionItem::STATUS_RESOLVED ? now() : null;
            $item->snoozed_until = $status === MpProfitActionItem::STATUS_SNOOZED ? now()->addDays(3) : null;
            $item->save();

            if ($previousStatus !== $status) {
                $eventMeta = [
                    'source' => 'bulk_action',
                ];

                if ($status === MpProfitActionItem::STATUS_RESOLVED) {
                    $eventMeta['closure_evidence'] = $this->closureEvidenceMeta($item);
                }

                $this->recordEvent($item, MpProfitActionEvent::TYPE_STATUS_CHANGED, $previousStatus, $status, $eventMeta);
            }
        }

        return $items->filter(fn (MpProfitActionItem $item) => $item->status === $status)->count();
    }

    /**
     * @param  array<int, int|string>  $ids
     * @param  array<string, mixed>  $filters
     */
    public function bulkApplyRecommendation(int $userId, array $ids, string $recommendation, array $filters): int
    {
        if (! in_array($recommendation, [
            'assign_default_owner',
            'refresh_due_dates',
            'reopen_plan_gaps',
        ], true)) {
            return 0;
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id) => $id > 0)));

        if ($ids === []) {
            return 0;
        }

        $items = MpProfitActionItem::query()
            ->where('user_id', $userId)
            ->where('scope_hash', $this->scopeHash($filters))
            ->whereIn('id', $ids)
            ->get();

        $updatedCount = 0;

        foreach ($items as $item) {
            $updated = match ($recommendation) {
                'assign_default_owner' => $this->applyDefaultOwnerRecommendation($item),
                'refresh_due_dates' => $this->applyDueDateRecommendation($item),
                'reopen_plan_gaps' => $this->applyPlanGapReopenRecommendation($item),
                default => false,
            };

            if ($updated) {
                $updatedCount++;
            }
        }

        return $updatedCount;
    }

    protected function applyDefaultOwnerRecommendation(MpProfitActionItem $item): bool
    {
        if ($item->status === MpProfitActionItem::STATUS_RESOLVED || trim((string) ($item->owner_label ?? '')) !== '') {
            return false;
        }

        $recommendation = $this->recommendationWithBlueprint(
            is_array($item->recommendation_json) ? $item->recommendation_json : [],
            (string) $item->action_key
        );
        $owner = $this->defaultOwner($recommendation);

        if ($owner === null) {
            return false;
        }

        $previous = [
            'priority' => (string) $item->priority,
            'due_date' => $item->due_date instanceof Carbon ? $item->due_date->toDateString() : null,
            'owner_label' => (string) ($item->owner_label ?? ''),
        ];

        $item->owner_label = $owner;
        $item->save();

        $this->recordEvent($item, MpProfitActionEvent::TYPE_PLAN_UPDATED, (string) $item->status, (string) $item->status, [
            'source' => 'bulk_recommendation',
            'recommendation' => 'assign_default_owner',
            'previous' => $previous,
            'current' => [
                'priority' => (string) $item->priority,
                'due_date' => $item->due_date instanceof Carbon ? $item->due_date->toDateString() : null,
                'owner_label' => (string) ($item->owner_label ?? ''),
            ],
        ]);

        return true;
    }

    protected function applyDueDateRecommendation(MpProfitActionItem $item): bool
    {
        if ($item->status === MpProfitActionItem::STATUS_RESOLVED) {
            return false;
        }

        $currentDueDate = $item->due_date instanceof Carbon ? $item->due_date->copy()->startOfDay() : null;

        if ($currentDueDate instanceof Carbon && $currentDueDate->gte(now()->startOfDay())) {
            return false;
        }

        $recommendation = $this->recommendationWithBlueprint(
            is_array($item->recommendation_json) ? $item->recommendation_json : [],
            (string) $item->action_key
        );
        $newDueDate = $this->defaultDueDate($recommendation);
        $previous = [
            'priority' => (string) $item->priority,
            'due_date' => $item->due_date instanceof Carbon ? $item->due_date->toDateString() : null,
            'owner_label' => (string) ($item->owner_label ?? ''),
        ];

        $item->due_date = $newDueDate;
        $item->save();

        $this->recordEvent($item, MpProfitActionEvent::TYPE_PLAN_UPDATED, (string) $item->status, (string) $item->status, [
            'source' => 'bulk_recommendation',
            'recommendation' => 'refresh_due_dates',
            'previous' => $previous,
            'current' => [
                'priority' => (string) $item->priority,
                'due_date' => $item->due_date instanceof Carbon ? $item->due_date->toDateString() : null,
                'owner_label' => (string) ($item->owner_label ?? ''),
            ],
        ]);

        return true;
    }

    protected function applyPlanGapReopenRecommendation(MpProfitActionItem $item): bool
    {
        if ($item->status !== MpProfitActionItem::STATUS_RESOLVED) {
            return false;
        }

        $progress = $this->playbookProgressForItem($item);

        if ((int) ($progress['total_steps'] ?? 0) < 1 || (bool) ($progress['is_complete'] ?? false)) {
            return false;
        }

        $previousStatus = (string) $item->status;
        $item->status = MpProfitActionItem::STATUS_OPEN;
        $item->resolved_at = null;
        $item->snoozed_until = null;
        $item->save();

        $this->recordEvent($item, MpProfitActionEvent::TYPE_STATUS_CHANGED, $previousStatus, (string) $item->status, [
            'source' => 'bulk_recommendation',
            'recommendation' => 'reopen_plan_gaps',
            'plan_percent' => (float) ($progress['percent'] ?? 0),
            'next_step_label' => (string) ($progress['next_step_label'] ?? ''),
        ]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function scopeHash(array $filters): string
    {
        return sha1(json_encode($this->normalizeFilters($filters), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }

    protected function hasActionClosureEvidence(MpProfitActionItem $item): bool
    {
        return trim((string) ($item->note ?? '')) !== '';
    }

    /**
     * @return array<string, mixed>
     */
    protected function playbookProgressForItem(MpProfitActionItem $item): array
    {
        $recommendation = $this->recommendationWithBlueprint(
            is_array($item->recommendation_json) ? $item->recommendation_json : [],
            (string) $item->action_key
        );
        $steps = is_array($recommendation['playbook_steps'] ?? null)
            ? array_values(array_filter(array_map('strval', $recommendation['playbook_steps'])))
            : [];

        return $this->playbookProgress($recommendation, $steps);
    }

    /**
     * @return array<string, mixed>
     */
    protected function closureSummary(MpProfitActionItem $item): array
    {
        $note = trim((string) ($item->note ?? ''));
        $owner = trim((string) ($item->owner_label ?? ''));
        $dueDate = $item->due_date instanceof Carbon ? $item->due_date : null;
        $resolvedAt = $item->resolved_at instanceof Carbon ? $item->resolved_at : null;
        $isResolved = $item->status === MpProfitActionItem::STATUS_RESOLVED && $resolvedAt instanceof Carbon;
        $hasNote = $note !== '';
        $hasOwner = $owner !== '';
        $hasDueDate = $dueDate instanceof Carbon;
        $onTime = $isResolved
            && $dueDate instanceof Carbon
            && $resolvedAt->copy()->startOfDay()->lte($dueDate->copy()->endOfDay());
        $completedSignals = collect([$hasNote, $hasOwner, $hasDueDate, $onTime])
            ->filter()
            ->count();
        $qualityPercent = $isResolved ? round(($completedSignals / 4) * 100, 1) : 0.0;
        $playbookProgress = $this->playbookProgressForItem($item);

        return [
            'is_resolved' => $isResolved,
            'has_note' => $hasNote,
            'has_owner' => $hasOwner,
            'has_due_date' => $hasDueDate,
            'on_time' => $onTime,
            'quality_percent' => $qualityPercent,
            'quality_label' => $this->closureQualityLabel($qualityPercent, $isResolved ? 1 : 0),
            'plan_percent' => (float) ($playbookProgress['percent'] ?? 0),
            'plan_completed_steps' => (int) ($playbookProgress['completed_count'] ?? 0),
            'plan_total_steps' => (int) ($playbookProgress['total_steps'] ?? 0),
            'plan_complete' => (bool) ($playbookProgress['is_complete'] ?? false),
            'plan_next_step' => (string) ($playbookProgress['next_step_label'] ?? ''),
            'note_excerpt' => $note !== '' ? Str::limit($note, 140) : '',
            'owner_label' => $owner,
            'due_date' => $dueDate?->toDateString(),
            'resolved_at' => $resolvedAt?->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function closureEvidenceMeta(MpProfitActionItem $item): array
    {
        $summary = $this->closureSummary($item);

        return array_merge($summary, [
            'note' => trim((string) ($item->note ?? '')),
            'captured_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function normalizeFilters(array $filters): array
    {
        return [
            'marketplace' => (string) ($filters['marketplace'] ?? ''),
            'store_id' => (string) ($filters['store_id'] ?? ''),
            'legal_entity_id' => (string) ($filters['legal_entity_id'] ?? ''),
            'date_from' => (string) ($filters['date_from'] ?? ''),
            'date_to' => (string) ($filters['date_to'] ?? ''),
        ];
    }

    protected function fingerprint(string $key, string $scopeHash): string
    {
        return sha1($scopeHash . '|' . $key);
    }

    /**
     * @return array<int, string>
     */
    protected function allowedPriorities(): array
    {
        return [
            MpProfitActionItem::PRIORITY_LOW,
            MpProfitActionItem::PRIORITY_MEDIUM,
            MpProfitActionItem::PRIORITY_HIGH,
            MpProfitActionItem::PRIORITY_CRITICAL,
        ];
    }

    /**
     * @param  array<string, mixed>  $actionFilters
     */
    protected function applyActionListFilters(Builder $query, array $actionFilters): void
    {
        $priority = (string) ($actionFilters['priority'] ?? '');
        $owner = trim((string) ($actionFilters['owner'] ?? ''));
        $focus = (string) ($actionFilters['focus'] ?? '');

        if ($priority !== '' && in_array($priority, $this->allowedPriorities(), true)) {
            $query->where('priority', $priority);
        }

        if ($owner === '__unowned') {
            $query->where(fn (Builder $ownerQuery) => $ownerQuery
                ->whereNull('owner_label')
                ->orWhere('owner_label', ''));
        } elseif ($owner !== '') {
            $query->where('owner_label', $owner);
        }

        match ($focus) {
            'overdue' => $query
                ->where('status', '!=', MpProfitActionItem::STATUS_RESOLVED)
                ->whereDate('due_date', '<', now()->toDateString()),
            'due_soon' => $query
                ->where('status', '!=', MpProfitActionItem::STATUS_RESOLVED)
                ->whereBetween('due_date', [now()->toDateString(), now()->addDays(3)->toDateString()]),
            'unowned' => $query
                ->where('status', '!=', MpProfitActionItem::STATUS_RESOLVED)
                ->where(fn (Builder $ownerQuery) => $ownerQuery
                    ->whereNull('owner_label')
                    ->orWhere('owner_label', '')),
            'high_priority' => $query->whereIn('priority', [
                MpProfitActionItem::PRIORITY_HIGH,
                MpProfitActionItem::PRIORITY_CRITICAL,
            ]),
            'plan_gap' => null,
            default => null,
        };
    }

    protected function applyActionListSort(Builder $query, string $sort): void
    {
        match ($sort) {
            'due_date' => $query
                ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('due_date')
                ->orderByRaw("CASE priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END")
                ->orderByDesc('score')
                ->latest('updated_at'),
            'impact' => $query
                ->orderByDesc('impact')
                ->orderByRaw("CASE priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END")
                ->orderByDesc('score')
                ->latest('updated_at'),
            'updated' => $query
                ->latest('updated_at')
                ->orderByDesc('score'),
            default => $query
                ->orderByRaw("CASE WHEN status != 'resolved' AND due_date IS NOT NULL AND due_date < CURDATE() THEN 0 ELSE 1 END")
                ->orderByRaw("CASE status WHEN 'in_progress' THEN 1 WHEN 'open' THEN 2 WHEN 'snoozed' THEN 3 ELSE 4 END")
                ->orderByRaw("CASE priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END")
                ->orderByDesc('score')
                ->latest('updated_at'),
        };
    }

    /**
     * @param  \Illuminate\Support\Collection<int, MpProfitActionItem>  $items
     * @return array<string, mixed>
     */
    protected function closureQuality($items): array
    {
        $resolvedItems = $items
            ->filter(fn (MpProfitActionItem $item) => $item->status === MpProfitActionItem::STATUS_RESOLVED
                && $item->resolved_at instanceof Carbon)
            ->values();

        $resolvedCount = $resolvedItems->count();
        $withNote = $resolvedItems->filter(fn (MpProfitActionItem $item) => trim((string) $item->note) !== '')->count();
        $withOwner = $resolvedItems->filter(fn (MpProfitActionItem $item) => trim((string) $item->owner_label) !== '')->count();
        $withDueDate = $resolvedItems->filter(fn (MpProfitActionItem $item) => $item->due_date instanceof Carbon)->count();
        $onTime = $resolvedItems->filter(fn (MpProfitActionItem $item) => $item->due_date instanceof Carbon
            && $item->resolved_at instanceof Carbon
            && $item->resolved_at->copy()->startOfDay()->lte($item->due_date->copy()->endOfDay()))
            ->count();
        $planProgressRows = $resolvedItems
            ->map(fn (MpProfitActionItem $item) => $this->playbookProgressForItem($item))
            ->filter(fn (array $progress) => (int) ($progress['total_steps'] ?? 0) > 0)
            ->values();
        $planScopeCount = $planProgressRows->count();
        $withPlanComplete = $planProgressRows
            ->filter(fn (array $progress) => (bool) ($progress['is_complete'] ?? false))
            ->count();
        $averagePlanPercent = $planProgressRows->avg(fn (array $progress) => (float) ($progress['percent'] ?? 0));

        $averageResolutionDays = $resolvedItems
            ->filter(fn (MpProfitActionItem $item) => $item->created_at instanceof Carbon && $item->resolved_at instanceof Carbon)
            ->map(fn (MpProfitActionItem $item) => $item->resolved_at->diffInHours($item->created_at) / 24)
            ->avg();

        $qualityPercent = $resolvedCount > 0
            ? round((($withNote + $withOwner + $withDueDate + $onTime) / ($resolvedCount * 4)) * 100, 1)
            : 0.0;

        return [
            'resolved_count' => $resolvedCount,
            'with_note' => $withNote,
            'with_owner' => $withOwner,
            'with_due_date' => $withDueDate,
            'on_time' => $onTime,
            'with_note_percent' => $this->percentOf($withNote, $resolvedCount),
            'with_owner_percent' => $this->percentOf($withOwner, $resolvedCount),
            'with_due_date_percent' => $this->percentOf($withDueDate, $resolvedCount),
            'on_time_percent' => $this->percentOf($onTime, $withDueDate),
            'plan_scope_count' => $planScopeCount,
            'with_plan_complete' => $withPlanComplete,
            'with_plan_complete_percent' => $this->percentOf($withPlanComplete, $planScopeCount),
            'average_plan_percent' => $averagePlanPercent !== null ? round((float) $averagePlanPercent, 1) : 0.0,
            'quality_percent' => $qualityPercent,
            'quality_label' => $this->closureQualityLabel($qualityPercent, $resolvedCount),
            'average_resolution_days' => $averageResolutionDays !== null ? round((float) $averageResolutionDays, 1) : 0.0,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, MpProfitActionItem>  $items
     * @return array<string, mixed>
     */
    protected function actionDistribution($items, Carbon $today): array
    {
        $totalCount = $items->count();
        $activeItems = $items
            ->filter(fn (MpProfitActionItem $item) => $item->status !== MpProfitActionItem::STATUS_RESOLVED)
            ->values();
        $activeCount = $activeItems->count();

        $statusRows = collect([
            ['key' => MpProfitActionItem::STATUS_OPEN, 'label' => 'Yeni', 'tone' => 'default'],
            ['key' => MpProfitActionItem::STATUS_IN_PROGRESS, 'label' => 'İnceleniyor', 'tone' => 'info'],
            ['key' => MpProfitActionItem::STATUS_SNOOZED, 'label' => 'Ertelendi', 'tone' => 'warning'],
            ['key' => MpProfitActionItem::STATUS_RESOLVED, 'label' => 'Çözüldü', 'tone' => 'success'],
        ])->map(function (array $row) use ($items, $totalCount) {
            $group = $items->where('status', $row['key']);

            return array_merge($row, [
                'count' => $group->count(),
                'percent' => $this->percentOf($group->count(), $totalCount),
                'impact' => round((float) $group->sum('impact'), 2),
            ]);
        })->values()->all();

        $priorityRows = collect([
            ['key' => MpProfitActionItem::PRIORITY_CRITICAL, 'label' => 'Kritik', 'tone' => 'danger'],
            ['key' => MpProfitActionItem::PRIORITY_HIGH, 'label' => 'Yüksek', 'tone' => 'danger'],
            ['key' => MpProfitActionItem::PRIORITY_MEDIUM, 'label' => 'Orta', 'tone' => 'warning'],
            ['key' => MpProfitActionItem::PRIORITY_LOW, 'label' => 'Düşük', 'tone' => 'success'],
        ])->map(function (array $row) use ($activeItems, $activeCount) {
            $group = $activeItems->where('priority', $row['key']);

            return array_merge($row, [
                'count' => $group->count(),
                'percent' => $this->percentOf($group->count(), $activeCount),
                'impact' => round((float) $group->sum('impact'), 2),
            ]);
        })->values()->all();

        $agingRows = collect([
            [
                'key' => 'overdue',
                'label' => 'Geciken',
                'tone' => 'danger',
                'items' => $activeItems->filter(fn (MpProfitActionItem $item) => $item->due_date instanceof Carbon
                    && $item->due_date->copy()->startOfDay()->lt($today)),
            ],
            [
                'key' => 'today',
                'label' => 'Bugün',
                'tone' => 'warning',
                'items' => $activeItems->filter(fn (MpProfitActionItem $item) => $item->due_date instanceof Carbon
                    && $item->due_date->isSameDay($today)),
            ],
            [
                'key' => 'due_soon',
                'label' => '3 gün içinde',
                'tone' => 'info',
                'items' => $activeItems->filter(fn (MpProfitActionItem $item) => $item->due_date instanceof Carbon
                    && $item->due_date->copy()->startOfDay()->gt($today)
                    && $item->due_date->copy()->startOfDay()->lte($today->copy()->addDays(3))),
            ],
            [
                'key' => 'planned',
                'label' => 'Planlı',
                'tone' => 'success',
                'items' => $activeItems->filter(fn (MpProfitActionItem $item) => $item->due_date instanceof Carbon
                    && $item->due_date->copy()->startOfDay()->gt($today->copy()->addDays(3))),
            ],
            [
                'key' => 'unscheduled',
                'label' => 'Hedefsiz',
                'tone' => 'warning',
                'items' => $activeItems->filter(fn (MpProfitActionItem $item) => ! ($item->due_date instanceof Carbon)),
            ],
        ])->map(function (array $row) use ($activeCount) {
            $group = $row['items'];
            unset($row['items']);

            return array_merge($row, [
                'count' => $group->count(),
                'percent' => $this->percentOf($group->count(), $activeCount),
                'impact' => round((float) $group->sum('impact'), 2),
            ]);
        })->values()->all();

        return [
            'total_count' => $totalCount,
            'active_count' => $activeCount,
            'status_rows' => $statusRows,
            'priority_rows' => $priorityRows,
            'aging_rows' => $agingRows,
            'dominant_status' => collect($statusRows)->sortByDesc('count')->first(),
            'top_priority' => collect($priorityRows)->sortByDesc('count')->first(),
            'top_aging' => collect($agingRows)->sortByDesc('count')->first(),
        ];
    }

    /**
     * @param  array<string, mixed>  $actionDistribution
     * @param  array<string, mixed>  $closureQuality
     * @param  array<int, array<string, mixed>>  $ownerWorkload
     * @param  array<int, array<string, mixed>>  $weeklyTrend
     * @return array<string, mixed>
     */
    protected function actionHealth(
        array $actionDistribution,
        array $closureQuality,
        array $ownerWorkload,
        array $weeklyTrend,
        int $unownedCount,
        float $resolvedImpact7d,
        float $overdueImpact
    ): array {
        $activeCount = (int) ($actionDistribution['active_count'] ?? 0);
        $totalCount = (int) ($actionDistribution['total_count'] ?? 0);
        $agingRows = collect((array) ($actionDistribution['aging_rows'] ?? []));
        $priorityRows = collect((array) ($actionDistribution['priority_rows'] ?? []));
        $overdueRow = (array) ($agingRows->firstWhere('key', 'overdue') ?? []);
        $criticalRow = (array) ($priorityRows->firstWhere('key', MpProfitActionItem::PRIORITY_CRITICAL) ?? []);
        $highRow = (array) ($priorityRows->firstWhere('key', MpProfitActionItem::PRIORITY_HIGH) ?? []);
        $dueSoonRow = (array) ($agingRows->firstWhere('key', 'due_soon') ?? []);
        $overdueCount = (int) ($overdueRow['count'] ?? 0);
        $highPriorityCount = (int) ($criticalRow['count'] ?? 0) + (int) ($highRow['count'] ?? 0);
        $highPriorityImpact = (float) ($criticalRow['impact'] ?? 0) + (float) ($highRow['impact'] ?? 0);
        $dueSoonCount = (int) ($dueSoonRow['count'] ?? 0);
        $planScopeCount = (int) ($closureQuality['plan_scope_count'] ?? 0);
        $withPlanComplete = (int) ($closureQuality['with_plan_complete'] ?? 0);
        $planGapCount = max(0, $planScopeCount - $withPlanComplete);
        $planGapPercent = $this->percentOf($planGapCount, $planScopeCount);
        $closureQualityPercent = (float) ($closureQuality['quality_percent'] ?? 0);
        $resolvedCount = (int) ($closureQuality['resolved_count'] ?? 0);
        $created6w = (int) collect($weeklyTrend)->sum('created');
        $resolved6w = (int) collect($weeklyTrend)->sum('resolved');
        $tempoPercent = $created6w > 0
            ? min(100.0, $this->percentOf($resolved6w, $created6w))
            : ($resolved6w > 0 ? 100.0 : 0.0);
        $overduePercent = $this->percentOf($overdueCount, $activeCount);
        $highPriorityPercent = $this->percentOf($highPriorityCount, $activeCount);
        $unownedPercent = $this->percentOf($unownedCount, $activeCount);
        $score = 100.0
            - ($overduePercent * 0.25)
            - ($highPriorityPercent * 0.18)
            - ($unownedPercent * 0.14)
            - ($planGapPercent * 0.14);

        if ($resolvedCount > 0) {
            $score -= (100 - $closureQualityPercent) * 0.20;
        } elseif ($activeCount > 0) {
            $score -= 8;
        }

        if ($created6w > 0 && $resolved6w < $created6w) {
            $score -= (100 - $tempoPercent) * 0.10;
        }

        $score = round(max(0, min(100, $score)), 1);
        $tone = $this->actionHealthTone($score);
        $drivers = [
            [
                'key' => 'overdue',
                'label' => 'Gecikme baskısı',
                'count' => $overdueCount,
                'percent' => $overduePercent,
                'impact' => $overdueImpact,
                'tone' => $overdueCount > 0 ? 'danger' : 'success',
                'detail' => 'Hedef tarihi geçmiş aktif aksiyonlar.',
            ],
            [
                'key' => 'high_priority',
                'label' => 'Yüksek öncelik',
                'count' => $highPriorityCount,
                'percent' => $highPriorityPercent,
                'impact' => round($highPriorityImpact, 2),
                'tone' => $highPriorityCount > 0 ? 'warning' : 'success',
                'detail' => 'Kritik veya yüksek öncelikli açık iş yükü.',
            ],
            [
                'key' => 'unowned',
                'label' => 'Sahipsiz yük',
                'count' => $unownedCount,
                'percent' => $unownedPercent,
                'impact' => 0.0,
                'tone' => $unownedCount > 0 ? 'warning' : 'success',
                'detail' => 'Sorumlu ataması bekleyen aktif aksiyonlar.',
            ],
            [
                'key' => 'plan_gap',
                'label' => 'Plan açığı',
                'count' => $planGapCount,
                'percent' => $planGapPercent,
                'impact' => 0.0,
                'tone' => $planGapCount > 0 ? 'warning' : 'success',
                'detail' => 'Plan adımları tamamlanmadan kapanan aksiyonlar.',
            ],
        ];
        $nextMoves = $this->actionHealthNextMoves(
            $overdueCount,
            $planGapCount,
            $unownedCount,
            $highPriorityCount,
            $dueSoonCount
        );

        return [
            'score' => $score,
            'label' => $this->actionHealthLabel($score, $totalCount),
            'tone' => $tone,
            'headline' => $this->actionHealthHeadline($score, $overdueCount, $planGapCount, $unownedCount),
            'active_count' => $activeCount,
            'total_count' => $totalCount,
            'cards' => [
                [
                    'key' => 'health_score',
                    'label' => 'Aksiyon sağlığı',
                    'value' => $score,
                    'format' => 'percent',
                    'tone' => $tone,
                    'detail' => $activeCount . ' aktif iş',
                ],
                [
                    'key' => 'closure_quality',
                    'label' => 'Kapanış kalitesi',
                    'value' => $closureQualityPercent,
                    'format' => 'percent',
                    'tone' => $closureQualityPercent >= 80 ? 'success' : ($closureQualityPercent >= 60 ? 'warning' : 'danger'),
                    'detail' => $resolvedCount . ' kapanış',
                ],
                [
                    'key' => 'plan_completion',
                    'label' => 'Plan tamamlama',
                    'value' => (float) ($closureQuality['with_plan_complete_percent'] ?? 0),
                    'format' => 'percent',
                    'tone' => $planGapCount > 0 ? 'warning' : 'success',
                    'detail' => $withPlanComplete . '/' . $planScopeCount . ' planlı kapanış',
                ],
                [
                    'key' => 'weekly_tempo',
                    'label' => 'Haftalık tempo',
                    'value' => $tempoPercent,
                    'format' => 'percent',
                    'tone' => $resolved6w >= $created6w ? 'success' : ($tempoPercent >= 60 ? 'warning' : 'danger'),
                    'detail' => $resolved6w . '/' . $created6w . ' kapanan/açılan',
                ],
            ],
            'drivers' => $drivers,
            'next_moves' => $nextMoves,
            'tempo' => [
                'created_6w' => $created6w,
                'resolved_6w' => $resolved6w,
                'resolved_impact_7d' => $resolvedImpact7d,
                'overdue_impact' => $overdueImpact,
                'closure_rate_percent' => $tempoPercent,
                'weeks' => array_map(fn (array $week) => [
                    'label' => (string) ($week['label'] ?? ''),
                    'created' => (int) ($week['created'] ?? 0),
                    'resolved' => (int) ($week['resolved'] ?? 0),
                    'net' => (int) ($week['resolved'] ?? 0) - (int) ($week['created'] ?? 0),
                    'closure_rate_percent' => $this->percentOf((int) ($week['resolved'] ?? 0), max(1, (int) ($week['created'] ?? 0))),
                ], $weeklyTrend),
            ],
            'owner_focus' => array_slice($ownerWorkload, 0, 3),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function actionHealthNextMoves(
        int $overdueCount,
        int $planGapCount,
        int $unownedCount,
        int $highPriorityCount,
        int $dueSoonCount
    ): array {
        $moves = collect([
            [
                'key' => 'overdue',
                'title' => 'Geciken hedefleri yenile',
                'description' => 'Hedef tarihi geçmiş seçili işleri yeni hedef tarihine çekin.',
                'count' => $overdueCount,
                'tone' => 'danger',
                'focus_key' => 'overdue',
                'recommendation_key' => 'refresh_due_dates',
                'bulk_status' => null,
                'apply_label' => 'Seçili hedefleri yenile',
                'priority' => 400,
            ],
            [
                'key' => 'plan_gap',
                'title' => 'Plan açığı kapanışlarını yeniden aç',
                'description' => 'Plan adımları bitmeden kapanmış işleri plana devam etmek üzere açın.',
                'count' => $planGapCount,
                'tone' => 'warning',
                'focus_key' => 'plan_gap',
                'recommendation_key' => 'reopen_plan_gaps',
                'bulk_status' => null,
                'apply_label' => 'Seçili işleri aç',
                'priority' => 350,
            ],
            [
                'key' => 'unowned',
                'title' => 'Sahipsiz işleri önerilen ekibe ata',
                'description' => 'Sorumlusu olmayan seçili aksiyonları blueprint ekibine devredin.',
                'count' => $unownedCount,
                'tone' => 'warning',
                'focus_key' => 'unowned',
                'recommendation_key' => 'assign_default_owner',
                'bulk_status' => null,
                'apply_label' => 'Seçili sorumluları ata',
                'priority' => 300,
            ],
            [
                'key' => 'high_priority',
                'title' => 'Yüksek öncelikli işleri incelemeye al',
                'description' => 'Kritik ve yüksek öncelikli seçili işleri aktif takip durumuna geçirin.',
                'count' => $highPriorityCount,
                'tone' => 'warning',
                'focus_key' => 'high_priority',
                'recommendation_key' => null,
                'bulk_status' => MpProfitActionItem::STATUS_IN_PROGRESS,
                'apply_label' => 'Seçili işleri başlat',
                'priority' => 200,
            ],
            [
                'key' => 'due_soon',
                'title' => 'Yaklaşan hedeflere odaklan',
                'description' => 'Önümüzdeki 3 gün içinde hedefi gelen işleri komuta kuyruğuna alın.',
                'count' => $dueSoonCount,
                'tone' => 'info',
                'focus_key' => 'due_soon',
                'recommendation_key' => null,
                'bulk_status' => null,
                'apply_label' => '',
                'priority' => 100,
            ],
        ])
            ->filter(fn (array $move) => (int) ($move['count'] ?? 0) > 0)
            ->sortByDesc('priority')
            ->values();

        return [
            'primary' => $moves->first(),
            'alternatives' => $moves->slice(1)->values()->all(),
            'empty_label' => 'Aksiyon sağlığı temiz',
            'empty_description' => 'Seçili kapsamda acil toplu hamle gerektiren risk sürücüsü görünmüyor.',
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, MpProfitActionItem>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function weeklyTrend($items): array
    {
        $firstWeek = now()->startOfWeek()->subWeeks(5);

        return collect(range(0, 5))
            ->map(function (int $weekOffset) use ($items, $firstWeek) {
                $weekStart = $firstWeek->copy()->addWeeks($weekOffset)->startOfWeek();
                $weekEnd = $weekStart->copy()->endOfWeek();
                $createdItems = $items->filter(fn (MpProfitActionItem $item) => $item->created_at instanceof Carbon
                    && $item->created_at->betweenIncluded($weekStart, $weekEnd));
                $resolvedItems = $items->filter(fn (MpProfitActionItem $item) => $item->resolved_at instanceof Carbon
                    && $item->resolved_at->betweenIncluded($weekStart, $weekEnd));
                $activeAtEnd = $items->filter(fn (MpProfitActionItem $item) => $item->created_at instanceof Carbon
                    && $item->created_at->lte($weekEnd)
                    && (! ($item->resolved_at instanceof Carbon) || $item->resolved_at->gt($weekEnd)));

                return [
                    'label' => $weekStart->format('d.m') . '-' . $weekEnd->format('d.m'),
                    'week_start' => $weekStart->toDateString(),
                    'week_end' => $weekEnd->toDateString(),
                    'created' => $createdItems->count(),
                    'resolved' => $resolvedItems->count(),
                    'resolved_impact' => round((float) $resolvedItems->sum('impact'), 2),
                    'active_impact' => round((float) $activeAtEnd->sum('impact'), 2),
                ];
            })
            ->values()
            ->all();
    }

    protected function percentOf(int|float $value, int|float $total): float
    {
        return $total > 0 ? round(((float) $value / (float) $total) * 100, 1) : 0.0;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $report
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $actionFilters
     * @return array<int, array<string, mixed>>
     */
    protected function managerSummaryExportRows(array $summary, array $report, array $filters, string $statusFilter, array $actionFilters, string $sort): array
    {
        $range = trim((string) ($filters['date_from'] ?? '') . ' - ' . (string) ($filters['date_to'] ?? ''), ' -');

        return [
            ['Alan' => 'Rapor zamanı', 'Değer' => now()->format('d.m.Y H:i')],
            ['Alan' => 'Rapor aralığı', 'Değer' => $range !== '' ? $range : 'Tüm dönem'],
            ['Alan' => 'Aksiyon sekmesi', 'Değer' => $this->statusFilterLabel($statusFilter)],
            ['Alan' => 'Aksiyon filtreleri', 'Değer' => $this->actionFilterSummary($actionFilters, $sort)],
            ['Alan' => 'Yeni aksiyon', 'Değer' => (int) ($summary['open'] ?? 0)],
            ['Alan' => 'İncelenen aksiyon', 'Değer' => (int) ($summary['in_progress'] ?? 0)],
            ['Alan' => 'Ertelenen aksiyon', 'Değer' => (int) ($summary['snoozed'] ?? 0)],
            ['Alan' => 'Çözülen aksiyon', 'Değer' => (int) ($summary['resolved'] ?? 0)],
            ['Alan' => 'Açık finansal etki', 'Değer' => (float) ($summary['active_impact'] ?? 0)],
            ['Alan' => 'Çözülen finansal etki', 'Değer' => (float) ($summary['resolved_impact'] ?? 0)],
            ['Alan' => 'Son 7 gün kapanan etki', 'Değer' => (float) ($report['resolved_impact_7d'] ?? 0)],
            ['Alan' => 'Yüksek öncelik', 'Değer' => (int) ($summary['high_priority'] ?? 0)],
            ['Alan' => 'Geciken aksiyon', 'Değer' => (int) ($summary['overdue'] ?? 0)],
            ['Alan' => '3 gün içinde hedef', 'Değer' => (int) ($summary['due_soon'] ?? 0)],
            ['Alan' => 'Sahipsiz iş', 'Değer' => (int) ($report['unowned'] ?? 0)],
            ['Alan' => 'Yoğun sorumlu', 'Değer' => (string) ($report['top_owner'] ?? 'Yok')],
            ['Alan' => 'Kapanış kalitesi', 'Değer' => (float) ($report['closure_quality']['quality_percent'] ?? 0)],
            ['Alan' => 'Kapanış etiketi', 'Değer' => (string) ($report['closure_quality']['quality_label'] ?? 'Veri yok')],
            ['Alan' => 'Planı tamamlanan kapanış', 'Değer' => (float) ($report['closure_quality']['with_plan_complete_percent'] ?? 0)],
            ['Alan' => 'Ortalama kapanış günü', 'Değer' => (float) ($report['closure_quality']['average_resolution_days'] ?? 0)],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $actions
     * @return array<int, array<string, mixed>>
     */
    protected function actionExportRows(array $actions): array
    {
        if ($actions === []) {
            return [[
                'Başlık' => 'Aksiyon bulunamadı',
                'Durum' => '',
                'Öncelik' => '',
                'Sorumlu' => '',
                'Önerilen sorumlu' => '',
                'Hedef tarih' => '',
                'Gecikti' => '',
                'Yakın hedef' => '',
                'Etki' => 0,
                'Skor' => 0,
                'Sinyal değeri' => 0,
                'İlk sinyal' => 0,
                'Güncel sinyal' => 0,
                'Kapanan sinyal' => 0,
                'Sinyal iyileşme %' => 0,
                'İlk güven skoru' => '',
                'Güncel güven skoru' => '',
                'Güven farkı' => '',
                'Kapanış notu var' => '',
                'Kapanış kalitesi %' => '',
                'Kapanış planı %' => '',
                'Kapanış planı tamamlandı' => '',
                'Kapanış özeti' => '',
                'Kapanış notu' => '',
                'Son güncelleme' => '',
                'Aksiyon' => '',
                'Plan özeti' => '',
                'Plan ilerleme %' => 0,
                'Tamamlanan adım' => 0,
                'Toplam adım' => 0,
                'Sıradaki adım' => '',
                'Başarı ölçütü' => '',
                'İşlem adımları' => '',
            ]];
        }

        return array_map(fn (array $action) => [
            'Başlık' => (string) ($action['title'] ?? ''),
            'Durum' => $this->statusLabel((string) ($action['status'] ?? MpProfitActionItem::STATUS_OPEN)),
            'Öncelik' => (string) ($action['priority_label'] ?? ''),
            'Sorumlu' => trim((string) ($action['owner_label'] ?? '')) !== '' ? trim((string) ($action['owner_label'] ?? '')) : 'Sahipsiz',
            'Önerilen sorumlu' => (string) ($action['default_owner'] ?? ''),
            'Hedef tarih' => (string) ($action['due_date'] ?? ''),
            'Gecikti' => (bool) ($action['is_overdue'] ?? false) ? 'Evet' : 'Hayır',
            'Yakın hedef' => (bool) ($action['is_due_soon'] ?? false) ? 'Evet' : 'Hayır',
            'Etki' => (float) ($action['impact'] ?? 0),
            'Skor' => (float) ($action['score'] ?? 0),
            'Sinyal değeri' => (int) ($action['value'] ?? 0),
            'İlk sinyal' => (int) ($action['signal_progress']['initial_value'] ?? 0),
            'Güncel sinyal' => (int) ($action['signal_progress']['current_value'] ?? 0),
            'Kapanan sinyal' => (int) ($action['signal_progress']['closed_value'] ?? 0),
            'Sinyal iyileşme %' => (float) ($action['signal_progress']['improvement_percent'] ?? 0),
            'İlk güven skoru' => $action['signal_progress']['health_score_before'] ?? '',
            'Güncel güven skoru' => $action['signal_progress']['health_score_now'] ?? '',
            'Güven farkı' => $action['signal_progress']['health_score_delta'] ?? '',
            'Kapanış notu var' => (bool) ($action['closure_summary']['has_note'] ?? false) ? 'Evet' : 'Hayır',
            'Kapanış kalitesi %' => (float) ($action['closure_summary']['quality_percent'] ?? 0),
            'Kapanış planı %' => (float) ($action['closure_summary']['plan_percent'] ?? 0),
            'Kapanış planı tamamlandı' => (bool) ($action['closure_summary']['plan_complete'] ?? false) ? 'Evet' : 'Hayır',
            'Kapanış özeti' => (string) ($action['closure_summary']['quality_label'] ?? ''),
            'Kapanış notu' => (string) ($action['closure_summary']['note_excerpt'] ?? ''),
            'Son güncelleme' => (string) ($action['updated_at'] ?? ''),
            'Aksiyon' => (string) ($action['action_label'] ?? ''),
            'Plan özeti' => (string) ($action['plan_summary'] ?? ''),
            'Plan ilerleme %' => (float) ($action['playbook_progress']['percent'] ?? 0),
            'Tamamlanan adım' => (int) ($action['playbook_progress']['completed_count'] ?? 0),
            'Toplam adım' => (int) ($action['playbook_progress']['total_steps'] ?? 0),
            'Sıradaki adım' => (string) ($action['playbook_progress']['next_step_label'] ?? ''),
            'Başarı ölçütü' => (string) ($action['success_metric'] ?? ''),
            'İşlem adımları' => implode(' | ', is_array($action['playbook_steps'] ?? null) ? $action['playbook_steps'] : []),
        ], $actions);
    }

    /**
     * @param  array<int, array<string, mixed>>  $actions
     * @return array<int, array<string, mixed>>
     */
    protected function commandQueueExportRows(array $actions): array
    {
        if ($actions === []) {
            return [[
                'Sıra' => 0,
                'Başlık' => 'Komuta kuyruğu yok',
                'Gerekçe' => '',
                'Neden bu sırada?' => '',
                'Önerilen ilk adım' => '',
                'Durum' => '',
                'Öncelik' => '',
                'Sorumlu' => '',
                'Hedef tarih' => '',
                'Gecikti' => '',
                'Yakın hedef' => '',
                'Etki' => 0,
                'Skor' => 0,
                'Komuta skoru' => 0,
                'Aksiyon' => '',
                'Plan özeti' => '',
                'Plan ilerleme %' => 0,
                'Sıradaki adım' => '',
                'Başarı ölçütü' => '',
                'Detay rotası' => '',
            ]];
        }

        return array_map(fn (array $action, int $index) => [
            'Sıra' => $index + 1,
            'Başlık' => (string) ($action['title'] ?? ''),
            'Gerekçe' => (string) ($action['command_reason'] ?? ''),
            'Neden bu sırada?' => (string) ($action['command_rank_explanation'] ?? ''),
            'Önerilen ilk adım' => (string) ($action['command_next_step'] ?? ''),
            'Durum' => $this->statusLabel((string) ($action['status'] ?? MpProfitActionItem::STATUS_OPEN)),
            'Öncelik' => (string) ($action['priority_label'] ?? ''),
            'Sorumlu' => (string) ($action['command_owner'] ?? 'Sahipsiz'),
            'Hedef tarih' => (string) ($action['due_date'] ?? ''),
            'Gecikti' => (bool) ($action['is_overdue'] ?? false) ? 'Evet' : 'Hayır',
            'Yakın hedef' => (bool) ($action['is_due_soon'] ?? false) ? 'Evet' : 'Hayır',
            'Etki' => (float) ($action['impact'] ?? 0),
            'Skor' => (float) ($action['score'] ?? 0),
            'Komuta skoru' => (float) ($action['command_score'] ?? 0),
            'Aksiyon' => (string) ($action['action_label'] ?? ''),
            'Plan özeti' => (string) ($action['plan_summary'] ?? ''),
            'Plan ilerleme %' => (float) ($action['playbook_progress']['percent'] ?? 0),
            'Sıradaki adım' => (string) ($action['playbook_progress']['next_step_label'] ?? ''),
            'Başarı ölçütü' => (string) ($action['success_metric'] ?? ''),
            'Detay rotası' => (string) ($action['route'] ?? ''),
        ], $actions, array_keys($actions));
    }

    /**
     * @param  array<string, mixed>  $distribution
     * @return array<int, array<string, mixed>>
     */
    protected function actionDistributionExportRows(array $distribution): array
    {
        $rows = [];

        foreach ((array) ($distribution['status_rows'] ?? []) as $row) {
            $rows[] = [
                'Kategori' => 'Durum dağılımı',
                'Başlık' => (string) ($row['label'] ?? ''),
                'Adet' => (int) ($row['count'] ?? 0),
                'Pay %' => (float) ($row['percent'] ?? 0),
                'Etki' => (float) ($row['impact'] ?? 0),
            ];
        }

        foreach ((array) ($distribution['priority_rows'] ?? []) as $row) {
            $rows[] = [
                'Kategori' => 'Öncelik baskısı',
                'Başlık' => (string) ($row['label'] ?? ''),
                'Adet' => (int) ($row['count'] ?? 0),
                'Pay %' => (float) ($row['percent'] ?? 0),
                'Etki' => (float) ($row['impact'] ?? 0),
            ];
        }

        foreach ((array) ($distribution['aging_rows'] ?? []) as $row) {
            $rows[] = [
                'Kategori' => 'Hedef yaşlanması',
                'Başlık' => (string) ($row['label'] ?? ''),
                'Adet' => (int) ($row['count'] ?? 0),
                'Pay %' => (float) ($row['percent'] ?? 0),
                'Etki' => (float) ($row['impact'] ?? 0),
            ];
        }

        return $rows !== [] ? $rows : [[
            'Kategori' => 'Aksiyon dağılımı',
            'Başlık' => 'Aksiyon yok',
            'Adet' => 0,
            'Pay %' => 0,
            'Etki' => 0,
        ]];
    }

    /**
     * @param  array<int, array<string, mixed>>  $ownerWorkload
     * @return array<int, array<string, mixed>>
     */
    protected function ownerWorkloadExportRows(array $ownerWorkload): array
    {
        if ($ownerWorkload === []) {
            return [[
                'Sorumlu' => 'Açık aksiyon yükü yok',
                'Açık iş' => 0,
                'Yeni' => 0,
                'İnceleniyor' => 0,
                'Ertelendi' => 0,
                'Geciken' => 0,
                'Etki' => 0,
            ]];
        }

        return array_map(fn (array $row) => [
            'Sorumlu' => (string) ($row['owner'] ?? 'Sahipsiz'),
            'Açık iş' => (int) ($row['count'] ?? 0),
            'Yeni' => (int) ($row['open'] ?? 0),
            'İnceleniyor' => (int) ($row['in_progress'] ?? 0),
            'Ertelendi' => (int) ($row['snoozed'] ?? 0),
            'Geciken' => (int) ($row['overdue'] ?? 0),
            'Etki' => (float) ($row['impact'] ?? 0),
        ], $ownerWorkload);
    }

    /**
     * @param  array<string, mixed>  $closureQuality
     * @return array<int, array<string, mixed>>
     */
    protected function closureQualityExportRows(array $closureQuality): array
    {
        return [
            ['Metrik' => 'Kapanış kalitesi (%)', 'Değer' => (float) ($closureQuality['quality_percent'] ?? 0), 'Açıklama' => (string) ($closureQuality['quality_label'] ?? 'Veri yok')],
            ['Metrik' => 'Çözülen aksiyon', 'Değer' => (int) ($closureQuality['resolved_count'] ?? 0), 'Açıklama' => 'Kapanış kalitesi kapsamındaki toplam çözüm.'],
            ['Metrik' => 'Notlu kapanış (%)', 'Değer' => (float) ($closureQuality['with_note_percent'] ?? 0), 'Açıklama' => (int) ($closureQuality['with_note'] ?? 0) . ' aksiyon notla kapandı.'],
            ['Metrik' => 'Sorumlusu olan kapanış (%)', 'Değer' => (float) ($closureQuality['with_owner_percent'] ?? 0), 'Açıklama' => (int) ($closureQuality['with_owner'] ?? 0) . ' aksiyonda sorumlu vardı.'],
            ['Metrik' => 'Hedef tarihli kapanış (%)', 'Değer' => (float) ($closureQuality['with_due_date_percent'] ?? 0), 'Açıklama' => (int) ($closureQuality['with_due_date'] ?? 0) . ' aksiyonda hedef tarih vardı.'],
            ['Metrik' => 'Zamanında kapanış (%)', 'Değer' => (float) ($closureQuality['on_time_percent'] ?? 0), 'Açıklama' => (int) ($closureQuality['on_time'] ?? 0) . ' aksiyon hedef tarihinde kapandı.'],
            ['Metrik' => 'Planı tamamlanan kapanış (%)', 'Değer' => (float) ($closureQuality['with_plan_complete_percent'] ?? 0), 'Açıklama' => (int) ($closureQuality['with_plan_complete'] ?? 0) . '/' . (int) ($closureQuality['plan_scope_count'] ?? 0) . ' planlı kapanış tamamlandı.'],
            ['Metrik' => 'Ortalama plan ilerleme (%)', 'Değer' => (float) ($closureQuality['average_plan_percent'] ?? 0), 'Açıklama' => 'Çözülen planlı aksiyonlardaki ortalama playbook ilerlemesi.'],
            ['Metrik' => 'Ortalama kapanış günü', 'Değer' => (float) ($closureQuality['average_resolution_days'] ?? 0), 'Açıklama' => 'Oluşturma ile çözüm arasındaki ortalama süre.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $actionHealth
     * @return array<int, array<string, mixed>>
     */
    protected function actionHealthExportRows(array $actionHealth): array
    {
        if ($actionHealth === []) {
            return [[
                'Kategori' => 'Aksiyon sağlığı',
                'Başlık' => 'Sağlık verisi yok',
                'Değer' => 0,
                'Pay %' => 0,
                'Etki' => 0,
                'Açıklama' => '',
            ]];
        }

        $rows = [[
            'Kategori' => 'Genel skor',
            'Başlık' => (string) ($actionHealth['label'] ?? 'Aksiyon sağlığı'),
            'Değer' => (float) ($actionHealth['score'] ?? 0),
            'Pay %' => (float) ($actionHealth['score'] ?? 0),
            'Etki' => 0,
            'Açıklama' => (string) ($actionHealth['headline'] ?? ''),
        ]];

        $primaryMove = is_array($actionHealth['next_moves']['primary'] ?? null)
            ? $actionHealth['next_moves']['primary']
            : null;

        if ($primaryMove) {
            $rows[] = [
                'Kategori' => 'Önerilen hamle',
                'Başlık' => (string) ($primaryMove['title'] ?? ''),
                'Değer' => (int) ($primaryMove['count'] ?? 0),
                'Pay %' => 0,
                'Etki' => 0,
                'Açıklama' => $this->actionMoveExportDescription($primaryMove),
            ];
        } else {
            $rows[] = [
                'Kategori' => 'Önerilen hamle',
                'Başlık' => (string) ($actionHealth['next_moves']['empty_label'] ?? 'Acil hamle yok'),
                'Değer' => 0,
                'Pay %' => 0,
                'Etki' => 0,
                'Açıklama' => (string) ($actionHealth['next_moves']['empty_description'] ?? ''),
            ];
        }

        foreach ((array) ($actionHealth['next_moves']['alternatives'] ?? []) as $move) {
            $rows[] = [
                'Kategori' => 'Alternatif hamle',
                'Başlık' => (string) ($move['title'] ?? ''),
                'Değer' => (int) ($move['count'] ?? 0),
                'Pay %' => 0,
                'Etki' => 0,
                'Açıklama' => $this->actionMoveExportDescription($move),
            ];
        }

        foreach ((array) ($actionHealth['cards'] ?? []) as $card) {
            $rows[] = [
                'Kategori' => 'Sağlık kartı',
                'Başlık' => (string) ($card['label'] ?? ''),
                'Değer' => (float) ($card['value'] ?? 0),
                'Pay %' => (float) ($card['value'] ?? 0),
                'Etki' => 0,
                'Açıklama' => (string) ($card['detail'] ?? ''),
            ];
        }

        foreach ((array) ($actionHealth['drivers'] ?? []) as $driver) {
            $rows[] = [
                'Kategori' => 'Risk sürücüsü',
                'Başlık' => (string) ($driver['label'] ?? ''),
                'Değer' => (int) ($driver['count'] ?? 0),
                'Pay %' => (float) ($driver['percent'] ?? 0),
                'Etki' => (float) ($driver['impact'] ?? 0),
                'Açıklama' => (string) ($driver['detail'] ?? ''),
            ];
        }

        foreach ((array) ($actionHealth['tempo']['weeks'] ?? []) as $week) {
            $rows[] = [
                'Kategori' => 'Haftalık tempo',
                'Başlık' => (string) ($week['label'] ?? ''),
                'Değer' => (int) ($week['resolved'] ?? 0),
                'Pay %' => (float) ($week['closure_rate_percent'] ?? 0),
                'Etki' => (int) ($week['net'] ?? 0),
                'Açıklama' => (int) ($week['created'] ?? 0) . ' açılan / ' . (int) ($week['resolved'] ?? 0) . ' kapanan',
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $move
     */
    protected function actionMoveExportDescription(array $move): string
    {
        $parts = [
            (string) ($move['description'] ?? ''),
            'Odak: ' . (string) ($move['focus_key'] ?? ''),
        ];

        if (! empty($move['recommendation_key'])) {
            $parts[] = 'Akıllı öneri: ' . (string) $move['recommendation_key'];
        }

        if (! empty($move['bulk_status'])) {
            $parts[] = 'Toplu durum: ' . $this->statusLabel((string) $move['bulk_status']);
        }

        if (! empty($move['apply_label'])) {
            $parts[] = 'Buton: ' . (string) $move['apply_label'];
        }

        return collect($parts)->filter(fn (string $part) => trim($part) !== '')->implode(' | ');
    }

    /**
     * @param  array<int, array<string, mixed>>  $weeklyTrend
     * @return array<int, array<string, mixed>>
     */
    protected function weeklyTrendExportRows(array $weeklyTrend): array
    {
        if ($weeklyTrend === []) {
            return [[
                'Hafta' => 'Trend verisi yok',
                'Başlangıç' => '',
                'Bitiş' => '',
                'Açılan' => 0,
                'Kapanan' => 0,
                'Kapanan etki' => 0,
                'Hafta sonu açık etki' => 0,
            ]];
        }

        return array_map(fn (array $row) => [
            'Hafta' => (string) ($row['label'] ?? ''),
            'Başlangıç' => (string) ($row['week_start'] ?? ''),
            'Bitiş' => (string) ($row['week_end'] ?? ''),
            'Açılan' => (int) ($row['created'] ?? 0),
            'Kapanan' => (int) ($row['resolved'] ?? 0),
            'Kapanan etki' => (float) ($row['resolved_impact'] ?? 0),
            'Hafta sonu açık etki' => (float) ($row['active_impact'] ?? 0),
        ], $weeklyTrend);
    }

    /**
     * @param  array<int, array<string, mixed>>  $deadlines
     * @return array<int, array<string, mixed>>
     */
    protected function deadlineExportRows(array $deadlines): array
    {
        if ($deadlines === []) {
            return [[
                'Başlık' => 'Yaklaşan hedef yok',
                'Durum' => '',
                'Öncelik' => '',
                'Sorumlu' => '',
                'Hedef tarih' => '',
                'Gecikti' => '',
                'Etki' => 0,
            ]];
        }

        return array_map(fn (array $row) => [
            'Başlık' => (string) ($row['title'] ?? ''),
            'Durum' => (string) ($row['status_label'] ?? ''),
            'Öncelik' => (string) ($row['priority_label'] ?? ''),
            'Sorumlu' => (string) ($row['owner'] ?? 'Sahipsiz'),
            'Hedef tarih' => (string) ($row['due_date'] ?? ''),
            'Gecikti' => (bool) ($row['is_overdue'] ?? false) ? 'Evet' : 'Hayır',
            'Etki' => (float) ($row['impact'] ?? 0),
        ], $deadlines);
    }

    /**
     * @param  array<int, array<string, mixed>>  $history
     * @return array<int, array<string, mixed>>
     */
    protected function historyExportRows(array $history): array
    {
        if ($history === []) {
            return [[
                'Olay' => 'Aksiyon geçmişi yok',
                'Başlık' => '',
                'Durum' => '',
                'Sorumlu' => '',
                'Etki' => 0,
                'Hedef tarih' => '',
                'Zaman' => '',
                'Kapanış kalitesi' => '',
                'Kapanış planı %' => '',
                'Kapanış notu' => '',
            ]];
        }

        return array_map(fn (array $row) => [
            'Olay' => (string) ($row['event_label'] ?? 'Son durum'),
            'Başlık' => (string) ($row['title'] ?? ''),
            'Durum' => (string) ($row['status_label'] ?? ''),
            'Sorumlu' => (string) ($row['owner'] ?? 'Sahipsiz'),
            'Etki' => (float) ($row['impact'] ?? 0),
            'Hedef tarih' => (string) ($row['due_date'] ?? ''),
            'Zaman' => (string) (($row['created_at'] ?? null) ?: ($row['updated_at'] ?? '')),
            'Kapanış kalitesi' => (string) ($row['closure_quality']['quality_label'] ?? $row['closure_summary']['quality_label'] ?? ''),
            'Kapanış planı %' => (float) ($row['closure_quality']['plan_percent'] ?? $row['closure_summary']['plan_percent'] ?? 0),
            'Kapanış notu' => (string) ($row['closure_note'] ?? $row['closure_summary']['note_excerpt'] ?? ''),
        ], $history);
    }

    /**
     * @param  array<string, mixed>  $actionFilters
     */
    protected function actionFilterSummary(array $actionFilters, string $sort): string
    {
        $parts = [];
        $priority = (string) ($actionFilters['priority'] ?? '');
        $owner = trim((string) ($actionFilters['owner'] ?? ''));
        $focus = (string) ($actionFilters['focus'] ?? '');

        if ($priority !== '' && in_array($priority, $this->allowedPriorities(), true)) {
            $parts[] = 'Öncelik: ' . $this->priorityLabel($priority);
        }

        if ($owner === '__unowned') {
            $parts[] = 'Sorumlu: Sahipsiz';
        } elseif ($owner !== '') {
            $parts[] = 'Sorumlu: ' . $owner;
        }

        if ($focus !== '') {
            $parts[] = 'Odak: ' . $this->focusLabel($focus);
        }

        $parts[] = 'Sıralama: ' . $this->sortLabel($sort);

        return implode(' · ', $parts);
    }

    /**
     * @param  array<string, mixed>  $recommendation
     * @return array<string, mixed>
     */
    protected function recommendationWithBlueprint(array $recommendation, ?string $key = null): array
    {
        $actionKey = (string) ($key ?: ($recommendation['key'] ?? 'unknown'));
        $blueprint = $this->blueprints->forKey($actionKey);
        $merged = array_merge($blueprint, $recommendation);

        foreach (['default_owner', 'default_priority', 'plan_summary', 'success_metric'] as $field) {
            if (trim((string) ($merged[$field] ?? '')) === '' && isset($blueprint[$field])) {
                $merged[$field] = $blueprint[$field];
            }
        }

        if (! array_key_exists('due_in_days', $merged) && array_key_exists('due_in_days', $blueprint)) {
            $merged['due_in_days'] = $blueprint['due_in_days'];
        }

        if (! is_array($merged['playbook_steps'] ?? null) || $merged['playbook_steps'] === []) {
            $merged['playbook_steps'] = $blueprint['playbook_steps'] ?? [];
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $recommendation
     * @return array<string, mixed>
     */
    protected function withBaselineSignal(array $recommendation, ?MpProfitActionItem $existing): array
    {
        $existingRecommendation = is_array($existing?->recommendation_json) ? $existing->recommendation_json : [];
        $existingBaseline = $existingRecommendation['baseline_signal'] ?? null;

        if (is_array($existingBaseline) && $existingBaseline !== []) {
            $recommendation['baseline_signal'] = $existingBaseline;

            return $recommendation;
        }

        $recommendation['baseline_signal'] = [
            'value' => max(0, (int) ($recommendation['value'] ?? 0)),
            'impact' => round((float) ($recommendation['impact'] ?? 0), 2),
            'score' => round((float) ($recommendation['score'] ?? 0), 2),
            'health_score' => data_get($recommendation, 'calculation_health_at_tracking.score'),
            'health_label' => data_get($recommendation, 'calculation_health_at_tracking.score_label'),
            'tracked_at' => now()->format('d.m.Y H:i'),
        ];

        return $recommendation;
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $currentRecommendations
     * @return array<string, mixed>|null
     */
    protected function currentRecommendationFor(string $key, array $currentRecommendations): ?array
    {
        foreach ($currentRecommendations as $recommendationKey => $recommendation) {
            if (! is_array($recommendation)) {
                continue;
            }

            if ((string) $recommendationKey === $key || (string) ($recommendation['key'] ?? '') === $key) {
                return $recommendation;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $recommendation
     * @param  array<int|string, array<string, mixed>>  $currentRecommendations
     * @param  array<string, mixed>|null  $currentCalculationHealth
     * @return array<string, mixed>
     */
    protected function signalProgress(array $recommendation, string $key, array $currentRecommendations, ?array $currentCalculationHealth): array
    {
        $baseline = is_array($recommendation['baseline_signal'] ?? null) ? $recommendation['baseline_signal'] : [];
        $currentRecommendation = $this->currentRecommendationFor($key, $currentRecommendations);

        $initialValue = max(0, (int) ($baseline['value'] ?? $recommendation['value'] ?? 0));
        $currentValue = $currentRecommendation !== null
            ? max(0, (int) ($currentRecommendation['value'] ?? 0))
            : 0;
        $closedValue = max(0, $initialValue - $currentValue);
        $improvementPercent = $initialValue > 0 ? round(($closedValue / $initialValue) * 100, 1) : 0.0;
        $trackedHealthScore = is_numeric($baseline['health_score'] ?? null) ? (float) $baseline['health_score'] : null;
        $currentHealthScore = is_array($currentCalculationHealth) && is_numeric($currentCalculationHealth['score'] ?? null)
            ? (float) $currentCalculationHealth['score']
            : null;

        return [
            'tracked' => $initialValue > 0 || $currentRecommendation !== null,
            'initial_value' => $initialValue,
            'current_value' => $currentValue,
            'closed_value' => $closedValue,
            'improvement_percent' => $improvementPercent,
            'health_score_before' => $trackedHealthScore,
            'health_score_now' => $currentHealthScore,
            'health_score_delta' => $trackedHealthScore !== null && $currentHealthScore !== null
                ? round($currentHealthScore - $trackedHealthScore, 1)
                : null,
            'tracked_at' => (string) ($baseline['tracked_at'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $recommendation
     */
    protected function defaultPriority(array $recommendation): string
    {
        $defaultPriority = (string) ($recommendation['default_priority'] ?? '');

        if ($defaultPriority !== '' && in_array($defaultPriority, $this->allowedPriorities(), true)) {
            return $defaultPriority;
        }

        $tone = (string) ($recommendation['tone'] ?? '');
        $score = (float) ($recommendation['score'] ?? 0);

        if ($tone === 'danger' && $score >= 80) {
            return MpProfitActionItem::PRIORITY_CRITICAL;
        }

        if ($tone === 'danger' || $tone === 'warning' || $score >= 60) {
            return MpProfitActionItem::PRIORITY_HIGH;
        }

        return MpProfitActionItem::PRIORITY_MEDIUM;
    }

    /**
     * @param  array<string, mixed>  $recommendation
     */
    protected function defaultDueDate(array $recommendation): Carbon
    {
        $dueInDays = (int) ($recommendation['due_in_days'] ?? -1);

        if ($dueInDays >= 0) {
            return now()->addDays($dueInDays)->startOfDay();
        }

        return match ($this->defaultPriority($recommendation)) {
            MpProfitActionItem::PRIORITY_CRITICAL => now()->addDay()->startOfDay(),
            MpProfitActionItem::PRIORITY_HIGH => now()->addDays(2)->startOfDay(),
            MpProfitActionItem::PRIORITY_LOW => now()->addDays(10)->startOfDay(),
            default => now()->addDays(5)->startOfDay(),
        };
    }

    /**
     * @param  array<string, mixed>  $recommendation
     */
    protected function defaultOwner(array $recommendation): ?string
    {
        $owner = trim((string) ($recommendation['default_owner'] ?? ''));

        return $owner !== '' ? $owner : null;
    }

    protected function normalizeDueDate(?string $dueDate): ?Carbon
    {
        if (! $dueDate || trim($dueDate) === '') {
            return null;
        }

        try {
            return Carbon::parse($dueDate)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function priorityLabel(string $priority): string
    {
        return match ($priority) {
            MpProfitActionItem::PRIORITY_CRITICAL => 'Kritik',
            MpProfitActionItem::PRIORITY_HIGH => 'Yüksek',
            MpProfitActionItem::PRIORITY_LOW => 'Düşük',
            default => 'Normal',
        };
    }

    protected function priorityTone(string $priority): string
    {
        return match ($priority) {
            MpProfitActionItem::PRIORITY_CRITICAL => 'danger',
            MpProfitActionItem::PRIORITY_HIGH => 'warning',
            MpProfitActionItem::PRIORITY_LOW => 'default',
            default => 'success',
        };
    }

    protected function priorityWeight(string $priority): int
    {
        return match ($priority) {
            MpProfitActionItem::PRIORITY_CRITICAL => 4,
            MpProfitActionItem::PRIORITY_HIGH => 3,
            MpProfitActionItem::PRIORITY_MEDIUM => 2,
            MpProfitActionItem::PRIORITY_LOW => 1,
            default => 0,
        };
    }

    /**
     * @param  array<string, mixed>  $action
     */
    protected function commandReason(array $action): string
    {
        if ($action['is_overdue'] ?? false) {
            return 'Hedef tarihi geçti';
        }

        if (($action['priority'] ?? '') === MpProfitActionItem::PRIORITY_CRITICAL) {
            return 'Kritik öncelik';
        }

        if ($action['is_due_soon'] ?? false) {
            return 'Hedef tarihi yaklaşıyor';
        }

        if (trim((string) ($action['owner_label'] ?? '')) === '') {
            return 'Sorumlu ataması bekliyor';
        }

        if (($action['priority'] ?? '') === MpProfitActionItem::PRIORITY_HIGH) {
            return 'Yüksek öncelik';
        }

        return 'Finansal etki yüksek';
    }

    /**
     * @param  array<string, mixed>  $action
     */
    protected function commandRankExplanation(array $action): string
    {
        $parts = [];
        $priority = (string) ($action['priority_label'] ?? $this->priorityLabel((string) ($action['priority'] ?? MpProfitActionItem::PRIORITY_MEDIUM)));
        $impact = round((float) ($action['impact'] ?? 0), 2);
        $score = round((float) ($action['score'] ?? 0), 1);

        if ($action['is_overdue'] ?? false) {
            $parts[] = 'hedef tarihi geçti';
        } elseif ($action['is_due_soon'] ?? false) {
            $parts[] = 'hedef tarihi yaklaşıyor';
        }

        if (in_array((string) ($action['priority'] ?? ''), [MpProfitActionItem::PRIORITY_CRITICAL, MpProfitActionItem::PRIORITY_HIGH], true)) {
            $parts[] = mb_strtolower($priority, 'UTF-8') . ' öncelik';
        }

        if ($impact !== 0.0) {
            $parts[] = 'etki ' . number_format($impact, 2, ',', '.');
        }

        if (trim((string) ($action['owner_label'] ?? '')) === '') {
            $parts[] = 'sorumlu ataması bekliyor';
        }

        if ($score > 0) {
            $parts[] = 'skor ' . number_format($score, 1, ',', '.');
        }

        return $parts !== []
            ? 'Bu aksiyon ' . implode(', ', $parts) . ' olduğu için üst sırada.'
            : 'Bu aksiyon seçili kapsamda takip gerektirdiği için komuta kuyruğunda.';
    }

    /**
     * @param  array<string, mixed>  $action
     */
    protected function commandNextStep(array $action): string
    {
        $progress = is_array($action['playbook_progress'] ?? null) ? $action['playbook_progress'] : [];
        $nextStepLabel = trim((string) ($progress['next_step_label'] ?? ''));

        if ($nextStepLabel !== '') {
            return $nextStepLabel;
        }

        $steps = is_array($action['playbook_steps'] ?? null)
            ? array_values(array_filter(array_map('strval', $action['playbook_steps'])))
            : [];

        if ($steps !== []) {
            return $steps[0];
        }

        $planSummary = trim((string) ($action['plan_summary'] ?? ''));

        if ($planSummary !== '') {
            return $planSummary;
        }

        $actionLabel = trim((string) ($action['action_label'] ?? ''));

        return $actionLabel !== '' ? $actionLabel : 'Aksiyonu incelemeye al ve sonucu notla kaydet.';
    }

    /**
     * @param  array<string, mixed>  $recommendation
     * @return array<int, int>
     */
    protected function normalizedCompletedSteps(array $recommendation, int $totalSteps): array
    {
        if ($totalSteps < 1) {
            return [];
        }

        return collect((array) ($recommendation['completed_playbook_steps'] ?? []))
            ->map(fn ($step) => (int) $step)
            ->filter(fn (int $step) => $step >= 0 && $step < $totalSteps)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $recommendation
     * @param  array<int, string>  $steps
     * @return array<string, mixed>
     */
    protected function playbookProgress(array $recommendation, array $steps): array
    {
        $totalSteps = count($steps);
        $completedSteps = $this->normalizedCompletedSteps($recommendation, $totalSteps);
        $completedCount = count($completedSteps);
        $nextStepIndex = null;

        foreach (array_keys($steps) as $index) {
            if (! in_array((int) $index, $completedSteps, true)) {
                $nextStepIndex = (int) $index;
                break;
            }
        }

        return [
            'total_steps' => $totalSteps,
            'completed_steps' => $completedSteps,
            'completed_count' => $completedCount,
            'percent' => $totalSteps > 0 ? round(($completedCount / $totalSteps) * 100, 1) : 0.0,
            'is_complete' => $totalSteps > 0 && $completedCount >= $totalSteps,
            'next_step_index' => $nextStepIndex,
            'next_step_label' => $nextStepIndex !== null ? (string) ($steps[$nextStepIndex] ?? '') : '',
            'rows' => array_map(fn (string $step, int $index) => [
                'index' => $index,
                'number' => $index + 1,
                'label' => $step,
                'completed' => in_array($index, $completedSteps, true),
            ], $steps, array_keys($steps)),
        ];
    }

    protected function statusLabel(string $status): string
    {
        return match ($status) {
            MpProfitActionItem::STATUS_IN_PROGRESS => 'İnceleniyor',
            MpProfitActionItem::STATUS_SNOOZED => 'Ertelendi',
            MpProfitActionItem::STATUS_RESOLVED => 'Çözüldü',
            default => 'Yeni',
        };
    }

    protected function statusFilterLabel(string $statusFilter): string
    {
        return match ($statusFilter) {
            'resolved' => 'Çözülen',
            'all' => 'Tümü',
            default => 'Aktif',
        };
    }

    protected function closureQualityLabel(float $qualityPercent, int $resolvedCount): string
    {
        if ($resolvedCount < 1) {
            return 'Veri yok';
        }

        if ($qualityPercent >= 80) {
            return 'Güçlü';
        }

        if ($qualityPercent >= 60) {
            return 'İzlenmeli';
        }

        return 'Riskli';
    }

    protected function actionHealthTone(float $score): string
    {
        if ($score >= 80) {
            return 'success';
        }

        if ($score >= 60) {
            return 'warning';
        }

        return 'danger';
    }

    protected function actionHealthLabel(float $score, int $totalCount): string
    {
        if ($totalCount < 1) {
            return 'Takip temiz';
        }

        if ($score >= 80) {
            return 'Sağlıklı';
        }

        if ($score >= 60) {
            return 'İzlenmeli';
        }

        return 'Riskli';
    }

    protected function actionHealthHeadline(float $score, int $overdueCount, int $planGapCount, int $unownedCount): string
    {
        if ($score >= 80 && $overdueCount < 1 && $planGapCount < 1 && $unownedCount < 1) {
            return 'Aksiyon akışı kontrollü; gecikme, sahipsiz iş ve plan açığı düşük.';
        }

        $parts = [];

        if ($overdueCount > 0) {
            $parts[] = $overdueCount . ' geciken iş';
        }

        if ($planGapCount > 0) {
            $parts[] = $planGapCount . ' plan açığı';
        }

        if ($unownedCount > 0) {
            $parts[] = $unownedCount . ' sahipsiz iş';
        }

        return $parts !== []
            ? 'Öncelikli kontrol: ' . implode(', ', $parts) . '.'
            : 'Aksiyon sağlığı seçili kapsamda izlenebilir seviyede.';
    }

    protected function focusLabel(string $focus): string
    {
        return match ($focus) {
            'overdue' => 'Gecikenler',
            'due_soon' => '3 gün içinde',
            'unowned' => 'Sahipsiz işler',
            'high_priority' => 'Yüksek öncelik',
            'plan_gap' => 'Plan açığı',
            default => 'Tüm odaklar',
        };
    }

    protected function sortLabel(string $sort): string
    {
        return match ($sort) {
            'due_date' => 'Hedef tarihe göre',
            'impact' => 'Finansal etkiye göre',
            'updated' => 'Son güncellenen',
            default => 'Öncelik ve gecikme',
        };
    }

    protected function eventLabel(string $eventType): string
    {
        return match ($eventType) {
            MpProfitActionEvent::TYPE_CREATED => 'Aksiyon oluşturuldu',
            MpProfitActionEvent::TYPE_REFRESHED => 'Öneri güncellendi',
            MpProfitActionEvent::TYPE_REOPENED_BY_SIGNAL => 'Öneri yeniden açıldı',
            MpProfitActionEvent::TYPE_STATUS_CHANGED => 'Durum değişti',
            MpProfitActionEvent::TYPE_PLAN_UPDATED => 'Plan güncellendi',
            MpProfitActionEvent::TYPE_NOTE_UPDATED => 'Not güncellendi',
            default => 'Aksiyon güncellendi',
        };
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    protected function eventMetaSummary(?array $meta, string $eventType): ?string
    {
        if ($eventType === MpProfitActionEvent::TYPE_PLAN_UPDATED) {
            $current = is_array($meta['current'] ?? null) ? $meta['current'] : [];

            if (isset($current['completed_count'], $current['total_steps'])) {
                $summary = (int) $current['completed_count'] . '/' . (int) $current['total_steps'] . ' plan adımı tamamlandı.';
                $changedStep = trim((string) ($current['changed_step_label'] ?? ''));

                return $changedStep !== '' ? $summary . ' Son adım: ' . $changedStep : $summary;
            }

            return collect([
                isset($current['priority']) ? 'Öncelik: ' . $this->priorityLabel((string) $current['priority']) : null,
                isset($current['due_date']) && $current['due_date'] ? 'Hedef: ' . $current['due_date'] : null,
                isset($current['owner_label']) && $current['owner_label'] ? 'Sorumlu: ' . $current['owner_label'] : null,
            ])->filter()->implode(' · ') ?: null;
        }

        if ($eventType === MpProfitActionEvent::TYPE_NOTE_UPDATED) {
            return (bool) ($meta['current_has_note'] ?? false) ? 'Karar notu eklendi veya güncellendi.' : 'Karar notu temizlendi.';
        }

        if ($eventType === MpProfitActionEvent::TYPE_STATUS_CHANGED) {
            $closureEvidence = is_array($meta['closure_evidence'] ?? null) ? $meta['closure_evidence'] : [];

            if ($closureEvidence !== []) {
                $quality = (float) ($closureEvidence['quality_percent'] ?? 0);
                $qualityText = '%' . number_format($quality, 1, ',', '.');

                return 'Kapanış kanıtı kaydedildi: ' . (string) ($closureEvidence['quality_label'] ?? 'Veri yok') . ' · ' . $qualityText . '.';
            }
        }

        if ($eventType === MpProfitActionEvent::TYPE_REFRESHED || $eventType === MpProfitActionEvent::TYPE_REOPENED_BY_SIGNAL) {
            return 'Öneri mevcut filtre verisiyle yeniden değerlendirildi.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function recordEvent(MpProfitActionItem $item, string $eventType, ?string $fromStatus, ?string $toStatus, array $meta = []): void
    {
        MpProfitActionEvent::query()->create([
            'mp_profit_action_item_id' => $item->id,
            'user_id' => $item->user_id,
            'event_type' => $eventType,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'meta_json' => $meta,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeReportItem(MpProfitActionItem $item): array
    {
        $dueDate = $item->due_date instanceof Carbon ? $item->due_date : null;

        return [
            'id' => (int) $item->id,
            'title' => (string) $item->title,
            'status' => (string) $item->status,
            'status_label' => $this->statusLabel((string) $item->status),
            'event_label' => 'Son durum',
            'from_status_label' => null,
            'to_status_label' => $this->statusLabel((string) $item->status),
            'closure_summary' => $this->closureSummary($item),
            'priority_label' => $this->priorityLabel((string) ($item->priority ?: MpProfitActionItem::PRIORITY_MEDIUM)),
            'owner' => trim((string) $item->owner_label) !== '' ? trim((string) $item->owner_label) : 'Sahipsiz',
            'impact' => (float) $item->impact,
            'due_date' => $dueDate?->toDateString(),
            'is_overdue' => $item->status !== MpProfitActionItem::STATUS_RESOLVED
                && $dueDate instanceof Carbon
                && $dueDate->isBefore(now()->startOfDay()),
            'updated_at' => $item->updated_at instanceof Carbon ? $item->updated_at->diffForHumans() : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeEvent(MpProfitActionEvent $event): array
    {
        $item = $event->actionItem;
        $status = (string) (($event->to_status ?: $item?->status) ?? MpProfitActionItem::STATUS_OPEN);
        $dueDate = $item?->due_date instanceof Carbon ? $item->due_date : null;
        $meta = is_array($event->meta_json) ? $event->meta_json : [];
        $closureEvidence = is_array($meta['closure_evidence'] ?? null) ? $meta['closure_evidence'] : null;

        if (! $closureEvidence && $status === MpProfitActionItem::STATUS_RESOLVED && $item instanceof MpProfitActionItem) {
            $closureEvidence = $this->closureSummary($item);
        }

        return [
            'id' => (int) $event->id,
            'action_id' => (int) ($item?->id ?? 0),
            'title' => (string) ($item?->title ?? 'Kar Merkezi aksiyonu'),
            'event_type' => (string) $event->event_type,
            'event_label' => $this->eventLabel((string) $event->event_type),
            'meta_summary' => $this->eventMetaSummary($meta, (string) $event->event_type),
            'closure_note' => (string) ($closureEvidence['note_excerpt'] ?? $closureEvidence['note'] ?? ''),
            'closure_quality' => $closureEvidence,
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'from_status_label' => $event->from_status ? $this->statusLabel((string) $event->from_status) : null,
            'to_status_label' => $event->to_status ? $this->statusLabel((string) $event->to_status) : null,
            'priority_label' => $this->priorityLabel((string) (($item?->priority ?: MpProfitActionItem::PRIORITY_MEDIUM))),
            'owner' => trim((string) ($item?->owner_label ?? '')) !== '' ? trim((string) $item?->owner_label) : 'Sahipsiz',
            'impact' => (float) ($item?->impact ?? 0),
            'due_date' => $dueDate?->toDateString(),
            'is_overdue' => $item?->status !== MpProfitActionItem::STATUS_RESOLVED
                && $dueDate instanceof Carbon
                && $dueDate->isBefore(now()->startOfDay()),
            'updated_at' => $event->created_at instanceof Carbon ? $event->created_at->diffForHumans() : null,
            'created_at' => $event->created_at instanceof Carbon ? $event->created_at->format('d.m.Y H:i') : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(MpProfitActionItem $item, array $currentRecommendations = [], ?array $currentCalculationHealth = null): array
    {
        $priority = (string) ($item->priority ?: MpProfitActionItem::PRIORITY_MEDIUM);
        $isActive = $item->status !== MpProfitActionItem::STATUS_RESOLVED;
        $dueDate = $item->due_date instanceof Carbon ? $item->due_date : null;
        $recommendation = $this->recommendationWithBlueprint(
            is_array($item->recommendation_json) ? $item->recommendation_json : [],
            (string) $item->action_key
        );
        $playbookSteps = is_array($recommendation['playbook_steps'] ?? null)
            ? array_values(array_filter(array_map('strval', $recommendation['playbook_steps'])))
            : [];
        $playbookProgress = $this->playbookProgressForItem($item);

        return [
            'id' => (int) $item->id,
            'key' => (string) $item->action_key,
            'title' => (string) $item->title,
            'description' => (string) $item->description,
            'plan_summary' => (string) ($recommendation['plan_summary'] ?? ''),
            'success_metric' => (string) ($recommendation['success_metric'] ?? ''),
            'playbook_steps' => $playbookSteps,
            'playbook_progress' => $playbookProgress,
            'default_owner' => (string) ($recommendation['default_owner'] ?? ''),
            'due_in_days' => isset($recommendation['due_in_days']) ? (int) $recommendation['due_in_days'] : null,
            'action_label' => (string) $item->action_label,
            'route' => (string) $item->route_name,
            'query' => $item->query_json ?? [],
            'filters' => $item->filters_json ?? [],
            'value' => (int) $item->value,
            'impact' => (float) $item->impact,
            'score' => (float) $item->score,
            'signal_progress' => $this->signalProgress($recommendation, (string) $item->action_key, $currentRecommendations, $currentCalculationHealth),
            'closure_summary' => $this->closureSummary($item),
            'status' => (string) $item->status,
            'priority' => $priority,
            'priority_label' => $this->priorityLabel($priority),
            'priority_tone' => $this->priorityTone($priority),
            'due_date' => $dueDate?->toDateString(),
            'owner_label' => (string) ($item->owner_label ?? ''),
            'is_overdue' => $isActive && $dueDate instanceof Carbon && $dueDate->isBefore(now()->startOfDay()),
            'is_due_soon' => $isActive && $dueDate instanceof Carbon && $dueDate->betweenIncluded(now()->startOfDay(), now()->addDays(3)->endOfDay()),
            'note' => (string) ($item->note ?? ''),
            'snoozed_until' => $item->snoozed_until instanceof Carbon ? $item->snoozed_until->toDateString() : null,
            'resolved_at' => $item->resolved_at instanceof Carbon ? $item->resolved_at->toDateString() : null,
            'updated_at' => $item->updated_at instanceof Carbon ? $item->updated_at->diffForHumans() : null,
        ];
    }
}
