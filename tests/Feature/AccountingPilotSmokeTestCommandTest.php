<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Accounting\AccountingPilotSmokeTestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AccountingPilotSmokeTestCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_smoke_test_command_outputs_json(): void
    {
        $exitCode = Artisan::call('accounting:pilot-smoke-test', ['--json' => true]);
        $this->assertSame(0, $exitCode);
    }

    public function test_smoke_test_fails_for_missing_user(): void
    {
        $exitCode = Artisan::call('accounting:pilot-smoke-test', ['--user' => 999999, '--json' => true]);
        $this->assertSame(1, $exitCode);

        $output = Artisan::output();
        $data = json_decode($output, true);
        $this->assertSame('failed', $data['status']);
    }

    public function test_smoke_test_fails_for_non_admin_user(): void
    {
        $role = \App\Models\Role::create(['name' => 'CRM Sorumlusu', 'slug' => 'crm_sorumlusu']);
        $user = User::factory()->create([
            'is_active' => true,
            'role_id' => $role->id,
            'role' => 'operator',
        ]);
        unset($user->role);
        $user->setRelation('role', $role);

        $exitCode = Artisan::call('accounting:pilot-smoke-test', ['--user' => $user->id, '--json' => true]);
        $this->assertSame(1, $exitCode);

        $output = Artisan::output();
        $data = json_decode($output, true);
        $this->assertSame('failed', $data['status']);
    }

    public function test_smoke_test_passes_for_admin_when_flags_enabled(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        config()->set('marketplace.features.party_core_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $exitCode = Artisan::call('accounting:pilot-smoke-test', ['--user' => $user->id, '--json' => true]);
        $this->assertSame(0, $exitCode);

        $output = Artisan::output();
        $data = json_decode($output, true);
        $this->assertSame('passed', $data['status']);
        $this->assertSame(0, $data['failed_count']);
    }

    public function test_smoke_test_warns_when_flags_disabled(): void
    {
        config()->set('marketplace.features.accounting_enabled', false);
        config()->set('marketplace.features.party_core_enabled', false);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $exitCode = Artisan::call('accounting:pilot-smoke-test', ['--user' => $user->id, '--json' => true]);
        $this->assertSame(0, $exitCode);

        $output = Artisan::output();
        $data = json_decode($output, true);
        $this->assertSame('warning', $data['status']);
        $this->assertGreaterThan(0, $data['warning_count']);
    }

    public function test_smoke_test_reports_required_routes(): void
    {
        Artisan::call('accounting:pilot-smoke-test', ['--json' => true]);
        $output = Artisan::output();
        $data = json_decode($output, true);

        $this->assertArrayHasKey('route_accounting_dashboard', $data['checks']);
        $this->assertArrayHasKey('route_accounting_pilot-center', $data['checks']);
        $this->assertArrayHasKey('route_accounting_parties', $data['checks']);
        $this->assertArrayHasKey('route_accounting_chart-of-accounts', $data['checks']);
        $this->assertArrayHasKey('route_accounting_products', $data['checks']);
        $this->assertArrayHasKey('route_accounting_audit-logs', $data['checks']);
    }

    public function test_smoke_test_missing_route_can_be_simulated(): void
    {
        $service = app(AccountingPilotSmokeTestService::class);
        $service->setRequiredRoutes(['accounting.dashboard', 'non.existing.route.name']);

        $result = $service->run();
        $this->assertSame('failed', $result['status']);
        $this->assertGreaterThan(0, $result['failed_count']);
        $this->assertSame('failed', $result['checks']['route_non_existing_route_name']['status']);
    }
}
