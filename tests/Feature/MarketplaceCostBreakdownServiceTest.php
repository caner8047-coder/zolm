<?php

namespace Tests\Feature;

use App\Services\Marketplace\MarketplaceCostBreakdownService;
use App\Services\Marketplace\Support\FinancialEventClassifier;
use Tests\TestCase;

class MarketplaceCostBreakdownServiceTest extends TestCase
{
    public function test_it_maps_extended_financial_events_to_a_single_cost_dictionary(): void
    {
        $summary = app(MarketplaceCostBreakdownService::class)->summarize([
            ['event_type' => 'seller_revenue', 'direction' => 'credit', 'amount' => 1000, 'status' => 'posted'],
            ['event_type' => 'commission', 'direction' => 'debit', 'amount' => 100, 'status' => 'posted'],
            ['event_type' => 'commission', 'direction' => 'credit', 'amount' => 20, 'status' => 'posted'],
            ['event_type' => 'return_cargo', 'direction' => 'debit', 'amount' => 50, 'status' => 'posted'],
            ['event_type' => 'international_service_fee', 'direction' => 'debit', 'amount' => 15, 'status' => 'posted'],
            ['event_type' => 'advertising', 'direction' => 'debit', 'amount' => 40, 'status' => 'posted'],
            ['event_type' => 'penalty', 'direction' => 'debit', 'amount' => 10, 'status' => 'posted'],
            ['event_type' => 'early_payment_fee', 'direction' => 'debit', 'amount' => 5, 'status' => 'posted'],
            ['event_type' => 'campaign_discount', 'direction' => 'debit', 'amount' => 25, 'status' => 'posted'],
            ['event_type' => 'other_financial_cost', 'direction' => 'debit', 'amount' => 8, 'status' => 'posted'],
            ['event_type' => 'advertising', 'direction' => 'debit', 'amount' => 500, 'status' => 'pending'],
            ['event_type' => 'provider_specific_charge', 'direction' => 'debit', 'amount' => 12, 'status' => 'posted'],
        ]);

        $this->assertSame(1000.0, $summary['seller_revenue_net']);
        $this->assertSame(80.0, data_get($summary, 'categories.commission.cost_total'));
        $this->assertSame(50.0, data_get($summary, 'categories.cargo.cost_total'));
        $this->assertSame(15.0, data_get($summary, 'categories.service_fee.cost_total'));
        $this->assertSame(40.0, data_get($summary, 'categories.advertising.cost_total'));
        $this->assertSame(10.0, data_get($summary, 'categories.penalty.cost_total'));
        $this->assertSame(5.0, data_get($summary, 'categories.early_payment.cost_total'));
        $this->assertSame(25.0, data_get($summary, 'categories.discount.cost_total'));
        $this->assertSame(8.0, data_get($summary, 'categories.other.cost_total'));
        $this->assertSame(233.0, $summary['total_deductions']);
        $this->assertSame(['provider_specific_charge'], $summary['unmapped']);
    }

    public function test_classifier_exposes_category_event_types_for_sql_aggregates(): void
    {
        $types = FinancialEventClassifier::eventTypesForCategories([
            'service_fee',
            'advertising',
            'penalty',
        ]);

        $this->assertContains('service_fee', $types);
        $this->assertContains('international_operation_fee', $types);
        $this->assertContains('advertising', $types);
        $this->assertContains('operational_penalty', $types);
        $this->assertNotContains('seller_revenue', $types);
    }
}
