<?php

namespace Tests\Feature\Hr;

use App\Models\HrLicense;
use App\Models\LegalEntity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Hr\RefreshHrDatabase;
use Tests\TestCase;

class DocumentLicenseMiddlewareTest extends TestCase
{
    use RefreshHrDatabase;

    private function hrAdminUser(): User
    {
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $user = User::factory()->create(['role' => 'admin']);
        $adminRole = DB::table('roles')->where('slug', 'hr_admin')->first();
        DB::table('model_has_roles')->insert(['role_id' => $adminRole->id, 'model_id' => $user->id, 'model_type' => User::class]);
        return $user;
    }

    public function test_documents_route_blocked_without_license(): void
    {
        $user = $this->hrAdminUser();
        LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '1111111111', 'is_active' => true]);
        $this->actingAs($user);
        $this->get('/hr/documents')->assertStatus(403);
    }

    public function test_documents_route_allowed_with_license(): void
    {
        $user = $this->hrAdminUser();
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '2222222222', 'is_active' => true]);
        HrLicense::create(['legal_entity_id' => $tenant->id, 'module_key' => 'personel', 'is_active' => true]);
        $this->actingAs($user);
        $this->get('/hr/documents')->assertStatus(200);
    }
}
