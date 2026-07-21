<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Models\HrIntegrationOutbox;
use App\Modules\Hr\Core\Services\HrIntegrationOutboxService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Integration\Actions\RetryHrIntegrationOutboxAction;
use App\Modules\Hr\Personnel\Actions\CreateEmployeeAction;
use App\Modules\Hr\Personnel\Actions\TerminateEmployeeAction;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class HrIntegrationWorkspaceTest extends TestCase
{
    use RefreshHrDatabase;

    public function test_extended_targets_are_idempotent_and_failed_event_can_be_requeued(): void
    {
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $roleId = DB::table('roles')->where('slug', 'hr_admin')->value('id');
        $user = User::factory()->create(['role_id' => $roleId]);
        $this->actingAs($user);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Entegrasyon', 'tax_number' => '6666666666', 'is_active' => true]);
        app(TenantContext::class)->set($tenant);
        $employee = app(CreateEmployeeAction::class)->execute([
            'national_id' => '10101010101',
            'first_name' => 'Entegrasyon',
            'last_name' => 'Çalışanı',
            'status' => 'active',
        ], [
            'employment_type' => 'full_time',
            'start_date' => today()->toDateString(),
        ]);
        $this->assertDatabaseHas('hr_integration_outbox', ['target' => 'crm', 'event_type' => 'employee_created', 'source_id' => $employee->id]);

        $service = app(HrIntegrationOutboxService::class);
        $first = $service->enqueue('crm', 'employee_synced', $employee, 'employee-sync-'.$employee->id, ['employee_id' => $employee->id, 'status' => 'active']);
        $second = $service->enqueue('crm', 'employee_synced', $employee, 'employee-sync-'.$employee->id, ['status' => 'active', 'employee_id' => $employee->id]);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, HrIntegrationOutbox::withoutGlobalScope('tenant')->where('source_key', 'employee-sync-'.$employee->id)->count());

        $first->update(['status' => 'failed', 'attempt_count' => 2, 'last_error' => 'Bağlantı kurulamadı']);
        $retried = app(RetryHrIntegrationOutboxAction::class)->execute($first->fresh());
        $this->assertSame('pending', $retried->status);
        $this->assertSame(2, $retried->attempt_count);
        $this->assertNull($retried->last_error);

        app(TerminateEmployeeAction::class)->execute($employee, 'Test ayrılışı', today()->toDateString());
        $this->assertDatabaseHas('hr_integration_outbox', ['target' => 'crm', 'event_type' => 'employee_terminated', 'source_id' => $employee->id]);

        $this->get(route('hr.integrations'))->assertOk()->assertSee('İK Entegrasyon Sağlığı')->assertSee('employee_synced');

        $otherTenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Diğer', 'tax_number' => '6767676767', 'is_active' => true]);
        app(TenantContext::class)->set($otherTenant);
        $this->expectException(HttpException::class);
        app(RetryHrIntegrationOutboxAction::class)->execute($first->fresh());
    }
}
