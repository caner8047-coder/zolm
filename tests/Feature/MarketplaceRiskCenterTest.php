<?php

namespace Tests\Feature;

use App\Livewire\MarketplaceRiskCenter;
use App\Models\AppNotification;
use App\Models\MarketplaceRiskSignalState;
use App\Models\User;
use App\Services\CampaignDecisionCenterQueryService;
use App\Services\Marketplace\MarketplaceDiagnosticsGuidanceService;
use App\Services\Marketplace\MarketplaceProfitCenterQueryService;
use App\Services\Marketplace\MarketplaceRiskSignalService;
use App\Services\Marketplace\MarketplaceSettlementAuditQueryService;
use App\Services\NotificationCenterService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Mockery;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class MarketplaceRiskCenterTest extends TestCase
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
        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::setDefaultConnection('mysql');

        config()->set('marketplace.features.risk_center_enabled', true);
        config()->set('marketplace.features.notifications_enabled', true);
    }

    public function test_service_sorts_signals_and_prevents_duplicate_state_rows(): void
    {
        $user = $this->createUser();
        $service = $this->bindRiskService([
            $this->recommendation('loss_orders', 'Zarar baskısını azalt', 3, 450, 'mp.finance'),
            $this->recommendation('missing_cost', 'Maliyet hazırlığını tamamla', 12, 25000, 'mp.products'),
        ]);

        $first = $service->dashboard($user->id);
        $second = $service->dashboard($user->id);

        $this->assertSame(2, $first['summary']['total_count']);
        $this->assertSame('critical', $first['queue'][0]['severity']);
        $this->assertSame('Zarar baskısını azalt', $first['queue'][0]['title']);
        $this->assertSame(450.0, $first['summary']['impact_total']);
        $this->assertSame(2, $second['summary']['total_count']);
        $fingerprints = MarketplaceRiskSignalState::query()
            ->where('user_id', $user->id)
            ->pluck('fingerprint');

        $this->assertCount(2, $fingerprints);
        $this->assertCount(2, $fingerprints->unique());
    }

    public function test_risk_can_be_resolved_snoozed_and_reopened_from_livewire(): void
    {
        $user = $this->createUser();
        $service = $this->bindRiskService([
            $this->recommendation('loss_orders', 'Zarar baskısını azalt', 2, 300, 'mp.finance'),
        ]);
        $fingerprint = $service->dashboard($user->id)['queue'][0]['fingerprint'];
        $this->actingAs($user);

        Livewire::test(MarketplaceRiskCenter::class)
            ->assertSee('Bugün ilk neyi düzeltmeliyim?')
            ->assertSee('Zarar baskısını azalt')
            ->call('snoozeRisk', $fingerprint, 3)
            ->assertHasNoErrors();

        $state = MarketplaceRiskSignalState::query()
            ->where('user_id', $user->id)
            ->where('fingerprint', $fingerprint)
            ->firstOrFail();

        $this->assertSame('snoozed', $state->status);
        $this->assertNotNull($state->snoozed_until);

        $service->reopen($user->id, $fingerprint);
        $this->assertSame('open', $state->refresh()->status);

        Livewire::test(MarketplaceRiskCenter::class)
            ->call('resolveRisk', $fingerprint)
            ->assertHasNoErrors();

        $this->assertSame('resolved', $state->refresh()->status);
        $this->assertNotNull($state->resolved_at);

        $this->bindRiskService([])->dashboard($user->id);
        $this->assertFalse($state->refresh()->is_current);

        $service->dashboard($user->id);
        $this->assertSame('open', $state->refresh()->status);
        $this->assertTrue($state->is_current);
    }

    public function test_risk_notifications_are_daily_deduplicated_and_respect_preferences(): void
    {
        $user = $this->createUser();
        $service = $this->bindRiskService([
            $this->recommendation('loss_orders', 'Kritik zarar sinyali', 5, 900, 'mp.finance'),
        ]);

        $first = $service->syncForUser($user->id);
        $second = $service->syncForUser($user->id);

        $this->assertSame(1, $first['notifications']);
        $this->assertSame(0, $second['notifications']);
        $this->assertSame(1, AppNotification::query()
            ->where('user_id', $user->id)
            ->where('type', 'risk_critical')
            ->count());
        $notification = AppNotification::query()
            ->where('user_id', $user->id)
            ->where('type', 'risk_critical')
            ->firstOrFail();
        $this->assertStringStartsWith('risk-summary:critical:', (string) $notification->event_key);
        $this->assertTrue((bool) ($notification->data_json['summary'] ?? false));

        $mutedUser = $this->createUser();
        app(NotificationCenterService::class)->setMutedTypes($mutedUser->id, ['risk_critical']);

        $mutedResult = $service->syncForUser($mutedUser->id);

        $this->assertSame(0, $mutedResult['notifications']);
        $this->assertDatabaseMissing('app_notifications', [
            'user_id' => $mutedUser->id,
            'type' => 'risk_critical',
        ]);
    }

    public function test_risk_notifications_are_summarized_and_old_granular_items_are_pruned(): void
    {
        $user = $this->createUser();
        AppNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'risk_critical',
            'severity' => 'critical',
            'event_key' => 'risk-signal:legacy-fingerprint:'.now()->format('Ymd'),
            'title' => 'Eski tekil risk bildirimi',
            'body' => 'Bu bildirim özet modele geçerken temizlenmeli.',
            'action_url' => route('mp.risk-center'),
        ]);
        $service = $this->bindRiskService([
            $this->recommendation('loss_orders', 'Zarar baskısını azalt', 3, 450, 'mp.finance'),
            $this->recommendation('material_variance', 'Mutabakat farkını kontrol et', 2, 800, 'mp.finance'),
            $this->recommendation('missing_cost', 'Maliyet hazırlığını tamamla', 12, 25000, 'mp.products'),
        ]);

        $result = $service->syncForUser($user->id);

        $this->assertSame(2, $result['notifications']);
        $this->assertDatabaseMissing('app_notifications', [
            'user_id' => $user->id,
            'event_key' => 'risk-signal:legacy-fingerprint:'.now()->format('Ymd'),
        ]);

        $critical = AppNotification::query()
            ->where('user_id', $user->id)
            ->where('event_key', 'like', 'risk-summary:critical:%')
            ->firstOrFail();
        $warning = AppNotification::query()
            ->where('user_id', $user->id)
            ->where('event_key', 'like', 'risk-summary:warning:%')
            ->firstOrFail();

        $this->assertSame('2 kritik risk açık', $critical->title);
        $this->assertSame(2, $critical->data_json['signal_count']);
        $this->assertStringContainsString('Zarar baskısını azalt', $critical->body);
        $this->assertSame('Maliyet hazırlığını tamamla', $warning->title);
        $this->assertSame(1, $warning->data_json['signal_count']);
    }

    public function test_screen_filters_exports_and_common_guidance_are_available(): void
    {
        $user = $this->createUser();
        $service = $this->bindRiskService([
            $this->recommendation('loss_orders', 'Zarar baskısını azalt', 4, 750, 'mp.finance'),
            $this->recommendation('missing_cost', 'Maliyet hazırlığını tamamla', 8, 12000, 'mp.products'),
        ]);
        $this->actingAs($user);

        $component = Livewire::test(MarketplaceRiskCenter::class)
            ->assertSee('Kategori baskısı')
            ->assertSee('Risk defteri')
            ->assertSee('Risk bildirim tercihleri')
            ->assertSee('Zarar baskısını azalt')
            ->assertSee('Maliyet hazırlığını tamamla')
            ->set('categoryFilter', 'product')
            ->assertSee('Maliyet hazırlığını tamamla')
            ->call('toggleColumn', 'source')
            ->assertHasNoErrors();

        $filteredTitles = collect($component->instance()->dashboard['queue'])->pluck('title');
        $this->assertTrue($filteredTitles->contains('Maliyet hazırlığını tamamla'));
        $this->assertFalse($filteredTitles->contains('Zarar baskısını azalt'));

        $guidance = $service->guidanceForContext($user->id, 'products');
        $this->assertTrue($guidance['has_risk']);
        $this->assertSame('Maliyet hazırlığını tamamla', $guidance['primary']['title']);

        $response = $component->instance()->exportRiskReport();
        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertStringContainsString('zolm-risk-merkezi', (string) $response->headers->get('content-disposition'));

        $spreadsheet = IOFactory::load($response->getFile()->getPathname());

        try {
            $this->assertNotNull($spreadsheet->getSheetByName('Risk Ozeti'));
            $this->assertNotNull($spreadsheet->getSheetByName('Kategori Baskisi'));
            $this->assertNotNull($spreadsheet->getSheetByName('Risk Kuyrugu'));
            $this->assertNotNull($spreadsheet->getSheetByName('Bildirim Tercihleri'));
        } finally {
            $spreadsheet->disconnectWorksheets();
            @unlink($response->getFile()->getPathname());
        }
    }

    public function test_route_is_protected_by_feature_flag(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        config()->set('marketplace.features.risk_center_enabled', false);
        $this->get('/marketplace-risk-center')->assertNotFound();

        config()->set('marketplace.features.risk_center_enabled', true);
        $this->get('/marketplace-risk-center')->assertOk();
    }

    protected function bindRiskService(array $recommendations): MarketplaceRiskSignalService
    {
        $profitCenter = Mockery::mock(MarketplaceProfitCenterQueryService::class);
        $profitCenter->shouldReceive('priorityRecommendations')
            ->zeroOrMoreTimes()
            ->andReturn($recommendations);

        $settlement = Mockery::mock(MarketplaceSettlementAuditQueryService::class);
        $settlement->shouldReceive('audit')
            ->zeroOrMoreTimes()
            ->andReturn(['risk_breakdown' => []]);

        $diagnostics = Mockery::mock(MarketplaceDiagnosticsGuidanceService::class);
        $diagnostics->shouldReceive('guidanceForUser')
            ->zeroOrMoreTimes()
            ->andReturn(['totals' => ['items' => 0, 'critical' => 0, 'warning' => 0, 'info' => 0], 'items' => []]);

        $campaigns = Mockery::mock(CampaignDecisionCenterQueryService::class);
        $campaigns->shouldReceive('dashboard')
            ->zeroOrMoreTimes()
            ->andReturn(['modules' => []]);

        $service = new MarketplaceRiskSignalService(
            $profitCenter,
            $settlement,
            $diagnostics,
            $campaigns,
            app(NotificationCenterService::class),
        );

        $this->app->instance(MarketplaceRiskSignalService::class, $service);

        return $service;
    }

    protected function recommendation(
        string $key,
        string $label,
        int $value,
        float $impact,
        string $route
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'impact' => $impact,
            'tone' => $key === 'loss_orders' ? 'danger' : 'warning',
            'description' => $label.' açıklaması.',
            'action_label' => 'Detayı aç',
            'route' => $route,
            'query' => [],
            'score' => $impact,
        ];
    }

    protected function createUser(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
    }
}
