<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use Tests\Feature\Hr\RefreshHrDatabase;
use Tests\TestCase;

class DocumentPermissionTest extends TestCase
{
    use RefreshHrDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
    }

    public function test_normal_admin_cannot_bypass_document_permissions(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->assertFalse($user->hasHrPermission('hr.documents.view'));
        $this->assertFalse($user->hasHrPermission('hr.documents.create'));
        $this->assertFalse($user->hasHrPermission('hr.documents.download'));
        $this->assertFalse($user->hasHrPermission('hr.documents.verify'));
        $this->assertFalse($user->hasHrPermission('hr.documents.view_sensitive'));
        $this->assertFalse($user->hasHrPermission('hr.documents.view_health'));
    }

    public function test_hr_admin_has_all_document_permissions(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $adminRole = \Illuminate\Support\Facades\DB::table('roles')->where('slug', 'hr_admin')->first();
        \Illuminate\Support\Facades\DB::table('model_has_roles')->where('model_id', $user->id)->where('model_type', User::class)->delete();
        \Illuminate\Support\Facades\DB::table('model_has_roles')->insert([
            'role_id' => $adminRole->id, 'model_id' => $user->id, 'model_type' => User::class,
        ]);

        $this->assertTrue($user->hasHrPermission('hr.documents.view'));
        $this->assertTrue($user->hasHrPermission('hr.documents.create'));
        $this->assertTrue($user->hasHrPermission('hr.documents.download'));
        $this->assertTrue($user->hasHrPermission('hr.documents.verify'));
        $this->assertTrue($user->hasHrPermission('hr.documents.view_sensitive'));
        $this->assertTrue($user->hasHrPermission('hr.documents.view_health'));
        $this->assertTrue($user->hasHrPermission('hr.documents.manage_types'));
        $this->assertTrue($user->hasHrPermission('hr.documents.export'));
    }

    public function test_document_types_route_requires_permission(): void
    {
        $user = User::factory()->create(['role' => 'operator']);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '1111111111', 'is_active' => true]);

        $this->actingAs($user);
        app(TenantContext::class)->set($tenant);

        $response = $this->get('/hr/settings/document-types');
        $response->assertStatus(403);
    }

    public function test_documents_route_requires_permission(): void
    {
        $user = User::factory()->create(['role' => 'operator']);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '2222222222', 'is_active' => true]);

        $this->actingAs($user);
        app(TenantContext::class)->set($tenant);

        $response = $this->get('/hr/documents');
        $response->assertStatus(403);
    }
}
