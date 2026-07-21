<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Overtime\Actions\CreateOvertimeRequestAction;
use App\Modules\Hr\Overtime\Actions\CancelOvertimeRequestAction;
use App\Modules\Hr\Overtime\Actions\DecideOvertimeRequestAction;
use App\Modules\Hr\Overtime\Enums\OvertimeRequestStatus;
use App\Modules\Hr\Overtime\Models\HrOvertimeType;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OvertimeWorkflowTest extends TestCase
{
    use RefreshHrDatabase;

    public function test_overtime_request_uses_two_stage_approval(): void
    {
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $roleId = DB::table('roles')->where('slug', 'hr_admin')->value('id'); $user = User::factory()->create(['role_id' => $roleId]); $this->actingAs($user);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '5555555555', 'is_active' => true]); app(TenantContext::class)->set($tenant);
        $employee = HrEmployee::withoutGlobalScope('tenant')->create(['legal_entity_id' => $tenant->id, 'user_id' => $user->id, 'employee_number' => 'O001', 'national_id_encrypted' => 'enc', 'national_id_hash' => 'overtime-h1', 'national_id_last_four' => '0001', 'first_name' => 'Test', 'last_name' => 'Çalışan', 'status' => 'active']);
        $type = HrOvertimeType::create(['legal_entity_id' => $tenant->id, 'code' => 'NORMAL', 'name' => 'Normal Fazla Mesai', 'multiplier' => 1.5, 'annual_limit_minutes' => 600]);
        $request = app(CreateOvertimeRequestAction::class)->execute($employee, $type, now()->addDay()->toDateString(), '18:00', '20:00', 'Sipariş yoğunluğu', productionOrderReference: 'URETIM-42');
        $this->assertSame(120, $request->requested_minutes);
        $this->assertSame(OvertimeRequestStatus::PendingManager, $request->status);
        $request = app(DecideOvertimeRequestAction::class)->approve($request, note: 'Yönetici uygun buldu');
        $this->assertSame(OvertimeRequestStatus::PendingHr, $request->status);
        $request = app(DecideOvertimeRequestAction::class)->approve($request, 90, 'İK 90 dakika onayladı');
        $this->assertSame(OvertimeRequestStatus::Approved, $request->status);
        $this->assertSame(90, $request->approved_minutes);
    }

    public function test_employee_can_cancel_own_pending_request(): void
    {
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $roleId = DB::table('roles')->where('slug', 'hr_admin')->value('id'); $user = User::factory()->create(['role_id' => $roleId]); $this->actingAs($user);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Cancel', 'tax_number' => '8888888888', 'is_active' => true]); app(TenantContext::class)->set($tenant);
        $employee = HrEmployee::withoutGlobalScope('tenant')->create(['legal_entity_id' => $tenant->id, 'user_id' => $user->id, 'employee_number' => 'O002', 'national_id_encrypted' => 'enc', 'national_id_hash' => 'overtime-h2', 'national_id_last_four' => '0002', 'first_name' => 'İptal', 'last_name' => 'Test', 'status' => 'active']);
        $type = HrOvertimeType::create(['legal_entity_id' => $tenant->id, 'code' => 'IPTAL', 'name' => 'İptal Testi']);
        $request = app(CreateOvertimeRequestAction::class)->execute($employee, $type, now()->addDay()->toDateString(), '18:00', '19:00', 'Test');
        $cancelled = app(CancelOvertimeRequestAction::class)->execute($request->load('employee'));
        $this->assertSame(OvertimeRequestStatus::Cancelled, $cancelled->status);
    }
}
