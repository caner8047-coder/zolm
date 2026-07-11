<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AccountingPilotFeedback;
use App\Services\Accounting\AccountingPilotBacklogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\TestCase;

class AccountingPilotBacklogTest extends TestCase
{
    use RefreshDatabase;

    // ─── yardımcı: feedback factory ─────────────────────────────────────────

    protected function makeFeedback(User $user, array $attrs = []): AccountingPilotFeedback
    {
        return AccountingPilotFeedback::create(array_merge([
            'user_id'       => $user->id,
            'actor_user_id' => $user->id,
            'module'        => 'Stock',
            'type'          => 'bug',
            'severity'      => 'medium',
            'status'        => 'open',
            'title'         => 'Test Feedback',
        ], $attrs));
    }

    // ─── testler ─────────────────────────────────────────────────────────────

    public function test_backlog_zero_state(): void
    {
        $service = app(AccountingPilotBacklogService::class);
        $summary = $service->summary();

        $this->assertSame(0, $summary['total_open']);
        $this->assertSame(0, $summary['fix_now_count']);
        $this->assertSame(0, $summary['fix_next_count']);
        $this->assertSame(0, $summary['watch_count']);
        $this->assertSame(0, $summary['document_count']);
    }

    public function test_critical_bug_scores_fix_now(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->makeFeedback($user, [
            'severity' => 'critical',
            'type'     => 'bug',
            'module'   => 'Stock',   // yüksek riskli modül
        ]);

        $service = app(AccountingPilotBacklogService::class);
        $items   = $service->build($user->id);

        $this->assertNotEmpty($items);
        $this->assertGreaterThanOrEqual(85, $items[0]['priority_score']);
        $this->assertSame('fix_now', $items[0]['recommended_action']);

        // CLI exit kodu 1 olmalı
        $exitCode = Artisan::call('accounting:pilot-backlog', ['--user' => $user->id, '--json' => true]);
        $this->assertSame(1, $exitCode);
    }

    public function test_high_feedback_scores_fix_next(): void
    {
        $user = User::factory()->create();
        $this->makeFeedback($user, ['severity' => 'high', 'type' => 'ux', 'module' => 'Reports']);

        $service = app(AccountingPilotBacklogService::class);
        $items   = $service->build($user->id);

        $this->assertSame('fix_next', $items[0]['recommended_action']);
    }

    public function test_medium_feedback_scores_watch(): void
    {
        $user = User::factory()->create();
        $this->makeFeedback($user, ['severity' => 'medium', 'type' => 'question', 'module' => 'UI']);

        $service = app(AccountingPilotBacklogService::class);
        $items   = $service->build($user->id);

        $this->assertSame('watch', $items[0]['recommended_action']);
    }

    public function test_low_question_scores_document(): void
    {
        $user = User::factory()->create();
        $this->makeFeedback($user, ['severity' => 'low', 'type' => 'question', 'module' => 'UI']);

        $service = app(AccountingPilotBacklogService::class);
        $items   = $service->build($user->id);

        $this->assertSame('document', $items[0]['recommended_action']);
    }

    public function test_backlog_is_tenant_isolated(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->makeFeedback($user1, ['severity' => 'high', 'title' => 'User1 Bug']);

        $service  = app(AccountingPilotBacklogService::class);
        $summary1 = $service->summary($user1->id);
        $summary2 = $service->summary($user2->id);

        $this->assertSame(1, $summary1['total_open']);
        $this->assertSame(0, $summary2['total_open']);
    }

    public function test_age_bonus_is_applied(): void
    {
        $user = User::factory()->create();

        // Yeni feedback — hiç yaş bonusu yok
        $fresh = $this->makeFeedback($user, [
            'severity' => 'medium',
            'type'     => 'question',
            'module'   => 'UI',
        ]);

        // 8 gün eski feedback — yaş bonusu +10 almalı
        $old = $this->makeFeedback($user, [
            'severity' => 'medium',
            'type'     => 'question',
            'module'   => 'UI',
            'title'    => 'Old Feedback',
        ]);
        $old->forceFill(['created_at' => now()->subDays(8)])->saveQuietly();
        $old->refresh();

        $service = app(AccountingPilotBacklogService::class);

        $freshScore = $service->priorityScore($fresh);
        $oldScore   = $service->priorityScore($old);

        $this->assertGreaterThan($freshScore, $oldScore);
    }

    public function test_command_json_is_parseable(): void
    {
        Artisan::call('accounting:pilot-backlog', ['--json' => true]);
        $output = Artisan::output();
        $data   = json_decode($output, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('items', $data);
    }

    public function test_pilot_center_renders_backlog_summary(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user);

        Livewire::test(\App\Livewire\Accounting\PilotCenter::class)
            ->assertStatus(200)
            ->assertSee('Fix Now')
            ->assertSee('Fix Next');
    }

    public function test_backlog_document_exists_and_has_sections(): void
    {
        $this->assertFileExists(base_path('docs/accounting-pilot-fix-sprint-backlog.md'));

        $content = file_get_contents(base_path('docs/accounting-pilot-fix-sprint-backlog.md'));

        $this->assertStringContainsString('Backlog Özeti', $content);
        $this->assertStringContainsString('Öncelik Kuralları', $content);
        $this->assertStringContainsString('P23 Hotfix Adayları', $content);
        $this->assertStringContainsString('P24 Sprint Adayları', $content);
    }
}
