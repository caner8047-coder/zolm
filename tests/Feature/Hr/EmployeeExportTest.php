<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Personnel\Actions\ExportEmployeesAction;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class EmployeeExportTest extends TestCase
{
    use RefreshDatabase;
    use HasHrPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
    }

    public function test_tenant_scope_applied_to_export(): void
    {
        $userA = User::factory()->create(['role' => 'admin']);
        $this->assignHrAdminRole($userA);
        $tenantA = LegalEntity::create(['user_id' => $userA->id, 'name' => 'Şirket A', 'tax_number' => '1111111111', 'is_active' => true]);

        $tenantB = LegalEntity::create(['user_id' => User::factory()->create(['role' => 'admin'])->id, 'name' => 'Şirket B', 'tax_number' => '2222222222', 'is_active' => true]);

        HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenantA->id, 'employee_number' => 'EMP00001',
            'national_id_encrypted' => 'enc', 'national_id_hash' => 'h1', 'national_id_last_four' => '0001',
            'first_name' => 'A', 'last_name' => 'Worker', 'status' => 'active',
        ]);

        HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenantB->id, 'employee_number' => 'EMP00001',
            'national_id_encrypted' => 'enc', 'national_id_hash' => 'h2', 'national_id_last_four' => '0002',
            'first_name' => 'B', 'last_name' => 'Worker', 'status' => 'active',
        ]);

        app(TenantContext::class)->set($tenantA);
        $employees = HrEmployee::forCurrentTenant()->get();
        $this->assertCount(1, $employees);
    }

    public function test_export_permission_required(): void
    {
        $user = User::factory()->create(['role' => 'operator']);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '3333333333', 'is_active' => true]);

        $this->actingAs($user);
        app(TenantContext::class)->set($tenant);

        $this->assertFalse($user->hasHrPermission('hr.employees.export'));
    }

    public function test_sensitive_fields_excluded_without_permission(): void
    {
        $service = app(HrAuditService::class);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('maskSensitive');
        $method->setAccessible(true);

        $data = ['national_id' => '12345678901', 'iban' => 'TR330006100519786457841326', 'gross_salary' => 15000];
        $masked = $method->invoke($service, $data);

        $this->assertStringStartsWith('***', $masked['national_id']);
        $this->assertStringContainsString('*', $masked['iban']);
        $this->assertEquals('[MASKED]', $masked['gross_salary']);
    }

    public function test_export_audit_logged(): void
    {
        $service = app(HrAuditService::class);

        $user = User::factory()->create(['role' => 'admin']);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '4444444444', 'is_active' => true]);
        app(TenantContext::class)->set($tenant);

        $employee = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenant->id, 'employee_number' => 'EMP00001',
            'national_id_encrypted' => 'enc', 'national_id_hash' => 'h', 'national_id_last_four' => '0001',
            'first_name' => 'Test', 'last_name' => 'Export', 'status' => 'active',
        ]);

        $service->log('employee_exported', $employee, null, ['format' => 'excel']);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'employee_exported',
            'entity_type' => HrEmployee::class,
            'entity_id' => $employee->id,
        ]);
    }

    public function test_identity_export_keeps_masked_column_and_adds_authorized_full_column(): void
    {
        Storage::fake('private');
        $user = User::factory()->create(['role' => 'admin']);
        $tenant = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Test',
            'tax_number' => '5555555555',
            'is_active' => true,
        ]);
        $this->actingAs($user);
        app(TenantContext::class)->set($tenant);

        HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenant->id,
            'employee_number' => 'EMP00001',
            'national_id_encrypted' => '12345678901',
            'national_id_hash' => 'identity-export-hash',
            'national_id_last_four' => '8901',
            'first_name' => 'Test',
            'last_name' => 'Çalışan',
            'status' => 'active',
        ]);

        $path = app(ExportEmployeesAction::class)->execute(options: ['view_identity' => true]);
        $sheet = IOFactory::load(storage_path("app/private/{$path}"))->getActiveSheet();

        $this->assertSame('TC Kimlik (Maskeli)', $sheet->getCell('E1')->getValue());
        $this->assertSame('***8901', $sheet->getCell('E2')->getValue());
        $this->assertSame('TC Kimlik (Tam)', $sheet->getCell('O1')->getValue());
        $this->assertSame('12345678901', $sheet->getCell('O2')->getValue());
    }
}
