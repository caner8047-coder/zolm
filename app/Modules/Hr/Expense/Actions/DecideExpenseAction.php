<?php

namespace App\Modules\Hr\Expense\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\HrIntegrationOutboxService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Expense\Enums\ExpenseStatus;
use App\Modules\Hr\Expense\Models\HrExpense;
use App\Modules\Hr\Expense\Models\HrExpenseStatusHistory;
use Illuminate\Support\Facades\DB;

class DecideExpenseAction
{
    public function __construct(private HrAuditService $audit, private HrIntegrationOutboxService $outbox) {}

    public function approve(HrExpense $expense, ?string $note = null): HrExpense
    {
        return $this->transition($expense, true, $note);
    }

    public function reject(HrExpense $expense, string $note): HrExpense
    {
        abort_if(blank($note), 422, 'Ret gerekçesi zorunludur.');
        return $this->transition($expense, false, trim($note));
    }

    private function transition(HrExpense $expense, bool $approve, ?string $note): HrExpense
    {
        $this->authorize($expense);
        return DB::transaction(function () use ($expense, $approve, $note) {
            $locked = HrExpense::withoutGlobalScope('tenant')->whereKey($expense->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($locked->status, [ExpenseStatus::PendingManager, ExpenseStatus::PendingHr], true), 422, 'Bu masraf artık onay beklemiyor.');
            $from = $locked->status;
            $to = $approve ? ($from === ExpenseStatus::PendingManager ? ExpenseStatus::PendingHr : ExpenseStatus::Approved) : ExpenseStatus::Rejected;
            $locked->update(['status' => $to, 'decided_by' => auth()->id(), 'decided_at' => now(), 'decision_note' => $note]);
            HrExpenseStatusHistory::create(['legal_entity_id' => $locked->legal_entity_id, 'expense_id' => $locked->id, 'from_status' => $from, 'to_status' => $to, 'note' => $note, 'acted_by' => auth()->id(), 'created_at' => now()]);
            if ($to === ExpenseStatus::Approved) {
                $sourceKey = 'hr-expense-approved-'.$locked->id;
                $this->outbox->enqueue('finance', 'expense_approved', $locked, $sourceKey, [
                    'expense_id' => $locked->id,
                    'employee_id' => $locked->employee_id,
                    'gross_amount' => (string) $locked->gross_amount,
                    'vat_amount' => (string) $locked->vat_amount,
                    'currency' => $locked->currency,
                    'expense_date' => $locked->expense_date->toDateString(),
                    'project_reference' => $locked->project_reference,
                    'order_reference' => $locked->order_reference,
                    'customer_reference' => $locked->customer_reference,
                ]);
                $locked->update(['finance_reference' => $sourceKey]);
            }
            $this->audit->log($approve ? 'expense_approved_step' : 'expense_rejected', $locked, ['status' => $from->value], ['status' => $to->value]);
            return $locked->fresh();
        });
    }

    private function authorize(HrExpense $expense): void
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.expenses.approve'), 403);
        abort_unless($expense->legal_entity_id === app(TenantContext::class)->getId(), 404);
    }
}
