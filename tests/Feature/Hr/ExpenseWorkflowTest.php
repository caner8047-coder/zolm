<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Expense\Actions\CancelExpenseAction;
use App\Modules\Hr\Expense\Actions\CreateExpenseAction;
use App\Modules\Hr\Expense\Actions\DecideExpenseAction;
use App\Modules\Hr\Expense\Actions\MarkExpensePaidAction;
use App\Modules\Hr\Expense\Enums\ExpenseStatus;
use App\Modules\Hr\Expense\Models\HrExpenseCategory;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExpenseWorkflowTest extends TestCase
{
    use RefreshHrDatabase;

    private User $user;
    private LegalEntity $tenant;
    private HrEmployee $employee;
    private HrExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $roleId = DB::table('roles')->where('slug', 'hr_admin')->value('id');
        $this->user = User::factory()->create(['role_id' => $roleId]);
        $this->actingAs($this->user);
        $this->tenant = LegalEntity::create(['user_id' => $this->user->id, 'name' => 'Masraf Test', 'tax_number' => '7878787878', 'is_active' => true]);
        app(TenantContext::class)->set($this->tenant);
        $this->employee = HrEmployee::withoutGlobalScope('tenant')->create(['legal_entity_id' => $this->tenant->id, 'user_id' => $this->user->id, 'employee_number' => 'EXP001', 'national_id_encrypted' => 'enc', 'national_id_hash' => 'expense-hash', 'national_id_last_four' => '0001', 'first_name' => 'Masraf', 'last_name' => 'Çalışanı', 'status' => 'active']);
        $this->category = HrExpenseCategory::create(['legal_entity_id' => $this->tenant->id, 'code' => 'SEYAHAT', 'name' => 'Seyahat', 'requires_receipt' => false, 'default_vat_rate' => 20, 'is_active' => true]);
    }

    public function test_amounts_are_calculated_server_side_and_workflow_is_audited(): void
    {
        $expense = app(CreateExpenseAction::class)->execute($this->employee, $this->category, ['expense_date' => now()->toDateString(), 'net_amount' => 100, 'vat_rate' => 20, 'gross_amount' => 9999, 'description' => 'Müşteri ziyareti', 'project_reference' => 'PRJ-42']);
        $this->assertSame('20.00', $expense->vat_amount);
        $this->assertSame('120.00', $expense->gross_amount);
        $this->assertSame(ExpenseStatus::PendingManager, $expense->status);
        $expense = app(DecideExpenseAction::class)->approve($expense, 'Yönetici onayı');
        $this->assertSame(ExpenseStatus::PendingHr, $expense->status);
        $expense = app(DecideExpenseAction::class)->approve($expense, 'İK onayı');
        $this->assertSame(ExpenseStatus::Approved, $expense->status);
        $this->assertSame('hr-expense-approved-'.$expense->id, $expense->finance_reference);
        $this->assertDatabaseHas('hr_integration_outbox', ['target' => 'finance', 'event_type' => 'expense_approved', 'source_id' => $expense->id]);
        $expense = app(MarkExpensePaidAction::class)->execute($expense, 'BANKA-2026-42');
        $this->assertSame(ExpenseStatus::Paid, $expense->status);
        $this->assertSame('BANKA-2026-42', $expense->payment_reference);
        $this->assertCount(4, $expense->statusHistory()->get());
    }

    public function test_source_key_is_idempotent_and_rejects_payload_drift(): void
    {
        $key = '3f57fe6b-7f53-4b29-9e50-463a8f1e7001';
        $data = ['expense_date' => now()->toDateString(), 'net_amount' => 100, 'description' => 'Taksi'];
        $first = app(CreateExpenseAction::class)->execute($this->employee, $this->category, $data, sourceKey: $key);
        $second = app(CreateExpenseAction::class)->execute($this->employee, $this->category, $data, sourceKey: $key);
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('hr_expenses', 1);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(CreateExpenseAction::class)->execute($this->employee, $this->category, [...$data, 'net_amount' => 101], sourceKey: $key);
    }

    public function test_employee_can_cancel_own_pending_expense(): void
    {
        $expense = app(CreateExpenseAction::class)->execute($this->employee, $this->category, ['expense_date' => now()->toDateString(), 'net_amount' => 50, 'description' => 'Otopark']);
        $cancelled = app(CancelExpenseAction::class)->execute($expense);
        $this->assertSame(ExpenseStatus::Cancelled, $cancelled->status);
    }

    public function test_receipt_is_required_by_category(): void
    {
        $this->category->update(['requires_receipt' => true]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(CreateExpenseAction::class)->execute($this->employee, $this->category->fresh(), ['expense_date' => now()->toDateString(), 'net_amount' => 50, 'description' => 'Konaklama']);
    }

    public function test_cross_tenant_category_cannot_be_used(): void
    {
        $other = LegalEntity::create(['user_id' => $this->user->id, 'name' => 'Diğer', 'tax_number' => '7979797979', 'is_active' => true]);
        $category = HrExpenseCategory::withoutGlobalScope('tenant')->create(['legal_entity_id' => $other->id, 'code' => 'OTHER', 'name' => 'Diğer', 'requires_receipt' => false]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(CreateExpenseAction::class)->execute($this->employee, $category, ['expense_date' => now()->toDateString(), 'net_amount' => 50, 'description' => 'Geçersiz']);
    }
}
