<?php

namespace App\Services\Returns;

use App\Models\ReturnIntakeDecision;
use App\Models\ReturnIntakeItem;
use App\Services\Marketplace\MarketplaceClaimActionService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class ReturnAutoDecisionPolicyService
{
    public function __construct(
        protected MarketplaceClaimActionService $claimActionService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(?CarbonInterface $date = null, ?int $itemId = null, ?int $limit = null): array
    {
        $items = $this->eligibleQuery($date, $itemId)
            ->limit($limit ?: (int) config('returns.auto_policy_limit', 25))
            ->get();

        $marketplaceEnabled = (bool) config('returns.auto_marketplace_actions_enabled', false);

        return [
            'eligible' => $items->count(),
            'restock' => $items->where('suggested_decision', 'restock')->count(),
            'scrap' => $items->where('suggested_decision', 'scrap')->count(),
            'manual_review' => $items->where('suggested_decision', 'manual_review')->count(),
            'marketplace' => $items->whereIn('suggested_decision', ['approve_marketplace', 'reject_marketplace'])->count(),
            'marketplace_enabled' => $marketplaceEnabled,
            'blocked_marketplace' => $marketplaceEnabled ? 0 : $items->whereIn('suggested_decision', ['approve_marketplace', 'reject_marketplace'])->count(),
            'items' => $items->map(fn (ReturnIntakeItem $item) => [
                'id' => $item->id,
                'suggested_decision' => $item->suggested_decision,
                'suggested_confidence' => (float) ($item->suggested_confidence ?? 0),
                'reference' => $item->detected_tracking_number ?: $item->manual_reference ?: $item->operator_barcode ?: ('INTAKE-' . $item->id),
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function run(
        bool $dryRun = false,
        ?CarbonInterface $date = null,
        ?int $itemId = null,
        ?int $limit = null,
        ?bool $allowMarketplace = null,
    ): array {
        $allowMarketplace = $allowMarketplace ?? (bool) config('returns.auto_marketplace_actions_enabled', false);
        $items = $this->eligibleQuery($date, $itemId)
            ->limit($limit ?: (int) config('returns.auto_policy_limit', 25))
            ->get();

        $summary = [
            'eligible' => $items->count(),
            'processed' => 0,
            'restocked' => 0,
            'scrapped' => 0,
            'manual_review' => 0,
            'approved' => 0,
            'rejected' => 0,
            'blocked' => 0,
            'errors' => 0,
            'items' => [],
        ];

        foreach ($items as $item) {
            $result = [
                'item_id' => $item->id,
                'decision' => $item->suggested_decision,
                'status' => 'skipped',
                'message' => '',
            ];

            try {
                if (in_array($item->suggested_decision, ['approve_marketplace', 'reject_marketplace'], true) && !$allowMarketplace) {
                    $summary['blocked']++;
                    $result['status'] = 'blocked';
                    $result['message'] = 'Marketplace otomasyonu kapalı.';
                    $summary['items'][] = $result;
                    continue;
                }

                if ($dryRun) {
                    $summary['processed']++;
                    $result['status'] = 'preview';
                    $result['message'] = 'Dry-run önizlemesi.';
                    $summary['items'][] = $result;
                    continue;
                }

                match ($item->suggested_decision) {
                    'restock' => $this->applyInternalDecision($item, 'restocked', 'auto_policy_restock'),
                    'scrap' => $this->applyInternalDecision($item, 'scrapped', 'auto_policy_scrap'),
                    'manual_review' => $this->applyInternalDecision($item, 'needs_review', 'auto_policy_manual_review'),
                    'approve_marketplace' => $this->applyMarketplaceApprove($item),
                    'reject_marketplace' => $this->applyMarketplaceReject($item),
                    default => null,
                };

                $summary['processed']++;
                $result['status'] = 'processed';
                $result['message'] = 'Politika uygulandı.';

                match ($item->suggested_decision) {
                    'restock' => $summary['restocked']++,
                    'scrap' => $summary['scrapped']++,
                    'manual_review' => $summary['manual_review']++,
                    'approve_marketplace' => $summary['approved']++,
                    'reject_marketplace' => $summary['rejected']++,
                    default => null,
                };
            } catch (\Throwable $exception) {
                $summary['errors']++;
                $result['status'] = 'error';
                $result['message'] = $exception->getMessage();
            }

            $summary['items'][] = $result;
        }

        return $summary;
    }

    public function eligibleQuery(?CarbonInterface $date = null, ?int $itemId = null): Builder
    {
        $query = ReturnIntakeItem::query()
            ->with(['claim.store', 'media'])
            ->where('decision_status', 'pending')
            ->where('intake_status', 'ready_for_decision')
            ->whereNotNull('suggested_decision')
            ->where('suggested_confidence', '>=', (float) config('returns.auto_policy_min_confidence', 88));

        if ($date) {
            $query->whereBetween('arrived_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()]);
        }

        if ($itemId) {
            $query->where('id', $itemId);
        }

        return $query->orderByDesc('suggested_confidence')->orderBy('id');
    }

    protected function applyInternalDecision(ReturnIntakeItem $item, string $decision, string $reasonCode): ReturnIntakeDecision
    {
        $record = $item->decisions()->create([
            'user_id' => null,
            'decision' => $decision,
            'decision_mode' => 'automatic',
            'reason_code' => $reasonCode,
            'note' => $item->suggestion_summary,
            'raw_payload' => [
                'suggested_decision' => $item->suggested_decision,
                'suggested_confidence' => $item->suggested_confidence,
            ],
        ]);

        $item->update([
            'decision_status' => $decision,
            'intake_status' => $decision === 'needs_review' ? 'needs_review' : 'decisioned',
        ]);

        return $record;
    }

    protected function applyMarketplaceApprove(ReturnIntakeItem $item): ReturnIntakeDecision
    {
        if (!$item->claim) {
            throw new \RuntimeException('Bağlı claim olmadığı için otomatik onay uygulanamadı.');
        }

        $result = $this->claimActionService->approveClaim($item->claim, [
            'source' => 'return_auto_policy',
            'summary' => $item->suggestion_summary,
        ]);

        $record = $item->decisions()->create([
            'user_id' => null,
            'decision' => 'approved',
            'decision_mode' => 'automatic',
            'reason_code' => 'auto_policy_marketplace_approve',
            'note' => $item->suggestion_summary ?: ($result['message'] ?? null),
            'marketplace_pushed_at' => now(),
            'raw_payload' => [
                'marketplace_result' => $result,
                'suggested_confidence' => $item->suggested_confidence,
            ],
        ]);

        $item->update([
            'decision_status' => 'approved',
            'intake_status' => 'decisioned',
        ]);

        return $record;
    }

    protected function applyMarketplaceReject(ReturnIntakeItem $item): ReturnIntakeDecision
    {
        if (!$item->claim) {
            throw new \RuntimeException('Bağlı claim olmadığı için otomatik red uygulanamadı.');
        }

        $reason = trim((string) ($item->suggestion_summary ?: config('returns.auto_reject_reason', 'Fiziksel iade incelemesinde ürün sipariş ile uyumsuz veya hasarlı bulundu.')));
        $result = $this->claimActionService->rejectClaim($item->claim, $reason, [
            'source' => 'return_auto_policy',
        ]);

        $record = $item->decisions()->create([
            'user_id' => null,
            'decision' => 'rejected',
            'decision_mode' => 'automatic',
            'reason_code' => 'auto_policy_marketplace_reject',
            'note' => $reason,
            'marketplace_pushed_at' => now(),
            'raw_payload' => [
                'marketplace_result' => $result,
                'suggested_confidence' => $item->suggested_confidence,
            ],
        ]);

        $item->update([
            'decision_status' => 'rejected',
            'intake_status' => 'decisioned',
        ]);

        return $record;
    }
}
