<?php

namespace App\Modules\Hr\Expense\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Expense\Enums\ExpenseStatus;
use App\Modules\Hr\Expense\Models\HrExpense;
use App\Modules\Hr\Expense\Models\HrExpenseStatusHistory;
use Illuminate\Support\Facades\DB;

class MarkExpensePaidAction
{
    public function __construct(private HrAuditService $audit) {}

    public function execute(HrExpense $expense, string $paymentReference): HrExpense
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.expenses.approve'), 403);
        abort_unless($expense->legal_entity_id === app(TenantContext::class)->getId(), 404);
        abort_if(blank($paymentReference), 422, 'Ödeme referansı zorunludur.');
        return DB::transaction(function () use ($expense, $paymentReference) {
            $locked = HrExpense::withoutGlobalScope('tenant')->whereKey($expense->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === ExpenseStatus::Approved, 422, 'Yalnızca onaylanmış masraf ödenebilir.');
            $locked->update(['status' => ExpenseStatus::Paid, 'paid_by' => auth()->id(), 'paid_at' => now(), 'payment_reference' => trim($paymentReference)]);
            HrExpenseStatusHistory::create(['legal_entity_id' => $locked->legal_entity_id, 'expense_id' => $locked->id, 'from_status' => ExpenseStatus::Approved, 'to_status' => ExpenseStatus::Paid, 'note' => trim($paymentReference), 'acted_by' => auth()->id(), 'created_at' => now()]);
            $this->audit->log('expense_paid', $locked, ['status' => ExpenseStatus::Approved->value], ['status' => ExpenseStatus::Paid->value, 'payment_reference' => trim($paymentReference)]);
            return $locked->fresh();
        });
    }
}
