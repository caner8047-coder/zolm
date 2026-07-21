<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Leave\Enums\LeaveTransactionType;
use App\Modules\Hr\Leave\Models\HrLeaveTransaction;
use App\Modules\Hr\Leave\Models\HrLeaveType;
use App\Modules\Hr\Leave\Services\LeaveBalanceService;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LeaveBalanceLedgerTest extends TestCase
{
    use RefreshHrDatabase;

    private LegalEntity $tenant;
    private HrEmployee $employee;
    private HrLeaveType $leaveType;

    protected function setUp(): void
    {
        parent::setUp();
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $roleId = DB::table('roles')->where('slug', 'hr_admin')->value('id');
        $user = User::factory()->create(['role_id' => $roleId]);
        $this->actingAs($user);
        $this->tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '1111111111', 'is_active' => true]);
        app(TenantContext::class)->set($this->tenant);
        $this->employee = HrEmployee::withoutGlobalScope('tenant')->create(['legal_entity_id' => $this->tenant->id, 'user_id' => $user->id, 'employee_number' => 'E001', 'national_id_encrypted' => 'enc', 'national_id_hash' => 'hash-1', 'national_id_last_four' => '0001', 'first_name' => 'Test', 'last_name' => 'User', 'status' => 'active']);
        $this->leaveType = HrLeaveType::create(['legal_entity_id' => $this->tenant->id, 'code' => 'ANNUAL', 'name' => 'Yıllık İzin', 'unit' => 'day']);
    }

    public function test_ledger_recalculates_balance_and_is_idempotent(): void
    {
        $service = app(LeaveBalanceService::class);
        $service->record($this->employee, $this->leaveType, LeaveTransactionType::Accrual, 14, 'test_accrual', 1, 2026);
        $balance = $service->record($this->employee, $this->leaveType, LeaveTransactionType::Usage, -2, 'test_request', 1, 2026);
        $sameBalance = $service->record($this->employee, $this->leaveType, LeaveTransactionType::Usage, -2, 'test_request', 1, 2026);

        $this->assertSame('12.00', $balance->remaining_amount);
        $this->assertSame('12.00', $sameBalance->remaining_amount);
        $this->assertSame(2, HrLeaveTransaction::count());
    }

    public function test_other_tenant_employee_cannot_receive_transaction(): void
    {
        $other = LegalEntity::create(['user_id' => User::factory()->create()->id, 'name' => 'Other', 'tax_number' => '2222222222', 'is_active' => true]);
        $employee = HrEmployee::withoutGlobalScope('tenant')->create(['legal_entity_id' => $other->id, 'employee_number' => 'E002', 'national_id_encrypted' => 'enc', 'national_id_hash' => 'hash-2', 'national_id_last_four' => '0002', 'first_name' => 'Other', 'last_name' => 'User', 'status' => 'active']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(LeaveBalanceService::class)->record($employee, $this->leaveType, LeaveTransactionType::Accrual, 1, 'test', 2, 2026);
    }
}
