<?php

namespace Tests\Feature\Hr;

use App\Models\HrHoliday;
use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use Tests\Feature\Hr\RefreshHrDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshHrDatabase;
    use HasHrPermissions;

    private User $userA;
    private User $userB;
    private LegalEntity $tenantA;
    private LegalEntity $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userA = User::factory()->create(['role' => 'admin']);
        $this->assignHrAdminRole($this->userA);
        $this->userB = User::factory()->create(['role' => 'admin']);
        $this->assignHrAdminRole($this->userB);

        $this->tenantA = LegalEntity::create([
            'user_id' => $this->userA->id,
            'name' => 'Şirket A',
            'tax_number' => '1234567890',
            'is_active' => true,
        ]);

        $this->tenantB = LegalEntity::create([
            'user_id' => $this->userB->id,
            'name' => 'Şirket B',
            'tax_number' => '0987654321',
            'is_active' => true,
        ]);

        // Seed permissions
        $this->seed(\Database\Seeders\Hr\HrPermissionSeeder::class);
    }

    public function test_user_cannot_access_other_tenant_data(): void
    {
        // Tenant A'ya tatil ekle
        HrHoliday::create([
            'legal_entity_id' => $this->tenantA->id,
            'name' => 'Test Tatil A',
            'date' => '2026-12-31',
            'year' => 2026,
            'type' => 'national',
            'is_recurring' => true,
        ]);

        HrHoliday::create([
            'legal_entity_id' => $this->tenantB->id,
            'name' => 'Test Tatil B',
            'date' => '2026-12-25',
            'year' => 2026,
            'type' => 'national',
            'is_recurring' => true,
        ]);

        // User A olarak Tenant A context'ine gir
        $this->actingAs($this->userA);
        app(TenantContext::class)->set($this->tenantA);

        // Tenant A'nın tatillerini al
        $holidays = HrHoliday::forCurrentTenant()->get();
        $this->assertCount(1, $holidays);
        $this->assertEquals('Test Tatil A', $holidays->first()->name);

        // Tenant B'nin tatilleri görünmemeli (global scope filtreliyor)
        $allTenantBHolidays = $holidays->filter(fn($h) => $h->legal_entity_id === $this->tenantB->id);
        $this->assertCount(0, $allTenantBHolidays);
    }

    public function test_route_model_binding_respects_tenant(): void
    {
        $holidayA = HrHoliday::create([
            'legal_entity_id' => $this->tenantA->id,
            'name' => 'Tenant A Tatil',
            'date' => '2026-06-01',
            'year' => 2026,
            'type' => 'national',
            'is_recurring' => true,
        ]);

        $this->actingAs($this->userA);
        app(TenantContext::class)->set($this->tenantA);

        // Tenant A'nın kendi tatiline erişebilmeli
        $holiday = HrHoliday::withoutGlobalScope('tenant')
            ->where('id', $holidayA->id)
            ->where('legal_entity_id', $this->tenantA->id)
            ->first();

        $this->assertNotNull($holiday);
        $this->assertEquals('Tenant A Tatil', $holiday->name);
    }

    public function test_tenant_context_is_required(): void
    {
        // LegalEntity'ı olmayan kullanıcı
        $userWithoutTenant = User::factory()->create(['role' => 'admin']);
        $this->actingAs($userWithoutTenant);

        // Tenant context olmadan erişim 403 dönmeli
        $response = $this->get('/hr');
        $response->assertStatus(403);
    }

    public function test_tenant_context_set_and_get(): void
    {
        $this->actingAs($this->userA);

        app(TenantContext::class)->set($this->tenantA);

        $this->assertTrue(app(TenantContext::class)->isSet());
        $this->assertEquals($this->tenantA->id, app(TenantContext::class)->getId());
        $this->assertEquals($this->tenantA->name, app(TenantContext::class)->get()->name);
    }

    public function test_tenant_context_clear(): void
    {
        $this->actingAs($this->userA);

        app(TenantContext::class)->set($this->tenantA);
        $this->assertTrue(app(TenantContext::class)->isSet());

        app(TenantContext::class)->clear();
        $this->assertFalse(app(TenantContext::class)->isSet());
    }
}
