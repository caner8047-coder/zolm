<?php

namespace App\Services\Marketplace\Support;

use App\Models\OrderFinancialEvent;

final class FinancialEventClassifier
{
    public const SELLER_REVENUE_EVENT_TYPES = ['seller_revenue', 'sale', 'capture', 'refund', 'void'];

    public const COMMISSION_EVENT_TYPES = ['commission'];

    public const CARGO_EVENT_TYPES = ['cargo', 'return_cargo'];

    public const SERVICE_FEE_EVENT_TYPES = [
        'service_fee',
        'deduction_invoice',
        'fee',
        'international_service_fee',
        'international_operation_fee',
        'other_invoice',
    ];

    public const WITHHOLDING_EVENT_TYPES = ['withholding'];

    public const ADVERTISING_EVENT_TYPES = ['advertising', 'advertising_fee', 'ads'];

    public const PENALTY_EVENT_TYPES = ['penalty', 'operational_penalty'];

    public const EARLY_PAYMENT_EVENT_TYPES = ['early_payment_fee'];

    public const DISCOUNT_EVENT_TYPES = ['campaign_discount', 'marketplace_discount'];

    public const OTHER_COST_EVENT_TYPES = ['other_financial_cost'];

    public const OVERHEAD_EVENT_TYPES = [
        'service_fee',
        'deduction_invoice',
        'fee',
        'international_service_fee',
        'international_operation_fee',
        'other_invoice',
        'advertising',
        'advertising_fee',
        'ads',
        'penalty',
        'operational_penalty',
        'early_payment_fee',
        'campaign_discount',
        'marketplace_discount',
        'other_financial_cost',
    ];

    public const COST_EVENT_TYPES = [
        'commission',
        'cargo',
        'return_cargo',
        'service_fee',
        'deduction_invoice',
        'fee',
        'international_service_fee',
        'international_operation_fee',
        'other_invoice',
        'withholding',
        'advertising',
        'advertising_fee',
        'ads',
        'penalty',
        'operational_penalty',
        'early_payment_fee',
        'campaign_discount',
        'marketplace_discount',
        'other_financial_cost',
    ];

    public const CONFIRMING_EVENT_TYPES = [
        'seller_revenue',
        'sale',
        'capture',
        'refund',
        'void',
        'commission',
        'cargo',
        'return_cargo',
        'service_fee',
        'deduction_invoice',
        'fee',
        'international_service_fee',
        'international_operation_fee',
        'other_invoice',
        'withholding',
        'advertising',
        'advertising_fee',
        'ads',
        'penalty',
        'operational_penalty',
        'early_payment_fee',
        'campaign_discount',
        'marketplace_discount',
        'other_financial_cost',
    ];

    public const NON_SETTLED_EVENT_STATUSES = ['pending', 'draft', 'authorized', 'authorization', 'failed', 'failure', 'error', 'cancelled', 'canceled', 'declined', 'expired'];

    /**
     * @return array<string, array{label: string, tone: string, kind: string, types: array<int, string>}>
     */
    public static function categoryDefinitions(): array
    {
        return [
            'seller_revenue' => [
                'label' => 'Satıcı geliri',
                'tone' => 'emerald',
                'kind' => 'revenue',
                'types' => self::SELLER_REVENUE_EVENT_TYPES,
            ],
            'commission' => [
                'label' => 'Komisyon',
                'tone' => 'amber',
                'kind' => 'cost',
                'types' => self::COMMISSION_EVENT_TYPES,
            ],
            'cargo' => [
                'label' => 'Kargo',
                'tone' => 'sky',
                'kind' => 'cost',
                'types' => self::CARGO_EVENT_TYPES,
            ],
            'service_fee' => [
                'label' => 'Hizmet bedeli',
                'tone' => 'slate',
                'kind' => 'cost',
                'types' => self::SERVICE_FEE_EVENT_TYPES,
            ],
            'withholding' => [
                'label' => 'Stopaj',
                'tone' => 'rose',
                'kind' => 'cost',
                'types' => self::WITHHOLDING_EVENT_TYPES,
            ],
            'advertising' => [
                'label' => 'Reklam',
                'tone' => 'indigo',
                'kind' => 'cost',
                'types' => self::ADVERTISING_EVENT_TYPES,
            ],
            'penalty' => [
                'label' => 'Ceza',
                'tone' => 'red',
                'kind' => 'cost',
                'types' => self::PENALTY_EVENT_TYPES,
            ],
            'early_payment' => [
                'label' => 'Erken ödeme',
                'tone' => 'violet',
                'kind' => 'cost',
                'types' => self::EARLY_PAYMENT_EVENT_TYPES,
            ],
            'discount' => [
                'label' => 'Kampanya indirimi',
                'tone' => 'orange',
                'kind' => 'cost',
                'types' => self::DISCOUNT_EVENT_TYPES,
            ],
            'other' => [
                'label' => 'Diğer',
                'tone' => 'zinc',
                'kind' => 'cost',
                'types' => self::OTHER_COST_EVENT_TYPES,
            ],
        ];
    }

    public static function categoryFor(string $eventType): string
    {
        $normalized = strtolower(trim($eventType));

        foreach (self::categoryDefinitions() as $key => $definition) {
            if (in_array($normalized, $definition['types'], true)) {
                return $key;
            }
        }

        return 'unmapped';
    }

    /**
     * @return array<int, string>
     */
    public static function eventTypesForCategories(array $categories): array
    {
        $definitions = self::categoryDefinitions();

        return collect($categories)
            ->flatMap(fn (string $category) => $definitions[$category]['types'] ?? [])
            ->unique()
            ->values()
            ->all();
    }

    public static function isSettledConfirmingEvent(OrderFinancialEvent $event): bool
    {
        if (! in_array(strtolower(trim((string) $event->event_type)), self::CONFIRMING_EVENT_TYPES, true)) {
            return false;
        }

        return self::isSettledStatus((string) $event->status);
    }

    public static function isSettledStatus(string $status): bool
    {
        $status = strtolower(trim($status));

        return ! in_array($status, self::NON_SETTLED_EVENT_STATUSES, true);
    }

    /**
     * @param  array<int, string>  $types
     */
    public static function quotedTypes(array $types): string
    {
        return collect($types)
            ->map(fn (string $type) => "'" . str_replace("'", "''", $type) . "'")
            ->implode(', ');
    }

    public static function settledEventSql(string $eventTypeColumn = 'event_type', string $statusColumn = 'status'): string
    {
        $confirmingTypesSql = self::quotedTypes(self::CONFIRMING_EVENT_TYPES);
        $nonSettledStatusesSql = self::quotedTypes(self::NON_SETTLED_EVENT_STATUSES);

        return "({$eventTypeColumn} IN ({$confirmingTypesSql}) AND LOWER(COALESCE({$statusColumn}, '')) NOT IN ({$nonSettledStatusesSql}))";
    }
}
