<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitTeamPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
    }

    public function test_view_permission_required_for_unit_list(): void
    {
        $user = User::factory()->create(['role' => 'operator']);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '1111111111', 'is_active' => true]);

        $this->actingAs($user);
        app(TenantContext::class)->set($tenant);

        $response = $this->get('/hr/settings/units');
        $response->assertStatus(403);
    }

    public function test_manage_permission_required_for_unit_create(): void
    {
        $user = User::factory()->create(['role' => 'operator']);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '2222222222', 'is_active' => true]);

        $this->actingAs($user);
        app(TenantContext::class)->set($tenant);

        $response = $this->get('/hr/settings/units/create');
        $response->assertStatus(403);
    }

    public function test_normal_admin_cannot_bypass_org_permission(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->assertFalse($user->hasHrPermission('hr.org_structure.view'));
        $this->assertFalse($user->hasHrPermission('hr.org_structure.manage'));
    }
}
