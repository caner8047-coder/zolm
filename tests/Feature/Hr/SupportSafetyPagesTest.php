<?php

namespace Tests\Feature\Hr;

use App\Models\HrLicense;
use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SupportSafetyPagesTest extends TestCase
{
    use RefreshHrDatabase;

    public function test_support_and_safety_pages_render(): void
    {
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $roleId = DB::table('roles')->where('slug', 'hr_admin')->value('id');
        $user = User::factory()->create(['role_id' => $roleId]);
        $this->actingAs($user);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Uyum', 'tax_number' => '6262626262', 'is_active' => true]);
        app(TenantContext::class)->set($tenant);
        HrLicense::create(['legal_entity_id' => $tenant->id, 'module_key' => 'isg', 'is_active' => true]);

        $this->get(route('hr.support'))->assertOk()->assertSee('Çalışan Destek Merkezi');
        $this->get(route('hr.isg'))->assertOk()->assertSee('İSG ve Uyum');
    }
}
