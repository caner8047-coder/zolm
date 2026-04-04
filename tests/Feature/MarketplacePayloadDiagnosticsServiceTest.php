<?php

namespace Tests\Feature;

use App\Services\Marketplace\MarketplacePayloadDiagnosticsService;
use Tests\TestCase;

class MarketplacePayloadDiagnosticsServiceTest extends TestCase
{
    public function test_it_summarizes_order_payload_gaps(): void
    {
        $diagnostics = app(MarketplacePayloadDiagnosticsService::class)->analyzeOrders([
            [
                'order' => ['order_number' => 'TY-1'],
                'package' => ['external_package_id' => 'PK-1', 'package_status' => 'Created'],
                'items' => [
                    ['external_line_id' => 'LN-1', 'stock_code' => 'STK-1', 'barcode' => 'BR-1', 'commission_rate' => 12, 'vat_rate' => 20],
                    ['external_line_id' => '', 'stock_code' => null, 'barcode' => null, 'commission_rate' => null, 'vat_rate' => null],
                ],
            ],
        ]);

        $this->assertSame(1, $diagnostics['package_count']);
        $this->assertSame(1, $diagnostics['order_count']);
        $this->assertSame(2, $diagnostics['item_count']);
        $this->assertSame(1, $diagnostics['missing_item_line_id_count']);
        $this->assertSame(1, $diagnostics['missing_stock_code_count']);
        $this->assertSame(1, $diagnostics['missing_barcode_count']);
        $this->assertNotEmpty($diagnostics['warnings']);
    }

    public function test_it_summarizes_product_payload_gaps(): void
    {
        $diagnostics = app(MarketplacePayloadDiagnosticsService::class)->analyzeProducts([
            [
                'product' => [
                    'external_product_id' => 'P-1',
                    'stock_code' => null,
                    'barcode' => null,
                    'title' => 'Deneme',
                ],
                'listing' => [
                    'listing_id' => '',
                    'sale_price' => null,
                    'stock_quantity' => null,
                    'listing_status' => 'active',
                ],
            ],
        ]);

        $this->assertSame(1, $diagnostics['product_count']);
        $this->assertSame(1, $diagnostics['missing_listing_id_count']);
        $this->assertSame(1, $diagnostics['missing_stock_code_count']);
        $this->assertSame(1, $diagnostics['missing_barcode_count']);
        $this->assertSame(1, $diagnostics['missing_sale_price_count']);
    }

    public function test_it_summarizes_financial_payload_gaps(): void
    {
        $diagnostics = app(MarketplacePayloadDiagnosticsService::class)->analyzeFinancialEvents([
            [
                'external_event_id' => '',
                'order_number' => '',
                'external_package_id' => '',
                'external_line_id' => '',
                'event_type' => 'commission',
                'amount' => null,
                'direction' => 'debit',
                'settlement_date' => null,
            ],
        ]);

        $this->assertSame(1, $diagnostics['event_count']);
        $this->assertSame(1, $diagnostics['missing_event_id_count']);
        $this->assertSame(1, $diagnostics['missing_order_number_count']);
        $this->assertSame(1, $diagnostics['missing_amount_count']);
        $this->assertNotEmpty($diagnostics['warnings']);
    }
}
