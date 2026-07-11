<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AccountingPilotFeedback;
use App\Models\AccountingPilotHealthSnapshot;
use App\Services\Accounting\AccountingPilotReleaseCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AccountingPilotReleaseCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_runs_without_user_and_returns_json(): void
    {
        $this->artisan('accounting:pilot-release-check', ['--json' => true])
            ->assertExitCode(0)
            ->expectsOutput(null); // JSON olarak direkt output'a yazar
    }

    public function test_invalid_user_id_produces_failure(): void
    {
        $this->artisan('accounting:pilot-release-check', ['--user' => 99999])
            ->assertExitCode(1);
    }

    public function test_non_admin_user_produces_failure(): void
    {
        $role = \App\Models\Role::create(['name' => 'CRM Sorumlusu', 'slug' => 'crm_sorumlusu']);
        $user = User::factory()->create([
            'is_active' => true,
            'role_id' => $role->id,
            'role' => 'operator',
        ]);
        unset($user->role);
        $user->setRelation('role', $role);

        $this->artisan('accounting:pilot-release-check', ['--user' => $user->id])
            ->assertExitCode(1);
    }

    public function test_admin_user_with_passed_snapshot_returns_success(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        // Seeder calistir ki health check gecsin
        Artisan::call('accounting:seed-demo', ['--user' => $user->id]);

        // Health check run
        app(\App\Services\Accounting\AccountingPilotReadinessService::class)->runHealthCheck($user->id);

        $this->artisan('accounting:pilot-release-check', ['--user' => $user->id])
            ->assertExitCode(0);
    }

    public function test_failed_snapshot_causes_release_check_failure(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        // Seeder calistirma (health check failed olsun)
        app(\App\Services\Accounting\AccountingPilotReadinessService::class)->runHealthCheck($user->id);

        $this->artisan('accounting:pilot-release-check', ['--user' => $user->id])
            ->assertExitCode(1);
    }

    public function test_open_critical_feedback_triggers_warning_exit_code_zero(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        // Seeder + Health check
        Artisan::call('accounting:seed-demo', ['--user' => $user->id]);
        app(\App\Services\Accounting\AccountingPilotReadinessService::class)->runHealthCheck($user->id);

        // Açık kritik geri bildirim ekle
        AccountingPilotFeedback::create([
            'user_id' => $user->id,
            'actor_user_id' => $user->id,
            'module' => 'Cariler',
            'type' => 'bug',
            'severity' => 'critical',
            'status' => 'open',
            'title' => 'Critical Hata',
        ]);

        $this->artisan('accounting:pilot-release-check', ['--user' => $user->id])
            ->assertExitCode(0);
    }

    public function test_json_output_is_parsable(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        // Seeder + Health check
        Artisan::call('accounting:seed-demo', ['--user' => $user->id]);
        app(\App\Services\Accounting\AccountingPilotReadinessService::class)->runHealthCheck($user->id);

        Artisan::call('accounting:pilot-release-check', ['--user' => $user->id, '--json' => true]);
        $output = Artisan::output();

        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('failed_count', $data);
        $this->assertArrayHasKey('checks', $data);
    }

    public function test_missing_documents_causes_failure(): void
    {
        $service = app(AccountingPilotReleaseCheckService::class);
        
        // Inject non-existing documents path
        $service->setRequiredDocs(['docs/non-existing-readiness-file.md']);
        
        $result = $service->run();
        $this->assertGreaterThan(0, $result['failed_count']);
        $this->assertSame('failed', $result['status']);
        $this->assertSame('failed', $result['checks']['required_documents']['status']);
    }
}
