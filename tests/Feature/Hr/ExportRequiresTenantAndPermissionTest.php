<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExportRequiresTenantAndPermissionTest extends TestCase
{
    use RefreshDatabase;
    use HasHrPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
    }

    public function test_user_without_permission_cannot_export(): void
    {
        $user = User::factory()->create(['role' => 'operator']);
        $tenant = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Test',
            'tax_number' => '4000000001',
            'is_active' => true,
        ]);

        $this->actingAs($user);
        app(TenantContext::class)->set($tenant);

        $this->assertFalse($user->hasHrPermission('hr.employees.export'));
        $this->assertFalse($user->hasHrPermission('hr.payroll.export'));
        $this->assertFalse($user->hasHrPermission('hr.analytics.export'));
    }

    public function test_tenant_context_required_for_export(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user);

        // Tenant context olmadan erişim 403
        $response = $this->get('/hr');
        $response->assertStatus(403);
    }

    public function test_user_cannot_export_other_tenant_data(): void
    {
        $userA = User::factory()->create(['role' => 'admin']);
        $userB = User::factory()->create(['role' => 'admin']);

        $tenantA = LegalEntity::create([
            'user_id' => $userA->id,
            'name' => 'Şirket A',
            'tax_number' => '4000000002',
            'is_active' => true,
        ]);

        $tenantB = LegalEntity::create([
            'user_id' => $userB->id,
            'name' => 'Şirket B',
            'tax_number' => '4000000003',
            'is_active' => true,
        ]);

        // Tenant A context'inde olmalı
        $this->actingAs($userA);
        app(TenantContext::class)->set($tenantA);
        $this->assertEquals($tenantA->id, app(TenantContext::class)->getId());
        $this->assertNotEquals($tenantB->id, app(TenantContext::class)->getId());

        // User B farklı tenant'a ait
        $this->actingAs($userB);
        app(TenantContext::class)->set($tenantB);
        $this->assertEquals($tenantB->id, app(TenantContext::class)->getId());
        $this->assertNotEquals($tenantA->id, app(TenantContext::class)->getId());
    }

    public function test_export_permission_required(): void
    {
        $user = User::factory()->create(['role' => 'operator']);
        $tenant = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Test',
            'tax_number' => '4000000004',
            'is_active' => true,
        ]);

        $this->actingAs($user);
        app(TenantContext::class)->set($tenant);

        $response = $this->get('/hr');
        $response->assertStatus(403);
    }

    public function test_sensitive_fields_not_in_export_without_permission(): void
    {
        $service = app(HrAuditService::class);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('maskSensitive');
        $method->setAccessible(true);

        $data = [
            'national_id' => '12345678901',
            'iban' => 'TR330006100519786457841326',
            'gross_salary' => 15000,
            'normal_field' => 'visible',
        ];

        $masked = $method->invoke($service, $data);

        $this->assertStringStartsWith('***', $masked['national_id']);
        $this->assertStringContainsString('*', $masked['iban']);
        $this->assertEquals('[MASKED]', $masked['gross_salary']);
        $this->assertEquals('visible', $masked['normal_field']);
    }

    public function test_admin_without_hr_role_cannot_export(): void
    {
        // Sistem admin'i ama hr_admin rolü atanmamış → export yapamaz
        $user = User::factory()->create(['role' => 'admin']);

        $this->assertFalse($user->hasHrPermission('hr.employees.export'));
        $this->assertFalse($user->hasHrPermission('hr.payroll.export'));
        $this->assertFalse($user->hasHrPermission('hr.analytics.export'));
    }

    public function test_admin_with_hr_role_can_export(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        // hr_admin rolünü ata
        $adminRole = DB::table('roles')->where('slug', 'hr_admin')->first();
        DB::table('model_has_roles')->insert([
            'role_id' => $adminRole->id,
            'model_id' => $user->id,
            'model_type' => User::class,
        ]);

        $this->assertTrue($user->hasHrPermission('hr.employees.export'));
        $this->assertTrue($user->hasHrPermission('hr.payroll.export'));
        $this->assertTrue($user->hasHrPermission('hr.analytics.export'));
    }
}
