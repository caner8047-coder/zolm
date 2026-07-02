<?php

namespace App\Services\Marketplace;

use App\Models\OrderFinancialEvent;
use App\Services\Marketplace\Support\FinancialEventClassifier;

class MarketplaceCostBreakdownService
{
    /**
     * @param  iterable<int, OrderFinancialEvent|array<string, mixed>>  $events
     * @return array{
     *     seller_revenue_net: float,
     *     total_deductions: float,
     *     categories: array<string, array{
     *         key: string,
     *         label: string,
     *         tone: string,
     *         kind: string,
     *         credit_total: float,
     *         debit_total: float,
     *         net_amount: float,
     *         cost_total: float,
     *         event_count: int
     *     }>,
     *     unmapped: array<int, string>
     * }
     */
    public function summarize(iterable $events, bool $settledOnly = true): array
    {
        $categories = collect(FinancialEventClassifier::categoryDefinitions())
            ->mapWithKeys(fn (array $definition, string $key) => [
                $key => [
                    'key' => $key,
                    'label' => $definition['label'],
                    'tone' => $definition['tone'],
                    'kind' => $definition['kind'],
                    'credit_total' => 0.0,
                    'debit_total' => 0.0,
                    'net_amount' => 0.0,
                    'cost_total' => 0.0,
                    'event_count' => 0,
                ],
            ])
            ->all();
        $unmapped = [];

        foreach ($events as $event) {
            $model = $event instanceof OrderFinancialEvent
                ? $event
                : new OrderFinancialEvent($event);

            if ($settledOnly && ! FinancialEventClassifier::isSettledStatus((string) $model->status)) {
                continue;
            }

            $eventType = strtolower(trim((string) $model->event_type));
            $category = FinancialEventClassifier::categoryFor($eventType);

            if ($category === 'unmapped') {
                if ($eventType !== '') {
                    $unmapped[] = $eventType;
                }

                continue;
            }

            $amount = abs((float) $model->amount);
            $direction = strtolower(trim((string) $model->direction));

            if ($direction === 'credit') {
                $categories[$category]['credit_total'] += $amount;
            } else {
                $categories[$category]['debit_total'] += $amount;
            }

            $categories[$category]['event_count']++;
        }

        foreach ($categories as $key => $row) {
            $netAmount = round((float) $row['credit_total'] - (float) $row['debit_total'], 2);
            $categories[$key]['credit_total'] = round((float) $row['credit_total'], 2);
            $categories[$key]['debit_total'] = round((float) $row['debit_total'], 2);
            $categories[$key]['net_amount'] = $netAmount;
            $categories[$key]['cost_total'] = $row['kind'] === 'cost'
                ? round(abs(min($netAmount, 0)), 2)
                : 0.0;
        }

        return [
            'seller_revenue_net' => round((float) $categories['seller_revenue']['net_amount'], 2),
            'total_deductions' => round((float) collect($categories)->sum('cost_total'), 2),
            'categories' => $categories,
            'unmapped' => collect($unmapped)->unique()->sort()->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    public function costTotal(array $summary, string $category): float
    {
        return round((float) data_get($summary, "categories.{$category}.cost_total", 0), 2);
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<int, string>  $categories
     */
    public function combinedCostTotal(array $summary, array $categories): float
    {
        return round((float) collect($categories)
            ->sum(fn (string $category) => $this->costTotal($summary, $category)), 2);
    }
}
