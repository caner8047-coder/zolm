<?php

namespace Tests\Feature\Hr;

use App\Models\HrLicense;
use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendancePagesTest extends TestCase
{
    use RefreshHrDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $roleId = DB::table('roles')->where('slug', 'hr_admin')->value('id');
        $this->user = User::factory()->create(['role_id' => $roleId]);
        $tenant = LegalEntity::create(['user_id' => $this->user->id, 'name' => 'Test', 'tax_number' => '3333333333', 'is_active' => true]);
        HrLicense::create(['legal_entity_id' => $tenant->id, 'module_key' => 'pdks', 'is_active' => true]);
        HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenant->id, 'user_id' => $this->user->id, 'employee_number' => 'P001',
            'national_id_encrypted' => 'enc', 'national_id_hash' => 'pages-h1', 'national_id_last_four' => '0001',
            'first_name' => 'Pınar', 'last_name' => 'Test', 'status' => 'active',
        ]);
        app(TenantContext::class)->set($tenant);
        $this->actingAs($this->user);
    }

    public function test_attendance_workspaces_render(): void
    {
        $this->get(route('hr.attendance'))->assertOk()->assertSee('PDKS Olay Defteri');
        $this->get(route('hr.attendance.anomalies'))->assertOk()->assertSee('PDKS Anomalileri');
        $this->get(route('hr.settings.attendance-devices'))->assertOk()->assertSee('PDKS Cihazları');
        $this->get(route('hr.my-attendance'))->assertOk()->assertSee('Giriş / Çıkış');
    }
}
