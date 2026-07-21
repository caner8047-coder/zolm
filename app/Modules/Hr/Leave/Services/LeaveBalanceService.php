<?php

namespace App\Modules\Hr\Leave\Services;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Leave\Enums\LeaveTransactionType;
use App\Modules\Hr\Leave\Models\HrLeaveBalance;
use App\Modules\Hr\Leave\Models\HrLeaveRequest;
use App\Modules\Hr\Leave\Models\HrLeaveTransaction;
use App\Modules\Hr\Leave\Models\HrLeaveType;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Support\Facades\DB;

class LeaveBalanceService
{
    public function balanceFor(HrEmployee $employee, HrLeaveType $leaveType, int $year): HrLeaveBalance
    {
        $this->assertTenant($employee, $leaveType);

        return HrLeaveBalance::withoutGlobalScope('tenant')->firstOrCreate([
            'legal_entity_id' => app(TenantContext::class)->getId(),
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'period_year' => $year,
        ]);
    }

    public function record(HrEmployee $employee, HrLeaveType $leaveType, LeaveTransactionType $type, float $amount, string $sourceType, int $sourceId, int $year, ?HrLeaveRequest $request = null, ?string $note = null): HrLeaveBalance
    {
        $this->assertTenant($employee, $leaveType);

        return DB::transaction(function () use ($employee, $leaveType, $type, $amount, $sourceType, $sourceId, $year, $request, $note) {
            $existing = HrLeaveTransaction::withoutGlobalScope('tenant')
                ->where('legal_entity_id', app(TenantContext::class)->getId())
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->where('transaction_type', $type)
                ->first();

            if (!$existing) {
                HrLeaveTransaction::create([
                    'legal_entity_id' => app(TenantContext::class)->getId(),
                    'employee_id' => $employee->id,
                    'leave_type_id' => $leaveType->id,
                    'leave_request_id' => $request?->id,
                    'period_year' => $year,
                    'transaction_type' => $type,
                    'amount' => $amount,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'note' => $note,
                    'created_by' => auth()->id(),
                ]);
            }

            $balance = $this->balanceFor($employee, $leaveType, $year);
            $transactions = HrLeaveTransaction::withoutGlobalScope('tenant')
                ->where('legal_entity_id', app(TenantContext::class)->getId())
                ->where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->where('period_year', $year);

            $sum = fn (array $types): float => (float) (clone $transactions)->whereIn('transaction_type', $types)->sum('amount');
            $entitled = $sum([LeaveTransactionType::Accrual->value]);
            $carried = $sum([LeaveTransactionType::Carryover->value]);
            $adjustment = $sum([LeaveTransactionType::Adjustment->value]);
            $used = abs($sum([LeaveTransactionType::Usage->value]));
            $remaining = $entitled + $carried + $adjustment - $used + $sum([LeaveTransactionType::Cancellation->value]);

            $balance->update(['entitled_amount' => $entitled, 'carried_amount' => $carried, 'adjustment_amount' => $adjustment, 'used_amount' => $used, 'remaining_amount' => $remaining]);

            return $balance->fresh();
        });
    }

    private function assertTenant(HrEmployee $employee, HrLeaveType $leaveType): void
    {
        $tenantId = app(TenantContext::class)->getId();
        abort_unless($employee->legal_entity_id === $tenantId && $leaveType->legal_entity_id === $tenantId, 422, 'Çalışan veya izin türü başka bir tüzel kişiliğe ait.');
    }
}
