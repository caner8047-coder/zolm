<?php

namespace App\Modules\Hr\Expense\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Expense\Enums\ExpenseStatus;
use App\Modules\Hr\Expense\Models\HrExpense;
use App\Modules\Hr\Expense\Models\HrExpenseStatusHistory;
use Illuminate\Support\Facades\DB;

class CancelExpenseAction
{
    public function __construct(private HrAuditService $audit) {}

    public function execute(HrExpense $expense): HrExpense
    {
        abort_unless($expense->legal_entity_id === app(TenantContext::class)->getId(), 404);
        $isOwner = $expense->employee()->where('user_id', auth()->id())->exists();
        abort_unless($isOwner || auth()->user()?->hasHrPermission('hr.expenses.approve'), 403);
        abort_unless(in_array($expense->status, [ExpenseStatus::PendingManager, ExpenseStatus::PendingHr], true), 422, 'Bu masraf iptal edilemez.');
        return DB::transaction(function () use ($expense) {
            $from = $expense->status;
            $expense->update(['status' => ExpenseStatus::Cancelled, 'decided_by' => auth()->id(), 'decided_at' => now()]);
            HrExpenseStatusHistory::create(['legal_entity_id' => $expense->legal_entity_id, 'expense_id' => $expense->id, 'from_status' => $from, 'to_status' => ExpenseStatus::Cancelled, 'note' => 'Talep iptal edildi.', 'acted_by' => auth()->id(), 'created_at' => now()]);
            $this->audit->log('expense_cancelled', $expense, ['status' => $from->value], ['status' => ExpenseStatus::Cancelled->value]);
            return $expense->fresh();
        });
    }
}
