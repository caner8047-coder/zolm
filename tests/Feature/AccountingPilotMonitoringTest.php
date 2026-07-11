<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AccountingPilotFeedback;
use App\Models\AccountingPilotHealthSnapshot;
use App\Services\Accounting\AccountingPilotMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\TestCase;

class AccountingPilotMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitoring_summary_returns_zero_state(): void
    {
        $service = app(AccountingPilotMonitoringService::class);
        $summary = $service->summary();

        $this->assertSame(0, $summary['open_feedback_count']);
        $this->assertSame(0, $summary['critical_feedback_count']);
        $this->assertSame(0, $summary['high_feedback_count']);
        $this->assertSame('unknown', $summary['latest_health_status']);
        $this->assertSame('proceed', $summary['pilot_decision']);
    }

    public function test_monitoring_decision_blocks_on_critical_feedback(): void
    {
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        AccountingPilotFeedback::create([
            'user_id' => $user->id,
            'actor_user_id' => $user->id,
            'module' => 'Stock',
            'type' => 'bug',
            'severity' => 'critical',
            'status' => 'open',
            'title' => 'Critical Hata',
        ]);

        $exitCode = Artisan::call('accounting:pilot-monitoring-report', ['--user' => $user->id, '--json' => true]);
        $this->assertSame(1, $exitCode);

        $output = Artisan::output();
        $data = json_decode($output, true);
        $this->assertSame('blocked', $data['decision']['status']);
    }

    public function test_monitoring_decision_blocks_on_failed_health_snapshot(): void
    {
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        AccountingPilotHealthSnapshot::create([
            'user_id' => $user->id,
            'run_uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'status' => 'failed',
            'score' => 70,
            'failed_count' => 3,
            'warning_count' => 0,
            'checks_json' => '[]',
        ]);

        $service = app(AccountingPilotMonitoringService::class);
        $decision = $service->decision($user->id);

        $this->assertSame('blocked', $decision['status']);
    }

    public function test_monitoring_decision_proceeds_with_fixes_on_high_feedback(): void
    {
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        AccountingPilotFeedback::create([
            'user_id' => $user->id,
            'actor_user_id' => $user->id,
            'module' => 'Sales',
            'type' => 'ux',
            'severity' => 'high',
            'status' => 'open',
            'title' => 'High Bug',
        ]);

        $exitCode = Artisan::call('accounting:pilot-monitoring-report', ['--user' => $user->id, '--json' => true]);
        $this->assertSame(0, $exitCode);

        $output = Artisan::output();
        $data = json_decode($output, true);
        $this->assertSame('proceed_with_fixes', $data['decision']['status']);
    }

    public function test_monitoring_decision_proceeds_with_fixes_on_health_warning(): void
    {
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        AccountingPilotHealthSnapshot::create([
            'user_id' => $user->id,
            'run_uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'status' => 'warning',
            'score' => 95,
            'failed_count' => 0,
            'warning_count' => 2,
            'checks_json' => '[]',
        ]);

        $service = app(AccountingPilotMonitoringService::class);
        $decision = $service->decision($user->id);

        $this->assertSame('proceed_with_fixes', $decision['status']);
    }

    public function test_monitoring_report_json_is_parseable(): void
    {
        Artisan::call('accounting:pilot-monitoring-report', ['--json' => true]);
        $output = Artisan::output();
        $data = json_decode($output, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('feedback_breakdown', $data);
        $this->assertArrayHasKey('health_trend', $data);
        $this->assertArrayHasKey('decision', $data);
    }

    public function test_monitoring_is_tenant_isolated(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        AccountingPilotFeedback::create([
            'user_id' => $user1->id,
            'actor_user_id' => $user1->id,
            'module' => 'Stock',
            'type' => 'bug',
            'severity' => 'high',
            'status' => 'open',
            'title' => 'User1 Bug',
        ]);

        $service = app(AccountingPilotMonitoringService::class);
        $sum1 = $service->summary($user1->id);
        $sum2 = $service->summary($user2->id);

        $this->assertSame(1, $sum1['open_feedback_count']);
        $this->assertSame(0, $sum2['open_feedback_count']);
    }

    public function test_health_trend_limit_is_capped(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 60; $i++) {
            AccountingPilotHealthSnapshot::create([
                'user_id' => $user->id,
                'run_uuid' => \Illuminate\Support\Str::uuid()->toString(),
                'status' => 'passed',
                'score' => 100,
                'failed_count' => 0,
                'warning_count' => 0,
                'checks_json' => '[]',
            ]);
        }

        $service = app(AccountingPilotMonitoringService::class);
        $trend = $service->healthTrend($user->id, 100);

        $this->assertCount(50, $trend);
    }

    public function test_pilot_center_renders_monitoring_decision_card(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $this->actingAs($user);

        Livewire::test(\App\Livewire\Accounting\PilotCenter::class)
            ->assertStatus(200)
            ->assertSee('Pilot Kararı')
            ->assertSee('PROCEED');
    }

    public function test_monitoring_document_exists_and_has_required_sections(): void
    {
        $this->assertFileExists(base_path('docs/accounting-pilot-monitoring-report.md'));

        $content = file_get_contents(base_path('docs/accounting-pilot-monitoring-report.md'));
        $this->assertStringContainsString('Summary', $content);
        $this->assertStringContainsString('Feedback Breakdown', $content);
        $this->assertStringContainsString('Health Trend', $content);
        $this->assertStringContainsString('Pilot Kararı', $content);
    }
}
