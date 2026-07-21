<?php

namespace Tests\Feature\Hr;

use App\Models\HrLicense;
use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExpensePagesTest extends TestCase
{
    use RefreshHrDatabase;

    public function test_expense_pages_render_with_module_license(): void
    {
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $roleId = DB::table('roles')->where('slug', 'hr_admin')->value('id');
        $user = User::factory()->create(['role_id' => $roleId]); $this->actingAs($user);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Sayfa Test', 'tax_number' => '6767676767', 'is_active' => true]); app(TenantContext::class)->set($tenant);
        HrEmployee::withoutGlobalScope('tenant')->create(['legal_entity_id' => $tenant->id, 'user_id' => $user->id, 'employee_number' => 'EXPP01', 'national_id_encrypted' => 'enc', 'national_id_hash' => 'expense-page', 'national_id_last_four' => '0001', 'first_name' => 'Sayfa', 'last_name' => 'Test', 'status' => 'active']);
        HrLicense::create(['legal_entity_id' => $tenant->id, 'module_key' => 'masraf', 'is_active' => true]);
        $this->get(route('hr.expenses'))->assertOk()->assertSee('Masraf Yönetimi');
        $this->get(route('hr.my-expenses'))->assertOk()->assertSee('Masraflarım');
        $this->get(route('hr.settings.expense-categories'))->assertOk()->assertSee('Masraf Kategorileri');
    }
}
