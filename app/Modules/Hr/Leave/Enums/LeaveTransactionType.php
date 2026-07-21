<?php

namespace App\Modules\Hr\Leave\Enums;

enum LeaveTransactionType: string
{
    case Accrual = 'accrual';
    case Carryover = 'carryover';
    case Adjustment = 'adjustment';
    case Usage = 'usage';
    case Cancellation = 'cancellation';
}
