<?php

namespace App\Services\Marketplace;

use App\Models\TrendyolBoosterActionState;
use App\Models\TrendyolBoosterActionAudit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Throwable;

class TrendyolBoosterActionCenterService
{
    /** @return array<string, mixed> */
    public function dashboard(int $userId, array $operational, array $priority): array
    {
        $items = collect($this->operationalItems($operational))
            ->concat($this->productItems($priority))
            ->sortByDesc('priority')
            ->values();

        $states = $this->states($userId, $items->pluck('fingerprint')->all());
        $now = now();
        $visible = $items->map(function (array $item) use ($states, $now): array {
            $state = $states->get($item['fingerprint']);
            $status = (string) ($state?->status ?: 'open');
            $snoozedUntil = $state?->snoozed_until;

            if ($status === 'snoozed' && $snoozedUntil?->isPast()) {
                $status = 'open';
            }

            return $item + [
                'status' => $status,
                'status_label' => match ($status) {
                    'acknowledged' => 'Kabul edildi',
                    'snoozed' => 'Ertelendi',
                    default => 'Açık',
                },
                'snoozed_until' => $snoozedUntil,
                'assigned_user_id' => $state?->assigned_user_id,
                'assigned_user_name' => $state?->assignedUser?->name,
            ];
        });

        return [
            'total' => $visible->count(),
            'open_count' => $visible->where('status', 'open')->count(),
            'acknowledged_count' => $visible->where('status', 'acknowledged')->count(),
            'snoozed_count' => $visible->where('status', 'snoozed')->count(),
            'critical_count' => $visible->where('status', 'open')->where('severity', 'critical')->count(),
            'items' => $visible
                ->sortBy(fn (array $item): array => [$this->statusRank($item['status']), -$item['priority']])
                ->take(12)
                ->values()
                ->all(),
            'audit' => $this->audit($userId),
        ];
    }

    public function setStatus(int $userId, string $fingerprint, string $status, ?int $snoozeHours = null, ?int $actorUserId = null): void
    {
        if (! $this->ready() || ! in_array($status, ['open', 'acknowledged', 'snoozed'], true)) {
            return;
        }

        $state = TrendyolBoosterActionState::query()->firstOrNew([
            'user_id' => $userId,
            'fingerprint' => $fingerprint,
        ]);
        $now = now();
        $previous = (string) ($state->status ?: 'open');
        $state->forceFill([
            'status' => $status,
            'acknowledged_at' => $status === 'acknowledged' ? $now : null,
            'snoozed_until' => $status === 'snoozed' ? $now->copy()->addHours(max(1, min(168, (int) $snoozeHours))) : null,
            'resolved_at' => null,
            'context_json' => array_merge((array) $state->context_json, [
                'last_action' => $status,
                'last_action_at' => $now->toIso8601String(),
            ]),
        ])->save();
        $this->recordAudit($userId, $actorUserId ?: $userId, $fingerprint, 'status_changed', $previous, $status, [
            'snooze_hours' => $status === 'snoozed' ? $snoozeHours : null,
        ]);
    }

    public function assign(int $ownerUserId, string $fingerprint, int $assignedUserId, int $actorUserId): void
    {
        if (! $this->ready()) {
            return;
        }

        $state = TrendyolBoosterActionState::query()->firstOrNew([
            'user_id' => $ownerUserId,
            'fingerprint' => $fingerprint,
        ]);
        $previous = $state->assigned_user_id;
        $state->forceFill([
            'status' => $state->status ?: 'open',
            'assigned_user_id' => $assignedUserId,
            'assigned_by_user_id' => $actorUserId,
            'context_json' => array_merge((array) $state->context_json, ['assigned_at' => now()->toIso8601String()]),
        ])->save();
        $this->recordAudit($ownerUserId, $actorUserId, $fingerprint, 'assigned', $previous ? (string) $previous : null, (string) $assignedUserId);
    }

