<?php

namespace App\Services\Returns;

use App\Models\ReturnDailyReport;
use App\Models\ReturnIntakeDecision;
use App\Models\ReturnIntakeItem;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ReturnDailyReportService
{
    public function __construct(
        protected ReturnAutoDecisionPolicyService $autoDecisionPolicyService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(CarbonInterface $date): array
    {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        $items = ReturnIntakeItem::query()
            ->with(['submittedBy:id,name', 'store:id,store_name', 'claim.store:id,store_name'])
            ->whereBetween('arrived_at', [$start, $end])
            ->get();

        $decisions = ReturnIntakeDecision::query()
            ->with(['item.store:id,store_name', 'item.claim.store:id,store_name', 'user:id,name'])
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $report = [
            'date' => $start->toDateString(),
            'totals' => $this->buildTotals($items, $decisions),
            'decision_breakdown' => $this->buildDecisionBreakdown($decisions),
            'condition_breakdown' => $this->buildConditionBreakdown($items),
            'operator_breakdown' => $this->buildOperatorBreakdown($items, $decisions),
            'store_breakdown' => $this->buildStoreBreakdown($items),
            'auto_policy' => $this->autoDecisionPolicyService->preview(date: $start),
            'hot_items' => $this->buildHotItems($items),
        ];

        return $report;
    }

    public function persist(CarbonInterface $date): ReturnDailyReport
    {
        $report = $this->build($date);

        return ReturnDailyReport::query()->updateOrCreate(
            ['report_date' => $report['date']],
            [
                'totals_json' => $report['totals'],
                'decision_breakdown_json' => $report['decision_breakdown'],
                'condition_breakdown_json' => $report['condition_breakdown'],
                'operator_breakdown_json' => $report['operator_breakdown'],
                'store_breakdown_json' => $report['store_breakdown'],
                'auto_policy_json' => $report['auto_policy'],
                'hot_items_json' => $report['hot_items'],
                'generated_at' => now(),
            ]
        );
    }

    /**
     * @param  Collection<int, ReturnIntakeItem>  $items
     * @param  Collection<int, ReturnIntakeDecision>  $decisions
     * @return array<string, int|float>
     */
    protected function buildTotals(Collection $items, Collection $decisions): array
    {
        $submitted = $items->count();
        $damaged = $items->where('condition_status', 'damaged')->count();
        $decisioned = $items->where('decision_status', '!=', 'pending')->count();
        $autoDecisioned = $decisions->where('decision_mode', 'automatic')->count();

        return [
            'submitted' => $submitted,
            'damaged' => $damaged,
            'undamaged' => $items->where('condition_status', 'undamaged')->count(),
            'pending' => $items->where('decision_status', 'pending')->count(),
            'decisioned' => $decisioned,
            'auto_decisioned' => $autoDecisioned,
            'marketplace_actions' => $decisions->filter(fn (ReturnIntakeDecision $decision) => $decision->marketplace_pushed_at !== null)->count(),
            'damage_rate' => $submitted > 0 ? round(($damaged / $submitted) * 100, 1) : 0.0,
        ];
    }

    /**
     * @param  Collection<int, ReturnIntakeDecision>  $decisions
     * @return array<int, array<string, int|string>>
     */
    protected function buildDecisionBreakdown(Collection $decisions): array
    {
        return $decisions
            ->groupBy('decision')
            ->map(fn (Collection $group, string $decision) => [
                'decision' => $decision,
                'count' => $group->count(),
                'automatic' => $group->where('decision_mode', 'automatic')->count(),
                'manual' => $group->where('decision_mode', 'manual')->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, ReturnIntakeItem>  $items
     * @return array<int, array<string, int|string>>
     */
    protected function buildConditionBreakdown(Collection $items): array
    {
        return $items
            ->groupBy('condition_status')
            ->map(fn (Collection $group, string $status) => [
                'status' => $status,
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, ReturnIntakeItem>  $items
     * @param  Collection<int, ReturnIntakeDecision>  $decisions
     * @return array<int, array<string, int|string>>
     */
    protected function buildOperatorBreakdown(Collection $items, Collection $decisions): array
    {
        return $items
            ->groupBy(fn (ReturnIntakeItem $item) => (string) ($item->submittedBy?->name ?: 'Bilinmeyen operatör'))
            ->map(function (Collection $group, string $name) use ($decisions) {
                $operatorItemIds = $group->pluck('id')->all();

                return [
                    'name' => $name,
                    'submitted' => $group->count(),
                    'damaged' => $group->where('condition_status', 'damaged')->count(),
                    'decisioned' => $group->where('decision_status', '!=', 'pending')->count(),
                    'automatic' => $decisions->whereIn('return_intake_item_id', $operatorItemIds)->where('decision_mode', 'automatic')->count(),
                ];
            })
            ->sortByDesc('submitted')
            ->take(5)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, ReturnIntakeItem>  $items
     * @return array<int, array<string, int|string>>
     */
    protected function buildStoreBreakdown(Collection $items): array
    {
        return $items
            ->groupBy(function (ReturnIntakeItem $item) {
                return (string) ($item->store?->store_name ?: $item->claim?->store?->store_name ?: 'Eşleşmemiş');
            })
            ->map(fn (Collection $group, string $storeName) => [
                'store_name' => $storeName,
                'submitted' => $group->count(),
                'damaged' => $group->where('condition_status', 'damaged')->count(),
                'pending' => $group->where('decision_status', 'pending')->count(),
            ])
            ->sortByDesc('submitted')
            ->take(5)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, ReturnIntakeItem>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function buildHotItems(Collection $items): array
    {
        return $items
            ->filter(fn (ReturnIntakeItem $item) => $item->decision_status === 'pending' || in_array($item->intake_status, ['needs_review', 'failed'], true))
            ->sortByDesc(function (ReturnIntakeItem $item) {
                return [
                    $item->condition_status === 'damaged' ? 2 : 1,
                    $item->intake_status === 'failed' ? 2 : 1,
                    $item->arrived_at?->timestamp ?? 0,
                ];
            })
            ->take(6)
            ->values()
            ->map(fn (ReturnIntakeItem $item) => [
                'id' => $item->id,
                'reference' => $item->detected_tracking_number ?: $item->manual_reference ?: $item->operator_barcode ?: ('INTAKE-' . $item->id),
                'status' => $item->statusLabel(),
                'decision' => $item->decisionLabel(),
                'condition' => $item->conditionLabel(),
                'store_name' => $item->store?->store_name ?: $item->claim?->store?->store_name ?: 'Eşleşmemiş',
            ])
            ->all();
    }
}
