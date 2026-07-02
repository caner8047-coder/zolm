<?php

namespace Tests\Feature;

use App\Livewire\MarketplaceProfitCenter;
use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\MpProfitActionEvent;
use App\Models\MpProfitActionItem;
use App\Models\MpProduct;
use App\Models\OrderFinancialEvent;
use App\Models\OrderProfitSnapshot;
use App\Models\User;
use App\Services\Marketplace\MarketplaceProfitActionService;
use App\Services\Marketplace\MarketplaceProfitCenterQueryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class MarketplaceProfitCenterTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.host', 'mysql');
        config()->set('database.connections.mysql.port', '3306');
        config()->set('database.connections.mysql.database', 'zolm');
        config()->set('database.connections.mysql.username', 'sail');
        config()->set('database.connections.mysql.password', 'password');
        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::setDefaultConnection('mysql');
    }

    public function test_profit_center_service_summarizes_profit_risk_and_cost_readiness(): void
    {
        [$user, $lossOrder] = $this->createProfitCenterGraph();

        $service = app(MarketplaceProfitCenterQueryService::class);
        $filters = [
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
        ];

        $summary = $service->summary($user->id, $filters);
        $costReadiness = $service->costReadiness($user->id, $filters);
        $executiveCommand = $service->executiveCommandSummary($user->id, $filters);
        $dailyTrend = $service->dailyTrend($user->id, $filters);
        $deductionBreakdown = collect($service->deductionBreakdown($user->id, $filters))->keyBy('key');
        $signals = collect($service->riskSignals($user->id, $filters))->keyBy('key');
        $topLossOrders = $service->topLossOrders($user->id, $filters);
        $recommendations = collect($service->priorityRecommendations($user->id, $filters))->keyBy('key');
        $orderFunnel = collect($service->orderRiskFunnel($user->id, $filters))->keyBy('key');
        $orderQueue = $service->orderDecisionQueue($user->id, $filters);
        $orderInsights = $service->orderDecisionInsights($user->id, $filters);
        $products = collect($service->productProfitability($user->id, $filters))->keyBy('stock_code');
        $productInsights = $service->productReadinessInsights($user->id, $filters);
        $analyticsSheets = collect($service->managerAnalyticsExportSheets($user->id, $filters))->keyBy('name');

        $this->assertSame(3, $summary['total_orders']);
        $this->assertSame(1600.0, $summary['gross_revenue']);
        $this->assertSame(70.0, $summary['profit_value']);
        $this->assertSame(1, $summary['loss_order_count']);
        $this->assertSame(1, $summary['material_variance_order_count']);
        $this->assertSame(1, $summary['finance_waiting_order_count']);
        $this->assertSame(1, $summary['snapshot_missing_order_count']);

        $this->assertSame(3, $costReadiness['total_lines']);
        $this->assertSame(1, $costReadiness['unmatched_lines']);
        $this->assertSame(1, $costReadiness['missing_cost_lines']);
        $this->assertSame(33.3, $costReadiness['ready_percent']);

        $this->assertSame('Kritik', $executiveCommand['score_label']);
        $this->assertSame('Maliyet hazırlığı', $executiveCommand['primary_focus']);
        $this->assertSame('mp.products', $executiveCommand['primary_action']['route']);
        $this->assertCount(4, $executiveCommand['metrics']);
        $this->assertLessThan(50, $executiveCommand['score']);

        $this->assertCount(1, $dailyTrend);
        $this->assertSame(1600.0, $dailyTrend[0]['gross_revenue']);
        $this->assertSame(70.0, $dailyTrend[0]['profit_value']);
        $this->assertSame(168.0, $deductionBreakdown['commission']['value']);
        $this->assertSame(50.0, $deductionBreakdown['cargo']['value']);

        $this->assertSame(1, $signals['loss_orders']['value']);
        $this->assertSame(2, $signals['missing_cost']['value']);
        $this->assertSame($lossOrder->order_number, $topLossOrders[0]['order_number']);
        $this->assertSame(1, $recommendations['material_variance']['value']);
        $this->assertSame('Finans ekibi', $recommendations['material_variance']['default_owner']);
        $this->assertSame(MpProfitActionItem::PRIORITY_HIGH, $recommendations['material_variance']['default_priority']);
        $this->assertSame(2, $recommendations['material_variance']['due_in_days']);
        $this->assertNotEmpty($recommendations['material_variance']['playbook_steps']);
        $this->assertSame(2, $recommendations['missing_cost']['value']);
        $this->assertSame(1, $orderFunnel['loss_orders']['value']);
        $this->assertSame($lossOrder->order_number, $orderQueue[0]['order_number']);
        $this->assertSame('Negatif kâr', $orderQueue[0]['primary_reason']);
        $this->assertSame('Maliyet ve iade etkisini kontrol et', $orderQueue[0]['action_hint']);
        $this->assertGreaterThan(0, $orderInsights['queue_count']);
        $this->assertSame('Negatif kâr', $orderInsights['top_reason']);
        $this->assertNotEmpty($orderInsights['reason_distribution']);
        $this->assertSame(1, $products['READY-' . substr($lossOrder->order_number, -6)]['order_count']);
        $this->assertSame(2, $productInsights['risk_product_count']);
        $this->assertSame(600.0, $productInsights['affected_revenue']);
        $this->assertStringContainsString('eşleşmeyen', mb_strtolower($productInsights['decision_hint']));
        $this->assertSame('Hazır', $products['READY-' . substr($lossOrder->order_number, -6)]['decision_hint']);
        $this->assertTrue($analyticsSheets->has('Siparis Risk Yogunlugu'));
        $this->assertTrue($analyticsSheets->has('Siparis Karar Kuyrugu'));
        $this->assertTrue($analyticsSheets->has('Urun Marj Maliyet'));
        $this->assertTrue($analyticsSheets->has('Urun Performans'));
        $this->assertStringContainsString('Risk bandı', collect($analyticsSheets['Siparis Risk Yogunlugu']['data'])->flatten()->implode('|'));
        $this->assertStringContainsString('Marj dağılımı', collect($analyticsSheets['Urun Marj Maliyet']['data'])->flatten()->implode('|'));
    }

    public function test_profit_center_uses_finance_events_when_snapshot_is_missing(): void
    {
        [$user, , , $snapshotMissingOrder] = $this->createProfitCenterGraph();

        $service = app(MarketplaceProfitCenterQueryService::class);
        $filters = [
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
        ];

        $summary = $service->summary($user->id, $filters);
        $queue = collect($service->orderDecisionQueue($user->id, $filters, 20))->keyBy('order_number');

        $this->assertSame(1350.0, $summary['net_receivable']);
        $this->assertSame(250.0, $summary['total_deductions']);
        $this->assertSame(168.0, $summary['commission_total']);
        $this->assertSame(50.0, $summary['cargo_total']);
        $this->assertSame(22.0, $summary['service_fee_total']);
        $this->assertSame(10.0, $summary['withholding_total']);
        $this->assertSame(1, $summary['snapshot_missing_order_count']);

        $snapshotMissingRow = $queue[$snapshotMissingOrder->order_number];

        $this->assertSame('snapshot_missing', $snapshotMissingRow['reconciliation_state']);
        $this->assertSame(200.0, $snapshotMissingRow['gross_revenue']);
        $this->assertSame(20.0, $snapshotMissingRow['deduction_total']);
        $this->assertSame(0.0, $snapshotMissingRow['profit_value']);
        $this->assertContains('Kâr kaydı eksik', $snapshotMissingRow['reasons']);
    }

    public function test_profit_center_folds_extended_cost_categories_into_reconciliation_totals(): void
    {
        [$user, , , $snapshotMissingOrder] = $this->createProfitCenterGraph();

        foreach ([
            ['event_type' => 'advertising', 'direction' => 'debit', 'amount' => 30],
            ['event_type' => 'return_cargo', 'direction' => 'debit', 'amount' => 10],
            ['event_type' => 'penalty', 'direction' => 'debit', 'amount' => 5],
        ] as $index => $event) {
            OrderFinancialEvent::query()->create([
                'store_id' => $snapshotMissingOrder->store_id,
                'legal_entity_id' => $snapshotMissingOrder->legal_entity_id,
                'channel_order_id' => $snapshotMissingOrder->id,
                'event_source' => 'extended-cost-test',
                'event_type' => $event['event_type'],
                'external_event_id' => $snapshotMissingOrder->order_number . '-EXT-' . $index,
                'event_date' => now(),
                'settlement_date' => now(),
                'amount' => $event['amount'],
                'currency' => 'TRY',
                'direction' => $event['direction'],
                'status' => 'settled',
            ]);
        }

        $service = app(MarketplaceProfitCenterQueryService::class);
        $filters = [
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
        ];

        $summary = $service->summary($user->id, $filters);
        $deductionBreakdown = collect($service->deductionBreakdown($user->id, $filters))->keyBy('key');

        $this->assertSame(1305.0, $summary['net_receivable']);
        $this->assertSame(295.0, $summary['total_deductions']);
        $this->assertSame(60.0, $summary['cargo_total']);
        $this->assertSame(57.0, $summary['service_fee_total']);
        $this->assertSame('Hizmet ve diğer', $deductionBreakdown['service_fee']['label']);
        $this->assertSame(57.0, $deductionBreakdown['service_fee']['value']);
    }

    public function test_profit_center_marks_packaging_cost_gap_as_not_ready(): void
    {
        [$user, $lossOrder] = $this->createProfitCenterGraph();
        $store = MarketplaceStore::query()->findOrFail($lossOrder->store_id);
        $legalEntity = LegalEntity::query()->findOrFail($lossOrder->legal_entity_id);
        $suffix = (string) random_int(100000, 999999);

        $packagingMissingProduct = MpProduct::query()->create([
            'user_id' => $user->id,
            'barcode' => 'PACK-MISS-' . $suffix,
            'stock_code' => 'PACK-MISS-' . $suffix,
            'product_name' => 'Ambalaj Maliyeti Eksik Ürün',
            'cogs' => 120,
            'packaging_cost' => 0,
            'commission_rate' => 10,
            'status' => 'active',
        ]);

        $this->createOrder(
            $store,
            $legalEntity,
            'KAR-PACK-MISS-' . $suffix,
            300,
            10,
            $packagingMissingProduct->id,
            [
                'profit_state' => 'estimated',
                'gross_revenue' => 300,
                'net_receivable' => 270,
                'commission_total' => 30,
                'cargo_total' => 0,
                'service_fee_total' => 0,
                'withholding_total' => 0,
                'cogs_cost' => 120,
                'packaging_cost' => 0,
                'estimated_profit' => 150,
                'confirmed_profit' => 0,
                'margin_percent' => 125,
            ],
            [],
        );

        $service = app(MarketplaceProfitCenterQueryService::class);
        $filters = [
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
        ];

        $readiness = $service->costReadiness($user->id, $filters);
        $products = collect($service->productProfitability($user->id, $filters, 20))->keyBy('stock_code');

        $this->assertSame(4, $readiness['total_lines']);
        $this->assertSame(1, $readiness['unmatched_lines']);
        $this->assertSame(2, $readiness['missing_cost_lines']);
        $this->assertSame(25.0, $readiness['ready_percent']);
        $this->assertSame(1, $products[$packagingMissingProduct->stock_code]['missing_cost_lines']);
        $this->assertSame('Eksik', $products[$packagingMissingProduct->stock_code]['readiness_label']);
        $this->assertSame('Maliyeti tamamla', $products[$packagingMissingProduct->stock_code]['decision_hint']);
    }

    public function test_profit_center_normalizes_sale_refund_and_fee_events_when_snapshot_is_missing(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'ZOLM Refund Ltd.',
            'tax_number' => '8' . $suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'shopify',
            'store_name' => 'Kar Merkezi Shopify',
            'store_code' => 'KAR-SH-' . $suffix,
            'seller_id' => 'KAR-SH-' . $suffix,
            'status' => 'active',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $product = MpProduct::query()->create([
            'user_id' => $user->id,
            'barcode' => 'REFUND-' . $suffix,
            'stock_code' => 'REFUND-' . $suffix,
            'product_name' => 'İadeli Finans Ürünü',
            'cogs' => 250,
            'packaging_cost' => 20,
            'commission_rate' => 10,
            'status' => 'active',
        ]);

        $order = $this->createOrder(
            $store,
            $legalEntity,
            'KAR-REFUND-' . $suffix,
            1000,
            10,
            $product->id,
            null,
            [
                ['event_type' => 'sale', 'direction' => 'credit', 'amount' => 1000],
                ['event_type' => 'refund', 'direction' => 'debit', 'amount' => 300],
                ['event_type' => 'commission', 'direction' => 'debit', 'amount' => 100],
                ['event_type' => 'commission', 'direction' => 'credit', 'amount' => 30],
                ['event_type' => 'fee', 'direction' => 'debit', 'amount' => 25],
            ],
        );

        $service = app(MarketplaceProfitCenterQueryService::class);
        $filters = [
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
        ];

        $summary = $service->summary($user->id, $filters);
        $queue = collect($service->orderDecisionQueue($user->id, $filters, 20))->keyBy('order_number');

        $this->assertSame(1, $summary['total_orders']);
        $this->assertSame(1000.0, $summary['gross_revenue']);
        $this->assertSame(605.0, $summary['net_receivable']);
        $this->assertSame(95.0, $summary['total_deductions']);
        $this->assertSame(70.0, $summary['commission_total']);
        $this->assertSame(25.0, $summary['service_fee_total']);
        $this->assertSame(1, $summary['finance_ready_order_count']);
        $this->assertSame(1, $summary['snapshot_missing_order_count']);

        $orderRow = $queue[$order->order_number];

        $this->assertSame('snapshot_missing', $orderRow['reconciliation_state']);
        $this->assertSame(95.0, $orderRow['deduction_total']);
        $this->assertContains('Kâr kaydı eksik', $orderRow['reasons']);
        $this->assertNotContains('Finans bekliyor', $orderRow['reasons']);
    }

    public function test_profit_center_does_not_mark_authorization_or_pending_events_as_finance_ready(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'ZOLM Pending Ltd.',
            'tax_number' => '9' . $suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'shopify',
            'store_name' => 'Kar Merkezi Pending',
            'store_code' => 'KAR-PEND-' . $suffix,
            'seller_id' => 'KAR-PEND-' . $suffix,
            'status' => 'active',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $product = MpProduct::query()->create([
            'user_id' => $user->id,
            'barcode' => 'PEND-' . $suffix,
            'stock_code' => 'PEND-' . $suffix,
            'product_name' => 'Bekleyen Finans Ürünü',
            'cogs' => 150,
            'packaging_cost' => 15,
            'commission_rate' => 10,
            'status' => 'active',
        ]);

        $authorizationOrder = $this->createOrder(
            $store,
            $legalEntity,
            'KAR-AUTH-' . $suffix,
            1000,
            10,
            $product->id,
            null,
            [
                ['event_type' => 'authorization', 'direction' => 'credit', 'amount' => 1000, 'status' => 'pending'],
            ],
        );

        $pendingSaleOrder = $this->createOrder(
            $store,
            $legalEntity,
            'KAR-PENDING-SALE-' . $suffix,
            500,
            10,
            $product->id,
            null,
            [
                ['event_type' => 'sale', 'direction' => 'credit', 'amount' => 500, 'status' => 'pending'],
                ['event_type' => 'fee', 'direction' => 'debit', 'amount' => 20, 'status' => 'pending'],
            ],
        );

        $service = app(MarketplaceProfitCenterQueryService::class);
        $filters = [
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
        ];

        $summary = $service->summary($user->id, $filters);
        $queue = collect($service->orderDecisionQueue($user->id, $filters, 20))->keyBy('order_number');

        $this->assertSame(2, $summary['total_orders']);
        $this->assertSame(1500.0, $summary['gross_revenue']);
        $this->assertSame(0.0, $summary['net_receivable']);
        $this->assertSame(0.0, $summary['total_deductions']);
        $this->assertSame(0, $summary['finance_ready_order_count']);
        $this->assertSame(2, $summary['finance_waiting_order_count']);
        $this->assertSame(0, $summary['snapshot_missing_order_count']);
        $this->assertSame(2, $summary['waiting_reconciliation_order_count']);

        foreach ([$authorizationOrder, $pendingSaleOrder] as $order) {
            $this->assertSame('waiting', $queue[$order->order_number]['reconciliation_state']);
            $this->assertSame(0.0, $queue[$order->order_number]['deduction_total']);
            $this->assertContains('Finans bekliyor', $queue[$order->order_number]['reasons']);
            $this->assertNotContains('Kâr kaydı eksik', $queue[$order->order_number]['reasons']);
        }
    }

    public function test_profit_center_livewire_screen_renders_and_filters(): void
    {
        [$user, $lossOrder, $waitingOrder] = $this->createProfitCenterGraph();

        $this->actingAs($user);

        $component = Livewire::test(MarketplaceProfitCenter::class)
            ->assertSee('Kâr Kokpiti')
            ->assertSee('Yönetici komuta özeti')
            ->assertSee('Karar skoru')
            ->assertSee('Birincil odak')
            ->assertSee('Açık aksiyon')
            ->assertSee('Yönetici karar radarı')
            ->assertSee('Otomatik odak')
            ->assertSee('Önerilen hamle')
            ->assertSee('Kâr hesabı güveni')
            ->assertSee('Risk kuyruğu')
            ->assertSee('Maliyet hazırlığı')
            ->assertSee('Operasyon baskısı')
            ->assertSee('Hesaplama güveni')
            ->assertSee('Kâr rakamı güven skoru')
            ->assertSee('Finans kesinliği')
            ->assertSee('Snapshot kapsamı')
            ->assertSee('KDV modu')
            ->assertSee('Stopaj modu')
            ->assertSee('Finansı aç')
            ->assertSee('Takibe al')
            ->assertSee('Öncelik önerileri')
            ->assertSee('Mutabakat farkını kapat')
            ->assertSee('Aksiyon masası')
            ->assertSee('Liste modu')
            ->assertSee('Yönetici özeti')
            ->assertSee('Aksiyon filtreleri')
            ->assertSee('Hızlı odak')
            ->assertSee('Yönetici raporu indir')
            ->assertSee('Yönetici raporu kapsamı')
            ->assertSee('Excel sayfası')
            ->assertSee('İndirme öncesi kontrol')
            ->assertSee('Rapor hazırlanıyor')
            ->assertSee('Rapor sayfaları')
            ->assertSee('Sipariş risk yoğunluğu')
            ->assertSee('Ürün performansı')
            ->assertSee('Kapanış kalitesi')
            ->assertSee('Haftalık trend')
            ->assertSee('Aksiyona al')
            ->assertSee('Kâr akışı')
            ->assertSee('Kesinti kompozisyonu')
            ->assertSee($lossOrder->order_number);

        $this->assertContains($component->instance()->calculationHealth['score_label'], [
            'Yüksek güven',
            'İzlenebilir',
            'Kontrollü risk',
            'Düşük güven',
            'Veri bekliyor',
        ]);
        $this->assertCount(4, $component->instance()->calculationHealth['cards']);
        $this->assertCount(2, $component->instance()->calculationHealth['assumptions']);
        $this->assertNotEmpty($component->instance()->calculationHealth['gap_actions']);
        $this->assertNotEmpty($component->instance()->managerReportPreview['readiness']['warnings']);
        $this->assertCount(4, $component->instance()->executiveDecisionRadar['cards']);
        $this->assertArrayHasKey('primary', $component->instance()->executiveDecisionRadar);

        $component
            ->call('setExecutiveRadarFocus', 'orders')
            ->assertSet('executiveRadarFocus', 'orders')
            ->assertSee('Siparişleri aç');

        $component
            ->set('executiveRadarFocus', 'invalid')
            ->assertSet('executiveRadarFocus', 'auto');

        $component->call('trackRecommendation', 'material_variance');
        $component->assertSee('Önerilen işlem planı')
            ->assertSee('Tahmini snapshot ile kesin finans hareketini karşılaştır')
            ->assertSee('Sinyal ilerlemesi')
            ->assertSee('Aksiyon kompozisyonu')
            ->assertSee('Aksiyon sağlığı')
            ->assertSee('Risk sürücüleri')
            ->assertSee('Haftalık tempo')
            ->assertSee('Önerilen sonraki hamle')
            ->assertSee('Yüksek öncelikli işleri incelemeye al')
            ->assertSee('Plan açığı')
            ->assertSee('Öncelik baskısı')
            ->assertSee('Hedef yaşlanması')
            ->assertSee('Komuta kuyruğu')
            ->assertSee('Yüksek önceliğe odaklan')
            ->assertSee('Neden bu sırada?')
            ->assertSee('Önerilen ilk adım')
            ->assertSee('Plan ilerleme')
            ->assertSee('Sıradaki adım')
            ->assertSee('Akıllı öneriler')
            ->assertSee('Sorumlu öner')
            ->assertSee('Hedef yenile')
            ->assertSee('Plan için aç')
            ->assertSee('Aksiyon görünümü')
            ->assertSee('Detaylı')
            ->assertSee('Kompakt')
            ->assertSee('Kritik aksiyonları göster');

        $action = MpProfitActionItem::query()
            ->where('user_id', $user->id)
            ->where('action_key', 'material_variance')
            ->first();

        $this->assertNotNull($action);
        $this->assertSame(MpProfitActionItem::STATUS_OPEN, $action->status);
        $this->assertSame(MpProfitActionItem::PRIORITY_HIGH, $action->priority);
        $this->assertSame('Finans ekibi', $action->owner_label);
        $this->assertSame(now()->addDays(2)->toDateString(), $action->due_date->toDateString());
        $this->assertSame('Finans ekibi', $action->recommendation_json['default_owner']);
        $this->assertNotEmpty($action->recommendation_json['playbook_steps']);
        $this->assertSame(1, (int) data_get($action->recommendation_json, 'baseline_signal.value'));
        $this->assertNotNull(data_get($action->recommendation_json, 'baseline_signal.health_score'));
        $this->assertSame(1, $component->instance()->actionReport['action_distribution']['total_count']);
        $this->assertSame(1, $component->instance()->actionReport['action_distribution']['active_count']);
        $this->assertArrayHasKey('action_health', $component->instance()->actionReport);
        $this->assertSame(1, $component->instance()->actionReport['action_health']['active_count']);
        $this->assertNotEmpty($component->instance()->actionReport['action_health']['cards']);
        $this->assertNotEmpty($component->instance()->actionReport['action_health']['drivers']);
        $this->assertSame('Yüksek', collect($component->instance()->actionReport['action_distribution']['priority_rows'])->firstWhere('key', MpProfitActionItem::PRIORITY_HIGH)['label']);
        $this->assertSame('3 gün içinde', collect($component->instance()->actionReport['action_distribution']['aging_rows'])->firstWhere('key', 'due_soon')['label']);
        $this->assertNotEmpty($component->instance()->actionCommandQueue($component->instance()->trackedActions));
        $this->assertSame(1, collect($component->instance()->actionQuickFocusControls())->firstWhere('key', 'high_priority')['count']);
        $this->assertSame('high_priority', $component->instance()->actionNextMoveRecommendations()['primary']['key']);
        $this->assertSame(0.0, (float) $component->instance()->trackedActions[0]['playbook_progress']['percent']);

        $component
            ->set('actionListDensity', 'compact')
            ->assertSet('actionListDensity', 'compact')
            ->assertSee('Kompakt');

        $component
            ->set('actionListDensity', 'invalid')
            ->assertSet('actionListDensity', 'detailed');

        $component
            ->call('toggleActionStep', $action->id, 0)
            ->assertSee('Tamamlandı');

        $action->refresh();
        $this->assertSame([0], data_get($action->recommendation_json, 'completed_playbook_steps'));
        $this->assertSame(33.3, (float) $component->instance()->trackedActions[0]['playbook_progress']['percent']);
        $this->assertSame('Tahmini snapshot ile kesin finans hareketini karşılaştır.', $component->instance()->trackedActions[0]['playbook_progress']['next_step_label']);

        $component->call('resolveAction', $action->id);
        $this->assertSame(MpProfitActionItem::STATUS_OPEN, $action->fresh()->status);

        $this->assertDatabaseHas('mp_profit_action_events', [
            'mp_profit_action_item_id' => $action->id,
            'event_type' => MpProfitActionEvent::TYPE_CREATED,
            'to_status' => MpProfitActionItem::STATUS_OPEN,
        ]);

        $targetDate = now()->addDays(4)->toDateString();

        $component
            ->set('actionPriorities.' . $action->id, MpProfitActionItem::PRIORITY_HIGH)
            ->set('actionDueDates.' . $action->id, $targetDate)
            ->set('actionOwners.' . $action->id, 'Finans ekibi')
            ->call('saveActionMeta', $action->id);

        $this->assertSame(MpProfitActionItem::PRIORITY_HIGH, $action->fresh()->priority);
        $this->assertSame($targetDate, $action->fresh()->due_date->toDateString());
        $this->assertSame('Finans ekibi', $action->fresh()->owner_label);
        $this->assertDatabaseHas('mp_profit_action_events', [
            'mp_profit_action_item_id' => $action->id,
            'event_type' => MpProfitActionEvent::TYPE_PLAN_UPDATED,
        ]);
        $this->assertSame(1, $component->instance()->actionSummary['high_priority']);
        $this->assertSame(0, $component->instance()->actionReport['unowned']);
        $this->assertSame('Finans ekibi', $component->instance()->actionReport['owner_workload'][0]['owner']);
        $this->assertSame(1, $component->instance()->actionReport['owner_workload'][0]['count']);

        $component
            ->set('actionPriorityFilter', MpProfitActionItem::PRIORITY_HIGH)
            ->set('actionSort', 'impact')
            ->assertSee('Finansal etkiye göre')
            ->assertSee('Finans ekibi');

        $this->assertCount(1, $component->instance()->trackedActions);

        $component->set('actionOwnerFilter', '__unowned');
        $this->assertCount(0, $component->instance()->trackedActions);

        $component
            ->call('resetActionListFilters')
            ->assertSet('actionPriorityFilter', '')
            ->assertSet('actionOwnerFilter', '')
            ->assertSet('actionFocusFilter', '')
            ->assertSet('actionSort', 'priority');

        $this->assertCount(1, $component->instance()->trackedActions);

        $actionService = app(MarketplaceProfitActionService::class);
        $activeFilters = [
            'marketplace' => '',
            'store_id' => '',
            'legal_entity_id' => '',
            'date_from' => $component->instance()->dateFrom,
            'date_to' => $component->instance()->dateTo,
        ];
        $legacyAction = MpProfitActionItem::query()->create([
            'user_id' => $user->id,
            'scope_hash' => $actionService->scopeHash($activeFilters),
            'fingerprint' => sha1('legacy-finance-waiting-' . $user->id . '-' . random_int(1000, 9999)),
            'action_key' => 'finance_waiting',
            'title' => 'Eski finans bekleyen aksiyon',
            'description' => 'Playbook metadata olmadan oluşturulmuş eski kayıt.',
            'action_label' => 'Bekleyen finansı aç',
            'route_name' => 'mp.finance',
            'query_json' => ['financialStateFilter' => 'waiting'],
            'filters_json' => $activeFilters,
            'recommendation_json' => [],
            'value' => 1,
            'impact' => 2500,
            'score' => 400,
            'status' => MpProfitActionItem::STATUS_OPEN,
            'priority' => MpProfitActionItem::PRIORITY_MEDIUM,
            'due_date' => now()->addDays(5)->startOfDay(),
            'last_seen_at' => now(),
        ]);
        $legacySerialized = collect($actionService->actionItems($user->id, $activeFilters, 20, 'all'))
            ->firstWhere('id', $legacyAction->id);

        $this->assertSame('Finans operasyon', $legacySerialized['default_owner']);
        $this->assertSame('Hakediş/finans olayı oluşmamış siparişlerin net alacak ve kâr kesinliğini tamamla.', $legacySerialized['plan_summary']);
        $this->assertContains('Hakediş dosyası veya entegrasyon gecikmesini kontrol et.', $legacySerialized['playbook_steps']);

        $component
            ->set('selectedActionIds', [$legacyAction->id])
            ->call('bulkApplyActionRecommendation', 'assign_default_owner');

        $this->assertSame('Finans operasyon', $legacyAction->fresh()->owner_label);
        $this->assertDatabaseHas('mp_profit_action_events', [
            'mp_profit_action_item_id' => $legacyAction->id,
            'event_type' => MpProfitActionEvent::TYPE_PLAN_UPDATED,
        ]);

        $legacyAction->forceFill(['due_date' => now()->subDay()->startOfDay()])->save();

        $component
            ->set('selectedActionIds', [$legacyAction->id])
            ->call('bulkApplyActionRecommendation', 'refresh_due_dates');

        $this->assertTrue($legacyAction->fresh()->due_date->startOfDay()->gte(now()->startOfDay()));

        $component
            ->set('selectedActionIds', [$legacyAction->id])
            ->assertSee('0 kapanışa hazır')
            ->assertSee('1 aksiyonun karar notu eksik')
            ->call('bulkUpdateActions', MpProfitActionItem::STATUS_RESOLVED);

        $this->assertSame(MpProfitActionItem::STATUS_OPEN, $legacyAction->fresh()->status);

        $response = $component->instance()->exportActionReport();
        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertStringContainsString('kar-merkezi-yonetici-raporu', (string) $response->headers->get('content-disposition'));

        $exportPath = $response->getFile()->getPathname();
        $spreadsheet = IOFactory::load($exportPath);

        try {
            $this->assertNotNull($spreadsheet->getSheetByName('Rapor Indeksi'));
            $this->assertNotNull($spreadsheet->getSheetByName('Hesaplama Guveni'));
            $this->assertNotNull($spreadsheet->getSheetByName('Yonetici Ozeti'));
            $this->assertNotNull($spreadsheet->getSheetByName('Komuta Kuyrugu'));
            $this->assertNotNull($spreadsheet->getSheetByName('Aksiyon Dagilimi'));
            $this->assertNotNull($spreadsheet->getSheetByName('Aksiyon Sagligi'));
            $this->assertNotNull($spreadsheet->getSheetByName('Kapanis Kalitesi'));
            $this->assertNotNull($spreadsheet->getSheetByName('Haftalik Trend'));
            $this->assertNotNull($spreadsheet->getSheetByName('Siparis Risk Yogunlugu'));
            $this->assertNotNull($spreadsheet->getSheetByName('Siparis Karar Kuyrugu'));
            $this->assertNotNull($spreadsheet->getSheetByName('Urun Marj Maliyet'));
            $this->assertNotNull($spreadsheet->getSheetByName('Maliyet Eksikleri'));
            $this->assertNotNull($spreadsheet->getSheetByName('Urun Performans'));
            $actionSheet = $spreadsheet->getSheetByName('Aksiyonlar');
            $this->assertNotNull($actionSheet);

            $actionExportText = collect($actionSheet->toArray())
                ->flatten()
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->implode('|');

            $this->assertStringContainsString('Finans ekibi', $actionExportText);
            $this->assertStringContainsString('Yüksek', $actionExportText);
            $this->assertStringContainsString('Mutabakat', $actionExportText);
            $this->assertStringContainsString('Tahmini snapshot ile kesin finans hareketini karşılaştır', $actionExportText);
            $this->assertStringContainsString('Materyal mutabakat farkı sıfıra yaklaştırıldı', $actionExportText);
            $this->assertStringContainsString('İlk sinyal', $actionExportText);
            $this->assertStringContainsString('Güven farkı', $actionExportText);
            $this->assertStringContainsString('Kapanış kalitesi %', $actionExportText);
            $this->assertStringContainsString('Kapanış notu var', $actionExportText);
            $this->assertStringContainsString('Kapanış planı %', $actionExportText);
            $this->assertStringContainsString('Kapanış planı tamamlandı', $actionExportText);
            $this->assertStringContainsString('Plan ilerleme %', $actionExportText);
            $this->assertStringContainsString('Sıradaki adım', $actionExportText);
            $this->assertStringContainsString('Finans operasyon', $actionExportText);
            $this->assertStringContainsString('Hakediş dosyası veya entegrasyon gecikmesini kontrol et', $actionExportText);

            $distributionExportText = collect($spreadsheet->getSheetByName('Aksiyon Dagilimi')->toArray())
                ->flatten()
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->implode('|');

            $this->assertStringContainsString('Durum dağılımı', $distributionExportText);
            $this->assertStringContainsString('Öncelik baskısı', $distributionExportText);
            $this->assertStringContainsString('Hedef yaşlanması', $distributionExportText);

            $actionHealthExportText = collect($spreadsheet->getSheetByName('Aksiyon Sagligi')->toArray())
                ->flatten()
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->implode('|');

            $this->assertStringContainsString('Genel skor', $actionHealthExportText);
            $this->assertStringContainsString('Önerilen hamle', $actionHealthExportText);
            $this->assertStringContainsString('Risk sürücüsü', $actionHealthExportText);
            $this->assertStringContainsString('Aksiyon sağlığı', $actionHealthExportText);
            $this->assertStringContainsString('Yüksek öncelikli işleri incelemeye al', $actionHealthExportText);
            $this->assertStringContainsString('Plan açığı', $actionHealthExportText);

            $commandQueueExportText = collect($spreadsheet->getSheetByName('Komuta Kuyrugu')->toArray())
                ->flatten()
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->implode('|');

            $this->assertStringContainsString('Komuta skoru', $commandQueueExportText);
            $this->assertStringContainsString('Neden bu sırada?', $commandQueueExportText);
            $this->assertStringContainsString('Önerilen ilk adım', $commandQueueExportText);
            $this->assertStringContainsString('Yüksek öncelik', $commandQueueExportText);
            $this->assertStringContainsString('Mutabakat farkını kapat', $commandQueueExportText);
            $this->assertStringContainsString('Plan ilerleme %', $commandQueueExportText);
            $this->assertStringContainsString('Tahmini snapshot ile kesin finans hareketini karşılaştır', $commandQueueExportText);

            $orderRiskExportText = collect($spreadsheet->getSheetByName('Siparis Risk Yogunlugu')->toArray())
                ->flatten()
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->implode('|');
            $productMarginExportText = collect($spreadsheet->getSheetByName('Urun Marj Maliyet')->toArray())
                ->flatten()
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->implode('|');

            $this->assertStringContainsString('Risk bandı', $orderRiskExportText);
            $this->assertStringContainsString('Kök neden', $orderRiskExportText);
            $this->assertStringContainsString('Marj dağılımı', $productMarginExportText);
            $this->assertStringContainsString('Maliyet bileşimi', $productMarginExportText);

            $healthExportText = collect($spreadsheet->getSheetByName('Hesaplama Guveni')->toArray())
                ->flatten()
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->implode('|');

            $this->assertStringContainsString('Güven skoru', $healthExportText);
            $this->assertStringContainsString('Finans kesinliği', $healthExportText);
            $this->assertStringContainsString('KDV modu', $healthExportText);
            $this->assertStringContainsString('Stopaj modu', $healthExportText);

            $costGapExportText = collect($spreadsheet->getSheetByName('Maliyet Eksikleri')->toArray())
                ->flatten()
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->implode('|');

            $this->assertStringContainsString('Aksiyon', $costGapExportText);
            $this->assertStringContainsString('tamamla', $costGapExportText);
            $this->assertStringContainsString('Etkilenen ciro', $costGapExportText);

            $closureQualityExportText = collect($spreadsheet->getSheetByName('Kapanis Kalitesi')->toArray())
                ->flatten()
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->implode('|');

            $this->assertStringContainsString('Planı tamamlanan kapanış', $closureQualityExportText);
            $this->assertStringContainsString('Ortalama plan ilerleme', $closureQualityExportText);

            $indexExportText = collect($spreadsheet->getSheetByName('Rapor Indeksi')->toArray())
                ->flatten()
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->implode('|');

            $this->assertStringContainsString('Kar Merkezi Yönetici Raporu', $indexExportText);
            $this->assertStringContainsString('Rapor hazırlığı', $indexExportText);
            $this->assertStringContainsString('Kontrol sayısı', $indexExportText);
            $this->assertStringContainsString('İndirme öncesi kontrol', $indexExportText);
            $this->assertStringContainsString('Kritik özet', $indexExportText);
            $this->assertStringContainsString('Güven skoru', $indexExportText);
            $this->assertStringContainsString('Açık aksiyon', $indexExportText);
            $this->assertStringContainsString('Risk kuyruğu', $indexExportText);
            $this->assertStringContainsString('Maliyet hazırlığı', $indexExportText);
            $this->assertStringContainsString('Tarih aralığı', $indexExportText);
            $this->assertStringContainsString('Aksiyon sekmesi', $indexExportText);
            $this->assertStringContainsString('Hesaplama Guveni', $indexExportText);
            $this->assertStringContainsString('Komuta Kuyrugu', $indexExportText);
            $this->assertStringContainsString('Aksiyon Sagligi', $indexExportText);
            $this->assertStringContainsString('Siparis Risk Yogunlugu', $indexExportText);
            $this->assertStringContainsString('Maliyet Eksikleri', $indexExportText);
            $this->assertStringContainsString('Urun Performans', $indexExportText);
        } finally {
            $spreadsheet->disconnectWorksheets();
            if (is_file($exportPath)) {
                unlink($exportPath);
            }
        }

        $component
            ->set('selectedActionIds', [$action->id])
            ->call('bulkUpdateActions', MpProfitActionItem::STATUS_IN_PROGRESS);

        $this->assertSame(MpProfitActionItem::STATUS_IN_PROGRESS, $action->fresh()->status);
        $this->assertSame([], $component->instance()->selectedActionIds);
        $this->assertDatabaseHas('mp_profit_action_events', [
            'mp_profit_action_item_id' => $action->id,
            'event_type' => MpProfitActionEvent::TYPE_STATUS_CHANGED,
            'from_status' => MpProfitActionItem::STATUS_OPEN,
            'to_status' => MpProfitActionItem::STATUS_IN_PROGRESS,
        ]);

        $component
            ->set('actionNotes.' . $action->id, 'Mutabakat farkı finans ekranında kontrol edilecek.')
            ->call('saveActionNote', $action->id);

        $this->assertSame('Mutabakat farkı finans ekranında kontrol edilecek.', $action->fresh()->note);
        $this->assertDatabaseHas('mp_profit_action_events', [
            'mp_profit_action_item_id' => $action->id,
            'event_type' => MpProfitActionEvent::TYPE_NOTE_UPDATED,
        ]);

        $component
            ->set('selectedActionIds', [$action->id])
            ->assertSee('1 kapanışa hazır')
            ->assertSee('1 plan tamamlanmadan kapanacak')
            ->set('selectedActionIds', []);

        $component->call('resolveAction', $action->id);
        $this->assertSame(MpProfitActionItem::STATUS_RESOLVED, $action->fresh()->status);
        $this->assertNotNull($action->fresh()->resolved_at);
        $this->assertSame(1, $component->instance()->actionReport['resolved_7d']);
        $this->assertGreaterThan(0, $component->instance()->actionReport['resolved_impact_7d']);
        $this->assertSame(1, $component->instance()->actionReport['closure_quality']['resolved_count']);
        $this->assertSame(100.0, $component->instance()->actionReport['closure_quality']['quality_percent']);
        $this->assertSame('Güçlü', $component->instance()->actionReport['closure_quality']['quality_label']);
        $this->assertSame(1, $component->instance()->actionReport['closure_quality']['plan_scope_count']);
        $this->assertSame(0, $component->instance()->actionReport['closure_quality']['with_plan_complete']);
        $this->assertSame(0.0, $component->instance()->actionReport['closure_quality']['with_plan_complete_percent']);
        $this->assertSame(33.3, $component->instance()->actionReport['closure_quality']['average_plan_percent']);
        $this->assertTrue(collect($component->instance()->actionReport['weekly_trend'])->contains(
            fn (array $week) => (int) $week['created'] >= 1 && (int) $week['resolved'] >= 1
        ));
        $this->assertSame(MpProfitActionEvent::TYPE_STATUS_CHANGED, $component->instance()->actionReport['recent_history'][0]['event_type']);
        $this->assertSame(MpProfitActionItem::STATUS_RESOLVED, $component->instance()->actionReport['recent_history'][0]['status']);
        $this->assertSame('Mutabakat farkı finans ekranında kontrol edilecek.', $component->instance()->actionReport['recent_history'][0]['closure_note']);
        $this->assertSame('Güçlü', $component->instance()->actionReport['recent_history'][0]['closure_quality']['quality_label']);
        $this->assertSame(33.3, (float) $component->instance()->actionReport['recent_history'][0]['closure_quality']['plan_percent']);
        $this->assertFalse($component->instance()->actionReport['recent_history'][0]['closure_quality']['plan_complete']);
        $this->assertTrue(collect($component->instance()->managerReportPreview['readiness']['warnings'])->contains(
            fn (array $warning) => ($warning['label'] ?? '') === 'Plan eksik kapanış'
        ));
        $this->assertSame(1, collect($component->instance()->actionQuickFocusControls())->firstWhere('key', 'plan_gap')['count']);
        $this->assertSame('plan_gap', $component->instance()->actionNextMoveRecommendations()['primary']['key']);

        $component
            ->call('applyActionQuickFocus', 'plan_gap')
            ->assertSet('actionDeskFilter', 'resolved')
            ->assertSet('actionFocusFilter', 'plan_gap')
            ->assertSet('actionSort', 'updated')
            ->assertSee('Plan açığı');

        $this->assertCount(1, $component->instance()->trackedActions);
        $this->assertSame($action->id, $component->instance()->trackedActions[0]['id']);

        $component->call('resetActionListFilters')
            ->assertSet('actionFocusFilter', '');

        $resolvedEvent = MpProfitActionEvent::query()
            ->where('mp_profit_action_item_id', $action->id)
            ->where('event_type', MpProfitActionEvent::TYPE_STATUS_CHANGED)
            ->where('to_status', MpProfitActionItem::STATUS_RESOLVED)
            ->latest('id')
            ->first();

        $this->assertNotNull($resolvedEvent);
        $this->assertSame('Mutabakat farkı finans ekranında kontrol edilecek.', data_get($resolvedEvent->meta_json, 'closure_evidence.note'));
        $this->assertTrue(data_get($resolvedEvent->meta_json, 'closure_evidence.has_note'));
        $this->assertSame(100.0, (float) data_get($resolvedEvent->meta_json, 'closure_evidence.quality_percent'));
        $this->assertSame(33.3, (float) data_get($resolvedEvent->meta_json, 'closure_evidence.plan_percent'));
        $this->assertFalse((bool) data_get($resolvedEvent->meta_json, 'closure_evidence.plan_complete'));

        $component->call('openActionTimeline', $action->id)
            ->assertSet('timelineActionId', $action->id)
            ->assertSee('Aksiyon geçmişi')
            ->assertSee('Durum değişti')
            ->assertSee('Kapanış notu')
            ->assertSee('Kapanış kanıtı kaydedildi')
            ->assertSee('Güçlü')
            ->assertSee('Plan eksik');

        $timeline = $component->instance()->actionTimeline;
        $this->assertNotNull($timeline);
        $this->assertGreaterThanOrEqual(5, $timeline['event_count']);
        $lastTimelineEvent = collect($timeline['events'])->last();
        $this->assertSame(MpProfitActionItem::STATUS_RESOLVED, $lastTimelineEvent['status']);
        $this->assertSame('Mutabakat farkı finans ekranında kontrol edilecek.', $lastTimelineEvent['closure_note']);
        $this->assertSame('Güçlü', $lastTimelineEvent['closure_quality']['quality_label']);
        $this->assertSame(33.3, (float) $lastTimelineEvent['closure_quality']['plan_percent']);

        $component->call('closeActionTimeline')
            ->assertSet('timelineActionId', null);

        $component->call('setActionDeskFilter', 'resolved')
            ->assertSet('actionDeskFilter', 'resolved')
            ->assertSee('Yeniden aç')
            ->assertSee('Kapanış özeti')
            ->assertSee('Plan eksik')
            ->assertSee('Mutabakat farkı finans ekranında kontrol edilecek.');

        $component
            ->set('selectedActionIds', [$action->id])
            ->call('bulkApplyActionRecommendation', 'reopen_plan_gaps');
        $this->assertSame(MpProfitActionItem::STATUS_OPEN, $action->fresh()->status);
        $this->assertNull($action->fresh()->resolved_at);
        $this->assertDatabaseHas('mp_profit_action_events', [
            'mp_profit_action_item_id' => $action->id,
            'event_type' => MpProfitActionEvent::TYPE_STATUS_CHANGED,
            'from_status' => MpProfitActionItem::STATUS_RESOLVED,
            'to_status' => MpProfitActionItem::STATUS_OPEN,
        ]);

        $component->call('setPanel', 'orders')
            ->assertSet('activePanel', 'orders')
            ->assertSee('Sipariş risk hunisi')
            ->assertSee('Öncelik rehberi')
            ->assertSee('Risk yoğunluğu')
            ->assertSee('Baskın bant')
            ->assertSee('Karar kuyruğu')
            ->assertSee('Karar akış planı')
            ->assertSee('4 kontrol adımı')
            ->assertSee('Hakedişi kesinleştir')
            ->assertSee('Negatif kârı doğrula')
            ->assertSee('Ürün bağını tamamla')
            ->assertSee('Sipariş karar kuyruğu')
            ->assertSee('Maliyet ve iade etkisini kontrol et')
            ->assertSee('Hızlı aksiyonlar')
            ->assertSee('Finans bekleyenleri aç')
            ->assertSee('Mutabakat farklarını incele');

        $this->assertCount(3, $component->instance()->orderDecisionInsights['risk_lane_rows']);
        $this->assertArrayHasKey('risk_exposure', $component->instance()->orderDecisionInsights);
        $this->assertNotEmpty($component->instance()->orderDecisionInsights['dominant_risk_lane']);

        $component->call('setPanel', 'products')
            ->assertSet('activePanel', 'products')
            ->assertSee('Ürün kârlılık matrisi')
            ->assertSee('Ürün karar özeti')
            ->assertSee('Marj dağılımı')
            ->assertSee('Maliyet bileşimi')
            ->assertSee('Riskli ürün')
            ->assertSee('Ciro lideri')
            ->assertSee('Kayıp odağı')
            ->assertSee('Hazırlık skoru')
            ->assertSee('Ürün performans listesi')
            ->assertSee('Maliyet eksiklerini aç')
            ->assertSee('Eşleşme merkezini aç')
            ->assertSee('Ürünü düzenle')
            ->assertSee('Maliyet tamamla');

        $this->assertCount(3, $component->instance()->productReadinessInsights['margin_rows']);
        $this->assertCount(3, $component->instance()->productReadinessInsights['cost_composition_rows']);
        $this->assertArrayHasKey('top_revenue_product', $component->instance()->productReadinessInsights);
        $this->assertArrayHasKey('top_loss_product', $component->instance()->productReadinessInsights);

        $component->set('storeFilter', (string) $waitingOrder->store_id)
            ->assertDontSee($lossOrder->order_number);

        $this->assertSame(1, $component->instance()->summary['total_orders']);
        $this->assertSame(1, $component->instance()->summary['finance_waiting_order_count']);
    }

    public function test_profit_center_route_is_feature_flag_protected(): void
    {
        [$user] = $this->createProfitCenterGraph();

        $this->actingAs($user);

        config()->set('marketplace.features.profit_center_enabled', false);
        $this->get('/marketplace-profit-center')->assertNotFound();

        config()->set('marketplace.features.profit_center_enabled', true);
        $this->get('/marketplace-profit-center')
            ->assertOk()
            ->assertSee('Kâr Kokpiti');
    }

    public function test_profit_center_can_track_calculation_health_gap(): void
    {
        [$user] = $this->createProfitCenterGraph();

        $this->actingAs($user);

        Livewire::test(MarketplaceProfitCenter::class)
            ->assertSee('Hesaplama güveni')
            ->call('trackCalculationGap', 'finance_waiting')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('mp_profit_action_items', [
            'user_id' => $user->id,
            'action_key' => 'finance_waiting',
            'status' => MpProfitActionItem::STATUS_OPEN,
        ]);

        $action = MpProfitActionItem::query()
            ->where('user_id', $user->id)
            ->where('action_key', 'finance_waiting')
            ->firstOrFail();

        $this->assertSame(1, (int) data_get($action->recommendation_json, 'baseline_signal.value'));
        $this->assertNotNull(data_get($action->recommendation_json, 'calculation_health_at_tracking.score'));
    }

    public function test_profit_center_defaults_to_latest_available_order_range(): void
    {
        $latestDate = now()->subDays(45);
        [$user] = $this->createProfitCenterGraph($latestDate->toDateTimeString());

        $this->actingAs($user);

        $component = Livewire::test(MarketplaceProfitCenter::class)
            ->assertSet('dateTo', $latestDate->toDateString())
            ->assertSet('dateFrom', $latestDate->copy()->subDays(30)->toDateString());

        $this->assertSame(3, $component->instance()->summary['total_orders']);
    }

    /**
     * @return array{0: User, 1: ChannelOrder, 2: ChannelOrder, 3: ChannelOrder}
     */
    protected function createProfitCenterGraph(?string $orderedAt = null): array
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'ZOLM Kar Merkezi Ltd.',
            'tax_number' => '7' . $suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $trendyolStore = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'Kar Merkezi Trendyol',
            'store_code' => 'KAR-TY-' . $suffix,
            'seller_id' => 'KAR-TY-' . $suffix,
            'status' => 'active',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $hepsiburadaStore = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'hepsiburada',
            'store_name' => 'Kar Merkezi HB',
            'store_code' => 'KAR-HB-' . $suffix,
            'seller_id' => 'KAR-HB-' . $suffix,
            'status' => 'active',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $readyProduct = MpProduct::query()->create([
            'user_id' => $user->id,
            'barcode' => 'READY-' . $suffix,
            'stock_code' => 'READY-' . $suffix,
            'product_name' => 'Hazır Maliyet Ürünü',
            'cogs' => 500,
            'packaging_cost' => 20,
            'commission_rate' => 10,
            'status' => 'active',
        ]);

        $missingCostProduct = MpProduct::query()->create([
            'user_id' => $user->id,
            'barcode' => 'MISS-' . $suffix,
            'stock_code' => 'MISS-' . $suffix,
            'product_name' => 'Eksik Maliyet Ürünü',
            'cogs' => 0,
            'packaging_cost' => 0,
            'commission_rate' => 10,
            'status' => 'active',
        ]);

        $lossOrder = $this->createOrder(
            $trendyolStore,
            $legalEntity,
            'KAR-LOSS-' . $suffix,
            1000,
            10,
            $readyProduct->id,
            [
                'profit_state' => 'confirmed',
                'gross_revenue' => 1000,
                'net_receivable' => 820,
                'commission_total' => 100,
                'cargo_total' => 50,
                'service_fee_total' => 20,
                'withholding_total' => 10,
                'cogs_cost' => 700,
                'packaging_cost' => 20,
                'estimated_profit' => 100,
                'confirmed_profit' => -50,
                'margin_percent' => 0.93,
            ],
            [
                ['event_type' => 'seller_revenue', 'direction' => 'credit', 'amount' => 1000],
                ['event_type' => 'commission', 'direction' => 'debit', 'amount' => 100],
                ['event_type' => 'cargo', 'direction' => 'debit', 'amount' => 50],
                ['event_type' => 'service_fee', 'direction' => 'debit', 'amount' => 20],
                ['event_type' => 'withholding', 'direction' => 'debit', 'amount' => 10],
            ],
            $orderedAt
        );

        $waitingOrder = $this->createOrder(
            $hepsiburadaStore,
            $legalEntity,
            'KAR-WAIT-' . $suffix,
            400,
            12,
            $missingCostProduct->id,
            [
                'profit_state' => 'estimated',
                'gross_revenue' => 400,
                'net_receivable' => 350,
                'commission_total' => 48,
                'cargo_total' => 0,
                'service_fee_total' => 2,
                'withholding_total' => 0,
                'cogs_cost' => 200,
                'packaging_cost' => 30,
                'estimated_profit' => 120,
                'confirmed_profit' => 0,
                'margin_percent' => 1.52,
            ],
            [],
            $orderedAt
        );

        $snapshotMissingOrder = $this->createOrder(
            $trendyolStore,
            $legalEntity,
            'KAR-SNAPSHOT-' . $suffix,
            200,
            10,
            null,
            null,
            [
                ['event_type' => 'seller_revenue', 'direction' => 'credit', 'amount' => 200],
                ['event_type' => 'commission', 'direction' => 'debit', 'amount' => 20],
            ],
            $orderedAt
        );

        return [$user, $lossOrder, $waitingOrder, $snapshotMissingOrder];
    }

    protected function createOrder(
        MarketplaceStore $store,
        LegalEntity $legalEntity,
        string $orderNumber,
        float $grossAmount,
        float $commissionRate,
        ?int $productId,
        ?array $snapshot,
        array $events,
        ?string $orderedAt = null
    ): ChannelOrder {
        $order = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $legalEntity->id,
            'external_order_id' => $orderNumber,
            'order_number' => $orderNumber,
            'order_status' => 'Delivered',
            'customer_name' => 'Kar Merkezi Test',
            'ordered_at' => $orderedAt ?? now(),
        ]);

        ChannelOrderItem::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'mp_product_id' => $productId,
            'external_line_id' => $orderNumber . '-LINE',
            'stock_code' => 'SKU-' . $orderNumber,
            'barcode' => 'BAR-' . $orderNumber,
            'product_name' => 'Kar Merkezi Ürünü',
            'quantity' => 1,
            'unit_price' => $grossAmount,
            'gross_amount' => $grossAmount,
            'billable_amount' => $grossAmount,
            'commission_rate' => $commissionRate,
            'line_status' => 'Delivered',
            'is_matched' => $productId !== null,
            'match_source' => $productId !== null ? 'manual' : null,
        ]);

        if ($snapshot !== null) {
            OrderProfitSnapshot::query()->create(array_merge([
                'store_id' => $store->id,
                'channel_order_id' => $order->id,
                'channel_order_item_id' => null,
                'own_cargo_cost' => 0,
                'return_effect' => 0,
                'vat_effect' => 0,
                'calculated_at' => now(),
                'version' => 1,
            ], $snapshot));
        }

        foreach ($events as $index => $event) {
            OrderFinancialEvent::query()->create([
                'store_id' => $store->id,
                'legal_entity_id' => $legalEntity->id,
                'channel_order_id' => $order->id,
                'event_source' => 'sync',
                'event_type' => $event['event_type'],
                'external_event_id' => $orderNumber . '-EVT-' . $index,
                'reference_number' => $orderNumber . '-REF-' . $index,
                'event_date' => now(),
                'settlement_date' => now(),
                'amount' => $event['amount'],
                'currency' => 'TRY',
                'direction' => $event['direction'],
                'status' => $event['status'] ?? 'settled',
            ]);
        }

        return $order;
    }
}