    /** @return Collection<string, TrendyolBoosterActionState> */
    protected function states(int $userId, array $fingerprints): Collection
    {
        if (! $this->ready() || $fingerprints === []) {
            return collect();
        }

        return TrendyolBoosterActionState::query()
            ->where('user_id', $userId)
            ->whereIn('fingerprint', $fingerprints)
            ->with('assignedUser:id,name')
            ->get()
            ->keyBy('fingerprint');
    }

    /** @return array<int, array<string, mixed>> */
    protected function operationalItems(array $operational): array
    {
        return collect($operational['issues'] ?? [])->map(fn (array $issue): array => [
            'fingerprint' => 'operational:'.($issue['key'] ?? 'unknown'),
            'source' => 'operation',
            'severity' => $issue['severity'] ?? 'warning',
            'tone' => $issue['severity'] === 'critical' ? 'rose' : 'amber',
            'priority' => $issue['severity'] === 'critical' ? 900 : 700,
            'label' => $issue['label'] ?? 'Operasyon uyarısı',
            'title' => $issue['detail'] ?? '',
            'reason' => $issue['action'] ?? '',
            'metric' => $issue['metric'] ?? null,
            'product_id' => null,
        ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    protected function productItems(array $priority): array
    {
        return collect($priority['actions'] ?? [])->map(fn (array $action): array => [
            'fingerprint' => 'product:'.($action['product_id'] ?? 0).':'.($action['key'] ?? 'unknown'),
            'source' => 'product',
            'severity' => $action['severity'] ?? 'info',
            'tone' => $action['tone'] ?? 'sky',
            'priority' => (int) ($action['priority'] ?? 0),
            'label' => $action['label'] ?? 'Ürün aksiyonu',
            'title' => $action['title'] ?? '',
            'reason' => $action['reason'] ?? '',
            'metric' => $action['metric'] ?? null,
            'product_id' => $action['product_id'] ?? null,
        ])->all();
    }

    protected function statusRank(string $status): int
    {
        return ['open' => 0, 'snoozed' => 1, 'acknowledged' => 2][$status] ?? 3;
    }

    protected function ready(): bool
    {
        try {
            return Schema::hasTable('trendyol_booster_action_states');
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array<int, array<string, mixed>> */
    protected function audit(int $userId): array
    {
        try {
            $ready = Schema::hasTable('trendyol_booster_action_audits');
        } catch (Throwable) {
            $ready = false;
        }

        if (! $ready) {
            return [];
        }

        return TrendyolBoosterActionAudit::query()
            ->where('owner_user_id', $userId)
            ->with('actor:id,name')
            ->latest('occurred_at')
            ->limit(12)
            ->get()
            ->map(fn (TrendyolBoosterActionAudit $audit): array => [
                'id' => $audit->id,
                'fingerprint' => $audit->fingerprint,
                'event' => $audit->event,
                'event_label' => $audit->event === 'assigned' ? 'Atandı' : 'Durum değişti',
                'actor' => $audit->actor?->name ?: 'Sistem',
                'from' => $audit->from_value,
                'to' => $audit->to_value,
                'occurred_at' => $audit->occurred_at,
            ])->all();
    }

    /** @param array<string, mixed> $context */
    protected function recordAudit(int $ownerUserId, int $actorUserId, string $fingerprint, string $event, ?string $from, ?string $to, array $context = []): void
    {
        try {
            $ready = Schema::hasTable('trendyol_booster_action_audits');
        } catch (Throwable) {
            $ready = false;
        }

        if (! $ready) {
            return;
        }

        TrendyolBoosterActionAudit::query()->create([
            'owner_user_id' => $ownerUserId,
            'actor_user_id' => $actorUserId,
            'fingerprint' => $fingerprint,
            'event' => $event,
            'from_value' => $from,
            'to_value' => $to,
            'context_json' => $context,
            'occurred_at' => now(),
        ]);
    }
}
