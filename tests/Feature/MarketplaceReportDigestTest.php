<?php

namespace Tests\Feature;

use App\Livewire\MarketplaceReportDigestSettings;
use App\Mail\MarketplaceReportDigestMail;
use App\Models\LegalEntity;
use App\Models\MarketplaceReportDigestRun;
use App\Models\MarketplaceReportSubscription;
use App\Models\MarketplaceStore;
use App\Models\Report;
use App\Models\User;
use App\Services\CampaignDecisionCenterQueryService;
use App\Services\Marketplace\MarketplaceProfitCenterQueryService;
use App\Services\Marketplace\MarketplaceReportDigestService;
use App\Services\Marketplace\MarketplaceRiskSignalService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class MarketplaceReportDigestTest extends TestCase
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
        config()->set('marketplace.features.report_digest_enabled', true);
        config()->set('marketplace.features.profit_center_enabled', true);
        config()->set('marketplace.features.risk_center_enabled', true);
        config()->set('marketplace.features.campaign_decision_center_enabled', true);
        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::setDefaultConnection('mysql');

        // Eski verileri temizle
        MarketplaceReportSubscription::query()->delete();
        Report::query()->delete();
        MarketplaceReportDigestRun::query()->delete();
    }

    public function test_send_due_delivers_mail_and_creates_report_history(): void
    {
        Mail::fake();

        $user = $this->createUser();
        $service = $this->bindDigestService();
        $subscription = $this->createSubscription($user, [
            'recipients_json' => ['yonetim@example.com'],
            'next_run_at' => now()->subMinute(),
        ]);

        $result = $service->sendDue(now());

        $this->assertSame(1, $result['processed']);
        $this->assertSame(1, $result['sent']);
        $this->assertSame(0, $result['failed']);

        Mail::assertSent(MarketplaceReportDigestMail::class, function (MarketplaceReportDigestMail $mail) {
            return $mail->payload['summary']['gross_revenue'] === 1000.0
                && str_contains($mail->payload['subject'], 'ZOLM Günlük Kâr Özeti');
        });

        $this->assertDatabaseHas('marketplace_report_digest_runs', [
            'marketplace_report_subscription_id' => $subscription->id,
            'recipient_email' => 'yonetim@example.com',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('reports', [
            'user_id' => $user->id,
            'status' => 'success',
        ]);

        $run = MarketplaceReportDigestRun::query()->firstOrFail();
        $this->assertNotNull($run->report_id);
        $this->assertSame('success', Report::query()->findOrFail($run->report_id)->status);
        $this->assertTrue($subscription->refresh()->next_run_at->greaterThan(now()));
    }

    public function test_weekly_next_run_uses_same_monday_when_send_time_is_future(): void
    {
        $service = $this->bindDigestService();
        $mondayMorning = Carbon::parse('2026-06-15 07:00:00', 'Europe/Istanbul');

        $next = $service->nextRunAt(
            MarketplaceReportSubscription::FREQUENCY_WEEKLY,
            '08:30',
            'Europe/Istanbul',
            $mondayMorning,
        );

        $this->assertSame('2026-06-15 08:30:00', $next->copy()->timezone('Europe/Istanbul')->format('Y-m-d H:i:s'));

        $afterSendTime = Carbon::parse('2026-06-15 09:00:00', 'Europe/Istanbul');
        $following = $service->nextRunAt(
            MarketplaceReportSubscription::FREQUENCY_WEEKLY,
            '08:30',
            'Europe/Istanbul',
            $afterSendTime,
        );

        $this->assertSame('2026-06-22 08:30:00', $following->copy()->timezone('Europe/Istanbul')->format('Y-m-d H:i:s'));
    }

    public function test_livewire_screen_saves_subscription_and_can_send_now(): void
    {
        Mail::fake();

        $user = $this->createUser();
        $suffix = (string) Str::uuid();
        $entity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'ZOLM Test Ltd.',
            'tax_number' => (string) random_int(1000000000, 9999999999),
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'store_name' => 'ZEM HOME',
            'marketplace' => 'trendyol',
            'store_code' => 'ZEM-' . $suffix,
            'seller_id' => 'TY-' . $suffix,
            'status' => 'active',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
        $this->bindDigestService();
        $this->actingAs($user);

        Livewire::test(MarketplaceReportDigestSettings::class)
            ->assertSee('Günlük ve haftalık kâr özetleri')
            ->set('name', 'Yönetim Sabah Özeti')
            ->set('frequency', MarketplaceReportSubscription::FREQUENCY_WEEKLY)
            ->set('storeId', (string) $store->id)
            ->set('sendTime', '09:15')
            ->set('recipientsText', "yonetim@example.com\nfinans@example.com")
            ->set('selectedSections', ['profit', 'risk', 'actions'])
            ->call('save')
            ->assertHasNoErrors()
            ->call('sendNow')
            ->assertHasNoErrors()
            ->assertSee('Manuel gönderim tamamlandı');

        $this->assertDatabaseHas('marketplace_report_subscriptions', [
            'user_id' => $user->id,
            'store_id' => $store->id,
            'name' => 'Yönetim Sabah Özeti',
            'frequency' => MarketplaceReportSubscription::FREQUENCY_WEEKLY,
            'send_time' => '09:15',
        ]);

        Mail::assertSent(MarketplaceReportDigestMail::class, 2);
        $this->assertSame(2, MarketplaceReportDigestRun::query()->where('user_id', $user->id)->where('status', 'sent')->count());
    }

    public function test_route_is_protected_by_feature_flag(): void
    {
        $user = $this->createUser();
        $this->bindDigestService();
        $this->actingAs($user);

        config()->set('marketplace.features.report_digest_enabled', false);
        $this->get('/marketplace-report-digests')->assertNotFound();

        config()->set('marketplace.features.report_digest_enabled', true);
        $this->get('/marketplace-report-digests')
            ->assertOk()
            ->assertSee('Günlük ve haftalık kâr özetleri');
    }

    protected function bindDigestService(): MarketplaceReportDigestService
    {
        $profitCenter = Mockery::mock(MarketplaceProfitCenterQueryService::class);
        $profitCenter->shouldReceive('summary')->zeroOrMoreTimes()->andReturn([
            'gross_revenue' => 1000.0,
            'profit_value' => 180.0,
            'profit_margin_percent' => 18.0,
            'net_receivable' => 820.0,
            'total_orders' => 4,
            'loss_order_count' => 1,
            'finance_waiting_order_count' => 0,
        ]);
        $profitCenter->shouldReceive('executiveCommandSummary')->zeroOrMoreTimes()->andReturn([
            'score' => 76.5,
            'score_label' => 'Kontrollü',
            'primary_action' => ['label' => 'Kâr Merkezi'],
        ]);
        $profitCenter->shouldReceive('priorityRecommendations')->zeroOrMoreTimes()->andReturn([
            [
                'key' => 'loss_orders',
                'label' => 'Zarar baskısını kapat',
                'description' => 'Negatif kâr veren siparişleri kontrol edin.',
                'impact' => 180.0,
                'route' => 'mp.profit-center',
            ],
        ]);
        $profitCenter->shouldReceive('marketplaceBreakdown')->zeroOrMoreTimes()->andReturn([
            [
                'label' => 'Trendyol',
                'marketplace' => 'trendyol',
                'order_count' => 4,
                'profit_value' => 180.0,
                'profit_margin_percent' => 18.0,
            ],
        ]);
        $profitCenter->shouldReceive('costReadiness')->zeroOrMoreTimes()->andReturn([
            'total_lines' => 10,
            'ready_lines' => 8,
            'ready_percent' => 80.0,
        ]);

        $riskSignals = Mockery::mock(MarketplaceRiskSignalService::class);
        $riskSignals->shouldReceive('dashboard')->zeroOrMoreTimes()->andReturn([
            'summary' => [
                'open_count' => 2,
                'critical_count' => 1,
                'warning_count' => 1,
                'impact_total' => 250.0,
                'risk_score' => 72.0,
                'risk_score_label' => 'Kontrollü',
            ],
            'priority_actions' => [
                [
                    'title' => 'Maliyet hazırlığını tamamla',
                    'description' => 'Eksik maliyet satırlarını tamamlayın.',
                ],
            ],
        ]);

        $campaigns = Mockery::mock(CampaignDecisionCenterQueryService::class);
        $campaigns->shouldReceive('profitCenterImpact')->zeroOrMoreTimes()->andReturn([
            'potential_profit' => 55.0,
            'risk_exposure' => 20.0,
            'has_reports' => true,
        ]);

        $service = new MarketplaceReportDigestService($profitCenter, $riskSignals, $campaigns);
        $this->app->instance(MarketplaceReportDigestService::class, $service);

        return $service;
    }

    protected function createSubscription(User $user, array $overrides = []): MarketplaceReportSubscription
    {
        return MarketplaceReportSubscription::query()->create(array_merge([
            'user_id' => $user->id,
            'name' => 'Pazaryeri Kâr Özeti',
            'frequency' => MarketplaceReportSubscription::FREQUENCY_DAILY,
            'channels_json' => ['email'],
            'recipients_json' => [$user->email],
            'filters_json' => [],
            'sections_json' => ['profit', 'risk', 'campaign', 'marketplace', 'actions'],
            'enabled' => true,
            'send_time' => '08:30',
            'timezone' => 'Europe/Istanbul',
            'next_run_at' => now()->subMinute(),
        ], $overrides));
    }

    protected function createUser(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
    }
}
