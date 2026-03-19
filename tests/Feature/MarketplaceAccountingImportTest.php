<?php

namespace Tests\Feature;

use App\Models\MpInvoice;
use App\Models\MpAuditLog;
use App\Models\MpOrder;
use App\Models\MpOperationalOrder;
use App\Models\MpOperationalOrderItem;
use App\Models\MpPeriod;
use App\Models\MpProduct;
use App\Models\MpSettlement;
use App\Models\MpTransaction;
use App\Models\User;
use App\Services\AuditEngine;
use App\Services\MarketplaceImportService;
use App\Services\MpSettingsService;
use App\Services\OrderDetailsService;
use App\Services\UnitEconomicsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class MarketplaceAccountingImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_stopaj_audit_writes_normalized_log_payload(): void
    {
        $user = User::factory()->create();
        $period = $this->createPeriod($user, 2025, 3);

        MpOrder::create([
            'period_id' => $period->id,
            'order_number' => 'AUD-STOPAJ-1',
            'barcode' => 'STP-1',
            'product_name' => 'Stopaj Test Urunu',
            'quantity' => 1,
            'gross_amount' => 110,
            'product_vat_rate' => 10,
            'withholding_tax' => 0.10,
            'status' => 'Teslim Edildi',
        ]);

        $this->runSingleAuditRule($period, 'checkStopaj');

        $log = MpAuditLog::where('period_id', $period->id)->firstOrFail();

        $this->assertSame('STOPAJ', $log->rule_code);
        $this->assertNotEmpty($log->title);
        $this->assertNotEmpty($log->description);
    }

    public function test_it_scopes_product_cost_lookup_to_the_explicit_import_user(): void
    {
        $otherUser = User::factory()->create();
        $targetUser = User::factory()->create();

        MpProduct::create([
            'user_id' => $otherUser->id,
            'barcode' => '8690000000001',
            'stock_code' => 'SKU-OTHER',
            'product_name' => 'Diger Kullanici Urunu',
            'cogs' => 10,
            'packaging_cost' => 1,
            'cargo_cost' => 2,
            'vat_rate' => 20,
        ]);

        MpProduct::create([
            'user_id' => $targetUser->id,
            'barcode' => '8690000000001',
            'stock_code' => 'SKU-TARGET',
            'product_name' => 'Hedef Kullanici Urunu',
            'cogs' => 45,
            'packaging_cost' => 4,
            'cargo_cost' => 5,
            'vat_rate' => 10,
        ]);

        $period = $this->createPeriod($targetUser, 2025, 1);
        $service = new MarketplaceImportService($targetUser->id);

        $file = $this->makeExcelUpload([
            ['Sipariş No', 'Sipariş Tarihi', 'Barkod', 'Adet', 'Satış Tutarı', 'Sipariş Durumu'],
            ['ORD-USER-SCOPE', '10.01.2025', '8690000000001', 2, '500,00', 'Teslim Edildi'],
        ], 'orders-user-scope.xlsx');

        $stats = $service->importOrders($file, $period);

        $order = MpOrder::where('order_number', 'ORD-USER-SCOPE')->firstOrFail();

        $this->assertSame(1, $stats['imported']);
        $this->assertSame('SKU-TARGET', $order->stock_code);
        $this->assertSame('Hedef Kullanici Urunu', $order->product_name);
        $this->assertSame(90.0, (float) $order->cogs_at_time);
        $this->assertSame(8.0, (float) $order->packaging_cost_at_time);
        $this->assertSame(10.0, (float) $order->product_vat_rate);
    }

    public function test_it_aggregates_and_distributes_withholding_by_order_number(): void
    {
        $user = User::factory()->create();
        $period = $this->createPeriod($user, 2025, 1);

        MpOrder::create([
            'period_id' => $period->id,
            'order_number' => '9900304971',
            'barcode' => 'BARKOD-1',
            'product_name' => 'Urun 1',
            'quantity' => 1,
            'gross_amount' => 100,
            'status' => 'Teslim Edildi',
        ]);

        MpOrder::create([
            'period_id' => $period->id,
            'order_number' => '9900304971',
            'barcode' => 'BARKOD-2',
            'product_name' => 'Urun 2',
            'quantity' => 1,
            'gross_amount' => 200,
            'status' => 'Teslim Edildi',
        ]);

        $service = new MarketplaceImportService($user->id);
        $file = $this->makeExcelUpload([
            ['Sipariş Numarası', 'Hesaplanan Stopaj Tutarı'],
            ['9900304971', '30,1818'],
            ['9900304971', '30,1818'],
        ], 'stopaj.xlsx');

        $stats = $service->importWithholdingTax($file, $period);

        $orders = MpOrder::where('order_number', '9900304971')->orderBy('gross_amount')->get();

        $this->assertSame(1, $stats['matched']);
        $this->assertSame(0, $stats['unmatched']);
        $this->assertEquals(20.12, (float) $orders[0]->withholding_tax);
        $this->assertEquals(40.24, (float) $orders[1]->withholding_tax);
        $this->assertEquals(60.36, (float) $orders->sum('withholding_tax'));
    }

    public function test_it_uses_source_line_number_for_transaction_idempotency_across_periods(): void
    {
        $user = User::factory()->create();
        $period = $this->createPeriod($user, 2025, 1);
        $service = new MarketplaceImportService($user->id);

        $file = $this->makeExcelUpload([
            ['İşlem Tarihi', 'Dekont No', 'Kalem NO', 'Sipariş No', 'İşlem Tipi', 'Borç', 'Alacak'],
            ['05.02.2025', 'DK-100', '1', 'ORD-TRANS', 'Komisyon Faturası', '10,00', '0'],
            ['05.02.2025', 'DK-100', '2', 'ORD-TRANS', 'Komisyon Faturası', '10,00', '0'],
        ], 'transactions.xlsx');

        $firstStats = $service->importTransactions($file, $period);
        $secondStats = $service->importTransactions($file, $period);

        $februaryPeriod = MpPeriod::where('user_id', $user->id)
            ->where('year', 2025)
            ->where('month', 2)
            ->firstOrFail();

        $transactions = MpTransaction::where('period_id', $februaryPeriod->id)
            ->orderBy('source_line_number')
            ->get();

        $this->assertSame(2, $firstStats['imported']);
        $this->assertSame(2, $secondStats['updated']);
        $this->assertCount(2, $transactions);
        $this->assertSame(['1', '2'], $transactions->pluck('source_line_number')->all());
    }

    public function test_it_preserves_signed_invoice_amounts_when_vat_breakdown_is_missing(): void
    {
        $user = User::factory()->create();
        $period = $this->createPeriod($user, 2026, 2);
        $service = new MarketplaceImportService($user->id);

        $file = $this->makeExcelUpload([
            ['Fatura No', 'Fatura Tarihi', 'Fatura Tipi', 'Tutar', 'Kategori'],
            ['INV-NEG-1', '24.02.2026', 'İade Hizmet Faturası', '-118,00', 'Hizmet'],
        ], 'invoices.xlsx');

        $stats = $service->importInvoices($file, $period);

        $invoice = MpInvoice::where('invoice_number', 'INV-NEG-1')->firstOrFail();

        $this->assertSame(1, $stats['imported']);
        $this->assertSame(-118.0, (float) $invoice->net_amount);
        $this->assertSame(0.0, (float) $invoice->vat_amount);
        $this->assertSame(0.0, (float) $invoice->vat_rate);
        $this->assertSame(-118.0, (float) $invoice->total_amount);
        $this->assertSame('Hizmet', $invoice->description);
    }

    public function test_payment_accessors_use_positive_settlements_instead_of_the_latest_negative_row(): void
    {
        $user = User::factory()->create();
        $period = $this->createPeriod($user, 2025, 1);

        $order = MpOrder::create([
            'period_id' => $period->id,
            'order_number' => 'ORD-PAYMENT',
            'barcode' => 'PAY-1',
            'product_name' => 'Odeme Test Urunu',
            'quantity' => 1,
            'gross_amount' => 250,
            'status' => 'Teslim Edildi',
        ]);

        MpSettlement::create([
            'user_id' => $user->id,
            'period_id' => $period->id,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'transaction_type' => 'Satış',
            'transaction_date' => '2025-01-14',
            'due_date' => '2025-01-15',
            'settlement_date' => '2025-01-16',
            'seller_hakedis' => 120,
            'total_amount' => 120,
        ]);

        MpSettlement::create([
            'user_id' => $user->id,
            'period_id' => $period->id,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'transaction_type' => 'İade',
            'transaction_date' => '2025-01-20',
            'due_date' => '2025-01-21',
            'settlement_date' => '2025-01-22',
            'seller_hakedis' => -20,
            'total_amount' => 20,
        ]);

        $freshOrder = MpOrder::with('settlements')->findOrFail($order->id);

        $this->assertTrue($freshOrder->is_paid);
        $this->assertNotNull($freshOrder->expected_payment_date);
        $this->assertSame('2025-01-16', $freshOrder->expected_payment_date->format('Y-m-d'));
    }

    public function test_payment_accessors_and_order_details_can_see_cross_period_settlements_for_same_order(): void
    {
        $user = User::factory()->create();
        $aprilPeriod = $this->createPeriod($user, 2025, 4);
        $mayPeriod = $this->createPeriod($user, 2025, 5);

        $order = MpOrder::create([
            'period_id' => $aprilPeriod->id,
            'order_number' => '10174002418',
            'barcode' => '4520051907',
            'stock_code' => '1BRJZEM00096',
            'product_name' => 'Petra Berjer, Beyaz Zemptr, One Size',
            'quantity' => 1,
            'order_date' => '2025-04-30 23:51:00',
            'delivery_date' => '2025-05-03 10:00:00',
            'gross_amount' => 4299.90,
            'net_hakedis' => 3666.22,
            'status' => 'Teslim Edildi',
        ]);

        MpSettlement::create([
            'user_id' => $user->id,
            'period_id' => $mayPeriod->id,
            'order_id' => null,
            'order_number' => $order->order_number,
            'document_number' => 'REC-10174002418',
            'transaction_type' => 'Satis',
            'transaction_date' => '2025-05-12',
            'due_date' => '2025-05-12',
            'settlement_date' => '2025-05-15',
            'seller_hakedis' => 3666.22,
            'total_amount' => 4299.90,
            'is_reconciled' => false,
        ]);

        $freshOrder = MpOrder::findOrFail($order->id);

        $this->assertTrue($freshOrder->is_paid);
        $this->assertNotNull($freshOrder->expected_payment_date);
        $this->assertSame('2025-05-15', $freshOrder->expected_payment_date->format('Y-m-d'));

        $details = (new OrderDetailsService())->getOrderDetails($order->id);

        $this->assertNotNull($details);
        $this->assertTrue($details['settlement']['has_settlement']);
        $this->assertSame('12.05.2025', $details['settlement']['due_date']);
        $this->assertSame('15.05.2025', $details['settlement']['settlement_date']);
        $this->assertSame(3666.22, $details['settlement']['seller_hakedis']);
    }

    public function test_missing_payment_audit_uses_cross_period_settlements(): void
    {
        $user = User::factory()->create();
        $aprilPeriod = $this->createPeriod($user, 2025, 4);
        $mayPeriod = $this->createPeriod($user, 2025, 5);

        $order = MpOrder::create([
            'period_id' => $aprilPeriod->id,
            'order_number' => 'AUD-PAY-XP-1',
            'barcode' => 'PAY-XP-1',
            'product_name' => 'Cross Period Odeme',
            'quantity' => 1,
            'gross_amount' => 150,
            'net_hakedis' => 120,
            'status' => 'Teslim Edildi',
        ]);

        $settlement = MpSettlement::create([
            'user_id' => $user->id,
            'period_id' => $mayPeriod->id,
            'order_number' => $order->order_number,
            'transaction_type' => 'Satis',
            'transaction_date' => '2025-05-10',
            'due_date' => '2025-05-12',
            'settlement_date' => '2025-05-15',
            'seller_hakedis' => 120,
            'total_amount' => 150,
            'is_reconciled' => false,
        ]);

        $this->runSingleAuditRule($aprilPeriod, 'checkMissingPayments');

        $this->assertDatabaseMissing('mp_audit_logs', [
            'period_id' => $aprilPeriod->id,
            'rule_code' => 'EKSIK_ODEME',
        ]);
        $this->assertTrue($settlement->fresh()->is_reconciled);
    }

    public function test_delayed_payment_audit_ignores_cross_period_payment_rows(): void
    {
        Carbon::setTestNow('2025-07-01 00:00:00');

        try {
            $user = User::factory()->create();
            $aprilPeriod = $this->createPeriod($user, 2025, 4);
            $mayPeriod = $this->createPeriod($user, 2025, 5);

            $order = MpOrder::create([
                'period_id' => $aprilPeriod->id,
                'order_number' => 'AUD-DELAY-XP-1',
                'barcode' => 'DLY-1',
                'product_name' => 'Gecikme Test Urunu',
                'quantity' => 1,
                'delivery_date' => '2025-04-10 10:00:00',
                'gross_amount' => 200,
                'net_hakedis' => 160,
                'status' => 'Teslim Edildi',
            ]);

            MpSettlement::create([
                'user_id' => $user->id,
                'period_id' => $mayPeriod->id,
                'order_number' => $order->order_number,
                'transaction_type' => 'Satis',
                'transaction_date' => '2025-05-05',
                'due_date' => '2025-05-08',
                'settlement_date' => '2025-05-12',
                'seller_hakedis' => 160,
                'total_amount' => 200,
            ]);

            $this->runSingleAuditRule($aprilPeriod, 'checkDelayedPayments');

            $this->assertDatabaseMissing('mp_audit_logs', [
                'period_id' => $aprilPeriod->id,
                'rule_code' => 'KAYIP_ODEME',
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_order_details_service_uses_operational_fallback_for_sparse_financial_rows(): void
    {
        $user = User::factory()->create();
        $period = $this->createPeriod($user, 2025, 12);

        MpProduct::create([
            'user_id' => $user->id,
            'barcode' => '1907584520',
            'stock_code' => '1PUFZEM00610',
            'product_name' => 'Lines Puf, Teddy Kumas Sutlu Kahve',
            'cogs' => 320,
            'packaging_cost' => 15,
            'cargo_cost' => 20,
            'vat_rate' => 10,
        ]);

        $order = MpOrder::create([
            'period_id' => $period->id,
            'order_number' => '10838774843',
            'quantity' => 2,
            'order_date' => '2025-12-31 23:29:00',
            'status' => 'Teslim Edildi',
            'gross_amount' => 1999.80,
            'commission_amount' => 289.98,
            'service_fee' => 10.19,
            'withholding_tax' => 16.67,
            'net_hakedis' => 1699.63,
        ]);

        $operationalOrder = MpOperationalOrder::create([
            'order_number' => $order->order_number,
            'order_date' => '2025-12-31 23:29:00',
            'delivery_date' => '2026-01-07 12:00:00',
            'status' => 'Teslim Edildi',
            'total_gross_amount' => 1999.80,
        ]);

        MpOperationalOrderItem::create([
            'operational_order_id' => $operationalOrder->id,
            'order_number' => $order->order_number,
            'barcode' => '1907584520',
            'stock_code' => '1PUFZEM00610',
            'product_name' => 'Lines Puf, Teddy Kumas Sutlu Kahve',
            'quantity' => 2,
            'unit_price' => 999.90,
            'sale_price' => 1999.80,
        ]);

        $details = (new OrderDetailsService())->getOrderDetails($order->id);

        $this->assertNotNull($details);
        $this->assertSame('1907584520', $details['basic']['barcode']);
        $this->assertSame('1PUFZEM00610', $details['basic']['stock_code']);
        $this->assertSame('Lines Puf, Teddy Kumas Sutlu Kahve', $details['basic']['product_name']);
        $this->assertSame('07.01.2026', $details['basic']['delivery_date']);
        $this->assertSame(640.0, $details['summary']['product_cost']);
        $this->assertFalse($details['settlement']['has_settlement']);
        $this->assertStringContainsString('Odeme Detay', $details['settlement']['missing_reason']);
    }

    public function test_profit_service_uses_raw_snapshot_and_stock_code_grouping_when_barcode_is_missing(): void
    {
        $user = User::factory()->create();
        $period = $this->createPeriod($user, 2025, 5);

        MpProduct::create([
            'user_id' => $user->id,
            'barcode' => 'BC-100',
            'stock_code' => 'SKU-100',
            'product_name' => 'Ham Finansal Urun A',
            'cogs' => 150,
            'packaging_cost' => 10,
            'cargo_cost' => 5,
            'vat_rate' => 10,
        ]);

        MpProduct::create([
            'user_id' => $user->id,
            'barcode' => 'BC-200',
            'stock_code' => 'SKU-200',
            'product_name' => 'Ham Finansal Urun B',
            'cogs' => 90,
            'packaging_cost' => 6,
            'cargo_cost' => 4,
            'vat_rate' => 10,
        ]);

        $firstOrder = MpOrder::create([
            'period_id' => $period->id,
            'order_number' => 'RAW-ORDER-1',
            'quantity' => 0,
            'gross_amount' => 500,
            'net_hakedis' => 420,
            'status' => 'Teslim Edildi',
            'raw_data' => [
                'stock_code' => 'SKU-100',
                'product_name' => 'Ham Finansal Urun A',
                'quantity' => 2,
            ],
        ]);

        $secondOrder = MpOrder::create([
            'period_id' => $period->id,
            'order_number' => 'RAW-ORDER-2',
            'quantity' => 0,
            'gross_amount' => 300,
            'net_hakedis' => 250,
            'status' => 'Teslim Edildi',
            'raw_data' => [
                'stock_code' => 'SKU-200',
                'product_name' => 'Ham Finansal Urun B',
                'quantity' => 1,
            ],
        ]);

        $this->assertSame('BC-100', $firstOrder->resolved_barcode);
        $this->assertSame('SKU-100', $firstOrder->resolved_stock_code);
        $this->assertSame('Ham Finansal Urun A', $firstOrder->resolved_product_name);
        $this->assertSame(2, $firstOrder->resolved_quantity);
        $this->assertSame(300.0, (float) $firstOrder->resolved_cogs_at_time);

        $this->assertSame('BC-200', $secondOrder->resolved_barcode);
        $this->assertSame('SKU-200', $secondOrder->resolved_stock_code);
        $this->assertSame('Ham Finansal Urun B', $secondOrder->resolved_product_name);
        $this->assertSame(1, $secondOrder->resolved_quantity);
        $this->assertSame(90.0, (float) $secondOrder->resolved_cogs_at_time);

        $profitItems = (new UnitEconomicsService())
            ->profitBySku($period)
            ->sortBy('stock_code')
            ->values();

        $this->assertCount(2, $profitItems);
        $this->assertSame('SKU-100', $profitItems[0]['stock_code']);
        $this->assertSame('BC-100', $profitItems[0]['barcode']);
        $this->assertSame(300.0, $profitItems[0]['total_cogs']);
        $this->assertSame('SKU-200', $profitItems[1]['stock_code']);
        $this->assertSame('BC-200', $profitItems[1]['barcode']);
        $this->assertSame(90.0, $profitItems[1]['total_cogs']);
    }

    public function test_missing_cogs_audit_uses_resolved_product_match_before_flagging(): void
    {
        $user = User::factory()->create();
        $period = $this->createPeriod($user, 2025, 5);

        MpProduct::create([
            'user_id' => $user->id,
            'barcode' => 'COGS-BC-1',
            'stock_code' => 'COGS-SKU-1',
            'product_name' => 'COGS Uyumlu Urun',
            'cogs' => 75,
            'packaging_cost' => 5,
            'cargo_cost' => 3,
            'vat_rate' => 10,
        ]);

        MpOrder::create([
            'period_id' => $period->id,
            'order_number' => 'COGS-AUD-1',
            'quantity' => 0,
            'gross_amount' => 400,
            'net_hakedis' => 320,
            'status' => 'Teslim Edildi',
            'raw_data' => [
                'stock_code' => 'COGS-SKU-1',
                'product_name' => 'COGS Uyumlu Urun',
                'quantity' => 2,
            ],
        ]);

        $this->runSingleAuditRule($period, 'checkMissingCogs');

        $this->assertDatabaseMissing('mp_audit_logs', [
            'period_id' => $period->id,
            'rule_code' => 'COGS_EKSIK',
        ]);
    }

    public function test_price_drop_audit_scopes_previous_period_to_same_user(): void
    {
        $currentUser = User::factory()->create();
        $otherUser = User::factory()->create();

        $currentPeriod = $this->createPeriod($currentUser, 2025, 5);
        $otherPrevPeriod = $this->createPeriod($otherUser, 2025, 4);

        foreach ([1, 2, 3] as $index) {
            MpOrder::create([
                'period_id' => $currentPeriod->id,
                'order_number' => 'CUR-PRICE-' . $index,
                'barcode' => 'SCOPE-BC-1',
                'product_name' => 'Scope Test Urunu',
                'quantity' => 1,
                'gross_amount' => 100,
                'status' => 'Teslim Edildi',
            ]);

            MpOrder::create([
                'period_id' => $otherPrevPeriod->id,
                'order_number' => 'OTH-PRICE-' . $index,
                'barcode' => 'SCOPE-BC-1',
                'product_name' => 'Scope Test Urunu',
                'quantity' => 1,
                'gross_amount' => 200,
                'status' => 'Teslim Edildi',
            ]);
        }

        $this->runSingleAuditRule($currentPeriod, 'checkPriceDrop');

        $this->assertDatabaseMissing('mp_audit_logs', [
            'period_id' => $currentPeriod->id,
            'rule_code' => 'FIYAT_DUSME',
        ]);
    }

    public function test_transaction_discrepancy_audit_logs_commission_reconciliation_gap(): void
    {
        $user = User::factory()->create();
        $period = $this->createPeriod($user, 2025, 6);

        MpOrder::create([
            'period_id' => $period->id,
            'order_number' => 'RECON-1',
            'barcode' => 'RECON-BC-1',
            'product_name' => 'Mutabakat Test Urunu',
            'quantity' => 1,
            'gross_amount' => 1000,
            'commission_amount' => 120,
            'cargo_amount' => 60,
            'status' => 'Teslim Edildi',
        ]);

        MpTransaction::create([
            'period_id' => $period->id,
            'transaction_date' => '2025-06-15',
            'document_number' => 'REC-COMM-1',
            'order_number' => 'RECON-1',
            'transaction_type' => 'Komisyon Faturasi',
            'description' => 'Komisyon kesintisi',
            'debt' => 80,
            'credit' => 0,
        ]);

        MpTransaction::create([
            'period_id' => $period->id,
            'transaction_date' => '2025-06-15',
            'document_number' => 'REC-CARGO-1',
            'order_number' => 'RECON-1',
            'transaction_type' => 'Kargo Faturasi',
            'description' => 'Kargo kesintisi',
            'debt' => 40,
            'credit' => 0,
        ]);

        $this->runSingleAuditRule($period, 'checkTransactionDiscrepancy');

        $log = MpAuditLog::where('period_id', $period->id)
            ->where('rule_code', 'CARI_UYUMSUZLUK')
            ->firstOrFail();

        $this->assertStringContainsString('Komisyon', $log->title);
        $this->assertSame(20.0, (float) $log->difference);
    }

    public function test_commission_mismatch_audit_uses_settlement_reference_rate(): void
    {
        $user = User::factory()->create();
        $period = $this->createPeriod($user, 2025, 7);

        $order = MpOrder::create([
            'period_id' => $period->id,
            'order_number' => 'COMM-MISMATCH-1',
            'barcode' => 'COMM-BC-1',
            'product_name' => 'Komisyon Referans Testi',
            'quantity' => 1,
            'gross_amount' => 100,
            'commission_rate' => 10,
            'commission_amount' => 10,
            'status' => 'Teslim Edildi',
        ]);

        MpSettlement::create([
            'user_id' => $user->id,
            'period_id' => $period->id,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'transaction_type' => 'Satis',
            'transaction_date' => '2025-07-10',
            'due_date' => '2025-07-15',
            'settlement_date' => '2025-07-17',
            'commission_rate' => 15,
            'seller_hakedis' => 75,
            'total_amount' => 100,
        ]);

        $this->runSingleAuditRule($period, 'checkCommissionMismatch');

        $log = MpAuditLog::where('period_id', $period->id)
            ->where('rule_code', 'KOMISYON_TUTARSIZLIGI')
            ->firstOrFail();

        $this->assertSame(15.0, (float) $log->expected_value);
        $this->assertSame(10.0, (float) $log->actual_value);
        $this->assertSame(5.0, (float) $log->difference);
    }

    public function test_price_drop_audit_uses_resolved_sku_when_financial_barcode_is_missing(): void
    {
        $user = User::factory()->create();
        $prevPeriod = $this->createPeriod($user, 2025, 4);
        $currentPeriod = $this->createPeriod($user, 2025, 5);

        foreach ([1, 2, 3] as $index) {
            MpOrder::create([
                'period_id' => $prevPeriod->id,
                'order_number' => 'RES-PREV-' . $index,
                'quantity' => 0,
                'gross_amount' => 200,
                'status' => 'Teslim Edildi',
                'raw_data' => [
                    'stock_code' => 'RES-SKU-1',
                    'product_name' => 'Resolved SKU Urunu',
                    'quantity' => 1,
                ],
            ]);

            MpOrder::create([
                'period_id' => $currentPeriod->id,
                'order_number' => 'RES-CUR-' . $index,
                'quantity' => 0,
                'gross_amount' => 100,
                'status' => 'Teslim Edildi',
                'raw_data' => [
                    'stock_code' => 'RES-SKU-1',
                    'product_name' => 'Resolved SKU Urunu',
                    'quantity' => 1,
                ],
            ]);
        }

        $this->runSingleAuditRule($currentPeriod, 'checkPriceDrop');

        $this->assertDatabaseHas('mp_audit_logs', [
            'period_id' => $currentPeriod->id,
            'rule_code' => 'FIYAT_DUSME',
        ]);
    }

    public function test_high_return_rate_audit_uses_resolved_quantity_and_sku_fallback(): void
    {
        $user = User::factory()->create();
        $period = $this->createPeriod($user, 2025, 8);

        MpOrder::create([
            'period_id' => $period->id,
            'order_number' => 'RET-OK-1',
            'quantity' => 0,
            'gross_amount' => 400,
            'status' => 'Teslim Edildi',
            'raw_data' => [
                'stock_code' => 'RET-SKU-1',
                'product_name' => 'Resolved Iade Urunu',
                'quantity' => 4,
            ],
        ]);

        MpOrder::create([
            'period_id' => $period->id,
            'order_number' => 'RET-BAD-1',
            'quantity' => 0,
            'gross_amount' => 200,
            'status' => 'İade Edildi',
            'raw_data' => [
                'stock_code' => 'RET-SKU-1',
                'product_name' => 'Resolved Iade Urunu',
                'quantity' => 2,
            ],
        ]);

        $this->runSingleAuditRule($period, 'checkHighReturnRate');

        $this->assertDatabaseHas('mp_audit_logs', [
            'period_id' => $period->id,
            'rule_code' => 'YUKSEK_IADE',
        ]);
    }

    public function test_auxiliary_audit_codes_resolve_meta_payload(): void
    {
        $meta = AuditEngine::getMetaByCode('KISMI_IADE');

        $this->assertNotNull($meta);
        $this->assertSame('KISMI_IADE', $meta['code']);
        $this->assertSame('info', $meta['severity']);
    }

    public function test_earsiv_reminder_does_not_report_count_when_info_logs_are_disabled(): void
    {
        $user = User::factory()->create();
        $period = $this->createPeriod($user, 2025, 9);

        MpOrder::create([
            'period_id' => $period->id,
            'order_number' => 'EARSIV-1',
            'gross_amount' => 250,
            'status' => 'İade Edildi',
        ]);

        $result = $this->runSingleAuditRule($period, 'checkEarsivReminder');

        $this->assertSame(0, $result['rules_run']['checkEarsivReminder']);
        $this->assertDatabaseMissing('mp_audit_logs', [
            'period_id' => $period->id,
            'rule_code' => 'EARSIV_UYARI',
        ]);
    }

    public function test_campaign_loss_ignores_resolved_own_cargo_when_setting_is_disabled(): void
    {
        $user = User::factory()->create();
        $period = $this->createPeriod($user, 2025, 10);

        (new MpSettingsService($user->id))->set('cargo.uses_own_cargo', false);

        MpProduct::create([
            'user_id' => $user->id,
            'stock_code' => 'CMP-SKU-1',
            'product_name' => 'Kampanya Test Urunu',
            'cogs' => 60,
            'packaging_cost' => 10,
            'cargo_cost' => 20,
        ]);

        MpOrder::create([
            'period_id' => $period->id,
            'order_number' => 'CMP-1',
            'quantity' => 1,
            'gross_amount' => 100,
            'net_hakedis' => 75,
            'campaign_discount' => 10,
            'status' => 'Teslim Edildi',
            'raw_data' => [
                'stock_code' => 'CMP-SKU-1',
                'product_name' => 'Kampanya Test Urunu',
                'quantity' => 1,
            ],
        ]);

        $result = $this->runSingleAuditRule($period, 'checkCampaignLoss');

        $this->assertSame(0, $result['rules_run']['checkCampaignLoss']);
        $this->assertDatabaseMissing('mp_audit_logs', [
            'period_id' => $period->id,
            'rule_code' => 'KAMPANYA_ZARAR',
        ]);
    }

    private function createPeriod(User $user, int $year, int $month): MpPeriod
    {
        return MpPeriod::create([
            'user_id' => $user->id,
            'seller_id' => 'SELLER-' . $user->id,
            'year' => $year,
            'month' => $month,
            'marketplace' => 'trendyol',
            'status' => 'draft',
        ]);
    }

    private function runSingleAuditRule(MpPeriod $period, string $rule): array
    {
        $disabledRules = array_values(array_diff(AuditEngine::RULES, [$rule]));

        return (new AuditEngine())->runAllRules($period, $disabledRules);
    }

    /**
     * @param  array<int, array<int, scalar|null>>  $rows
     */
    private function makeExcelUpload(array $rows, string $filename): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($rows, null, 'A1', true);

        $path = storage_path('framework/testing/' . uniqid('mp-import-', true) . '-' . $filename);
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return new UploadedFile(
            $path,
            $filename,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}
