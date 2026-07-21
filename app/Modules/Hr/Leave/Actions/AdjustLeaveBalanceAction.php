<?php

namespace App\Modules\Hr\Leave\Actions;

use App\Modules\Hr\Leave\Enums\LeaveTransactionType;
use App\Modules\Hr\Leave\Models\HrLeaveType;
use App\Modules\Hr\Leave\Services\LeaveBalanceService;
use App\Modules\Hr\Personnel\Models\HrEmployee;

class AdjustLeaveBalanceAction
{
    public function __construct(private LeaveBalanceService $balances) {}

    public function execute(HrEmployee $employee, HrLeaveType $type, float $amount, string $note): void
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.leaves.adjust_balance'), 403);
        abort_if($amount == 0.0 || blank($note), 422, 'Sıfır olmayan tutar ve düzeltme gerekçesi zorunludur.');
        $this->balances->record($employee, $type, LeaveTransactionType::Adjustment, $amount, self::class, crc32($employee->id . '|' . $type->id . '|' . now()->format('YmdHis.u')), now()->year, null, $note);
    }
}
