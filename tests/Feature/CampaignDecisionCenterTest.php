<?php

namespace Tests\Feature;

use App\Livewire\CampaignDecisionCenter;
use App\Models\OptimizationReport;
use App\Models\OptimizationReportItem;
use App\Models\User;
use App\Services\CampaignDecisionCenterQueryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class CampaignDecisionCenterTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.host', 'mysql');
        config()->set('database.connections.mysql.port', '3306');
        config()->set('database.connections.mysql.database', $this->mysqlTestDatabaseName());
        config()->set('database.connections.mysql.username', 'sail');
        config()->set('database.connections.mysql.password', 'password');
        config()->set('marketplace.features.campaign_decision_center_enabled', true);
        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::setDefaultConnection('mysql');
    }

    public function test_dashboard_uses_latest_report_per_type_and_builds_safe_decisions(): void
    {
        [$user, $latestTariff, $basket] = $this->createDecisionGraph();

        $dashboard = app(CampaignDecisionCenterQueryService::class)->dashboard($user->id);
        $summary = $dashboard['summary'];
        $queue = collect($dashboard['queue'])->keyBy('stock_code');

        $this->assertSame(2, $summary['report_count']);
        $this->assertSame(4, $summary['product_count']);
        $this->assertSame(1, $summary['approve_count']);
        $this->assertSame(2, $summary['risk_count']);
        $this->assertSame(1, $summary['keep_count']);
        $this->assertSame(100.0, $summary['potential_profit']);
        $this->assertSame(90.0, $summary['risk_exposure']);
        $this->assertSame(60.0, $summary['raw_impact']);
        $this->assertEqualsCanonicalizing([$latestTariff->id, $basket->id], $dashboard['latest_report_ids']);

        $this->assertSame('approve', $queue['APPROVE-1']['decision']);
        $this->assertSame('risk', $queue['MISSING-COST-1']['decision']);
        $this->assertContains('Maliyet verisi eksik', $queue['MISSING-COST-1']['risk_reasons']);
        $this->assertSame('keep', $queue['KEEP-1']['decision']);
        $this->assertSame('risk', $queue['BASKET-LOSS-1']['decision']);
        $this->assertContains('Kampanya mevcut kârı azaltıyor', $queue['BASKET-LOSS-1']['risk_reasons']);
    }

    public function test_dashboard_filters_by_campaign_type_decision_and_search(): void
    {
        [$user] = $this->createDecisionGraph();
        $service = app(CampaignDecisionCenterQueryService::class);

        $basket = $service->dashboard($user->id, ['campaign_type' => 'basket_discount']);
        $this->assertSame(1, $basket['summary']['report_count']);
        $this->assertSame(1, $basket['queue_total']);
        $this->assertSame('BASKET-LOSS-1', $basket['queue'][0]['stock_code']);

        $approve = $service->dashboard($user->id, ['decision' => 'approve']);
        $this->assertSame(1, $approve['queue_total']);
        $this->assertSame('APPROVE-1', $approve['queue'][0]['stock_code']);

        $search = $service->dashboard($user->id, ['search' => 'maliyet eksik']);
        $this->assertSame(1, $search['queue_total']);
        $this->assertSame('MISSING-COST-1', $search['queue'][0]['stock_code']);
    }

    public function test_livewire_screen_renders_filters_deep_links_and_excel_export(): void
    {
        [$user, $tariff] = $this->createDecisionGraph();
        $this->actingAs($user);

        $component = Livewire::test(CampaignDecisionCenter::class)
            ->assertSee('Kampanya Karar Merkezi')
            ->assertSee('Kampanya modülleri')
            ->assertSee('Rapor etki trendi')
            ->assertSee('Kampanya karar kuyruğu')
            ->assertSee('APPROVE-1')
            ->assertSee('BASKET-LOSS-1')
            ->set('decisionFilter', 'approve')
            ->assertSee('APPROVE-1')
            ->assertDontSee('BASKET-LOSS-1')
            ->call('toggleColumn', 'cost')
            ->assertHasNoErrors();

        $this->assertStringContainsString(
            '/campaigns/product-commission?report='.$tariff->id,
            $component->instance()->moduleUrl(
                collect($component->instance()->dashboard['modules'])->firstWhere('campaign_type', 'tariff')
            )
        );

        $response = $component->instance()->exportDecisionReport();
        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertStringContainsString('kampanya-karar-merkezi', (string) $response->headers->get('content-disposition'));

        $spreadsheet = IOFactory::load($response->getFile()->getPathname());

        try {
            $this->assertNotNull($spreadsheet->getSheetByName('Karar Ozeti'));
            $this->assertNotNull($spreadsheet->getSheetByName('Modul Karsilastirma'));
            $this->assertNotNull($spreadsheet->getSheetByName('Karar Kuyrugu'));
            $this->assertNotNull($spreadsheet->getSheetByName('Son Raporlar'));

            $queueText = collect($spreadsheet->getSheetByName('Karar Kuyrugu')->toArray())
                ->flatten()
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->implode('|');

            $this->assertStringContainsString('APPROVE-1', $queueText);
            $this->assertStringContainsString('Onaylanabilir', $queueText);
        } finally {
            $spreadsheet->disconnectWorksheets();
            @unlink($response->getFile()->getPathname());
        }
    }

    public function test_route_is_protected_by_feature_flag(): void
    {
        [$user] = $this->createDecisionGraph();
        $this->actingAs($user);

        config()->set('marketplace.features.campaign_decision_center_enabled', false);
        $this->get('/campaigns/decision-center')->assertNotFound();

        config()->set('marketplace.features.campaign_decision_center_enabled', true);
        $this->get('/campaigns/decision-center')
            ->assertOk()
            ->assertSee('Kampanya Karar Merkezi');
    }

    /**
     * @return array{0: User, 1: OptimizationReport, 2: OptimizationReport}
     */
    protected function createDecisionGraph(): array
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $oldTariff = $this->createReport($user, 'tariff', 'Eski Tarife Raporu', [
            [
                'stock_code' => 'OLD-1',
                'current_net_profit' => 100,
                'suggested_net_profit' => 10100,
                'extra_profit' => 10000,
                'production_cost' => 100,
                'action' => 'update',
            ],
        ]);
        $oldTariff->forceFill(['created_at' => now()->subDays(10)])->save();

        $latestTariff = $this->createReport($user, 'tariff', 'Güncel Tarife Raporu', [
            [
                'stock_code' => 'APPROVE-1',
                'product_name' => 'Güvenli Fırsat Ürünü',
                'current_net_profit' => 200,
                'suggested_net_profit' => 300,
                'extra_profit' => 100,
                'production_cost' => 500,
                'action' => 'update',
                'is_selected' => true,
            ],
            [
                'stock_code' => 'MISSING-COST-1',
                'product_name' => 'Maliyet Eksik Ürün',
                'current_net_profit' => 100,
                'suggested_net_profit' => 150,
                'extra_profit' => 50,
                'production_cost' => 0,
                'shipping_cost' => 0,
                'action' => 'update',
            ],
            [
                'stock_code' => 'KEEP-1',
                'product_name' => 'Mevcut Durumu Koru',
                'current_net_profit' => 180,
                'suggested_net_profit' => 160,
                'extra_profit' => -20,
                'production_cost' => 450,
                'action' => 'keep',
            ],
        ]);

        $basket = $this->createReport($user, 'basket_discount', 'Sepet İndirimi Raporu', [
            [
                'stock_code' => 'BASKET-LOSS-1',
                'product_name' => 'Sepet Kampanyası Riskli Ürün',
                'current_net_profit' => 400,
                'suggested_net_profit' => 310,
                'extra_profit' => -90,
                'production_cost' => 600,
                'action' => 'update',
            ],
        ]);

        return [$user, $latestTariff, $basket];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function createReport(User $user, string $type, string $name, array $items): OptimizationReport
    {
        $report = OptimizationReport::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'campaign_type' => $type,
            'total_products' => count($items),
            'opportunity_count' => collect($items)->where('action', 'update')->count(),
            'total_current_profit' => collect($items)->sum('current_net_profit'),
            'total_optimized_profit' => collect($items)->sum('suggested_net_profit'),
            'total_extra_profit' => collect($items)->sum('extra_profit'),
            'unmatched_count' => 0,
            'status' => 'completed',
        ]);

        foreach ($items as $index => $item) {
            OptimizationReportItem::query()->create(array_merge([
                'report_id' => $report->id,
                'stock_code' => 'ITEM-'.$index,
                'barcode' => 'BARCODE-'.$report->id.'-'.$index,
                'product_name' => 'Kampanya Test Ürünü',
                'current_price' => 1000,
                'current_commission' => 15,
                'current_net_profit' => 100,
                'suggested_tariff' => 'Öneri',
                'suggested_price' => 950,
                'suggested_commission' => 10,
                'suggested_net_profit' => 120,
                'extra_profit' => 20,
                'production_cost' => 500,
                'shipping_cost' => 50,
                'action' => 'keep',
                'is_selected' => false,
                'scenario_details' => [],
                'campaign_data' => ['matched' => true],
            ], $item));
        }

        return $report;
    }
}
